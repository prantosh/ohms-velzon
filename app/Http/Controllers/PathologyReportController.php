<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PathologyReportFinding;
use App\Models\PathologyReportFindingItem;
use App\Models\PathologyReportTemplate;
use App\Models\TestReportConfirmation;
use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use App\Services\WatiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Narrative, template-driven Pathology report entry -- replaces the old
 * structured analyte grid (see TestResultEntryController / TestReportRowBuilder,
 * both untouched: historical confirmed invoices keep printing through them
 * exactly as before). Reached through Test Result Entry's Pathology tab
 * (test-result-entry.init.js), the same way NonPathologyReportController is
 * reached through its Non-Pathology tab -- this module has no standalone
 * dashboard of its own.
 *
 * Unlike every other narrative module in this app (USG, Cardiology,
 * Non-Pathology -- always exactly one billed line per report), a Pathology
 * report can bundle SEVERAL billed lines into one report (a template
 * covering a panel of tests, e.g. a Widal panel) -- see
 * pathology_report_finding_items, which is what makes staggered/partial
 * invoice completion work: a line not yet claimed by any finding stays
 * selectable for a later template pick, independent of lines already
 * confirmed and delivered.
 */
class PathologyReportController extends Controller
{
    private const MODULE_CODE = 'PATHOLOGY_REPORT';
    private const ITEM_CODE = 'PAT001';
    private const UNGROUPED_LABEL = 'Ungrouped / General';

