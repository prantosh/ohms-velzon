<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The test date(s) a printed report shows.
 *
 * Every billed line can carry its own test_date (invoice_details.test_date);
 * a line without one uses the invoice's test_date, so everything created
 * before per-test dates existed prints exactly as it always did.
 *
 *   - every line of the report has the same date  -> that date, printed once
 *     as "Test Date : dd-mm-yyyy" (the layout as it was)
 *   - the lines have different dates              -> no single date; each date
 *     is printed in brackets beside its item name instead, e.g.
 *     "GLUCOSE (FBS) (11-10-2026), GLUCOSE (PPBS) (12-10-2026)"
 */
class ReportTestDates
{
    /**
     * Effective test date (Y-m-d or null) of each billed line, keyed by the
     * invoice_detail id: the line's own date, else the invoice's.
     *
     * $override (invoice_detail id => Y-m-d or ''/null) replaces a line's own
     * date -- used by the report preview to show dates that were edited but
     * not saved yet. '' / null means "no own date" (falls back to the invoice's).
     *
     * @param  array<int,int>  $detailIds
     * @param  array<int,?string>  $override
     * @return array<int,?string>
     */
    public static function effectiveDates(array $detailIds, array $override = []): array
    {
        if (empty($detailIds)) {
            return [];
        }

        $rows = DB::table('invoice_details as d')
            ->leftJoin('invoices as i', 'i.invoice_no', '=', 'd.invoice_no')
            ->whereIn('d.id', $detailIds)
            ->get(['d.id', 'd.test_date as line_date', 'i.test_date as invoice_date']);

        $dates = [];

        foreach ($rows as $row) {
            $own = array_key_exists((int) $row->id, $override) ? $override[(int) $row->id] : $row->line_date;

            $dates[(int) $row->id] = self::clean($own) ?? self::clean($row->invoice_date);
        }

        return $dates;
    }

    /**
     * What a report should print for the given lines.
     *
     * @param  array<int,int>     $detailIds
     * @param  array<int,string>  $names    invoice_detail id => item description
     * @param  array<int,?string> $override unsaved date edits (see effectiveDates())
     * @return array{single: ?string, title: string, mixed: bool, header_date: ?string, header_date_raw: ?string}
     *         single = "dd-mm-yyyy" when every line shares one date (else null);
     *         title  = the item names joined with ", ", each followed by its
     *                  date in brackets ONLY when the dates differ;
     *         header_date(_raw) = the date the report's single "Test Date"
     *                  line should show even when mixed -- same as single
     *                  when every line agrees, else the FIRST line's own
     *                  date (in the order given) -- see
     *                  KeyParameterService::buildAutoTable(), which uses
     *                  header_date_raw to decide which rows need their own
     *                  "(Test Done : ...)" note beside the result instead.
     */
    public static function describe(array $detailIds, array $names, array $override = []): array
    {
        // A package line is reported as one, but its components may each have
        // been done on a different day -- dates (and names) are per component.
        $billedIds = $detailIds;
        $billedNames = $names;

        $expanded = self::expandPackages($detailIds);

        $detailIds = array_column($expanded, 'id');

        foreach ($expanded as $e) {
            $names[$e['id']] = $names[$e['id']] ?? $e['name'];
        }

        $dates = self::effectiveDates($detailIds, $override);

        $distinct = collect($dates)->filter()->unique()->values();

        $mixed = $distinct->count() > 1;

        // Same dates throughout: the report keeps its billed names (a package
        // stays "LIPID PROFILE"); only differing dates split it into components.
        if (!$mixed) {
            $detailIds = $billedIds;
            $names = $billedNames;
        }

        $parts = [];

        foreach ($detailIds as $id) {

            $name = $names[$id] ?? null;

            if ($name === null || $name === '') {
                continue;
            }

            $date = $dates[$id] ?? null;

            $parts[] = ($mixed && $date)
                ? $name . ' (' . Carbon::parse($date)->format('d-m-Y') . ')'
                : $name;
        }

        // Same order the report prints its rows in: $detailIds has already
        // been swapped to the expanded component ids above when mixed, else
        // it's the billed lines as given.
        $headerDateRaw = null;
        foreach ($detailIds as $id) {
            if (!empty($dates[$id])) {
                $headerDateRaw = $dates[$id];
                break;
            }
        }

        return [
            'single' => (!$mixed && $distinct->count() === 1)
                ? Carbon::parse($distinct->first())->format('d-m-Y')
                : null,
            'title' => implode(', ', $parts),
            'mixed' => $mixed,
            'header_date_raw' => $headerDateRaw,
            'header_date' => $headerDateRaw ? Carbon::parse($headerDateRaw)->format('d-m-Y') : null,
        ];
    }

