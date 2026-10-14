<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Only Senior Citizen" discount % of a test: applies ONLY to a patient
     * aged 60 or over (not to an In-House patient, not to a Member), beside
     * the existing standard / member / other-lab / member + other-lab rates.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('invoice_item_details', 'discount_senior_citizen')) {

            Schema::table('invoice_item_details', function (Blueprint $table) {
                $table->decimal('discount_senior_citizen', 5, 2)->nullable()->default(0)->after('discount_member_other_lab');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoice_item_details', 'discount_senior_citizen')) {

            Schema::table('invoice_item_details', function (Blueprint $table) {
                $table->dropColumn('discount_senior_citizen');
            });
        }
    }
};
