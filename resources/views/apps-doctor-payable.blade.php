@extends('layouts.master')

@section('title')
Doctor Payables Register
@endsection

@section('css')

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}"
      rel="stylesheet">

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

.payment{
    text-align:right;
    font-weight:600;
}

.group-header{
    background:#e8f2ff !important;
    font-weight:bold;
    color:#0b4ea2;
}

.group-total{
    background:#fff8dc !important;
    font-weight:bold;
}

.badge-transaction{
    font-size:11px;
}

</style>

@endsection


@section('content')

<div class="row">

    <div class="col-12">

        <div class="page-title-box d-sm-flex align-items-center justify-content-between">

            <h4 class="mb-sm-0">
                Doctor Payables Report
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
                       Doctor Payables
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

Doctor Payables Search

</h5>

</div>

<div class="card-body">

<form id="doctorPaymentHistoryForm">

@csrf

<div class="row">

<div class="col-lg-3">

<label class="form-label">

Doctor

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
Invoice Type
</label>

<select
class="form-select"
name="invoice_type"
id="invoice_type">

<option value="ALL">
All
</option>

<option value="DOCTOR_VISIT">
Doctor Visit
</option>

<option value="DIAGNOSTIC">
Diagnostic
</option>

</select>

</div>
    <div class="col-lg-2">

<label class="form-label">
Transaction Type
</label>

<select
class="form-select"
name="transaction_type"
id="transaction_type">

<option value="ALL">
All
</option>

<option value="CONSULTATION">
Consultation
</option>

<option value="DIAGNOSTIC_TEST">
Diagnostic Test
</option>

</select>

</div>
    <div class="col-lg-2">

<label class="form-label">
Payment Status
</label>

<select
class="form-select"
name="payment_status"
id="payment_status">

<option value="ALL">
All
</option>

<option value="PENDING">
Pending
</option>

<option value="APPROVED">
Approved
</option>

<option value="PARTIALLY_PAID">
Partially Paid
</option>

<option value="PAID">
Paid
</option>

<option value="CANCELLED">
Cancelled
</option>

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


<div class="col-lg-2 d-flex align-items-end">

<button
type="button"
id="btnSearch"
class="btn btn-primary me-2">

<i class="ri-search-line"></i>

Search

</button>

</div>

</div>


<div class="row mt-3">

<div class="col-lg-12">

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



</div>

</div>

</form>

</div>

</div>

</div>

</div>

<!-- ==========================================================
SUMMARY
=========================================================== -->

<div class="row">

<div class="col-xl-3 col-md-6">

<div class="card card-summary">

<div class="card-body">

<div class="summary-title">

Total Doctors

</div>

<div
class="summary-value"
id="totalDoctors">

0

</div>

</div>

</div>

</div>

<div class="col-xl-3 col-md-6">

<div class="card card-summary">

<div class="card-body">

<div class="summary-title">

Pending Amount
</div>

<div
class="summary-value"
id="totalTransactions">

0

</div>

</div>

</div>

</div>

<div class="col-xl-3 col-md-6">

<div class="card card-summary">

<div class="card-body">

<div class="summary-title">

Approved Amount
</div>

<div
class="summary-value"
id="grandPayment">

₹0.00

</div>

</div>

</div>

</div>

<div class="col-xl-3 col-md-6">

<div class="card card-summary">

<div class="card-body">

<div class="summary-title">

Grand Payable
</div>

<div
class="summary-value"
id="averagePayment">

₹0.00

</div>

</div>

</div>

</div>

</div>


<div class="row mt-3">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header bg-success">

                <h5 class="text-white mb-0">

                    Grand Summary

                </h5>

            </div>

            <div class="card-body">

                <div class="row text-center">

                    <div class="col-md-3">

                        <h6>Total Doctors</h6>

                        <h4 id="footerDoctors">

                            0

                        </h4>

                    </div>

                    <div class="col-md-3">

                        <h6>Pending Amount</h6>

                        <h4 id="footerTransactions">

                            0

                        </h4>

                    </div>

                    <div class="col-md-3">

                        <h6>Approved Amount</h6>

                        <h4 id="footerPayment">

                            ₹0.00

                        </h4>

                    </div>

                    <div class="col-md-3">

                        <h6>Grand Payable</h6>

                        <h4 id="footerAverage">

                            ₹0.00

                        </h4>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
<!-- ==========================================================
REPORT
=========================================================== -->

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-header">

                <h5 class="card-title mb-0">

                    Doctor Payment Register

                </h5>

            </div>

            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-bordered table-hover table-sm align-middle mb-0">

                        <thead class="table-primary">

                        <tr>

                            <th width="50">SL</th>

                            <th width="100">Payable No</th>

                            <th width="120">Payable Date</th>

                            <th width="120">User</th>

                            <th width="120">Invoice No</th>


                            <th width="120">Invoice Type</th>
                            <th width="120">Patient</th>
                            <th width="120">Item Description</th>
                            <th width="120">Payable Amount</th>
                            <th width="120">Status</th>
                            <th width="120">Paid Amount</th>

                            <th width="100">Paid Date</th>

                            

                            <th width="90" class="text-center">
                                Action
                            </th>

                        </tr>

                        </thead>

                        <tbody id="reportBody">

                        <tr>

                            <td colspan="11"
                                class="text-center text-muted py-5">

                                <i class="ri-search-line fs-2 d-block mb-2"></i>

                                    Click <strong>Search </strong>to load Doctor Payables Register.
                            </td>

                        </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>
@endsection     
@section('script')

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>

<script>
window.reportRoutes = {
    search: "{{ route('doctor-payable.search') }}",
    pdf: "{{ route('doctor-payable.pdf') }}",
    excel: "{{ route('doctor-payable.excel') }}",
    print: "{{ route('doctor-payable.print') }}"
};
</script>

<script src="{{ URL::asset('build/js/common-loader.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/doctor-payable.init.js') }}"></script>

@endsection
