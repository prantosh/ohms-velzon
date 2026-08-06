@extends('layouts.master')

@section('title')
    Cancellation Permission
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Billing
    @endslot

    @slot('title')
        Cancellation Permission
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-shield-user-line"></i>
            This dashboard is for Supervisors and Admins only. Search a non-today invoice here and grant permission
            so that any user can then cancel it from the Invoice Cancellation page. Today's invoices never need permission.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Search Invoice</h5>
            </div>

            <div class="card-body">

                <form id="searchForm">

                    <div class="row align-items-end">

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invoice Number <span class="text-danger">*</span></label>
                            <input type="text" id="invoice_no-field" class="form-control" placeholder="e.g. AMB/0726/001/0001" required>
                        </div>

                        <div class="col-md-2 mb-3">
                            <button type="submit" class="btn btn-primary" id="searchBtn">
                                <i class="ri-search-line"></i>
                                Search
                            </button>
                        </div>

                    </div>

                </form>

                <div id="notFoundWrap" class="alert alert-warning" style="display:none;">
                    No invoice found with this invoice number.
                </div>

            </div>

        </div>

        <div class="card" id="invoiceResultCard" style="display:none;">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Invoice Details</h5>
                <span id="statusBadge"></span>
            </div>

            <div class="card-body">

                <div class="table-responsive mb-4">

                    <table class="table table-sm table-bordered">
                        <tbody>
                            <tr>
                                <th width="20%" class="label-cell">Invoice No</th>
                                <td width="30%" id="detail-invoice_no"></td>
                                <th width="20%" class="label-cell">Invoice Type</th>
                                <td width="30%" id="detail-invoice_type"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Invoice Date</th>
                                <td id="detail-invoice_date"></td>
                                <th class="label-cell">Patient / Party</th>
                                <td id="detail-patient_name"></td>
                            </tr>
                            <tr>
                                <th class="label-cell">Total Amount (&#8377;)</th>
                                <td id="detail-total_amount"></td>
                                <th class="label-cell">Paid Amount (&#8377;)</th>
                                <td id="detail-paid_amount"></td>
                            </tr>
                        </tbody>
                    </table>

                </div>

                <div id="todayWrap" class="alert alert-secondary" style="display:none;">
                    This invoice was created today. It does not need cancellation permission &mdash; any user can cancel it directly.
                </div>

                <div id="alreadyCancelledWrap" class="alert alert-danger" style="display:none;">
                    This invoice has already been cancelled. Permission is not applicable.
                </div>

                <div id="permissionGrantedWrap" class="alert alert-success" style="display:none;">
                    <strong>Cancellation permission already granted.</strong><br>
                    Granted By: <span id="granted-by"></span> on <span id="granted-at"></span><br>
                    Remarks: <span id="granted-remarks"></span>
                </div>

                <div id="grantActionWrap" style="display:none;">

                    <div class="mb-3">
                        <label class="form-label">Remarks <span class="text-danger">*</span></label>
                        <textarea id="grant_remarks-field" class="form-control" rows="3" placeholder="Reason for granting cancellation permission" required></textarea>
                    </div>

                    <button type="button" class="btn btn-success" id="btnGrantPermission">
                        <i class="ri-shield-check-line"></i>
                        Grant Cancellation Permission
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<style>
    .label-cell {
        background-color: var(--vz-light);
        font-weight: 600;
    }
</style>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/cancellation-permission.init.js') }}"></script>

@endsection
