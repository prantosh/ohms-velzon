@extends('layouts.master')

@section('title')
Pay Rest Payment
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet">

@endsection

@section('content')

<div class="container-fluid">

<form id="invoiceForm">

@csrf

<input
    type="hidden"
    id="invoice_id"
    name="invoice_id"
    value="{{ $invoice->id }}">
<input
    type="hidden"
    id="payment_edit_count"
    value="{{ $invoice->payment_edit_count }}">
<div class="card">

    <div class="card-header">

        <h4 class="card-title">

            Pay Rest Payment

        </h4>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3 mb-3">

                <label>

                    Invoice No

                </label>

                <input
                    type="text"
                    class="form-control"
                    value="{{ $invoice->invoice_no }}"
                    readonly>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Invoice Date

                </label>

                <input
                    type="text"
                    id="invoice_date"
                    class="form-control"
                    value="{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') : '' }}"
                    readonly>

            </div>
            <div class="col-md-3 mb-3">

                <label>Test Date</label>

                <input
                    type="text"
                    id="test_date"
                    class="form-control"
                    value="{{ $invoice->test_date ? \Carbon\Carbon::parse($invoice->test_date)->format('d-m-Y') : '' }}"
                    readonly>

            </div>
            <div class="col-md-3 mb-3">

                <label>

                    Patient ID

                </label>

                <input
                    type="text"
                    id="patient_id"
                    class="form-control"
                    value="{{ $invoice->patient_id }}"
                    readonly>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Patient Name

                </label>

                <input
                    type="text"
                    id="patient_name"
                    class="form-control"
                    value="{{ $invoice->patient_name }}"
                    readonly>

            </div>

            <div class="col-md-2 mb-3">

                <label>

                    Age

                </label>

                <input
                    type="text"
                    id="patient_age"
                    class="form-control"
                    value="{{ $invoice->patient_age }}"
                    readonly>

            </div>

            <div class="col-md-2 mb-3">

                <label>

                    Gender

                </label>

                <select
                    id="patient_gender"
                    class="form-select"
                    disabled>

                    <option value="Male"
                    {{ $invoice->patient_gender=='Male' ? 'selected' : '' }}>

                        Male

                    </option>

                    <option value="Female"
                    {{ $invoice->patient_gender=='Female' ? 'selected' : '' }}>

                        Female

                    </option>

                    <option value="Other"
                    {{ $invoice->patient_gender=='Other' ? 'selected' : '' }}>

                        Other

                    </option>

                </select>

            </div>

            <div class="col-md-4 mb-3">

                <label>

                    Mobile No

                </label>

                <input
                    type="text"
                    id="patient_mobile_no"
                    class="form-control"
                    value="{{ $invoice->patient_mobile_no }}"
                    readonly>

            </div>
            <div class="col-md-4 mb-3">

                <label>
                    Referred Doctor
                </label>

                <input
                    type="text"
                    id="referred_doctor"
                    class="form-control"
                    value="{{ $invoice->referred_doctor }}"
                    readonly>

            </div>
        </div>

    </div>

</div>

<!-- TEST DETAILS -->

