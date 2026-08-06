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
        Schema::table('users', function (Blueprint $table) {

            $table->enum('role', ['Admin', 'Member', 'Employee'])
                ->default('Employee')
                ->after('authorised');

            $table->json('page_access')
                ->nullable()
                ->after('role');

            $table->enum('status', ['ACTIVE', 'INACTIVE'])
                ->default('ACTIVE')
                ->after('page_access');

            $table->date('date_of_birth')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->enum('gender', ['Male', 'Female', 'Other'])->nullable();

            $table->string('aadhar_no', 20)->nullable();
            $table->string('pan_no', 10)->nullable();
            $table->string('qualification', 50)->nullable();
            $table->string('blood_group', 5)->nullable();

            $table->string('father_name', 100)->nullable();
            $table->string('guardian_name', 100)->nullable();

            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('emergency_mobile_no', 10)->nullable();

            $table->string('bank_ac_no', 20)->nullable();
            $table->string('bank_name', 50)->nullable();
            $table->string('bank_branch', 50)->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
        });

        DB::table('users')->update(['role' => 'Employee']);
        DB::table('users')->where('id', 1)->update(['role' => 'Admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'role',
                'page_access',
                'status',
                'date_of_birth',
                'date_of_joining',
                'gender',
                'aadhar_no',
                'pan_no',
                'qualification',
                'blood_group',
                'father_name',
                'guardian_name',
                'present_address',
                'permanent_address',
                'emergency_mobile_no',
                'bank_ac_no',
                'bank_name',
                'bank_branch',
                'created_by',
                'updated_by'
            ]);
        });
    }
};
