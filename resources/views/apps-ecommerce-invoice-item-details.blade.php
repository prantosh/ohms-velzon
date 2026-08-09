@extends('layouts.master')

@section('title')
Invoice Item Details
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

<style>
    #invoiceItemDetailTable thead th {
        white-space: nowrap;
        vertical-align: middle;
        text-align: center;
        font-size: 12px;
        line-height: 1.15;
    }

    #invoiceItemDetailTable tbody td {
        font-size: 12px;
    }
    .select2-container {
        width: 100% !important;
    }

    .select2-selection {
        min-height: 38px !important;
        border: 1px solid #ced4da !important;
    }

    .select2-selection__rendered {
        line-height: 36px !important;
    }

    .select2-selection__arrow {
        height: 36px !important;
    }
.table>thead>tr>th{

    background:#0d6efd;
    color:#fff;
    vertical-align:middle;
    white-space:nowrap;

}

.table td{

    vertical-align:middle;

}

.modal-header{

    background:#0d6efd;
    color:#fff;

}

.required{

    color:red;

}

.dataTables_filter{

    display:none;

}

.numeric{

    text-align:right;

}

.readonly{

    background:#f8f9fa;

}

</style>

@endsection


@section('content')

@component('components.breadcrumb')

@slot('li_1')

Masters

@endslot

@slot('title')

Invoice Item Details

@endslot

@endcomponent


<div class="row">

<div class="col-12">

<div class="card">

<div class="card-header">

<div class="row align-items-center">

<div class="col-md-6">

<h4 class="card-title mb-0">

Invoice Item Details

</h4>

</div>

<div class="col-md-6 text-end">

<button
class="btn btn-success"
id="btnAdd">

<i class="ri-add-line"></i>

Add New

</button>

<button
class="btn btn-primary"
id="btnReload">

<i class="ri-refresh-line"></i>

Refresh

</button>

<button
class="btn btn-info"
id="btnExcel">

<i class="ri-file-excel-line"></i>

Excel

</button>

</div>

</div>

</div>


<div class="card-body">

<div class="row mb-3">

<div class="col-md-4">

<input
type="text"
id="searchText"
class="form-control"
placeholder="Search...">

</div>

<div class="col-md-4 d-flex align-items-center">

<div class="form-check">
    <input type="checkbox" id="packagesOnlyFilter" class="form-check-input">
    <label class="form-check-label" for="packagesOnlyFilter">
        Show Packages Only
    </label>
</div>

</div>

</div>

<div class="table-responsive">

<table
class="table table-bordered table-hover align-middle"
id="invoiceItemDetailTable"
width="100%">

<thead class="table-light">

<tr>

    <th rowspan="2" class="text-center align-middle">Sl</th>

    <th rowspan="2" class="text-center align-middle">Item<br>Code</th>

    <th rowspan="2" class="text-center align-middle">Item<br>Name</th>

    <th rowspan="2" class="text-center align-middle">Item<br>Type</th>

    <th rowspan="2" class="text-center align-middle">Sub<br>Code</th>

    <th rowspan="2" class="text-center align-middle">Test Description</th>

    <th rowspan="2" class="text-center align-middle">Rate<br>(₹)</th>

    <th colspan="4" class="text-center bg-soft-info">
        Discount %
    </th>

    <th rowspan="2" class="text-center align-middle">
        Test<br>Group
    </th>

    <th rowspan="2" class="text-center align-middle">
        UOM
    </th>

    <th rowspan="2" class="text-center align-middle">
        Range<br>(Male)
    </th>

    <th rowspan="2" class="text-center align-middle">
        Range<br>(Female)
    </th>

    <th rowspan="2" class="text-center align-middle">
        Range<br>(Common)
    </th>

    <th rowspan="2" class="text-center align-middle">
        Method
    </th>

    <th rowspan="2" class="text-center align-middle">
        Report<br>Days
    </th>

    <th rowspan="2" class="text-center align-middle">
        Package
    </th>

    <th rowspan="2" class="text-center align-middle">
        Status
    </th>

    <th rowspan="2" class="text-center align-middle">
        Action
    </th>

</tr>

<tr>

    <th class="text-center">
        Standard
    </th>

    <th class="text-center">
        Member
    </th>

    <th class="text-center">
        Other Lab
    </th>

    <th class="text-center">
        Member +<br>Other Lab
    </th>

</tr>

</thead>

<tbody>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>

<!-- Add / Edit Modal -->

