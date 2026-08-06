let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchText = '';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function loadDestinations(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/ambulance-destination/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchText)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#destinationTable tbody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector('#pagination-info').innerText =
        `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    result.data.forEach((raw, index) => {

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>

            <td>${startSl + index + 1}</td>

            <td>${raw.destination_code}</td>

            <td>${raw.destination_name}</td>

            <td>${Number(raw.fare_ac).toFixed(2)}</td>

            <td>${Number(raw.fare_nonac).toFixed(2)}</td>

            <td>${statusBadge}</td>

            <td>${raw.created_by_name ?? ''}</td>

            <td>${raw.created_dt ?? ''}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   data-code="${raw.destination_code}"
                   data-name="${raw.destination_name}"
                   data-fare-ac="${raw.fare_ac}"
                   data-fare-nonac="${raw.fare_nonac}"
                   data-remarks="${raw.remarks ?? ''}"
                   data-status="${raw.status}"
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

    formData.append('destination_name', document.querySelector('#destination_name-field').value);
    formData.append('fare_ac', document.querySelector('#fare_ac-field').value);
    formData.append('fare_nonac', document.querySelector('#fare_nonac-field').value);
    formData.append('remarks', document.querySelector('#remarks-field').value);
    formData.append('status', document.querySelector('#status-field').value);

    let url = '/ambulance-destination/store';

    if (editId) {
        url = `/ambulance-destination/update/${editId}`;
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
            : (result.message ?? 'Unable to save Ambulance Destination.');

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

    loadDestinations(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add Ambulance Destination';

    document.querySelector('#add-btn').innerText = 'Save Destination';
});

document.getElementById('searchText').addEventListener('input', function () {

    searchText = this.value;
    loadDestinations(1);
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadDestinations(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadDestinations(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Ambulance Destination';
        document.querySelector('#add-btn').innerText = 'Update Destination';

        document.querySelector('#edit-id').value = editBtn.dataset.id;
        document.querySelector('#destination_code-field').value = editBtn.dataset.code;
        document.querySelector('#destination_name-field').value = editBtn.dataset.name;
        document.querySelector('#fare_ac-field').value = editBtn.dataset.fareAc;
        document.querySelector('#fare_nonac-field').value = editBtn.dataset.fareNonac;
        document.querySelector('#remarks-field').value = editBtn.dataset.remarks;
        document.querySelector('#status-field').value = editBtn.dataset.status;

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete Ambulance Destination?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/ambulance-destination/delete/${id}`, {
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
            loadDestinations(currentPage);
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {

        perPage = parseInt(e.target.value);
        loadDestinations(1);
    }
});

loadDestinations();
