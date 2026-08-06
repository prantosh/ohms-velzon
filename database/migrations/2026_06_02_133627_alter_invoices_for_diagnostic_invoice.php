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
        Schema::table('invoices', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | INVOICE TYPE
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasColumn('invoices', 'invoice_type')) {

                $table->string('invoice_type', 30)
                    ->default('DOCTOR_VISIT')
                    ->after('invoice_no');
            }

            /*
            |--------------------------------------------------------------------------
            | PATIENT DETAILS
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasColumn('invoices', 'patient_age')) {

                $table->string('patient_age', 20)
                    ->nullable()
                    ->after('patient_name');
            }

            if (!Schema::hasColumn('invoices', 'patient_gender')) {

                $table->string('patient_gender', 20)
                    ->nullable()
                    ->after('patient_age');
            }

            /*
            |--------------------------------------------------------------------------
            | TOTAL AMOUNT
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasColumn('invoices', 'total_amount')) {

                $table->decimal('total_amount', 12, 2)
                    ->default(0)
                    ->after('consultation_fee_total');
            }

            /*
            |--------------------------------------------------------------------------
            | PAYMENT MODE
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasColumn('invoices', 'payment_mode')) {

                $table->string('payment_mode', 50)
                    ->nullable()
                    ->after('due_amount');
            }

            /*
            |--------------------------------------------------------------------------
            | CANCELLATION
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasColumn('invoices', 'cancelled')) {

                $table->char('cancelled', 1)
                    ->default('N')
                    ->after('status');
            }

            if (!Schema::hasColumn('invoices', 'cancelled_at')) {

                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('cancelled');
            }

            if (!Schema::hasColumn('invoices', 'cancelled_by')) {

                $table->unsignedBigInteger('cancelled_by')
                    ->nullable()
                    ->after('cancelled_at');
            }

            /*
            |--------------------------------------------------------------------------
            | AUDIT
            |--------------------------------------------------------------------------
            */

            if (!Schema::hasColumn('invoices', 'created_by')) {

                $table->unsignedBigInteger('created_by')
                    ->nullable()
                    ->after('remarks');
            }

            if (!Schema::hasColumn('invoices', 'updated_by')) {

                $table->unsignedBigInteger('updated_by')
                    ->nullable()
                    ->after('created_by');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | APPOINTMENT NULLABLE
        |--------------------------------------------------------------------------
        | Diagnostic invoices do not have appointments
        |--------------------------------------------------------------------------
        */

        try {

            DB::statement(
                "ALTER TABLE invoices MODIFY appointment_id BIGINT NULL"
            );

        } catch (\Exception $e) {

            // ignore if already nullable
        }

        try {

            DB::statement(
                "ALTER TABLE invoices MODIFY appointment_no VARCHAR(50) NULL"
            );

        } catch (\Exception $e) {

            // ignore if already nullable
        }

        try {

            DB::statement(
                "ALTER TABLE invoices MODIFY doctor_id BIGINT NULL"
            );

        } catch (\Exception $e) {

            // ignore if already nullable
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            if (Schema::hasColumn('invoices', 'cancelled_by')) {

                $table->dropColumn('cancelled_by');
            }

            if (Schema::hasColumn('invoices', 'cancelled_at')) {

                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('invoices', 'cancelled')) {

                $table->dropColumn('cancelled');
            }

            if (Schema::hasColumn('invoices', 'updated_by')) {

                $table->dropColumn('updated_by');
            }

            if (Schema::hasColumn('invoices', 'created_by')) {

                $table->dropColumn('created_by');
            }

            /*
            |--------------------------------------------------------------------------
            | DO NOT DROP EXISTING COLUMNS
            |--------------------------------------------------------------------------
            | Because they may already be used by
            | Doctor Visit Invoice module.
            |--------------------------------------------------------------------------
            */
        });
    }
};
