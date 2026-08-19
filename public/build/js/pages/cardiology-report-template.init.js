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
| RICH-TEXT EDITORS -- one per [data-cardio-field] textarea (16 of them:
| Heading, M-Mode Data, Doppler Data, the 11 numbered chamber/valve items,
| Conclusion, Remarks). Generalized from USG's 3-hardcoded-field approach
| since hand-listing 16 field keys would be unwieldy -- the field list is
| discovered from the DOM instead.
|--------------------------------------------------------------------------
*/

const {
    ClassicEditor, Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo,
    Table, TableToolbar, TableProperties, TableCellProperties
} = CKEDITOR;

const CARDIO_EDITOR_CONFIG = {
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

let cardioEditors = {};

function cardioFieldKeys() {
    return Array.from(document.querySelectorAll('[data-cardio-field]')).map(el => el.dataset.cardioField);
}

async function initCardioTemplateEditors() {

    for (const field of cardioFieldKeys()) {
        cardioEditors[field] = await ClassicEditor.create(
            document.querySelector(`#${field}-field`),
            CARDIO_EDITOR_CONFIG
        );
    }
}

// Records saved before rich-text editing existed are plain text with literal
// newlines. Each line becomes its own paragraph (not just a <br> within one
// shared paragraph) so alignment/formatting can target one line without
// dragging the rest of the text along with it. Already-HTML content passes
// through untouched. Mirrors usgToEditorHtml() exactly.
function cardioToEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

let cardioEditorsReady = initCardioTemplateEditors();

function fillEditorsFrom(data) {
    cardioFieldKeys().forEach(key => {
        cardioEditors[key].setData(cardioToEditorHtml(data[key]));
    });
}

function clearEditors() {
    cardioFieldKeys().forEach(key => cardioEditors[key].setData(''));
}

async function loadCopyFromOptions() {

    const { result } = await fetchJson('/cardiology-report-template/all');

    let select = document.querySelector('#copy-from-field');

    select.innerHTML = '<option value="">-- Start Blank --</option>';

    if (!result.status) return;

    result.data.forEach(tpl => {

        let option = document.createElement('option');
        option.value = tpl.id;
        option.textContent = `${tpl.study_name} — ${tpl.title}` + (tpl.status === 'INACTIVE' ? ' (Inactive)' : '');
        select.appendChild(option);
    });
}

document.querySelector('#copy-from-field').addEventListener('change', async function () {

    let id = this.value;

    if (!id) return;

    await cardioEditorsReady;

    const { result } = await fetchJson(`/cardiology-report-template/edit/${id}`);

    if (result.status) {
        document.querySelector('#title-field').value = result.data.title;
        document.querySelector('#item_code_sub-field').value = result.data.item_code_sub;
        fillEditorsFrom(result.data);
        document.querySelector('#status-field').value = result.data.status;
    }

    this.value = '';
});

async function loadTemplates(page = 1) {
    currentPage = page;

    const { result } = await fetchJson(
        `/cardiology-report-template/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    let tbody = document.querySelector('#templateTable tbody');

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

            <td>${escapeHtml(raw.title)}</td>

            <td>${escapeHtml(raw.study_name ?? raw.item_code_sub)}</td>

            <td>${escapeHtml(raw.created_by_name ?? '-')}</td>

            <td>${escapeHtml(raw.created_dt ?? '-')}</td>

            <td>${escapeHtml(raw.updated_by_name ?? '-')}</td>

            <td>${escapeHtml(raw.updated_dt ?? '-')}</td>

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

    let form = this;

    try {

        await cardioEditorsReady;

        let editId = document.querySelector('#edit-id').value;

        let formData = new FormData();

        formData.append('title', document.querySelector('#title-field').value);
        formData.append('item_code_sub', document.querySelector('#item_code_sub-field').value);
        cardioFieldKeys().forEach(key => {
            formData.append(key, cardioEditors[key].getData());
        });
        formData.append('status', document.querySelector('#status-field').value);

        let url = '/cardiology-report-template/store';

        if (editId) {
            url = `/cardiology-report-template/update/${editId}`;
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
                : (result.message ?? 'Unable to save template.');

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

        form.reset();

        loadTemplates(currentPage);
        loadCopyFromOptions();

    } catch (err) {

        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: err.message || 'Unable to save template.'
        });
    }
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', async function () {

    document.querySelector('.tablelist-form').reset();

    // form.reset() only touches the native form fields -- CKEditor's visible
    // content lives in a separate contenteditable div and needs clearing
    // explicitly, or stale content would carry over into the next Add.
    await cardioEditorsReady;
    clearEditors();

    document.querySelector('#edit-id').value = '';

    document.querySelector('#modal-title').innerText = 'Add Template';
    document.querySelector('#add-btn').innerText = 'Save Template';
});

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadTemplates(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadTemplates(currentPage + 1);
        return;
    }

    if (e.target.closest('#addTemplateBtn')) {

        // Copying only makes sense when starting a brand new template.
        document.querySelector('.copy-from-wrap').style.display = '';
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        // Editing already loads its own data below -- copying from
        // another template here would just clobber it, so hide the option.
        document.querySelector('.copy-from-wrap').style.display = 'none';

        document.querySelector('#modal-title').innerText = 'Update Template';
        document.querySelector('#add-btn').innerText = 'Update Template';

        let id = editBtn.dataset.id;

        document.querySelector('#edit-id').value = id;

        await cardioEditorsReady;

        const { result } = await fetchJson(`/cardiology-report-template/edit/${id}`);

        if (result.status) {
            document.querySelector('#title-field').value = result.data.title;
            document.querySelector('#item_code_sub-field').value = result.data.item_code_sub;
            fillEditorsFrom(result.data);
            document.querySelector('#status-field').value = result.data.status;
        }

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete this Template?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        const { result } = await fetchJson(`/cardiology-report-template/delete/${id}`, {
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
            loadTemplates(currentPage);
            loadCopyFromOptions();
        }
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadTemplates(1);
    }
});

document.getElementById('searchInput').addEventListener('input', function () {

    searchTerm = this.value;
    loadTemplates(1);
});

loadTemplates();
loadCopyFromOptions();
