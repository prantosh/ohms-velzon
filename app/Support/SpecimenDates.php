<?php

namespace App\Support;

/**
 * "Date of Receipt" / "Date of Report" -- a two-date line printed above the
 * content of the URINE C/S (NO GROWTH) pathology template. The dates are
 * entered on the report card and stored on the finding.
 */
class SpecimenDates
{
    /** Printed block (HTML) to put above the report content, or '' when neither date is set. */
    public static function html($receipt, $report): string
    {
        $fmt = function ($d) {
            if (!$d) {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($d)->format('d-m-Y');
            } catch (\Throwable $e) {
                return null;
            }
        };

        $receipt = $fmt($receipt);
        $report = $fmt($report);

        if (!$receipt && !$report) {
            return '';
        }

        return '<table style="width:100%; border-collapse:collapse; margin:0 0 8px 0; font-size:12px;"><tr>'
            . '<td style="width:50%; text-align:left;"><strong>Date of Receipt :</strong> ' . e($receipt ?: '-') . '</td>'
            . '<td style="width:50%; text-align:right;"><strong>Date of Report :</strong> ' . e($report ?: '-') . '</td>'
            . '</tr></table>';
    }

    public static function prepend($finding, string $content): string
    {
        return self::html($finding->receipt_date ?? null, $finding->report_date ?? null) . $content;
    }
}
