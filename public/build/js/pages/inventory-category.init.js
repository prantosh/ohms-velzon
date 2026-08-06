let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function loadCategories(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/inventory-category/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#categoryTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No categories found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${raw.category_name}</td>
            <td>${statusBadge}</td>
            <td>${raw.created_dt ?? ''}</td>
            <td>
                <a href="#showModal" data-bs-toggle="modal" class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}" data-name="${raw.category_name}" data-status="${raw.status}" title="Edit">
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
        category_name: document.querySelector('#category_name-field').value,
        status: document.querySelector('#status-field').value
    };

    let url = editId ? `/inventory-category/update/${editId}` : '/inventory-category/store';

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
            : (result.message ?? 'Unable to save category.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Modal.getInstance(document.getElementById('showModal')).hide();

    this.reset();

    loadCategories(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();
    document.querySelector('#edit-id').value = '';
    document.querySelector('#modal-title').innerText = 'Add Category';
    document.querySelector('#add-btn').innerText = 'Save Category';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadCategories(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadCategories(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Category';
        document.querySelector('#add-btn').innerText = 'Update Category';
        document.querySelector('#edit-id').value = editBtn.dataset.id;
        document.querySelector('#category_name-field').value = editBtn.dataset.name;
        document.querySelector('#status-field').value = editBtn.dataset.status;
        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete Category?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/inventory-category/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) loadCategories(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadCategories(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadCategories(1);
    }, 350);
});

loadCategories();
