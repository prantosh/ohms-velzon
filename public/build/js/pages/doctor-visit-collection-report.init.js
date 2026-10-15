function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function fmtMoney(value) {
    return Number(value || 0).toFixed(2);
}

// A date cell shows its own visit count and collected amount for that
// specialisation -- summing every column's "collected" across a row equals
// that row's own Collected total, the same way every date's visit count
// sums to the row's Visits total.
function renderDateCell(stats) {
    stats = stats || { visits: 0, collected: 0 };
    if (!stats.visits) {
        return `<td class="text-end text-muted">0</td>`;
    }
    return `<td class="text-end">
        <div class="fw-semibold">${fmtMoney(stats.collected)}</div>
        <div class="small text-muted">${stats.visits} visit${stats.visits === 1 ? '' : 's'}</div>
    </td>`;
}

/* ==========================================================
   SUMMARY TAB
========================================================== */

let summaryRequestSeq = 0;

async function loadSummary() {

    let fromDate = document.getElementById('summary_from_date-field').value;
    let toDate = document.getElementById('summary_to_date-field').value;

    if (!fromDate || !toDate) {
        Swal.fire({ icon: 'warning', title: 'Missing Dates', text: 'Please select both From Date and To Date.' });
        return;
    }

    const mySeq = ++summaryRequestSeq;

    const params = new URLSearchParams({ from_date: fromDate, to_date: toDate });

    // Explicit Accept header -- a 7-day-range validation failure must come
    // back as JSON (so the error below can read it), not Laravel's default
    // redirect-back for a non-JSON-expecting request.
    const response = await fetch(`/doctor-visit-collection-report/summary?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });
    const result = await response.json();

    if (mySeq !== summaryRequestSeq) return;

    document.getElementById('summaryTotalsRow').style.display = 'none';
    document.getElementById('summaryCard').style.display = 'none';
    document.getElementById('summaryNoDataWrap').style.display = 'none';
    document.getElementById('printSummaryBtn').style.display = 'none';

    if (!result.status) {
        Swal.fire({ icon: 'error', title: 'Could Not Load', text: (result.errors && result.errors.to_date && result.errors.to_date[0]) || 'Something went wrong.' });
        return;
    }

    if (!result.rows.length) {
        document.getElementById('summaryNoDataWrap').style.display = 'block';
        return;
    }

    let g = result.grand_total;

    document.getElementById('grand-visits').innerText = g.visits;
    document.getElementById('grand-collected').innerText = fmtMoney(g.collected);
    document.getElementById('grand-doctor_share').innerText = fmtMoney(g.doctor_share);
    document.getElementById('grand-clinic_share').innerText = fmtMoney(g.clinic_share);

    document.getElementById('summaryTotalsRow').style.display = 'flex';

    // Date columns are every day in the selected range (max 7) -- rebuild
    // the header every load, same as the specialisation-column approach
    // this replaced.
    const dateColumns = result.date_columns || [];
    const headRow = document.getElementById('summaryTableHeadRow');
    headRow.querySelectorAll('.dynamic-date-col').forEach(th => th.remove());
    const actionTh = headRow.lastElementChild;
    dateColumns.forEach(col => {
        let th = document.createElement('th');
        th.className = 'text-end dynamic-date-col';
        th.textContent = col.label;
        headRow.insertBefore(th, actionTh);
    });

    let tbody = document.getElementById('summaryTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        let dateCells = dateColumns.map(col => renderDateCell(row.dates[col.key])).join('');
        tbody.innerHTML += `
        <tr>
            <td class="fw-semibold">${escapeHtml(row.specialisation)}</td>
            <td class="text-end">${row.visits}</td>
            <td class="text-end text-success">${fmtMoney(row.collected)}</td>
            <td class="text-end text-info">${fmtMoney(row.doctor_share)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.clinic_share)}</td>
            ${dateCells}
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-soft-primary view-spec-btn" data-specialisation="${escapeHtml(row.specialisation)}">
                    <i class="ri-eye-line"></i> View
                </button>
            </td>
        </tr>
        `;
    });

    let totalDateCells = dateColumns.map(col => renderDateCell(g.dates[col.key])).join('');
    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td>Total</td>
        <td class="text-end">${g.visits}</td>
        <td class="text-end">${fmtMoney(g.collected)}</td>
        <td class="text-end">${fmtMoney(g.doctor_share)}</td>
        <td class="text-end">${fmtMoney(g.clinic_share)}</td>
        ${totalDateCells}
        <td></td>
    </tr>
    `;

    document.getElementById('summaryCard').style.display = 'block';

    let printBtn = document.getElementById('printSummaryBtn');
    printBtn.href = `/doctor-visit-collection-report/print-summary?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadSummaryBtn').addEventListener('click', loadSummary);

document.getElementById('summaryTableBody').addEventListener('click', function (e) {

    let btn = e.target.closest('.view-spec-btn');
    if (!btn) return;

    // Jump to the Detail tab and pre-fill the specialisation -- date still
    // needs picking, since this row spans the whole range.
    document.querySelector('a[href="#detailTab"]').click();
    document.getElementById('detail_specialisation-field').value = btn.dataset.specialisation;
});

/* ==========================================================
   DETAIL TAB
========================================================== */

let detailRequestSeq = 0;

async function loadDetail() {

    let date = document.getElementById('detail_date-field').value;
    let specialisation = document.getElementById('detail_specialisation-field').value;

    if (!date || !specialisation) {
        Swal.fire({ icon: 'warning', title: 'Missing Selection', text: 'Please select both a Date and a Specialisation.' });
        return;
    }

    const mySeq = ++detailRequestSeq;

    const params = new URLSearchParams({ date: date, specialisation: specialisation });

    const response = await fetch(`/doctor-visit-collection-report/detail?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });
    const result = await response.json();

    if (mySeq !== detailRequestSeq) return;

    document.getElementById('detailTotalsRow').style.display = 'none';
    document.getElementById('detailCard').style.display = 'none';
    document.getElementById('detailNoDataWrap').style.display = 'none';
    document.getElementById('printDetailBtn').style.display = 'none';

    if (!result.status || !result.rows.length) {
        document.getElementById('detailNoDataWrap').style.display = 'block';
        return;
    }

    let s = result.summary;

    document.getElementById('detail-visits').innerText = s.visits;
    document.getElementById('detail-collected').innerText = fmtMoney(s.collected);
    document.getElementById('detail-doctor_share').innerText = fmtMoney(s.doctor_share);
    document.getElementById('detail-clinic_share').innerText = fmtMoney(s.clinic_share);

    document.getElementById('detailTotalsRow').style.display = 'flex';

    document.getElementById('detailCardTitle').innerText =
        'Visit Detail -- ' + specialisation + ' -- ' + date;

    let tbody = document.getElementById('detailTableBody');
    tbody.innerHTML = '';

    result.rows.forEach(row => {
        tbody.innerHTML += `
        <tr>
            <td>${escapeHtml(row.time_fmt)}</td>
            <td class="fw-semibold">${escapeHtml(row.invoice_no)}</td>
            <td>${escapeHtml(row.patient_name) || '-'}</td>
            <td>${escapeHtml(row.doctor_name) || '-'}</td>
            <td class="text-end text-success">${fmtMoney(row.collected)}</td>
            <td class="text-end text-info">${fmtMoney(row.doctor_share)}</td>
            <td class="text-end fw-semibold">${fmtMoney(row.clinic_share)}</td>
        </tr>
        `;
    });

    tbody.innerHTML += `
    <tr class="table-light fw-bold">
        <td colspan="4">Total</td>
        <td class="text-end">${fmtMoney(s.collected)}</td>
        <td class="text-end">${fmtMoney(s.doctor_share)}</td>
        <td class="text-end">${fmtMoney(s.clinic_share)}</td>
    </tr>
    `;

    document.getElementById('detailCard').style.display = 'block';

    let printBtn = document.getElementById('printDetailBtn');
    printBtn.href = `/doctor-visit-collection-report/print-detail?${params.toString()}`;
    printBtn.style.display = 'inline-block';
}