    /**
     * Test dates for a printed INVOICE. Returns the date for the header's
     * "Test Date" (the most common date among the tests) and appends
     * " (dd-mm-yyyy)" to the test_name of every line whose date differs from
     * it. A package line is judged by its components: those that differ are
     * named with their dates. When every test shares one date nothing is
     * appended and the layout is exactly as it always was.
     *
     * @param  \Illuminate\Support\Collection  $tests  invoice_details rows (id, item_code_sub, invoice_no, test_name)
     * @param  object  $invoice  (needs ->test_date)
     * @return string  header date as dd-mm-yyyy, or '' when unknown
     */
    public static function applyToInvoiceLines($tests, $invoice): string
    {
        $expanded = self::expandPackages($tests->pluck('id')->map(fn ($v) => (int) $v)->all());

        $dates = self::effectiveDates(array_column($expanded, 'id'));

        $counts = array_count_values(array_filter(array_values($dates)));

        if (empty($counts)) {
            return '';
        }

        // most common date; on a tie prefer the invoice's own date, then the earliest
        $invoiceDate = self::clean($invoice->test_date ?? null);
        $max = max($counts);
        $top = array_keys(array_filter($counts, fn ($c) => $c === $max));
        sort($top);
        $majority = ($invoiceDate && in_array($invoiceDate, $top, true)) ? $invoiceDate : $top[0];

        $fmt = fn ($d) => Carbon::parse($d)->format('d-m-Y');

        foreach ($tests as $row) {

            $parts = array_values(array_filter($expanded, fn ($e) => $e['parent'] === (int) $row->id));

            $differing = array_values(array_filter(
                $parts,
                fn ($e) => ($dates[$e['id']] ?? $majority) !== $majority
            ));

            if (empty($differing)) {
                continue;
            }

            $distinct = array_unique(array_map(fn ($e) => $dates[$e['id']], $differing));

            if (count($differing) === count($parts) && count($distinct) === 1) {
                $row->test_name .= ' (' . $fmt($distinct[0]) . ')';
            } else {
                // e.g. "GLUCOSE (FBS & PPBS) PPBS (Test Date 13-10-2026)" -- only the
                // components that differ from the majority date are named
                $row->test_name .= ' ' . implode(', ', array_map(function ($e) use ($parts, $dates, $fmt) {
                    $short = preg_match('/\(([^()]*)\)\s*$/', $e['name'], $m) ? $m[1] : $e['name'];

                    return (count($parts) > 1 ? $short . ' ' : '') . '(Test Date ' . $fmt($dates[$e['id']]) . ')';
                }, $differing));
            }
        }

        return $fmt($majority);
    }

    /**
     * The lines whose dates a report shows: a package line (one that has
     * tagged component lines on the same invoice) is replaced by its
     * components; any other line stands for itself.
     *
     * @param  array<int,int>  $detailIds
     * @return array<int,array{id:int,name:string,item_code_sub:string,parent:int}>  parent = the id passed in
     */
    public static function expandPackages(array $detailIds): array
    {
        if (empty($detailIds)) {
            return [];
        }

        $rows = DB::table('invoice_details')->whereIn('id', $detailIds)
            ->get(['id', 'invoice_no', 'item_code_sub', 'item_description', 'package_parent_item_code_sub'])
            ->keyBy('id');

        $out = [];

        foreach ($detailIds as $id) {

            $row = $rows->get((int) $id);

            if (!$row) {
                continue;
            }

            $children = $row->package_parent_item_code_sub === null
                ? DB::table('invoice_details')
                    ->where('invoice_no', $row->invoice_no)
                    ->where('package_parent_item_code_sub', $row->item_code_sub)
                    ->orderBy('id')
                    ->get(['id', 'item_code_sub', 'item_description'])
                : collect();

            if ($children->isEmpty()) {
                $out[] = ['id' => (int) $row->id, 'name' => (string) $row->item_description, 'item_code_sub' => (string) $row->item_code_sub, 'parent' => (int) $row->id];
                continue;
            }

            foreach ($children as $child) {
                $out[] = ['id' => (int) $child->id, 'name' => (string) $child->item_description, 'item_code_sub' => (string) $child->item_code_sub, 'parent' => (int) $row->id];
            }
        }

        return $out;
    }

    /**
     * "dd-mm-yyyy" (or "-") for a report that covers ONE billed line (USG,
     * Cardiology, Non-Pathology): the line's own date, else the invoice's.
     * $override is an unsaved date typed on the card (preview): a valid date
     * replaces the line's own, '' means "no own date".
     *
     * @param object $invoice the invoice (needs ->test_date)
     */
    public static function textForLine(?int $detailId, $invoice, ?string $override = null): string
    {
        $ownDate = null;

        if ($override !== null) {
            $ownDate = self::clean($override);
        } elseif ($detailId) {
            $ownDate = self::clean(DB::table('invoice_details')->where('id', $detailId)->value('test_date'));
        }

        $date = $ownDate ?? self::clean($invoice->test_date ?? null);

        return $date ? Carbon::parse($date)->format('d-m-Y') : '-';
    }

    public static function clean($value): ?string
    {
        if (!$value) {
            return null;
        }

        // legacy zero dates and garbage count as "no date"
        $text = substr((string) $value, 0, 10);

        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)
            && (int) $m[1] > 1000 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
            ? $text
            : null;
    }
}
