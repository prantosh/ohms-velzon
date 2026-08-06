
@extends('layouts.master')
@section('title')
    Doctor Settlement
@endsection

@section('css')

<style>
    .blink-warning{
    color:#dc3545;
    font-weight:700;
    animation: blink 1s infinite;
}

@keyframes blink{
    0%,100%{opacity:1;}
    50%{opacity:.15;}
}
.settlement-table{

    max-height:550px;

    overflow-y:auto;

}

.settlement-table thead th{

    position:sticky;

    top:0;

    z-index:2;

    background:#f8f9fa;

}


.summary-table td{

    text-align:right;

    font-weight:600;

}

.summary-table th{

    color:#495057;

}

.alert h5{

    margin-top:5px;

    margin-bottom:0;

    font-weight:700;

}
:root{

    --primary:#405189;
    --success:#0ab39c;
    --danger:#f06548;
    --warning:#f7b84b;
    --info:#299cdb;

}

/* --------------------------------------------------
   Card
-------------------------------------------------- */

.card{

    border:none;

    border-radius:12px;

    box-shadow:0 2px 10px rgba(0,0,0,.05);

}

.card-header{

    border-bottom:1px solid #edf1f7;

    padding:.85rem 1rem;

    font-weight:600;

}

/* --------------------------------------------------
   Labels
-------------------------------------------------- */

.form-label{

    font-size:13px;

    font-weight:600;

    color:#495057;

    margin-bottom:4px;

}

/* --------------------------------------------------
   Inputs
-------------------------------------------------- */

.form-control,
.form-select{

    height:40px;

    border-radius:6px;

}

textarea.form-control{

    height:auto;

}

/* --------------------------------------------------
   Page Title
-------------------------------------------------- */

.page-title-box{

    margin-bottom:1rem;

}

/* --------------------------------------------------
   KPI Cards
-------------------------------------------------- */

.kpi-card{

    transition:.25s;

    cursor:pointer;

}
.kpi-card .card-body{

    padding:.8rem;

}
.kpi-card:hover{

    transform:translateY(-3px);

    box-shadow:0 8px 20px rgba(0,0,0,.12);

}

.kpi-title{

    font-size:12px;

    color:#6c757d;

}

.kpi-value{

    font-size:20px;

    font-weight:700;

    margin-top:4px;

}

/* --------------------------------------------------
   Table
-------------------------------------------------- */

#settlementTable{

    font-size:13px;

}

#settlementTable thead th{

    white-space:nowrap;

    vertical-align:middle;

}

#settlementTable tbody td{

    vertical-align:middle;

}

#settlementTable tbody tr:hover{

    background:#eef7ff;

}

/* --------------------------------------------------
   Summary
-------------------------------------------------- */

.summary-table th{

    width:65%;

}

.summary-table td{

    text-align:right;

    font-weight:600;

}

/* --------------------------------------------------
   Financial Ribbon
-------------------------------------------------- */

.ribbon-box{

    border-radius:8px;

    padding:10px;

    text-align:center;

    color:#fff;

}

.ribbon-box h5{

    margin-top:6px;

    margin-bottom:0;

}

/* --------------------------------------------------
   Loading Overlay
-------------------------------------------------- */

#loadingOverlay{

    position:fixed;

    inset:0;

    background:rgba(255,255,255,.70);

    z-index:99999;

    display:none;

    align-items:center;

    justify-content:center;

}

.loading-content{

    text-align:center;

}

/* --------------------------------------------------
   Floating Toolbar
-------------------------------------------------- */

.floating-toolbar{

    position:fixed;

    right:20px;

    bottom:20px;

    display:flex;

    flex-direction:column;

    gap:10px;

    z-index:9999;

}

.floating-toolbar .btn{

    width:54px;

    height:54px;

    border-radius:50%;

}

/* --------------------------------------------------
   Print
-------------------------------------------------- */

@media print{

.page-title-box,
.card-header,
.btn,
.offcanvas-header{

display:none !important;

}

.card{

box-shadow:none;

border:none;

}

}
#previewCanvas{

    font-size:13px;

}

