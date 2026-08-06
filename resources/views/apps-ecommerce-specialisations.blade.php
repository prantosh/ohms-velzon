@extends('layouts.master')

@section('title')
    @lang('translation.doctorspecialisation')
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
        Doctor Specialisation Management
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

        <div id="customSearch"></div>

        <!-- ADD BUTTON -->

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#showModal"
                id="addDoctorBtn">

            <i class="ri-add-line align-bottom me-1"></i>

            Add Specialisation

        </button>

    </div>

</div>



            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="specialisationTable">

                        <thead class="table-light">

                            <tr>
                                <th width="50"></th>
                                <th>ID</th>
                                <th>Category</th>
                                <th>Created Date</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody>

                        </tbody>

                    </table>
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">

    <div id="pagination-info"></div>

    <div class="d-flex align-items-center gap-2">

        <button class="btn btn-sm btn-primary"
                id="prevPage">

            Previous

        </button>

        <span id="pageNumber">

            Page 1

        </span>

        <button class="btn btn-sm btn-primary"
                id="nextPage">

            Next

        </button>

    </div>

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
                    Add New Specialisation
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form class="tablelist-form"
                enctype="multipart/form-data">
               

                @csrf

                <input type="hidden"
                       id="edit-id">

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Category
                            </label>

                            <input type="text"
                                   id="category-field"
                                   class="form-control"
                                   required>

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
                        Save Specialisation
                    </button>

                </div>

            </form>

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

<script>

document.addEventListener("DOMContentLoaded", function () {

    flatpickr("#joining_date-field", {

        dateFormat: "d/m/Y",

        maxDate: "today",

        allowInput: true,

        disableMobile: true
    });

    flatpickr("#date_of_birth-field", {

        dateFormat: "d/m/Y",

        allowInput: true,

        disableMobile: true
    });

});

</script>


<script src="{{ URL::asset('build/js/pages/ecommerce-specialisation-list.init.js') }}"></script>

@endsection
