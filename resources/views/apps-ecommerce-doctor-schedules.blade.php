@extends('layouts.master')

@section('title')
    @lang('translation.doctorschedules')
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />
<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
      href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
<link rel="stylesheet"
      href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>

.flatpickr-calendar {

    border-radius: 14px !important;

    box-shadow:
        0 10px 25px rgba(0,0,0,0.12) !important;

    border: none !important;

    overflow: hidden;
}

.flatpickr-day.selected {

    background: #405189 !important;

    border-color: #405189 !important;
}

.flatpickr-day:hover {

    background: #e9edf7 !important;
}

.flatpickr-months {

    background: #405189;

    color: white;
}

.flatpickr-current-month input.cur-year {

    color: white !important;
}

.flatpickr-monthDropdown-months {

    color: white !important;
}

.flatpickr-weekday {

    color: #405189 !important;

    font-weight: 600;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Hospital
    @endslot

    @slot('title')
        Doctor Schedule Management
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

    <!-- LEFT SIDE -->

    <div class="d-flex align-items-center gap-3 flex-wrap">

        <!-- SHOW RECORDS -->

        <div class="d-flex align-items-center">

            <label class="me-2 mb-0 fw-semibold">

                Show

            </label>

            <select id="perPage"
                    class="form-select form-select-sm w-auto">

                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>

            </select>

            <span class="ms-2">

                records

            </span>

        </div>

        <!-- EXPORT BUTTONS -->

        <div id="exportButtons"
             class="d-flex gap-2"></div>

    </div>

    <!-- RIGHT SIDE -->

    <div class="d-flex align-items-center gap-2 flex-wrap">

        <!-- SEARCH -->

        <input type="search"
       id="customSearch"
       class="form-control"
       placeholder="Search..."
       style="width:220px;">
        <!-- ADD BUTTON -->

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#showModal"
                id="addDoctorBtn">

            <i class="ri-add-line align-bottom me-1"></i>

            Add Schedule

        </button>

    </div>

</div>



            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="scheduleTable">

                        <thead class="table-light">

                            <tr>
                                
                                <th>ID</th>
                                <th>Image</th>
                                <th>Doctor</th>
                                <th>Specialisation</th>
                               <th>Monday</th>
                                <th>Tuesday</th>
                                <th>Wednesday</th>
                                <th>Thursday</th>
                                <th>Friday</th>
                                <th>Saturday</th>
                                <th>Sunday</th>
                                <th>Status</th>
                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody id="scheduleTableBody">

                        </tbody>

                    </table>
                    
                    

                </div>



                </div>

            </div>

        </div>

    </div>

</div>

<!-- MODAL -->

<div class="modal fade"
     id="showModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title"  id="modal-title">
                    Add New Schedule
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form id="scheduleForm"
                  class="tablelist-form"
                  enctype="multipart/form-data">
               

                @csrf

                <input type="hidden"
                    id="id-field">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Doctor
                            </label>

                            <select id="doctor_id-field"
                                name="doctor_id"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Doctor
                                </option>

                                @foreach($doctors as $doctor)

                                    <option value="{{ $doctor->id }}"
                                           data-specialisation="{{ $doctor->specialisation }}">
                                        {{ $doctor->doctor_name }}
                                    </option>

                                @endforeach

                            </select>
                            

                        </div>
                        <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Specialisation
                                </label>

                                <input type="text"
                                       id="specialisation-field"
                                       class="form-control"
                                       readonly>

                            </div>
                        <div class="alert alert-info py-2 px-3 mb-3">
                            <i class="ri-information-line align-middle me-1"></i>
                            A doctor can have more than one session on the same day (e.g. 10 AM-12 PM and again
                            5 PM-7 PM). Use "+ Session" to add another time block to a day. A time is always
                            required. Tick "By Appointment" if this session should be shown as "By Appointment"
                            (instead of the actual time) on the staff calendar and the public schedule, and
                            hidden from the public online booking page -- staff can still book it here using
                            the real time.
                        </div>

                        <div class="row">

                            @php
                                $days = [
                                    'monday',
                                    'tuesday',
                                    'wednesday',
                                    'thursday',
                                    'friday',
                                    'saturday',
                                    'sunday'
                                ];
                            @endphp

                            @foreach($days as $day)

                            <div class="col-md-6 mb-4">

                                <div class="border rounded p-3">

                                    <div class="d-flex justify-content-between align-items-center mb-2">

                                        <h6 class="mb-0 text-primary text-capitalize">
                                            {{ $day }}
                                        </h6>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary add-session-btn"
                                                data-day="{{ $day }}">
                                            <i class="ri-add-line align-bottom"></i> Session
                                        </button>

                                    </div>

                                    <div id="sessions-{{ $day }}"
                                         class="session-rows-container"
                                         data-day="{{ $day }}">
                                    </div>

                                    <div class="text-muted small no-sessions-label" data-day="{{ $day }}">
                                        OFF -- no sessions
                                    </div>

                                </div>

                            </div>

                            @endforeach

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select id="status-field"
                                name="status"
                                class="form-select">

                                <option value="1">
                                    Active
                                </option>

                                <option value="0">
                                    Inactive
                                </option>

                            </select>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        Close
                    </button>

                    <button type="submit"
                            class="btn btn-success"
                            id="add-btn">
                        Save Schedule
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- ==========================================================
     UNAVAILABLE DATES MODAL (blackout exceptions per doctor)
========================================================== -->

<div class="modal fade"
     id="exceptionModal"
     tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Unavailable Dates -- <span id="exceptionDoctorName"></span>
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="alert alert-info py-2 px-3 mb-3">
                    <i class="ri-information-line align-middle me-1"></i>
                    This doctor keeps their normal weekly schedule. Mark specific calendar dates here
                    only for the days they won't actually be sitting (e.g. sits just once or twice a
                    month) -- appointments cannot be booked on a marked date, on that date only.
                </div>

                <form id="exceptionForm">

                    <div class="row g-2 align-items-end mb-3">

                        <div class="col-md-5">
                            <label class="form-label">Date(s)</label>
                            <input type="text"
                                   id="exceptionDates"
                                   class="form-control"
                                   placeholder="Click to pick one or more dates">
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Reason (optional)</label>
                            <input type="text"
                                   id="exceptionReason"
                                   class="form-control"
                                   placeholder="e.g. On leave">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="ri-calendar-close-line align-middle"></i> Mark
                            </button>
                        </div>

                    </div>

                </form>

                <hr>

                <label class="form-label">Currently unavailable (upcoming)</label>

                <div id="exceptionList"></div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal">
                    Close
                </button>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>




<script src="{{ URL::asset('build/js/pages/ecommerce-doctor-schedule-list.init.js') }}"></script>
@endsection
