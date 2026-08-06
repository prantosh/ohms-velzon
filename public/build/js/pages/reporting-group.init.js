let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function getCheckedItemCodes() {
    return Array.from(document.querySelectorAll('.item-code-checkbox:checked')).map(el => el.value);
}

function setCheckedItemCodes(itemCodes) {
    document.querySelectorAll('.item-code-checkbox').forEach(el => {
        el.checked = itemCodes.includes(el.value);
    });
}

async function loadGroups(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/reporting-group/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#groupTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No reporting groups found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        let itemBadges = raw.item_labels.length
            ? raw.item_labels.map(label => `<span class="badge bg-info-subtle text-info me-1 mb-1">${escapeHtml(label)}</span>`).join('')
            : '<span class="text-muted">No items assigned</span>';

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td class="fw-semibold">${escapeHtml(raw.group_name)}</td>
            <td>${itemBadges}</td>
            <td>${escapeHtml(raw.remarks ?? '-')}</td>
            <td>${statusBadge}</td>
            <td>
                <a href="#showModal" data-bs-toggle="modal" class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}" title="Edit">
                    <i class="ri-pencil-fill"></i>
                </a>
                <a href="javascript:void(0)" class="btn btn-sm btn-soft-danger delete-item-btn" data-id="${raw.id}" title="Delete">
                    <i class="ri-delete-bin-5-fill"></i>
                </a>
            </td>
        </tr>
        `;
    });
}

document.querySelector('.tablelist-form').addEventListener('submit', async function (e) {

    e.preventDefault();

    Swal.fire({
        title: 'Saving....',
        text: 'Please wait',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    let editId = document.querySelector('#edit-id').value;

    let payload = {
        group_name: document.querySelector('#group_name-field').value,
        remarks: document.querySelector('#remarks-field').value,
        status: document.querySelector('#status-field').value,
        item_codes: getCheckedItemCodes(),
    };

    let url = editId ? `/reporting-group/update/${editId}` : '/reporting-group/store';

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to save reporting group.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Modal.getInstance(document.getElementById('showModal')).hide();

    this.reset();

    loadGroups(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();
    document.querySelector('#edit-id').value = '';
    document.querySelector('#modal-title').innerText = 'Add Reporting Group';
    document.querySelector('#add-btn').innerText = 'Save Group';
    setCheckedItemCodes([]);
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadGroups(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadGroups(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        const response = await fetch(`/reporting-group/edit/${editBtn.dataset.id}`);
        const result = await response.json();

        if (!result.status) return;

        let row = result.data;

        document.querySelector('#modal-title').innerText = 'Update Reporting Group';
        document.querySelector('#add-btn').innerText = 'Update Group';
        document.querySelector('#edit-id').value = row.id;
        document.querySelector('#group_name-field').value = row.group_name;
        document.querySelector('#remarks-field').value = row.remarks ?? '';
        document.querySelector('#status-field').value = row.status;
        setCheckedItemCodes(row.item_codes);

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete Reporting Group?',
            text: 'This will remove the group and its item code assignments.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/reporting-group/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) loadGroups(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadGroups(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadGroups(1);
    }, 350);
});

loadGroups();
