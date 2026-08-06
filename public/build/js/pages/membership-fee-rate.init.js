let currentPage = 1;
let lastPage = 1;
let perPage = 10;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

async function loadRates(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/membership-fee-rate/list?page=${page}&per_page=${perPage}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#rateTable tbody');

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

            <td>${raw.effective_from}</td>

            <td>${Number(raw.monthly_rate).toFixed(2)}</td>

            <td>${raw.remarks ?? ''}</td>

            <td>${statusBadge}</td>

            <td>${raw.created_by_name ?? ''}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   data-effective-from="${raw.effective_from}"
                   data-rate="${raw.monthly_rate}"
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

    let monthValue = document.querySelector('#effective_from-field').value;

    let formData = new FormData();

    formData.append('effective_from', monthValue ? `${monthValue}-01` : '');
    formData.append('monthly_rate', document.querySelector('#monthly_rate-field').value);
    formData.append('remarks', document.querySelector('#remarks-field').value);
    formData.append('status', document.querySelector('#status-field').value);

    let url = '/membership-fee-rate/store';

    if (editId) {
        url = `/membership-fee-rate/update/${editId}`;
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
            : (result.message ?? 'Unable to save membership fee rate.');

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

    loadRates(currentPage);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add Membership Fee Rate';

    document.querySelector('#add-btn').innerText = 'Save Rate';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadRates(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadRates(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Membership Fee Rate';
        document.querySelector('#add-btn').innerText = 'Update Rate';

        document.querySelector('#edit-id').value = editBtn.dataset.id;
        document.querySelector('#effective_from-field').value = editBtn.dataset.effectiveFrom.substring(0, 7);
        document.querySelector('#monthly_rate-field').value = editBtn.dataset.rate;
        document.querySelector('#remarks-field').value = editBtn.dataset.remarks;
        document.querySelector('#status-field').value = editBtn.dataset.status;

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete this rate?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/membership-fee-rate/delete/${id}`, {
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
            loadRates(currentPage);
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {

        perPage = parseInt(e.target.value);
        loadRates(1);
    }
});

loadRates();
