@extends('layouts.master')

@section('title')
    User Reconciliation Dashboard
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Reports
    @endslot

    @slot('title')
        User Reconciliation Dashboard
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-scales-3-line"></i>
            Month-by-month reconciliation of one user (or all users): collection, refund, doctor payable and
            doctor payment made. Every month is also shown <b>cumulatively since the system began</b>, so the
            <b>Doctor Payment Pending</b> of a row is everything still unpaid to doctors, from the beginning up to
            the end of that month, on the invoices that user billed. A completed month is saved on the 1st of the
            next month; the current month is shown month-to-date. Use <b>Show detail</b> for the mode-wise and
            doctor-wise breakdown.
        </div>

        <div class="card">

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-3 mb-2">
                        <label class="form-label">User</label>
                        <select id="user_id-field" class="form-select">
                            <option value="EMPLOYEES" selected>All Employees (employee-wise)</option>
                            <option value="ALL">All Users (combined)</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 mb-2" id="monthWrap">
                        <label class="form-label">Month</label>
                        <select id="month-field" class="form-select"></select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <button type="button" class="btn btn-primary w-100" id="loadBtn">
                            <i class="ri-search-line"></i> Load
                        </button>
                    </div>

                    <div class="col-md-2 mb-2">
                        <a href="javascript:void(0)" id="printBtn" class="btn btn-success w-100" target="_blank" style="display:none;">
                            <i class="ri-printer-line"></i> Print PDF
                        </a>
                    </div>

                    <div class="col-12 mb-1 text-md-end text-muted small" id="inceptionNote"></div>

                </div>

            </div>

        </div>

        <div class="card" id="tableCard" style="display:none;">

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle table-sm">

                        <thead class="table-light text-center">
                            <tr>
                                <th rowspan="2" class="align-middle" id="firstHeader">Month</th>
                                <th colspan="6" class="bg-soft-info">This Month</th>
                                <th colspan="3" class="bg-soft-danger">Doctor Balance (Payable &minus; Paid)</th>
                                <th colspan="6" class="bg-soft-warning">Cumulative Since Inception (up to month end)</th>
                                <th rowspan="2" class="align-middle"></th>
                            </tr>
                            <tr>
                                <th class="text-end">Collection<br>Cash</th>
                                <th class="text-end">Collection<br>Non-Cash</th>
                                <th class="text-end">Refund</th>
                                <th class="text-end">Doctor<br>Payable</th>
                                <th class="text-end">Paid to<br>Doctor</th>
                                <th class="text-end">Net Cash</th>
                                <th class="text-end">Carried over from<br>previous month</th>
                                <th class="text-end">This month<br>movement</th>
                                <th class="text-end">Balance at<br>month end</th>
                                <th class="text-end">Collection</th>
                                <th class="text-end">Refund</th>
                                <th class="text-end">Doctor<br>Payable</th>
                                <th class="text-end">Paid to<br>Doctor</th>
                                <th class="text-end">Net Cash</th>
                                <th class="text-end">Doctor Payment<br>Pending</th>
                            </tr>
                        </thead>

                        <tbody id="reconBody"></tbody>

                    </table>

                </div>

                <div class="small text-muted mb-1">
                    <b>Doctor Balance</b> = doctor payable &minus; paid to doctors. <b>Carried over from previous month</b> is the balance on the
                    1st (the previous month's closing balance); adding this month's movement gives the balance at month end, which is carried to the next month.
                    A positive balance is <span class="text-danger">pending</span>, a negative one is <span class="text-primary">excess paid</span>.
                </div>
                <div class="small text-muted">
                    Net Cash = cash collected &minus; cash refunded &minus; cash paid to doctors.
                    Doctor Payment Pending = doctor payable created on the user's invoices &minus; everything settled against it. It counts invoices the patient has not
                    paid in full too; such part is shown <span class="text-warning">"awaiting final payment of the invoice"</span> under the figure, because it cannot be settled
                    (Doctor Settlement offers a payable only after the invoice is fully paid).
                </div>

            </div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No transactions found for this selection.
        </div>

    </div>

</div>

<!-- Show detail -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reconciliation Detail &mdash; <span id="detailTitle"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="detailBody"></div>
            <div class="modal-footer">
                <a href="javascript:void(0)" id="detailPrintBtn" class="btn btn-success" target="_blank">
                    <i class="ri-printer-line"></i> Print PDF
                </a>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/user-reconciliation.init.js') }}"></script>

@endsection
