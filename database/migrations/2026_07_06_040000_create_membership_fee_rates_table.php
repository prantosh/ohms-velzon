<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_fee_rates', function (Blueprint $table) {

            $table->engine = 'InnoDB';

            $table->id();

            $table->date('effective_from')->unique();

            $table->decimal('monthly_rate', 10, 2);

            $table->text('remarks')->nullable();

            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');

            $table->unsignedBigInteger('created_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });

        DB::table('membership_fee_rates')->insert([

            [
                'effective_from' => '2000-01-01',
                'monthly_rate' => 10,
                'remarks' => 'Rate applicable up to March 2022.',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'effective_from' => '2022-04-01',
                'monthly_rate' => 20,
                'remarks' => 'Rate applicable from April 2022 onward.',
                'status' => 'ACTIVE',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_fee_rates');
    }
};
