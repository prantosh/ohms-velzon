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
        Schema::create('role_page_access', function (Blueprint $table) {

            $table->id();

            $table->enum('role', ['Admin', 'Member', 'Employee'])->unique();

            $table->json('page_access')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });

        $allPages = array_keys(config('access_pages'));

        DB::table('role_page_access')->insert([

            [
                'role' => 'Admin',
                'page_access' => json_encode($allPages),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'role' => 'Member',
                'page_access' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'role' => 'Employee',
                'page_access' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now()
            ],

        ]);

        if (Schema::hasColumn('users', 'page_access')) {

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('page_access');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_page_access');

        if (!Schema::hasColumn('users', 'page_access')) {

            Schema::table('users', function (Blueprint $table) {
                $table->json('page_access')->nullable()->after('role');
            });
        }
    }
};
