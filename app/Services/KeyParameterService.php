<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PathologyReportFinding;
use App\Support\ReportTestDates;
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

        // The test master's own range_male/range_female/range_common.
        $itemMaster = DB::table('invoice_item_details')
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->get()
            ->keyBy('item_code_sub');

        $testDate = $invoice->test_date ?: $invoice->invoice_date;

        $rows = [];

        foreach ($values as $itemCodeSub => $entry) {

            $resultValue = is_array($entry) ? ($entry['value'] ?? null) : $entry;

            if ($resultValue === null || trim((string) $resultValue) === '') {
                continue;
            }

            $meta = $paramMeta->get($itemCodeSub);
            $item = $itemMaster->get($itemCodeSub);

            $range = implode('; ', $this->combinedRangeParts($item)) ?: null;

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

    /**
     * Every tracked parameter captured on this finding, keyed by
     * item_code_sub, with or without prior history -- unlike
     * recentValuesForPatient() alone, nothing is dropped just because it
     * has no history yet (a first-time tracked value still needs to show
     * in the Current column). Used only for the print-time table
     * expansion below; never persisted.
     *
     * $finding is duck-typed (object, not PathologyReportFinding) on
     * purpose -- only ->key_parameter_values is ever read off it, and
     * PathologyReportController::preview() calls this with a plain
     * stdClass draft (no real saved finding exists yet at preview time),
     * the same pattern used for every other unsaved-draft preview in this
     * codebase.
     *
     * @return array<string, array{uom: ?string, current: ?string, history: array}>
     */
    public function tableDataFor(object $finding, Invoice $invoice): array
    {
        $current = $finding->key_parameter_values;

        if (empty($current)) {
            return [];
        }

        $itemCodeSubs = array_keys($current);

        $paramMeta = DB::table('key_parameters')
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->get()
            ->keyBy('item_code_sub');

        // Combined range -- see combinedRangeHtml() -- needed here as
        // ready-to-print HTML so buildAutoTable() doesn't have to re-derive
        // it separately.
        $itemMaster = DB::table('invoice_item_details')
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->get()
            ->keyBy('item_code_sub');

        $history = $this->recentValuesForPatient($invoice->patient_id, $itemCodeSubs, $invoice->invoice_no);

        // This item's own billed line on THIS invoice, if it has one of its
        // own -- else the invoice's own date (same fallback ReportTestDates
        // uses). Lets buildAutoTable() flag a row whose test was actually
        // done on a different day than the report's header date.
        $lineDates = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->pluck('test_date', 'item_code_sub');

        $invoiceDate = ReportTestDates::clean($invoice->test_date ?? null);

        $data = [];

        foreach ($itemCodeSubs as $code) {

            $value = $current[$code] ?? null;
            $value = is_array($value) ? ($value['value'] ?? null) : $value;

            $meta = $paramMeta->get($code);
            $item = $itemMaster->get($code);

            $range = $this->combinedRangeHtml($item);

            $data[$code] = [
                'parameter_name' => $meta->parameter_name ?? ($item->item_description_sub ?? $code),
                'uom' => $meta->uom ?? null,
                'range' => $range,
                'method' => $item->method ?? null,
                'sort_order' => $meta->sort_order ?? 0,
                'current' => $value,
                'history' => $history[$code] ?? [],
                'test_date' => ReportTestDates::clean($lineDates->get($code)) ?? $invoiceDate,
            ];
        }

        return $data;
    }

    /**
     * A full result table built entirely from tracked data -- no admin-
     * authored table to expand at all. For a template whose table has been
     * stripped down to just a placeholder (see
     * ReportListSpacing-style one-off template edits, or new templates
     * authored from scratch for a fully-tracked item/panel): the
     * Investigation name, Unit and Reference Range that used to be typed
     * by hand are pulled from key_parameters/invoice_item_details instead,
     * so there's nothing left for an admin to keep in sync by hand, and no
     * marker that can silently go missing (the failure mode that caused
     * TC/HDL to not show up at all until this was built). Rows are ordered
     * by key_parameters.sort_order. Returns '' when there's nothing
     * tracked on this finding yet, so the placeholder can be dropped
     * cleanly instead of leaving a stray empty table.
     *
     * History (the previous-test-dates columns) is deliberately not
     * printed for now -- $tableData's 'history' entries are simply
     * ignored here -- pending a later redesign of how that's shown;
     * tableDataFor() still computes it so nothing upstream needs to
     * change when it comes back.
     *
     * $headerDateRaw (Y-m-d, see ReportTestDates::describe()'s
     * header_date_raw) is the date already printed in the report's own
     * "Test Date :" line. A row whose own test_date differs from it (a
     * panel whose components were actually done on different days) gets
     * "(Test Done : dd/mm/yyyy)" printed beside its own result instead of
     * silently sharing a header date that isn't actually its own.
     */
    public function buildAutoTable(array $tableData, ?string $headerDateRaw = null): string
    {
        if (empty($tableData)) {
            return '';
        }

        uasort($tableData, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

        // Lets a long, otherwise-unbreakable run of text (e.g. a Reference
        // Range description) actually wrap inside its own cell instead of
        // forcing the column -- and with it, the whole table -- wider to
        // fit it.
        $wordWrap = 'word-wrap:break-word;overflow-wrap:break-word;';

        // Light tint (not the data cells' own #F7FAFC, so the header still
        // reads as a distinct band above them) with a solid navy bottom
        // rule, bold black text sized bigger than the body content below
        // it (8px) so it reads as a clear heading rather than just another
        // row.
        $headerCellStyle = 'background-color:#E3EBF3;border-color:#B8C8D4;border-width:1pt;border-bottom:1.4pt solid #1F4E78;padding:4.5pt 6pt;font-size:9.5px;' . $wordWrap;
        $contentFontSize = 'font-size:8px;';
        // Percentages, not absolute inches -- paired with the table's own
        // width:100% below, this is what makes the table span exactly the
        // same left-to-right extent as every other table in this report
        // (the base `table { width:100%; }` rule in this file's own
        // <style>), instead of sitting narrower and left-aligned with a
        // visible gap only on its right that nothing else on the page has.
        // Mixing absolute cell widths with a 100%-wide table was what
        // previously made DomPDF silently rescale these ratios -- purely
        // relative units on both the table and its cells is the case
        // table-layout:fixed actually honours correctly.
        $investigationWidth = 'width:30%;';
        $unitWidth = 'width:15%;';
        $resultValueWidth = 'width:15%;';
        $rangeWidth = 'width:40%;';

        $makeHeaderCell = function (string $text, string $widthStyle) use ($headerCellStyle) {
            $td = '<td style="' . $headerCellStyle . $widthStyle . '">';
            $td .= '<p style="text-align:center;"><span style="color:#000000;letter-spacing:0.4px;"><strong>' . e($text) . '</strong></span></p>';
            return $td . '</td>';
        };

        // table-layout:fixed is required for the percentage widths above to
        // actually hold -- without it, DomPDF's content-driven sizing wins
        // anyway, widening Investigation past its 30% to fit its text +
        // Method line and pushing the whole table past the page's right
        // margin.
        $html = '<table style="border-width:0px;table-layout:fixed;width:100%;"><tbody><tr>';
        $html .= $makeHeaderCell('INVESTIGATION', $investigationWidth);
        $html .= $makeHeaderCell('UNIT', $unitWidth);
        $html .= $makeHeaderCell('RESULT', $resultValueWidth);
        $html .= $makeHeaderCell('REFERENCE RANGE', $rangeWidth);
        $html .= '</tr>';

        $dataCellBorder = 'border-color:#B8C8D4;border-width:1pt;';

        $makeDataCell = function (string $text, string $widthStyle, bool $bold = false, bool $shaded = true) use ($wordWrap, $contentFontSize, $dataCellBorder) {
            $style = $dataCellBorder . ($shaded ? 'background-color:#F7FAFC;' : '') . 'padding:4.5pt 6pt;' . $widthStyle . ($bold ? 'font-weight:bold;' : '') . $wordWrap . $contentFontSize;
            return '<td style="' . $style . '"><p style="text-align:center;">' . e($text) . '</p></td>';
        };

        foreach ($tableData as $info) {

            // Method prints right under the investigation name, same spot
            // and convention admin-authored templates already use for it
            // (e.g. Creatinine's own template: "Creatinine" / "(Enzymatic
            // method)" in a muted, smaller line below) -- just a plain
            // sibling <p>, not bold like the name itself.
            $methodLine = !empty($info['method'])
                ? '<p style="margin:0;font-size:7.5px;color:#5B6570;font-weight:normal;"><strong>Method :</strong> ' . e($info['method']) . '</p>'
                : '';

            $html .= '<tr>';
            $html .= '<td style="border-color:#B8C8D4;border-width:1pt;padding:4.5pt 6pt;' . $investigationWidth . $wordWrap . $contentFontSize . '">'
                . '<p style="margin:0;font-weight:bold;color:#222222;">' . e($info['parameter_name']) . '</p>'
                . $methodLine . '</td>';
            $html .= $makeDataCell((string) ($info['uom'] ?: '-'), $unitWidth);

            $resultText = e((string) ($info['current'] !== null && $info['current'] !== '' ? $info['current'] : '-'));

            // Only when this row's own date is actually known and differs
            // from the header's -- a row with no date of its own (e.g. a
            // value entered with nothing in invoice_details.test_date and
            // no invoice test_date either) says nothing rather than
            // guessing it matches.
            $testDoneNote = ($headerDateRaw && !empty($info['test_date']) && $info['test_date'] !== $headerDateRaw)
                ? '<p style="margin:2px 0 0;font-size:6.5px;color:#5B6570;font-weight:normal;">(Test Done : ' . e(\Carbon\Carbon::parse($info['test_date'])->format('d/m/Y')) . ')</p>'
                : '';

            $html .= '<td style="' . $dataCellBorder . 'background-color:#F7FAFC;padding:4.5pt 6pt;' . $resultValueWidth . $wordWrap . $contentFontSize . '">'
                . '<p style="text-align:center;font-weight:bold;">' . $resultText . '</p>'
                . $testDoneNote . '</td>';
            // $info['range'] is already-escaped HTML from combinedRange()
            // (may contain a real <br> between Male/Female/Common lines) --
            // must NOT be escaped again here, or the <br> prints as literal
            // text instead of a line break.
            $html .= '<td style="' . $dataCellBorder . 'padding:4.5pt 6pt;text-align:center;' . $rangeWidth . $wordWrap . $contentFontSize . '"><span style="color:#222222;">' . ($info['range'] ?: '-') . '</span></td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Splices an auto-generated table (see buildAutoTable()) into a
     * template that's been stripped down to a placeholder, preserving
     * whatever supplementary content (instrument notes, headings, ...)
     * surrounds it. An empty $tableHtml (nothing tracked yet) drops the
     * placeholder cleanly rather than leaving a stray empty tag. A
     * template with no placeholder at all (the legacy expand-in-place
     * templates like CBC/LFT, or plain free-text templates) is returned
     * unchanged -- this never invents a table where there wasn't one.
     */
    public function spliceAutoTable(string $contentHtml, string $tableHtml): string
    {
        if (strpos($contentHtml, 'data-auto-key-param-table') === false) {
            return $contentHtml;
        }

        // [^<]* rather than \s* -- CKEditor's editing view can't display a
        // truly empty block element, so it fills the placeholder with a
        // non-breaking space (U+00A0) before the data round-trips back
        // here; PCRE's \s doesn't match that multi-byte character without
        // the /u modifier, so the old pattern silently failed to match at
        // all once a report had actually been through the live editor
        // (direct DB/tinker tests never exercised this, since they never
        // round-tripped the placeholder through CKEditor first).
        return preg_replace(
            '/<div[^>]*data-auto-key-param-table="1"[^>]*>[^<]*<\/div>/i',
            $tableHtml,
            $contentHtml,
            1
        );
    }

    /**
     * Folds each tracked parameter's current + up to 3 previous values
     * directly into its own row of the main investigation table, in place
     * of the single Result cell -- replaces the separate "Previous
     * Values" section that used to sit below the report. Only touches a
     * <table> that actually contains at least one data-key-param-result
     * marker; every other table (including entirely free-text templates,
     * which have no <table> at all) is left completely untouched.
     *
     * New column order: Investigation | Unit | Current | Previous 1 |
     * Previous 2 | Previous 3 | Reference Range. A row with no marker
     * (an untracked parameter sharing the same table, e.g. LFT's Direct
     * Bilirubin alongside tracked SGOT) keeps its own single value in the
     * Current column and shows '-' in Unit/Previous 1-3 -- there's no
     * tracked history for it to show.
     *
     * The three "Previous" column headers show the actual test date of
     * whichever tracked row in this table first has a history entry at
     * that slot (falls back to "Previous N" if none do) -- reasonable
     * because a bundled panel's own components (CBC, LFT, Lipid Profile)
     * are tested together, so their tracked rows' history dates coincide
     * in practice; it's never meant to reconcile genuinely different
     * dates across rows.
     */

    /**
     * Rescales any width:<number><unit> found in an inline style string by
     * $factor (e.g. 1/5 when one column's width is about to be cloned onto
     * 5 new columns in its place) -- leaves every other style property
     * (background-color, padding, border, ...) untouched.
     */
    private function scaleWidthInStyle(string $style, float $factor): string
    {
        // Negative lookbehind excludes border-width/min-width/max-width --
        // a plain substring match on "width:" would also catch those
        // (e.g. "border-width:1pt" contains "width:1pt") and incorrectly
        // shrink the border too.
        return preg_replace_callback(
            '/(?<![\w-])width\s*:\s*([\d.]+)([a-z%]*)/i',
            fn ($m) => 'width:' . round(((float) $m[1]) * $factor, 3) . $m[2],
            $style
        );
    }

    private function appendStyle(string $style, string $addition): string
    {
        return rtrim(trim($style), ';') . ';' . $addition;
    }

    /**
     * Every one of range_male/range_female/range_common that's actually
     * filled in for this item, as plain text parts -- not gender-filtered
     * down to a single value, since the patient's own gender isn't always
     * known/relevant at a glance and admin-authored templates already
     * print multiple genders together the same way (e.g. Haemoglobin's
     * own template: "Males=12-18 Females=12-16 gm/dl"). Male/Female parts
     * are labelled so they aren't mistaken for each other; Common has no
     * gender to label, so it's returned as-is. Plain text/no HTML -- a
     * caller that's building printable markup (see combinedRangeHtml())
     * escapes and joins these itself; one that's just storing the value
     * (recordConfirmedValues()) joins them as plain text instead.
     */
    private function combinedRangeParts($item): array
    {
        if (!$item) {
            return [];
        }

        $parts = [];

        if (!empty($item->range_male)) {
            $parts[] = '(M) ' . $item->range_male;
        }

        if (!empty($item->range_female)) {
            $parts[] = '(F) ' . $item->range_female;
        }

        if (!empty($item->range_common)) {
            $parts[] = $item->range_common;
        }

        return $parts;
    }

    /**
     * combinedRangeParts(), escaped and stacked one per line for direct
     * printing -- used by buildAutoTable(). Callers must print this
     * result raw (not escape it again), or the <br> prints as literal
     * text instead of a line break.
     */
    private function combinedRangeHtml($item): ?string
    {
        $parts = $this->combinedRangeParts($item);

        return empty($parts) ? null : implode('<br>', array_map('e', $parts));
    }

    public function expandTrackedResultTables(string $contentHtml, array $tableData): string
    {
        if (empty($tableData) || strpos($contentHtml, 'data-key-param-result') === false) {
            return $contentHtml;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__root__">' . $contentHtml . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $root = $dom->getElementById('__root__');
        if (!$root) {
            return $contentHtml;
        }

        $xpath = new \DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//table')) as $table) {

            if ($xpath->query('.//*[@data-key-param-result]', $table)->length === 0) {
                continue;
            }

            $rows = iterator_to_array($xpath->query('./tbody/tr|./tr', $table));
            if (count($rows) < 2) {
                continue;
            }

            $headerRow = array_shift($rows);
            $dataRows = $rows;

            $headerCells = iterator_to_array($xpath->query('./td|./th', $headerRow));
            if (count($headerCells) < 3) {
                continue;
            }

            $investigationHeader = $headerCells[0];
            $resultHeader = $headerCells[1];
            $rangeHeader = $headerCells[count($headerCells) - 1];

            // The original single Result column's own width (e.g.
            // "width:1.75in"), scaled for the 5 new columns it's being
            // split into. Investigation and Reference Range both give up
            // some of their own width too, so each of the 3 "Previous"
            // date columns is wide enough to fit a dd/mm/yyyy date on one
            // line at this font size -- without this, cloning the
            // original Result width unscaled onto all 5 columns blew the
            // table out to roughly 5x its original width.
            $resultHeaderStyle = $this->scaleWidthInStyle($resultHeader->getAttribute('style'), 0.42);

            // Investigation's and Reference Range's own columns narrow in
            // place (don't need recreating, just restyling).
            $investigationHeader->setAttribute('style', $this->appendStyle($this->scaleWidthInStyle($investigationHeader->getAttribute('style'), 0.65), 'font-size:8px;'));
            $rangeHeader->setAttribute('style', $this->appendStyle($this->scaleWidthInStyle($rangeHeader->getAttribute('style'), 0.68), 'font-size:8px;'));

            // Previous 1/2/3 header labels -- real date if any tracked row
            // in this table has one at that slot, else left blank (no
            // generic "Previous 2" placeholder when there's nothing to
            // show there).
            $prevLabels = ['', '', ''];

            foreach ($dataRows as $row) {
                $marker = $xpath->query('.//*[@data-key-param-result]', $row)->item(0);
                if (!$marker) continue;
                $code = $marker->getAttribute('data-key-param-result');
                $hist = $tableData[$code]['history'] ?? [];
                foreach ([0, 1, 2] as $i) {
                    if ($prevLabels[$i] === '' && isset($hist[$i])) {
                        // dd/mm/yyyy specifically here (not the d-m-Y
                        // test_date_fmt used elsewhere) -- narrow enough to
                        // fit on one line at this column's reduced width.
                        $prevLabels[$i] = \Carbon\Carbon::parse($hist[$i]['test_date'])->format('d/m/Y');
                    }
                }
            }

            $makeHeaderCell = function (string $text) use ($dom, $resultHeaderStyle) {
                $td = $dom->createElement('td');
                if ($resultHeaderStyle !== '') $td->setAttribute('style', $this->appendStyle($resultHeaderStyle, 'font-size:8px;'));
                $p = $dom->createElement('p');
                $p->setAttribute('style', 'text-align:center;');
                $span = $dom->createElement('span');
                $span->setAttribute('style', 'color:#FFFFFF;');
                $strong = $dom->createElement('strong');
                $strong->appendChild($dom->createTextNode($text));
                $span->appendChild($strong);
                $p->appendChild($span);
                $td->appendChild($p);
                return $td;
            };

            $newHeaderCells = [
                $makeHeaderCell('UNIT'),
                $makeHeaderCell('CURRENT'),
                $makeHeaderCell(strtoupper($prevLabels[0])),
                $makeHeaderCell(strtoupper($prevLabels[1])),
                $makeHeaderCell(strtoupper($prevLabels[2])),
            ];

            foreach ($newHeaderCells as $cell) {
                $headerRow->insertBefore($cell, $resultHeader);
            }
            $headerRow->removeChild($resultHeader);

            foreach ($dataRows as $row) {

                $cells = iterator_to_array($xpath->query('./td', $row));
                if (count($cells) < 3) {
                    continue;
                }

                $investigationCell = $cells[0];
                $investigationCell->setAttribute('style', $this->appendStyle($this->scaleWidthInStyle($investigationCell->getAttribute('style'), 0.65), 'font-weight:bold;'));

                $rangeCell = $cells[count($cells) - 1];
                $rangeCell->setAttribute('style', $this->scaleWidthInStyle($rangeCell->getAttribute('style'), 0.68));

                $resultCell = $cells[1];
                $cellStyle = $this->scaleWidthInStyle($resultCell->getAttribute('style'), 0.42);

                $makeDataCell = function (string $text, bool $bold = false) use ($dom, $cellStyle) {
                    $td = $dom->createElement('td');
                    if ($cellStyle !== '') $td->setAttribute('style', $bold ? $this->appendStyle($cellStyle, 'font-weight:bold;') : $cellStyle);
                    $p = $dom->createElement('p');
                    $p->setAttribute('style', 'text-align:center;');
                    $p->appendChild($dom->createTextNode($text));
                    $td->appendChild($p);
                    return $td;
                };

                $marker = $xpath->query('.//*[@data-key-param-result]', $resultCell)->item(0);

                if ($marker) {

                    $code = $marker->getAttribute('data-key-param-result');
                    $info = $tableData[$code] ?? ['uom' => null, 'current' => null, 'history' => []];
                    $hist = $info['history'];

                    $newCells = [
                        $makeDataCell((string) ($info['uom'] ?? '-')),
                        $makeDataCell((string) ($info['current'] !== null && $info['current'] !== '' ? $info['current'] : '-'), true),
                        $makeDataCell(isset($hist[0]) ? (string) $hist[0]['result_value'] : '-'),
                        $makeDataCell(isset($hist[1]) ? (string) $hist[1]['result_value'] : '-'),
                        $makeDataCell(isset($hist[2]) ? (string) $hist[2]['result_value'] : '-'),
                    ];

                } else {

                    $existingValue = trim($resultCell->textContent);

                    $newCells = [
                        $makeDataCell('-'),
                        $makeDataCell($existingValue !== '' ? $existingValue : '-', true),
                        $makeDataCell('-'),
                        $makeDataCell('-'),
                        $makeDataCell('-'),
                    ];
                }

                foreach ($newCells as $cell) {
                    $row->insertBefore($cell, $resultCell);
                }
                $row->removeChild($resultCell);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }
}