document.getElementById('loadDetailBtn').addEventListener('click', loadDetail);

/* ==========================================================
   INIT (default: last 7 days for summary, today for detail)
========================================================== */

document.getElementById('summary_to_date-field').value = new Date().toISOString().substring(0, 10);
document.getElementById('summary_from_date-field').value = new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10);
document.getElementById('detail_date-field').value = new Date().toISOString().substring(0, 10);

/* ==========================================================
   SUMMARY DATE RANGE CAP (7 days at a time) -- constrains the "To Date"
   picker's own calendar so the user can't even select an invalid range,
   on top of the server-side check in DoctorVisitCollectionReportController.
   Runs after DOMContentLoaded so flatpickr-dmy.init.js's own
   DOMContentLoaded handler (which creates ._flatpickr) has already run --
   it was registered first (loaded as an earlier <script> tag), and
   same-event listeners fire in registration order.
========================================================== */

document.addEventListener('DOMContentLoaded', function () {

    const fromField = document.getElementById('summary_from_date-field');
    const toField = document.getElementById('summary_to_date-field');

    function addDays(dateStr, days) {
        let d = new Date(dateStr + 'T00:00:00');
        d.setDate(d.getDate() + days);
        return d.toISOString().substring(0, 10);
    }

    function constrainToDate() {
        if (!fromField.value || !toField._flatpickr) return;
        toField._flatpickr.set('minDate', fromField.value);
        toField._flatpickr.set('maxDate', addDays(fromField.value, 6));
    }

    if (fromField._flatpickr) {
        constrainToDate();
        fromField._flatpickr.config.onChange.push(constrainToDate);
    }
});
