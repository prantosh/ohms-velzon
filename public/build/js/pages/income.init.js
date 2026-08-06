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

async function loadIncomes(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/income/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#incomeTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No income from other source entries found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        let statusBadgeClass = {
            'Active': 'bg-success',
            'Cancelled': 'bg-danger'
        }[raw.status] ?? 'bg-secondary';

        let actions = `
            <a href="/income/print/${raw.id}" target="_blank" class="btn btn-sm btn-soft-primary me-1" title="Print">
                <i class="ri-printer-line"></i>
            </a>
        `;

        if (raw.status === 'Active') {
            actions += `
            <a href="javascript:void(0)" class="btn btn-sm btn-soft-danger cancel-item-btn" data-id="${raw.id}" title="Cancel">
                <i class="ri-close-line"></i>
            </a>
            `;
        }

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td class="fw-semibold">${raw.invoice_no}</td>
            <td>${escapeHtml(raw.reference_number ?? '-')}</td>
            <td>${raw.invoice_date_fmt ?? ''}</td>
            <td>${escapeHtml(raw.category_name ?? '')}</td>
            <td>${escapeHtml(raw.patient_name ?? '-')}</td>
            <td class="text-end">${fmtMoney(raw.total_amount)}</td>
            <td>${escapeHtml(raw.payment_mode)}</td>
            <td>${escapeHtml(raw.received_by_name ?? '')}</td>
            <td><span class="badge ${statusBadgeClass}">${raw.status}</span></td>
            <td>${actions}</td>
        </tr>
        `;
    });
}

/* ==========================================================
   FORM HELPERS
========================================================== */

function resetIncomeForm() {

    document.getElementById('incomeForm').reset();
    setFlatpickrValue('transaction_date-field', new Date().toISOString().substring(0, 10));
    setFlatpickrValue('cheque_date-field', '');
    toggleChequeFields();
}

function gatherFormPayload() {

    return {
        income_category_id: document.getElementById('income_category_id-field').value,
        received_from: document.getElementById('received_from-field').value,
        transaction_date: document.getElementById('transaction_date-field').value,
        amount: document.getElementById('amount-field').value,
        payment_mode: document.getElementById('payment_mode-field').value,
        cheque_number: document.getElementById('cheque_number-field').value,
        bank_name: document.getElementById('bank_name-field').value,
        cheque_date: document.getElementById('cheque_date-field').value,
        reference_number: document.getElementById('reference_number-field').value,
        received_by: document.getElementById('received_by-field').value,
        remarks: document.getElementById('remarks-field').value
    };
}

/* ==========================================================
   SUBMIT -> OFFCANVAS CONFIRMATION -> SAVE
========================================================== */

document.getElementById('incomeForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    // populate review fields and show confirmation offcanvas
    let categorySelect = document.getElementById('income_category_id-field');
    let receivedBySelect = document.getElementById('received_by-field');

    document.getElementById('review-category').innerText = categorySelect.options[categorySelect.selectedIndex]?.text ?? '-';
    document.getElementById('review-received_from').innerText = document.getElementById('received_from-field').value || '-';
    document.getElementById('review-transaction_date').innerText = document.getElementById('transaction_date-field').value || '-';
    document.getElementById('review-amount').innerText = '₹ ' + fmtMoney(document.getElementById('amount-field').value);
    document.getElementById('review-payment_mode').innerText = document.getElementById('payment_mode-field').value || '-';
    document.getElementById('review-cheque_number').innerText = document.getElementById('cheque_number-field').value || '-';
    document.getElementById('review-bank_name').innerText = document.getElementById('bank_name-field').value || '-';
    document.getElementById('review-cheque_date').innerText = document.getElementById('cheque_date-field').value || '-';
    document.getElementById('review-reference_number').innerText = document.getElementById('reference_number-field').value || '-';
    document.getElementById('review-received_by').innerText = receivedBySelect.options[receivedBySelect.selectedIndex]?.text ?? '-';
    document.getElementById('review-remarks').innerText = document.getElementById('remarks-field').value || '-';

    new bootstrap.Offcanvas(document.getElementById('confirmIncomeOffcanvas')).show();
});

document.getElementById('btnBackToEditIncome').addEventListener('click', function () {
    bootstrap.Offcanvas.getInstance(document.getElementById('confirmIncomeOffcanvas'))?.hide();
});

document.getElementById('btnConfirmSaveIncome').addEventListener('click', async function () {

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

    const response = await fetch('/income/store', {
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
            : (result.message ?? 'Unable to record income from other source entry.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    bootstrap.Offcanvas.getInstance(document.getElementById('confirmIncomeOffcanvas'))?.hide();

    window.open(`/income/print/${result.invoice_id}`, '_blank');

    Swal.fire({ icon: 'success', title: 'Saved', text: result.message });

    resetIncomeForm();

    loadIncomes(1);
});

/* ==========================================================
   CANCEL / PAGINATION
========================================================== */

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadIncomes(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadIncomes(currentPage + 1);
        return;
    }

    let cancelBtn = e.target.closest('.cancel-item-btn');

    if (cancelBtn) {

        let id = cancelBtn.dataset.id;

        let confirm = await Swal.fire({
            title: 'Cancel this entry?',
            text: 'The received amount will be refunded in the ledger and the entry marked cancelled.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ab39c',
            cancelButtonColor: '#f06548',
            confirmButtonText: 'Yes, Cancel'
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/income/cancel/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Cancelled' : 'Error',
            text: result.message
        });

        if (result.status) loadIncomes(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadIncomes(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadIncomes(1);
    }, 350);
});

/* ==========================================================
   INIT
========================================================== */

document.getElementById('transaction_date-field').value = new Date().toISOString().substring(0, 10);
toggleChequeFields();
loadIncomes();
