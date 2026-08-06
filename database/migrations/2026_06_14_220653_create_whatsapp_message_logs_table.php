<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {

            $table->id();

            $table->string('invoice_no', 50);

            $table->string('mobile_no', 20);

            $table->string('message_type', 30);

            $table->string('message_id', 100)
                ->nullable();

            $table->string('status', 30)
                ->nullable();

            $table->longText('response')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'whatsapp_message_logs'
        );
    }
};
