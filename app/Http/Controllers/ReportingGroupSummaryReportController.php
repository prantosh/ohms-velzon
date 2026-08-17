<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Single-day, all-users financial summary sliced by Reporting Group, with
 * one row per user inside each group. Sits between CashSubmissionReportController
 * (same Reporting-Group breakdown, but only one user at a time) and
 * MonthlyReconciliationReportController (all users, but by test category over
 * a whole month, no per-user split) -- this is the day-grain, all-users,
 * per-user-within-group view neither of those provides.
 */
class ReportingGroupSummaryReportController extends Controller
{
    // DOCTOR_VISIT (and other non-itemized invoice types) never write an
    // invoice_details row, so there's no line item_code to key the
    // reporting-group lookup off of -- each type's one fixed
    // invoice_item_masters code stands in instead. Mirrors
    // CashLedgerService::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE exactly (same
    // DB, same convention) -- kept as a separate copy since this
    // controller's day+all-users scoping differs enough from that
    // service's user+date-range scoping to not be worth sharing more than
    // this one constant (same rationale CashLedgerService's own docblock
    // already gives for not sharing weight-map logic with
    // ItemWiseSummaryReportController).
    private const FALLBACK_ITEM_CODE_BY_INVOICE_TYPE = [
        'DOCTOR_VISIT' => 'DOC001',
        'OXYGEN_RENT' => 'OXY001',
        'CONCENTRATOR_RENT' => 'CON001',
        'AMBULANCE_RENT' => 'AMB001',
        'MEMBERSHIP_FEE' => 'MEM001',
        'OTHER_INCOME' => 'DON001',
    ];

    private const UNASSIGNED_GROUP_LABEL = 'Unassigned (No Reporting Group)';

    private const COLUMNS = [
        'collection_cash', 'collection_noncash', 'refund',
        'doctor_payable', 'paid_to_doctor', 'cash_to_deposit',
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-reporting-group-summary-report');
    }

    /*
    |--------------------------------------------------------------------------
    | REPORT (single day, all users, grouped by Reporting Group)
    |--------------------------------------------------------------------------
    */

    public function report(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->date;

        [$itemToGroupId, $groupNames] = $this->resolveGroupLookup();

        // $totals[$groupLabel][$userId] => ['collection_cash' => .., ...]
        $totals = [];
        $userNames = [];

        $this->accumulateReceiptsAndRefunds($totals, $userNames, $itemToGroupId, $groupNames, $date);
        $this->accumulateDoctorPayables($totals, $userNames, $itemToGroupId, $groupNames, $date);
        $this->accumulateDoctorPayments($totals, $userNames, $itemToGroupId, $groupNames, $date);

        $groups = $this->assembleGroups($totals, $userNames);

        $grandTotal = $this->emptyRow();
        foreach ($groups as $group) {
            foreach (self::COLUMNS as $col) {
                $grandTotal[$col] += $group['subtotal'][$col];
            }
        }
        foreach (self::COLUMNS as $col) {
            $grandTotal[$col] = round($grandTotal[$col], 2);
        }

        return response()->json([
            'status' => true,
            'date' => $date,
            'date_fmt' => Carbon::parse($date)->format('d-m-Y (l)'),
            'groups' => $groups,
            'grand_total' => $grandTotal,
        ]);
    }

