@extends('layouts.master')

@section('title')
    Test Parameter Entry
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
        Test Parameter Entry
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <div class="row mb-3">

                    <div class="col-md-5">

                        <label class="form-label fw-semibold">Select Item Name</label>

                        <select id="item-select" class="form-select">

                            <option value="">Select Item Name</option>

                            @foreach($itemMasters as $master)
                                <option value="{{ $master->item_code }}">{{ $master->item_name }}</option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">Search Description</label>

                        <input type="text"
                               id="searchInput"
                               class="form-control"
                               placeholder="Search by description">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label fw-semibold">Records Per Page</label>

                        <select id="perPageSelect" class="form-select">

                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>

                        </select>

                    </div>

                </div>

                <div id="parameter-table-wrap" style="display:none;">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>
                                    <th>Item Code</th>
                                    <th>Sub Code</th>
                                    <th>Description</th>
                                    <th>Test Group</th>
                                    <th>Sample</th>
                                    <th>UOM</th>
                                    <th>Range (Male)</th>
                                    <th>Range (Female)</th>
                                    <th>Range (Common)</th>
                                    <th>Method</th>
                                    <th>Report Days</th>
                                    <th width="140">Action</th>
                                </tr>

                            </thead>

                            <tbody id="parameter-table-body">

                            </tbody>

                        </table>

                        <div class="d-flex justify-content-between align-items-center mt-3">

                            <div id="pagination-info"></div>

                            <div class="d-flex align-items-center gap-2">

                                <button class="btn btn-sm btn-primary" id="firstPage">First</button>

                                <button class="btn btn-sm btn-primary" id="prevPage">Previous</button>

                                <span id="pageNumber">Page 1</span>

                                <button class="btn btn-sm btn-primary" id="nextPage">Next</button>

                                <button class="btn btn-sm btn-primary" id="lastPage">Last</button>

                            </div>

                        </div>

                    </div>

                </div>

                <div id="no-selection-msg" class="text-muted">
                    Select an item name above to view and edit its test parameters.
                </div>

            </div>

        </div>

    </div>

</div>

<!-- MODAL -->

