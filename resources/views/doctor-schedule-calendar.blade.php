
@extends('layouts.master')

@section('title')
Doctor Schedule Calendar
@endsection

@section('css')
<style>
:root {
    --schedule-accent: #405189;
}

/* -------------------------------------------------- Header / filters ------ */

.schedule-card-header {
    background: linear-gradient(135deg, #0ca678, #0b8f66);
}

#calendarFilters {
    background: #f7f8fa;
    border: 1px solid #e9ebec;
    border-radius: 10px;
    padding: 14px 16px;
}

#calendarFilters .form-label {
    font-size: 12.5px;
    font-weight: 600;
    color: #495057;
    margin-bottom: 4px;
}

#calendarFilters .form-select {
    min-width: 200px;
    border-radius: 8px;
}

#viewToggle .btn {
    min-width: 72px;
}

/* -------------------------------------------------- Custom grid ----------- */

#customGridWrapper {
    background: #fff;
    border: 1px solid #e9ebec;
    border-radius: 10px;
    padding: 4px;
    overflow-x: auto;
}

table.schedule-grid {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

table.schedule-grid th,
table.schedule-grid td {
    border: 1px solid #eef0f2;
}

table.schedule-grid thead th {
    background: #f7f8fa;
    color: #495057;
    font-weight: 600;
    text-align: center;
    padding: 10px 6px;
    font-size: 13px;
    position: sticky;
    top: 0;
    z-index: 2;
}

table.schedule-grid thead th.today-col {
    background: rgba(64, 81, 137, .1);
    color: var(--schedule-accent);
}

table.schedule-grid .time-col {
    width: 78px;
    background: #f7f8fa;
    color: #74788d;
    font-size: 11.5px;
    white-space: nowrap;
    text-align: right;
    padding: 6px 10px 6px 6px;
    vertical-align: top;
}

table.schedule-grid td.day-cell {
    padding: 0;
    vertical-align: top;
}

.cell-inner {
    min-height: 46px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px;
}

.doctor-bar {
    display: block;
    width: 100%;
    border-radius: 6px;
    border-left: 4px solid var(--schedule-accent);
    background: rgba(64, 81, 137, .08);
    padding: 5px 8px;
    cursor: pointer;
    transition: box-shadow .15s ease, transform .15s ease;
}

.doctor-bar:hover {
    box-shadow: 0 3px 10px rgba(0, 0, 0, .16);
    transform: translateY(-1px);
}

.doctor-bar .doc-name {
    font-weight: 700;
    font-size: 12.5px;
    color: #2b2b2b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.doctor-bar .doc-spec {
    font-size: 11px;
    color: #5c5c5c;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.doctor-bar .doc-meta {
    font-size: 10.5px;
    color: #6c757d;
    margin-top: 2px;
}

.doctor-bar .doc-appt-badge {
    display: inline-block;
    font-size: 9.5px;
    font-weight: 600;
    padding: 1px 6px;
    border-radius: 4px;
    background: #fff3cd;
    color: #7a5b00;
    margin-top: 3px;
}

/* -------------------------------------------------- Month view (FullCalendar, unchanged look) --- */

#calendarWrapper {
    background: #fff;
    border: 1px solid #e9ebec;
    border-radius: 10px;
    padding: 12px;
}

.fc {
    --fc-border-color: #e9ebec;
    --fc-today-bg-color: rgba(64, 81, 137, .06);
    --fc-neutral-bg-color: #f7f8fa;
}

.fc .fc-toolbar-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #343a40;
}

.fc .fc-button-primary {
    background-color: var(--schedule-accent);
    border-color: var(--schedule-accent);
    box-shadow: none !important;
}

.fc .fc-button-primary:hover {
    background-color: #34426d;
    border-color: #34426d;
}

.fc .fc-button-primary:disabled {
    background-color: #8590b3;
    border-color: #8590b3;
}

.fc .fc-button-primary:not(:disabled).fc-button-active,
.fc .fc-button-primary:not(:disabled):active {
    background-color: #2c3861;
    border-color: #2c3861;
}

.fc .fc-col-header-cell {
    background-color: #f7f8fa;
    padding: 8px 0;
}

.fc .fc-col-header-cell-cushion {
    color: #495057;
    font-weight: 600;
    text-decoration: none;
}

.fc-daygrid-event {
    border: none !important;
    border-radius: 6px !important;
    white-space: normal !important;
    cursor: pointer;
}

.fc-event-main {
    padding: 3px 6px;
    font-size: 12px;
}
</style>
<link
href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css"
rel="stylesheet">

