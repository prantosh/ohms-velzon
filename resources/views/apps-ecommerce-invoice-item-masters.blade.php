@extends('layouts.master')

@section('title')
Invoice Item Masters
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
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    #status {
        min-width: 180px;
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

</style>

@endsection


@section('content')

@component('components.breadcrumb')

@slot('li_1')

Masters

@endslot

@slot('title')

Invoice Item Masters

@endslot

@endcomponent


<div class="row">

<div class="col-12">

<div class="card">

<div class="card-header">

<div class="row align-items-center">

<div class="col-md-6">

<h4 class="card-title mb-0">

Invoice Item Masters

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

</div>


<div class="table-responsive">

<table

class="table table-bordered table-hover align-middle"

id="invoiceItemTable"

width="100%">

<thead>

<tr>

<th width="5%">

Sl

</th>

<th>

Item Code

</th>

<th>

Item Name

</th>

<th>

Item Type

</th>

<th>

Description

</th>

<th width="8%">

Status

</th>

<th width="10%">

Test Param Required

</th>

<th width="12%">

Created

</th>

<th width="12%">

Updated

</th>

<th width="10%">

Action

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



<div

class="modal fade"

id="itemModal"

tabindex="-1">

<div class="modal-dialog modal-lg">

<div class="modal-content">


<div class="modal-header">

<h5

class="modal-title"

id="modalTitle">

Add Invoice Item

</h5>

<button

class="btn-close btn-close-white"

data-bs-dismiss="modal">

</button>

</div>


<div class="modal-body">

<input

type="hidden"

id="record_id">



<div class="row">

<div class="col-md-4">

<label>

Item Code

<span class="required">*</span>

</label>

<input

type="text"

id="item_code"

maxlength="6"

class="form-control">

<div

class="invalid-feedback"

id="error_item_code">

</div>

</div>


<div class="col-md-8">

<label>

Item Name

<span class="required">*</span>

</label>

<input

type="text"

id="item_name"

class="form-control">

<div

class="invalid-feedback"

id="error_item_name">

</div>

</div>

</div>




<div class="row mt-3">

    <div class="col-lg-7">

<label>

Item Type

<span class="required">*</span>

</label>

<select

id="item_type"

class="form-select">

<option value="">

Select

</option>

<option value="DOCTOR_VISIT">

Doctor Visit

</option>
<option value="LAB">LAB</option>
<option value="DIAGNOSTIC">

Diagnostic

</option>

<option value="PHARMACY">

Pharmacy

</option>

<option value="PROCEDURE">

Procedure

</option>

<option value="PACKAGE">

Package

</option>
<option value="DONATION">Donation</option>
<option value="OTHER">

Other

</option>

</select>

<div

class="invalid-feedback"

id="error_item_type">

</div>

</div>


<div class="col-lg-5">

    <label class="form-label">
        Status
    </label>

    <select
        id="item_master_status"
        class="form-select">

        <option value="1">Active</option>
        <option value="0">Inactive</option>

    </select>

</div>

<div class="col-lg-5 mt-3">

    <label class="form-label">
        Test Parameter Required
    </label>

    <select
        id="test_parameter_required"
        class="form-select">

        <option value="NO">NO</option>
        <option value="YES">YES</option>

    </select>

</div>

</div>


<div class="row mt-3">

<div class="col-md-12">

<label>

Description

</label>

<textarea

id="description"

rows="4"

class="form-control">

</textarea>

</div>

</div>

</div>


<div class="modal-footer">

<button

class="btn btn-light"

data-bs-dismiss="modal">

Close

</button>

<button

class="btn btn-primary"

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

window.invoiceItemRoutes={

list:"{{ route('invoice-item-masters.list') }}",

store:"{{ route('invoice-item-masters.store') }}",

index:"{{ route('invoice-item-masters.index') }}",

token:"{{ csrf_token() }}"

};

</script>
@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="{{ URL::asset('build/js/pages/ecommerce-invoice-item-master.init.js') }}"></script>

@endsection
