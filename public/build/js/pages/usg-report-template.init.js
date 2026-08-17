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

/*
|--------------------------------------------------------------------------
| RICH-TEXT EDITORS -- Clinical History / Findings / Impression
|--------------------------------------------------------------------------
*/

const { ClassicEditor, Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo } = CKEDITOR;

const USG_EDITOR_CONFIG = {
    licenseKey: 'GPL',
    plugins: [Essentials, Paragraph, Bold, Italic, Underline, Alignment, FontSize, List, Undo],
    toolbar: [
        'bold', 'italic', 'underline', '|',
        'alignment', '|',
        'fontSize', '|',
        'bulletedList', 'numberedList', '|',
        'undo', 'redo'
    ]
};

let usgEditors = {};

async function initUsgTemplateEditors() {

    const fields = ['clinical_history', 'findings', 'impression'];

    for (const field of fields) {
        usgEditors[field] = await ClassicEditor.create(
            document.querySelector(`#${field}-field`),
            USG_EDITOR_CONFIG
        );
    }
}

// Records saved before rich-text editing existed are plain text with literal
// newlines. Each line becomes its own paragraph (not just a <br> within one
// shared paragraph) so alignment/formatting can target one line -- e.g. an
// organ heading like "LIVER" -- without dragging the rest of the text along
// with it, since text-align always applies to the whole enclosing paragraph.
// Already-HTML content (post-feature) passes through untouched.
function usgToEditorHtml(text) {
    if (!text) return '';
    if (text.includes('<')) return text;
    return text.split('\n').map(line => `<p>${escapeHtml(line)}</p>`).join('');
}

let usgEditorsReady = initUsgTemplateEditors();

async function loadCopyFromOptions() {

    const response = await fetch('/usg-report-template/all');
    const result = await response.json();

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

    await usgEditorsReady;

    const response = await fetch(`/usg-report-template/edit/${id}`);
    const result = await response.json();

    if (result.status) {
        document.querySelector('#title-field').value = result.data.title;
        document.querySelector('#item_code_sub-field').value = result.data.item_code_sub;
        usgEditors.clinical_history.setData(usgToEditorHtml(result.data.clinical_history));
        usgEditors.findings.setData(usgToEditorHtml(result.data.findings));
        usgEditors.impression.setData(usgToEditorHtml(result.data.impression));
        document.querySelector('#status-field').value = result.data.status;
    }

    this.value = '';
});

async function loadTemplates(page = 1) {
    currentPage = page;

    const response = await fetch(
        `/usg-report-template/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

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

    await usgEditorsReady;

    let editId = document.querySelector('#edit-id').value;

    let formData = new FormData();

    formData.append('title', document.querySelector('#title-field').value);
    formData.append('item_code_sub', document.querySelector('#item_code_sub-field').value);
    formData.append('clinical_history', usgEditors.clinical_history.getData());
    formData.append('findings', usgEditors.findings.getData());
    formData.append('impression', usgEditors.impression.getData());
    formData.append('status', document.querySelector('#status-field').value);

    let url = '/usg-report-template/store';

    if (editId) {
        url = `/usg-report-template/update/${editId}`;
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

    this.reset();

    loadTemplates(currentPage);
    loadCopyFromOptions();
});

document.getElementById('showModal').addEventListener('hidden.bs.modal', async function () {

    document.querySelector('.tablelist-form').reset();

    // form.reset() only touches the native form fields -- CKEditor's visible
    // content lives in a separate contenteditable div and needs clearing
    // explicitly, or stale content would carry over into the next Add.
    await usgEditorsReady;
    usgEditors.clinical_history.setData('');
    usgEditors.findings.setData('');
    usgEditors.impression.setData('');

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

        await usgEditorsReady;

        const response = await fetch(`/usg-report-template/edit/${id}`);
        const result = await response.json();

        if (result.status) {
            document.querySelector('#title-field').value = result.data.title;
            document.querySelector('#item_code_sub-field').value = result.data.item_code_sub;
            usgEditors.clinical_history.setData(usgToEditorHtml(result.data.clinical_history));
            usgEditors.findings.setData(usgToEditorHtml(result.data.findings));
            usgEditors.impression.setData(usgToEditorHtml(result.data.impression));
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

        let response = await fetch(`/usg-report-template/delete/${id}`, {
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