<div class="card">

    

    <div class="card-body">

        <div class="table-responsive">

            <table
                class="table table-bordered"
                id="testTable">

                <thead>

                <tr>

                    <th>Category</th>

                    <th>Test</th>

                    <th>Rate</th>

                    <th>Std. Disc %</th>
                    <th>Addl. Disc %</th>
                    <th>Addl. Amt</th>
                    <th>Approved By</th>
                    <th>Amount</th>
                    <th>Remarks</th>
                    <th>Doctor</th>
                    <th>Doctor Payment</th>
                    <th>Waive Doctor Payment</th>

                </tr>

                </thead>

                <tbody>

                @foreach($tests as $row)

                <tr>

                    <td>

                        <select
                            class="form-select category"
                            disabled>

                            <option value="">

                                Select

                            </option>

                            @foreach($categories as $cat)

                            <option
                                value="{{ $cat->item_code }}"
                                {{ $row->item_code==$cat->item_code ? 'selected':'' }}>

                                {{ $cat->item_name }}

                            </option>

                            @endforeach

                        </select>

                    </td>

                    <td>

                        <input
                            type="hidden"
                            class="item_code_sub"
                            value="{{ $row->item_code_sub }}">

                        <input
                            type="text"
                            class="form-control test_name"
                            value="{{ $row->item_description }}"
                            readonly>

                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control rate"
                            value="{{ $row->rate }}"
                               readonly>

                    </td>

                    <td>
                        <input type="number"
                               class="form-control standardDiscount"
                               value="{{ $row->standard_discount }}"
                               readonly>
                    </td>

                    <td>
                        <input type="number"
                               class="form-control additionalDiscountPercent"
                               value="{{ $row->additional_discount_percent }}"
                               readonly>
                    </td>

                    <td>
                        <input type="number"
                               class="form-control additionalDiscountAmount"
                               value="{{ $row->additional_discount_amount }}"
                               readonly>
                    </td>

                    <td>
                        <input type="text"
                               class="form-control"
                               value="{{ $row->discount_approved_by }}"
                               readonly>
                    </td>

                    <td>

                        <input
                            type="number"
                            class="form-control amount"
                            value="{{ $row->amount }}"
                            readonly>

                    </td>

                    <td>

                        <input
                            type="text"
                            class="form-control remarksRow"
                            value="{{ $row->remarks }}"
                               readonly>

                    </td>

                    <td>
                        {{ $row->doctor_id ? $row->doctor_name : '-' }}
                    </td>

                    <td>
                        @if($row->payment_value > 0)
                            <input
                                type="text"
                                class="form-control rowDoctorPayment"
                                value="{{ number_format($row->payment_value, 2) }}"
                                readonly>
                        @else
                            -
                        @endif
                    </td>

                    <td>
                        @if($row->doctor_id && $row->payment_value > 0)
                            @if($row->doctor_payment_waived)
                                <span class="badge bg-success">Waived</span>
                            @else
                                <div class="form-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input waiveLine"
                                        name="waive_lines[]"
                                        value="{{ $row->id }}"
                                        data-payment-value="{{ $row->payment_value }}"
                                        id="waive_line_{{ $row->id }}">
                                    <label
                                        class="form-check-label small"
                                        for="waive_line_{{ $row->id }}">
                                        Waive
                                    </label>
                                </div>
                            @endif
                        @else
                            -
                        @endif
                    </td>

                </tr>

                @endforeach

                </tbody>

            </table>

        </div>

    </div>

</div>

<!-- PAYMENT -->

