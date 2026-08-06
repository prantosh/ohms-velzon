<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * schedule_code (auto-generated 'SCH000123' label) has no functional
 * implication anywhere in the system -- confirmed by searching the whole
 * codebase: it's written once at creation and displayed in one list
 * column, never used for lookup, filtering, uniqueness, or cross-reference.
 * 80 of 94 live doctor_schedules rows already have it null/empty (no
 * unique index enforced it either), confirming it was never reliably
 * maintained. Removed at the user's request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->dropColumn('schedule_code');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->string('schedule_code', 50)->nullable()->after('doctor_id');
        });
    }
};
