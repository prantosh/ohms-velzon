<?php

namespace App\Services;

use App\Models\PathologyReportFinding;
use Illuminate\Support\Facades\DB;

/**
 * "Key parameter" history -- a curated subset of pathology results (FBS,
 * PPBS, Creatinine, Lipid/LFT components, electrolytes, Hb/RBC/Platelet,
 * Vitamin D, ...) captured in structured form alongside the normal
 * free-text report, so a returning patient's past values can be shown/
 * printed on their next report. See the create_key_parameter_history_tables
 * migration's docblock for the full data model.
 *
 * Display/print policy: at most the last 3 results, within the last 12
 * months -- enforced here at query time, not by deleting anything.
 */
class KeyParameterService
{
    private const RECENT_LIMIT = 3;
    private const MAX_AGE_MONTHS = 12;

    /**
     * Which key parameters apply to this set of billed item_code_subs,
     * grouped by group_label -- e.g. billing PAT176 (LFT) returns all six
     * LFT sub-parameters even though only PAT176 itself was billed.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection>
     *     keyed by group_label, each a collection of key_parameters rows
     */
    public function triggeredParametersForItemCodeSubs(array $billedItemCodeSubs): \Illuminate\Support\Collection
    {
        if (empty($billedItemCodeSubs)) {
            return collect();
        }

        return DB::table('key_parameters')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->filter(function ($param) use ($billedItemCodeSubs) {
                $triggers = json_decode($param->trigger_item_code_subs, true) ?: [];
                return !empty(array_intersect($triggers, $billedItemCodeSubs));
            })
            ->groupBy('group_label');
    }

    /**
     * A patient's recent history for a set of parameters -- e.g. for
     * pre-filling the "Patient History" panel when composing a report.
     *
     * @return array<string, array> item_code_sub => list of
     *     {result_value, uom, reference_range, test_date, invoice_no},
     *     most recent first, max 3 entries within the last 12 months
     */
    public function recentValuesForPatient(?string $patientId, array $itemCodeSubs, ?string $excludeInvoiceNo = null): array
    {
        if (!$patientId || empty($itemCodeSubs)) {
            return [];
        }

        $cutoff = now()->subMonths(self::MAX_AGE_MONTHS)->toDateString();

        $rows = DB::table('patient_key_parameter_values')
            ->where('patient_id', $patientId)
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->where('test_date', '>=', $cutoff)
            ->when($excludeInvoiceNo, fn ($q) => $q->where('invoice_no', '!=', $excludeInvoiceNo))
            ->orderByDesc('test_date')
            ->orderByDesc('id')
            ->get();

        $byParam = [];

        foreach ($rows as $row) {

            $byParam[$row->item_code_sub] ??= [];

            if (count($byParam[$row->item_code_sub]) >= self::RECENT_LIMIT) {
                continue;
            }

            $byParam[$row->item_code_sub][] = [
                'result_value' => $row->result_value,
                'uom' => $row->uom,
                'reference_range' => $row->reference_range,
                'test_date' => $row->test_date,
                'test_date_fmt' => \Carbon\Carbon::parse($row->test_date)->format('d-m-Y'),
                'invoice_no' => $row->invoice_no,
            ];
        }

        return $byParam;
    }

    /**
     * Promotes a finding's draft key_parameter_values JSON into the
     * permanent history table -- called once, at confirm time (not on
     * every intermediate save, since the draft can still change until
     * then). Safe to call on a finding with no key-parameter draft data at
     * all (most reports have none of the tracked parameters) -- it's then
     * a no-op.
     */
    public function recordConfirmedValues(PathologyReportFinding $finding): void
    {
        $values = $finding->key_parameter_values;

        if (empty($values) || !is_array($values)) {
            return;
        }

        $invoice = DB::table('invoices')->where('invoice_no', $finding->invoice_no)->first();

        if (!$invoice || !$invoice->patient_id) {
            return;
        }

        $itemCodeSubs = array_keys($values);

        $paramMeta = DB::table('key_parameters')
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->get()
            ->keyBy('item_code_sub');

        // The test master's own range_male/range_female/range_common --
        // gender-adjusted the same way the invoice/report cards show it,
        // so a past value can be judged in/out of range later too.
        $itemMaster = DB::table('invoice_item_details')
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->get()
            ->keyBy('item_code_sub');

        $isFemale = strtolower((string) $invoice->patient_gender) === 'female';

        $testDate = $invoice->test_date ?: $invoice->invoice_date;

        $rows = [];

        foreach ($values as $itemCodeSub => $entry) {

            $resultValue = is_array($entry) ? ($entry['value'] ?? null) : $entry;

            if ($resultValue === null || trim((string) $resultValue) === '') {
                continue;
            }

            $meta = $paramMeta->get($itemCodeSub);
            $item = $itemMaster->get($itemCodeSub);

            $range = $item
                ? (($isFemale ? $item->range_female : $item->range_male) ?: $item->range_common)
                : null;

            $rows[] = [
                'patient_id' => $invoice->patient_id,
                'item_code_sub' => $itemCodeSub,
                'parameter_name' => $meta->parameter_name ?? $itemCodeSub,
                'result_value' => trim((string) $resultValue),
                'uom' => $meta->uom ?? ($item->uom ?? null),
                'reference_range' => $range,
                'invoice_no' => $finding->invoice_no,
                'pathology_report_finding_id' => $finding->id,
                'test_date' => $testDate,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (!empty($rows)) {
            DB::table('patient_key_parameter_values')->insert($rows);
        }
    }
}