#previewCanvas .card{

    border-radius:10px;

}

#previewCanvas table td{

    vertical-align:middle;

}

#previewCanvas .table th{

    background:#f8f9fa;

}

#previewCanvas .card-header{

    background:#f8f9fa;

}
</style>
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')

<div class="container-fluid">
    <div class="row">

    <div class="col-12">

        <div class="page-title-box d-sm-flex align-items-center justify-content-between">

            <h4 class="mb-sm-0">

                <i class="ri-bank-card-line me-2"></i>

                Doctor Settlement

            </h4>

            <div class="page-title-right">

                <ol class="breadcrumb m-0">

                    <li class="breadcrumb-item">

                        Accounts

                    </li>

                    <li class="breadcrumb-item active">

                        Doctor Settlement

                    </li>

                </ol>

            </div>

        </div>

    </div>

</div>
<!-- ========================================================= -->
<!-- Settlement Entry -->
<!-- ========================================================= -->

<div class="card">

    <div class="card-header bg-primary text-white">

        <div class="d-flex justify-content-between align-items-center">

            <div>

                <h5 class="mb-0 text-white">

                    <i class="ri-money-dollar-circle-line me-2"></i>

                    Doctor Settlement Entry

                </h5>

            </div>

            <span class="badge bg-light text-primary">

                Financial Module

            </span>

        </div>

    </div>

    <div class="card-body">

        <!-- ============================= -->
        <!-- Row 1 -->
        <!-- ============================= -->

        <div class="row g-3 align-items-end">

            

            <div class="col-xl-2 col-lg-3">

                <label class="form-label">

                    Settlement Date

                </label>

                <input
                    type="text"
                    id="settlement_date"
                    class="form-control flatpickr"
                    value="{{ date('Y-m-d') }}">

            </div>

            <div class="col-xl-2 col-lg-3">

                <label class="form-label">

                    Invoice Date

                </label>

                <input
                    type="text"
                    id="invoice_date"
                    class="form-control flatpickr"
                    value="{{ date('Y-m-d') }}">

            </div>

            <div class="col-xl-3 col-lg-4">

                <label class="form-label">

                    User

                </label>

                <select
                    id="user_id"
                    class="form-select">

                    <option value="">

                        Select User

                    </option>

                    @foreach($users as $user)

                        <option value="{{ $user->id }}">

                            {{ $user->name }}

                        </option>

                    @endforeach

                </select>

            </div>

            <div class="col-xl-3 col-lg-5">

                <label class="form-label">

                    Doctor

                </label>

                <select
                    id="doctor_id"
                    class="form-select">

                    <option value="">

                        Select Doctor

                    </option>

                    @foreach($doctors as $doctor)

                        <option value="{{ $doctor->id }}">

                            {{ $doctor->doctor_name }}

                        </option>

                    @endforeach

                </select>

            </div>

            <div class="col-xl-2 col-lg-3">

                <label class="form-label">

                    Payment Mode

                </label>

                <select
                    id="payment_mode"
                    class="form-select">

                    <option value="CASH">

                        Cash

                    </option>

                    <option value="BANK">

                        Bank Transfer

                    </option>

                    <option value="CHEQUE">

                        Cheque

                    </option>

                    <option value="UPI">

                        UPI

                    </option>

                </select>

            </div>

        </div>

        <!-- ============================= -->
        <!-- Bank Details -->
        <!-- ============================= -->

        <div class="row g-3 mt-2 bank-section ">

            <div class="col-lg-4" id="bank_div">

                <label class="form-label">

                    Bank Name

                </label>

                <input
                    type="text"
                    id="bank_name"
                    class="form-control">

            </div>

            <div class="col-lg-4" id="cheque_div">

                <label class="form-label">

                    Cheque No

                </label>

                <input
                    type="text"
                    id="cheque_no"
                    class="form-control">

            </div>

            <div class="col-lg-4" id="utr_div">

                <label class="form-label">

                    UTR / Transaction No

                </label>

                <input
                    type="text"
                    id="utr_no"
                    class="form-control">

            </div>
            <input type="hidden"
                   id="gross_amount"
                   name="gross_amount">

            <input type="hidden"
                   id="net_payment"
                   name="net_payment">

            <input type="hidden"
                   id="selected_payable_ids"
                   name="selected_payable_ids">

            <input type="hidden"
                   id="settlement_json"
                   name="settlement_json">
        </div>

        <!-- ============================= -->
        <!-- Remarks -->
        <!-- ============================= -->

        <div class="row g-3 mt-2">

            <div class="col-lg-6">

                <label class="form-label">

                    Remarks

                </label>

                <textarea
                    id="remarks"
                    rows="2"
                    class="form-control"
                    placeholder="Settlement remarks..."></textarea>

            </div>

            <div class="col-lg-6">

                <label class="form-label">

                    Internal Notes

                </label>

                <textarea
                    id="internal_notes"
                    rows="2"
                    class="form-control"
                    placeholder="Internal notes (optional)..."></textarea>

            </div>

        </div>

    </div>

