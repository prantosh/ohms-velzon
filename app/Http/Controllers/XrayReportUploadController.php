<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\WhatsappAutoSendSetting;
use App\Models\XrayReportUpload;
use App\Services\AuditService;
use App\Services\PdfPasswordProtectionService;
use App\Services\ReportWhatsappDueGate;
use App\Services\WatiService;
use App\Support\DiagnosticReportGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * "Upload X-Ray Report" -- X-Ray reports are produced outside this system
 * (by the imaging equipment's own software), so instead of narrative entry
 * the user attaches the finished PDF to the invoice. One PDF per invoice,
 * stored in public/invoices as <invoice-no>-xray-report.pdf (slashes
 * replaced by dashes), and surfaced on the Test Report Dashboard and the
 * Delivery Log via XrayReportUpload::viewUrlFor().
 */
class XrayReportUploadController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_TEST_REPORT';

    private const MESSAGE_TYPE = 'XRAY_REPORT';

    private const MAX_KB = 20480;

    public function index()
    {
        return view('apps-xray-report-upload');
    }

    /*
    |--------------------------------------------------------------------------
    | LIST -- every non-cancelled diagnostic invoice carrying an X-Ray line
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            })
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('invoice_details as d')
                    ->whereColumn('d.invoice_no', 'invoices.invoice_no')
                    ->where('d.item_code', XrayReportUpload::ITEM_CODE);
            });

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
        }

        $range = $request->get('range', '7');
        $rangeDays = ['3' => 3, '7' => 7, '15' => 15, '30' => 30];

        if (isset($rangeDays[$range])) {
            $query->whereDate(
                'invoice_date',
                '>=',
                now()->subDays($rangeDays[$range] - 1)->toDateString()
            );
        }

        $uploadStatus = $request->get('upload_status', 'all');

        $uploaded = function ($sub) {
            $sub->selectRaw('1')
                ->from('xray_report_uploads as x')
                ->whereColumn('x.invoice_no', 'invoices.invoice_no');
        };

        if ($uploadStatus === 'uploaded') {
            $query->whereExists($uploaded);
        } elseif ($uploadStatus === 'not_uploaded') {
            $query->whereNotExists($uploaded);
        }

        $invoices = $query->orderByDesc('invoice_date')->orderByDesc('id')->paginate($perPage);

        $invoiceNos = $invoices->getCollection()->pluck('invoice_no');

        $uploads = XrayReportUpload::with('uploader')
            ->whereIn('invoice_no', $invoiceNos)
            ->get()
            ->keyBy('invoice_no');

        $tests = DB::table('invoice_details')
            ->whereIn('invoice_no', $invoiceNos)
            ->where('item_code', XrayReportUpload::ITEM_CODE)
            ->orderBy('line_no')
            ->get(['invoice_no', 'item_description'])
            ->groupBy('invoice_no');

        $rows = $invoices->getCollection()->map(function ($invoice) use ($uploads, $tests) {

            $upload = $uploads->get($invoice->invoice_no);

            $paymentStatus = $invoice->due_amount <= 0
                ? 'Paid'
                : ($invoice->paid_amount <= 0 ? 'Due' : 'Partial');

            return [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date
                    ? Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : null,
                'patient_name' => $invoice->patient_name,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'xray_tests' => $tests->get($invoice->invoice_no, collect())->pluck('item_description')->implode(', '),
                'payment_status' => $paymentStatus,
                'due_amount' => (float) $invoice->due_amount,
                'uploaded' => (bool) $upload,
                'uploaded_at' => $upload ? $upload->updated_at->format('d-m-Y H:i') : null,
                'uploaded_by' => $upload ? optional($upload->uploader)->name : null,
                'view_url' => ($upload && $upload->fileExists())
                    ? route('xray-report-upload.view', $invoice->id)
                    : null,
                'file_missing' => $upload && !$upload->fileExists(),
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $rows,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPLOAD (or replace) the invoice's X-Ray report PDF
    |--------------------------------------------------------------------------
    */

    public function upload(Request $request, AuditService $auditService, WatiService $wati)
    {
        $request->validate([
            'invoice_id' => 'required|integer',
            'file' => 'required|file|mimes:pdf|max:' . self::MAX_KB,
        ], [
            'file.mimes' => 'Only a PDF file can be uploaded.',
            'file.max' => 'The PDF must be ' . (self::MAX_KB / 1024) . ' MB or smaller.',
        ]);

        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($request->invoice_id);

        if (DiagnosticReportGuard::invoiceIsCancelled($invoice->invoice_no)) {

            return response()->json([
                'status' => false,
                'message' => 'This invoice has been cancelled -- no report can be uploaded for it.'
            ], 422);
        }

        $hasXray = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->where('item_code', XrayReportUpload::ITEM_CODE)
            ->exists();

        if (!$hasXray) {

            return response()->json([
                'status' => false,
                'message' => 'This invoice has no X-Ray item.'
            ], 422);
        }

        $file = $request->file('file');

        // The extension/MIME check above can be fooled by a renamed file --
        // a real PDF always begins with the %PDF- signature.
        $handle = fopen($file->getRealPath(), 'rb');
        $signature = $handle ? fread($handle, 5) : '';
        if ($handle) {
            fclose($handle);
        }

        if ($signature !== '%PDF-') {

            return response()->json([
                'status' => false,
                'message' => 'The selected file is not a valid PDF.'
            ], 422);
        }

        $fileName = XrayReportUpload::fileNameFor($invoice->invoice_no);

        $dir = public_path('invoices');

        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $file->move($dir, $fileName);

        $existing = XrayReportUpload::where('invoice_no', $invoice->invoice_no)->first();

        $upload = XrayReportUpload::updateOrCreate(
            ['invoice_no' => $invoice->invoice_no],
            [
                'file_name' => $fileName,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'size_bytes' => File::size($dir . DIRECTORY_SEPARATOR . $fileName),
                'uploaded_by' => Auth::id(),
            ]
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'CUSTOM',
            ($existing ? 'X-Ray report replaced' : 'X-Ray report uploaded') . ' (' . $fileName . ')'
        );

        $whatsapp = $this->autoSendWhatsapp($invoice, $upload, $wati, $auditService);

        $base = $existing ? 'X-Ray report replaced.' : 'X-Ray report uploaded.';

        return response()->json([
            'status' => true,
            'message' => $base . ' ' . $whatsapp['message'],
            'data' => [
                'file_name' => $upload->file_name,
                'whatsapp_status' => $whatsapp['status'],
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP -- sent automatically right after an upload. Follows the same
    | rules as every other report: Admin auto-send switch, held while any
    | payment is due (released by ReportWhatsappDueGate::releaseAll() once
    | the invoice is paid), and password-protected with the last 4 digits of
    | the patient's mobile number.
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{status: string, message: string}
     */
    private function autoSendWhatsapp(Invoice $invoice, XrayReportUpload $upload, WatiService $wati, AuditService $auditService): array
    {
        if (!WhatsappAutoSendSetting::isEnabled(self::MESSAGE_TYPE)) {

            WhatsappAutoSendSetting::logSkipped(self::MESSAGE_TYPE, $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);

            return ['status' => 'skipped', 'message' => 'WhatsApp was not sent (automatic sending is switched off).'];
        }

        if ((float) $invoice->due_amount > 0) {

            ReportWhatsappDueGate::hold(self::MESSAGE_TYPE, $invoice);

            return ['status' => 'held_due', 'message' => 'WhatsApp is on hold until the pending payment is cleared; it will then be sent automatically.'];
        }

        $result = $this->sendWhatsapp($invoice, $upload, $wati, $auditService);

        return [
            'status' => $result['sent'] ? 'sent' : 'failed',
            'message' => $result['sent']
                ? 'Sent to the patient via WhatsApp.'
                : 'WhatsApp could not be sent: ' . $result['reason'],
        ];
    }

    /**
     * Manual "Send WhatsApp" -- staff retry after a failure, or a resend.
     * Blocked while a payment is due (Admin may override), like every
     * other report's manual send.
     */
    public function sendWhatsappNow($invoiceId, AuditService $auditService, WatiService $wati)
    {
        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($invoiceId);

        $upload = XrayReportUpload::where('invoice_no', $invoice->invoice_no)->first();

        if (!$upload || !$upload->fileExists()) {

            return response()->json(['status' => false, 'message' => 'Upload the X-Ray report first.'], 422);
        }

        if ($blocked = ReportWhatsappDueGate::manualBlockMessage($invoice->invoice_no)) {

            return response()->json(['status' => false, 'message' => $blocked]);
        }

        $result = $this->sendWhatsapp($invoice, $upload, $wati, $auditService);

        return response()->json([
            'status' => $result['sent'],
            'message' => $result['sent']
                ? 'Report sent via WhatsApp successfully.'
                : 'Unable to send report via WhatsApp: ' . $result['reason'],
        ], $result['sent'] ? 200 : 500);
    }

    /**
     * Sends held X-Ray reports once the invoice is fully paid (called from
     * ReportWhatsappDueGate::releaseAll()).
     */
    public function releaseHeldWhatsapp(string $invoiceNo): void
    {
        if (!ReportWhatsappDueGate::takeHeld($invoiceNo, self::MESSAGE_TYPE)) {
            return;
        }

        $invoice = Invoice::where('invoice_no', $invoiceNo)->first();
        $upload = XrayReportUpload::where('invoice_no', $invoiceNo)->first();

        if ($invoice && $upload && $upload->fileExists()) {
            $this->sendWhatsapp($invoice, $upload, app(WatiService::class), app(AuditService::class));
        }
    }

    /**
     * @return array{sent: bool, reason: string}
     */
    private function sendWhatsapp(Invoice $invoice, XrayReportUpload $upload, WatiService $wati, AuditService $auditService): array
    {
        $logFailure = function (string $reason) use ($invoice) {

            DB::table('whatsapp_message_logs')->insert([
                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => self::MESSAGE_TYPE,
                'status' => 'FAILED',
                'response' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['sent' => false, 'reason' => $reason];
        };

        try {

            $password = PdfPasswordProtectionService::passwordForMobile($invoice->patient_mobile_no);

            $sendFileName = $upload->file_name;

            if ($password) {

                // The stored upload stays readable for staff; the patient
                // gets a separate password-protected copy. If this PDF can't
                // be protected (some PDF versions can't be re-imported) it
                // is NOT sent unprotected -- better to tell staff than to
                // quietly drop the patient-privacy rule.
                try {

                    $protected = PdfPasswordProtectionService::protect(file_get_contents($upload->path()), $password);

                } catch (\Throwable $e) {

                    \Log::warning('X-Ray report password protection failed for ' . $invoice->invoice_no . ': ' . $e->getMessage());

                    return $logFailure('This PDF could not be password-protected (unsupported PDF version). Re-save or re-export it as a standard PDF and upload it again.');
                }

                $sendFileName = str_replace(XrayReportUpload::FILE_SUFFIX, '-xray-report-wa.pdf', $upload->file_name);

                file_put_contents(public_path('invoices/' . $sendFileName), $protected);
            }

            $testNames = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->where('item_code', XrayReportUpload::ITEM_CODE)
                ->orderBy('line_no')
                ->pluck('item_description')
                ->implode(', ');

            // Reuses the approved Non-Pathology report template (X-Ray is a
            // Non-Pathology category): {{1}}=patient name, {{2}}=test(s),
            // {{3}}=invoice no, {{4}}=Document header (the PDF).
            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.non_pathology_report_template_name'),
                config('services.wati.non_pathology_report_broadcast_name'),
                [
                    ['name' => '1', 'value' => $invoice->patient_name],
                    ['name' => '2', 'value' => $testNames],
                    ['name' => '3', 'value' => PdfPasswordProtectionService::invoiceNoWithHint($invoice->invoice_no, (bool) $password)],
                    ['name' => '4', 'value' => asset('invoices/' . $sendFileName)],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([
                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => self::MESSAGE_TYPE,
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => json_encode($watiResponse),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sent) {

                $auditService->logAction(
                    self::MODULE_CODE,
                    $invoice,
                    'WHATSAPP',
                    'X-Ray report sent via WhatsApp' . ($password ? ' (password-protected)' : ' (unprotected: no valid mobile number)')
                );
            }

            return ['sent' => $sent, 'reason' => $sent ? '' : 'WhatsApp service did not accept the message.'];

        } catch (\Throwable $e) {

            \Log::error('X-Ray Report WhatsApp Send Failed: ' . $e->getMessage());

            return ['sent' => false, 'reason' => 'unexpected error (see log).'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW -- streams the PDF to a logged-in user (the file also sits in the
    | public invoices folder, but in-app links go through here)
    |--------------------------------------------------------------------------
    */

    public function view($invoiceId)
    {
        $invoice = Invoice::where('invoice_type', 'DIAGNOSTIC')->findOrFail($invoiceId);

        $upload = XrayReportUpload::where('invoice_no', $invoice->invoice_no)->firstOrFail();

        abort_unless($upload->fileExists(), 404, 'The uploaded X-Ray report file could not be found.');

        return response()->file($upload->path(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $upload->file_name . '"',
        ]);
    }
}
