<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| whatsapp_message_logs.wati_local_message_id: generated -> plain column
|--------------------------------------------------------------------------
| It was a STORED generated column derived from `response`. phpMyAdmin
| exports write a value for it in every INSERT, which MySQL rejects with
| "#3105 The value specified for generated column ... is not allowed", so
| production dumps could not be imported locally.
|
| As a plain column, any dump imports cleanly. MySQL converts a STORED
| generated column in place: existing values and the index are kept. The
| value for new rows is now filled by WhatsappDeliveryStatusService when
| the first webhook event for that message arrives.
*/

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE whatsapp_message_logs MODIFY COLUMN wati_local_message_id VARCHAR(64) NULL');
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE whatsapp_message_logs
            MODIFY COLUMN wati_local_message_id VARCHAR(64)
                GENERATED ALWAYS AS (
                    NULLIF(
                        IF(JSON_VALID(response), JSON_UNQUOTE(JSON_EXTRACT(response, '$.local_message_id')), NULL),
                        'null'
                    )
                ) STORED
        ");
    }
};
