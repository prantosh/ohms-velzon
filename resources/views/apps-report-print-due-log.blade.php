@extends('layouts.master')

@section('title')
    Report Print With Due Log
@endsection

@section('css')
<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Admin
    @endslot

    @slot('title')
        Report Print With Due Log
    @endslot

@endcomponent

<div class="card">

    <div class="card-header">
        <h5 class="card-title mb-0">Reports printed while payment was still due</h5>
    </div>

    <div class="card-body">

        <form method="GET" class="row g-2 align-items-end mb-3">

            <div class="col-md-4">
                <label class="form-label fw-semibold">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                       placeholder="Invoice No / Patient / User">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">From</label>
                <input type="text" name="from" value="{{ request('from') }}" class="form-control flatpickr" placeholder="dd-mm-yyyy">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">To</label>
                <input type="text" name="to" value="{{ request('to') }}" class="form-control flatpickr" placeholder="dd-mm-yyyy">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">Final Payment</label>
                <select name="payment_status" class="form-select">
                    <option value="">All</option>
                    <option value="paid" @selected(request('payment_status') === 'paid')>Fully Paid</option>
                    <option value="due" @selected(request('payment_status') === 'due')>Due</option>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('report-print-due-log.index') }}" class="btn btn-light">Reset</a>
            </div>

        </form>

        <div class="table-responsive">

            <table class="table table-bordered align-middle table-mobile-cards">

                <thead class="table-light">
                    <tr>
                        <th>Report Printed At</th>
                        <th>Invoice No</th>
                        <th>Patient</th>
                        <th>Test Items</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th>Printed By</th>
                        <th>Final Payment Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td data-label="Report Printed At" class="text-nowrap">{{ optional($log->printed_at)->format('d-m-Y H:i') }}</td>
                            <td data-label="Invoice No" class="fw-semibold">{{ $log->invoice_no }}</td>
                            <td data-label="Patient">{{ $log->patient_name }}<br><small class="text-muted">{{ $log->patient_mobile_no }}</small></td>
                            <td data-label="Test Items">{{ $log->test_items }}</td>
                            <td data-label="Total" class="text-end">{{ number_format($log->total_amount, 2) }}</td>
                            <td data-label="Paid" class="text-end">{{ number_format($log->paid_amount, 2) }}</td>
                            <td data-label="Due" class="text-end text-danger fw-semibold">{{ number_format($log->due_amount, 2) }}</td>
                            <td data-label="Printed By">{{ $log->printed_by_name }}<br><small class="text-muted">{{ $log->ip_address }}</small></td>
                            @php
                                $finalPayment = $log->final_payment ?? ['label' => '-', 'badge' => 'bg-light text-muted', 'lines' => []];
                            @endphp
                            <td data-label="Final Payment Status">
                                <span class="badge {{ $finalPayment['badge'] }}">{{ $finalPayment['label'] }}</span>
                                @foreach($finalPayment['lines'] as $line)
                                    <div class="small text-muted mt-1 text-nowrap">{{ $line }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No reports have been printed with a payment due.</td>
                        </tr>
                    @endforelse
                </tbody>

            </table>

        </div>

        {{ $logs->links() }}

    </div>

</div>

@endsection

@section('script')
<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>
@endsection