    public function print(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $result = json_decode($this->report($request)->getContent(), true);

        $pdf = Pdf::loadView('apps-reporting-group-summary-report-pdf', [
            'groups' => $result['groups'],
            'grandTotal' => $result['grand_total'],
            'dateFmt' => $result['date_fmt'],
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Reporting-Group-Summary-Report-' . Carbon::parse($result['date'])->format('d-m-Y') . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | AGGREGATION
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection} [item_code => reporting_group_id, group_id => group_name]
     */
    private function resolveGroupLookup(): array
    {
        $itemToGroupId = DB::table('reporting_group_items')->pluck('reporting_group_id', 'item_code');
        $groupNames = DB::table('reporting_groups')->pluck('group_name', 'id');

        return [$itemToGroupId, $groupNames];
    }

    private function groupLabelForItemCode(?string $itemCode, $itemToGroupId, $groupNames): string
    {
        if (!$itemCode) {
            return self::UNASSIGNED_GROUP_LABEL;
        }

        $groupId = $itemToGroupId[$itemCode] ?? null;

        return $groupId ? ($groupNames[$groupId] ?? self::UNASSIGNED_GROUP_LABEL) : self::UNASSIGNED_GROUP_LABEL;
    }

    /**
     * Collection Cash / Collection Non-Cash: split by the RECEIVED row's own
     * payment_mode. Refund: always counted as a cash outflow regardless of
     * the original payment's mode (refunds are physically handed back as
     * cash from the drawer) -- matches MonthlyReconciliationReportController
     * and CashLedgerService exactly.
     *
     * daily_transactions rows are invoice-level; a multi-line DIAGNOSTIC
     * invoice's amount is apportioned across invoice_details.item_code rows
     * by each line's share of the invoice total.
     *
     * User dimension: dt.operator_id (who actually collected/refunded this),
     * the same column DoctorVisitInvoiceController/DiagnosticInvoiceController/etc
     * already populate with the collecting staff member.
     */
    private function accumulateReceiptsAndRefunds(array &$totals, array &$userNames, $itemToGroupId, $groupNames, string $date): void
    {
        $transactions = DB::table('daily_transactions as dt')
            ->leftJoin('invoices as inv', 'inv.invoice_no', '=', 'dt.invoice_reference')
            ->whereIn('dt.transaction_type', ['RECEIVED', 'REFUND'])
            ->where('dt.status', '!=', 'CANCELLED')
            ->whereDate('dt.transaction_date', $date)
            ->select(
                'dt.transaction_type',
                'dt.payment_mode',
                'dt.received_amount',
                'dt.refund_amount',
                'dt.operator_id',
                'dt.operator_name',
                'inv.invoice_type',
                DB::raw('COALESCE(inv.invoice_no, dt.invoice_reference) as invoice_no')
            )
            ->get();

        $invoiceNosNeedingWeights = $transactions
            ->filter(fn ($t) => $t->invoice_type !== 'DOCTOR_VISIT')
            ->pluck('invoice_no')
            ->filter()
            ->unique()
            ->values();

        $weightMaps = $this->buildInvoiceWeightMaps($invoiceNosNeedingWeights);

        foreach ($transactions as $t) {

            $userId = $t->operator_id;
            $userNames[$userId] = $t->operator_name ?? 'Unknown';

            $weights = $t->invoice_type === 'DOCTOR_VISIT'
                ? [self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE['DOCTOR_VISIT'] => 1.0]
                : ($weightMaps[$t->invoice_no] ?? [null => 1.0]);

            $isCash = $t->payment_mode === 'Cash';

            foreach ($weights as $itemCode => $share) {

                $label = $this->groupLabelForItemCode($itemCode, $itemToGroupId, $groupNames);

                if ($t->transaction_type === 'RECEIVED') {
                    $this->addAmount($totals, $label, $userId, $isCash ? 'collection_cash' : 'collection_noncash', (float) $t->received_amount * $share);
                } else {
                    $this->addAmount($totals, $label, $userId, 'refund', (float) $t->refund_amount * $share);
                }
            }
        }
    }

    /**
     * Per-invoice item_code weight map (invoice_no => [item_code => share of
     * that invoice's line total]), built from invoice_details. Mirrors
     * MonthlyReconciliationReportController::buildInvoiceWeightMaps() exactly.
     */
    private function buildInvoiceWeightMaps($invoiceNos): array
    {
        if ($invoiceNos->isEmpty()) {
            return [];
        }

        $detailRows = DB::table('invoice_details')
            ->whereIn('invoice_no', $invoiceNos)
            ->select('invoice_no', 'item_code', DB::raw('SUM(amount) as line_total'))
            ->groupBy('invoice_no', 'item_code')
            ->get();

        $invoiceTotals = [];
        foreach ($detailRows as $d) {
            $invoiceTotals[$d->invoice_no] = ($invoiceTotals[$d->invoice_no] ?? 0) + (float) $d->line_total;
        }

        $weightMaps = [];
        foreach ($detailRows as $d) {
            $total = $invoiceTotals[$d->invoice_no] ?? 0;
            $weightMaps[$d->invoice_no][$d->item_code] = $total > 0 ? ((float) $d->line_total / $total) : 0;
        }

        return $weightMaps;
    }

    /**
     * Doctor Payable (accrued that day) -- new payable rows created the
     * selected day (invoices billed that day with a referring doctor).
     * Already per-line (item_code is real, not an invoice-level figure that
     * needs weighting), so no weight map needed here. Attributed to
     * created_by -- whichever staff member billed the invoice that
     * generated the payable.
     */
    private function accumulateDoctorPayables(array &$totals, array &$userNames, $itemToGroupId, $groupNames, string $date): void
    {
        $rows = DB::table('doctor_payables as dp')
            ->leftJoin('users as u', 'u.id', '=', 'dp.created_by')
            ->where('dp.payment_status', '!=', 'CANCELLED')
            ->whereDate('dp.created_at', $date)
            ->select('dp.item_code', 'dp.invoice_type', 'dp.created_by', 'u.name as user_name', 'dp.payable_amount')
            ->get();

        foreach ($rows as $row) {

            $userId = $row->created_by;
            $userNames[$userId] = $row->user_name ?? 'Unknown';

            $itemCode = $row->invoice_type === 'DOCTOR_VISIT'
                ? self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE['DOCTOR_VISIT']
                : $row->item_code;

            $label = $this->groupLabelForItemCode($itemCode, $itemToGroupId, $groupNames);

            $this->addAmount($totals, $label, $userId, 'doctor_payable', (float) $row->payable_amount);
        }
    }

    /**
     * Paid to Doctor (settled that day) -- doctor_settlement_items joined to
     * doctor_settlements processed (settlement_date) the selected day.
     * Shown total includes every payment_mode; the cash-only portion is
     * tracked separately (paid_to_doctor_cash, not surfaced as its own
     * column) purely to feed the Cash to Deposit formula. Attributed to
     * doctor_settlements.created_by -- whichever staff member processed the
     * payout, which is routinely a different person than whoever collected
     * the original payment.
     */
    private function accumulateDoctorPayments(array &$totals, array &$userNames, $itemToGroupId, $groupNames, string $date): void
    {
        $rows = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->leftJoin('users as u', 'u.id', '=', 'ds.created_by')
            ->where('ds.status', '!=', 'CANCELLED')
            ->whereDate('ds.settlement_date', $date)
            ->select('dsi.item_code', 'dsi.invoice_type', 'ds.created_by', 'u.name as user_name', 'ds.payment_mode', 'dsi.settlement_amount')
            ->get();

        foreach ($rows as $row) {

            $userId = $row->created_by;
            $userNames[$userId] = $row->user_name ?? 'Unknown';

            $itemCode = $row->invoice_type === 'DOCTOR_VISIT'
                ? self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE['DOCTOR_VISIT']
                : $row->item_code;

            $label = $this->groupLabelForItemCode($itemCode, $itemToGroupId, $groupNames);
            $amount = (float) $row->settlement_amount;

            $this->addAmount($totals, $label, $userId, 'paid_to_doctor', $amount);

            if ($row->payment_mode === 'CASH') {
                $this->addAmount($totals, $label, $userId, 'paid_to_doctor_cash', $amount);
            }
        }
    }

    private function addAmount(array &$totals, string $groupLabel, $userId, string $column, float $amount): void
    {
        if (!isset($totals[$groupLabel][$userId])) {
            $totals[$groupLabel][$userId] = $this->emptyRow();
            $totals[$groupLabel][$userId]['paid_to_doctor_cash'] = 0.0;
        }

        $totals[$groupLabel][$userId][$column] += $amount;
    }

    private function emptyRow(): array
    {
        return array_fill_keys(self::COLUMNS, 0.0);
    }

    /**
     * Turn the raw $totals[$group][$userId] accumulator into the final
     * sorted group/row/subtotal structure, computing cash_to_deposit per
     * user cell along the way.
     */
    private function assembleGroups(array $totals, array $userNames): array
    {
        $groups = [];

        foreach ($totals as $groupLabel => $userRows) {

            $rows = [];
            $subtotal = $this->emptyRow();

            foreach ($userRows as $userId => $cols) {

                $cols['cash_to_deposit'] = $cols['collection_cash'] - $cols['refund'] - $cols['paid_to_doctor_cash'];

                $row = ['user_name' => $userNames[$userId] ?? 'Unknown'];
                foreach (self::COLUMNS as $col) {
                    $row[$col] = round($cols[$col], 2);
                    $subtotal[$col] += $cols[$col];
                }

                $rows[] = $row;
            }

            usort($rows, fn ($a, $b) => strcmp($a['user_name'], $b['user_name']));

            foreach (self::COLUMNS as $col) {
                $subtotal[$col] = round($subtotal[$col], 2);
            }

            $groups[] = [
                'group_name' => $groupLabel,
                'rows' => $rows,
                'subtotal' => $subtotal,
            ];
        }

        // Unassigned pinned last -- matches its treatment everywhere else
        // this bucket appears (CashLedgerService::allocateLedgerToReportingGroups()).
        usort($groups, function ($a, $b) {

            if ($a['group_name'] === self::UNASSIGNED_GROUP_LABEL) {
                return 1;
            }
            if ($b['group_name'] === self::UNASSIGNED_GROUP_LABEL) {
                return -1;
            }

            return strcmp($a['group_name'], $b['group_name']);
        });

        return $groups;
    }
}
