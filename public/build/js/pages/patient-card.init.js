let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let activeFilter = '';

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

document.getElementById('mobile_no-field').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').substring(0, 10);
});

async function loadCards(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/patient-card/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}&active=${activeFilter}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#cardTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No patient cards found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        let statusBadge = raw.active === 'Yes'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        let names = [raw.patient_name1, raw.patient_name2, raw.patient_name3, raw.patient_name4, raw.patient_name5]
            .filter(n => n)
            .map(n => escapeHtml(n))
            .join(', ');

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td class="fw-semibold">${escapeHtml(raw.card_no)}</td>
            <td>${raw.mobile_no ?? '-'}</td>
            <td>${names}</td>
            <td>${statusBadge}</td>
            <td>${raw.created_dt ?? ''}</td>
            <td>
                <a href="#showModal" data-bs-toggle="modal" class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   data-card="${escapeHtml(raw.card_no)}"
                   data-mobile="${raw.mobile_no ?? ''}"
                   data-name1="${escapeHtml(raw.patient_name1 ?? '')}"
                   data-name2="${escapeHtml(raw.patient_name2 ?? '')}"
                   data-name3="${escapeHtml(raw.patient_name3 ?? '')}"
                   data-name4="${escapeHtml(raw.patient_name4 ?? '')}"
                   data-name5="${escapeHtml(raw.patient_name5 ?? '')}"
                   data-active="${raw.active}"
                   data-remarks="${escapeHtml(raw.remarks ?? '')}"
                   title="Edit">
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
        card_no: document.querySelector('#card_no-field').value,
        mobile_no: document.querySelector('#mobile_no-field').value,
        patient_name1: document.querySelector('#patient_name1-field').value,
        patient_name2: document.querySelector('#patient_name2-field').value,
        patient_name3: document.querySelector('#patient_name3-field').value,
        patient_name4: document.querySelector('#patient_name4-field').value,
        patient_name5: document.querySelector('#patient_name5-field').value,
        active: document.querySelector('#active-field').value,
        remarks: document.querySelector('#remarks-field').value
    };

    let url = editId ? `/patient-card/update/${editId}` : '/patient-card/store';

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
            : (result.message ?? 'Unable to save patient card.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Modal.getInstance(document.getElementById('showModal')).hide();

    this.reset();

    loadCards(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();
    document.querySelector('#edit-id').value = '';
    document.querySelector('#modal-title').innerText = 'Add Patient Card';
    document.querySelector('#add-btn').innerText = 'Save Card';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadCards(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadCards(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Patient Card';
        document.querySelector('#add-btn').innerText = 'Update Card';
        document.querySelector('#edit-id').value = editBtn.dataset.id;
        document.querySelector('#card_no-field').value = editBtn.dataset.card;
        document.querySelector('#mobile_no-field').value = editBtn.dataset.mobile;
        document.querySelector('#patient_name1-field').value = editBtn.dataset.name1;
        document.querySelector('#patient_name2-field').value = editBtn.dataset.name2;
        document.querySelector('#patient_name3-field').value = editBtn.dataset.name3;
        document.querySelector('#patient_name4-field').value = editBtn.dataset.name4;
        document.querySelector('#patient_name5-field').value = editBtn.dataset.name5;
        document.querySelector('#active-field').value = editBtn.dataset.active;
        document.querySelector('#remarks-field').value = editBtn.dataset.remarks;
        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete Patient Card?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/patient-card/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) loadCards(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadCards(1);
    }

    if (e.target.id === 'activeFilter') {
        activeFilter = e.target.value;
        loadCards(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadCards(1);
    }, 350);
});

loadCards();
