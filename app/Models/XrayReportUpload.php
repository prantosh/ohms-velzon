<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An externally generated X-Ray report PDF attached to a diagnostic invoice
 * (one per invoice; re-uploading replaces it). The file itself lives in
 * public/invoices as <invoice-no with / replaced by ->-xray-report.pdf.
 */
class XrayReportUpload extends Model
{
    public const ITEM_CODE = 'XRY001';

    public const FILE_SUFFIX = '-xray-report.pdf';

    protected $fillable = [
        'invoice_no',
        'file_name',
        'original_name',
        'size_bytes',
        'uploaded_by',
    ];

    public static function fileNameFor(string $invoiceNo): string
    {
        return str_replace(['/', '\\'], '-', $invoiceNo) . self::FILE_SUFFIX;
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function path(): string
    {
        return public_path('invoices/' . $this->file_name);
    }

    public function fileExists(): bool
    {
        return is_file($this->path());
    }

    /**
     * Authenticated in-app link to the uploaded PDF for this invoice, or
     * null when none was uploaded (or the file has since gone missing).
     * Shared by the Test Report Dashboard and the Delivery Log.
     */
    public static function viewUrlFor($invoice): ?string
    {
        $upload = static::where('invoice_no', $invoice->invoice_no)->first();

        return ($upload && $upload->fileExists())
            ? route('xray-report-upload.view', $invoice->id)
            : null;
    }
}
