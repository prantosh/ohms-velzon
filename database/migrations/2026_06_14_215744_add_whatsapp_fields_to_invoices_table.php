<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->timestamp('whatsapp_sent_at')
                ->nullable()
                ->after('updated_at');

            $table->string('whatsapp_message_id', 100)
                ->nullable()
                ->after('whatsapp_sent_at');

            $table->string('whatsapp_status', 30)
                ->nullable()
                ->after('whatsapp_message_id');

            $table->integer('whatsapp_send_count')
                ->default(0)
                ->after('whatsapp_status');

            $table->text('whatsapp_error')
                ->nullable()
                ->after('whatsapp_send_count');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'whatsapp_sent_at',
                'whatsapp_message_id',
                'whatsapp_status',
                'whatsapp_send_count',
                'whatsapp_error'
            ]);
        });
    }
};
