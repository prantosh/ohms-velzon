@extends('layouts.master')

@section('title')
    Diagnostic Test Report
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Diagnostic Test Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <div class="row mb-3 align-items-end">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">Invoice Number</label>

                        <input type="text"
                               id="invoiceNoInput"
                               class="form-control"
                               placeholder="Enter Invoice Number">

                    </div>

                    <div class="col-md-2">

                        <button class="btn btn-primary w-100" id="btnSearchInvoice">
                            <i class="ri-search-line"></i>
                            Search
                        </button>

                    </div>

                </div>

                <div id="invoiceNotFoundMsg" class="text-danger mb-3" style="display:none;">
                    Invoice not found, or it has no diagnostic tests.
                </div>

                <div id="invoiceInfoWrap" class="mb-3" style="display:none;">

                    <div class="border rounded p-3 bg-light-subtle">

                        <div class="row">

                            <div class="col-md-3">
                                <strong>Invoice No:</strong>
                                <span id="info-invoice_no"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Invoice Date:</strong>
                                <span id="info-invoice_date"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Patient:</strong>
                                <span id="info-patient_name"></span>
                            </div>

                            <div class="col-md-3">
                                <strong>Confirmation Status:</strong>
                                <span id="info-confirmed_status"></span>
                            </div>

                        </div>

                    </div>

                </div>

                <div id="notConfirmedMsg" class="text-warning mb-3" style="display:none;">
                    <i class="ri-error-warning-line align-bottom me-1"></i>
                    This invoice's test results have not been confirmed yet in Test Result Entry. Printing is disabled until confirmed.
                </div>

                <div id="groupsTableWrap" style="display:none;">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>
                                    <th>Test Group</th>
                                    <th width="15%">Test Count</th>
                                    <th width="15%">Results Entered</th>
                                    <th width="15%">Action</th>
                                </tr>

                            </thead>

                            <tbody id="groupsTableBody">

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/diagnostic-test-report.init.js') }}"></script>

@endsection
