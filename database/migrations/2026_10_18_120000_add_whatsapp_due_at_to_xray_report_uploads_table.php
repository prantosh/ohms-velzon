<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X-Ray's WhatsApp send is deliberately delayed 2 hours from the latest
 * upload/replace (see XrayReportUploadController::upload()), instead of
 * firing immediately the way every other report type's does -- a fresh
 * upload sets this to now()+2h; a replace within that window just
 * overwrites it, which is what gives the delay its "resets on change"
 * behaviour for free. A scheduled command (xray:send-due-whatsapp) picks up
 * whatever's due; null means nothing is currently scheduled (either never
 * uploaded, or already sent/handed off to the due-amount hold).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xray_report_uploads', function (Blueprint $table) {
            $table->timestamp('whatsapp_due_at')->nullable()->after('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('xray_report_uploads', function (Blueprint $table) {
            $table->dropColumn('whatsapp_due_at');
        });
    }
};
