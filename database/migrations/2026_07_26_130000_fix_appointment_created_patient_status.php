<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AppointmentBookingService::book() was creating new patients with
 * status = 1 instead of the 'A' (Active) convention used everywhere else
 * (Diagnostic Invoice, Equipment Rental, Ambulance Rental) -- since
 * DiagnosticInvoiceController::searchPatient() filters on status = 'A',
 * any patient whose first record came from a doctor appointment booking
 * was invisible there. This corrects the existing rows left behind by
 * that bug; the code itself is fixed separately in AppointmentBookingService.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('patients')->where('status', '1')->update(['status' => 'A']);
    }

    public function down(): void
    {
        // Not reversible -- there is no way to distinguish which rows were
        // corrected by this migration from ones that were always 'A'.
    }
};
