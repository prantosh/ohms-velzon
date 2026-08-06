<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('patient_cards', function (Blueprint $table) {

            $table->string('mobile_no')
                ->nullable()
                ->after('card_no');

            $table->text('remarks')
                ->nullable()
                ->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('patient_cards', function (Blueprint $table) {
            $table->dropColumn(['mobile_no', 'remarks']);
        });
    }
};
