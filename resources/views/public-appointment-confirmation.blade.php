@extends('layouts.master-without-nav')

@section('title')
Appointment Confirmed
@endsection

@section('content')

<div style="max-width:600px; margin:60px auto; padding:0 15px;">

    <div class="card">

        <div class="card-body p-4 text-center">

            <div class="avatar-lg mx-auto mb-3">
                <div class="avatar-title bg-success-subtle text-success rounded-circle fs-1">
                    <i class="ri-check-line"></i>
                </div>
            </div>

            <h4 class="fw-bold mb-1">Appointment Confirmed</h4>
            <p class="text-muted mb-4">A confirmation has also been sent to you via WhatsApp.</p>

            <table class="table table-bordered text-start">
                <tr>
                    <th style="width:40%;">Appointment No</th>
                    <td>{{ $appointment->appointment_no }}</td>
                </tr>
                <tr>
                    <th>Patient Name</th>
                    <td>{{ $appointment->patient_name }}</td>
                </tr>
                <tr>
                    <th>Doctor</th>
                    <td>{{ $appointment->doctor_name }}</td>
                </tr>
                <tr>
                    <th>Date</th>
                    <td>{{ \Carbon\Carbon::parse($appointment->appointment_date)->format('d-m-Y') }}</td>
                </tr>
                <tr>
                    <th>Time</th>
                    <td>{{ \Carbon\Carbon::parse($appointment->appointment_time)->format('h:i A') }}</td>
                </tr>
                <tr>
                    <th>Token No</th>
                    <td>{{ $appointment->token_no }}</td>
                </tr>
            </table>

            <div class="d-flex gap-2 justify-content-center mt-2">

                <a href="{{ route('public-appointment.index') }}" class="btn btn-primary">
                    Book Another Appointment
                </a>

                <button type="button" class="btn btn-outline-secondary" id="closeConfirmationBtn">
                    Close
                </button>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script>
// This page is opened as its own tab via a plain link from the main site
// (not window.open()'d by script), so browsers will normally block
// window.close() here -- it's attempted anyway in case it does work, and
// the fallback sends the patient back to the main website after a short
// delay (which never fires if the close above actually succeeded).
document.getElementById('closeConfirmationBtn').addEventListener('click', function () {

    window.close();

    setTimeout(function () {
        window.location.href = 'https://abssrk.online';
    }, 150);
});
</script>

@endsection
