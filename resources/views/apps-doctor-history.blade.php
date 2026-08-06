@extends('layouts.master')

@section('title')
Doctor History Report
@endsection

@section('css')

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet">

<style>

.card-summary{
    border-left:4px solid #405189;
    transition:.2s;
}

.card-summary:hover{
    transform:translateY(-2px);
}

.summary-value{
    font-size:28px;
    font-weight:700;
    color:#405189;
}

.summary-title{
    font-size:13px;
    color:#878a99;
}

.table td{
    vertical-align:middle;
}

.table th{
    white-space:nowrap;
}

</style>

@endsection


@section('content')

<div class="row">

<div class="col-12">

<div class="page-title-box d-sm-flex align-items-center justify-content-between">

<h4 class="mb-sm-0">

Doctor History Report

</h4>

<div class="page-title-right">

<ol class="breadcrumb m-0">

<li class="breadcrumb-item">

Home

</li>

<li class="breadcrumb-item">

Reports

</li>

<li class="breadcrumb-item active">

Doctor History

</li>

</ol>

</div>

</div>

</div>

</div>


<div class="row">

<div class="col-lg-12">

<div class="card">

<div class="card-header bg-primary">

<h5 class="text-white mb-0">

Doctor History Search

</h5>

</div>

<div class="card-body">

<form id="doctorHistoryForm">

@csrf

<div class="row">

<div class="col-lg-3">

<label class="form-label">

Doctor

<span class="text-danger">*</span>

</label>

<select
class="form-select"
name="doctor_id"
id="doctor_id">

<option value="ALL">

All Doctors

</option>

@foreach($doctors as $doctor)

<option value="{{ $doctor->id }}">

{{ $doctor->doctor_name }}

</option>

@endforeach

</select>

</div>


<div class="col-lg-2">

<label class="form-label">

From Date

</label>

<input
type="text"
class="form-control flatpickr"
id="from_date"
name="from_date"
value="{{ date('Y-m-01') }}">

</div>

<div class="col-lg-2">

<label class="form-label">

To Date

</label>

<input
type="text"
class="form-control flatpickr"
id="to_date"
name="to_date"
value="{{ date('Y-m-d') }}">

</div>

<div class="col-lg-5 d-flex align-items-end">

<button
type="button"
id="btnSearch"
class="btn btn-primary me-2">

<i class="ri-search-line"></i>

Search

</button>

<button
type="reset"
id="btnReset"
class="btn btn-secondary me-2">

Reset

</button>

<button
type="button"
id="btnPdf"
class="btn btn-danger me-2">

<i class="ri-file-pdf-line"></i>

PDF

</button>

<button
type="button"
id="btnExcel"
class="btn btn-success me-2">

<i class="ri-file-excel-line"></i>

Excel

</button>

<button
type="button"
id="btnPrint"
class="btn btn-info">

<i class="ri-printer-line"></i>

Print

</button>

</div>

</div>

</form>

</div>

</div>

</div>

</div>
<!-- ==========================================================
SUMMARY CARDS
=========================================================== -->

<div class="row">

    <div class="col-xl col-md-6">

        <div class="card card-summary">

            <div class="card-body">

                <div class="summary-title">
                    Total Patients
                </div>

                <div
                    class="summary-value"
                    id="totalPatients">

                    0

                </div>

            </div>

        </div>

    </div>

    <div class="col-xl col-md-6">

        <div class="card card-summary">

            <div class="card-body">

                <div class="summary-title">

                    Male Patients

                </div>

                <div
                    class="summary-value"
                    id="malePatients">

                    0

                </div>

            </div>

        </div>

    </div>

    <div class="col-xl col-md-6">

        <div class="card card-summary">

            <div class="card-body">

                <div class="summary-title">

                    Female Patients

                </div>

                <div
                    class="summary-value"
                    id="femalePatients">

                    0

                </div>

            </div>

        </div>

    </div>

   

   

</div>

<!-- ==========================================================
RESULT GRID
=========================================================== -->

<div class="row">

<div class="col-lg-12">

<div class="card">

<div class="card-header bg-light">

<h5 class="mb-0">

Doctor Visit History

</h5>

</div>

<div class="card-body">

<div class="table-responsive">

<table
id="doctorHistoryTable"
class="table table-bordered table-hover table-striped align-middle w-100">

<thead class="table-primary">

<tr>

<th width="60">

SL

</th>

<th>

Visit Date

</th>

<th>

Visit Time

</th>

<th>

Invoice No

</th>

<th>

Patient Name

</th>

<th width="80">

Gender

</th>

<th width="70">

Age

</th>

<th>

Mobile

</th>

<th>

Doctor

</th>

<th>

Specialisation

</th>



<th width="180">

Action

</th>

</tr>

</thead>

<tbody>



</tbody>

<tfoot class="table-light">

<tr>

<th colspan="10" class="text-end">
    Total Records
</th>

<th id="totalPatientsFooter" class="text-center">
    0
</th>

</tr>

</tfoot>

</table>

</div>

</div>

</div>

</div>

</div>

<!-- ==========================================================
LOADING
=========================================================== -->

<div
class="modal"
id="loadingModal"
data-bs-backdrop="static"
data-bs-keyboard="false">

<div class="modal-dialog modal-sm modal-dialog-centered">

<div class="modal-content">

<div class="modal-body text-center">

<div
class="spinner-border text-primary mb-3">

</div>

<h6>

Please wait...

</h6>

<p class="text-muted mb-0">

Loading Doctor History...

</p>

</div>

</div>

</div>

</div>
@endsection

@section('script')

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>

<script>
window.doctorHistoryRoutes = {
    search: "{{ route('doctor-history.search') }}",
    pdf: "{{ route('doctor-history.pdf') }}",
    excel: "{{ route('doctor-history.excel') }}",
    whatsapp: "{{ url('/doctor-visit-invoice/send-whatsapp') }}",
    printInvoice: "{{ url('/doctor-visit-invoice/print') }}",
    editInvoice: "{{ url('/doctor-visit-invoice/edit') }}",
    deleteInvoice: "{{ url('/doctor-visit-invoice/delete') }}"
};
</script>

<script src="{{ URL::asset('build/js/pages/doctor-history.init.js') }}"></script>

@endsection
