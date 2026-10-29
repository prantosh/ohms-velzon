<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Date-range counterpart of ReportingGroupSummaryReportController: the same
 * per-user, per-Reporting-Group figures, but for any period, laid out
 * day -> user -> group, with optional user and group filters.
 *
 * Attribution rules are identical to that report (see its docblocks):
 *   - Collection / Refund   -> daily_transactions.operator_id, by transaction_date;
 *                              multi-line invoices apportioned over invoice_details.item_code
 *   - Doctor Payable        -> doctor_payables.created_by, by created_at
 *   - Paid to Doctor        -> doctor_settlements.created_by, by settlement_date
 *   - Cash to Deposit       -> cash collection - refund - cash paid to doctors
 *     (the system does not record a separate "deposited" entry; this is the
 *     amount the user is due to deposit, as on the cash submission slip)
 */
class DayWiseGroupSummaryReportController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private const FALLBACK_ITEM_CODE_BY_INVOICE_TYPE = [
        'DOCTOR_VISIT' => 'DOC001',
        'OXYGEN_RENT' => 'OXY001',
        'CONCENTRATOR_RENT' => 'CON001',
        'AMBULANCE_RENT' => 'AMB001',
        'MEMBERSHIP_FEE' => 'MEM001',
        'OTHER_INCOME' => 'DON001',
    ];

    private const UNASSIGNED_GROUP_LABEL = 'Unassigned (No Reporting Group)';

    private const UNASSIGNED_FILTER = 'unassigned';

    private const COLUMNS = [
        'collection_cash', 'collection_noncash', 'refund',
        'doctor_payable', 'paid_to_doctor', 'cash_to_deposit',
    ];

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $groups = DB::table('reporting_groups')->orderBy('group_name')->get(['id', 'group_name']);

        return view('apps-day-wise-group-summary-report', compact('users', 'groups'));
    }

    public function report(Request $request)
    {
        $data = $this->build($request);

        return response()->json(['status' => true] + $data);
    }

    public function print(Request $request)
    {
        $data = $this->build($request);

        $pdf = Pdf::loadView('apps-day-wise-group-summary-report-pdf', [
            'days' => $data['days'],
            'grandTotal' => $data['grand_total'],
            'fromFmt' => Carbon::parse($data['from_date'])->format('d-m-Y'),
            'toFmt' => Carbon::parse($data['to_date'])->format('d-m-Y'),
            'userFilter' => $data['user_filter'],
            'groupFilter' => $data['group_filter'],
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Day-wise-Group-Summary-' .
            Carbon::parse($data['from_date'])->format('d-m-Y') . '-to-' .
            Carbon::parse($data['to_date'])->format('d-m-Y') . '.pdf'
        );
    }

    private function build(Request $request): array
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'user_id' => 'nullable|integer',
            'group' => 'nullable|string',
        ]);

        $from = Carbon::parse($request->from_date)->toDateString();
        $to = Carbon::parse($request->to_date)->toDateString();
        $userId = $request->filled('user_id') ? (int) $request->user_id : null;
        $groupFilter = $request->filled('group') ? $request->group : null;

        $itemToGroupId = DB::table('reporting_group_items')->pluck('reporting_group_id', 'item_code');
        $groupNames = DB::table('reporting_groups')->pluck('group_name', 'id');

        $ctx = [
            'itemToGroupId' => $itemToGroupId,
            'groupNames' => $groupNames,
            'userId' => $userId,
            'groupFilter' => $groupFilter,
        ];

        // $totals[$date][$userId][$groupLabel] => columns
        $totals = [];
        $userNames = [];

        $this->accumulateReceiptsAndRefunds($totals, $userNames, $ctx, $from, $to);
        $this->accumulateDoctorPayables($totals, $userNames, $ctx, $from, $to);
        $this->accumulateDoctorPayments($totals, $userNames, $ctx, $from, $to);

        ksort($totals);

        $grand = $this->emptyRow();
        $days = [];

        foreach ($totals as $date => $userRows) {

            $rows = [];
            $subtotal = $this->emptyRow();

            foreach ($userRows as $uid => $groupRows) {
                foreach ($groupRows as $groupLabel => $cols) {

                    $cols['cash_to_deposit'] = $cols['collection_cash'] - $cols['refund'] - $cols['paid_to_doctor_cash'];

                    $row = [
                        'user_id' => $uid,
                        'user_name' => $userNames[$uid] ?? 'Unknown',
                        'group_name' => $groupLabel,
                    ];

                    foreach (self::COLUMNS as $col) {
                        $row[$col] = round($cols[$col], 2);
                        $subtotal[$col] += $cols[$col];
                    }

                    $rows[] = $row;
                }
            }

            usort($rows, function ($a, $b) {
                $byUser = strcmp($a['user_name'], $b['user_name']);
                if ($byUser !== 0) {
                    return $byUser;
                }
                if ($a['group_name'] === self::UNASSIGNED_GROUP_LABEL) {
                    return 1;
                }
                if ($b['group_name'] === self::UNASSIGNED_GROUP_LABEL) {
                    return -1;
                }
                return strcmp($a['group_name'], $b['group_name']);
            });

            foreach (self::COLUMNS as $col) {
                $grand[$col] += $subtotal[$col];
                $subtotal[$col] = round($subtotal[$col], 2);
            }

            $days[] = [
                'date' => $date,
                'date_fmt' => Carbon::parse($date)->format('d-m-Y (D)'),
                'rows' => $rows,
                'subtotal' => $subtotal,
            ];
        }

        foreach (self::COLUMNS as $col) {
            $grand[$col] = round($grand[$col], 2);
        }

        return [
            'from_date' => $from,
            'to_date' => $to,
            'user_filter' => $userId ? (optional(DB::table('users')->find($userId))->name ?? 'Unknown') : 'All Users',
            'group_filter' => $groupFilter === null
                ? 'All Groups'
                : ($groupFilter === self::UNASSIGNED_FILTER
                    ? self::UNASSIGNED_GROUP_LABEL
                    : ($groupNames[$groupFilter] ?? 'Unknown')),
            'days' => $days,
            'grand_total' => $grand,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | AGGREGATION
    |--------------------------------------------------------------------------
    */

    private function groupLabelForItemCode(?string $itemCode, array $ctx): string
    {
        if (!$itemCode) {
            return self::UNASSIGNED_GROUP_LABEL;
        }

        $groupId = $ctx['itemToGroupId'][$itemCode] ?? null;

        return $groupId ? ($ctx['groupNames'][$groupId] ?? self::UNASSIGNED_GROUP_LABEL) : self::UNASSIGNED_GROUP_LABEL;
    }

    /** Whether the (already resolved) group label passes the group filter. */
    private function groupAllowed(string $label, array $ctx): bool
    {
        $filter = $ctx['groupFilter'];

        if ($filter === null) {
            return true;
        }

        if ($filter === self::UNASSIGNED_FILTER) {
            return $label === self::UNASSIGNED_GROUP_LABEL;
        }

        return $label === ($ctx['groupNames'][$filter] ?? null);
    }

    private function accumulateReceiptsAndRefunds(array &$totals, array &$userNames, array $ctx, string $from, string $to): void
    {
        $query = DB::table('daily_transactions as dt')
            ->leftJoin('invoices as inv', 'inv.invoice_no', '=', 'dt.invoice_reference')
            ->whereIn('dt.transaction_type', ['RECEIVED', 'REFUND'])
            ->where('dt.status', '!=', 'CANCELLED')
            ->whereDate('dt.transaction_date', '>=', $from)
            ->whereDate('dt.transaction_date', '<=', $to);

        if ($ctx['userId']) {
            $query->where('dt.operator_id', $ctx['userId']);
        }

        $transactions = $query->select(
            'dt.transaction_date',
            'dt.transaction_type',
            'dt.payment_mode',
            'dt.received_amount',
            'dt.refund_amount',
            'dt.operator_id',
            'dt.operator_name',
            'inv.invoice_type',
            DB::raw('COALESCE(inv.invoice_no, dt.invoice_reference) as invoice_no')
        )->get();

        $weightMaps = $this->buildInvoiceWeightMaps(
            $transactions
                ->filter(fn ($t) => $t->invoice_type !== 'DOCTOR_VISIT')
                ->pluck('invoice_no')
                ->filter()
                ->unique()
                ->values()
        );

        foreach ($transactions as $t) {

            $date = substr($t->transaction_date, 0, 10);
            $uid = $t->operator_id;
            $userNames[$uid] = $t->operator_name ?? 'Unknown';

            $weights = $t->invoice_type === 'DOCTOR_VISIT'
                ? [self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE['DOCTOR_VISIT'] => 1.0]
                : ($weightMaps[$t->invoice_no] ?? [null => 1.0]);

            $isCash = $t->payment_mode === 'Cash';

            foreach ($weights as $itemCode => $share) {

                $label = $this->groupLabelForItemCode($itemCode, $ctx);

                if (!$this->groupAllowed($label, $ctx)) {
                    continue;
                }

                if ($t->transaction_type === 'RECEIVED') {
                    $this->addAmount($totals, $date, $uid, $label, $isCash ? 'collection_cash' : 'collection_noncash', (float) $t->received_amount * $share);
                } else {
                    $this->addAmount($totals, $date, $uid, $label, 'refund', (float) $t->refund_amount * $share);
                }
            }
        }
    }

    private function buildInvoiceWeightMaps($invoiceNos): array
    {
        if ($invoiceNos->isEmpty()) {
            return [];
        }

        $detailRows = [];
        foreach ($invoiceNos->chunk(1000) as $chunk) {
            $detailRows = array_merge($detailRows, DB::table('invoice_details')
                ->whereIn('invoice_no', $chunk)
                ->select('invoice_no', 'item_code', DB::raw('SUM(amount) as line_total'))
                ->groupBy('invoice_no', 'item_code')
                ->get()
                ->all());
        }

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

    private function accumulateDoctorPayables(array &$totals, array &$userNames, array $ctx, string $from, string $to): void
    {
        $query = DB::table('doctor_payables as dp')
            ->leftJoin('users as u', 'u.id', '=', 'dp.created_by')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'dp.invoice_id')
            ->where('dp.payment_status', '!=', 'CANCELLED')
            ->where(function ($q) {
                $q->whereNull('inv.cancelled')
                    ->orWhere('inv.cancelled', '!=', 'Y')
                    ->orWhere('dp.payment_status', 'PAID');
            })
            ->whereDate('dp.created_at', '>=', $from)
            ->whereDate('dp.created_at', '<=', $to);

        if ($ctx['userId']) {
            $query->where('dp.created_by', $ctx['userId']);
        }

        $rows = $query->select('dp.created_at', 'dp.item_code', 'dp.invoice_type', 'dp.created_by', 'u.name as user_name', 'dp.payable_amount')->get();

        foreach ($rows as $row) {

            $uid = $row->created_by;
            $userNames[$uid] = $row->user_name ?? 'Unknown';

            $itemCode = $row->invoice_type === 'DOCTOR_VISIT'
                ? self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE['DOCTOR_VISIT']
                : $row->item_code;

            $label = $this->groupLabelForItemCode($itemCode, $ctx);

            if (!$this->groupAllowed($label, $ctx)) {
                continue;
            }

            $this->addAmount($totals, substr($row->created_at, 0, 10), $uid, $label, 'doctor_payable', (float) $row->payable_amount);
        }
    }

    private function accumulateDoctorPayments(array &$totals, array &$userNames, array $ctx, string $from, string $to): void
    {
        $query = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->leftJoin('users as u', 'u.id', '=', 'ds.created_by')
            ->where('ds.status', '!=', 'CANCELLED')
            ->whereDate('ds.settlement_date', '>=', $from)
            ->whereDate('ds.settlement_date', '<=', $to);

        if ($ctx['userId']) {
            $query->where('ds.created_by', $ctx['userId']);
        }

        $rows = $query->select('ds.settlement_date', 'dsi.item_code', 'dsi.invoice_type', 'ds.created_by', 'u.name as user_name', 'ds.payment_mode', 'dsi.settlement_amount')->get();

        foreach ($rows as $row) {

            $uid = $row->created_by;
            $userNames[$uid] = $row->user_name ?? 'Unknown';

            $itemCode = $row->invoice_type === 'DOCTOR_VISIT'
                ? self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE['DOCTOR_VISIT']
                : $row->item_code;

            $label = $this->groupLabelForItemCode($itemCode, $ctx);

            if (!$this->groupAllowed($label, $ctx)) {
                continue;
            }

            $date = substr($row->settlement_date, 0, 10);
            $amount = (float) $row->settlement_amount;

            $this->addAmount($totals, $date, $uid, $label, 'paid_to_doctor', $amount);

            if ($row->payment_mode === 'CASH') {
                $this->addAmount($totals, $date, $uid, $label, 'paid_to_doctor_cash', $amount);
            }
        }
    }

    private function addAmount(array &$totals, string $date, $userId, string $groupLabel, string $column, float $amount): void
    {
        if (!isset($totals[$date][$userId][$groupLabel])) {
            $totals[$date][$userId][$groupLabel] = $this->emptyRow() + ['paid_to_doctor_cash' => 0.0];
        }

        $totals[$date][$userId][$groupLabel][$column] += $amount;
    }

    private function emptyRow(): array
    {
        return array_fill_keys(self::COLUMNS, 0.0);
    }
}
