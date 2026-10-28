@extends('layouts.master')

@section('title')
    Report Print With Due Log
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
                <input type="date" name="from" value="{{ request('from') }}" class="form-control">
            </div>

            <div class="col-md-2">
                <label class="form-label fw-semibold">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control">
            </div>

            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('report-print-due-log.index') }}" class="btn btn-light">Reset</a>
            </div>

        </form>

        <div class="table-responsive">

            <table class="table table-bordered align-middle">

                <thead class="table-light">
                    <tr>
                        <th>Printed At</th>
                        <th>Invoice No</th>
                        <th>Patient</th>
                        <th>Test Items</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Due</th>
                        <th>Printed By</th>
                        <th>Type</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ optional($log->printed_at)->format('d-m-Y H:i') }}</td>
                            <td class="fw-semibold">{{ $log->invoice_no }}</td>
                            <td>{{ $log->patient_name }}<br><small class="text-muted">{{ $log->patient_mobile_no }}</small></td>
                            <td>{{ $log->test_items }}</td>
                            <td class="text-end">{{ number_format($log->total_amount, 2) }}</td>
                            <td class="text-end">{{ number_format($log->paid_amount, 2) }}</td>
                            <td class="text-end text-danger fw-semibold">{{ number_format($log->due_amount, 2) }}</td>
                            <td>{{ $log->printed_by_name }}<br><small class="text-muted">{{ $log->ip_address }}</small></td>
                            <td>{{ $log->print_type }}</td>
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
