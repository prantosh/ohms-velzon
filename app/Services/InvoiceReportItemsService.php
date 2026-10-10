<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\TestReportConfirmation;
use App\Models\XrayReportUpload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-item report status for one invoice -- one row per qualifying billed
 * line, whichever module owns its report (Pathology, USG, Cardiology,
 * Non-Pathology, an uploaded X-Ray PDF, or the legacy whole-invoice
 * confirmation). Feeds the Test Report Dashboard's print modal: who prepared
 * and confirmed each item and when, and whether it may be printed.
 *
 * Mirrors DiagnosticResultStatusService's notion of "prepared" (a report row
 * exists for the line) and "confirmed" (that row's confirmed_at is set, or an
 * X-Ray PDF was uploaded, or the invoice carries the legacy confirmation), so
 * this screen and the status column can't disagree.
 */
class InvoiceReportItemsService
{
    public const KIND_PATHOLOGY = 'pathology';
    public const KIND_USG = 'usg';
    public const KIND_CARDIOLOGY = 'cardiology';
    public const KIND_NON_PATHOLOGY = 'non_pathology';
    public const KIND_XRAY_UPLOAD = 'xray_upload';
    public const KIND_LEGACY = 'legacy';

    private DiagnosticResultStatusService $statusService;

    public function __construct(DiagnosticResultStatusService $statusService)
    {
        $this->statusService = $statusService;
    }

