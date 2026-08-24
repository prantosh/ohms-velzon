<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log of every "mark as delivered" action on a diagnostic
     * report -- distinct from invoices.report_delivered_at/report_delivered_by,
     * which only track the CURRENT delivery state (and get cleared on
     * unmark). One invoice can be delivered more than once over time (e.g.
     * unmarked and re-delivered to a different family member), so each
     * delivery is its own row here, snapshotting the payment position at
     * that exact moment for the delivery report.
     */
    public function up(): void
    {
        Schema::create('test_report_deliveries', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('invoice_id');
            $table->string('invoice_no', 30);
            $table->string('patient_name', 150)->nullable();
            $table->string('patient_mobile_no', 20)->nullable();

            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);

            $table->unsignedBigInteger('delivered_by');
            $table->timestamp('delivered_at');

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('invoice_no');
            $table->index('delivered_by');
            $table->index('delivered_at');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('delivered_by')
                ->references('id')
                ->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_report_deliveries');
    }
};
