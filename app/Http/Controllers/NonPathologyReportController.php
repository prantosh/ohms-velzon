<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItemMaster;
use App\Models\NonPathologyReportFinding;
use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Services\ReportWhatsappDueGate;
use App\Support\PdfPageNumbers;
use App\Services\PdfPasswordProtectionService;
use App\Services\HtmlSanitizerService;
use App\Services\WatiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Narrative-report module (Clinical History / Findings / Impression) for
 * every Non-Pathology category that has no structured parameter grid --
 * i.e. everything except Pathology (see TestResultEntryController /
 * TestReportRowBuilder) and USG (see UsgReportController, which already
 * solved this same problem for itself and stays separate). Each billed
 * line gets its own independently completable/confirmable/printable
 * report, mirroring UsgReportController's design exactly -- no separate
 * confirmation table; confirmed_by/confirmed_at live directly on
 * non_pathology_report_findings.
 *
 * Has its own standalone dashboard (index()/list() below), same pattern as
 * UsgReportController/CardiologyReportController -- previously this was
 * only reachable through Test Result Entry's Non-Pathology tab, which has
 * since been removed now that this has its own page.
 */
class NonPathologyReportController extends Controller
{
    private const MODULE_CODE = 'NON_PATHOLOGY_REPORT';

    private function qualifyingItemCodes(): array
    {
        return InvoiceItemMaster::where('test_parameter_required', '!=', 'YES')
            ->whereNotIn('item_code', ['USG001', 'DOC001'])
            ->pluck('item_code')
            ->toArray();
    }

    public function index()
    {
        return view('apps-non-pathology-report');
    }

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD LIST
    |--------------------------------------------------------------------------
    */

    private const RANGE_DAYS = [
        '1' => 1,
        '3' => 3,
        '7' => 7,
        '30' => 30,
    ];

