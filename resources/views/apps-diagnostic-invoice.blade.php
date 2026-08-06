@extends('layouts.master')

@section('title')
Diagnostic Invoice
@endsection

@section('css')



<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
    /* Invoice table */
    #invoiceTable {
        font-size: 11px;
    }

    #invoiceTable th,
    #invoiceTable td {
        padding: 6px 8px !important;
        vertical-align: middle;
    }

    /* DataTables controls */
    .dataTables_wrapper {
        font-size: 11px;
    }

    .dataTables_length,
    .dataTables_filter,
    .dataTables_info,
    .dataTables_paginate {
        font-size: 11px;
    }

    .dataTables_filter input {
        height: 28px;
        font-size: 11px;
        padding: 2px 6px;
    }

    .dataTables_length select {
        height: 28px;
        font-size: 11px;
        padding: 2px 20px 2px 6px;
    }

    .page-link {
        font-size: 11px;
        padding: 0.25rem 0.5rem;
    }
</style>

@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h4 class="card-title mb-0">
                Diagnostic Invoice
            </h4>

            <a href="{{ url('/diagnostic-invoice/create') }}"
               class="btn btn-primary">

                <i class="ri-add-line"></i>

                Create Diagnostic Invoice

            </a>

        </div>

        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">

                <ul class="nav nav-tabs mb-0" id="invoiceCategoryTabs">

                    <li class="nav-item">
                        <a class="nav-link active"
                           href="javascript:void(0)"
                           data-category="PATHOLOGY">
                            Pathology
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link"
                           href="javascript:void(0)"
                           data-category="NON_PATHOLOGY">
                            Non-Pathology
                        </a>
                    </li>

                </ul>

                <div class="btn-group" id="invoiceDaysFilter" role="group">

                    <button type="button" class="btn btn-sm btn-primary" data-days="3">3 Days</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-days="7">7 Days</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-days="15">15 Days</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-days="30">30 Days</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-days="60">60 Days</button>

                </div>

            </div>

            <div class="text-muted mb-2" style="font-size:11px;">
                Showing invoices from the last <span id="invoiceDaysLabel">3</span> day(s), including today.
            </div>

            <div class="table-responsive">

                <table class="table table-bordered align-middle"
                       id="invoiceTable">

                    <thead class="table-light">

                        <tr>

                            <th>Inv No</th>

                            <th>PID</th>

                            <th>Patient</th>

                           

                           <th>Inv Dt</th>
                            <th>Test Dt</th>
                            <th>Invoice Category</th>

                            <th>Total</th>

                            <th>Discount</th>

                            <th>Paid</th>
                            <th>Due</th>
                            <th>Dr Pay</th>
                           
                            <th>Status</th>
                            <th>WA</th>
                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    </tbody>

                </table>

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

<script src="{{ URL::asset('build/js/pages/apps-diagnostic-invoice.init.js') }}"></script>

@endsection
