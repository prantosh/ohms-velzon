function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

async function loadLog() {

    let chars = document.getElementById('charsSelect').value;
    let search = document.getElementById('searchInput').value.trim();

    let params = new URLSearchParams({ chars: chars });
    if (search) params.set('search', search);

    let output = document.getElementById('logOutput');
    output.textContent = 'Loading...';

    const response = await fetch(`/system-log/tail?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });
    const result = await response.json();

    if (!result.status) {
        output.textContent = 'Unable to load the log file.';
        return;
    }

    if (!result.exists) {
        document.getElementById('logMeta').innerText = 'Log file does not exist yet.';
        output.textContent = '(empty)';
        return;
    }

    document.getElementById('logMeta').innerText =
        `File size: ${(result.size / 1024).toFixed(1)} KB` + (result.truncated ? ' (showing tail only)' : '');

    output.textContent = result.content && result.content.trim() ? result.content : '(no matching entries)';

    // Scroll to the bottom -- the newest entries are always at the end.
    output.scrollTop = output.scrollHeight;
}

document.getElementById('refreshBtn').addEventListener('click', loadLog);
document.getElementById('charsSelect').addEventListener('change', loadLog);

let searchDebounce = null;
document.getElementById('searchInput').addEventListener('input', function () {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(loadLog, 400);
});

document.getElementById('clearBtn').addEventListener('click', async function () {

    let confirmResult = await Swal.fire({
        title: 'Clear the log file?',
        text: 'This permanently empties storage/logs/laravel.log. Only do this once you no longer need the current contents.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Clear'
    });

    if (!confirmResult.isConfirmed) return;

    const response = await fetch('/system-log/clear', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' }
    });
    const result = await response.json();

    Swal.fire({
        icon: result.status ? 'success' : 'error',
        title: result.status ? 'Cleared' : 'Error',
        text: result.message,
        timer: result.status ? 1200 : undefined,
        showConfirmButton: !result.status
    });

    if (result.status) loadLog();
});

loadLog();
