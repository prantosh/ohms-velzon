<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (!Schema::hasColumn('invoice_details', 'income_category_id')) {
                $table->unsignedBigInteger('income_category_id')->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'cheque_number')) {
                $table->string('cheque_number', 50)->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'bank_name')) {
                $table->string('bank_name', 150)->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'cheque_date')) {
                $table->date('cheque_date')->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'reference_number')) {
                $table->string('reference_number', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            $columns = [
                'income_category_id',
                'cheque_number',
                'bank_name',
                'cheque_date',
                'reference_number',
            ];

            foreach ($columns as $column) {

                if (Schema::hasColumn('invoice_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
