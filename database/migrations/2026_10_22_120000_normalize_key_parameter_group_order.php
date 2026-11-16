<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('key_parameters')
            ->whereNotNull('group_item_code_sub')
            ->orderBy('group_item_code_sub')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'group_item_code_sub'])
            ->groupBy('group_item_code_sub')
            ->each(function ($parameters) {
                foreach ($parameters->values() as $order => $parameter) {
                    DB::table('key_parameters')
                        ->where('id', $parameter->id)
                        ->update(['sort_order' => $order]);
                }
            });
    }

    public function down(): void
    {
        // Existing display orders cannot be reconstructed after normalization.
    }
};