<div class="modal fade"
     id="itemDetailModal"
     tabindex="-1">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title"
                    id="modalTitle">

                    Add Invoice Item Detail

                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <input type="hidden"
                       id="record_id">

                <!-- Row 1 -->

                <div class="row">

                    <div class="col-md-4">

                        <label class="form-label">

                            Item Code
                            <span class="required">*</span>

                        </label>

                        <select id="item_code"
                                class="form-select select2">

                            <option value="">Select Item Code</option>

                            @foreach($itemMasters as $item)

                                <option value="{{ $item->item_code }}">

                                    {{ $item->item_code }}
                                    -
                                    {{ $item->item_name }}

                                </option>

                            @endforeach

                        </select>

                        <div class="invalid-feedback"
                             id="error_item_code">
                        </div>

                    </div>

                    <div class="col-md-5">

                        <label class="form-label">

                            Item Name

                        </label>

                        <input type="text"
                               id="item_name"
                               class="form-control readonly"
                               readonly>

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Item Type

                        </label>

                        <input type="text"
                               id="item_type"
                               class="form-control readonly"
                               readonly>

                    </div>

                </div>

                <!-- Row 2 -->

                <div class="row mt-3">

                    <div class="col-md-3">

                        <label class="form-label">

                            Sub Item Code

                        </label>

                        <input

                            type="text"

                            id="item_code_sub"

                            class="form-control bg-light"

                            readonly>

                    </div>

                    <div class="col-md-9">

                        <label class="form-label">

                            Description
                            <span class="required">*</span>

                        </label>

                        <input type="text"
                               id="item_description_sub"
                               class="form-control">

                        <div class="invalid-feedback"
                             id="error_item_description_sub">
                        </div>

                    </div>

                </div>

                <!-- Row 2b: Package -->

                <div class="row mt-3">

                    <div class="col-md-3">

                        <div class="form-check mt-4">
                            <input type="checkbox"
                                   id="is_package"
                                   class="form-check-input">
                            <label class="form-check-label" for="is_package">
                                This is a Package (bills its components instead of itself)
                            </label>
                        </div>

                    </div>

                    <div class="col-md-9" id="componentsWrap" style="display:none;">

                        <label class="form-label">
                            Component Tests
                            <span class="required">*</span>
                        </label>

                        <select id="components"
                                class="form-select select2"
                                multiple>
                        </select>

                        <div class="invalid-feedback"
                             id="error_components">
                        </div>

                    </div>

                </div>

                <!-- Row 3 -->

                <div class="row mt-3">

                    <div class="col-md-3">

                        <label class="form-label">

                            Rate

                        </label>

                        <input type="number"
                               step="0.01"
                               id="rate"
                               class="form-control numeric">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Standard Discount

                        </label>

                        <input type="number"
                               step="0.01"
                               id="standard_discount"
                               class="form-control numeric">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Member Discount

                        </label>

                        <input type="number"
                               step="0.01"
                               id="member_discount"
                               class="form-control numeric">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Status

                        </label>

                        <select id="item_status"
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

                <!-- Row 4 -->

                <div class="row mt-3">

                    <div class="col-md-4">

                        <label class="form-label">

                            Other Lab Discount

                        </label>

                        <input type="number"
                               step="0.01"
                               id="other_lab_discount"
                               class="form-control numeric">

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">

                            Member Other Lab Discount

                        </label>

                        <input type="number"
                               step="0.01"
                               id="member_other_lab_discount"
                               class="form-control numeric">

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">

                            Test Group

                        </label>

                        <select id="test_group_code"
                                class="form-select">

                            <option value="">Select Test Group</option>

                            @foreach($testGroups as $group)
                                <option value="{{ $group->id }}">{{ $group->test_group_name }}</option>
                            @endforeach

                        </select>

                    </div>

                </div>

                <div id="testDetailsWrap">

                <!-- Row 5 -->

                <div class="row mt-3">

                    <div class="col-md-3">

                        <label class="form-label">

                            UOM

                        </label>

                        <input type="text"
                               id="uom"
                               class="form-control">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Male Range

                        </label>

                        <div class="input-group">
                            <input type="text"
                                   id="male_range"
                                   class="form-control">
                            <select class="form-select range-quick-pick" style="max-width:110px;" title="Pick a saved detail range"></select>
                            <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert &plusmn;">&plusmn;</button>
                        </div>

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Female Range

                        </label>

                        <div class="input-group">
                            <input type="text"
                                   id="female_range"
                                   class="form-control">
                            <select class="form-select range-quick-pick" style="max-width:110px;" title="Pick a saved detail range"></select>
                            <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert &plusmn;">&plusmn;</button>
                        </div>

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Common Range

                        </label>

                        <div class="input-group">
                            <input type="text"
                                   id="common_range"
                                   class="form-control">
                            <select class="form-select range-quick-pick" style="max-width:110px;" title="Pick a saved detail range"></select>
                            <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert &plusmn;">&plusmn;</button>
                        </div>

                    </div>

                </div>

                <!-- Row 6 -->

                <div class="row mt-3">

                    <div class="col-md-8">

                        <label class="form-label">

                            Method

                        </label>

                        <textarea id="method"
                                  rows="2"
                                  class="form-control"></textarea>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label">

                            Report Days

                        </label>

                        <input type="number"
                               id="report_days"
                               class="form-control numeric">

                    </div>

                </div>

                </div>

            </div>

            <div class="modal-footer">

                <button class="btn btn-light"
                        data-bs-dismiss="modal">

                    Close

                </button>

                <button class="btn btn-primary"
                        id="btnSave">

                    Save

                </button>

            </div>

        </div>

    </div>

</div>
@endsection


@section('script')

<script>

window.invoiceItemDetailRoutes = {

    list       : "{{ route('invoice-item-details.list') }}",
    store      : "{{ route('invoice-item-details.store') }}",
    index      : "{{ route('invoice-item-details.index') }}",
    master     : "{{ url('invoice-item-details/master') }}",
    components : "{{ url('invoice-item-details/components') }}",
    excel      : "{{ route('invoice-item-details.excel') }}",
    token      : "{{ csrf_token() }}"
};

</script>


<!-- Sweet Alert -->

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<!-- JQuery -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Select2 -->

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css"
      rel="stylesheet"/>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- DataTables -->

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<!-- CRUD JS -->

<script src="{{ URL::asset('build/js/pages/ecommerce-invoice-item-detail.init.js') }}"></script>

@endsection
