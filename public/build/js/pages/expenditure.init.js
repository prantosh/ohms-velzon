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

function fmtMoney(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ==========================================================
   CHEQUE FIELD TOGGLE
========================================================== */

function toggleChequeFields() {

    let isCheque = document.getElementById('payment_mode-field').value === 'Cheque';

    document.querySelectorAll('.cheque-field-wrap').forEach(el => {
        el.style.display = isCheque ? 'block' : 'none';
    });
}

document.getElementById('payment_mode-field').addEventListener('change', toggleChequeFields);

/* ==========================================================
   LIST
========================================================== */

async function loadExpenditures(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/expenditure/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#expenditureTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted">No expenditure entries found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td class="fw-semibold">${raw.voucher_number}</td>
            <td>${escapeHtml(raw.bill_number ?? '-')}</td>
            <td>${raw.transaction_date_fmt ?? ''}</td>
            <td>${escapeHtml(raw.category_name ?? '')}</td>
            <td>${escapeHtml(raw.agency_name ?? '')}</td>
            <td class="text-end">${fmtMoney(raw.amount)}</td>
            <td>${escapeHtml(raw.payment_mode)}</td>
            <td>${escapeHtml(raw.expenditure_by_name ?? '')}</td>
            <td>
                <a href="/expenditure/print/${raw.id}" target="_blank" class="btn btn-sm btn-soft-primary me-1" title="Print">
                    <i class="ri-printer-line"></i>
                </a>
                <a href="javascript:void(0)" class="btn btn-sm btn-soft-success edit-item-btn me-1" data-id="${raw.id}" title="Edit">
                    <i class="ri-pencil-fill"></i>
                </a>
                <a href="javascript:void(0)" class="btn btn-sm btn-soft-danger delete-item-btn" data-id="${raw.id}" title="Delete">
                    <i class="ri-delete-bin-5-fill"></i>
                </a>
            </td>
        </tr>
        `;
    });
}

/* ==========================================================
   FORM HELPERS
========================================================== */

function resetExpenditureForm() {

    document.getElementById('expenditureForm').reset();
    document.getElementById('edit-id').value = '';
    document.getElementById('formTitle').innerText = 'Record Expenditure';
    document.getElementById('submitBtn').innerHTML = '<i class="ri-save-line"></i> Save Expenditure';
    document.getElementById('btnCancelEdit').style.display = 'none';
    setFlatpickrValue('transaction_date-field', new Date().toISOString().substring(0, 10));
    setFlatpickrValue('cheque_date-field', '');
    toggleChequeFields();
}

document.getElementById('btnCancelEdit').addEventListener('click', resetExpenditureForm);

function gatherFormPayload() {

    return {
        expenditure_category_id: document.getElementById('category_id-field').value,
        expenditure_agency_id: document.getElementById('agency_id-field').value,
        transaction_date: document.getElementById('transaction_date-field').value,
        amount: document.getElementById('amount-field').value,
        payment_mode: document.getElementById('payment_mode-field').value,
        cheque_number: document.getElementById('cheque_number-field').value,
        bank_name: document.getElementById('bank_name-field').value,
        cheque_date: document.getElementById('cheque_date-field').value,
        bill_number: document.getElementById('bill_number-field').value,
        expenditure_by: document.getElementById('expenditure_by-field').value,
        remarks: document.getElementById('remarks-field').value
    };
}

/* ==========================================================
   SUBMIT (NEW ENTRY -> OFFCANVAS CONFIRMATION, EDIT -> DIRECT UPDATE)
========================================================== */

document.getElementById('expenditureForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    let editId = document.getElementById('edit-id').value;

    if (editId) {
        await submitUpdate(editId);
        return;
    }

    // populate review fields and show confirmation offcanvas
    let categorySelect = document.getElementById('category_id-field');
    let agencySelect = document.getElementById('agency_id-field');
    let expenditureBySelect = document.getElementById('expenditure_by-field');

    document.getElementById('review-category').innerText = categorySelect.options[categorySelect.selectedIndex]?.text ?? '-';
    document.getElementById('review-agency').innerText = agencySelect.options[agencySelect.selectedIndex]?.text ?? '-';
    document.getElementById('review-transaction_date').innerText = document.getElementById('transaction_date-field').value || '-';
    document.getElementById('review-amount').innerText = '₹ ' + fmtMoney(document.getElementById('amount-field').value);
    document.getElementById('review-payment_mode').innerText = document.getElementById('payment_mode-field').value || '-';
    document.getElementById('review-cheque_number').innerText = document.getElementById('cheque_number-field').value || '-';
    document.getElementById('review-bank_name').innerText = document.getElementById('bank_name-field').value || '-';
    document.getElementById('review-cheque_date').innerText = document.getElementById('cheque_date-field').value || '-';
    document.getElementById('review-bill_number').innerText = document.getElementById('bill_number-field').value || '-';
    document.getElementById('review-expenditure_by').innerText = expenditureBySelect.options[expenditureBySelect.selectedIndex]?.text ?? '-';
    document.getElementById('review-remarks').innerText = document.getElementById('remarks-field').value || '-';

    new bootstrap.Offcanvas(document.getElementById('confirmExpenditureOffcanvas')).show();
});

document.getElementById('btnBackToEditExpenditure').addEventListener('click', function () {
    bootstrap.Offcanvas.getInstance(document.getElementById('confirmExpenditureOffcanvas'))?.hide();
});

document.getElementById('btnConfirmSaveExpenditure').addEventListener('click', async function () {

    let payload = gatherFormPayload();

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

    const response = await fetch('/expenditure/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to record expenditure entry.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    bootstrap.Offcanvas.getInstance(document.getElementById('confirmExpenditureOffcanvas'))?.hide();

    window.open(`/expenditure/print/${result.id}`, '_blank');

    Swal.fire({ icon: 'success', title: 'Saved', text: result.message });

    resetExpenditureForm();

    loadExpenditures(1);
});

async function submitUpdate(id) {

    let payload = gatherFormPayload();

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

    const response = await fetch(`/expenditure/update/${id}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        let errorText = result.errors
            ? Object.values(result.errors).flat().join(', ')
            : (result.message ?? 'Unable to update expenditure entry.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Updated', text: result.message });

    resetExpenditureForm();

    loadExpenditures(currentPage);
}

