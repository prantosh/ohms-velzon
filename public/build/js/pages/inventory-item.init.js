let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let categoryFilter = '';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function loadItems(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/inventory-item/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}&category_id=${categoryFilter}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#itemTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No items found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${raw.item_code}</td>
            <td>${raw.item_name}</td>
            <td>${raw.category_name ?? '-'}</td>
            <td>${raw.uom}</td>
            <td>${Number(raw.current_stock).toFixed(2)}</td>
            <td>${Number(raw.avg_rate).toFixed(2)}</td>
            <td>${statusBadge}</td>
            <td>
                <a href="#showModal" data-bs-toggle="modal" class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   data-name="${raw.item_name}"
                   data-uom="${raw.uom}"
                   data-category="${raw.inventory_category_id ?? ''}"
                   data-status="${raw.status}"
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
        item_name: document.querySelector('#item_name-field').value,
        uom: document.querySelector('#uom-field').value,
        inventory_category_id: document.querySelector('#inventory_category_id-field').value || null,
        opening_stock: document.querySelector('#opening_stock-field').value,
        opening_value: document.querySelector('#opening_value-field').value,
        status: document.querySelector('#status-field').value
    };

    let url = editId ? `/inventory-item/update/${editId}` : '/inventory-item/store';

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
            : (result.message ?? 'Unable to save item.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Modal.getInstance(document.getElementById('showModal')).hide();

    this.reset();

    loadItems(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();
    document.querySelector('#edit-id').value = '';
    document.querySelector('#modal-title').innerText = 'Add Item';
    document.querySelector('#add-btn').innerText = 'Save Item';
    document.querySelector('#openingStockWrap').style.display = 'block';
    document.querySelector('#openingValueWrap').style.display = 'block';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadItems(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadItems(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Item';
        document.querySelector('#add-btn').innerText = 'Update Item';
        document.querySelector('#edit-id').value = editBtn.dataset.id;
        document.querySelector('#item_name-field').value = editBtn.dataset.name;
        document.querySelector('#uom-field').value = editBtn.dataset.uom;
        document.querySelector('#inventory_category_id-field').value = editBtn.dataset.category;
        document.querySelector('#status-field').value = editBtn.dataset.status;

        // Opening stock/value cannot be changed once an item is created (it only sets the initial baseline)
        document.querySelector('#openingStockWrap').style.display = 'none';
        document.querySelector('#openingValueWrap').style.display = 'none';

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete Item?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/inventory-item/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) loadItems(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadItems(1);
    }

    if (e.target.id === 'categoryFilter') {
        categoryFilter = e.target.value;
        loadItems(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadItems(1);
    }, 350);
});

loadItems();
