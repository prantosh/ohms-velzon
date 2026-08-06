<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasTable('diagnostic_test_additional_info_tabs')) {
            return;
        }

        Schema::create('diagnostic_test_additional_info_tabs', function (Blueprint $table) {

            $table->id();
            $table->string('label');
            $table->string('route_name')->unique();

            // Null for tabs added later via the dashboard's own "Manage
            // Tabs" UI -- those are visible to everyone who can open the
            // dashboard. Set only for the 9 original tabs seeded below, so
            // their existing per-role visibility (via role_page_access)
            // keeps working exactly as before.
            $table->string('page_access_key')->nullable();

            $table->integer('sort_order')->default(0);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });

        $seed = [
            ['label' => 'Test Extra Field Master', 'route_name' => 'test-extra-field-type.index', 'page_access_key' => 'test-extra-field-type', 'sort_order' => 1],
            ['label' => 'Instrument Master', 'route_name' => 'instrument-master.index', 'page_access_key' => 'instrument-master', 'sort_order' => 2],
            ['label' => 'Kit Master', 'route_name' => 'kit-master.index', 'page_access_key' => 'kit-master', 'sort_order' => 3],
            ['label' => 'Note Master', 'route_name' => 'note-master.index', 'page_access_key' => 'note-master', 'sort_order' => 4],
            ['label' => 'Microscopy Master', 'route_name' => 'microscopy-master.index', 'page_access_key' => 'microscopy-master', 'sort_order' => 5],
            ['label' => 'Impression Master', 'route_name' => 'impression-master.index', 'page_access_key' => 'impression-master', 'sort_order' => 6],
            ['label' => 'Detail Range Master', 'route_name' => 'detail-range-master.index', 'page_access_key' => 'detail-range-master', 'sort_order' => 7],
            ['label' => 'Sample Master', 'route_name' => 'sample-master.index', 'page_access_key' => 'sample-master', 'sort_order' => 8],
            ['label' => 'UOM Master', 'route_name' => 'uom-master.index', 'page_access_key' => 'uom-master', 'sort_order' => 9],
        ];

        foreach ($seed as $row) {

            $row['created_at'] = now();
            $row['updated_at'] = now();

            DB::table('diagnostic_test_additional_info_tabs')->insert($row);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_test_additional_info_tabs');
    }
};
