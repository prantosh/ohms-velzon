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
        if (Schema::hasTable('ctrl')) {
            return;
        }

        Schema::create('ctrl', function (Blueprint $table) {

            $table->id();

            $table->integer('cylinder_min_rate');

            $table->integer('cylinder_rate_after_seven_days');

            $table->integer('concentrator_deposit_amt');

            $table->integer('concentrator_rate_per_day');

            $table->string('fin_yr', 2);
        });

        DB::table('ctrl')->insert([
            'id' => 1,
            'cylinder_min_rate' => 500,
            'cylinder_rate_after_seven_days' => 50,
            'concentrator_deposit_amt' => 3000,
            'concentrator_rate_per_day' => 200,
            'fin_yr' => now()->format('y'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctrl');
    }
};
