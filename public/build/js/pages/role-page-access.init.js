function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function setCheckedPages(pages) {

    document.querySelectorAll('.page-access-checkbox').forEach(cb => {
        cb.checked = Array.isArray(pages) && pages.includes(cb.value);
    });
}

function collectCheckedPages() {

    let pages = [];

    document.querySelectorAll('.page-access-checkbox:checked').forEach(cb => {
        pages.push(cb.value);
    });

    return pages;
}

async function loadRoleAccess(role) {

    const response = await fetch(`/role-page-access/${role}`);

    const result = await response.json();

    if (!result.status) return;

    setCheckedPages(result.page_access);
}

document.getElementById('role-select').addEventListener('change', function () {

    loadRoleAccess(this.value);
});

document.getElementById('save-btn').addEventListener('click', async function () {

    let role = document.getElementById('role-select').value;

    let formData = new FormData();

    collectCheckedPages().forEach(page => {
        formData.append('page_access[]', page);
    });

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

    const response = await fetch(`/role-page-access/${role}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },
        body: formData
    });

    const result = await response.json();

    Swal.fire({
        icon: result.status ? 'success' : 'error',
        title: result.status ? 'Saved' : 'Error',
        text: result.message
    });
});

document.getElementById('add-role-btn').addEventListener('click', async function () {

    let roleInput = document.getElementById('new-role-field');

    let role = roleInput.value.trim();

    if (!role) {

        Swal.fire({
            icon: 'warning',
            title: 'Enter a role name'
        });

        return;
    }

    let formData = new FormData();

    formData.append('role', role);

    const response = await fetch('/role-page-access/roles', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken()
        },
        body: formData
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message ?? 'Unable to create role.'
        });

        return;
    }

    let roleSelect = document.getElementById('role-select');

    let option = document.createElement('option');
    option.value = result.role;
    option.innerText = result.role;

    roleSelect.appendChild(option);
    roleSelect.value = result.role;

    roleInput.value = '';

    Swal.fire({
        icon: 'success',
        title: 'Role Added',
        text: result.message
    });

    loadRoleAccess(result.role);
});

loadRoleAccess(document.getElementById('role-select').value);
