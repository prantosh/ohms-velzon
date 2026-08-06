let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let defaultAvatarUrl = document.querySelector('#user_image-preview').src;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

/*
|--------------------------------------------------------------------------
| ROLE DROPDOWN RESTRICTION -- a Supervisor may only create/assign the
| Employee or Member role. The Add-user flow only shows those two options;
| the Edit flow always shows every role (so an existing user's actual role,
| even if Admin/Supervisor, displays correctly) and relies on the backend
| to reject saving a disallowed role -- never silently downgrade a role
| the frontend simply doesn't show an option for.
|--------------------------------------------------------------------------
*/

const SUPERVISOR_ASSIGNABLE_ROLES = ['Employee', 'Member'];
const allRoleOptionsHtml = document.getElementById('role-field').innerHTML;

function restoreAllRoleOptions() {
    document.getElementById('role-field').innerHTML = allRoleOptionsHtml;
}

function applyRoleOptionsForAddMode() {

    restoreAllRoleOptions();

    if (window.currentUserRole !== 'Supervisor') return;

    let select = document.getElementById('role-field');

    Array.from(select.options).forEach(opt => {
        if (opt.value && !SUPERVISOR_ASSIGNABLE_ROLES.includes(opt.value)) {
            opt.remove();
        }
    });
}

applyRoleOptionsForAddMode();

/*
|--------------------------------------------------------------------------
| FAMILY MEMBER FIELDS -- only Member/Supervisor roles are eligible for the
| diagnostic-test family discount, so the section is hidden and its inputs
| cleared for every other role. Kept in sync with MEMBER_TIER_ROLES on the
| backend (UserController).
|--------------------------------------------------------------------------
*/

const MEMBER_TIER_ROLES = ['Member', 'Supervisor'];

function toggleFamilyMemberFields(role) {

    const section = document.getElementById('family-members-section');

    if (MEMBER_TIER_ROLES.includes(role)) {
        section.style.display = '';
    } else {
        section.style.display = 'none';
        document.getElementById('family_member_1-field').value = '';
        document.getElementById('family_member_2-field').value = '';
        document.getElementById('family_member_3-field').value = '';
    }
}

toggleFamilyMemberFields(document.getElementById('role-field').value);

