<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {

            // Primary Key
            $table->bigIncrements('id');

            // Groups all audit records generated during one HTTP request
            $table->uuid('request_id')->nullable()->comment('Unique Request UUID');

            // Module Information
            $table->string('module_code', 75)->comment('Example: INVOICE_ITEM_DETAIL');
            $table->string('module_name', 100)->comment('Example: Invoice Item Detail');

            // Table & Record Information
            $table->string('table_name', 100);
            $table->string('record_id', 100)->nullable();

            // Action
            $table->string('action', 30)->comment('CREATE, UPDATE, DELETE, PRINT, EXPORT_EXCEL etc.');

            // Audit Data
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->json('changed_data')->nullable();

            // Optional Remarks
            $table->text('remarks')->nullable();

            // User Information (Snapshot)
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 150)->nullable();

            // Request Information
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_url', 500)->nullable();

            // Audit Time
            $table->timestamp('created_at')->useCurrent();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->index('request_id', 'idx_audit_request');
            $table->index('module_code', 'idx_audit_module');
            $table->index('table_name', 'idx_audit_table');
            $table->index('record_id', 'idx_audit_record');
            $table->index('action', 'idx_audit_action');
            $table->index('user_id', 'idx_audit_user');
            $table->index('created_at', 'idx_audit_created');

            /*
            |--------------------------------------------------------------------------
            | Composite Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['module_code', 'action'],
                'idx_audit_module_action'
            );

            $table->index(
                ['table_name', 'record_id'],
                'idx_audit_table_record'
            );

            $table->index(
                ['user_id', 'created_at'],
                'idx_audit_user_date'
            );
            $table->index(
                ['module_code', 'created_at'],
                'idx_audit_module_created'
            );
            /*
            |--------------------------------------------------------------------------
            | Foreign Key
            |--------------------------------------------------------------------------
            | Keeps audit records even if users are removed.
            */

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {

            $table->dropForeign(['user_id']);

            $table->dropIndex('idx_audit_request');
            $table->dropIndex('idx_audit_module');
            $table->dropIndex('idx_audit_table');
            $table->dropIndex('idx_audit_record');
            $table->dropIndex('idx_audit_action');
            $table->dropIndex('idx_audit_user');
            $table->dropIndex('idx_audit_created');

            $table->dropIndex('idx_audit_module_action');
            $table->dropIndex('idx_audit_table_record');
            $table->dropIndex('idx_audit_user_date');
        });

        Schema::dropIfExists('audit_logs');
    }
};
