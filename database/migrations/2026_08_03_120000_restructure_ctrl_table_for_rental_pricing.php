<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        Schema::table('ctrl', function (Blueprint $table) {

            if (!Schema::hasColumn('ctrl', 'oxygen_min_advance')) {
                $table->decimal('oxygen_min_advance', 10, 2)->default(500)->after('id');
            }

            // Oxygen rent is additive: (oxygen_rate_per_day x days) +
            // (oxygen_rate_per_unit x units consumed) -- two separate rate
            // components, not one rate multiplied by both.
            if (!Schema::hasColumn('ctrl', 'oxygen_rate_per_day')) {
                $table->decimal('oxygen_rate_per_day', 10, 2)->default(50)->after('oxygen_min_advance');
            }

            if (!Schema::hasColumn('ctrl', 'oxygen_rate_per_unit')) {
                $table->decimal('oxygen_rate_per_unit', 10, 2)->default(50)->after('oxygen_rate_per_day');
            }

            if (!Schema::hasColumn('ctrl', 'concentrator_min_advance')) {
                $table->decimal('concentrator_min_advance', 10, 2)->default(3000)->after('oxygen_rate_per_unit');
            }
        });

        // Carry the real configured values over from the old columns before
        // dropping them, so production keeps its actual rates (not just the
        // column defaults above). The old cylinder_rate_after_seven_days
        // becomes the new oxygen_rate_per_unit (both were 50); the flat
        // day-rate for oxygen (oxygen_rate_per_day) has no prior equivalent
        // -- it keeps the column default of 50, matching the old
        // cylinder_min_rate value which this business rule change replaces.
        if (Schema::hasColumn('ctrl', 'cylinder_rate_after_seven_days')) {
            DB::table('ctrl')->update([
                'oxygen_rate_per_unit' => DB::raw('cylinder_rate_after_seven_days'),
            ]);
        }

        if (Schema::hasColumn('ctrl', 'concentrator_deposit_amt')) {
            DB::table('ctrl')->update([
                'concentrator_min_advance' => DB::raw('concentrator_deposit_amt'),
            ]);
        }

        Schema::table('ctrl', function (Blueprint $table) {

            if (Schema::hasColumn('ctrl', 'cylinder_min_rate')) {
                $table->dropColumn('cylinder_min_rate');
            }

            if (Schema::hasColumn('ctrl', 'cylinder_rate_after_seven_days')) {
                $table->dropColumn('cylinder_rate_after_seven_days');
            }

            if (Schema::hasColumn('ctrl', 'concentrator_deposit_amt')) {
                $table->dropColumn('concentrator_deposit_amt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ctrl', function (Blueprint $table) {

            if (!Schema::hasColumn('ctrl', 'cylinder_min_rate')) {
                $table->decimal('cylinder_min_rate', 10, 2)->default(500);
            }

            if (!Schema::hasColumn('ctrl', 'cylinder_rate_after_seven_days')) {
                $table->decimal('cylinder_rate_after_seven_days', 10, 2)->default(50);
            }

            if (!Schema::hasColumn('ctrl', 'concentrator_deposit_amt')) {
                $table->decimal('concentrator_deposit_amt', 10, 2)->default(3000);
            }
        });

        if (Schema::hasColumn('ctrl', 'oxygen_rate_per_unit')) {
            DB::table('ctrl')->update([
                'cylinder_rate_after_seven_days' => DB::raw('oxygen_rate_per_unit'),
            ]);
        }

        if (Schema::hasColumn('ctrl', 'concentrator_min_advance')) {
            DB::table('ctrl')->update([
                'concentrator_deposit_amt' => DB::raw('concentrator_min_advance'),
            ]);
        }

        Schema::table('ctrl', function (Blueprint $table) {

            if (Schema::hasColumn('ctrl', 'oxygen_min_advance')) {
                $table->dropColumn('oxygen_min_advance');
            }

            if (Schema::hasColumn('ctrl', 'oxygen_rate_per_day')) {
                $table->dropColumn('oxygen_rate_per_day');
            }

            if (Schema::hasColumn('ctrl', 'oxygen_rate_per_unit')) {
                $table->dropColumn('oxygen_rate_per_unit');
            }

            if (Schema::hasColumn('ctrl', 'concentrator_min_advance')) {
                $table->dropColumn('concentrator_min_advance');
            }
        });
    }
};
