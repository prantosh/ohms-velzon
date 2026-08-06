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

function fmtQty(value) {
    return Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

let requestSeq = 0;

/* ==========================================================
   DATE MODE TOGGLE
========================================================== */

function toggleDateMode() {
    const isYearEnd = document.getElementById('dateModeYearEnd').checked;
    document.getElementById('anyDateWrap').style.display = isYearEnd ? 'none' : '';
    document.getElementById('yearEndWrap').style.display = isYearEnd ? '' : 'none';
}

document.getElementById('dateModeAny').addEventListener('change', toggleDateMode);
document.getElementById('dateModeYearEnd').addEventListener('change', toggleDateMode);

function populateYearEndOptions() {
    const select = document.getElementById('year_end-field');
    const currentYear = new Date().getFullYear();
    select.innerHTML = '';
    for (let y = currentYear; y >= currentYear - 10; y--) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = `31-Mar-${y}`;
        select.appendChild(opt);
    }
}

populateYearEndOptions();

/* ==========================================================
   FILTERS
========================================================== */

function resolveAsOnDate() {
    if (document.getElementById('dateModeYearEnd').checked) {
        const year = document.getElementById('year_end-field').value;
        return `${year}-03-31`;
    }
    return document.getElementById('as_on_date-field').value;
}

function currentFilters() {
    const filters = {
        as_on_date: resolveAsOnDate(),
    };
    const categoryId = document.getElementById('category_id-field').value;
    if (categoryId) filters.inventory_category_id = categoryId;
    const search = document.getElementById('search-field').value.trim();
    if (search) filters.search = search;
    return filters;
}

/* ==========================================================
   RENDER
========================================================== */

function renderResult(result, params) {

    document.getElementById('totalsRow').style.display = 'none';
    document.getElementById('resultCard').style.display = 'none';
    document.getElementById('noDataWrap').style.display = 'none';
    document.getElementById('printBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('noDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('total-qty').innerText = fmtQty(g.closing_qty);
    document.getElementById('total-value').innerText = '₹' + fmtMoney(g.closing_value);
    document.getElementById('asOnDateLabel').innerText = result.as_on_date;

    document.getElementById('totalsRow').style.display = 'flex';

    let tbody = document.getElementById('resultTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.item_code)}</td>
            <td>${escapeHtml(row.item_name)}</td>
            <td>${escapeHtml(row.category_name) || '-'}</td>
            <td class="text-center">${escapeHtml(row.uom)}</td>
            <td class="text-end">${fmtQty(row.closing_qty)}</td>
            <td class="text-end">${fmtMoney(row.effective_rate)}</td>
            <td class="text-end">${fmtMoney(row.closing_value)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td colspan="4" class="text-end">Total</td>
        <td class="text-end">${fmtQty(g.closing_qty)}</td>
        <td></td>
        <td class="text-end">${fmtMoney(g.closing_value)}</td>
    </tr>
    `;

    document.getElementById('resultCard').style.display = 'block';

    let printBtn = document.getElementById('printBtn');
    printBtn.href = `/stock-as-on-date/print?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

async function loadReport() {

    let filters = currentFilters();

    if (!filters.as_on_date) {
        Swal.fire({ icon: 'warning', title: 'Missing Filter', text: 'Please select a cutoff date.' });
        return;
    }

    const mySeq = ++requestSeq;

    const params = new URLSearchParams(filters);

    const response = await fetch(`/stock-as-on-date/report?${params.toString()}`);
    const result = await response.json();

    if (mySeq !== requestSeq) return;

    renderResult(result, params);
}

document.getElementById('loadReportBtn').addEventListener('click', loadReport);

/* ==========================================================
   INIT (default: today)
========================================================== */

setFlatpickrValue('as_on_date-field', new Date().toISOString().substring(0, 10));
