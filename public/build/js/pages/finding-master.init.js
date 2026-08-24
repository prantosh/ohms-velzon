let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';

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

// Without an explicit Accept header, fetch() doesn't tell Laravel this is an
// AJAX call -- a validation failure then 302-redirects to a normal HTML page
// instead of returning JSON, response.json() throws, and (uncaught) whatever
// loading indicator is showing stays stuck forever. This forces the Accept
// header and turns a non-JSON response into a clear, catchable error instead.
async function fetchJson(url, options = {}) {
    const headers = Object.assign({ 'Accept': 'application/json' }, options.headers || {});
    const response = await fetch(url, Object.assign({}, options, { headers }));

    let result;
    try {
        result = await response.json();
    } catch (e) {
        throw new Error(`Unexpected server response (HTTP ${response.status}). Please try again.`);
    }

    return { response, result };
}

/*
|--------------------------------------------------------------------------
| RICH-TEXT EDITOR -- Finding content
|--------------------------------------------------------------------------
*/

const {
    ClassicEditor, Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo,
    Table, TableToolbar, TableProperties, TableCellProperties
} = CKEDITOR;

const MASTER_EDITOR_CONFIG = {
    licenseKey: 'GPL',
    plugins: [
        Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo,
        Table, TableToolbar, TableProperties, TableCellProperties
    ],
    toolbar: [
        'bold', 'italic', 'underline', '|',
        'alignment', '|',
        'fontSize', '|',
        'bulletedList', 'numberedList', '|',
        'insertTable', '|',
        'undo', 'redo'
    ],
    table: {
        contentToolbar: [
            'tableColumn', 'tableRow', 'mergeTableCells',
            'tableProperties', 'tableCellProperties'
        ]
    }
};

let nameEditor = null;
let nameEditorReady = ClassicEditor.create(document.querySelector('#name-field'), MASTER_EDITOR_CONFIG)
    .then(editor => { nameEditor = editor; return editor; });

// Records saved before rich-text editing existed are plain text with literal
// newlines -- each line becomes its own paragraph so it displays the same
// way it would have as plain text. Already-HTML content (post-feature)
// passes through untouched.
function toEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

async function loadFindings(page = 1) {
    currentPage = page;

    const { result } = await fetchJson(
        `/finding-master/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    let tbody = document.querySelector('#findingTable tbody');

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

            <td>${raw.name}</td>

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

    try {

        await nameEditorReady;

        let editId = document.querySelector('#edit-id').value;

        let formData = new FormData();

        formData.append('name', nameEditor.getData());
        formData.append('status', document.querySelector('#status-field').value);

        let url = '/finding-master/store';

        if (editId) {
            url = `/finding-master/update/${editId}`;
        }

        const { response, result } = await fetchJson(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            },
            body: formData
        });

        if (!response.ok || !result.status) {

            let errorText = result.errors
                ? Object.values(result.errors).flat().join(', ')
                : (result.message ?? 'Unable to save finding.');

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

        loadFindings(currentPage);

    } catch (err) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'Unable to save finding.'
        });
    }
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', function () {

    document.querySelector('.tablelist-form').reset();

    // form.reset() only touches the native form fields -- CKEditor's visible
    // content is separate DOM it manages itself, so it must be cleared here.
    if (nameEditor) nameEditor.setData('');

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add Finding';
    document.querySelector('#add-btn').innerText = 'Save Finding';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadFindings(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadFindings(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        document.querySelector('#modal-title').innerText = 'Update Finding';
        document.querySelector('#add-btn').innerText = 'Update Finding';

        let id = editBtn.dataset.id;

        document.querySelector('#edit-id').value = id;

        const { result } = await fetchJson(`/finding-master/edit/${id}`);

        await nameEditorReady;

        if (result.status) {
            nameEditor.setData(toEditorHtml(result.data.name));
            document.querySelector('#status-field').value = result.data.status;
        }

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete this Finding?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        const { result } = await fetchJson(`/finding-master/delete/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken()
            }
        });

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) {
            loadFindings(currentPage);
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadFindings(1);
    }
});

document.getElementById('searchInput').addEventListener('input', function () {

    searchTerm = this.value;
    loadFindings(1);
});

loadFindings();
