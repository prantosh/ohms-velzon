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
        Schema::create('maintenance_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('is_enabled')->default(false);
            $table->text('message')->nullable();

            $table->unsignedBigInteger('enabled_by')->nullable();
            $table->timestamp('enabled_at')->nullable();

            $table->timestamps();
        });

        // Single-row settings table -- seed the one row every read/write targets.
        \Illuminate\Support\Facades\DB::table('maintenance_settings')->insert([
            'is_enabled' => false,
            'message' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_settings');
    }
};
