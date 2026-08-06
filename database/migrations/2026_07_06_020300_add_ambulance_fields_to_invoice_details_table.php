<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            if (!Schema::hasColumn('invoice_details', 'ambulance_destination_id')) {
                $table->unsignedBigInteger('ambulance_destination_id')->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'booking_type')) {
                $table->enum('booking_type', ['AC', 'NONAC'])->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'from_destination')) {
                $table->string('from_destination', 255)->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'address')) {
                $table->text('address')->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'pickup_time')) {
                $table->dateTime('pickup_time')->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'release_time')) {
                $table->dateTime('release_time')->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'odometer_pickup_km')) {
                $table->decimal('odometer_pickup_km', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'odometer_release_km')) {
                $table->decimal('odometer_release_km', 10, 2)->nullable();
            }

            if (!Schema::hasColumn('invoice_details', 'waiting_charge')) {
                $table->decimal('waiting_charge', 10, 2)->nullable()->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {

            $columns = [
                'ambulance_destination_id',
                'booking_type',
                'from_destination',
                'address',
                'pickup_time',
                'release_time',
                'odometer_pickup_km',
                'odometer_release_km',
                'waiting_charge',
            ];

            foreach ($columns as $column) {

                if (Schema::hasColumn('invoice_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