<div class="card">

    <div class="card-header">

        <h4 class="card-title">

            Payment Details

        </h4>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-3 mb-3">

                <label>

                    Total Amount

                </label>

                <input
                    type="number"
                    id="total_amount"
                    class="form-control"
                    value="{{ $invoice->total_amount }}"
                    readonly>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Discount

                </label>

                <input
                    type="number"
                    id="discount"
                    class="form-control"
                    value="{{ $invoice->discount }}"
                    readonly>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Total Paid Amount

                </label>

                <input
                    type="number"
                    id="paid_amount"
                    class="form-control"
                    value="{{ $invoice->paid_amount }}"
                    readonly>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Due Amount

                </label>

                <input
                    type="number"
                    id="due_amount"
                    class="form-control"
                    value="{{ $invoice->due_amount }}"
                    readonly>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Amount Paid against Due Amount

                </label>

               <input
                    type="number"
                    name="additional_paid_amount"
                    id="additional_paid_amount"
                    class="form-control">

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Payment Mode

                </label>

                <select
                    id="payment_mode"
                    name="payment_mode"
                    class="form-select">

                    {{--
                        Deliberately NOT pre-selected from $invoice->payment_mode --
                        that column only ever holds whichever payment (initial,
                        phase 1, or phase 2) was collected LAST, so pre-filling
                        this due-payment form from it silently carried the
                        previous phase's mode forward even when staff was
                        actually collecting a different one this time (e.g. an
                        initial online payment made every later cash phase
                        payment save as "non-cash" unless someone happened to
                        notice and change the dropdown). Cash is just the first
                        listed option, same neutral default as the create form.
                    --}}

                    <option value="Cash">
                        Cash
                    </option>

                    <option value="Card">
                        Card
                    </option>

                    <option value="UPI">
                        UPI
                    </option>

                </select>

            </div>

            <div class="col-md-3 mb-3">

                <label>

                    Status

                </label>

                <select
                    id="invoice_status"
                    class="form-select"
                    disabled>

                    <option value="Paid"
                    {{ $invoice->status=='Paid' ? 'selected':'' }}>

                        Paid

                    </option>

                    <option value="Partial"
                    {{ $invoice->status=='Partial' ? 'selected':'' }}>

                        Partial

                    </option>

                    <option value="Due"
                    {{ $invoice->status=='Due' ? 'selected':'' }}>

                        Due

                    </option>

                </select>

            </div>

            <div class="col-md-6 mb-3">

                <label>

                    Remarks

                </label>

                <textarea
                    id="remarks"
                    name="remarks"
                    class="form-control">{{ $invoice->remarks }}</textarea>

            </div>

        </div>
        <div class="row">

    <div class="col-md-3">

        <label>1st Due Payment</label>

        <input
            type="text"
            class="form-control"
            value="{{ $invoice->paid_amount_1 }}"
            readonly>

    </div>

    <div class="col-md-3">

        <label>1st Payment Date</label>

        <input
            type="text"
            class="form-control"
            value="{{ $invoice->paid_date_1 }}"
            readonly>

    </div>

    <div class="col-md-3">

        <label>2nd Due Payment</label>

        <input
            type="text"
            class="form-control"
            value="{{ $invoice->paid_amount_2 }}"
            readonly>

    </div>

    <div class="col-md-3">

        <label>2nd Payment Date</label>

        <input
            type="text"
            class="form-control"
            value="{{ $invoice->paid_date_2 }}"
            readonly>

    </div>

</div>

    </div>

</div>

<div class="text-end mb-5">

    <a href="{{ url('/diagnostic-invoice') }}"
       class="btn btn-secondary">

        Back

    </a>

    <button
        type="submit"
        class="btn btn-success">

        Collect Due Payment

    </button>

</div>

</form>

</div>

<!-- CONFIRM DUE PAYMENT OFFCANVAS -->

<div class="offcanvas offcanvas-end"
     tabindex="-1"
     id="confirmDuePaymentOffcanvas"
     style="width: 450px;">

    <div class="offcanvas-header border-bottom">

        <h5 class="offcanvas-title">Confirm Payment Details</h5>

        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>

    </div>

    <div class="offcanvas-body">

        <table class="table table-sm table-bordered mb-4">

            <tbody>

                <tr><th width="45%">Invoice No</th><td id="review-due-invoice_no">{{ $invoice->invoice_no }}</td></tr>
                <tr><th>Patient Name</th><td id="review-due-patient_name">{{ $invoice->patient_name }}</td></tr>
                <tr><th>Due Amount</th><td id="review-due-due_amount"></td></tr>
                <tr><th>Amount Being Paid Now</th><td id="review-due-additional_paid_amount"></td></tr>
                <tr><th>Payment Mode</th><td id="review-due-payment_mode"></td></tr>
                <tr><th>Remarks</th><td id="review-due-remarks"></td></tr>

            </tbody>

        </table>

        <div class="d-flex gap-2">

            <button type="button" class="btn btn-light w-50" id="btnBackToEditDuePayment">
                Back &amp; Edit
            </button>

            <button type="button" class="btn btn-success w-50" id="btnConfirmDuePayment">
                <i class="ri-checkbox-circle-line"></i>
                Confirm &amp; Collect
            </button>

        </div>

    </div>

</div>

@endsection

@section('script')

<script>

window.editMode = true;

window.invoiceId =
    "{{ $invoice->id }}";

    window.isPathology =
    "{{ $invoice->invoice_category }}" === "PATHOLOGY";

window.categoryOptions = `

<option value="">
Select
</option>

@foreach($categories as $cat)

<option value="{{ $cat->item_code }}">

{{ $cat->item_name }}

</option>

@endforeach

`;

</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/apps-diagnostic-invoice.init.js') }}"></script>


@endsection
