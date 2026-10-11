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
     * Same answer as owesDelivery() below, read from the values stored on the
     * invoice (report_total_tests = in-house lines, report_outsourced_tests =
     * outside-lab lines, plus the two delivered_at columns) instead of nested
     * EXISTS subqueries -- roughly 5-20x cheaper per query, and the dashboard
     * runs it twice (page + count). Callers must first make sure the invoices
     * in the query have those values stored
     * (InvoiceReportStatusRecorder::fillMissing()); owesDelivery() stays as the
     * from-scratch reference this is verified against.
     */
    public static function owesDeliveryStored(Builder $query, bool $negate = false): Builder
    {
        $condition = function ($q) {
            $q->where(function ($inHouse) {
                $inHouse->whereNull('report_delivered_at')->where('report_total_tests', '>', 0);
            })->orWhere(function ($outsourced) {
                $outsourced->whereNull('outsourced_report_delivered_at')->where('report_outsourced_tests', '>', 0);
            });
        };

        return $negate ? $query->whereNot($condition) : $query->where($condition);
    }

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
                            ->whereRaw(\App\Support\ReportableLines::sql())
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
