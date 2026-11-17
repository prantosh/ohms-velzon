<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Patients that have an age but no age_recorded_on (everything entered
        // before 15/11/2026): the age was captured when they were first
        // invoiced, so anchor it to their earliest invoice date (their
        // registration date if they have no invoice). Rows already stamped are
        // left alone. Raw SQL so no model event re-stamps the date to today.
        // (invoices.patient_id has no index, so the first dates are grouped once
        // and joined -- a per-patient subquery would scan invoices 11k times.)
        DB::statement("
            UPDATE patients p
            LEFT JOIN (
                SELECT patient_id, MIN(invoice_date) AS first_date
                FROM invoices
                WHERE invoice_date IS NOT NULL
                GROUP BY patient_id
            ) f ON f.patient_id = p.patient_id
            SET p.age_recorded_on = COALESCE(f.first_date, DATE(p.created_at))
            WHERE p.age IS NOT NULL AND p.age_recorded_on IS NULL
        ");
    }

    public function down(): void
    {
        // Not reversible: the original NULLs can't be told apart from stamped rows.
    }
};
