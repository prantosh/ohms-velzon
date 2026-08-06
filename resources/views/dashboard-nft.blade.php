@extends('layouts.master')

@section('title')
    Dashboard
@endsection

@section('content')

<div class="container-fluid">

    <!-- PAGE TITLE -->

    <div class="row mb-4">

        <div class="col-12">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <h4 class="mb-1">
                        Doctor Clinic & Laboratory Dashboard
                    </h4>

                    <p class="text-muted mb-0">
                        Welcome to Clinic Management System
                    </p>

                </div>

                <div>

                    <button class="btn btn-primary">
                        <i class="ri-calendar-check-line me-1"></i>
                        New Appointment
                    </button>

                </div>

            </div>

        </div>

    </div>

    <!-- SUMMARY CARDS -->

    <div class="row">

        <div class="col-xl-3 col-md-6">

            <div class="card card-animate">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="fw-medium text-muted mb-0">
                                Total Patients
                            </p>

                            <h2 class="mt-4 ff-secondary fw-semibold">
                                1,250
                            </h2>

                        </div>

                        <div>

                            <div class="avatar-sm flex-shrink-0">

                                <span class="avatar-title bg-primary-subtle text-primary rounded-circle fs-3">

                                    <i class="ri-user-3-line"></i>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="col-xl-3 col-md-6">

            <div class="card card-animate">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="fw-medium text-muted mb-0">
                                Today's Appointments
                            </p>

                            <h2 class="mt-4 ff-secondary fw-semibold">
                                86
                            </h2>

                        </div>

                        <div>

                            <div class="avatar-sm flex-shrink-0">

                                <span class="avatar-title bg-success-subtle text-success rounded-circle fs-3">

                                    <i class="ri-calendar-check-line"></i>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="col-xl-3 col-md-6">

            <div class="card card-animate">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="fw-medium text-muted mb-0">
                                Laboratory Tests
                            </p>

                            <h2 class="mt-4 ff-secondary fw-semibold">
                                320
                            </h2>

                        </div>

                        <div>

                            <div class="avatar-sm flex-shrink-0">

                                <span class="avatar-title bg-warning-subtle text-warning rounded-circle fs-3">

                                    <i class="ri-flask-line"></i>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="col-xl-3 col-md-6">

            <div class="card card-animate">

                <div class="card-body">

                    <div class="d-flex justify-content-between">

                        <div>

                            <p class="fw-medium text-muted mb-0">
                                Total Revenue
                            </p>

                            <h2 class="mt-4 ff-secondary fw-semibold">
                                ₹ 2,45,000
                            </h2>

                        </div>

                        <div>

                            <div class="avatar-sm flex-shrink-0">

                                <span class="avatar-title bg-danger-subtle text-danger rounded-circle fs-3">

                                    <i class="ri-money-rupee-circle-line"></i>

                                </span>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- CHARTS -->

    <div class="row">

        <div class="col-xl-8">

            <div class="card">

                <div class="card-header">

                    <h4 class="card-title mb-0">
                        Monthly Patient Statistics
                    </h4>

                </div>

                <div class="card-body">

                    <div id="patient_chart"
                         style="height: 350px;">
                    </div>

                </div>

            </div>

        </div>

        <div class="col-xl-4">

            <div class="card">

                <div class="card-header">

                    <h4 class="card-title mb-0">
                        Laboratory Status
                    </h4>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-bordered align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>Test</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <tr>

                                    <td>Blood Test</td>

                                    <td>
                                        <span class="badge bg-success">
                                            Completed
                                        </span>
                                    </td>

                                </tr>

                                <tr>

                                    <td>Urine Test</td>

                                    <td>
                                        <span class="badge bg-warning">
                                            Pending
                                        </span>
                                    </td>

                                </tr>

                                <tr>

                                    <td>X-Ray</td>

                                    <td>
                                        <span class="badge bg-primary">
                                            Processing
                                        </span>
                                    </td>

                                </tr>

                                <tr>

                                    <td>ECG</td>

                                    <td>
                                        <span class="badge bg-success">
                                            Completed
                                        </span>
                                    </td>

                                </tr>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- RECENT APPOINTMENTS -->

    <div class="row">

        <div class="col-12">

            <div class="card">

                <div class="card-header">

                    <div class="d-flex justify-content-between align-items-center">

                        <h4 class="card-title mb-0">
                            Recent Appointments
                        </h4>

                        <button class="btn btn-sm btn-primary">
                            View All
                        </button>

                    </div>

                </div>

                <div class="card-body">

                    <div class="table-responsive">

                        <table class="table table-hover align-middle">

                            <thead class="table-light">

                                <tr>

                                    <th>Patient Name</th>
                                    <th>Doctor</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <tr>

                                    <td>Rahul Sharma</td>
                                    <td>Dr. Amit Roy</td>
                                    <td>22/05/2026</td>
                                    <td>10:30 AM</td>

                                    <td>
                                        <span class="badge bg-success">
                                            Confirmed
                                        </span>
                                    </td>

                                </tr>

                                <tr>

                                    <td>Priya Das</td>
                                    <td>Dr. S. Ghosh</td>
                                    <td>22/05/2026</td>
                                    <td>11:00 AM</td>

                                    <td>
                                        <span class="badge bg-warning">
                                            Pending
                                        </span>
                                    </td>

                                </tr>

                                <tr>

                                    <td>Ankit Paul</td>
                                    <td>Dr. Roy</td>
                                    <td>22/05/2026</td>
                                    <td>12:15 PM</td>

                                    <td>
                                        <span class="badge bg-danger">
                                            Cancelled
                                        </span>
                                    </td>

                                </tr>

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

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<script>

var options = {

    series: [{
        name: 'Patients',
        data: [120, 150, 180, 170, 210, 250, 300]
    }],

    chart: {
        height: 350,
        type: 'area',
        toolbar: {
            show: false
        }
    },

    dataLabels: {
        enabled: false
    },

    stroke: {
        curve: 'smooth'
    },

    xaxis: {
        categories: [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul'
        ]
    }
};

var chart =
    new ApexCharts(
        document.querySelector("#patient_chart"),
        options
    );

chart.render();

</script>

@endsection
