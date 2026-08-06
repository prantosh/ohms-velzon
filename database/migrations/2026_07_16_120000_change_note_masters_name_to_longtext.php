<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('note_masters', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        // LONGTEXT to allow full-length note content (up to 1000+
        // characters). MySQL cannot place a DB-level unique index across
        // a value this long (InnoDB's max index key is 3072 bytes, i.e.
        // ~768 characters in utf8mb4) — uniqueness for this column is
        // therefore enforced at the application layer only (see
        // NoteMasterController's 'unique:note_masters,name' validation),
        // matching this codebase's existing convention for text-heavy
        // columns without a DB-level constraint.
        DB::statement('ALTER TABLE note_masters MODIFY name LONGTEXT NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE note_masters MODIFY name VARCHAR(150) NOT NULL');

        Schema::table('note_masters', function (Blueprint $table) {
            $table->unique('name');
        });
    }
};
