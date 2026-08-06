<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Seed uom_masters from every distinct UOM value already in use
     * (invoice_item_details.uom and test_analytes.uom), so switching those
     * fields to a dropdown doesn't blank out anything already saved.
     */
    public function up(): void
    {
        $fromDetails = DB::table('invoice_item_details')
            ->whereNotNull('uom')
            ->where('uom', '!=', '')
            ->pluck('uom');

        $fromAnalytes = DB::table('test_analytes')
            ->whereNotNull('uom')
            ->where('uom', '!=', '')
            ->pluck('uom');

        $distinctValues = $fromDetails->merge($fromAnalytes)
            ->map(fn ($u) => trim($u))
            ->filter()
            ->unique()
            ->values();

        $existing = DB::table('uom_masters')->pluck('name')->all();

        $now = now();

        foreach ($distinctValues as $value) {

            if (in_array($value, $existing, true)) {
                continue;
            }

            DB::table('uom_masters')->insert([
                'name' => $value,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally no-op: removing seeded rows on rollback could
        // delete a uom_masters row a user has since started relying on.
    }
};