@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header schedule-card-header text-white">

            <h4 class="mb-0">
                <i class="ri-calendar-line"></i>
                Doctor Schedule Calendar
            </h4>

        </div>

        <div class="card-body">

            <div id="calendarFilters" class="d-flex flex-wrap align-items-end gap-2 mb-3">

                <div>
                    <label class="form-label mb-1">Doctor</label>
                    <select id="doctorFilter" class="form-select">
                        <option value="">-- All Doctors --</option>
                        @foreach($doctors as $doctor)
                        <option value="{{ $doctor->id }}">{{ $doctor->doctor_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label mb-1">Specialisation</label>
                    <select id="specialisationFilter" class="form-select">
                        <option value="">-- All Specialisations --</option>
                        @foreach($specialisations as $spec)
                        <option value="{{ $spec->id }}">{{ $spec->category }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="dayFilterWrap">
                    <label class="form-label mb-1" id="dayFilterLabel">Day</label>
                    <select id="dayOfWeekFilter" class="form-select"></select>
                </div>

                <div>
                    <button type="button" id="clearFiltersBtn" class="btn btn-outline-secondary">
                        <i class="ri-close-line align-middle"></i> Clear Filters
                    </button>
                </div>

                <div id="viewToggle" class="btn-group ms-auto" role="group">
                    <button type="button" class="btn btn-outline-primary" data-view="week">Week</button>
                    <button type="button" class="btn btn-outline-primary" data-view="day">Day</button>
                    <button type="button" class="btn btn-outline-primary" data-view="month">Month</button>
                </div>

            </div>

            <div id="noScheduleMessage" class="alert alert-warning text-center fs-5 py-5" style="display:none;">
                <i class="ri-calendar-close-line align-middle me-2"></i>
                No Schedule Found
            </div>

            <div id="customGridWrapper"></div>

            <div id="calendarWrapper" style="display:none;">
                <div id="calendar"></div>
            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const DAY_LABELS = { monday: 'Mon', tuesday: 'Tue', wednesday: 'Wed', thursday: 'Thu', friday: 'Fri', saturday: 'Sat', sunday: 'Sun' };

const SLOT_START_MIN = 8 * 60;   // 08:00
const SLOT_END_MIN = 22 * 60;    // 22:00
const SLOT_STEP = 30;

let calendarInstance = null;
let monthCalendarRendered = false;
let currentView = 'day';
let currentGridEvents = [];

function todayKey() {
    const map = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    return map[new Date().getDay()];
}

function buildSlotList() {
    let slots = [];
    for (let m = SLOT_START_MIN; m < SLOT_END_MIN; m += SLOT_STEP) {
        slots.push(m);
    }
    return slots;
}

function slotLabel(minutesFromMidnight) {
    let h = Math.floor(minutesFromMidnight / 60);
    let m = minutesFromMidnight % 60;
    let period = h >= 12 ? 'PM' : 'AM';
    let h12 = h % 12 === 0 ? 12 : h % 12;
    return h12 + (m === 0 ? '' : ':' + String(m).padStart(2, '0')) + ' ' + period;
}

function slotKeyForTime(timeStr) {
    let parts = (timeStr || '00:00').split(':').map(Number);
    let h = parts[0] || 0;
    let m = parts[1] || 0;
    return h * 60 + (m < 30 ? 0 : 30);
}

function showEventDetail(p) {

    Swal.fire({

        title: p.doctor,

        html: `
            <div style="text-align:left; font-size:14px;">

                <div class="mb-2">
                    <i class="ri-stethoscope-line text-primary align-middle me-1"></i>
                    <b>Specialisation:</b> ${p.specialisation || '-'}
                </div>

                <div class="mb-2">
                    <i class="ri-calendar-event-line text-primary align-middle me-1"></i>
                    <b>Day:</b> ${p.day}
                </div>

                ${p.is_by_appointment ? `
                <div class="mb-2">
                    <i class="ri-time-line text-primary align-middle me-1"></i>
                    <b>Timing:</b> By Appointment
                </div>
                ` : `
                <div class="mb-2">
                    <i class="ri-time-line text-primary align-middle me-1"></i>
                    <b>Time:</b> ${p.start_time} - ${p.end_time}
                </div>
                `}

                <div>
                    <i class="ri-group-line text-primary align-middle me-1"></i>
                    <b>Booked:</b> ${p.booking_status}
                </div>

            </div>
        `,

        icon: 'info',

        confirmButtonColor: '#405189'
    });
}

/*
|--------------------------------------------------------------------------
| CUSTOM GRID (Week / Day) -- cells grow to fit however many doctors are
| scheduled in that slot, each doctor shown as a full-width bar stacked
| vertically, instead of FullCalendar's time-grid forcing them into
| ever-narrower side-by-side slivers.
|--------------------------------------------------------------------------
*/

function renderGrid(events, days) {

    currentGridEvents = events;

    let slots = buildSlotList();

    let grouped = {};
    days.forEach(d => grouped[d] = {});

    events.forEach((ev, idx) => {

        let day = (ev.extendedProps.day || '').toLowerCase();

        if (!grouped.hasOwnProperty(day)) return;

        let slotKey = slotKeyForTime(ev.extendedProps.start_time);

        if (!grouped[day][slotKey]) grouped[day][slotKey] = [];

        grouped[day][slotKey].push(idx);
    });

    let table = document.createElement('table');
    table.className = 'schedule-grid';

    let thead = document.createElement('thead');
    let headRow = document.createElement('tr');
    headRow.appendChild(document.createElement('th'));

    let today = todayKey();

    days.forEach(d => {
        let th = document.createElement('th');
        th.textContent = DAY_LABELS[d];
        if (d === today) th.classList.add('today-col');
        headRow.appendChild(th);
    });

    thead.appendChild(headRow);
    table.appendChild(thead);

    let tbody = document.createElement('tbody');

    slots.forEach(slotMin => {

        let tr = document.createElement('tr');

        let timeTd = document.createElement('td');
        timeTd.className = 'time-col';
        timeTd.textContent = slotLabel(slotMin);
        tr.appendChild(timeTd);

        days.forEach(d => {

            let td = document.createElement('td');
            td.className = 'day-cell';

            let inner = document.createElement('div');
            inner.className = 'cell-inner';

            let idxList = grouped[d][slotMin] || [];

            idxList.forEach(idx => {

                let ev = events[idx];
                let p = ev.extendedProps;
                let hex = ev.backgroundColor || '#405189';

                let bar = document.createElement('div');
                bar.className = 'doctor-bar';
                bar.style.borderLeftColor = hex;
                bar.style.backgroundColor = hex + '17';
                bar.dataset.idx = idx;
                bar.title = p.doctor + ' • ' + (p.specialisation || '') + ' • ' + p.booking_status + ' booked';

                let name = document.createElement('div');
                name.className = 'doc-name';
                name.textContent = p.doctor;
                bar.appendChild(name);

                let spec = document.createElement('div');
                spec.className = 'doc-spec';
                spec.textContent = p.specialisation || '';
                bar.appendChild(spec);

                let meta = document.createElement('div');
                meta.className = 'doc-meta';
                meta.innerHTML = '<i class="ri-group-line align-middle"></i> ' + p.booking_status;
                bar.appendChild(meta);

                if (p.is_by_appointment) {
                    let badge = document.createElement('div');
                    badge.className = 'doc-appt-badge';
                    badge.textContent = 'By Appointment';
                    bar.appendChild(badge);
                }

                inner.appendChild(bar);
            });

            td.appendChild(inner);
            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });

    table.appendChild(tbody);

    return table;
}

document.getElementById('customGridWrapper').addEventListener('click', function (e) {

    let bar = e.target.closest('.doctor-bar');

    if (!bar) return;

    let ev = currentGridEvents[bar.dataset.idx];

    if (ev) showEventDetail(ev.extendedProps);
});

function loadCustomGrid() {

    let params = new URLSearchParams();

    let doctorId = document.getElementById('doctorFilter').value;
    let specialisationCode = document.getElementById('specialisationFilter').value;

    if (doctorId) params.append('doctor_id', doctorId);
    if (specialisationCode) params.append('specialisation_code', specialisationCode);

    let days;

    if (currentView === 'day') {

        let selected = document.getElementById('dayOfWeekFilter').value || todayKey();

        days = [selected];

        params.append('day_of_week', selected);

    } else {

        days = DAYS;
    }

    fetch("{{ route('doctor-schedule.calendar.events') }}?" + params.toString())
        .then(response => response.json())
        .then(data => {

            toggleNoScheduleMessage(data.length === 0);

            let wrapper = document.getElementById('customGridWrapper');
            wrapper.innerHTML = '';
            wrapper.appendChild(renderGrid(data, days));
        });
}

/*
|--------------------------------------------------------------------------
| VIEW SWITCHING
|--------------------------------------------------------------------------
*/

function toggleNoScheduleMessage(isEmpty) {

    document.getElementById('noScheduleMessage').style.display = isEmpty ? 'block' : 'none';

    let activeWrapper = currentView === 'month'
        ? document.getElementById('calendarWrapper')
        : document.getElementById('customGridWrapper');

    activeWrapper.style.display = isEmpty ? 'none' : 'block';

    if (!isEmpty && currentView === 'month' && calendarInstance) {
        setTimeout(() => calendarInstance.updateSize(), 0);
    }
}

function refreshCurrentView() {

    if (currentView === 'month') {
        calendarInstance.refetchEvents();
    } else {
        loadCustomGrid();
    }
}

function dayLabel(d) {
    return d.charAt(0).toUpperCase() + d.slice(1);
}

/*
 * Day view always needs exactly one concrete day selected (there's no
 * meaningful "all days" in a single-day grid), while Week/Month need a
 * real optional "-- All Days --" filter. Rebuilding the option list per
 * view keeps one control instead of two competing ones, and resets to a
 * sensible default every time the view changes so a leftover selection
 * from one view never silently filters another.
 */
function configureDayFilterForView(view) {

    let select = document.getElementById('dayOfWeekFilter');
    let dayOptions = DAYS.map(d => `<option value="${d}">${dayLabel(d)}</option>`).join('');

    if (view === 'day') {

        document.getElementById('dayFilterLabel').textContent = 'Day';
        select.innerHTML = dayOptions;
        select.value = todayKey();
        select.disabled = false;

    } else {

        document.getElementById('dayFilterLabel').textContent = 'Day (filter, Week ignores this)';
        select.innerHTML = '<option value="">-- All Days --</option>' + dayOptions;
        select.value = '';
        select.disabled = (view === 'week');
    }
}

function switchView(view) {

    currentView = view;

    document.querySelectorAll('#viewToggle button').forEach(b => {
        b.classList.toggle('btn-primary', b.dataset.view === view);
        b.classList.toggle('btn-outline-primary', b.dataset.view !== view);
    });

    configureDayFilterForView(view);

    if (view === 'month') {

        document.getElementById('customGridWrapper').style.display = 'none';
        document.getElementById('calendarWrapper').style.display = 'block';

        if (!monthCalendarRendered) {

            // Lazy-render: FullCalendar measuring a hidden (display:none)
            // container at init corrupts its internal size tracking, and
            // rendering it eagerly on every page load would also fire a
            // wasted fetch whose completion could race with the custom
            // grid's own load (both funnel through toggleNoScheduleMessage,
            // which reads the shared currentView). Rendering it only once
            // it's actually visible sidesteps both problems.
            calendarInstance.render();
            monthCalendarRendered = true;

        } else {

            setTimeout(() => calendarInstance.updateSize(), 0);

            calendarInstance.refetchEvents();
        }

    } else {

        document.getElementById('calendarWrapper').style.display = 'none';
        document.getElementById('customGridWrapper').style.display = 'block';

        loadCustomGrid();
    }
}

document.addEventListener('DOMContentLoaded', function () {

    let calendarEl = document.getElementById('calendar');

    let calendar = new FullCalendar.Calendar(calendarEl, {

        initialView: 'dayGridMonth',
        height: 750,
        dayMaxEvents: true,

        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },

        events: function (fetchInfo, successCallback, failureCallback) {

            let params = new URLSearchParams();

            let doctorId = document.getElementById('doctorFilter').value;
            let specialisationCode = document.getElementById('specialisationFilter').value;
            let dayOfWeek = document.getElementById('dayOfWeekFilter').value;

            if (doctorId) params.append('doctor_id', doctorId);
            if (specialisationCode) params.append('specialisation_code', specialisationCode);
            if (dayOfWeek) params.append('day_of_week', dayOfWeek);

            fetch("{{ route('doctor-schedule.calendar.events') }}?" + params.toString())
                .then(response => response.json())
                .then(data => {
                    toggleNoScheduleMessage(data.length === 0);
                    successCallback(data);
                })
                .catch(error => failureCallback(error));
        },

        eventContent: function (arg) {

            let p = arg.event.extendedProps;
            let timing = p.is_by_appointment ? 'By Appointment' : p.start_time;

            return {
                html: `
                    <div style="font-size:11.5px; line-height:1.25; overflow:hidden;"
                         title="${p.doctor} • ${timing} • ${p.booking_status} booked">
                        <b>${p.doctor}</b> - ${p.specialisation || ''} <span style="opacity:.85;">(${timing})</span>
                    </div>
                `
            };
        },

        eventClick: function (info) {
            showEventDetail(info.event.extendedProps);
        }
    });

    calendarInstance = calendar;

    // Not rendered yet -- see the lazy-render note in switchView(); it's
    // rendered the first time the user actually switches to Month view.

    document.getElementById('doctorFilter').addEventListener('change', refreshCurrentView);

    document.getElementById('specialisationFilter').addEventListener('change', refreshCurrentView);

    document.getElementById('dayOfWeekFilter').addEventListener('change', refreshCurrentView);

    document.getElementById('clearFiltersBtn').addEventListener('click', function () {
        document.getElementById('doctorFilter').value = '';
        document.getElementById('specialisationFilter').value = '';
        configureDayFilterForView(currentView);
        refreshCurrentView();
    });

    document.querySelectorAll('#viewToggle button').forEach(btn => {
        btn.addEventListener('click', function () {
            switchView(this.dataset.view);
        });
    });

    switchView('day');
});

</script>

@endsection
