<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            // Invoice type
            if (!Schema::hasColumn('invoices', 'invoice_type')) {
                $table->enum(
                    'invoice_type',
                    [
                        'DOCTOR_VISIT',
                        'DIAGNOSTIC',
                        'PHARMACY',
                        'PROCEDURE'
                    ]
                )->default('DOCTOR_VISIT')
                    ->after('invoice_no');
            }

            // Reference fields
            if (!Schema::hasColumn('invoices', 'reference_no')) {
                $table->string('reference_no', 50)
                    ->nullable()
                    ->after('invoice_type');
            }

            // Referral information
            if (!Schema::hasColumn('invoices', 'referred_by')) {
                $table->unsignedBigInteger('referred_by')
                    ->nullable()
                    ->after('doctor_id');
            }

            if (!Schema::hasColumn('invoices', 'referred_name')) {
                $table->string('referred_name', 191)
                    ->nullable()
                    ->after('referred_by');
            }

            // Patient information
            if (!Schema::hasColumn('invoices', 'patient_gender')) {
                $table->string('patient_gender', 20)
                    ->nullable()
                    ->after('patient_name');
            }

            if (!Schema::hasColumn('invoices', 'patient_age')) {
                $table->string('patient_age', 20)
                    ->nullable()
                    ->after('patient_gender');
            }

            if (!Schema::hasColumn('invoices', 'patient_mobile_no')) {
                $table->string('patient_mobile_no', 15)
                    ->nullable()
                    ->after('patient_age');
            }

            // Financial fields
            if (!Schema::hasColumn('invoices', 'gross_amount')) {
                $table->decimal('gross_amount', 12, 2)
                    ->default(0)
                    ->after('invoice_date');
            }

            if (!Schema::hasColumn('invoices', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)
                    ->default(0)
                    ->after('gross_amount');
            }

            if (!Schema::hasColumn('invoices', 'refund_amount')) {
                $table->decimal('refund_amount', 12, 2)
                    ->default(0)
                    ->after('due_amount');
            }

            // Cancellation fields
            if (!Schema::hasColumn('invoices', 'cancelled')) {
                $table->char('cancelled', 1)
                    ->default('N')
                    ->after('status');
            }

            if (!Schema::hasColumn('invoices', 'cancelled_by')) {
                $table->unsignedBigInteger('cancelled_by')
                    ->nullable()
                    ->after('cancelled');
            }

            if (!Schema::hasColumn('invoices', 'cancelled_at')) {
                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('cancelled_by');
            }

            // Audit fields
            if (!Schema::hasColumn('invoices', 'created_by')) {
                $table->unsignedBigInteger('created_by')
                    ->nullable()
                    ->after('cancelled_at');
            }

            if (!Schema::hasColumn('invoices', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')
                    ->nullable()
                    ->after('created_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'reference_no',
                'referred_by',
                'referred_name',
                'patient_gender',
                'patient_age',
                'patient_mobile_no',
                'gross_amount',
                'discount_amount',
                'refund_amount',
                'cancelled',
                'cancelled_by',
                'cancelled_at',
                'created_by',
                'updated_by'
            ]);
        });
    }
};