</div>
<!-- ========================================================= -->
<!-- Doctor KPI Dashboard -->
<!-- ========================================================= -->
<div id="doctorInfo">
<div class="row mt-3 g-3">

    

    <!-- Outstanding Invoices -->

<div class="col">
        <div class="card kpi-card border-start border-info border-4">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="kpi-title">

                            Outstanding

                        </div>

                        <div
                            class="kpi-value text-info"
                            id="doctor_invoice_count">

                            0

                        </div>

                    </div>

                    <div class="align-self-center">

                        <i class="ri-file-list-3-line text-info fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Outstanding Amount -->

<div class="col">
        <div class="card kpi-card border-start border-danger border-4">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="kpi-title">

                            Outstanding Amount

                        </div>

                        <div
                            class="kpi-value text-danger"
                            id="doctor_outstanding">

                            ₹0.00

                        </div>

                    </div>

                    <div class="align-self-center">

                        <i class="ri-wallet-3-line text-danger fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Last Settlement -->

<div class="col">
        <div class="card kpi-card border-start border-success border-4">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="kpi-title">

                            Last Settlement

                        </div>

                        <div
                            class="kpi-value text-success"
                            id="doctor_last_settlement"
                            style="font-size:16px;">

                            --

                        </div>

                    </div>

                    <div class="align-self-center">

                        <i class="ri-calendar-check-line text-success fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- This Month -->

<div class="col">
        <div class="card kpi-card border-start border-warning border-4">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="kpi-title">

                            This Month

                        </div>

                        <div
                            class="kpi-value text-warning"
                            id="doctorMonthAmount">

                            ₹0.00

                        </div>

                    </div>

                    <div class="align-self-center">

                        <i class="ri-bar-chart-line text-warning fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Year To Date -->

<div class="col">
        <div class="card kpi-card border-start border-dark border-4">

            <div class="card-body">

                <div class="d-flex justify-content-between">

                    <div>

                        <div class="kpi-title">

                            Year To Date

                        </div>

                        <div
                            class="kpi-value text-dark"
                            id="doctorYearAmount">

                            ₹0.00

                        </div>

                    </div>

                    <div class="align-self-center">

                        <i class="ri-line-chart-line text-dark fs-1"></i>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
</div>
<!-- ========================================================= -->
<!-- Outstanding Payables + Settlement Summary                 -->
<!-- ========================================================= -->

<div class="row">

    <!-- ========================================= -->
    <!-- LEFT : Outstanding Payables               -->
    <!-- ========================================= -->

    <div class="col-xl-9">

        <div class="card h-100 shadow-sm">

                <div class="card-header bg-info text-white py-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap">

                    <h5 class="mb-0 text-white">

                        <i class="ri-file-list-3-line me-2"></i>

                        Outstanding Doctor Payables

                    </h5>

                    <div class="d-flex gap-2">

                        <button
                            class="btn btn-light btn-sm"
                            id="btnSelectAll">

                            <i class="ri-checkbox-multiple-line"></i>

                            Select All

                        </button>

                        <button
                            class="btn btn-light btn-sm"
                            id="btnUnselectAll">

                            Clear

                        </button>

                        

                    </div>

                </div>

            </div>

            <div class="card-body p-1">
