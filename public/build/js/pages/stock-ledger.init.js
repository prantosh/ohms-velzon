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

async function loadStockSummary(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/stock-ledger/summary?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#stockTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No items found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${raw.item_code}</td>
            <td>${escapeHtml(raw.item_name)}</td>
            <td>${raw.category_name ?? '-'}</td>
            <td>${raw.uom}</td>
            <td class="text-end">${Number(raw.current_stock).toFixed(2)}</td>
            <td class="text-end">${Number(raw.avg_rate).toFixed(2)}</td>
            <td class="text-end">${Number(raw.stock_value).toFixed(2)}</td>
            <td>
                <button class="btn btn-sm btn-soft-info view-ledger-btn"
                        data-id="${raw.id}"
                        data-name="${escapeHtml(raw.item_name)}"
                        title="View Ledger">
                    <i class="ri-file-list-3-line"></i>
                </button>
            </td>
        </tr>
        `;
    });
}

async function loadLedgerDetail() {

    let payload = {
        inventory_item_id: document.getElementById('ledger_item_id-field').value,
        from_date: document.getElementById('ledger_from_date-field').value || null,
        to_date: document.getElementById('ledger_to_date-field').value || null
    };

    const response = await fetch('/stock-ledger/detail', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!result.status) return;

    let printParams = new URLSearchParams({ inventory_item_id: payload.inventory_item_id });
    if (payload.from_date) printParams.set('from_date', payload.from_date);
    if (payload.to_date) printParams.set('to_date', payload.to_date);

    let printBtn = document.getElementById('btnPrintLedger');
    printBtn.href = `/stock-ledger/print-detail?${printParams.toString()}`;
    printBtn.style.display = 'inline-flex';

    document.getElementById('ledgerOpening').innerText =
        `${Number(result.opening_qty).toFixed(2)} ${result.item.uom} (₹ ${Number(result.opening_value).toFixed(2)})`;

    document.getElementById('ledgerClosing').innerText =
        `${Number(result.closing_qty).toFixed(2)} ${result.item.uom} (₹ ${Number(result.closing_value).toFixed(2)})`;

    let tbody = document.getElementById('ledgerTableBody');

    if (!result.ledger.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No transactions in this period.</td></tr>';
        return;
    }

    tbody.innerHTML = result.ledger.map(row => {

        let typeBadge = row.type === 'RECEIPT'
            ? '<span class="badge bg-success-subtle text-success">RECEIPT</span>'
            : '<span class="badge bg-danger-subtle text-danger">ISSUE</span>';

        return `
        <tr>
            <td>${row.txn_date}</td>
            <td>${row.txn_no}</td>
            <td>${typeBadge}</td>
            <td class="text-end">${row.qty.toFixed(2)}</td>
            <td class="text-end">${row.rate.toFixed(2)}</td>
            <td class="text-end">${row.value.toFixed(2)}</td>
            <td class="text-end">${row.balance_qty.toFixed(2)}</td>
            <td class="text-end">${row.balance_value.toFixed(2)}</td>
        </tr>
        `;
    }).join('');
}

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadStockSummary(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadStockSummary(currentPage + 1);
        return;
    }

    let ledgerBtn = e.target.closest('.view-ledger-btn');

    if (ledgerBtn) {

        document.getElementById('ledger_item_id-field').value = ledgerBtn.dataset.id;
        document.getElementById('ledgerItemTitle').innerText = `Stock Ledger - ${ledgerBtn.dataset.name}`;
        setFlatpickrValue('ledger_from_date-field', '');
        setFlatpickrValue('ledger_to_date-field', '');

        await loadLedgerDetail();

        new bootstrap.Offcanvas(document.getElementById('ledgerOffcanvas')).show();
    }
});

document.getElementById('btnLoadLedger').addEventListener('click', loadLedgerDetail);

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadStockSummary(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadStockSummary(1);
    }, 350);
});

loadStockSummary();