    /*
    |--------------------------------------------------------------------------
    | SEARCH INVOICE -- returns the invoice's Pathology lines bucketed by
    | test group, each bucket split into "already has a report" (existing
    | findings, editable/confirmable/printable) and "still open" (unclaimed
    | lines, with the templates currently available to cover them).
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

        // Confirmed under the OLD analyte-grid system before this feature
        // existed -- there is no data for it in the new tables at all, so
        // stop here and let the frontend show the legacy read-only card
        // (Print/WhatsApp still hit TestResultEntryController's untouched
        // routes) instead of a misleading "nothing reported yet" screen.
        if (TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->exists()) {

            return response()->json([
                'status' => true,
                'invoice' => $this->invoicePayload($invoice),
                'legacy_confirmed' => true,
                'groups' => [],
            ]);
        }

        $lines = DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->leftJoin('pathology_report_finding_items as pfi', 'pfi.invoice_detail_id', '=', 'd.id')
            ->where('d.invoice_no', $invoice->invoice_no)
            ->where('d.item_code', self::ITEM_CODE)
            ->where('iid.is_outsourced', 0)
            ->orderBy('d.line_no')
            ->get([
                'd.id as invoice_detail_id',
                'd.item_code_sub',
                'd.item_description',
                'iid.test_group_code',
                'pfi.pathology_report_finding_id',
            ]);

        if ($lines->isEmpty()) {

            return response()->json([
                'status' => true,
                'invoice' => $this->invoicePayload($invoice),
                'legacy_confirmed' => false,
                'groups' => [],
            ]);
        }

        $lineDescriptionById = $lines->pluck('item_description', 'invoice_detail_id');

        $testGroupCodes = $lines->pluck('test_group_code')->filter()->unique();

        $testGroupNames = DB::table('test_group_masters')
            ->whereIn('id', $testGroupCodes)
            ->pluck('test_group_name', 'id');

        $findingIds = $lines->pluck('pathology_report_finding_id')->filter()->unique();

        $findings = PathologyReportFinding::with('items')
            ->whereIn('id', $findingIds)
            ->get()
            ->keyBy('id');

        $templateTitles = PathologyReportTemplate::whereIn(
            'id',
            $findings->pluck('pathology_report_template_id')->filter()->unique()
        )->pluck('title', 'id');

        $unclaimedByGroup = $lines->whereNull('pathology_report_finding_id')
            ->groupBy(fn ($l) => $l->test_group_code ?? 0);

        $availableTemplatesByGroup = $unclaimedByGroup->map(function ($groupLines, $testGroupCode) {

            $unclaimedItemCodeSubs = $groupLines->pluck('item_code_sub')->unique();

            $candidateTemplateIds = DB::table('pathology_report_template_items')
                ->whereIn('item_code_sub', $unclaimedItemCodeSubs)
                ->pluck('pathology_report_template_id')
                ->unique();

            if ($candidateTemplateIds->isEmpty()) {
                return collect();
            }

            $templates = PathologyReportTemplate::with('items')
                ->whereIn('id', $candidateTemplateIds)
                ->where('status', 'ACTIVE')
                ->where('test_group_code', $testGroupCode == 0 ? null : $testGroupCode)
                ->orderBy('title')
                ->get();

            // Available only if EVERY item the template covers is among
            // this invoice's still-open lines in this group -- this is
            // what makes "pick a template -> its items are auto-selected"
            // work with no separate manual item-selection step.
            return $templates->filter(function ($template) use ($unclaimedItemCodeSubs) {
                $covered = $template->items->pluck('item_code_sub');
                return $covered->isNotEmpty() && $covered->diff($unclaimedItemCodeSubs)->isEmpty();
            })->values();
        });

        $groups = $lines->groupBy(fn ($l) => $l->test_group_code ?? 0)
            ->map(function ($groupLines, $testGroupCode) use ($testGroupNames, $findings, $templateTitles, $availableTemplatesByGroup, $lineDescriptionById) {

                $groupFindingIds = $groupLines->pluck('pathology_report_finding_id')->filter()->unique();

                $findingsPayload = $groupFindingIds->map(function ($findingId) use ($findings, $templateTitles, $lineDescriptionById) {

                    $finding = $findings->get($findingId);

                    if (!$finding) {
                        return null;
                    }

                    return [
                        'id' => $finding->id,
                        'content' => $finding->content,
                        'template_id' => $finding->pathology_report_template_id,
                        'template_title' => $templateTitles->get($finding->pathology_report_template_id),
                        'confirmed_at' => optional($finding->confirmed_at)->format('d-m-Y H:i'),
                        'items' => $finding->items->map(fn ($i) => [
                            'invoice_detail_id' => $i->invoice_detail_id,
                            'item_code_sub' => $i->item_code_sub,
                            'item_description' => $lineDescriptionById->get($i->invoice_detail_id, $i->item_code_sub),
                        ])->values(),
                    ];
                })->filter()->values();

                $unclaimed = $groupLines->whereNull('pathology_report_finding_id')->map(fn ($l) => [
                    'invoice_detail_id' => $l->invoice_detail_id,
                    'item_code_sub' => $l->item_code_sub,
                    'item_description' => $l->item_description,
                ])->values();

                $availableTemplates = ($availableTemplatesByGroup->get($testGroupCode) ?? collect())
                    ->map(fn ($t) => [
                        'id' => $t->id,
                        'title' => $t->title,
                        'content' => $t->content,
                        'item_code_subs' => $t->items->pluck('item_code_sub')->values(),
                    ])->values();

                return [
                    'test_group_code' => $testGroupCode == 0 ? null : $testGroupCode,
                    'test_group_name' => $testGroupCode == 0 ? self::UNGROUPED_LABEL : $testGroupNames->get($testGroupCode, self::UNGROUPED_LABEL),
                    'findings' => $findingsPayload,
                    'unclaimed_items' => $unclaimed,
                    'available_templates' => $availableTemplates,
                ];
            })
            ->sortBy('test_group_name')
            ->values();

        return response()->json([
            'status' => true,
            'invoice' => $this->invoicePayload($invoice),
            'legacy_confirmed' => false,
            'groups' => $groups,
        ]);
    }

    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_date' => $invoice->invoice_date
                ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                : null,
            'patient_name' => $invoice->patient_name,
            'patient_age' => $invoice->patient_age,
            'patient_gender' => $invoice->patient_gender,
            'referred_doctor' => $invoice->referred_doctor,
            'status' => $invoice->status,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE -- creates a new finding claiming the given lines (a template
    | pick, or a blank multi/single-item report), or updates an existing
    | unconfirmed finding's content in place (its claimed item set is fixed
    | at creation -- to report on more lines, save another finding for them).
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_no' => 'required',
                'finding_id' => 'nullable|integer',
                'template_id' => 'nullable|integer|exists:pathology_report_templates,id',
                'invoice_detail_ids' => 'required_without:finding_id|array|min:1',
                'invoice_detail_ids.*' => 'integer',
                'content' => 'nullable|string',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $content = HtmlSanitizerService::sanitizeClinicalText($request->content);

        DB::beginTransaction();

        try {

            if ($request->filled('finding_id')) {

                $finding = PathologyReportFinding::findOrFail($request->finding_id);

                if ($finding->confirmed_at) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'This report is already confirmed and locked.'
                    ], 422);
                }

                $finding->update([
                    'content' => $content,
                    'updated_by' => Auth::id(),
                ]);

                DB::commit();

                $auditService->logAction(self::MODULE_CODE, $finding, 'UPDATE', 'Pathology report finding updated');

                return response()->json([
                    'status' => true,
                    'message' => 'Saved.',
                    'data' => ['id' => $finding->id]
                ]);
            }

            $invoiceDetailIds = collect($request->invoice_detail_ids)->unique()->values();

            $lines = DB::table('invoice_details')
                ->whereIn('id', $invoiceDetailIds)
                ->where('item_code', self::ITEM_CODE)
                ->get();

            if ($lines->count() !== $invoiceDetailIds->count()) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'One or more selected lines are not valid Pathology items.'
                ], 422);
            }

            $alreadyClaimed = PathologyReportFindingItem::whereIn('invoice_detail_id', $invoiceDetailIds)->exists();

            if ($alreadyClaimed) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'One or more selected lines already belong to another report.'
                ], 422);
            }

            $invoiceNo = $lines->first()->invoice_no;

            $finding = PathologyReportFinding::create([
                'invoice_no' => $invoiceNo,
                'pathology_report_template_id' => $request->template_id,
                'content' => $content,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach ($lines as $line) {

                PathologyReportFindingItem::create([
                    'pathology_report_finding_id' => $finding->id,
                    'invoice_detail_id' => $line->id,
                    'item_code_sub' => $line->item_code_sub,
                ]);
            }

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $finding,
                array_merge($finding->only($finding->getFillable()), ['invoice_detail_ids' => $invoiceDetailIds]),
                'Pathology report finding created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Saved.',
                'data' => ['id' => $finding->id]
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            \Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Pathology report.'
            ], 500);
        }
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
            [
                'invoice_detail_ids' => 'required|array|min:1',
                'invoice_detail_ids.*' => 'integer',
                'content' => 'nullable|string',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $lines = DB::table('invoice_details')
            ->whereIn('id', $request->invoice_detail_ids)
            ->where('item_code', self::ITEM_CODE)
            ->get();

        if ($lines->isEmpty()) {

            return response()->json([
                'status' => false,
                'message' => 'Not a valid Pathology line item.'
            ], 422);
        }

        $invoice = Invoice::where('invoice_no', $lines->first()->invoice_no)->firstOrFail();

        $finding = (object) [
            'invoice_no' => $invoice->invoice_no,
            'content' => HtmlSanitizerService::sanitizeClinicalText($request->content),
            'confirmed_at' => null,
        ];

        $itemDescriptions = $lines->pluck('item_description');

        $pdf = Pdf::loadView(
            'apps-pathology-report-pdf',
            compact('finding', 'invoice', 'itemDescriptions')
        );

        return $pdf->stream('pathology-report-preview.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM (locks this one report)
    |--------------------------------------------------------------------------
    */