<div class="row g-2 mb-3">

    <div class="col">

        <div class="alert alert-danger text-center mb-0">

            <small>Outstanding</small>

            <h5 class="mb-0">

                ₹ <span id="ribbonOutstanding">

                    0.00

                </span>

            </h5>

        </div>

    </div>

    <div class="col">

        <div class="alert alert-primary text-center mb-0">

            <small>Selected</small>

            <h5 class="mb-0">

                <span id="ribbonSelected">

                    0

                </span>

            </h5>

        </div>

    </div>

    <div class="col">

        <div class="alert alert-warning text-center mb-0">

            <small>Deduction</small>

            <h5 class="mb-0">

                ₹ <span id="ribbonDeduction">

                    0.00

                </span>

            </h5>

        </div>

    </div>

    <div class="col">

        <div class="alert alert-success text-center mb-0">

            <small>Net Payment</small>

            <h5 class="mb-0">

                ₹ <span id="ribbonNet">

                    0.00

                </span>

            </h5>

        </div>

    </div>

</div>
<div class="table-responsive settlement-table">

    <table
        id="settlementTable"
        class="table table-bordered table-hover table-striped table-sm align-middle w-100"
        data-outstanding-url="{{ route('doctor-settlement.outstanding') }}"
        data-preview-url="{{ route('doctor-settlement.preview') }}"
        data-save-url="{{ route('doctor-settlement.store') }}"
        data-settlement-status-url="{{ route('doctor-settlement.settlement-status') }}">
        
        <thead class="table-light">

        <tr>

        <th width="40">
        <input type="checkbox" id="checkAll">
        </th>

        <th>Invoice No</th>

        <th>Date</th>

        <th>Type</th>

        <th>Patient ID</th>

        <th>Patient Name</th>

        <th>Description</th>

        <th class="text-end">Payable</th>

        <th class="text-end">Paid</th>

        <th class="text-end">Balance</th>

        <th width="140">Settlement</th>

        </tr>

        </thead>

        <tbody>

        </tbody>

        <tfoot>

<tr class="table-secondary fw-bold">

<th colspan="7" class="text-end">

Grand Total

</th>

<th class="text-end">

₹ <span id="footerPayable">0.00</span>

</th>

<th class="text-end">

₹ <span id="footerPaid">0.00</span>

</th>

<th class="text-end">

₹ <span id="footerBalance">0.00</span>

</th>

<th class="text-end">

₹ <span id="footerSettlement">0.00</span>

</th>

</tr>

</tfoot>

    </table>

</div>

            </div>

        </div>

    </div>
<div class="col-xl-3">

    <div class="card summary-card shadow-sm">

        <div class="card-header bg-primary text-white py-2">

            <h5 class="mb-0 text-white">

                Settlement Summary

            </h5>

        </div>

        <div class="card-body">

            <table class="table table-borderless summary-table">

                <tr>

                    <th>Outstanding</th>

                    <td>

                        ₹ <span id="summaryOutstanding">

                            0.00

                        </span>

                    </td>

                </tr>

                <tr>

                    <th>Selected</th>

                    <td>

                        <span id="summarySelected">

                            0

                        </span>

                    </td>

                </tr>

                <tr>

                    <th>Settlement</th>

                    <td>

                        ₹ <span id="summarySettlement">

                            0.00

                        </span>

                    </td>

                </tr>

                <tr>

                    <th>Deduction</th>

                    <td>

                        ₹ <span id="summaryDeduction">

                            0.00

                        </span>

                    </td>

                </tr>

                <tr>

                    <th>Net Payment</th>

                    <td>

                        ₹ <span id="summaryNet">

                            0.00

                        </span>

                    </td>

                </tr>

                <tr class="table-success">

                    <th>Remaining</th>

                    <td>

                        ₹ <span id="summaryRemaining">

                            0.00

                        </span>

                    </td>

                </tr>

            </table>

            <hr>

            <div class="d-grid gap-2">

                <button
                    class="btn btn-warning"
                    id="btnClear">

                    <i class="ri-refresh-line me-1"></i>

                    Clear

                </button>

                <button
                    class="btn btn-info"
                    id="btnPreview"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#previewCanvas">

                    <i class="ri-eye-line me-1"></i>

                    Preview

                </button>

                <button
                    class="btn btn-success"
                    id="btnConfirmSettlement">

                    <i class="ri-save-line me-1"></i>

                    Save Settlement

                </button>
                <button
                    class="btn btn-danger"
                    id="btnSettlementPdf"
                    disabled>

                    <i class="ri-file-pdf-line me-1"></i>

                    Settlement PDF

                </button>
                <button
                    class="btn btn-primary"
                    id="btnRegister">

                    <i class="ri-file-list-line me-1"></i>

                    Register

                </button>

            </div>

        </div>

    </div>