    /**
     * Same qualifying-line definition as search()/store() above (every
     * Non-Pathology item_code except USG, minus Cardiology's own 6 Echo
     * sub-items, minus outsourced lines) -- an invoice needs at least one
     * such line to belong on this dashboard.
     */
    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $qualifyingItemCodes = $this->qualifyingItemCodes();

        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            })
            ->whereExists(function ($sub) use ($qualifyingItemCodes) {
                $sub->selectRaw('1')
                    ->from('invoice_details')
                    ->join('invoice_item_details', function ($join) {
                        $join->on('invoice_item_details.item_code', '=', 'invoice_details.item_code')
                            ->on('invoice_item_details.item_code_sub', '=', 'invoice_details.item_code_sub');
                    })
                    ->whereColumn('invoice_details.invoice_no', 'invoices.invoice_no')
                    ->whereIn('invoice_details.item_code', $qualifyingItemCodes)
                    ->whereNotIn('invoice_details.item_code_sub', \App\Support\CardiologyReportFields::ITEM_CODE_SUBS)
                    ->where('invoice_item_details.is_outsourced', 0)
                    ->where('invoice_item_details.is_report_not_required', 0);
            });

        $range = $request->get('range', '3');

        if ($range !== 'all' && isset(self::RANGE_DAYS[$range])) {
            $query->whereDate('invoice_date', '>=', now()->subDays(self::RANGE_DAYS[$range] - 1)->toDateString());
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
        }

        $invoices = $query->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = $invoices->getCollection()->map(fn ($invoice) => $this->toDashboardRow($invoice, $qualifyingItemCodes));

        return response()->json([
            'status' => true,
            'data' => $rows,
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ]
        ]);
    }

    private function toDashboardRow(Invoice $invoice, array $qualifyingItemCodes): array
    {
        $lines = DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->whereIn('d.item_code', $qualifyingItemCodes)
            ->whereNotIn('d.item_code_sub', \App\Support\CardiologyReportFields::ITEM_CODE_SUBS)
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0)
            ->get(['d.id as invoice_detail_id', 'd.item_code', 'd.item_description']);

        $totalTests = $lines->count();

        $confirmedTests = DB::table('non_pathology_report_findings')
            ->whereIn('invoice_detail_id', $lines->pluck('invoice_detail_id'))
            ->whereNotNull('confirmed_at')
            ->count();

        $resultStatus = $confirmedTests <= 0
            ? 'Pending'
            : ($confirmedTests >= $totalTests ? 'Complete' : 'Partial');

        // Spans several item_code families on one invoice (X-Ray, Dental,
        // Vaccination, Misc, etc.), unlike USG/Cardiology's single family --
        // shown so staff can tell at a glance what this row actually is.
        $testCategoryNames = InvoiceItemMaster::whereIn('item_code', $lines->pluck('item_code')->unique())
            ->pluck('item_name', 'item_code');

        $testCategory = $lines->pluck('item_code')->unique()
            ->map(fn ($code) => $testCategoryNames->get($code, $code))
            ->implode(', ');

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => $invoice->invoice_date
                ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                : null,
            'patient_name' => $invoice->patient_name,
            'patient_age' => $invoice->patient_age,
            'patient_gender' => $invoice->patient_gender,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'referred_doctor' => $invoice->referred_doctor,
            'test_category' => $testCategory,
            'test_description' => $lines->pluck('item_description')->implode(', '),
            'total_tests' => $totalTests,
            'confirmed_tests' => $confirmedTests,
            'result_status' => $resultStatus,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH INVOICE
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            ['invoice_no' => 'required']
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $invoice = Invoice::where('invoice_no', trim($request->invoice_no))->first();

        if (!$invoice) {

            return response()->json([
                'status' => false,
                'message' => 'Invoice not found.'
            ]);
        }

        $lines = DB::table('invoice_details as d')
            ->leftJoin('doctors as doc', 'doc.id', '=', 'd.doctor_id')
            ->leftJoin('non_pathology_report_findings as f', 'f.invoice_detail_id', '=', 'd.id')
            // Excludes tests physically performed and reported by an
            // outside agency -- this system has no report to produce for
            // those lines.
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->whereIn('d.item_code', $this->qualifyingItemCodes())
            // The 6 real Echo sub-items of CRD001 have their own dedicated
            // structured module now (CardiologyReportController) -- excluded
            // here so the two systems don't both claim the same line. The
            // other CRD001 sub-items (ECG, Holter, ABPM, Sleep Study) are
            // untouched and keep using this generic system.
            ->whereNotIn('d.item_code_sub', \App\Support\CardiologyReportFields::ITEM_CODE_SUBS)
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0)
            ->orderBy('d.line_no')
            ->get([
                'd.id as invoice_detail_id',
                'd.item_code',
                'd.item_code_sub',
                'd.item_description',
                'doc.doctor_name',
                'f.id as finding_id',
                'f.clinical_history',
                'f.findings',
                'f.impression',
                'f.confirmed_at',
            ]);

        return response()->json([
            'status' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_date' => $invoice->invoice_date
                    ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : null,
                'patient_name' => $invoice->patient_name,
                'patient_age' => $invoice->patient_age,
                'patient_gender' => $invoice->patient_gender,
            ],
            'lines' => $lines,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE FINDING (one Non-Pathology line)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_detail_id' => 'required|integer',
                'clinical_history' => 'nullable|max:20000',
                'findings' => 'nullable|max:20000',
                'impression' => 'nullable|max:20000',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $line = DB::table('invoice_details')
            ->where('id', $request->invoice_detail_id)
            ->whereIn('item_code', $this->qualifyingItemCodes())
            ->whereNotIn('item_code_sub', \App\Support\CardiologyReportFields::ITEM_CODE_SUBS)
            ->first();

        if (!$line) {

            return response()->json([
                'status' => false,
                'message' => 'Not a valid line item for this report.'
            ], 422);
        }

        $existing = NonPathologyReportFinding::where('invoice_detail_id', $line->id)->first();

        if ($existing && $existing->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'This report is already confirmed and locked.'
            ], 422);
        }

        if (\App\Support\DiagnosticReportGuard::invoiceIsCancelled($line->invoice_no)) {

            return response()->json([
                'status' => false,
                'message' => $existing
                    ? 'This invoice has been cancelled -- its report can no longer be edited.'
                    : 'This invoice has been cancelled -- no report can be created for it.'
            ], 422);
        }

        $finding = NonPathologyReportFinding::updateOrCreate(
            ['invoice_detail_id' => $line->id],
            [
                'invoice_no' => $line->invoice_no,
                'item_code' => $line->item_code,
                'item_code_sub' => $line->item_code_sub,
                'item_description' => $line->item_description,
                'clinical_history' => HtmlSanitizerService::sanitizeClinicalText($request->clinical_history),
                'findings' => HtmlSanitizerService::sanitizeClinicalText($request->findings),
                'impression' => HtmlSanitizerService::sanitizeClinicalText($request->impression),
                'created_by' => $existing->created_by ?? Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            $existing ? 'UPDATE' : 'CREATE',
            'Non-Pathology report finding saved'
        );

        return response()->json([
            'status' => true,
            'message' => 'Saved.',
            'data' => ['id' => $finding->id]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM (locks this one line's report)
    |--------------------------------------------------------------------------
    */

    public function confirm(Request $request, AuditService $auditService, WatiService $wati)
    {
        $validator = Validator::make(
            $request->all(),
            ['invoice_detail_id' => 'required|integer']
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $finding = NonPathologyReportFinding::where('invoice_detail_id', $request->invoice_detail_id)->first();

        // A report with nothing but a clinical history isn't reportable --
        // mirrors the same guard in UsgReportController::confirm().
        if (!$finding || (empty($finding->findings) && empty($finding->impression))) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot confirm -- Findings and Impression must be entered first.'
            ]);
        }

        if ($finding->confirmed_at) {

            return response()->json([
                'status' => true,
                'message' => 'Already confirmed.',
                'data' => ['confirmed_at' => $finding->confirmed_at->format('d-m-Y H:i')]
            ]);
        }

        $finding->update([
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            'CONFIRM',
            'Non-Pathology report confirmed and locked'
        );

        $whatsappStatus = $this->autoSendReportWhatsapp($finding, $wati, $auditService);

        return response()->json([
            'status' => true,
            'message' => (($whatsappStatus ?? null) === 'held_due') ? 'Report confirmed. WhatsApp is on hold until the pending payment is cleared.' : 'Report confirmed.',
            'data' => [
                'id' => $finding->id,
                'confirmed_at' => $finding->confirmed_at->format('d-m-Y H:i'),
                'whatsapp_status' => $whatsappStatus,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PDF PRINT
    |--------------------------------------------------------------------------
    */

    public function printReport($id, AuditService $auditService)
    {
        $finding = NonPathologyReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            abort(403, 'Report must be confirmed before printing.');
        }

        $pdf = $this->buildFindingPdf($finding);

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            'PRINT',
            'Non-Pathology report printed'
        );

        return $pdf->stream($this->safeFileName($finding));
    }

    /**
     * The staff-print PDF of one finding, page-numbered -- shared with the
     * Test Report Dashboard's "print selected items" (which merges several).
     */
    public function buildFindingPdf(NonPathologyReportFinding $finding)
    {
        [$invoice, , $doctor] = $this->loadReportContext($finding);

        $pdf = Pdf::loadView(
            'apps-non-pathology-report-pdf',
            compact('finding', 'invoice', 'doctor')
        );

        PdfPageNumbers::add($pdf, 20, 'center');

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REPORT VIA WHATSAPP
    |--------------------------------------------------------------------------
    */

    public function sendWhatsapp($id, AuditService $auditService, WatiService $wati)
    {
        $finding = NonPathologyReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'Report must be confirmed before sending.'
            ]);
        }

        if ($blocked = ReportWhatsappDueGate::manualBlockMessage($finding->invoice_no)) {

            return response()->json(['status' => false, 'message' => $blocked]);
        }

        $sent = $this->sendReportWhatsapp($finding, $wati, $auditService);

        return response()->json([
            'status' => $sent,
            'message' => $sent
                ? 'Report sent via WhatsApp successfully.'
                : 'Unable to send report via WhatsApp.'
        ], $sent ? 200 : 500);
    }

    /**
     * Shared by the manual "Send WhatsApp" button (always runs, ungated)
     * and the automatic send fired from confirm() (gated by
     * WhatsappAutoSendSetting -- checked by the caller, not in here, so
     * this method itself always actually attempts the send).
     */
    private function sendReportWhatsapp(NonPathologyReportFinding $finding, WatiService $wati, AuditService $auditService): bool
    {
        try {

            [$invoice, , $doctor] = $this->loadReportContext($finding);

            $pdf = Pdf::loadView(
                'apps-non-pathology-report-pdf',
                compact('finding', 'invoice', 'doctor')
            );

            PdfPageNumbers::add($pdf, 20, 'center');

            $fileName = $this->safeFileName($finding);

            $pdfPath = public_path('invoices/' . $fileName);

            $password = PdfPasswordProtectionService::passwordForMobile($invoice->patient_mobile_no);

            file_put_contents(
                $pdfPath,
                $password
                    ? PdfPasswordProtectionService::protect($pdf->output(), $password)
                    : $pdf->output()
            );

            $pdfUrl = asset('invoices/' . $fileName);

            // Unlike USG (one shared test type), this module spans several
            // categories (X-Ray, Cardiology, EMG-NCV, etc.), so the message
            // names the specific test via {{2}} -- item_description was
            // already captured on the finding at save time. Parameter order
            // confirmed against the approved WATI template: {{1}}=patient
            // name, {{2}}=test description, {{3}}=invoice no,
            // {{4}}=Document header (the PDF).
            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.non_pathology_report_template_name'),
                config('services.wati.non_pathology_report_broadcast_name'),
                [
                    ['name' => '1', 'value' => $invoice->patient_name],
                    ['name' => '2', 'value' => $finding->item_description],
                    ['name' => '3', 'value' => PdfPasswordProtectionService::invoiceNoWithHint($invoice->invoice_no, (bool) $password)],
                    ['name' => '4', 'value' => $pdfUrl],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $finding->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => 'NON_PATHOLOGY_REPORT',
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => json_encode($watiResponse),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sent) {

                $auditService->logAction(
                    self::MODULE_CODE,
                    $finding,
                    'WHATSAPP',
                    'Non-Pathology report sent via WhatsApp' . ($password ? ' (password-protected)' : ' (unprotected: no valid mobile number)')
                );
            }

            return $sent;

        } catch (\Exception $e) {

            \Log::error('Non-Pathology Report WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Gated entry point for the automatic trigger fired from confirm().
     * The manual sendWhatsapp() route above calls sendReportWhatsapp()
     * directly, ungated, so staff always retain a working resend button.
     */
    private function autoSendReportWhatsapp(NonPathologyReportFinding $finding, WatiService $wati, AuditService $auditService): string
    {
        if (!WhatsappAutoSendSetting::isEnabled('NON_PATHOLOGY_REPORT')) {
            [$invoice] = $this->loadReportContext($finding);
            WhatsappAutoSendSetting::logSkipped('NON_PATHOLOGY_REPORT', $finding->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            return 'skipped';
        }

        [$invoice] = $this->loadReportContext($finding);

        if ((float) $invoice->due_amount > 0) {
            ReportWhatsappDueGate::hold('NON_PATHOLOGY_REPORT', $invoice, $finding->id);
            return 'held_due';
        }

        return $this->sendReportWhatsapp($finding, $wati, $auditService) ? 'sent' : 'failed';
    }

    /**
     * Sends reports whose automatic WhatsApp was held for a payment due,
     * once the invoice is fully paid (see ReportWhatsappDueGate).
     */
    public function releaseHeldWhatsapp(string $invoiceNo): void
    {
        foreach (ReportWhatsappDueGate::takeHeld($invoiceNo, 'NON_PATHOLOGY_REPORT') as $findingId) {

            $finding = $findingId ? NonPathologyReportFinding::find($findingId) : null;

            if ($finding && $finding->confirmed_at) {
                $this->sendReportWhatsapp($finding, app(WatiService::class), app(AuditService::class));
            }
        }
    }

    /**
     * @return array{0: Invoice, 1: object|null, 2: object|null} [invoice, invoice_details row, doctor]
     */
    private function loadReportContext(NonPathologyReportFinding $finding): array
    {
        $invoice = Invoice::where('invoice_no', $finding->invoice_no)->firstOrFail();

        $line = DB::table('invoice_details')
            ->where('id', $finding->invoice_detail_id)
            ->first();

        $doctor = $line && $line->doctor_id
            ? DB::table('doctors')->where('id', $line->doctor_id)->first()
            : null;

        return [$invoice, $line, $doctor];
    }

    private function safeFileName(NonPathologyReportFinding $finding): string
    {
        return str_replace(
            ['/', '\\'],
            '-',
            $finding->invoice_no . '-' . $finding->item_code_sub
        ) . '-report.pdf';
    }
}
