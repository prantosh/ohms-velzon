<?php

namespace App\Http\Controllers;

use App\Models\CardiologyReportFinding;
use App\Models\Invoice;
use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use App\Services\WatiService;
use App\Support\CardiologyReportFields;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Mirrors UsgReportController exactly (same standalone-dashboard,
 * one-report-per-billed-line, no-separate-confirmation-table design), but
 * for the structured 16-field 2D-Echo layout in CardiologyReportFields
 * instead of USG's 3 freeform fields. Scoped to only the 6 real Echo
 * sub-items of CRD001 -- ECG/Holter/ABPM/Sleep Study stay on the generic
 * Non-Pathology narrative system (see that controller's exclusion of
 * CardiologyReportFields::ITEM_CODE_SUBS).
 */
class CardiologyReportController extends Controller
{
    private const MODULE_CODE = 'CARDIOLOGY_REPORT';

    public function index()
    {
        return view('apps-cardiology-report', [
            'fields' => CardiologyReportFields::FIELDS,
        ]);
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
                    ->whereIn('invoice_details.item_code_sub', CardiologyReportFields::ITEM_CODE_SUBS)
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
            ->whereIn('item_code_sub', CardiologyReportFields::ITEM_CODE_SUBS)
            ->pluck('item_description');

        $totalStudies = $studyDescriptions->count();

        $confirmedStudies = DB::table('cardiology_report_findings')
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
            ->leftJoin('cardiology_report_findings as f', 'f.invoice_detail_id', '=', 'd.id')
            // Excludes studies physically performed and reported by an
            // outside agency -- this system has no report to produce for
            // those lines.
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->whereIn('d.item_code_sub', CardiologyReportFields::ITEM_CODE_SUBS)
            ->where('iid.is_outsourced', 0)
            ->orderBy('d.line_no')
            ->get(array_merge(
                ['d.id as invoice_detail_id', 'd.item_code_sub', 'd.item_description', 'doc.doctor_name', 'f.id as finding_id', 'f.confirmed_at'],
                array_map(fn ($key) => "f.{$key}", array_keys(CardiologyReportFields::FIELDS))
            ));

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
    | SAVE FINDING (one Echo line)
    |--------------------------------------------------------------------------
    */

    private function fieldsFromRequest(Request $request): array
    {
        $data = [];
        foreach (CardiologyReportFields::FIELDS as $key => $label) {
            $data[$key] = HtmlSanitizerService::sanitizeClinicalText($request->input($key));
        }
        return $data;
    }

    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge(['invoice_detail_id' => 'required|integer'], CardiologyReportFields::validationRules())
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $line = DB::table('invoice_details')
            ->where('id', $request->invoice_detail_id)
            ->whereIn('item_code_sub', CardiologyReportFields::ITEM_CODE_SUBS)
            ->first();

        if (!$line) {

            return response()->json([
                'status' => false,
                'message' => 'Not a valid Cardiology Echo line item.'
            ], 422);
        }

        $existing = CardiologyReportFinding::where('invoice_detail_id', $line->id)->first();

        if ($existing && $existing->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'This report is already confirmed and locked.'
            ], 422);
        }

        $finding = CardiologyReportFinding::updateOrCreate(
            ['invoice_detail_id' => $line->id],
            array_merge([
                'invoice_no' => $line->invoice_no,
                'item_code_sub' => $line->item_code_sub,
                'item_description' => $line->item_description,
                'created_by' => $existing->created_by ?? Auth::id(),
                'updated_by' => Auth::id(),
            ], $this->fieldsFromRequest($request))
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            $existing ? 'UPDATE' : 'CREATE',
            'Cardiology report finding saved'
        );

        return response()->json([
            'status' => true,
            'message' => 'Saved.',
            'data' => ['id' => $finding->id]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW (unsaved draft)
    |--------------------------------------------------------------------------
    */

    public function preview(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            array_merge(['invoice_detail_id' => 'required|integer'], CardiologyReportFields::validationRules())
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $line = DB::table('invoice_details')
            ->where('id', $request->invoice_detail_id)
            ->whereIn('item_code_sub', CardiologyReportFields::ITEM_CODE_SUBS)
            ->first();

        if (!$line) {

            return response()->json([
                'status' => false,
                'message' => 'Not a valid Cardiology Echo line item.'
            ], 422);
        }

        $invoice = Invoice::where('invoice_no', $line->invoice_no)->firstOrFail();

        $doctor = $line->doctor_id
            ? DB::table('doctors')->where('id', $line->doctor_id)->first()
            : null;

        // A plain stdClass standing in for CardiologyReportFinding -- the PDF
        // template only ever reads properties off $finding, never calls a
        // model method, so the unsaved draft text can flow straight through.
        $finding = (object) array_merge([
            'item_description' => $line->item_description,
            'confirmed_at' => null,
        ], $this->fieldsFromRequest($request));

        $pdf = Pdf::loadView(
            'apps-cardiology-report-pdf',
            compact('finding', 'invoice', 'doctor')
        );

        return $pdf->stream('cardiology-report-preview.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM (locks this one study's report)
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

        $finding = CardiologyReportFinding::where('invoice_detail_id', $request->invoice_detail_id)->first();

        // Conclusion is the one mandatory "this report has a bottom line"
        // field -- mirrors USG requiring findings+impression, without
        // demanding every one of the 16 sections be filled (a normal study
        // legitimately leaves some sections blank).
        if (!$finding || empty($finding->conclusion)) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot confirm -- Conclusion must be entered first.'
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
            'Cardiology report confirmed and locked'
        );

        $whatsappStatus = $this->autoSendReportWhatsapp($finding, $wati, $auditService);

        return response()->json([
            'status' => true,
            'message' => 'Cardiology report confirmed.',
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
        $finding = CardiologyReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            abort(403, 'Cardiology report must be confirmed before printing.');
        }

        [$invoice, , $doctor] = $this->loadReportContext($finding);

        $pdf = Pdf::loadView(
            'apps-cardiology-report-pdf',
            compact('finding', 'invoice', 'doctor')
        );

        $auditService->logAction(
            self::MODULE_CODE,
            $finding,
            'PRINT',
            'Cardiology report printed'
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
        $finding = CardiologyReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'Cardiology report must be confirmed before sending.'
            ]);
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
    private function sendReportWhatsapp(CardiologyReportFinding $finding, WatiService $wati, AuditService $auditService): bool
    {
        try {

            [$invoice, , $doctor] = $this->loadReportContext($finding);

            $pdf = Pdf::loadView(
                'apps-cardiology-report-pdf',
                compact('finding', 'invoice', 'doctor')
            );

            $fileName = $this->safeFileName($finding);

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.cardiology_report_template_name'),
                config('services.wati.cardiology_report_broadcast_name'),
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
                'patient_name' => $invoice->patient_name,
                'message_type' => 'CARDIOLOGY_REPORT',
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
                    'Cardiology report sent via WhatsApp'
                );
            }

            return $sent;

        } catch (\Exception $e) {

            \Log::error('Cardiology Report WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Gated entry point for the automatic trigger fired from confirm().
     * The manual sendWhatsapp() route above calls sendReportWhatsapp()
     * directly, ungated, so staff always retain a working resend button.
     */
    private function autoSendReportWhatsapp(CardiologyReportFinding $finding, WatiService $wati, AuditService $auditService): string
    {
        if (!WhatsappAutoSendSetting::isEnabled('CARDIOLOGY_REPORT')) {
            [$invoice] = $this->loadReportContext($finding);
            WhatsappAutoSendSetting::logSkipped('CARDIOLOGY_REPORT', $finding->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            return 'skipped';
        }

        return $this->sendReportWhatsapp($finding, $wati, $auditService) ? 'sent' : 'failed';
    }

    /**
     * @return array{0: Invoice, 1: object|null, 2: object|null} [invoice, invoice_details row, doctor]
     */
    private function loadReportContext(CardiologyReportFinding $finding): array
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

    private function safeFileName(CardiologyReportFinding $finding): string
    {
        return str_replace(
            ['/', '\\'],
            '-',
            $finding->invoice_no . '-' . $finding->item_code_sub
        ) . '-cardiology-report.pdf';
    }
}
