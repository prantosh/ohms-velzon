<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PathologyReportFinding;
use App\Models\PathologyReportFindingItem;
use App\Models\PathologyReportTemplate;
use App\Models\TestReportConfirmation;
use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Services\ReportWhatsappDueGate;
use App\Support\PdfPageNumbers;
use App\Services\HtmlSanitizerService;
use App\Services\PdfPasswordProtectionService;
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

    public static function remainingTemplateItems(array $templateItemCodeSubs, array $openItemCodeSubs): array
    {
        return array_values(array_unique(array_intersect($templateItemCodeSubs, $openItemCodeSubs)));
    }

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
                'pathology_whatsapp' => ['sent' => false, 'sent_at' => null],
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
            ->where('iid.is_report_not_required', 0)
            // A package's own billed line (e.g. "LIPID PROFILE") is a
            // pricing label, not a measurable test -- it has no result of
            // its own (mirrors TestReportRowBuilder's identical exclusion
            // for the old tabular system). Its real, reportable components
            // are separate billed lines already, each carrying its own
            // test_group_code, so they fall into their own group's tab
            // exactly like any other item -- no special handling needed
            // for them beyond excluding the package's own non-reportable
            // row here.
            // New reports belong to package components, not the package's
            // pricing row. Keep a package row only when an older report is
            // already linked to it so existing confirmed reports remain
            // visible after package rows were excluded from new reporting.
            ->where(function ($q) {
                $q->where('iid.is_package', 0)
                    ->orWhereNotNull('pfi.pathology_report_finding_id');
            })
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
                'pathology_whatsapp' => ['sent' => false, 'sent_at' => null],
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

            // A template is offered only when EVERY test it covers is still
            // open on this invoice. A panel template (e.g. RENAL PROFILE, or
            // 'GLUCOSE (FBS & PPBS)') used to show up as soon as ONE of its
            // tests was open, cluttering the dropdown with templates for
            // tests the patient never ordered -- e.g. an FBS-only invoice
            // was offered the FBS+PPBS panel. Blank reports (one per open
            // item) are always available regardless; see the JS picker.
            return $templates->filter(function ($template) use ($unclaimedItemCodeSubs) {
                $covered = $template->items->pluck('item_code_sub')->values()->all();
                $notOpen = array_diff($covered, $unclaimedItemCodeSubs->values()->all());
                return !empty($covered) && empty($notOpen);
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
            'pathology_whatsapp' => $this->pathologyWhatsappStatus($invoice->invoice_no),
        ]);
    }

    /**
     * Whether a combined "all reports on this invoice" WhatsApp message has
     * already gone out -- whatsapp_message_logs is the source of truth
     * (same table every other module's WhatsApp send already logs to), so
     * a retry/manual send never double-sends. Which test groups are
     * complete/confirmed is fully derivable client-side already from the
     * groups payload above (each finding carries its own confirmed_at, and
     * unclaimed_items shows what's still open) -- this is the one piece of
     * server-side state that isn't.
     */
    private function pathologyWhatsappStatus(string $invoiceNo): array
    {
        $log = DB::table('whatsapp_message_logs')
            ->where('invoice_no', $invoiceNo)
            ->where('message_type', 'PATHOLOGY_REPORT')
            ->where('status', 'SENT')
            ->orderByDesc('id')
            ->first();

        return [
            'sent' => (bool) $log,
            'sent_at' => $log ? \Carbon\Carbon::parse($log->created_at)->format('d-m-Y H:i') : null,
        ];
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
                // Only a NEW finding (invoice_detail_ids branch) needs
                // invoice_no -- updating an EXISTING one (finding_id
                // branch, see below) already knows its invoice from the
                // finding row itself, and the JS never sends invoice_no in
                // that case at all. Unconditionally required here broke
                // every re-save of an already-started-but-unconfirmed
                // report with "The invoice no field is required.".
                'invoice_no' => 'required_without:finding_id',
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

                if (\App\Support\DiagnosticReportGuard::invoiceIsCancelled($finding->invoice_no)) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'This invoice has been cancelled -- its report can no longer be edited.'
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

            $requestedInvoiceNo = trim($request->invoice_no);

            if ($lines->contains(fn ($line) => $line->invoice_no !== $requestedInvoiceNo)) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Selected test lines must all belong to the requested invoice.'
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

            $invoiceNo = $requestedInvoiceNo;

            if (\App\Support\DiagnosticReportGuard::invoiceIsCancelled($invoiceNo)) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'This invoice has been cancelled -- no report can be created for it.'
                ], 422);
            }

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

        PdfPageNumbers::add($pdf);

        return $pdf->stream('pathology-report-preview.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM -- ONE TEST GROUP AT A TIME, NOT ONE REPORT AT A TIME
    |--------------------------------------------------------------------------
    | Confirming is invoice+test-group scoped: every report within the given
    | group is saved (see savePathologyCard() in the JS) and locked in one
    | action, matching how the group is also printed and WhatsApp'd as one
    | combined document. This is deliberately stricter than the old
    | per-report confirm -- it refuses unless EVERY billed line in the group
    | already has a report started, so a group can't be partially locked
    | while a line in it has no report at all yet.
    */

    public function confirm(Request $request, AuditService $auditService, WatiService $wati)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_no' => 'required',
                'test_group_code' => 'nullable|integer',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        $invoiceNo = trim($request->invoice_no);
        $testGroupCode = $request->filled('test_group_code') ? (int) $request->test_group_code : null;

        $lines = DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->leftJoin('pathology_report_finding_items as pfi', 'pfi.invoice_detail_id', '=', 'd.id')
            ->where('d.invoice_no', $invoiceNo)
            ->where('d.item_code', self::ITEM_CODE)
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0)
            ->where(function ($q) {
                $q->where('iid.is_package', 0)
                    ->orWhereNotNull('pfi.pathology_report_finding_id');
            })
            ->where(function ($q) use ($testGroupCode) {
                $testGroupCode === null
                    ? $q->whereNull('iid.test_group_code')
                    : $q->where('iid.test_group_code', $testGroupCode);
            })
            ->get(['d.id as invoice_detail_id', 'pfi.pathology_report_finding_id']);

        if ($lines->isEmpty()) {

            return response()->json([
                'status' => false,
                'message' => 'No Pathology items found for this test group on this invoice.'
            ]);
        }

        if ($lines->contains(fn ($l) => !$l->pathology_report_finding_id)) {

            return response()->json([
                'status' => false,
                'message' => 'Start a report for every test in this group before confirming.'
            ]);
        }

        $findings = PathologyReportFinding::whereIn('id', $lines->pluck('pathology_report_finding_id')->unique())->get();

        if ($findings->contains(fn ($f) => empty($f->content))) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot confirm -- every report in this group must have content entered first.'
            ]);
        }

        $toConfirm = $findings->whereNull('confirmed_at');

        foreach ($toConfirm as $finding) {

            $finding->update([
                'confirmed_by' => Auth::id(),
                'confirmed_at' => now(),
            ]);

            $auditService->logAction(self::MODULE_CODE, $finding, 'CONFIRM', 'Pathology report confirmed and locked (group confirm)');
        }

        // WhatsApp is invoice-scoped, not per-group -- the patient gets
        // exactly one combined message once every Pathology report on the
        // whole invoice is confirmed. Most invoices have only this one
        // group and this fires immediately; an invoice with several groups
        // only sends once the LAST one is confirmed.
        $whatsappStatus = $this->isInvoicePathologyFullyConfirmed($invoiceNo)
            ? $this->autoSendInvoiceWhatsapp($invoiceNo, $wati, $auditService)
            : 'pending_other_reports';

        return response()->json([
            'status' => true,
            'message' => 'Group confirmed.',
            'data' => [
                'confirmed_finding_ids' => $toConfirm->pluck('id')->values(),
                'confirmed_at' => now()->format('d-m-Y H:i'),
                'whatsapp_status' => $whatsappStatus,
                'whatsapp_sent_at' => $whatsappStatus === 'sent' ? now()->format('d-m-Y H:i') : null,
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIRM -- ONE REPORT (ONE FINDING) AT A TIME
    |--------------------------------------------------------------------------
    | Some tests in a group turn around in a day, others take several days
    | (e.g. Biochemistry's Urea vs. a culture-dependent test) -- staff need
    | to lock and hand over whichever report is ready now without waiting
    | on the rest of the group. This sits alongside confirm() (the group
    | batch action) rather than replacing it; either path can set
    | confirmed_at on a finding, and isInvoicePathologyFullyConfirmed()
    | below doesn't care which one did it -- WhatsApp still only fires once
    | every Pathology line on the whole invoice is confirmed, exactly as
    | before.
    */

    public function confirmFinding($id, AuditService $auditService, WatiService $wati)
    {
        $finding = PathologyReportFinding::findOrFail($id);

        if ($finding->confirmed_at) {

            return response()->json([
                'status' => false,
                'message' => 'This report is already confirmed.'
            ]);
        }

        if (empty($finding->content)) {

            return response()->json([
                'status' => false,
                'message' => 'Cannot confirm -- enter report content first.'
            ]);
        }

        $finding->update([
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);

        $auditService->logAction(self::MODULE_CODE, $finding, 'CONFIRM', 'Pathology report confirmed and locked (individual item confirm)');

        $whatsappStatus = $this->isInvoicePathologyFullyConfirmed($finding->invoice_no)
            ? $this->autoSendInvoiceWhatsapp($finding->invoice_no, $wati, $auditService)
            : 'pending_other_reports';

        return response()->json([
            'status' => true,
            'message' => 'Report confirmed.',
            'data' => [
                'confirmed_finding_id' => $finding->id,
                'confirmed_at' => now()->format('d-m-Y H:i'),
                'whatsapp_status' => $whatsappStatus,
                'whatsapp_sent_at' => $whatsappStatus === 'sent' ? now()->format('d-m-Y H:i') : null,
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

        $pdf = $this->buildFindingPdf($finding);

        $auditService->logAction(self::MODULE_CODE, $finding, 'PRINT', 'Pathology report printed');

        return $pdf->stream($this->safeFileName($finding));
    }

    /**
     * The staff-print PDF of one finding, page-numbered -- shared with the
     * Test Report Dashboard's "print selected items" (which merges several).
     */
    public function buildFindingPdf(PathologyReportFinding $finding)
    {
        [$invoice, $itemDescriptions] = $this->loadReportContext($finding);

        $pdf = Pdf::loadView(
            'apps-pathology-report-pdf',
            compact('finding', 'invoice', 'itemDescriptions')
        );

        PdfPageNumbers::add($pdf);

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | PDF PRINT -- ONE TEST GROUP, COMBINED
    |--------------------------------------------------------------------------
    | Every confirmed report within a single test group (e.g. Haematology)
    | as one PDF. Usually there's exactly one report covering the whole
    | group already (a template picked up front), so this looks identical
    | to printReport() above -- it only visibly differs once a group was
    | completed in stages as several independent reports, which is exactly
    | the case that needed a single combined document instead of one print
    | per report.
    */

    public function printGroup(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'invoice_no' => 'required',
                'test_group_code' => 'nullable|integer',
            ]
        );

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $invoice = Invoice::where('invoice_no', trim($request->invoice_no))->first();

        if (!$invoice) {
            abort(404, 'Invoice not found.');
        }

        $testGroupCode = $request->filled('test_group_code') ? (int) $request->test_group_code : null;

        $lines = DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->leftJoin('pathology_report_finding_items as pfi', 'pfi.invoice_detail_id', '=', 'd.id')
            ->where('d.invoice_no', $invoice->invoice_no)
            ->where('d.item_code', self::ITEM_CODE)
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0)
            ->where(function ($q) {
                $q->where('iid.is_package', 0)
                    ->orWhereNotNull('pfi.pathology_report_finding_id');
            })
            ->where(function ($q) use ($testGroupCode) {
                $testGroupCode === null
                    ? $q->whereNull('iid.test_group_code')
                    : $q->where('iid.test_group_code', $testGroupCode);
            })
            ->orderBy('d.line_no')
            ->get(['d.id as invoice_detail_id', 'd.item_description', 'pfi.pathology_report_finding_id']);

        if ($lines->isEmpty()) {
            abort(404, 'No Pathology items found for this test group on this invoice.');
        }

        if ($lines->contains(fn ($l) => !$l->pathology_report_finding_id)) {
            abort(422, 'Not every test in this group has a report yet -- finish those first.');
        }

        $findings = PathologyReportFinding::with('items')
            ->whereIn('id', $lines->pluck('pathology_report_finding_id')->unique())
            ->orderBy('id')
            ->get();

        if ($findings->contains(fn ($f) => !$f->confirmed_at)) {
            abort(422, 'Not every report in this group is confirmed yet.');
        }

        $itemDescById = $lines->pluck('item_description', 'invoice_detail_id');

        $groupName = $testGroupCode
            ? DB::table('test_group_masters')->where('id', $testGroupCode)->value('test_group_name')
            : self::UNGROUPED_LABEL;

        $groupName = $groupName ?: self::UNGROUPED_LABEL;

        $sections = $findings->map(fn ($finding) => [
            'group_name' => $groupName,
            'title' => $finding->items->pluck('invoice_detail_id')
                ->map(fn ($id) => $itemDescById->get($id))
                ->filter()
                ->implode(', '),
            'content' => $finding->content,
            'confirmed_at' => $finding->confirmed_at,
        ]);

        $pdf = Pdf::loadView(
            'apps-pathology-report-pdf-group',
            compact('invoice', 'sections')
        );

        PdfPageNumbers::add($pdf);

        $auditService->logAction(
            self::MODULE_CODE,
            $invoice,
            'PRINT_GROUP',
            "Pathology group report printed ({$groupName})"
        );

        $fileName = str_replace(['/', '\\'], '-', $invoice->invoice_no . '-' . $groupName) . '-pathology-report.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | SEND WHATSAPP -- ONE MESSAGE FOR THE WHOLE INVOICE
    |--------------------------------------------------------------------------
    | Invoice-scoped, not per-report: a patient gets exactly one WhatsApp
    | message covering every confirmed Pathology report on the invoice,
    | sent automatically the moment the last one is confirmed (see
    | confirm() below). This endpoint is the manual retry for when that
    | auto-send failed (e.g. WATI was down) -- it re-sends the same
    | combined document, not a second/duplicate message, since
    | the SENT check in autoSendInvoiceWhatsapp() only blocks the
    | AUTOMATIC path, not an explicit manual retry.
    */

    public function sendWhatsapp(Request $request, AuditService $auditService, WatiService $wati)
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

        $invoiceNo = trim($request->invoice_no);

        if (!$this->isInvoicePathologyFullyConfirmed($invoiceNo)) {

            return response()->json([
                'status' => false,
                'message' => 'Not every Pathology report on this invoice is confirmed yet.'
            ]);
        }

        if ($blocked = ReportWhatsappDueGate::manualBlockMessage($invoiceNo)) {

            return response()->json(['status' => false, 'message' => $blocked]);
        }

        $sent = $this->sendCombinedInvoiceWhatsapp($invoiceNo, $wati, $auditService);

        return response()->json([
            'status' => $sent,
            'message' => $sent
                ? 'Report sent via WhatsApp successfully.'
                : 'Unable to send report via WhatsApp.',
            'data' => $sent ? ['sent_at' => now()->format('d-m-Y H:i')] : null,
        ], $sent ? 200 : 500);
    }

    /**
     * Every qualifying Pathology line on the invoice (mirrors search()'s own
     * PAT001/non-outsourced/non-package filter) must be claimed by a
     * finding, and every one of those findings confirmed -- staggered
     * completion means some lines can still be open while others are
     * already confirmed, so this has to check the WHOLE invoice, not just
     * whichever finding was just confirmed.
     */
    private function isInvoicePathologyFullyConfirmed(string $invoiceNo): bool
    {
        $lines = DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->leftJoin('pathology_report_finding_items as pfi', 'pfi.invoice_detail_id', '=', 'd.id')
            ->leftJoin('pathology_report_findings as pf', 'pf.id', '=', 'pfi.pathology_report_finding_id')
            ->where('d.invoice_no', $invoiceNo)
            ->where('d.item_code', self::ITEM_CODE)
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0)
            ->where(function ($q) {
                $q->where('iid.is_package', 0)
                    ->orWhereNotNull('pfi.pathology_report_finding_id');
            })
            ->get(['d.id', 'pfi.pathology_report_finding_id', 'pf.confirmed_at']);

        if ($lines->isEmpty()) {
            return false;
        }

        return $lines->every(fn ($l) => $l->pathology_report_finding_id && $l->confirmed_at);
    }

    private function autoSendInvoiceWhatsapp(string $invoiceNo, WatiService $wati, AuditService $auditService): string
    {
        // Idempotency guard for the AUTOMATIC path only -- confirm() calls
        // this every time a finding is confirmed, and once the invoice is
        // fully confirmed it stays fully confirmed, so without this a
        // second confirm() on an already-complete invoice (e.g. a race, or
        // a finding re-saved/re-confirmed) would send a duplicate message.
        // The manual sendWhatsapp() endpoint above is a deliberate retry
        // and intentionally does not check this.
        $alreadySent = DB::table('whatsapp_message_logs')
            ->where('invoice_no', $invoiceNo)
            ->where('message_type', 'PATHOLOGY_REPORT')
            ->where('status', 'SENT')
            ->exists();

        if ($alreadySent) {
            return 'already_sent';
        }

        if (!WhatsappAutoSendSetting::isEnabled('PATHOLOGY_REPORT')) {
            $invoice = Invoice::where('invoice_no', $invoiceNo)->first();
            if ($invoice) {
                WhatsappAutoSendSetting::logSkipped('PATHOLOGY_REPORT', $invoiceNo, $invoice->patient_mobile_no, $invoice->patient_name);
            }
            return 'skipped';
        }

        $invoice = Invoice::where('invoice_no', $invoiceNo)->first();

        if ($invoice && (float) $invoice->due_amount > 0) {
            ReportWhatsappDueGate::hold('PATHOLOGY_REPORT', $invoice);
            return 'held_due';
        }

        return $this->sendCombinedInvoiceWhatsapp($invoiceNo, $wati, $auditService) ? 'sent' : 'failed';
    }

    /**
     * Sends the combined report whose automatic WhatsApp was held for a
     * payment due, once the invoice is fully paid (see ReportWhatsappDueGate).
     */
    public function releaseHeldWhatsapp(string $invoiceNo): void
    {
        if (!ReportWhatsappDueGate::takeHeld($invoiceNo, 'PATHOLOGY_REPORT')) {
            return;
        }

        if ($this->isInvoicePathologyFullyConfirmed($invoiceNo)) {
            $this->sendCombinedInvoiceWhatsapp($invoiceNo, app(WatiService::class), app(AuditService::class));
        }
    }

    /**
     * Builds and sends ONE WhatsApp message covering every confirmed
     * Pathology report on the invoice, across every test group -- the
     * combined document is what actually gets attached, not any single
     * report on its own.
     */
    /**
     * Every confirmed Pathology report on the invoice, across every test
     * group, in the shape apps-pathology-report-pdf-whatsapp.blade.php
     * expects -- shared by the actual WhatsApp send AND printInvoice()
     * below, so "print all reports" always shows exactly the same document
     * that was (or will be) sent, not a second implementation that could
     * quietly drift from it.
     */
    public function buildInvoiceSections(string $invoiceNo, ?array $onlyFindingIds = null)
    {
        $lines = DB::table('invoice_details as d')
            ->join('invoice_item_details as iid', function ($join) {
                $join->on('iid.item_code', '=', 'd.item_code')
                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
            })
            ->leftJoin('pathology_report_finding_items as pfi', 'pfi.invoice_detail_id', '=', 'd.id')
            ->where('d.invoice_no', $invoiceNo)
            ->where('d.item_code', self::ITEM_CODE)
            ->where('iid.is_outsourced', 0)
            ->where('iid.is_report_not_required', 0)
            ->where(function ($q) {
                $q->where('iid.is_package', 0)
                    ->orWhereNotNull('pfi.pathology_report_finding_id');
            })
            ->get([
                'd.id as invoice_detail_id',
                'd.item_description',
                'iid.test_group_code',
                'pfi.pathology_report_finding_id',
            ]);

        $itemDescById = $lines->pluck('item_description', 'invoice_detail_id');
        $testGroupCodeByFindingId = $lines->pluck('test_group_code', 'pathology_report_finding_id');

        $findingIds = $lines->pluck('pathology_report_finding_id')->filter()->unique();

        if ($onlyFindingIds !== null) {
            $findingIds = $findingIds->intersect($onlyFindingIds);
        }

        $findings = PathologyReportFinding::with('items')
            ->whereIn('id', $findingIds)
            ->whereNotNull('confirmed_at')
            ->get();

        $testGroupNames = DB::table('test_group_masters')
            ->whereIn('id', $lines->pluck('test_group_code')->filter()->unique())
            ->pluck('test_group_name', 'id');

        $groupNameForFinding = fn ($findingId) => ($code = $testGroupCodeByFindingId->get($findingId))
            ? ($testGroupNames->get($code) ?: self::UNGROUPED_LABEL)
            : self::UNGROUPED_LABEL;

        return $findings
            ->sortBy(fn ($finding) => $groupNameForFinding($finding->id))
            ->values()
            ->map(fn ($finding) => [
                'group_name' => $groupNameForFinding($finding->id),
                'title' => $finding->items->pluck('invoice_detail_id')
                    ->map(fn ($id) => $itemDescById->get($id))
                    ->filter()
                    ->implode(', '),
                'content' => $finding->content,
                'confirmed_at' => $finding->confirmed_at,
            ]);
    }

    /**
     * Several confirmed reports as ONE document in the group layout: reports
     * of the same test group flow on continuously and a new page starts only
     * when the group changes. Used by the Test Report Dashboard's "print
     * selected items" so ticking e.g. three Haematology tests doesn't give
     * three separate documents.
     *
     * @param array<int,int> $findingIds
     */
    public function buildSectionedPdf(Invoice $invoice, array $findingIds)
    {
        $sections = $this->buildInvoiceSections($invoice->invoice_no, $findingIds);

        $pdf = Pdf::loadView(
            'apps-pathology-report-pdf-group',
            compact('invoice', 'sections')
        );

        PdfPageNumbers::add($pdf);

        return $pdf;
    }

    /*
    |--------------------------------------------------------------------------
    | PDF PRINT -- WHOLE INVOICE, COMBINED
    |--------------------------------------------------------------------------
    | "Print All Reports" -- every confirmed Pathology report across every
    | test group, combined into one document, same header/footer-less
    | letterhead layout as printGroup()/printReport() (the branded
    | header/footer-image layout stays reserved for the actual WhatsApp
    | send). Requires the whole invoice to be fully confirmed, same
    | precondition as the WhatsApp send, since the CONTENT still matches
    | what was/will be sent -- only the printed page style differs.
    |--------------------------------------------------------------------------
    */

    public function printInvoice(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            ['invoice_no' => 'required']
        );

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        $invoiceNo = trim($request->invoice_no);

        $invoice = Invoice::where('invoice_no', $invoiceNo)->first();

        if (!$invoice) {
            abort(404, 'Invoice not found.');
        }

        if (!$this->isInvoicePathologyFullyConfirmed($invoiceNo)) {
            abort(422, 'Not every Pathology report on this invoice is confirmed yet.');
        }

        $sections = $this->buildInvoiceSections($invoiceNo);

        // Header/footer-less, same plain letterhead layout as Print Group
        // Report -- the branded (header/footer image) layout stays
        // reserved for the actual WhatsApp send only.
        $pdf = Pdf::loadView(
            'apps-pathology-report-pdf-group',
            compact('invoice', 'sections')
        );

        PdfPageNumbers::add($pdf);

        $auditService->logAction(self::MODULE_CODE, $invoice, 'PRINT_ALL', 'All Pathology reports printed for this invoice');

        $fileName = str_replace(['/', '\\'], '-', $invoiceNo) . '-pathology-report-all.pdf';

        return $pdf->stream($fileName);
    }

    private function sendCombinedInvoiceWhatsapp(string $invoiceNo, WatiService $wati, AuditService $auditService): bool
    {
        try {

            $invoice = Invoice::where('invoice_no', $invoiceNo)->firstOrFail();

            $sections = $this->buildInvoiceSections($invoiceNo);

            $pdf = Pdf::loadView(
                'apps-pathology-report-pdf-whatsapp',
                compact('invoice', 'sections')
            );

            PdfPageNumbers::add($pdf);

            $fileName = str_replace(['/', '\\'], '-', $invoiceNo) . '-pathology-report.pdf';

            $pdfPath = public_path('invoices/' . $fileName);

            $password = PdfPasswordProtectionService::passwordForMobile($invoice->patient_mobile_no);

            file_put_contents(
                $pdfPath,
                $password
                    ? PdfPasswordProtectionService::protect($pdf->output(), $password)
                    : $pdf->output()
            );

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
                    ['name' => '3', 'value' => PdfPasswordProtectionService::invoiceNoWithHint($invoice->invoice_no, (bool) $password)],
                ]
            );

            $sent = is_array($watiResponse) && ($watiResponse['result'] ?? false) === true;

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $invoiceNo,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => 'PATHOLOGY_REPORT',
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => json_encode($watiResponse),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($sent) {

                $auditService->logAction(self::MODULE_CODE, $invoice, 'WHATSAPP', 'Combined Pathology report sent via WhatsApp for entire invoice' . ($password ? ' (password-protected)' : ' (unprotected: no valid mobile number)'));
            }

            return $sent;

        } catch (\Exception $e) {

            \Log::error('Pathology Combined Invoice WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
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
