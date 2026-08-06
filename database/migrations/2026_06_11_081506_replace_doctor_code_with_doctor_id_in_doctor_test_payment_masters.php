<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('doctor_test_payment_masters', function (Blueprint $table) {

            $table->unsignedBigInteger('doctor_id')
                  ->nullable()
                  ->after('id');

        });

        DB::statement("
            UPDATE doctor_test_payment_masters dtpm
            INNER JOIN doctors d
                ON dtpm.doctor_code = d.doctor_code
            SET dtpm.doctor_id = d.id
        ");

        Schema::table('doctor_test_payment_masters', function (Blueprint $table) {

            $table->dropUnique('uk_doctor_test_payment');

            $table->dropIndex(
                'doctor_test_payment_masters_doctor_code_index'
            );

            $table->dropColumn('doctor_code');

            $table->unique(
                ['doctor_id', 'item_code_sub'],
                'uk_doctor_test_payment'
            );

            $table->foreign('doctor_id')
                ->references('id')
                ->on('doctors')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down()
    {
        //
    }
};