</div>

</div>
<!-- ========================================================= -->
<!-- Settlement Preview Offcanvas -->
<!-- ========================================================= -->

<div class="offcanvas offcanvas-end"
     tabindex="-1"
     id="previewCanvas"
     style="width:750px;">

    <div class="offcanvas-header bg-primary text-white">

        <div>

            <h4 class="mb-0 text-white">

                <i class="ri-file-list-3-line me-2"></i>

                Doctor Settlement Preview

            </h4>

            <div class="alert alert-warning text-center mt-2">
            <span class="blink-warning">
                ⚠ VERIFY ALL INFORMATION BEFORE FINAL CONFIRMATION ⚠
            </span>
        </div>

        </div>

        <button
            type="button"
            class="btn-close btn-close-white"
            data-bs-dismiss="offcanvas">
        </button>

    </div>

    <div class="offcanvas-body">
<div class="text-center border-bottom pb-3">

    <h3 class="fw-bold mb-1">

        {{ config('app.name') }}

    </h3>

    <div class="text-muted">

        Doctor Settlement Voucher

    </div>

</div>
<div class="row mt-4">

    <div class="col-md-6">

        <table class="table table-sm table-borderless">

            <tr>

                <th width="45%">

                    Settlement No

                </th>

                <td id="previewSettlementNo"></td>

            </tr>

            <tr>

                <th>

                    Settlement Date

                </th>

                <td id="previewSettlementDate"></td>

            </tr>

            <tr>

                <th>

                    Doctor

                </th>

                <td id="previewDoctor"></td>

            </tr>

        </table>

    </div>

    <div class="col-md-6">

        <table class="table table-sm table-borderless">

            <tr>

                <th width="45%">

                    Payment Mode

                </th>

                <td id="previewPaymentMode"></td>

            </tr>

            <tr>

                <th>

                    Prepared By

                </th>

                <td>

                    {{ auth()->user()->name }}

                </td>

            </tr>

            <tr>

                <th>

                    Generated On

                </th>

                <td id="previewGenerated"></td>

            </tr>

        </table>

    </div>

</div>
<div class="card bg-light">

    <div class="card-body">

        <div class="row text-center">

            <div class="col">

                <small>

                    Outstanding

                </small>

                <h5 id="previewGross">

                    ₹0.00

                </h5>

            </div>

            <div class="col">

                <small>

                    Settlement

                </small>

                <h5 id="previewSettlement">

                    ₹0.00

                </h5>

            </div>

            <div class="col">

                <small>

                    Deduction

                </small>

                <h5 id="previewDeduction">

                    ₹0.00

                </h5>

            </div>

            <div class="col">

                <small>

                    Net Payment

                </small>

                <h4
                    class="text-success"
                    id="previewNet">

                    ₹0.00

                </h4>

            </div>

        </div>

    </div>