/* ==========================================================
   EDIT / DELETE / PAGINATION
========================================================== */

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadExpenditures(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadExpenditures(currentPage + 1);
        return;
    }

    let editBtn = e.target.closest('.edit-item-btn');

    if (editBtn) {

        const response = await fetch(`/expenditure/edit/${editBtn.dataset.id}`);
        const result = await response.json();

        if (!result.status) return;

        let row = result.data;

        document.getElementById('edit-id').value = row.id;
        document.getElementById('category_id-field').value = row.expenditure_category_id;
        document.getElementById('agency_id-field').value = row.expenditure_agency_id;
        setFlatpickrValue('transaction_date-field', row.transaction_date);
        document.getElementById('amount-field').value = row.amount;
        document.getElementById('payment_mode-field').value = row.payment_mode;
        document.getElementById('cheque_number-field').value = row.cheque_number ?? '';
        document.getElementById('bank_name-field').value = row.bank_name ?? '';
        setFlatpickrValue('cheque_date-field', row.cheque_date ?? '');
        document.getElementById('bill_number-field').value = row.bill_number ?? '';
        document.getElementById('expenditure_by-field').value = row.expenditure_by;
        document.getElementById('remarks-field').value = row.remarks ?? '';

        toggleChequeFields();

        document.getElementById('formTitle').innerText = `Update Expenditure - ${row.voucher_number}`;
        document.getElementById('submitBtn').innerHTML = '<i class="ri-save-line"></i> Update Expenditure';
        document.getElementById('btnCancelEdit').style.display = 'inline-block';

        document.getElementById('expenditureForm').scrollIntoView({ behavior: 'smooth' });

        return;
    }

    let deleteBtn = e.target.closest('.delete-item-btn');

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Delete Expenditure Entry?',
            text: 'This record will be permanently deleted.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Delete'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/expenditure/delete/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Deleted' : 'Error',
            text: result.message
        });

        if (result.status) loadExpenditures(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadExpenditures(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadExpenditures(1);
    }, 350);
});

/* ==========================================================
   INIT
========================================================== */

document.getElementById('transaction_date-field').value = new Date().toISOString().substring(0, 10);
toggleChequeFields();
loadExpenditures();
