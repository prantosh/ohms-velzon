let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
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
   LIST
========================================================== */

async function loadReceipts(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/goods-receipt/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#grnTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No goods receipts found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${raw.receipt_no}</td>
            <td>${raw.receipt_date_fmt ?? ''}</td>
            <td>${raw.po_no ?? '-'}</td>
            <td>${escapeHtml(raw.vendor_name ?? '')}</td>
            <td>
                <button class="btn btn-sm btn-soft-info view-grn-btn" data-id="${raw.id}" title="View">
                    <i class="ri-eye-fill"></i>
                </button>
            </td>
        </tr>
        `;
    });
}

/* ==========================================================
   OPEN PURCHASE ORDER DROPDOWN
========================================================== */

async function loadOpenPurchaseOrders() {

    const response = await fetch('/purchase-order/open-orders');
    const result = await response.json();

    let select = document.getElementById('purchase_order_id-field');

    select.innerHTML = '<option value="">-- Direct / Local Purchase --</option>';

    if (result.status) {

        result.data.forEach(po => {

            let option = document.createElement('option');
            option.value = po.id;
            option.textContent = `${po.po_no} - ${po.vendor_name}`;
            select.appendChild(option);
        });
    }
}

document.getElementById('btnLoadPoItems').addEventListener('click', async function () {

    let poId = document.getElementById('purchase_order_id-field').value;

    if (!poId) {
        Swal.fire({ icon: 'warning', title: 'Select a purchase order first.' });
        return;
    }

    const response = await fetch(`/purchase-order/pending-items/${poId}`);
    const result = await response.json();

    if (!result.status) return;

    if (!result.items.length) {
        Swal.fire({ icon: 'info', title: 'All items on this purchase order have already been fully received.' });
        return;
    }

    document.getElementById('vendor_name-field').value = result.vendor_name ?? '';

    document.getElementById('grnItemsBody').innerHTML = '';

    result.items.forEach(item => {

        let pendingQty = (parseFloat(item.po_qty) - parseFloat(item.received_qty)).toFixed(2);

        addGrnItemRow({
            id: item.inventory_item_id,
            name: item.item.item_name,
            uom: item.uom,
            rate: item.unit_rate,
            qty: pendingQty,
            poItemId: item.id
        });
    });

    recalculateGrandTotal();
});

/* ==========================================================
   ITEM ROW MANAGEMENT
========================================================== */

function addGrnItemRow(prefill = null) {

    rowCounter++;

    let tr = document.createElement('tr');
    tr.id = `grn-row-${rowCounter}`;

    let itemName = prefill ? escapeHtml(prefill.name) : '';
    let itemId = prefill ? prefill.id : '';
    let uom = prefill ? escapeHtml(prefill.uom) : '';
    let rate = prefill ? prefill.rate : 0;
    let qty = prefill ? prefill.qty : 1;
    let poItemId = prefill && prefill.poItemId ? prefill.poItemId : '';

    tr.innerHTML = `
        <td class="item-search-wrap">
            <input type="text" class="form-control item-search-input" placeholder="Search item..." value="${itemName}">
            <input type="hidden" class="item-id-field" value="${itemId}">
            <input type="hidden" class="po-item-id-field" value="${poItemId}">
            <div class="item-suggestions"></div>
        </td>
        <td><input type="text" class="form-control uom-field" value="${uom}" readonly></td>
        <td><input type="number" step="0.01" min="0.01" class="form-control qty-field" value="${qty}"></td>
        <td><input type="number" step="0.01" min="0" class="form-control rate-field" value="${rate}"></td>
        <td class="text-end amount-cell">0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-soft-danger remove-row-btn"><i class="ri-delete-bin-5-line"></i></button>
        </td>
    `;

    document.querySelector('#grnItemsBody').appendChild(tr);

    recalculateRow(tr);
}

document.getElementById('btnAddGrnRow').addEventListener('click', () => addGrnItemRow());

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

    suggestionsBox.style.display = 'block';
}

function recalculateRow(row) {

    let qty = parseFloat(row.querySelector('.qty-field').value) || 0;
    let rate = parseFloat(row.querySelector('.rate-field').value) || 0;

    row.querySelector('.amount-cell').innerText = (qty * rate).toFixed(2);

    recalculateGrandTotal();
}

function recalculateGrandTotal() {

    let total = 0;

    document.querySelectorAll('#grnItemsBody tr').forEach(row => {
        total += parseFloat(row.querySelector('.amount-cell').innerText) || 0;
    });

    document.querySelector('#grnGrandTotal').innerText = total.toFixed(2);
}

document.getElementById('grnItemsBody').addEventListener('input', function (e) {

    let row = e.target.closest('tr');
    if (!row) return;

    if (e.target.classList.contains('item-search-input')) {
        searchItemsForRow(row, e.target.value.trim());
    }

    if (e.target.classList.contains('qty-field') || e.target.classList.contains('rate-field')) {
        recalculateRow(row);
    }
});

document.getElementById('grnItemsBody').addEventListener('click', function (e) {

    let suggestion = e.target.closest('.suggestion-item');

    if (suggestion && suggestion.dataset.id) {

        let row = suggestion.closest('tr');

        row.querySelector('.item-search-input').value = suggestion.dataset.name;
        row.querySelector('.item-id-field').value = suggestion.dataset.id;
        row.querySelector('.uom-field').value = suggestion.dataset.uom;
        row.querySelector('.po-item-id-field').value = '';

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

/* ==========================================================
   CREATE GRN
========================================================== */

document.getElementById('btnCreateGrn').addEventListener('click', async function () {

    document.getElementById('grnForm').reset();
    document.getElementById('grnItemsBody').innerHTML = '';
    document.getElementById('grnGrandTotal').innerText = '0.00';
    setFlatpickrValue('receipt_date-field', new Date().toISOString().substring(0, 10));

    await loadOpenPurchaseOrders();

    addGrnItemRow();

    new bootstrap.Offcanvas(document.getElementById('grnOffcanvas')).show();
});

document.getElementById('grnForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    let items = [];

    document.querySelectorAll('#grnItemsBody tr').forEach(row => {

        let itemId = row.querySelector('.item-id-field').value;

        if (!itemId) return;

        items.push({
            inventory_item_id: itemId,
            purchase_order_item_id: row.querySelector('.po-item-id-field').value || null,
            uom: row.querySelector('.uom-field').value,
            received_qty: row.querySelector('.qty-field').value,
            unit_rate: row.querySelector('.rate-field').value
        });
    });

    if (!items.length) {
        Swal.fire({ icon: 'warning', title: 'Add at least one item to the goods receipt.' });
        return;
    }

    let payload = {
        receipt_date: document.getElementById('receipt_date-field').value,
        purchase_order_id: document.getElementById('purchase_order_id-field').value || null,
        ref_no: document.getElementById('ref_no-field').value,
        vendor_name: document.getElementById('vendor_name-field').value,
        remarks: document.getElementById('remarks-field').value,
        items: items
    };

    const response = await fetch('/goods-receipt/store', {
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
            : (result.message ?? 'Unable to record goods receipt.');

        Swal.fire({ icon: 'error', title: 'Error', text: errorText });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Offcanvas.getInstance(document.getElementById('grnOffcanvas')).hide();

    loadReceipts(1);
});

/* ==========================================================
   VIEW / PAGINATION
========================================================== */

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadReceipts(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadReceipts(currentPage + 1);
        return;
    }

    let viewBtn = e.target.closest('.view-grn-btn');

    if (viewBtn) {

        const response = await fetch(`/goods-receipt/show/${viewBtn.dataset.id}`);
        const result = await response.json();

        if (!result.status) return;

        let grn = result.data;

        let rows = grn.items.map(item => `
            <tr>
                <td>${escapeHtml(item.item.item_name)}</td>
                <td>${item.uom}</td>
                <td class="text-end">${Number(item.received_qty).toFixed(2)}</td>
                <td class="text-end">${Number(item.unit_rate).toFixed(2)}</td>
                <td class="text-end">${Number(item.amount).toFixed(2)}</td>
            </tr>
        `).join('');

        Swal.fire({
            title: grn.receipt_no,
            html: `
                <div class="text-start">
                    <p><strong>Vendor:</strong> ${escapeHtml(grn.vendor_name ?? '-')}</p>
                    <p><strong>Against PO:</strong> ${grn.purchase_order ? grn.purchase_order.po_no : 'Direct / Local Purchase'}</p>
                    <table class="table table-bordered table-sm">
                        <thead><tr><th>Item</th><th>UOM</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `,
            width: 650
        });
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadReceipts(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadReceipts(1);
    }, 350);
});

loadReceipts();
