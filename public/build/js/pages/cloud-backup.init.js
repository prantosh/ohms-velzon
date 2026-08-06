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

function fmtSize(bytes) {
    bytes = Number(bytes ?? 0);
    if (bytes >= 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
}

function currentCredentials() {
    return {
        host: document.getElementById('host-field').value.trim(),
        port: document.getElementById('port-field').value.trim(),
        database: document.getElementById('database-field').value.trim(),
        username: document.getElementById('username-field').value.trim(),
        password: document.getElementById('password-field').value,
    };
}

function validateCredentials(creds) {
    return creds.host && creds.database && creds.username;
}

function showResult(status, message) {
    let box = document.getElementById('resultAlert');
    box.className = 'alert mt-3 ' + (status ? 'alert-success' : 'alert-danger');
    box.textContent = message;
    box.style.display = 'block';
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });
    return response.json();
}

document.getElementById('btnTestConnection').addEventListener('click', async function () {

    let creds = currentCredentials();

    if (!validateCredentials(creds)) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Please fill Host, Database and Username.' });
        return;
    }

    let btn = this;
    btn.disabled = true;

    const result = await postJson('/cloud-backup/test-connection', creds);

    btn.disabled = false;

    showResult(result.status, result.message);
});

document.getElementById('btnRunBackup').addEventListener('click', async function () {

    let creds = currentCredentials();

    if (!validateCredentials(creds)) {
        Swal.fire({ icon: 'warning', title: 'Missing Fields', text: 'Please fill Host, Database and Username.' });
        return;
    }

    let confirm = await Swal.fire({
        title: 'Start Backup?',
        text: `This will download a full backup of "${creds.database}" from ${creds.host} to this machine.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0ab39c',
        cancelButtonColor: '#f06548',
        confirmButtonText: 'Yes, Start Backup'
    });

    if (!confirm.isConfirmed) return;

    document.getElementById('resultAlert').style.display = 'none';
    document.getElementById('backupProgress').style.display = 'block';
    document.getElementById('btnTestConnection').disabled = true;
    document.getElementById('btnRunBackup').disabled = true;

    const result = await postJson('/cloud-backup/run', creds);

    document.getElementById('backupProgress').style.display = 'none';
    document.getElementById('btnTestConnection').disabled = false;
    document.getElementById('btnRunBackup').disabled = false;

    showResult(
        result.status,
        result.status
            ? `Backup completed: ${result.file_name} (${fmtSize(result.size)})`
            : result.message
    );

    if (result.status) {
        loadBackupList();
    }
});

async function loadBackupList() {

    const response = await fetch('/cloud-backup/list');
    const result = await response.json();

    let tbody = document.getElementById('backupListBody');

    if (!result.status || !result.data.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No backups yet.</td></tr>';
        return;
    }

    tbody.innerHTML = result.data.map(row => `
        <tr>
            <td>${escapeHtml(row.file_name)}</td>
            <td>${fmtSize(row.size)}</td>
            <td>${escapeHtml(row.created_at)}</td>
            <td>
                <a href="/cloud-backup/download/${encodeURIComponent(row.file_name)}" class="btn btn-sm btn-soft-success" title="Download">
                    <i class="ri-download-2-line"></i>
                </a>
                <button type="button" class="btn btn-sm btn-soft-danger delete-backup-btn" data-name="${escapeHtml(row.file_name)}" title="Delete">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

document.getElementById('btnRefreshList').addEventListener('click', loadBackupList);

document.getElementById('backupListBody').addEventListener('click', async function (e) {

    let deleteBtn = e.target.closest('.delete-backup-btn');
    if (!deleteBtn) return;

    let fileName = deleteBtn.dataset.name;

    let confirm = await Swal.fire({
        title: 'Delete Backup File?',
        text: `"${fileName}" will be permanently deleted from this machine.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0ab39c',
        cancelButtonColor: '#f06548',
        confirmButtonText: 'Yes, Delete'
    });

    if (!confirm.isConfirmed) return;

    const response = await fetch(`/cloud-backup/delete/${encodeURIComponent(fileName)}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() }
    });

    const result = await response.json();

    Swal.fire({
        icon: result.status ? 'success' : 'error',
        title: result.status ? 'Deleted' : 'Error',
        text: result.message
    });

    loadBackupList();
});

loadBackupList();