async function loadUsers(page = 1) {
    currentPage = page;

    const response = await fetch(
        `/users/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#userTable tbody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector('#pagination-info').innerText =
        `Total Records : ${result.pagination.total}`;

    result.data.forEach(raw => {

        let roleBadgeClass = {
            'Admin': 'bg-danger',
            'Member': 'bg-info',
            'Employee': 'bg-secondary'
        }[raw.role] ?? 'bg-secondary';

        let authorisedBadge = raw.authorised === 'YES'
            ? '<span class="badge bg-success">YES</span>'
            : '<span class="badge bg-secondary">NO</span>';

        let statusBadge = raw.status === 'ACTIVE'
            ? '<span class="badge bg-success">ACTIVE</span>'
            : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>

            <td>
                <input type="checkbox">
            </td>

            <td>
                <img src="${raw.image_url}" class="rounded-circle" style="width:36px;height:36px;object-fit:cover;">
            </td>

            <td>${raw.name}</td>

            <td>${raw.email}</td>

            <td>${raw.mobile_no ?? ''}</td>

            <td><span class="badge ${roleBadgeClass}">${raw.role}</span></td>

            <td>${authorisedBadge}</td>

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

async function renderRolePageAccessPreview(role) {

    let container = document.querySelector('#role-page-access-preview');

    if (!role) {
        container.innerHTML = '<span class="text-muted">Select a role to see its allowed pages.</span>';
        return;
    }

    const response = await fetch(`/role-page-access/${role}`);

    const result = await response.json();

    if (!result.status) {
        container.innerHTML = '<span class="text-danger">Unable to load page access for this role.</span>';
        return;
    }

    let pages = result.page_access ?? [];

    if (pages.length === 0) {
        container.innerHTML = '<span class="text-muted">No pages configured for this role yet.</span>';
        return;
    }

    container.innerHTML = pages
        .map(key => `<span class="badge bg-info me-1 mb-1">${window.pageLabels[key] ?? key}</span>`)
        .join('');
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

    formData.append('name', document.querySelector('#name-field').value);
    formData.append('email', document.querySelector('#email-field').value);
    formData.append('mobile_no', document.querySelector('#mobile_no-field').value);
    formData.append('password', document.querySelector('#password-field').value);

    let imageFile = document.querySelector('#user_image-field').files[0];

    if (imageFile) {
        formData.append('user_image', imageFile);
    }

    formData.append('role', document.querySelector('#role-field').value);
    formData.append('status', document.querySelector('#status-field').value);
    formData.append('authorised', document.querySelector('#authorised-field').value);

    formData.append('date_of_birth', document.querySelector('#date_of_birth-field').value);
    formData.append('date_of_joining', document.querySelector('#date_of_joining-field').value);
    formData.append('gender', document.querySelector('#gender-field').value);
    formData.append('blood_group', document.querySelector('#blood_group-field').value);
    formData.append('qualification', document.querySelector('#qualification-field').value);
    formData.append('aadhar_no', document.querySelector('#aadhar_no-field').value);
    formData.append('pan_no', document.querySelector('#pan_no-field').value);
    formData.append('father_name', document.querySelector('#father_name-field').value);
    formData.append('guardian_name', document.querySelector('#guardian_name-field').value);

    formData.append('present_address', document.querySelector('#present_address-field').value);
    formData.append('permanent_address', document.querySelector('#permanent_address-field').value);
    formData.append('emergency_mobile_no', document.querySelector('#emergency_mobile_no-field').value);

    formData.append('bank_ac_no', document.querySelector('#bank_ac_no-field').value);
    formData.append('bank_name', document.querySelector('#bank_name-field').value);
    formData.append('bank_branch', document.querySelector('#bank_branch-field').value);

    formData.append('family_member_1', document.querySelector('#family_member_1-field').value);
    formData.append('family_member_2', document.querySelector('#family_member_2-field').value);
    formData.append('family_member_3', document.querySelector('#family_member_3-field').value);

    let url = '/users/store';

    if (editId) {
        url = `/users/update/${editId}`;
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
            : (result.message ?? 'Unable to save user.');

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
    setFlatpickrValue(document.querySelector('#date_of_birth-field'), '');
    setFlatpickrValue(document.querySelector('#date_of_joining-field'), '');

    loadUsers(currentPage);
});

document.getElementById('user_image-field').addEventListener('change', function () {

    let file = this.files[0];

    if (!file) return;

    document.querySelector('#user_image-preview').src = URL.createObjectURL(file);
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();
    setFlatpickrValue(document.querySelector('#date_of_birth-field'), '');
    setFlatpickrValue(document.querySelector('#date_of_joining-field'), '');

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add User';
    document.querySelector('#add-btn').innerText = 'Save User';

    document.querySelector('#user_image-preview').src = defaultAvatarUrl;

    document.querySelector('#password-field').required = true;
    document.querySelector('#password-required').style.display = 'inline';
    document.querySelector('#password-hint').style.display = 'none';

    applyRoleOptionsForAddMode();

    renderRolePageAccessPreview(document.querySelector('#role-field').value);
    toggleFamilyMemberFields(document.querySelector('#role-field').value);
});

document.getElementById('role-field').addEventListener('change', function () {
    renderRolePageAccessPreview(this.value);
    toggleFamilyMemberFields(this.value);
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadUsers(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadUsers(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        let id = editBtn.dataset.id;

        const response = await fetch(`/users/edit/${id}`);
        const result = await response.json();

        if (!result.status) return;

        let user = result.data;

        document.querySelector('#modal-title').innerText = 'Update User';
        document.querySelector('#add-btn').innerText = 'Update User';

        document.querySelector('#edit-id').value = user.id;

        document.querySelector('#name-field').value = user.name ?? '';
        document.querySelector('#email-field').value = user.email ?? '';
        document.querySelector('#mobile_no-field').value = user.mobile_no ?? '';

        document.querySelector('#user_image-preview').src = user.image_url ?? defaultAvatarUrl;

        document.querySelector('#password-field').required = false;
        document.querySelector('#password-required').style.display = 'none';
        document.querySelector('#password-hint').style.display = 'inline';

        restoreAllRoleOptions();
        document.querySelector('#role-field').value = user.role ?? 'Employee';
        document.querySelector('#status-field').value = user.status ?? 'ACTIVE';
        document.querySelector('#authorised-field').value = user.authorised ?? 'NO';

        renderRolePageAccessPreview(document.querySelector('#role-field').value);
        toggleFamilyMemberFields(document.querySelector('#role-field').value);

        setFlatpickrValue(document.querySelector('#date_of_birth-field'), user.date_of_birth);
        setFlatpickrValue(document.querySelector('#date_of_joining-field'), user.date_of_joining);
        document.querySelector('#gender-field').value = user.gender ?? '';
        document.querySelector('#blood_group-field').value = user.blood_group ?? '';
        document.querySelector('#qualification-field').value = user.qualification ?? '';
        document.querySelector('#aadhar_no-field').value = user.aadhar_no ?? '';
        document.querySelector('#pan_no-field').value = user.pan_no ?? '';
        document.querySelector('#father_name-field').value = user.father_name ?? '';
        document.querySelector('#guardian_name-field').value = user.guardian_name ?? '';

        document.querySelector('#present_address-field').value = user.present_address ?? '';
        document.querySelector('#permanent_address-field').value = user.permanent_address ?? '';
        document.querySelector('#emergency_mobile_no-field').value = user.emergency_mobile_no ?? '';

        document.querySelector('#bank_ac_no-field').value = user.bank_ac_no ?? '';
        document.querySelector('#bank_name-field').value = user.bank_name ?? '';
        document.querySelector('#bank_branch-field').value = user.bank_branch ?? '';

        document.querySelector('#family_member_1-field').value = user.family_member_1 ?? '';
        document.querySelector('#family_member_2-field').value = user.family_member_2 ?? '';
        document.querySelector('#family_member_3-field').value = user.family_member_3 ?? '';

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete this user?',
            text: 'This account will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/users/delete/${id}`, {
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
            loadUsers(currentPage);
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadUsers(1);
    }
});

document.getElementById('searchInput').addEventListener('input', function () {

    searchTerm = this.value;
    loadUsers(1);
});

loadUsers();