<div class="modal fade"
     id="showModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">
                    Update Test Parameter
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form class="tablelist-form">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <div id="analyte-notice" class="alert alert-info" style="display:none;">
                        <i class="ri-information-line align-bottom me-1"></i>
                        This test also has individually configured analytes (via Manage Analytes). The UOM/Range/Method below apply to this test's own result, which staff may optionally enter in Test Result Entry in addition to the individual analyte results.
                    </div>

                    <div class="row">

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Item Code</label>
                            <input type="text" id="display-item_code" class="form-control" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sub Code</label>
                            <input type="text" id="display-item_code_sub" class="form-control" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" id="display-item_description_sub" class="form-control" readonly>
                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">Test Group</label>

                            <select id="test_group_code-field" class="form-select">
                                <option value="">Select Test Group</option>
                                @foreach($testGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->test_group_name }}</option>
                                @endforeach
                            </select>

                        </div>

                        <div class="col-md-4 mb-3">

                            <label class="form-label">Sample</label>

                            <select id="sample_master_id-field" class="form-select">
                                <option value="">Select Sample</option>
                                @foreach($sampleMasters as $sample)
                                    <option value="{{ $sample->id }}">{{ $sample->name }}</option>
                                @endforeach
                            </select>

                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">UOM</label>
                            <select id="uom-field" class="form-select">
                                <option value="">Select UOM</option>
                                @foreach($uomMasters as $uom)
                                    <option value="{{ $uom->name }}">{{ $uom->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Report Days</label>
                            <input type="number" id="report_days-field" class="form-control" min="0">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Range (Male)</label>
                            <div class="input-group">
                                <input type="text" id="range_male-field" class="form-control">
                                <select class="form-select range-quick-pick" style="max-width:110px;" title="Pick a saved detail range"></select>
                                <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert ±">&plusmn;</button>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Range (Female)</label>
                            <div class="input-group">
                                <input type="text" id="range_female-field" class="form-control">
                                <select class="form-select range-quick-pick" style="max-width:110px;" title="Pick a saved detail range"></select>
                                <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert ±">&plusmn;</button>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Range (Common)</label>
                            <div class="input-group">
                                <input type="text" id="range_common-field" class="form-control">
                                <select class="form-select range-quick-pick" style="max-width:110px;" title="Pick a saved detail range"></select>
                                <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert ±">&plusmn;</button>
                            </div>
                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-label">Method</label>
                            <input type="text" id="method-field" class="form-control">
                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                    <button type="submit" class="btn btn-success" id="add-btn">Save</button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- MANAGE ANALYTES MODAL -->

<div class="modal fade"
     id="analyteModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">
                    Manage Analytes — <span id="analyte-parent-name"></span>
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <input type="hidden" id="analyte-parent-id">

                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">

                            <div class="col-md-4">
                                <label class="form-label small mb-1">Copy Analytes &amp; Sub Groups From Another Test</label>
                                <select id="copy-source-category" class="form-select form-select-sm">
                                    <option value="">Select Category</option>
                                    @foreach($itemMasters as $master)
                                        <option value="{{ $master->item_code }}">{{ $master->item_name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-5">
                                <select id="copy-source-test" class="form-select form-select-sm" disabled>
                                    <option value="">Select Test</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btnCopyAnalytes" disabled>
                                    <i class="ri-file-copy-line align-bottom me-1"></i>
                                    Copy
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-2">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="14%">Analyte Name</th>
                                <th width="10%">Group</th>
                                <th width="8%">UOM</th>
                                <th width="12%">Range (Male)</th>
                                <th width="12%">Range (Female)</th>
                                <th width="14%">Range (Common)</th>
                                <th width="12%">Method</th>
                                <th width="8%">Status</th>
                                <th width="6%"></th>
                            </tr>
                        </thead>

                        <tbody id="analyteTableBody">
                        </tbody>

                        <tfoot>
                            <tr>
                                <td><input type="text" class="form-control form-control-sm" id="new-analyte_name"></td>
                                <td><input type="text" class="form-control form-control-sm" id="new-analyte_group" placeholder="e.g. A" title="Optional group heading (e.g. A, B) to group analytes under on the report"></td>
                                <td>
                                    <select class="form-select form-select-sm" id="new-analyte_uom">
                                        <option value="">Select</option>
                                        @foreach($uomMasters as $uom)
                                            <option value="{{ $uom->name }}">{{ $uom->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="new-analyte_range_male">
                                        <select class="form-select form-select-sm range-quick-pick" style="max-width:60px;" title="Pick a saved detail range"></select>
                                        <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert ±">&plusmn;</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="new-analyte_range_female">
                                        <select class="form-select form-select-sm range-quick-pick" style="max-width:60px;" title="Pick a saved detail range"></select>
                                        <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert ±">&plusmn;</button>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" id="new-analyte_range_common">
                                        <select class="form-select form-select-sm range-quick-pick" style="max-width:60px;" title="Pick a saved detail range"></select>
                                        <button type="button" class="btn btn-outline-secondary insert-plusminus" title="Insert ±">&plusmn;</button>
                                    </div>
                                </td>
                                <td><input type="text" class="form-control form-control-sm" id="new-analyte_method"></td>
                                <td></td>
                                <td>
                                    <button class="btn btn-sm btn-success" id="btnAddAnalyteRow" title="Add">
                                        <i class="ri-add-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>

                    </table>

                </div>

                <hr>

                <h6 class="mb-1">Sub Groups</h6>
                <p class="text-muted small mb-2">
                    Club several of the analyte groups above under a broader sub-group heading
                    (e.g. Sub Group "Liver Panel" = groups A + B). A test can have several sub-groups.
                </p>

                <div class="table-responsive mb-2">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th width="25%">Sub Group Name</th>
                                <th>Clubbed Groups</th>
                                <th width="110">Action</th>
                            </tr>
                        </thead>

                        <tbody id="subGroupTableBody">
                        </tbody>

                        <tfoot>
                            <tr>
                                <td>
                                    <input type="text" class="form-control form-control-sm" id="new-subgroup_name" placeholder="e.g. Liver Panel">
                                </td>
                                <td>
                                    <div id="new-subgroup_groups" class="d-flex flex-wrap gap-2"></div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-success" id="btnAddSubGroupRow" title="Add">
                                        <i class="ri-add-line"></i>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>

                    </table>

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

<script>
    window.uomMasterOptions = @json($uomMasters->pluck('name'));
</script>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/test-parameter.init.js') }}"></script>

@endsection
