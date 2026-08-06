let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let statusFilter = '';
let rowCounter = 0;

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

/* ==========================================================
   PO LIST
========================================================== */

async function loadPurchaseOrders(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/purchase-order/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}&status=${statusFilter}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#poTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No purchase orders found.</td></tr>';
        return;
    }

    let statusColors = { OPEN: 'primary', CLOSED: 'success', CANCELLED: 'danger' };

    result.data.forEach((raw, index) => {

        let color = statusColors[raw.status] || 'secondary';

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${raw.po_no}</td>
            <td>${raw.po_date_fmt ?? ''}</td>
            <td>${escapeHtml(raw.vendor_name)}</td>
            <td class="text-end">${Number(raw.total_value).toFixed(2)}</td>
            <td><span class="badge bg-${color}-subtle text-${color}">${raw.status}</span></td>
            <td>
                <button class="btn btn-sm btn-soft-info view-po-btn" data-id="${raw.id}" title="View">
                    <i class="ri-eye-fill"></i>
                </button>
                ${raw.status === 'OPEN' ? `
                <button class="btn btn-sm btn-soft-danger cancel-po-btn" data-id="${raw.id}" title="Cancel">
                    <i class="ri-close-circle-line"></i>
                </button>` : ''}
            </td>
        </tr>
        `;
    });
}

/* ==========================================================
   ITEM ROW MANAGEMENT
========================================================== */

function addPoItemRow() {

    rowCounter++;

    let rowId = `row-${rowCounter}`;

    let tr = document.createElement('tr');
    tr.id = rowId;

    tr.innerHTML = `
        <td class="item-search-wrap">
            <input type="text" class="form-control item-search-input" placeholder="Search item...">
            <input type="hidden" class="item-id-field">
            <div class="item-suggestions"></div>
        </td>
        <td><input type="text" class="form-control uom-field" readonly></td>
        <td><input type="number" step="0.01" min="0.01" class="form-control qty-field" value="1"></td>
        <td><input type="number" step="0.01" min="0" class="form-control rate-field" value="0"></td>
        <td><input type="number" step="0.01" min="0" max="100" class="form-control gst-field" value="0"></td>
        <td class="text-end amount-cell">0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-soft-danger remove-row-btn"><i class="ri-delete-bin-5-line"></i></button>
        </td>
    `;

    document.querySelector('#poItemsBody').appendChild(tr);
}

document.getElementById('btnAddPoRow').addEventListener('click', addPoItemRow);

function positionSuggestionsBox(row, suggestionsBox) {

    let input = row.querySelector('.item-search-input');
    let rect = input.getBoundingClientRect();

    suggestionsBox.style.top = rect.bottom + 'px';
    suggestionsBox.style.left = rect.left + 'px';
    suggestionsBox.style.width = rect.width + 'px';
}

async function searchItemsForRow(row, term) {

    let suggestionsBox = row.querySelector('.item-suggestions');

    if (!term) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        return;
    }

    const response = await fetch(`/inventory-item/search-by-name?term=${encodeURIComponent(term)}`);
    const result = await response.json();

    if (!result.status || !result.items.length) {
        suggestionsBox.innerHTML = '<div class="suggestion-item text-muted">No items found</div>';
        positionSuggestionsBox(row, suggestionsBox);
        suggestionsBox.style.display = 'block';
        return;
    }

    suggestionsBox.innerHTML = result.items.map(item => `
        <div class="suggestion-item"
             data-id="${item.id}"
             data-name="${escapeHtml(item.item_name)}"
             data-uom="${escapeHtml(item.uom)}"
             data-rate="${item.avg_rate}">
            <strong>${escapeHtml(item.item_name)}</strong> (${escapeHtml(item.item_code)}) - ${escapeHtml(item.uom)}, Stock: ${item.current_stock}
        </div>
    `).join('');

    positionSuggestionsBox(row, suggestionsBox);
    suggestionsBox.style.display = 'block';
}

function recalculateRow(row) {

    let qty = parseFloat(row.querySelector('.qty-field').value) || 0;
    let rate = parseFloat(row.querySelector('.rate-field').value) || 0;

    let amount = qty * rate;

    row.querySelector('.amount-cell').innerText = amount.toFixed(2);

    recalculateGrandTotal();
}

function recalculateGrandTotal() {

    let total = 0;

    document.querySelectorAll('#poItemsBody tr').forEach(row => {
        total += parseFloat(row.querySelector('.amount-cell').innerText) || 0;
    });

    document.querySelector('#poGrandTotal').innerText = total.toFixed(2);
}

document.getElementById('poItemsBody').addEventListener('input', function (e) {

    let row = e.target.closest('tr');
    if (!row) return;

    if (e.target.classList.contains('item-search-input')) {
        searchItemsForRow(row, e.target.value.trim());
    }

    if (e.target.classList.contains('qty-field') || e.target.classList.contains('rate-field')) {
        recalculateRow(row);
    }
});

document.getElementById('poItemsBody').addEventListener('click', function (e) {

    let suggestion = e.target.closest('.suggestion-item');

    if (suggestion && suggestion.dataset.id) {

        let row = suggestion.closest('tr');

        row.querySelector('.item-search-input').value = suggestion.dataset.name;
        row.querySelector('.item-id-field').value = suggestion.dataset.id;
        row.querySelector('.uom-field').value = suggestion.dataset.uom;

        if (parseFloat(row.querySelector('.rate-field').value) === 0 && suggestion.dataset.rate) {
            row.querySelector('.rate-field').value = suggestion.dataset.rate;
        }

        row.querySelector('.item-suggestions').style.display = 'none';

        recalculateRow(row);
        return;
    }

    let removeBtn = e.target.closest('.remove-row-btn');

    if (removeBtn) {
        removeBtn.closest('tr').remove();
        recalculateGrandTotal();
    }
});

document.addEventListener('click', function (e) {

    if (!e.target.closest('.item-search-wrap')) {
        document.querySelectorAll('.item-suggestions').forEach(box => box.style.display = 'none');
    }
});

// .item-suggestions is position:fixed (to escape .table-responsive's overflow
// clipping), so it won't follow the input if an ancestor scrolls -- hide it
// instead of leaving it floating in the wrong place. Scrolling inside the
// suggestions list itself (it has its own overflow-y:auto) must NOT close it.
document.addEventListener('scroll', function (e) {
    if (e.target.closest && e.target.closest('.item-suggestions')) {
        return;
    }
    document.querySelectorAll('.item-suggestions').forEach(box => box.style.display = 'none');
}, true);

/* ==========================================================
   CREATE PO
========================================================== */

document.getElementById('btnCreatePo').addEventListener('click', function () {

    document.getElementById('poForm').reset();
    document.getElementById('poItemsBody').innerHTML = '';
    document.getElementById('poGrandTotal').innerText = '0.00';
    setFlatpickrValue('po_date-field', new Date().toISOString().substring(0, 10));
    setFlatpickrValue('requisition_date-field', '');

    addPoItemRow();

    new bootstrap.Offcanvas(document.getElementById('poOffcanvas')).show();
});

document.getElementById('poForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    let items = [];

    document.querySelectorAll('#poItemsBody tr').forEach(row => {

        let itemId = row.querySelector('.item-id-field').value;

        if (!itemId) return;

        items.push({
            inventory_item_id: itemId,
            uom: row.querySelector('.uom-field').value,
            po_qty: row.querySelector('.qty-field').value,
            unit_rate: row.querySelector('.rate-field').value,
            gst_percent: row.querySelector('.gst-field').value || 0
        });
    });

    if (!items.length) {
        Swal.fire({ icon: 'warning', title: 'Add at least one item to the purchase order.' });
        return;
    }

    let payload = {
        po_date: document.getElementById('po_date-field').value,
        vendor_name: document.getElementById('vendor_name-field').value,
        payment_term: document.getElementById('payment_term-field').value,
        requisition_ref: document.getElementById('requisition_ref-field').value,
        requisition_date: document.getElementById('requisition_date-field').value,
        note: document.getElementById('note-field').value,
        items: items
    };

    const response = await fetch('/purchase-order/store', {
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
            : (result.message ?? 'Unable to create purchase order.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Offcanvas.getInstance(document.getElementById('poOffcanvas')).hide();

    loadPurchaseOrders(1);
});

/* ==========================================================
   VIEW / CANCEL
========================================================== */

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadPurchaseOrders(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadPurchaseOrders(currentPage + 1);
        return;
    }

    let viewBtn = e.target.closest('.view-po-btn');

    if (viewBtn) {

        const response = await fetch(`/purchase-order/show/${viewBtn.dataset.id}`);
        const result = await response.json();

        if (!result.status) return;

        let po = result.data;

        let rows = po.items.map(item => `
            <tr>
                <td>${escapeHtml(item.item.item_name)}</td>
                <td>${item.uom}</td>
                <td class="text-end">${Number(item.po_qty).toFixed(2)}</td>
                <td class="text-end">${Number(item.received_qty).toFixed(2)}</td>
                <td class="text-end">${Number(item.unit_rate).toFixed(2)}</td>
                <td class="text-end">${Number(item.amount).toFixed(2)}</td>
            </tr>
        `).join('');

        document.getElementById('viewPoBody').innerHTML = `
            <div class="row mb-3">
                <div class="col-md-6"><strong>PO No:</strong> ${po.po_no}</div>
                <div class="col-md-6"><strong>Status:</strong> ${po.status}</div>
                <div class="col-md-6"><strong>Vendor:</strong> ${escapeHtml(po.vendor_name)}</div>
                <div class="col-md-6"><strong>Payment Term:</strong> ${po.payment_term ?? '-'}</div>
            </div>
            <table class="table table-bordered table-sm">
                <thead class="table-light">
                    <tr><th>Item</th><th>UOM</th><th>PO Qty</th><th>Received</th><th>Rate</th><th>Amount</th></tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
            <div class="text-end fw-bold">Total: &#8377; ${Number(po.total_value).toFixed(2)}</div>
            ${po.note ? `<div class="mt-2"><strong>Note:</strong> ${escapeHtml(po.note)}</div>` : ''}
        `;

        new bootstrap.Modal(document.getElementById('viewPoModal')).show();
        return;
    }

    let cancelBtn = e.target.closest('.cancel-po-btn');

    if (cancelBtn) {

        let confirm = await Swal.fire({
            title: 'Cancel Purchase Order?',
            text: 'This purchase order will be marked as cancelled.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f06548',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Cancel'
        });

        if (!confirm.isConfirmed) return;

        const response = await fetch(`/purchase-order/cancel/${cancelBtn.dataset.id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        Swal.fire({
            icon: result.status ? 'success' : 'error',
            title: result.status ? 'Cancelled' : 'Error',
            text: result.message
        });

        if (result.status) loadPurchaseOrders(currentPage);
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadPurchaseOrders(1);
    }

    if (e.target.id === 'statusFilter') {
        statusFilter = e.target.value;
        loadPurchaseOrders(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadPurchaseOrders(1);
    }, 350);
});

loadPurchaseOrders();
