<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('key_parameters', function (Blueprint $table) {
            $table->string('group_item_code_sub', 20)->nullable()->after('group_label');
            $table->index('group_item_code_sub');
        });

        $parameters = DB::table('key_parameters')
            ->get(['id', 'item_code_sub', 'trigger_item_code_subs']);

        foreach ($parameters as $parameter) {
            $packageParents = DB::table('test_package_components as component')
                ->join('invoice_item_details as child_item', 'child_item.id', '=', 'component.component_invoice_item_detail_id')
                ->join('invoice_item_details as parent_item', 'parent_item.id', '=', 'component.package_invoice_item_detail_id')
                ->where('child_item.item_code_sub', $parameter->item_code_sub)
                ->where('parent_item.rate', '<>', 0)
                ->where('component.status', 'ACTIVE')
                ->distinct()
                ->get(['parent_item.item_code_sub']);

            if ($packageParents->count() === 1) {
                DB::table('key_parameters')
                    ->where('id', $parameter->id)
                    ->update(['group_item_code_sub' => $packageParents->first()->item_code_sub]);

                continue;
            }

            $triggers = json_decode($parameter->trigger_item_code_subs ?? '[]', true) ?: [];
            $parentCodes = array_values(array_filter($triggers, function ($code) use ($parameter) {
                return $code !== $parameter->item_code_sub;
            }));

            foreach ($parentCodes as $parentCode) {
                $parent = DB::table('invoice_item_details')
                    ->where('item_code_sub', $parentCode)
                    ->where('rate', '<>', 0)
                    ->first(['item_code_sub', 'item_description_sub']);

                if (!$parent) {
                    continue;
                }

                DB::table('key_parameters')
                    ->where('id', $parameter->id)
                    ->update([
                        'group_item_code_sub' => $parent->item_code_sub,
                    ]);

                break;
            }
        }
    }

    public function down(): void
    {
        Schema::table('key_parameters', function (Blueprint $table) {
            $table->dropIndex(['group_item_code_sub']);
            $table->dropColumn('group_item_code_sub');
        });
    }
};
