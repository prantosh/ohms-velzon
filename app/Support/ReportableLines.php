<?php

namespace App\Support;

/**
 * The single definition of "is this billed line something a report is
 * written for" -- shared by every query that lists/counts/prints reportable
 * lines (Pathology report entry, print, WhatsApp sections, the status
 * service, the dashboard and delivery filters) so they can never disagree.
 *
 * Packages (e.g. LIPID PROFILE = one flat-rate line + hidden zero-rate
 * component lines tagged with package_parent_item_code_sub) are reported at
 * PACKAGE level: the package line is the reportable item and its components
 * are not. Invoices that were already reported the old way keep working:
 *
 *   ordinary test ........ reportable (not a package line, not a component)
 *   package line ......... reportable when a report is already attached to
 *                          it, or when it has tagged components and none of
 *                          them has a report yet (the new, package-level way)
 *   component line ...... NOT reportable -- unless a report is already
 *                          attached to it, or the package was reported the
 *                          old way (package line unreported while a sibling
 *                          component is reported), so its remaining real
 *                          tests stay reportable to finish that package. A
 *                          package nested inside a package is only a price
 *                          line and never reportable unless already linked.
 *   package line with no tagged components (invoices from before components
 *                          were tagged) stays unreportable, as before
 *
 * Only Pathology (item_code PAT001) has report-attachment tracking, so every
 * other category keeps the original rule: a line counts unless it is a
 * package pricing line.
 */
class ReportableLines
{
    /**
     * SQL condition for a query over invoice_details and its item master row.
     *
     * @param string $d   alias of invoice_details
     * @param string $iid alias of invoice_item_details
     */
    public static function sql(string $d = 'd', string $iid = 'iid'): string
    {
        $linked = "EXISTS (SELECT 1 FROM pathology_report_finding_items rl_x WHERE rl_x.invoice_detail_id = {$d}.id)";

        return "(
            ({$d}.item_code <> 'PAT001' AND {$iid}.is_package = 0)
            OR ({$d}.item_code = 'PAT001' AND (
                ({$iid}.is_package = 0 AND {$d}.package_parent_item_code_sub IS NULL)
                OR ({$d}.package_parent_item_code_sub IS NOT NULL AND (
                    {$linked}
                    OR (
                        {$iid}.is_package = 0
                        AND NOT EXISTS (
                            SELECT 1 FROM invoice_details rl_p
                            JOIN pathology_report_finding_items rl_px ON rl_px.invoice_detail_id = rl_p.id
                            WHERE rl_p.invoice_no = {$d}.invoice_no
                              AND rl_p.item_code_sub = {$d}.package_parent_item_code_sub
                              AND rl_p.package_parent_item_code_sub IS NULL
                        )
                        AND EXISTS (
                            SELECT 1 FROM invoice_details rl_s
                            JOIN pathology_report_finding_items rl_sx ON rl_sx.invoice_detail_id = rl_s.id
                            WHERE rl_s.invoice_no = {$d}.invoice_no
                              AND rl_s.package_parent_item_code_sub = {$d}.package_parent_item_code_sub
                        )
                    )
                ))
                OR ({$iid}.is_package = 1 AND (
                    {$linked}
                    OR (
                        {$d}.package_parent_item_code_sub IS NULL
                        AND EXISTS (
                            SELECT 1 FROM invoice_details rl_c
                            WHERE rl_c.invoice_no = {$d}.invoice_no
                              AND rl_c.package_parent_item_code_sub = {$d}.item_code_sub
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM invoice_details rl_c2
                            JOIN pathology_report_finding_items rl_cx ON rl_cx.invoice_detail_id = rl_c2.id
                            WHERE rl_c2.invoice_no = {$d}.invoice_no
                              AND rl_c2.package_parent_item_code_sub = {$d}.item_code_sub
                        )
                    )
                ))
            ))
        )";
    }
}