</div>
<div class="card mt-3">

    <div class="card-header">

        Settlement Adjustment

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6">

                <label class="form-label">

                    Deduction Amount

                </label>

                <input
                    type="number"
                    id="preview_deduction"
                    class="form-control text-end"
                    min="0"
                    step="0.01"
                    value="0.00">

            </div>

            <div class="col-md-6">

                <label class="form-label">

                    Deduction Reason

                </label>

                <input
                    type="text"
                    id="preview_deduction_reason"
                    class="form-control"
                    placeholder="Optional">

            </div>

        </div>

    </div>

</div>
<div class="card mt-3">

    <div class="card-header">

        Selected Invoice Details

    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table
                class="table table-bordered table-hover mb-0">

                <thead class="table-light">

                <tr>

                    <th width="110">

                        Invoice

                    </th>

                    <th width="90">

                        Date

                    </th>

                    <th>

                        Patient

                    </th>

                    <th>

                        Description

                    </th>

                    <th class="text-end">

                        Settlement

                    </th>

                </tr>

                </thead>

                <tbody id="previewBody">

                </tbody>

            </table>

        </div>

    </div>

</div>
<div class="card mt-3">

    <div class="card-body">

        <table class="table table-borderless mb-0">

            <tr>

                <th>

                    Total Settlement

                </th>

                <td class="text-end">

                    ₹ <span id="previewInvoiceTotal">

                        0.00

                    </span>

                </td>

            </tr>

            <tr>

                <th>

                    Deduction

                </th>

                <td class="text-end">

                    ₹ <span id="previewDeduction">

                        0.00

                    </span>

                </td>

            </tr>

            <tr class="table-success">

                <th>

                    Net Payable

                </th>

                <th class="text-end">

                    ₹ <span id="previewNetAmount">

                        0.00

                    </span>

                </th>

            </tr>
            <tr>

            <th>Total Selected Invoices</th>

            <td class="text-end">

            <span id="previewInvoiceCount">

            0

            </span>

            </td>

            </tr>
            <tr>

        <th>Remaining Outstanding</th>

        <td class="text-end">

        ₹ <span id="previewRemaining">

        0.00

        </span>

        </td>

        </tr>
        </table>

    </div>

</div>
<div class="d-flex justify-content-end gap-2 mt-4">

    <button
        type="button"
        class="btn btn-light"
        data-bs-dismiss="offcanvas">

        Close

    </button>

    

    <button
        type="button"
        class="btn btn-success"
        id="btnFinalSave">

        <i class="ri-check-line me-1"></i>

        Confirm Settlement

    </button>

</div>

    </div>

</div>
<!-- ========================================================= -->
<!-- Settlement Status Modal                                    -->
<!-- ========================================================= -->

<div class="modal fade" id="settlementStatusModal" tabindex="-1">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title text-white">
                    <i class="ri-file-list-line me-2"></i>
                    Settlement Status
                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <strong>User:</strong> <span id="statusUserName"></span>
                    &nbsp;&nbsp;
                    <strong>Invoice Date:</strong> <span id="statusInvoiceDate"></span>
                </div>

                <div class="table-responsive">

                    <table class="table table-bordered table-hover table-sm align-middle">

                        <thead class="table-light">

                        <tr>
                            <th>Invoice No</th>
                            <th>Doctor</th>
                            <th>Patient</th>
                            <th>Description</th>
                            <th class="text-end">Payable</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Balance</th>
                            <th>Status</th>
                            <th>Settlement No</th>
                            <th>Settlement Date</th>
                        </tr>

                        </thead>

                        <tbody id="settlementStatusBody"></tbody>

                    </table>

                </div>

                <div id="settlementStatusEmpty" class="alert alert-info text-center" style="display:none;">
                    No doctor payables found for this user and invoice date.
                </div>

            </div>

            <div class="modal-footer">

                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

            </div>

        </div>

    </div>

</div>
    @endsection
@section('script')

<!-- jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Select2 AFTER jQuery -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert -->
<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<!-- Doctor Settlement -->
<script src="{{ URL::asset('build/js/pages/doctor-settlement.init.js') }}"></script>

@endsection
