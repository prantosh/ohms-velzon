<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('doctor_test_payment_masters', function (Blueprint $table) {

            // Drop old unique key
            $table->dropUnique('uk_doctor_test_payment');

            // Create new unique key
            $table->unique(
                ['doctor_code', 'item_code_sub'],
                'uk_doctor_test_payment'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_test_payment_masters', function (Blueprint $table) {

            // Drop new unique key
            $table->dropUnique('uk_doctor_test_payment');

            // Restore old unique key
            $table->unique(
                ['doctor_code', 'item_code_sub', 'effective_from'],
                'uk_doctor_test_payment'
            );
        });
    }
};
