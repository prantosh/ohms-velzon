<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| WhatsApp delivery tracking
|--------------------------------------------------------------------------
| Until now a log row's status = 'SENT' only ever meant "WATI accepted the
| API call" -- nothing recorded whether WhatsApp actually delivered it to
| the patient's phone or whether they opened it. WATI reports that later,
| asynchronously, through its webhook (see WatiWebhookController).
|
| To match a webhook event back to its log row we need the
| `local_message_id` WATI returns in the send response. Every send site
| already stores that full response JSON in `response`, so instead of
| touching ~20 insert sites we derive it with a STORED generated column:
| it is filled for every existing row by this migration and for every
| future insert automatically, and it can be indexed for the webhook
| lookup. JSON_VALID guards the rows whose `response` is a plain-text
| reason (e.g. SKIPPED "Automatic sending is turned off ...").
|
| `status` is deliberately left untouched -- it keeps meaning "accepted by
| WATI" so every existing check (already-sent, deletion guard, report
| reconciliation) behaves exactly as before. Delivery is tracked
| alongside it in `delivery_status`.
*/

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE whatsapp_message_logs
            ADD COLUMN wati_local_message_id VARCHAR(64)
                GENERATED ALWAYS AS (
                    NULLIF(
                        IF(JSON_VALID(response), JSON_UNQUOTE(JSON_EXTRACT(response, '$.local_message_id')), NULL),
                        'null'
                    )
                ) STORED
                AFTER message_id
        ");

        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->index('wati_local_message_id', 'wml_wati_local_message_id_idx');
            $table->index('message_id', 'wml_message_id_idx');

            // NULL = no delivery update received (yet). Otherwise one of
            // SENT (reached WhatsApp's servers), DELIVERED (on the
            // patient's phone), READ (opened), FAILED (WhatsApp gave up).
            $table->string('delivery_status', 20)->nullable()->after('status');
            $table->dateTime('delivered_at')->nullable()->after('delivery_status');
            $table->dateTime('read_at')->nullable()->after('delivered_at');
            $table->dateTime('delivery_failed_at')->nullable()->after('read_at');
            $table->string('delivery_failure_reason', 500)->nullable()->after('delivery_failed_at');
            $table->dateTime('delivery_updated_at')->nullable()->after('delivery_failure_reason');
        });

        // Raw webhook trail -- lets an admin (or us) see exactly what WATI
        // reported, including events that could not be matched to a log row.
        Schema::create('wati_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 60)->nullable();
            $table->string('delivery_status', 20)->nullable();
            $table->string('local_message_id', 64)->nullable()->index();
            $table->string('whatsapp_message_id', 191)->nullable();
            $table->unsignedBigInteger('whatsapp_message_log_id')->nullable()->index();
            $table->longText('payload')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wati_webhook_events');

        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->dropIndex('wml_wati_local_message_id_idx');
            $table->dropIndex('wml_message_id_idx');
            $table->dropColumn([
                'wati_local_message_id',
                'delivery_status',
                'delivered_at',
                'read_at',
                'delivery_failed_at',
                'delivery_failure_reason',
                'delivery_updated_at',
            ]);
        });
    }
};
