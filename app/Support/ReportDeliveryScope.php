<?php

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single definition of "this diagnostic invoice still owes a report
 * delivery", shared by the Test Report Delivery Log's Undelivered tab and
 * the Test Report Dashboard's Delivered / Not Delivered filter so the two
 * screens can never disagree.
 *
 * Still owes a delivery on at least one front: has in-house lines and isn't
 * in-house-delivered yet, OR has outsourced lines and isn't
 * outsourced-delivered yet. An invoice whose in-house side is done but has
 * no outsourced lines at all (or vice versa) must NOT keep reappearing as
 * undelivered just because the other column is permanently N/A -- each half
 * of the OR is gated by its own EXISTS so a not-applicable side never
 * counts as "still pending".
 */
class ReportDeliveryScope
{
    /**
     * Adds the "owes a delivery" condition to an invoices query as one
     * grouped where. Pass $negate to get invoices that owe nothing, i.e.
     * fully delivered.
     */
    public static function owesDelivery(Builder $query, bool $negate = false): Builder
    {
        $condition = function ($q) {

            $q->where(function ($inHouse) {
                $inHouse->whereNull('report_delivered_at')
                    ->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('invoice_details as d')
                            ->join('invoice_item_details as iid', function ($join) {
                                $join->on('iid.item_code', '=', 'd.item_code')
                                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
                            })
                            ->whereColumn('d.invoice_no', 'invoices.invoice_no')
                            ->where(function ($reportable) {
                                $reportable->where('iid.is_package', 0)
                                    ->orWhereExists(function ($finding) {
                                        $finding->selectRaw('1')
                                            ->from('pathology_report_finding_items as pfi')
                                            ->whereColumn('pfi.invoice_detail_id', 'd.id')
                                            ->where('d.item_code', 'PAT001');
                                    });
                            })
                            ->where('iid.is_outsourced', 0)
                            ->where('iid.is_report_not_required', 0);
                    });
            })->orWhere(function ($outsourced) {
                $outsourced->whereNull('outsourced_report_delivered_at')
                    ->whereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('invoice_details as d')
                            ->join('invoice_item_details as iid', function ($join) {
                                $join->on('iid.item_code', '=', 'd.item_code')
                                    ->on('iid.item_code_sub', '=', 'd.item_code_sub');
                            })
                            ->whereColumn('d.invoice_no', 'invoices.invoice_no')
                            ->where('iid.is_package', 0)
                            ->where('iid.is_outsourced', 1)
                            ->where('iid.is_report_not_required', 0);
                    });
            });
        };

        return $negate ? $query->whereNot($condition) : $query->where($condition);
    }
}
