@extends('layouts.master')

@section('title')
Test Detail Master
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

    .select2-selection__choice {
        background-color: #0d6efd !important;
        border: 1px solid #0d6efd !important;
        color: #fff !important;
        padding: 2px 8px !important;
    }

    .select2-selection__choice__display {
        color: #fff !important;
    }

    .select2-selection__choice__remove {
        color: #fff !important;
        opacity: 0.8;
        margin-right: 6px !important;
    }

    .select2-selection__choice__remove:hover {
        color: #fff !important;
        opacity: 1;
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

Test Detail Master

@endslot

@endcomponent


<div class="row">

<div class="col-12">

<div class="card">

<div class="card-header">

<div class="row align-items-center">

<div class="col-md-6">

<h4 class="card-title mb-0">

Test Detail Master

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

<div class="row mb-3 gy-2">

<div class="col-md-3">

<input
type="text"
id="searchText"
class="form-control"
placeholder="Search...">

</div>

<div class="col-md-3">

<select id="itemNameFilter" class="form-select">
    <option value="">All Item Names</option>
    @foreach($itemMasters as $item)
        <option value="{{ $item->item_code }}">{{ $item->item_name }}</option>
    @endforeach
</select>

</div>

<div class="col-md-2">

<select id="packageFilter" class="form-select">
    <option value="">Package: All</option>
    <option value="1">Package Only</option>
    <option value="0">Non-Package Only</option>
</select>

</div>

<div class="col-md-2">

<select id="outsourcedFilter" class="form-select">
    <option value="">Outsourced: All</option>
    <option value="1">Outsourced Only</option>
    <option value="0">Not Outsourced</option>
</select>

</div>

<div class="col-md-2">

<select id="reportNotRequiredFilter" class="form-select">
    <option value="">Report: All</option>
    <option value="1">Report Not Required</option>
    <option value="0">Report Required</option>
</select>

</div>

</div>

<div class="table-responsive">

<table
class="table table-bordered table-hover align-middle"
id="invoiceItemDetailTable"
width="100%">

<thead class="table-light">

<tr>

    <th class="text-center align-middle">Sl</th>

    <th class="text-center align-middle">Item Name</th>

    <th class="text-center align-middle">Sub Code</th>

    <th class="text-center align-middle">Test Description</th>

    <th class="text-center align-middle">Rate (₹)</th>

    <th class="text-center align-middle">Discount</th>

    <th class="text-center align-middle">Test Group</th>

    <th class="text-center align-middle">UOM</th>

    <th class="text-center align-middle">Range</th>

    <th class="text-center align-middle">Method</th>

    <th class="text-center align-middle">Package</th>

    <th class="text-center align-middle">Outsourced</th>

    <th class="text-center align-middle">Report Not Required</th>

    <th class="text-center align-middle">Status</th>

    <th class="text-center align-middle">Action</th>

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

                <!-- Row 2c: Outsourced -->

                <div class="row mt-3">

                    <div class="col-md-6">

                        <div class="form-check">
                            <input type="checkbox"
                                   id="is_outsourced"
                                   class="form-check-input">
                            <label class="form-check-label" for="is_outsourced">
                                Outsourced (physically performed and reported by an outside agency -- excluded from report entry in this system)
                            </label>
                        </div>

                    </div>

                </div>

                <!-- Row 2d: Report Not Required -->

                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox"
                                   id="is_report_not_required"
                                   class="form-check-input">
                            <label class="form-check-label" for="is_report_not_required">
                                Report Not Required (no report is generated for this item)
                            </label>
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

                            <option value="Y">

                                Active

                            </option>

                            <option value="N">

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

                            Only Senior Citizen Discount (age 60+)

                        </label>

                        <input type="number"
                               step="0.01"
                               id="senior_citizen_discount"
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

                        <div class="invalid-feedback"
                             id="error_uom">
                        </div>

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

                <!-- Key Parameter tracking (edit only -- a new item has no
                     item_code_sub committed yet for this to attach to) -->
                <div id="keyParamWrap"
                     class="mt-3"
                     style="display:none;">

                    <hr>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input"
                               type="checkbox"
                               id="kp_enabled">
                        <label class="form-check-label" for="kp_enabled">
                            Track as Key Parameter (shown with patient history on printed reports)
                        </label>
                    </div>

                    <div id="keyParamFields" style="display:none;">

                        <div class="row">

                            <div class="col-md-4">
                                <label class="form-label">Parameter Name</label>
                                <input type="text" id="kp_parameter_name" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Group Label</label>
                                <input type="text" id="kp_group_label" class="form-control" placeholder="e.g. Lipid Profile">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">UOM</label>
                                <input type="text" id="kp_uom" class="form-control">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" id="kp_sort_order" class="form-control numeric">
                            </div>

                        </div>

                        <div class="row mt-2">

                            <div class="col-md-12">
                                <label class="form-label">
                                    Trigger Item Code(s)
                                    <i class="ri-information-line text-muted"
                                       title="Which billed item code(s) make this parameter capturable. Leave blank to default to this item's own code. For a parameter that's really a component of a bundled panel billed as one line (e.g. SGOT inside LFT), put the panel's own code here instead."></i>
                                </label>
                                <input type="text" id="kp_trigger_item_code_subs" class="form-control" placeholder="Comma-separated, defaults to this item's own code">
                            </div>

                        </div>

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

<!-- Discount Details Modal -->

<div class="modal fade"
     id="discountModal"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Discount Details
                    <span id="discountModalDesc" class="fw-normal"></span>
                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <table class="table table-bordered mb-0">

                    <tbody>

                        <tr>
                            <th>Standard Discount</th>
                            <td id="discountModalStandard" class="text-end"></td>
                        </tr>

                        <tr>
                            <th>Member Discount</th>
                            <td id="discountModalMember" class="text-end"></td>
                        </tr>

                        <tr>
                            <th>Other Lab Discount</th>
                            <td id="discountModalOtherLab" class="text-end"></td>
                        </tr>

                        <tr>
                            <th>Member + Other Lab Discount</th>
                            <td id="discountModalMemberOtherLab" class="text-end"></td>
                        </tr>

                        <tr>
                            <th>Only Senior Citizen Discount</th>
                            <td id="discountModalSenior" class="text-end"></td>
                        </tr>

                    </tbody>

                </table>

            </div>

            <div class="modal-footer">

                <button class="btn btn-light"
                        data-bs-dismiss="modal">
                    Close
                </button>

            </div>

        </div>

    </div>

</div>

<!-- Range Details Modal -->

<div class="modal fade"
     id="rangeModal"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Range Details
                    <span id="rangeModalDesc" class="fw-normal"></span>
                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <table class="table table-bordered mb-0">

                    <tbody>

                        <tr>
                            <th>Range (Male)</th>
                            <td id="rangeModalMale"></td>
                        </tr>

                        <tr>
                            <th>Range (Female)</th>
                            <td id="rangeModalFemale"></td>
                        </tr>

                        <tr>
                            <th>Range (Common)</th>
                            <td id="rangeModalCommon"></td>
                        </tr>

                    </tbody>

                </table>

            </div>

            <div class="modal-footer">

                <button class="btn btn-light"
                        data-bs-dismiss="modal">
                    Close
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

// Creating a NEW package is Admin-only (the server enforces it too) --
// editing an existing package's components stays open to any authorized
// staff role, same convention as report template creation.
window.isAdminUser = {{ (auth()->user() && auth()->user()->role === 'Admin') ? 'true' : 'false' }};

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
