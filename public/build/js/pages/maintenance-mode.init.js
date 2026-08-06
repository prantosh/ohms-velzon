function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function showResult(status, message) {
    let box = document.getElementById('resultAlert');
    box.className = 'alert mt-3 ' + (status ? 'alert-success' : 'alert-danger');
    box.textContent = message;
    box.style.display = 'block';
}

function updateStatusLabel(isEnabled) {
    let label = document.getElementById('maintenanceStatusLabel');
    let meta = document.getElementById('maintenanceStatusMeta');

    if (isEnabled) {
        label.innerHTML = '<span class="text-danger">Currently ON</span>';
        meta.textContent = 'Enabled just now.';
    } else {
        label.innerHTML = '<span class="text-success">Currently OFF</span>';
        meta.textContent = 'The application is accessible to everyone.';
    }
}

document.getElementById('btnSaveMaintenance').addEventListener('click', async function () {

    let isEnabled = document.getElementById('maintenanceToggle').checked;
    let message = document.getElementById('maintenanceMessage-field').value.trim();

    if (isEnabled) {
        let confirm = await Swal.fire({
            title: 'Turn Maintenance Mode ON?',
            text: 'Every user except Admin will be blocked from using the application until you turn this off again.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f06548',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, Turn It ON'
        });

        if (!confirm.isConfirmed) {
            document.getElementById('maintenanceToggle').checked = false;
            return;
        }
    }

    let btn = this;
    btn.disabled = true;

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

    const response = await fetch('/maintenance-mode/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ is_enabled: isEnabled, message: message })
    });

    const result = await response.json();

    btn.disabled = false;

    Swal.close();

    showResult(result.status, result.message);

    if (result.status) {
        updateStatusLabel(result.is_enabled);
    }
});
