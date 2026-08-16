<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\UsgReportFinding;
use App\Services\AuditService;
use App\Services\WatiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * USG is a narrative-report diagnostic category (Clinical History /
 * Findings / Impression), unlike Pathology's tabular lab-value reports
 * (see TestResultEntryController / TestReportRowBuilder, both untouched by
 * this controller). Each billed USG line gets its own independently
 * completable/confirmable/printable report -- not one combined report per
 * invoice like Pathology -- so there is no separate confirmation table:
 * confirmed_by/confirmed_at live directly on usg_report_findings.
 */
class UsgReportController extends Controller
{
    private const MODULE_CODE = 'USG_REPORT';

    /**
     * The one thing distinguishing this module from a future sibling
     * (e.g. an X-Ray report module built the same way) -- kept as a single
     * constant rather than scattered literals.
     */
    private const ITEM_CODE = 'USG001';

    public function index()
    {
        return view('apps-usg-report');
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

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        // Cannot filter by invoices.invoice_category -- USG shares
        // NON_PATHOLOGY with every other non-pathology category. An
        // invoice qualifies for this dashboard if it has at least one
        // billed, non-outsourced USG001 line (an outsourced USG study is
        // physically performed and reported by an outside agency -- this
        // system has no report to produce for it).
        $query = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->where(function ($q) {
                $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
            })
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('invoice_details')
                    ->join('invoice_item_details', function ($join) {
                        $join->on('invoice_item_details.item_code', '=', 'invoice_details.item_code')
                            ->on('invoice_item_details.item_code_sub', '=', 'invoice_details.item_code_sub');
                    })
                    ->whereColumn('invoice_details.invoice_no', 'invoices.invoice_no')
                    ->where('invoice_details.item_code', self::ITEM_CODE)
                    ->where('invoice_item_details.is_outsourced', 0);
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

        $rows = $invoices->getCollection()->map(fn ($invoice) => $this->toRow($invoice));

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

    private function toRow(Invoice $invoice): array
    {
        $studyDescriptions = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->where('item_code', self::ITEM_CODE)
            ->pluck('item_description');

        $totalStudies = $studyDescriptions->count();

        $confirmedStudies = DB::table('usg_report_findings')
            ->where('invoice_no', $invoice->invoice_no)
            ->whereNotNull('confirmed_at')
            ->count();

        $resultStatus = $confirmedStudies <= 0
            ? 'Pending'
            : ($confirmedStudies >= $totalStudies ? 'Complete' : 'Partial');

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
            'test_description' => $studyDescriptions->implode(', '),
            'total_studies' => $totalStudies,
            'confirmed_studies' => $confirmedStudies,
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
            ->leftJoin('usg_report_findings as f', 'f.invoice_detail_id', '=', 'd.id')
            // Excludes studies physically performed and reported by an
            // outside agency -- this system has no report to produce for
            // those lines.
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->where('d.item_code', self::ITEM_CODE)
            ->where('iid.is_outsourced', 0)
            ->orderBy('d.line_no')
            ->get([
                'd.id as invoice_detail_id',
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
    | SAVE FINDING (one USG line)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_detail_id' => 'required|integer',
                'clinical_history' => 'nullable|max:5000',
                'findings' => 'nullable|max:5000',
                'impression' => 'nullable|max:5000',
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
            ->where('item_code', self::ITEM_CODE)
            ->first();

        if (!$line) {

            return response()->json([
                'status' => false,
                'message' => 'Not a valid USG line item.'
            ], 422);
        }

        $existing = UsgReportFinding::where('invoice_detail_id', $line->id)->first();

        if ($existing && $existing->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'This report is already confirmed and locked.'
            ], 422);
        }

        $finding = UsgReportFinding::updateOrCreate(
            ['invoice_detail_id' => $line->id],
            [
                'invoice_no' => $line->invoice_no,
                'item_code_sub' => $line->item_code_sub,
                'item_description' => $line->item_description,
                'clinical_history' => $request->clinical_history,
                'findings' => $request->findings,
                'impression' => $request->impression,
                'created_by' => $existing->created_by ?? Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            $existing ? 'UPDATE' : 'CREATE',
            'USG report finding saved'
        );

        return response()->json([
            'status' => true,
            'message' => 'Saved.',
            'data' => ['id' => $finding->id]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW (unsaved draft -- lets staff see the printed report and still
    | go back and edit before Save/Confirm; nothing is persisted here)
    |--------------------------------------------------------------------------
    */

    public function preview(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_detail_id' => 'required|integer',
                'clinical_history' => 'nullable|max:5000',
                'findings' => 'nullable|max:5000',
                'impression' => 'nullable|max:5000',
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
            ->where('item_code', self::ITEM_CODE)
            ->first();

        if (!$line) {

            return response()->json([
                'status' => false,
                'message' => 'Not a valid USG line item.'
            ], 422);
        }

        $invoice = Invoice::where('invoice_no', $line->invoice_no)->firstOrFail();

        $doctor = $line->doctor_id
            ? DB::table('doctors')->where('id', $line->doctor_id)->first()
            : null;

        // A plain stdClass standing in for UsgReportFinding -- the PDF
        // template only ever reads properties off $finding, never calls a
        // model method, so the unsaved draft text can flow straight through.
        $finding = (object) [
            'item_description' => $line->item_description,
            'clinical_history' => $request->clinical_history,
            'findings' => $request->findings,
            'impression' => $request->impression,
            'confirmed_at' => null,
        ];

        $pdf = Pdf::loadView(
            'apps-usg-report-pdf',
            compact('finding', 'invoice', 'doctor')
        );

        return $pdf->stream('usg-report-preview.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM (locks this one study's report)
    |--------------------------------------------------------------------------
    */

    public function confirm(Request $request, AuditService $auditService)
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

        $finding = UsgReportFinding::where('invoice_detail_id', $request->invoice_detail_id)->first();

        // A report with nothing but a clinical history isn't reportable --
        // mirrors the zero-results guard in TestResultEntryController::confirm()
        // (added after LAB/0726/001/0001 was confirmed with no results at all).
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
            'USG report confirmed and locked'
        );

        return response()->json([
            'status' => true,
            'message' => 'USG report confirmed.',
            'data' => [
                'id' => $finding->id,
                'confirmed_at' => $finding->confirmed_at->format('d-m-Y H:i'),
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
        $finding = UsgReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            abort(403, 'USG report must be confirmed before printing.');
        }

        [$invoice, , $doctor] = $this->loadReportContext($finding);

        $pdf = Pdf::loadView(
            'apps-usg-report-pdf',
            compact('finding', 'invoice', 'doctor')
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            'PRINT',
            'USG report printed'
        );

        return $pdf->stream($this->safeFileName($finding));
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REPORT VIA WHATSAPP
    |--------------------------------------------------------------------------
    */

    public function sendWhatsapp($id, AuditService $auditService, WatiService $wati)
    {
        try {

            $finding = UsgReportFinding::findOrFail($id);

            if (!$finding->confirmed_at) {

                return response()->json([
                    'status' => false,
                    'message' => 'USG report must be confirmed before sending.'
                ]);
            }

            [$invoice, , $doctor] = $this->loadReportContext($finding);

            $pdf = Pdf::loadView(
                'apps-usg-report-pdf',
                compact('finding', 'invoice', 'doctor')
            );

            $fileName = $this->safeFileName($finding);

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            // Parameter order matches the approved WATI template exactly --
            // its builder numbered the body placeholders first ({{1}} =
            // patient name, {{2}} = invoice no) and the Document header
            // last ({{3}} = the PDF URL), not header-first as the sibling
            // diagnostic_test_report template assumes.
            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.usg_report_template_name'),
                config('services.wati.usg_report_broadcast_name'),
                [
                    ['name' => '1', 'value' => $invoice->patient_name],
                    ['name' => '2', 'value' => $invoice->invoice_no],
                    ['name' => '3', 'value' => $pdfUrl],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $finding->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'message_type' => 'USG_REPORT',
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
                    'USG report sent via WhatsApp'
                );
            }

            return response()->json([
                'status' => $sent,
                'message' => $sent
                    ? 'Report sent via WhatsApp successfully.'
                    : 'Unable to send report via WhatsApp.'
            ], $sent ? 200 : 500);

        } catch (\Exception $e) {

            \Log::error('USG Report WhatsApp Send Failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Unable to send report via WhatsApp.'
            ], 500);
        }
    }

    /**
     * @return array{0: Invoice, 1: object|null, 2: object|null} [invoice, invoice_details row, doctor]
     */
    private function loadReportContext(UsgReportFinding $finding): array
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

    private function safeFileName(UsgReportFinding $finding): string
    {
        return str_replace(
            ['/', '\\'],
            '-',
            $finding->invoice_no . '-' . $finding->item_code_sub
        ) . '-usg-report.pdf';
    }
}
