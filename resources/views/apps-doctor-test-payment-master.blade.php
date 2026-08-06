@extends('layouts.master')

@section('title')
Doctor Test Payment Master
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

@endsection

@section('content')

<div class="container-fluid">

    <div class="card">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h4 class="card-title mb-0">

                Doctor Test Payment Master

            </h4>

            <button
                type="button"
                class="btn btn-primary"
                id="addBtn">

                <i class="ri-add-line"></i>

                Add Payment Rule

            </button>

        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table
                    class="table table-bordered align-middle"
                    id="paymentTable">

                    <thead class="table-light">

                    <tr>

                        <th>ID</th>

                        <th>Doctor</th>

                        <th>Test Category</th>

                        <th>Test</th>

                        <th>Type</th>

                        <th>Value</th>

                        <th>Effective From</th>

                        

                        <th>Status</th>

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

<!-- ==========================================================
     MODAL
========================================================== -->

<div
    class="modal fade"
    id="paymentModal"
    tabindex="-1">

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Doctor Test Payment

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <form id="paymentForm">

                @csrf

                <input
                    type="hidden"
                    id="id"
                    name="id">

                <div class="modal-body">

                    <div class="row">

                        <!-- Doctor Specialisation (filters the doctor checkbox list) -->

                        <div class="col-md-6 mb-3" id="specialisationWrap">

                            <label>

                                Doctor Category (Specialisation)

                            </label>

                            <select
                                id="specialisation"
                                name="specialisation"
                                class="form-select">

                                <option value="">

                                    Select Category

                                </option>

                                @foreach($specialisations as $spec)

                                <option value="{{ $spec }}">
                                    {{ $spec }}
                                </option>

                                @endforeach

                            </select>

                        </div>

                        <!-- Doctors (checkbox list, populated after Category is chosen) -->

                        <div class="col-md-12 mb-3" id="doctorCheckboxWrap">

                            <label>

                                Doctors

                            </label>

                            <div
                                id="doctorCheckboxList"
                                class="border rounded p-2"
                                style="max-height:180px; overflow-y:auto;">

                                <span class="text-muted">Select a category to list doctors.</span>

                            </div>

                            <div class="border rounded p-2 mt-2 d-none" id="staffDoctorWrap">
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="doctorStaff"
                                        name="doctor_ids[]"
                                        value="{{ $staffDoctor->id ?? '' }}">
                                    <label class="form-check-label fw-semibold" for="doctorStaff">
                                        {{ $staffDoctor->doctor_name ?? 'STAFF' }} <small class="text-muted">(Diagnostic tests performed by staff)</small>
                                    </label>
                                </div>
                            </div>

                        </div>

                        <!-- Doctor (read-only, edit mode only) -->

                        <div class="col-md-12 mb-3 d-none" id="editDoctorWrap">

                            <label>

                                Doctor

                            </label>

                            <input
                                type="text"
                                id="editDoctorDisplay"
                                class="form-control"
                                readonly>

                        </div>

                        <!-- Category -->

                        <div class="col-md-6 mb-3">

                            <label>

                                Test Category

                            </label>

                            <select
                                id="item_code"
                                name="item_code"
                                class="form-select">

                                <option value="">

                                    Select Test Category

                                </option>

                                @foreach($categories as $row)

                                <option
                                    value="{{ $row->item_code }}"
                                    data-item-type="{{ $row->item_type }}">

                                    {{ $row->item_name }}

                                </option>

                                @endforeach

                            </select>

                        </div>

                        <!-- Test -->

                        <div class="col-md-6 mb-3">

                            <label>

                                Test

                            </label>

                            <select
                                id="item_code_sub"
                                name="item_code_sub"
                                class="form-select">

                                <option value="">

                                    Select Test

                                </option>

                            </select>

                        </div>


                        <!-- Payment Type -->

                        <div class="col-md-4 mb-3">

                            <label>

                                Payment Type

                            </label>

                            <select
                                id="payment_type"
                                name="payment_type"
                                class="form-select">

                                <option value="FIXED">

                                    FIXED

                                </option>

                                <option value="PERCENTAGE">

                                    PERCENTAGE

                                </option>

                            </select>

                        </div>

                        <!-- Payment Value -->

                        <!-- Payment Value -->
                        <div class="col-md-4 mb-3">

                            <label>
                                Payment Value
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                id="payment_value"
                                name="payment_value"
                                class="form-control">

                        </div>

                        <!-- Status -->
                        <div class="col-md-4 mb-3">

                            <label>
                                Status
                            </label>

                            <select
                                id="payment_status"
                                name="status"
                                class="form-select">

                                <option value="A">
                                    Active
                                </option>

                                <option value="I">
                                    Inactive
                                </option>

                            </select>

                        </div>
                        <!-- Effective From -->

                        

                        

                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">

                        Close

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Save

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>



<script
src="{{ URL::asset('build/js/pages/apps-doctor-test-payment-master.init.js') }}">
</script>

@endsection
