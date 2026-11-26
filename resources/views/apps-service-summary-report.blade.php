@extends('layouts.master')

@section('title')
    Service Summary Report
@endsection

@section('css')
<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')

@component('components.breadcrumb')
    @slot('li_1')
        Reports
    @endslot
    @slot('title')
        Service Summary (Date-wise)
    @endslot
@endcomponent

<div class="alert alert-info">
    <i class="ri-information-line"></i>
    Date-wise version of the home page <b>Today's Services</b> tiles. <b>Appointments</b> = booked (cancelled excluded)
    by appointment date; <b>Visited</b> = those appointments that have a non-cancelled invoice. Service columns count
    invoice lines on non-cancelled invoices by invoice date. <b>Doctor</b> filters the appointment's doctor / the
    line's doctor; <b>Employee</b> filters who booked the appointment / who created the invoice. Only dates with
    activity are listed.
</div>

<div class="card">
    <div class="card-header"><h5 class="card-title mb-0">Filters</h5></div>
    <div class="card-body">
        <div class="row align-items-end">

            <div class="col-md-2 mb-3">
                <label class="form-label">From Date <span class="text-danger">*</span></label>
                <input type="text" id="from_date" class="form-control flatpickr" required>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">To Date <span class="text-danger">*</span></label>
                <input type="text" id="to_date" class="form-control flatpickr" required>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Item <small class="text-muted">(none = all)</small></label>
                <select id="items" class="form-select" multiple size="4">
                    @foreach($items as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Sub Item <small class="text-muted">(none = all)</small></label>
                <select id="sub_items" class="form-select" multiple size="4"></select>
            </div>

            <div class="col-md-2 mb-3">
                <label class="form-label">Doctor</label>
                <select id="doctor_id" class="form-select">
                    <option value="">All Doctors</option>
                    @foreach($doctors as $d)
                        <option value="{{ $d->id }}">{{ $d->doctor_name }}</option>
                    @endforeach
                </select>

                <label class="form-label mt-2">Employee</label>
                <select id="employee_id" class="form-select">
                    <option value="">All Employees</option>
                    @foreach($employees as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-1 mb-3">
                <button type="button" class="btn btn-primary w-100 mb-2" id="loadBtn">
                    <i class="ri-search-line"></i> Load
                </button>
                <a href="javascript:void(0)" class="btn btn-success w-100" id="printBtn" style="display:none;" target="_blank">
                    <i class="ri-printer-line"></i> Print PDF
                </a>
            </div>

        </div>
    </div>
</div>

<div class="card" id="resultCard" style="display:none;">
    <div class="card-header"><h5 class="card-title mb-0" id="resultTitle">Date-wise Summary</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle text-center">
                <thead class="table-light" id="thead"></thead>
                <tbody id="tbody"></tbody>
                <tfoot class="table-light fw-bold" id="tfoot"></tfoot>
            </table>
        </div>
        <div id="noData" class="alert alert-warning mb-0" style="display:none;">
            No activity found for the selected filters.
        </div>
    </div>
</div>

<div class="card" id="detailCard" style="display:none;">
    <div class="card-header"><h5 class="card-title mb-0">Service Line Details</h5></div>
    <div class="card-body">
        <p class="text-muted small mb-2">Received / Pending are each invoice's payment status shared pro-rata across its lines.
            Discount = Gross Amount &minus; Net Amount.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle text-center">
                <thead class="table-light"><tr>
                    <th>Date</th><th>Invoice No</th><th class="text-start">Patient</th>
                    <th class="text-start">Item Group</th><th class="text-start">Sub Item</th>
                    <th class="text-end">Gross Amount</th><th class="text-end">Discount</th><th class="text-end">Net Amount</th>
                    <th class="text-end">Received</th><th class="text-end">Pending</th>
                </tr></thead>
                <tbody id="detailBody"></tbody>
                <tfoot class="table-light fw-bold" id="detailFoot"></tfoot>
            </table>
        </div>
    </div>
</div>

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const $ = id => document.getElementById(id);
    const fmtDate = s => s.split('-').reverse().join('-');

    // default: first of this month -> today
    const now = new Date(), pad = n => String(n).padStart(2, '0');
    const today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    $('from_date')._flatpickr.setDate(now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-01');
    $('to_date')._flatpickr.setDate(today);

    // Sub-item list follows the selected Item(s); empty until an Item is chosen (empty = all sub-items)
    const subMap = @json($subItems);
    function fillSubItems() {
        const sel = Array.from($('items').selectedOptions).map(o => o.value);
        const codes = sel.filter(c => subMap[c]); // no Item selected => list stays empty
        const keep = new Set(Array.from($('sub_items').selectedOptions).map(o => o.value));
        const specific = codes.flatMap(c => subMap[c].map(s =>
            `<option value="${s.code}"${keep.has(s.code) ? ' selected' : ''}>${s.name}</option>`)).join('');
        const anySpecific = Array.from(keep).some(v => v !== '');
        $('sub_items').innerHTML = codes.length
            ? `<option value=""${anySpecific ? '' : ' selected'}>ALL ITEMS</option>` + specific
            : '';
        allWasSelected = codes.length > 0 && !anySpecific;
    }

    // "ALL ITEMS" (first option) and specific sub-items are mutually exclusive
    let allWasSelected = false;
    $('sub_items').addEventListener('change', function () {
        const opts = Array.from(this.options);
        const all = opts[0];
        if (!all || all.value !== '') return;
        const others = opts.slice(1).filter(o => o.selected);
        if (all.selected && !allWasSelected) {
            others.forEach(o => o.selected = false);       // user just picked ALL ITEMS
        } else if (all.selected && others.length) {
            all.selected = false;                           // user picked a specific one
        }
        if (!opts.some(o => o.selected)) all.selected = true;
        allWasSelected = all.selected;
    });
    $('items').addEventListener('change', fillSubItems);
    fillSubItems();

    function params() {
        const p = new URLSearchParams();
        p.set('from_date', $('from_date').value);
        p.set('to_date', $('to_date').value);
        Array.from($('items').selectedOptions).forEach(o => p.append('items[]', o.value));
        Array.from($('sub_items').selectedOptions).filter(o => o.value !== '').forEach(o => p.append('sub_items[]', o.value));
        if ($('doctor_id').value) p.set('doctor_id', $('doctor_id').value);
        if ($('employee_id').value) p.set('employee_id', $('employee_id').value);
        return p.toString();
    }

    $('loadBtn').addEventListener('click', function () {
        if (!$('from_date').value || !$('to_date').value) {
            alert('Please select From and To dates.');
            return;
        }
        fetch('{{ url('service-summary-report/report') }}?' + params(), { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(res => {
                const cols = res.columns;
                const fmt = (c, v) => c.money ? Number(v || 0).toFixed(2) : (v || 0);
                const m = v => Number(v || 0).toFixed(2);
                const esc = t => String(t ?? '').replace(/[&<>]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[ch]));
                const det = res.details || [];
                const sum = k => det.reduce((a, d) => a + Number(d[k] || 0), 0);
                $('detailBody').innerHTML = det.map(d =>
                    `<tr><td>${fmtDate(d.date)}</td><td>${esc(d.invoice_no)}</td><td class="text-start">${esc(d.patient)}</td>` +
                    `<td class="text-start">${esc(d.group)}</td><td class="text-start">${esc(d.sub_item)}</td>` +
                    ['gross','discount','net','received','pending'].map(k => `<td class="text-end">${m(d[k])}</td>`).join('') + '</tr>').join('');
                $('detailFoot').innerHTML = det.length
                    ? '<tr><td colspan="5" class="text-start">Total</td>' +
                      ['gross','discount','net','received','pending'].map(k => `<td class="text-end">${m(sum(k))}</td>`).join('') + '</tr>' : '';
                $('detailCard').style.display = det.length ? '' : 'none';
                $('thead').innerHTML = '<tr><th class="text-start">Date</th>' +
                    cols.map(c => `<th>${c.label}</th>`).join('') + '</tr>';
                $('tbody').innerHTML = res.rows.map(r =>
                    `<tr><td class="text-start">${fmtDate(r.date)}</td>` +
                    cols.map(c => `<td${c.money ? ' class="text-end"' : ''}>${fmt(c, r[c.key])}</td>`).join('') + '</tr>'
                ).join('');
                $('tfoot').innerHTML = res.rows.length
                    ? '<tr><td class="text-start">Total</td>' + cols.map(c => `<td${c.money ? ' class="text-end"' : ''}>${fmt(c, res.totals[c.key])}</td>`).join('') + '</tr>'
                    : '';
                const txt = (id, all) => {
                    const o = Array.from($(id).selectedOptions).map(x => x.text.trim());
                    return o.length ? o.join(', ') : all;
                };
                const sel1 = (id, all) => $(id).value ? $(id).selectedOptions[0].text.trim() : all;
                $('resultTitle').textContent = 'Date-wise Summary of Appointments, Visits & Services — ' +
                    $('from_date').value + ' to ' + $('to_date').value +
                    ' | Items: ' + txt('items', 'All') +
                    ' | Sub-items: ' + txt('sub_items', 'All') +
                    ' | Doctor: ' + sel1('doctor_id', 'All') +
                    ' | Employee: ' + sel1('employee_id', 'All');
                $('noData').style.display = res.rows.length ? 'none' : '';
                $('resultCard').style.display = '';
                $('printBtn').href = '{{ url('service-summary-report/print') }}?' + params();
                $('printBtn').style.display = '';
            })
            .catch(() => alert('Could not load the report.'));
    });
});
</script>
@endsection
