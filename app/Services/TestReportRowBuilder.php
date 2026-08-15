<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\TestResultEntry;
use App\Models\TestResultExtraValue;
use Illuminate\Support\Facades\DB;

class TestReportRowBuilder
{
    /**
     * Build one row per qualifying billed line on the invoice, nesting
     * analyte sub-rows (when the billed test has a configured analyte
     * breakdown) and any saved extra parameter values.
     */
    public function buildRows(Invoice $invoice): array
    {
        $details = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->get();

        $qualifyingItemCodes = InvoiceItemMaster::where(
            'test_parameter_required',
            'YES'
        )->pluck('item_code')->toArray();

        [$packageMasterSubCodes, $componentToPackageName] = $this->resolvePackageGrouping(
            $details->pluck('item_code_sub')->all()
        );

        $rows = [];

        foreach ($details as $detail) {

            if (!in_array($detail->item_code, $qualifyingItemCodes)) {
                continue;
            }

            // A package's own billed line (e.g. LIPID PROFILE) is a
            // pricing label, not a measurable test -- it has no result of
            // its own, so it's excluded from Test Result Entry and from
            // the report as a row. Its name instead becomes the group
            // heading over its components (see package_group_name below).
            if (isset($packageMasterSubCodes[$detail->item_code_sub])) {
                continue;
            }

            $itemDetail = InvoiceItemDetail::where('item_code', $detail->item_code)
                ->where('item_code_sub', $detail->item_code_sub)
                ->first();

            // Physically performed and reported by an outside agency --
            // this system has no report to produce for it, so it's skipped
            // entirely rather than shown as an empty/unusable row.
            if ($itemDetail && $itemDetail->is_outsourced) {
                continue;
            }

            $analytes = $itemDetail ? $itemDetail->analytes()->get() : collect();

            $groupToSubGroup = [];

            if ($itemDetail) {
                foreach ($itemDetail->subGroups as $subGroup) {
                    foreach ($subGroup->members as $member) {
                        $groupToSubGroup[$member->group_name] = $subGroup->name;
                    }
                }
            }

            $extraValues = TestResultExtraValue::where('invoice_detail_id', $detail->id)
                ->with('fieldType')
                ->get()
                ->filter(fn ($value) => $value->fieldType !== null)
                ->map(fn ($value) => [
                    'id' => $value->id,
                    'field_type_id' => $value->test_extra_field_type_id,
                    'field_name' => $value->fieldType->field_name,
                    'input_type' => $value->fieldType->input_type,
                    'value' => $value->value,
                ])
                ->values()
                ->all();

            // The main billed test always keeps its own result slot
            // (analyte_id = 0), independent of whether it also has a
            // configured analyte breakdown below it — some tests need
            // both (e.g. an overall result/interpretation plus individual
            // component results).
            $existing = TestResultEntry::where('invoice_detail_id', $detail->id)
                ->where('analyte_id', 0)
                ->first();

            $row = [
                'invoice_detail_id' => $detail->id,
                'item_code' => $detail->item_code,
                'item_code_sub' => $detail->item_code_sub,
                'item_description' => $detail->item_description,
                'test_group_code' => $itemDetail->test_group_code ?? null,
                'test_group_name' => $itemDetail?->testGroup?->test_group_name,
                'has_analytes' => $analytes->isNotEmpty(),
                'uom' => $itemDetail->uom ?? null,
                'range_male' => $itemDetail->range_male ?? null,
                'range_female' => $itemDetail->range_female ?? null,
                'range_common' => $itemDetail->range_common ?? null,
                'method' => $itemDetail->method ?? null,
                'result_id' => $existing->id ?? null,
                'result_value' => $existing->result_value ?? null,
                'remarks' => $existing->remarks ?? null,
                'package_group_name' => $componentToPackageName[$detail->item_code_sub] ?? null,
                'analytes' => [],
                'extra_values' => $extraValues,
            ];

            if ($analytes->isNotEmpty()) {

                $row['analytes'] = $analytes->map(function ($analyte) use ($detail, $groupToSubGroup) {

                    $existingAnalyte = TestResultEntry::where('invoice_detail_id', $detail->id)
                        ->where('analyte_id', $analyte->id)
                        ->first();

                    return [
                        'analyte_id' => $analyte->id,
                        'analyte_name' => $analyte->analyte_name,
                        'group_name' => $analyte->group_name,
                        'sub_group_name' => $groupToSubGroup[$analyte->group_name] ?? null,
                        'uom' => $analyte->uom,
                        'range_male' => $analyte->range_male,
                        'range_female' => $analyte->range_female,
                        'range_common' => $analyte->range_common,
                        'method' => $analyte->method,
                        'result_id' => $existingAnalyte->id ?? null,
                        'result_value' => $existingAnalyte->result_value ?? null,
                        'remarks' => $existingAnalyte->remarks ?? null,
                    ];
                })->values()->all();
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * For every billed item_code_sub on this invoice that is itself a
     * package (e.g. LIPID PROFILE), resolve its configured components
     * (also by item_code_sub) so buildRows() can exclude the package's own
     * row and instead label its components with the package's name.
     *
     * @return array{0: array<string, true>, 1: array<string, string>}
     *     [0] package item_code_sub => true (rows to exclude)
     *     [1] component item_code_sub => package display name
     */
    private function resolvePackageGrouping(array $billedSubCodes): array
    {
        $packageDetails = InvoiceItemDetail::whereIn('item_code_sub', $billedSubCodes)
            ->where('is_package', true)
            ->get(['id', 'item_code_sub', 'item_description_sub']);

        $packageMasterSubCodes = [];
        $componentToPackageName = [];

        foreach ($packageDetails as $packageDetail) {

            $packageMasterSubCodes[$packageDetail->item_code_sub] = true;

            $componentSubCodes = DB::table('test_package_components')
                ->join(
                    'invoice_item_details',
                    'invoice_item_details.id',
                    '=',
                    'test_package_components.component_invoice_item_detail_id'
                )
                ->where('test_package_components.package_invoice_item_detail_id', $packageDetail->id)
                ->where('test_package_components.status', 'ACTIVE')
                ->pluck('invoice_item_details.item_code_sub');

            foreach ($componentSubCodes as $subCode) {
                $componentToPackageName[$subCode] = $packageDetail->item_description_sub;
            }
        }

        return [$packageMasterSubCodes, $componentToPackageName];
    }
}
