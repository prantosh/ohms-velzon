let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function sourceMasterLabel(sourceMaster) {

    let labels = {
        instrument: 'Instrument Master',
        kit: 'Kit Master',
        note: 'Note Master',
        microscopy: 'Microscopy Master',
        impression: 'Impression Master'
    };

    return labels[sourceMaster] ?? '';
}

function toggleSourceMasterField() {

    let isSelect = document.querySelector('#input_type-field').value === 'SELECT';

    document.querySelector('#source_master-wrap').style.display = isSelect ? 'block' : 'none';

    if (!isSelect) {
        document.querySelector('#source_master-field').value = '';
    }
}

async function loadFieldTypes(page = 1) {
    currentPage = page;

    const response = await fetch(
        `/test-extra-field-type/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#fieldTypeTable tbody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector('#pagination-info').innerText =
        `Total Records : ${result.pagination.total}`;

    result.data.forEach(raw => {

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>

            <td>
                <input type="checkbox">
            </td>

            <td>${raw.field_name}</td>

            <td>${raw.input_type}</td>

            <td>${sourceMasterLabel(raw.source_master)}</td>

            <td>${raw.sort_order}</td>

            <td>${statusBadge}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   title="Edit">
                    <i class="ri-pencil-fill"></i>
                </a>

                <a href="javascript:void(0)"
                   class="btn btn-sm btn-soft-danger delete-item-btn"
                   data-id="${raw.id}"
                   title="Delete">
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

    let formData = new FormData();

    formData.append('field_name', document.querySelector('#field_name-field').value);
    formData.append('input_type', document.querySelector('#input_type-field').value);
    formData.append('source_master', document.querySelector('#source_master-field').value);
    formData.append('sort_order', document.querySelector('#sort_order-field').value || 0);
    formData.append('status', document.querySelector('#status-field').value);

    let url = '/test-extra-field-type/store';

    if (editId) {
        url = `/test-extra-field-type/update/${editId}`;
    }

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },
        body: formData
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to save extra field.');

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorText
        });

        return;
    }

    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: result.message
    });

    bootstrap.Modal.getInstance(document.getElementById('showModal')).hide();

    this.reset();

    loadFieldTypes(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add Extra Field';
    document.querySelector('#add-btn').innerText = 'Save Extra Field';

    toggleSourceMasterField();
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadFieldTypes(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadFieldTypes(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Extra Field';
        document.querySelector('#add-btn').innerText = 'Update Extra Field';

        let id = editBtn.dataset.id;

        document.querySelector('#edit-id').value = id;

        const response = await fetch(`/test-extra-field-type/edit/${id}`);
        const result = await response.json();

        if (result.status) {
            document.querySelector('#field_name-field').value = result.data.field_name;
            document.querySelector('#input_type-field').value = result.data.input_type;
            document.querySelector('#source_master-field').value = result.data.source_master ?? '';
            document.querySelector('#sort_order-field').value = result.data.sort_order ?? 0;
            document.querySelector('#status-field').value = result.data.status;
            toggleSourceMasterField();
        }

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete this Extra Field?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/test-extra-field-type/delete/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) {
            loadFieldTypes(currentPage);
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadFieldTypes(1);
        return;
    }

    if (e.target.id === 'input_type-field') {
        toggleSourceMasterField();
    }
});

document.getElementById('searchInput').addEventListener('input', function () {

    searchTerm = this.value;
    loadFieldTypes(1);
});

loadFieldTypes();
