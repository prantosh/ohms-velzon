@extends('layouts.master')

@section('title')
    Expenditure Report
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Reports
    @endslot

    @slot('title')
        Expenditure Report
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-money-rupee-circle-line"></i>
            Category-wise expenditure for any period. The summary lists every category with its number of entries and
            total; <b>Detail</b> on a row (or the Category box) shows the entries of that category, and either can be
            printed as a PDF.
        </div>

        <div class="card">

            <div class="card-body">

                <div class="row align-items-end">

                    <div class="col-md-2 mb-2">
                        <label class="form-label">From <span class="text-danger">*</span></label>
                        <input type="text" id="from_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="form-label">To <span class="text-danger">*</span></label>
                        <input type="text" id="to_date-field" class="form-control flatpickr">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label class="form-label">Category</label>
                        <select id="category-field" class="form-select">
                            <option value="ALL">All Categories</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->description }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4 mb-2">
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary" id="loadBtn"><i class="ri-search-line"></i> Load</button>
                            <a href="javascript:void(0)" id="printSummaryBtn" class="btn btn-success" target="_blank" style="display:none;"><i class="ri-printer-line"></i> Summary PDF</a>
                            <a href="javascript:void(0)" id="printDetailBtn" class="btn btn-outline-success" target="_blank" style="display:none;"><i class="ri-printer-line"></i> Detail PDF</a>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <div class="card" id="summaryCard" style="display:none;">

            <div class="card-header">
                <h5 class="card-title mb-0" id="summaryTitle">Summary</h5>
            </div>

            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered align-middle table-sm">
                        <thead class="table-light">
                            <tr>
                                <th width="60">Sl</th>
                                <th>Category</th>
                                <th class="text-end" width="110">Entries</th>
                                <th class="text-end" width="170">Amount (&#8377;)</th>
                                <th class="text-end" width="100">Share</th>
                                <th width="130"></th>
                            </tr>
                        </thead>
                        <tbody id="summaryBody"></tbody>
                    </table>
                </div>

            </div>

        </div>

        <div class="card" id="detailCard" style="display:none;">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0" id="detailTitle">Detail</h5>
                <button type="button" class="btn btn-sm btn-light" id="hideDetailBtn">Close detail</button>
            </div>

            <div class="card-body" id="detailBody"></div>

        </div>

        <div id="noDataWrap" class="alert alert-warning" style="display:none;">
            No expenditure entries found for this selection.
        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/expenditure-report.init.js') }}"></script>

@endsection
