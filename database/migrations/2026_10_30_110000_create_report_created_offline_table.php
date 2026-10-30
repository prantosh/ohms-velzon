<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // One row per billed test whose report was written offline (system or
        // internet trouble) -- USG / Cardiology. The line counts as reported
        // and confirmed, but there is nothing in the system to print. Other
        // tests on the same invoice (e.g. X-Ray) are unaffected.
        // invoice_details is MyISAM, so no foreign key on invoice_detail_id.
        Schema::create('report_created_offline', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->string('invoice_no', 30)->index();

            $table->unsignedBigInteger('invoice_detail_id')->unique();

            $table->string('report_type', 20);

            $table->unsignedBigInteger('confirmed_by')->nullable();

            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_created_offline');
    }
};