    /**
     * Why an item can't be printed yet (null when it can) -- shown in the
     * Test Report Dashboard's print window so the status is self-explanatory.
     */
    private function reasonFor(string $kind, bool $prepared, bool $confirmed, bool $fileMissing): ?string
    {
        if ($fileMissing) {
            return 'The uploaded X-Ray PDF file is missing. Upload it again on the Upload X-Ray Report screen.';
        }

        if ($confirmed) {
            return null;
        }

        $screen = [
            self::KIND_PATHOLOGY => 'Pathology Test Result Entry',
            self::KIND_USG => 'USG Report',
            self::KIND_CARDIOLOGY => 'Cardiology (Echo) Findings Entry',
            self::KIND_NON_PATHOLOGY => 'Rest Test Result Entry',
            self::KIND_XRAY_UPLOAD => 'Upload X-Ray Report',
            self::KIND_LEGACY => 'Test Result Entry',
        ][$kind] ?? 'the report entry screen';

        return $prepared
            ? 'Report prepared but not confirmed yet. Confirm it in ' . $screen . ' to print.'
            : 'No report prepared yet. Prepare and confirm it in ' . $screen . '.';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forInvoice(Invoice $invoice): array
    {
        $lines = $this->statusService->qualifyingLinesQuery($invoice)
            ->orderBy('d.line_no')
            ->get(['d.id as invoice_detail_id', 'd.item_code', 'd.item_code_sub', 'd.item_description']);

        if ($lines->isEmpty()) {
            return [];
        }

        $ids = $lines->pluck('invoice_detail_id');

        $legacy = TestReportConfirmation::where('invoice_no', $invoice->invoice_no)->first();

        $legacyPrepared = $legacy
            ? DB::table('test_result_entries')
                ->whereIn('invoice_detail_id', $ids)
                ->selectRaw('invoice_detail_id, MIN(created_at) as created_at, MIN(created_by) as created_by')
                ->groupBy('invoice_detail_id')
                ->get()
                ->keyBy('invoice_detail_id')
            : collect();

        $pathology = DB::table('pathology_report_finding_items as pfi')
            ->join('pathology_report_findings as pf', 'pf.id', '=', 'pfi.pathology_report_finding_id')
            ->whereIn('pfi.invoice_detail_id', $ids)
            ->get(['pfi.invoice_detail_id', 'pf.id as finding_id', 'pf.created_by', 'pf.created_at', 'pf.confirmed_by', 'pf.confirmed_at'])
            ->keyBy('invoice_detail_id');

        $perLine = [];

        foreach ([
            self::KIND_USG => 'usg_report_findings',
            self::KIND_CARDIOLOGY => 'cardiology_report_findings',
            self::KIND_NON_PATHOLOGY => 'non_pathology_report_findings',
        ] as $kind => $table) {

            $perLine[$kind] = DB::table($table)
                ->whereIn('invoice_detail_id', $ids)
                ->get(['invoice_detail_id', 'id as finding_id', 'created_by', 'created_at', 'confirmed_by', 'confirmed_at'])
                ->keyBy('invoice_detail_id');
        }

        $upload = XrayReportUpload::where('invoice_no', $invoice->invoice_no)->first();

        $userIds = collect()
            ->merge($pathology->pluck('created_by'))->merge($pathology->pluck('confirmed_by'))
            ->merge($legacyPrepared->pluck('created_by'));

        foreach ($perLine as $rows) {
            $userIds = $userIds->merge($rows->pluck('created_by'))->merge($rows->pluck('confirmed_by'));
        }

        if ($legacy) {
            $userIds->push($legacy->confirmed_by);
        }

        if ($upload) {
            $userIds->push($upload->uploaded_by);
        }

        $names = DB::table('users')->whereIn('id', $userIds->filter()->unique())->pluck('name', 'id');

        $when = fn ($value) => $value ? Carbon::parse($value)->format('d-m-Y H:i') : null;

        return $lines->map(function ($line) use ($legacy, $legacyPrepared, $pathology, $perLine, $upload, $names, $when) {

            $kind = null;
            $row = null;

            if ($legacy) {

                $kind = self::KIND_LEGACY;
                $prep = $legacyPrepared->get($line->invoice_detail_id);

                $row = (object) [
                    'finding_id' => null,
                    'created_by' => $prep->created_by ?? null,
                    'created_at' => $prep->created_at ?? null,
                    'confirmed_by' => $legacy->confirmed_by,
                    'confirmed_at' => $legacy->confirmed_at,
                ];

            } elseif ($line->item_code === 'PAT001') {

                $kind = self::KIND_PATHOLOGY;
                $row = $pathology->get($line->invoice_detail_id);

            } elseif ($line->item_code === 'USG001') {

                $kind = self::KIND_USG;
                $row = $perLine[self::KIND_USG]->get($line->invoice_detail_id);

            } elseif ($line->item_code === 'CRD001') {

                $kind = self::KIND_CARDIOLOGY;
                $row = $perLine[self::KIND_CARDIOLOGY]->get($line->invoice_detail_id);

            } elseif ($line->item_code === XrayReportUpload::ITEM_CODE && $upload) {

                // The uploaded PDF is both the preparation and the sign-off.
                $kind = self::KIND_XRAY_UPLOAD;

                $row = (object) [
                    'finding_id' => null,
                    'created_by' => $upload->uploaded_by,
                    'created_at' => $upload->created_at,
                    'confirmed_by' => $upload->uploaded_by,
                    'confirmed_at' => $upload->updated_at,
                ];

            } else {

                $kind = self::KIND_NON_PATHOLOGY;
                $row = $perLine[self::KIND_NON_PATHOLOGY]->get($line->invoice_detail_id);
            }

            $prepared = (bool) $row;
            $confirmed = $row && $row->confirmed_at;

            $fileMissing = $kind === self::KIND_XRAY_UPLOAD && !$upload->fileExists();

            $canPrint = (bool) $confirmed && !$fileMissing;

            return [
                'invoice_detail_id' => $line->invoice_detail_id,
                'item_description' => $line->item_description,
                'kind' => $kind,
                'finding_id' => $row->finding_id ?? null,
                'prepared' => $prepared,
                'prepared_by' => $prepared ? ($names->get($row->created_by) ?? '-') : null,
                'prepared_at' => $prepared ? $when($row->created_at) : null,
                'confirmed' => (bool) $confirmed,
                'confirmed_by' => $confirmed ? ($names->get($row->confirmed_by) ?? '-') : null,
                'confirmed_at' => $confirmed ? $when($row->confirmed_at) : null,
                'can_print' => $canPrint,
                'file_missing' => $fileMissing,
                'reason' => $this->reasonFor($kind, $prepared, (bool) $confirmed, $fileMissing),
            ];
        })->values()->all();
    }
}
