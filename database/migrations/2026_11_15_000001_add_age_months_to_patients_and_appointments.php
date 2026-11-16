<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'age_months')) {
                // 0-11, only meaningful alongside `age` (whole years).
                $table->unsignedTinyInteger('age_months')->nullable()->after('age');
            }
            if (!Schema::hasColumn('patients', 'age_recorded_on')) {
                // Date `age`/`age_months` were last entered; lets the current
                // age be rolled forward instead of going stale.
                $table->date('age_recorded_on')->nullable()->after('age_months');
            }
        });

        Schema::table('doctor_appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('doctor_appointments', 'patient_age_months')) {
                $table->unsignedTinyInteger('patient_age_months')->nullable()->after('patient_age');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            foreach (['age_months', 'age_recorded_on'] as $col) {
                if (Schema::hasColumn('patients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('doctor_appointments', function (Blueprint $table) {
            if (Schema::hasColumn('doctor_appointments', 'patient_age_months')) {
                $table->dropColumn('patient_age_months');
            }
        });
    }
};