    public function confirm(Request $request, AuditService $auditService, WatiService $wati)
    {
        $validator = Validator::make(
            $request->all(),
            ['finding_id' => 'required|integer']
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $finding = PathologyReportFinding::find($request->finding_id);

        if (!$finding || empty($finding->content)) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot confirm -- report content must be entered first.'
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

        $auditService->logAction(self::MODULE_CODE, $finding, 'CONFIRM', 'Pathology report confirmed and locked');

        $whatsappStatus = $this->autoSendReportWhatsapp($finding, $wati, $auditService);

        return response()->json([
            'status' => true,
            'message' => 'Pathology report confirmed.',
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
        $finding = PathologyReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            abort(403, 'Pathology report must be confirmed before printing.');
        }

        [$invoice, $itemDescriptions] = $this->loadReportContext($finding);

        $pdf = Pdf::loadView(
            'apps-pathology-report-pdf',
            compact('finding', 'invoice', 'itemDescriptions')
        );

        $auditService->logAction(self::MODULE_CODE, $finding, 'PRINT', 'Pathology report printed');

        return $pdf->stream($this->safeFileName($finding));
    }

    /*
    |--------------------------------------------------------------------------
    | SEND REPORT VIA WHATSAPP
    |--------------------------------------------------------------------------
    */

    public function sendWhatsapp($id, AuditService $auditService, WatiService $wati)
    {
        $finding = PathologyReportFinding::findOrFail($id);

        if (!$finding->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'Pathology report must be confirmed before sending.'
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

    private function sendReportWhatsapp(PathologyReportFinding $finding, WatiService $wati, AuditService $auditService): bool
    {
        try {

            [$invoice, $itemDescriptions] = $this->loadReportContext($finding);

            $pdf = Pdf::loadView(
                'apps-pathology-report-pdf',
                compact('finding', 'invoice', 'itemDescriptions')
            );

            $fileName = $this->safeFileName($finding);

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            // Reuses Pathology's existing approved WATI template (the same
            // one TestResultEntryController::sendReportWhatsapp() sends) --
            // param order confirmed against that usage: {{1}}=Document
            // header (the PDF), {{2}}=patient name, {{3}}=invoice no.
            $watiResponse = $wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
                config('services.wati.test_report_template_name'),
                config('services.wati.test_report_broadcast_name'),
                [
                    ['name' => '1', 'value' => $pdfUrl],
                    ['name' => '2', 'value' => $invoice->patient_name],
                    ['name' => '3', 'value' => $invoice->invoice_no],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $finding->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => 'PATHOLOGY_REPORT',
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => json_encode($watiResponse),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sent) {

                $auditService->logAction(self::MODULE_CODE, $finding, 'WHATSAPP', 'Pathology report sent via WhatsApp');
            }

            return $sent;

        } catch (\Exception $e) {

            \Log::error('Pathology Report WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    private function autoSendReportWhatsapp(PathologyReportFinding $finding, WatiService $wati, AuditService $auditService): string
    {
        if (!WhatsappAutoSendSetting::isEnabled('PATHOLOGY_REPORT')) {
            [$invoice] = $this->loadReportContext($finding);
            WhatsappAutoSendSetting::logSkipped('PATHOLOGY_REPORT', $finding->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            return 'skipped';
        }

        return $this->sendReportWhatsapp($finding, $wati, $auditService) ? 'sent' : 'failed';
    }

    /**
     * @return array{0: Invoice, 1: \Illuminate\Support\Collection}
     */
    private function loadReportContext(PathologyReportFinding $finding): array
    {
        $invoice = Invoice::where('invoice_no', $finding->invoice_no)->firstOrFail();

        $itemDescriptions = DB::table('invoice_details')
            ->whereIn('id', $finding->items()->pluck('invoice_detail_id'))
            ->pluck('item_description');

        return [$invoice, $itemDescriptions];
    }

    private function safeFileName(PathologyReportFinding $finding): string
    {
        return str_replace(
            ['/', '\\'],
            '-',
            $finding->invoice_no . '-' . $finding->id
        ) . '-pathology-report.pdf';
    }
}
