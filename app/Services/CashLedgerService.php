<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shared cash-reconciliation logic for a single staff user over a date
 * range (a single day is just $fromDate === $toDate). Originally lived
 * only in CashSubmissionReportController for a single day; extracted so
 * the Employee Performance Dashboard's multi-day, multi-employee view
 * can reuse the exact same formulas instead of a second implementation
 * that could quietly drift from this one.
 */
class CashLedgerService
{
    private const UNASSIGNED_GROUP_LABEL = 'Unassigned (No Reporting Group)';

    // DOCTOR_VISIT invoices/settlement items carry no invoice_details row and
    // no item_code of their own -- this is the one fixed invoice_item_masters
    // code for that whole invoice type, mirroring
    // ItemWiseSummaryReportController::DOCTOR_VISIT_ITEM_CODE (same DB, same
    // convention -- kept in sync deliberately, not coincidentally).
    private const DOCTOR_VISIT_ITEM_CODE = 'DOC001';

    private const UNCATEGORIZED_ITEM = 'Uncategorized';

    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Consultation',
        'DIAGNOSTIC' => 'Diagnostic',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

    /**
     * Only DIAGNOSTIC invoices get itemized invoice_details rows (real lab
     * test line items). Every other invoice type never writes to
     * invoice_details at all, so allocateLedgerToReportingGroups() would
     * always dump them into "Unassigned" for lack of a line item to key
     * off of. These are each invoice type's single canonical
     * invoice_item_masters code (mirrors DoctorPayableController's DOC002
     * doctor-visit code and the MEM001/DON001 convention already used
     * elsewhere), used as a stand-in item_code so the reporting-group
     * lookup has something to match against.
     */
    private const FALLBACK_ITEM_CODE_BY_INVOICE_TYPE = [
        'DOCTOR_VISIT' => 'DOC001',
        'OXYGEN_RENT' => 'OXY001',
        'CONCENTRATOR_RENT' => 'CON001',
        'AMBULANCE_RENT' => 'AMB001',
        'MEMBERSHIP_FEE' => 'MEM001',
        'OTHER_INCOME' => 'DON001',
    ];

    /**
     * Aggregate-only totals for one user over a date range -- no per-line
     * ledger, no group breakdown. Used where many users are summarized at
     * once (the performance dashboard's list view) and building the full
     * line-by-line ledger for every one of them would be wasteful.
     */
    public function buildSummary($userId, string $fromDate, string $toDate): array
    {
        $invoiceQuery = DB::table('invoices')
            ->where('created_by', $userId)
            ->whereBetween('invoice_date', [$fromDate, $toDate]);

        $invoiceCount = (clone $invoiceQuery)->count();

        $invoiceNos = (clone $invoiceQuery)->pluck('invoice_no');

        // Cash vs non-cash is determined per PAYMENT EVENT
        // (daily_transactions), not by invoices.payment_mode -- that column
        // only ever holds whichever payment (initial, phase 1, or phase 2)
        // was recorded LAST, so an invoice paid partly by card and partly
        // by cash was previously having its ENTIRE total_amount dumped into
        // a single bucket based on whatever mode happened to be collected
        // last. BINARY forces the same case-sensitive comparison used
        // throughout this file (MySQL's default collation treats 'Cash'
        // and 'CASH' as equal).
        $receivedByMode = DB::table('daily_transactions')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', 'ACTIVE')
            ->whereIn('invoice_reference', $invoiceNos)
            ->select(
                DB::raw('SUM(CASE WHEN BINARY payment_mode = \'Cash\' THEN received_amount ELSE 0 END) as cash_total'),
                DB::raw('SUM(CASE WHEN BINARY payment_mode != \'Cash\' OR payment_mode IS NULL THEN received_amount ELSE 0 END) as non_cash_total')
            )
            ->first();

        $cashCollected = $receivedByMode->cash_total ?? 0;
        $nonCashCollected = $receivedByMode->non_cash_total ?? 0;

        $cashRefunded = DB::table('daily_transactions')
            ->where('transaction_type', 'REFUND')
            ->where('payment_mode', 'Cash')
            ->whereIn('invoice_reference', $invoiceNos)
            ->sum('refund_amount');

        $cashPaidToDoctors = DB::table('doctor_settlement_items')
            ->join('doctor_settlements', 'doctor_settlements.id', '=', 'doctor_settlement_items.settlement_id')
            ->where('doctor_settlements.payment_mode', 'CASH')
            ->where('doctor_settlements.status', '!=', 'CANCELLED')
            ->where('doctor_settlements.created_by', $userId)
            ->whereBetween('doctor_settlements.settlement_date', [$fromDate, $toDate])
            ->sum('doctor_settlement_items.settlement_amount');

        $cashCollected = round((float) $cashCollected, 2);
        $cashRefunded = round((float) $cashRefunded, 2);
        $cashPaidToDoctors = round((float) $cashPaidToDoctors, 2);

        return [
            'invoice_count' => $invoiceCount,
            'cash_collected' => $cashCollected,
            'non_cash_collected' => round((float) $nonCashCollected, 2),
            'cash_refunded' => $cashRefunded,
            'cash_paid_to_doctors' => $cashPaidToDoctors,
            'net_cash_to_deposit' => round($cashCollected - $cashRefunded - $cashPaidToDoctors, 2),
        ];
    }

    /**
     * "Category" here is invoice_item_masters.item_name (the Diagnostic Test
     * Category Master -- Pathology, USG, Dental, Doctor Visit, Oxygen
     * Cylinder Rent, etc.), NOT reporting_groups and NOT the coarse
     * INVOICE_TYPE_LABELS map used elsewhere in this file. Same
     * user/date-scoped source rows as buildSummary()/buildLedger() (so the
     * grand total row here reconciles exactly with those), just split by
     * category using the same proportional-weight technique as
     * ItemWiseSummaryReportController -- a multi-line DIAGNOSTIC invoice's
     * amount is divided across its invoice_details.item_code rows by each
     * line's own share of the invoice total; DOCTOR_VISIT invoices carry no
     * invoice_details row at all, so they're attributed wholesale to
     * DOCTOR_VISIT_ITEM_CODE instead.
     */
    public function buildCategoryWiseSummary($userId, string $fromDate, string $toDate): array
    {
        $itemCodeToName = DB::table('invoice_item_masters')->pluck('item_name', 'item_code')->toArray();

        $invoices = DB::table('invoices')
            ->where('created_by', $userId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->get(['id', 'invoice_no', 'invoice_type', 'item_code']);

        $invoiceByNo = $invoices->keyBy('invoice_no');

        $weightMaps = $this->buildCategoryWeightMaps(
            $invoices->where('invoice_type', '!=', 'DOCTOR_VISIT')->pluck('invoice_no')->values()
        );

        $weightsForInvoice = function ($invoice) use ($weightMaps) {

            if ($invoice->invoice_type === 'DOCTOR_VISIT') {
                return [self::DOCTOR_VISIT_ITEM_CODE => 1.0];
            }

            return $weightMaps[$invoice->invoice_no] ?? [self::UNCATEGORIZED_ITEM => 1.0];
        };

        $totals = [];

        /*
        |------------------------------------------------------------------
        | 1) COLLECTIONS -- cash and non-cash, split by category. Classified
        |    per payment EVENT (daily_transactions), not by
        |    invoices.payment_mode -- see buildLedger()'s matching comment
        |    for why. Each transaction's own amount is split across
        |    categories using the invoice's weight map (the category
        |    composition doesn't change between payment phases, only how
        |    much of it -- and in what mode -- has been collected so far).
        |------------------------------------------------------------------
        */

        $receivedTxns = DB::table('daily_transactions')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', 'ACTIVE')
            ->whereIn('invoice_reference', $invoiceByNo->keys())
            ->get(['invoice_reference', 'received_amount', 'payment_mode']);

        foreach ($receivedTxns as $txn) {

            $invoice = $invoiceByNo->get($txn->invoice_reference);

            if (!$invoice) {
                continue;
            }

            $column = $txn->payment_mode === 'Cash' ? 'cash_collected' : 'non_cash_collected';

            foreach ($weightsForInvoice($invoice) as $itemCode => $share) {
                $this->addCategoryAmount($totals, $itemCode, $column, (float) $txn->received_amount * $share);
            }
        }

        /*
        |------------------------------------------------------------------
        | 2) CASH REFUNDS against this user's invoices, split the same way
        |------------------------------------------------------------------
        */

        $refunds = DB::table('daily_transactions')
            ->where('transaction_type', 'REFUND')
            ->where('payment_mode', 'Cash')
            ->whereIn('invoice_reference', $invoiceByNo->keys())
            ->get(['invoice_reference', 'refund_amount']);

        foreach ($refunds as $refund) {

            $invoice = $invoiceByNo->get($refund->invoice_reference);

            if (!$invoice) {
                $this->addCategoryAmount($totals, self::UNCATEGORIZED_ITEM, 'refund', (float) $refund->refund_amount);
                continue;
            }

            foreach ($weightsForInvoice($invoice) as $itemCode => $share) {
                $this->addCategoryAmount($totals, $itemCode, 'refund', (float) $refund->refund_amount * $share);
            }
        }

        /*
        |------------------------------------------------------------------
        | 3) CASH DOCTOR PAYMENTS made by this user -- doctor_settlement_items
        |    already carries its own item_code for every type except
        |    DOCTOR_VISIT (empty item_code there, no line-item exists for a
        |    consultation), so no weight split needed here.
        |------------------------------------------------------------------
        */

        $doctorPayments = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->where('ds.payment_mode', 'CASH')
            ->where('ds.status', '!=', 'CANCELLED')
            ->where('ds.created_by', $userId)
            ->whereBetween('ds.settlement_date', [$fromDate, $toDate])
            ->select('dsi.item_code', 'dsi.invoice_type', 'dsi.settlement_amount')
            ->get();

        foreach ($doctorPayments as $payment) {

            $itemCode = $payment->invoice_type === 'DOCTOR_VISIT'
                ? self::DOCTOR_VISIT_ITEM_CODE
                : ($payment->item_code ?: self::UNCATEGORIZED_ITEM);

            $this->addCategoryAmount($totals, $itemCode, 'doctor_payment', (float) $payment->settlement_amount);
        }

        /*
        |------------------------------------------------------------------
        | ASSEMBLE ROWS -- amount_to_deposit is deliberately cash-only
        |    (cash_collected - refund - doctor_payment), same formula as
        |    buildSummary()'s net_cash_to_deposit -- non-cash collection is
        |    shown for visibility but never enters the deposit figure since
        |    it never physically passes through this user's hands as cash.
        |------------------------------------------------------------------
        */

        $rows = collect($totals)
            ->map(function ($cols, $itemCode) use ($itemCodeToName) {

                $cashCollected = round($cols['cash_collected'], 2);
                $nonCashCollected = round($cols['non_cash_collected'], 2);
                $refund = round($cols['refund'], 2);
                $doctorPayment = round($cols['doctor_payment'], 2);

                return [
                    'item_code' => $itemCode,
                    'item_name' => $itemCodeToName[$itemCode] ?? self::UNCATEGORIZED_ITEM,
                    'cash_collected' => $cashCollected,
                    'non_cash_collected' => $nonCashCollected,
                    'total_collected' => round($cashCollected + $nonCashCollected, 2),
                    'refund' => $refund,
                    'doctor_payment' => $doctorPayment,
                    'amount_to_deposit' => round($cashCollected - $refund - $doctorPayment, 2),
                ];
            })
            ->sortBy('item_name')
            ->values();

        $grandTotal = [
            'cash_collected' => round($rows->sum('cash_collected'), 2),
            'non_cash_collected' => round($rows->sum('non_cash_collected'), 2),
            'total_collected' => round($rows->sum('total_collected'), 2),
            'refund' => round($rows->sum('refund'), 2),
            'doctor_payment' => round($rows->sum('doctor_payment'), 2),
            'amount_to_deposit' => round($rows->sum('amount_to_deposit'), 2),
        ];

        return [
            'rows' => $rows->all(),
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * For each invoice_no, what fraction of its total belongs to each
     * item_code -- derived from each invoice_details line's own share of
     * the invoice's line-item total. Mirrors
     * ItemWiseSummaryReportController::buildInvoiceWeightMaps() exactly
     * (same problem, same DB shape); kept as a separate copy here rather
     * than a shared dependency since the two controllers' surrounding
     * scoping (user+single-day vs global+date-range) differs enough that
     * sharing just this one fragment wasn't worth the indirection.
     */
    private function buildCategoryWeightMaps($invoiceNos): array
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
            $code = $d->item_code ?: self::UNCATEGORIZED_ITEM;

            $weightMaps[$d->invoice_no][$code] = $total > 0 ? ((float) $d->line_total / $total) : 0;
        }

        return $weightMaps;
    }

    private function addCategoryAmount(array &$totals, string $itemCode, string $column, float $amount): void
    {
        if (!isset($totals[$itemCode])) {
            $totals[$itemCode] = [
                'cash_collected' => 0.0,
                'non_cash_collected' => 0.0,
                'refund' => 0.0,
                'doctor_payment' => 0.0,
            ];
        }

        $totals[$itemCode][$column] += $amount;
    }

    /**
     * Full per-transaction ledger plus reporting-group breakdown for one
     * user over a date range. Same formula as buildSummary() above, just
     * itemized -- used for the single-user drill-down view.
     */
    public function buildLedger($userId, string $fromDate, string $toDate): array
    {
        $invoices = DB::table('invoices')
            ->where('created_by', $userId)
            ->whereBetween('invoice_date', [$fromDate, $toDate])
            ->get(['id', 'invoice_no', 'invoice_type', 'invoice_date', 'patient_name']);

        $invoiceByNo = $invoices->keyBy('invoice_no');

        $ledger = [];

        $cashCollected = 0;
        $cashRefunded = 0;
        $cashPaidToDoctors = 0;

        /*
        |----------------------------------------------------------------
        | 1) COLLECTIONS -- per payment EVENT (daily_transactions), not per
        |    invoice. invoices.payment_mode only holds whichever payment
        |    (initial, phase 1, or phase 2) was recorded LAST, so an
        |    invoice paid partly by card and partly by cash was previously
        |    having its ENTIRE total_amount misclassified into a single
        |    bucket based on the last mode used. Each RECEIVED transaction
        |    is its own ledger line here (using ITS OWN time, which is also
        |    more accurate than the invoice's creation time for a later
        |    due-payment phase).
        |----------------------------------------------------------------
        */

        $receivedTxns = DB::table('daily_transactions')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', 'ACTIVE')
            ->whereIn('invoice_reference', $invoiceByNo->keys())
            ->get(['invoice_reference', 'received_amount', 'payment_mode', 'created_at']);

        $nonCashCollected = 0;

        foreach ($receivedTxns as $txn) {

            $invoice = $invoiceByNo->get($txn->invoice_reference);

            if (!$invoice) {
                continue;
            }

            $amount = round((float) $txn->received_amount, 2);

            if ($txn->payment_mode !== 'Cash') {
                $nonCashCollected += $amount;
                continue;
            }

            $cashCollected += $amount;

            $ledger[] = [
                'type' => 'Collection',
                'invoice_no' => $invoice->invoice_no,
                'invoice_type' => $invoice->invoice_type,
                'category' => self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type,
                'transaction_no' => $invoice->invoice_no,
                'transaction_to' => $invoice->patient_name,
                'invoice_date' => $invoice->invoice_date,
                'receive_time' => $txn->created_at,
                'doctor_settlement_time' => null,
                'refund_time' => null,
                'amount' => $amount,
            ];
        }

        /*
        |----------------------------------------------------------------
        | 2) CASH REFUNDS (against this user's invoices in the range)
        |----------------------------------------------------------------
        */

        $refunds = DB::table('daily_transactions')
            ->where('transaction_type', 'REFUND')
            ->where('payment_mode', 'Cash')
            ->whereIn('invoice_reference', $invoiceByNo->keys())
            ->get(['transaction_no', 'invoice_reference', 'patient_name', 'refund_amount', 'created_at']);

        foreach ($refunds as $refund) {

            $invoice = $invoiceByNo->get($refund->invoice_reference);

            $amount = round((float) $refund->refund_amount, 2);

            $cashRefunded += $amount;

            $ledger[] = [
                'type' => 'Refund',
                'invoice_no' => $refund->invoice_reference,
                'invoice_type' => $invoice->invoice_type ?? null,
                'category' => $invoice ? (self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type) : '-',
                'transaction_no' => $refund->transaction_no,
                'transaction_to' => $refund->patient_name,
                'invoice_date' => $invoice->invoice_date ?? null,
                'receive_time' => null,
                'doctor_settlement_time' => null,
                'refund_time' => $refund->created_at,
                'amount' => -$amount,
            ];
        }

        /*
        |----------------------------------------------------------------
        | 3) CASH PAYMENTS TO DOCTORS -- settlements MADE BY this user
        |    within the range (doctor_settlements.settlement_date +
        |    created_by), regardless of which date the underlying invoice
        |    was raised on. A referral doctor is often settled in a batch
        |    days after the invoice date, so matching against invoices in
        |    the range would miss (or misdate) these payments; the
        |    settlement's own date is what actually needs to be adjusted
        |    against this range's cash.
        |----------------------------------------------------------------
        */

        $doctorPayments = DB::table('doctor_settlement_items')
            ->join('doctor_settlements', 'doctor_settlements.id', '=', 'doctor_settlement_items.settlement_id')
            ->where('doctor_settlements.payment_mode', 'CASH')
            ->where('doctor_settlements.status', '!=', 'CANCELLED')
            ->where('doctor_settlements.created_by', $userId)
            ->whereBetween('doctor_settlements.settlement_date', [$fromDate, $toDate])
            ->get([
                'doctor_settlement_items.invoice_no',
                'doctor_settlement_items.invoice_type',
                'doctor_settlement_items.settlement_amount',
                'doctor_settlement_items.created_at as item_created_at',
                'doctor_settlements.settlement_no',
                'doctor_settlements.doctor_name',
            ]);

        $doctorPaymentInvoiceDates = DB::table('invoices')
            ->whereIn('invoice_no', collect($doctorPayments)->pluck('invoice_no')->unique())
            ->pluck('invoice_date', 'invoice_no');

        $doctorPaymentDetail = [];

        foreach ($doctorPayments as $payment) {

            $invoiceDate = $invoiceByNo->get($payment->invoice_no)?->invoice_date
                ?? $doctorPaymentInvoiceDates[$payment->invoice_no]
                ?? null;

            $amount = round((float) $payment->settlement_amount, 2);

            $cashPaidToDoctors += $amount;

            $ledger[] = [
                'type' => 'Doctor Payment',
                'invoice_no' => $payment->invoice_no,
                'invoice_type' => $payment->invoice_type,
                'category' => self::INVOICE_TYPE_LABELS[$payment->invoice_type] ?? $payment->invoice_type,
                'transaction_no' => $payment->settlement_no,
                'transaction_to' => $payment->doctor_name,
                'invoice_date' => $invoiceDate,
                'receive_time' => null,
                'doctor_settlement_time' => $payment->item_created_at,
                'refund_time' => null,
                'amount' => -$amount,
            ];

            $doctorPaymentDetail[] = [
                'invoice_no' => $payment->invoice_no,
                'doctor_name' => $payment->doctor_name,
                'settlement_no' => $payment->settlement_no,
                'settlement_time' => $payment->item_created_at
                    ? Carbon::parse($payment->item_created_at)->format('h:i A')
                    : '-',
                'amount' => $amount,
            ];
        }

        /*
        |----------------------------------------------------------------
        | GROUP-WISE CASH DEPOSIT BREAKDOWN
        | (invoice_details -> reporting_group_items -> reporting_groups)
        |----------------------------------------------------------------
        */

        $groupBreakdown = $this->allocateLedgerToReportingGroups($ledger);

        /*
        |----------------------------------------------------------------
        | SORT (chronological, by whichever timestamp is populated)
        |----------------------------------------------------------------
        */

        usort($ledger, function ($a, $b) {

            $timeA = $a['receive_time'] ?? $a['refund_time'] ?? $a['doctor_settlement_time'];
            $timeB = $b['receive_time'] ?? $b['refund_time'] ?? $b['doctor_settlement_time'];

            return strcmp((string) $timeA, (string) $timeB);
        });

        foreach ($ledger as &$row) {

            $time = $row['receive_time'] ?? $row['refund_time'] ?? $row['doctor_settlement_time'];

            $row['invoice_date_fmt'] = $row['invoice_date'] ? Carbon::parse($row['invoice_date'])->format('d-m-Y') : '-';
            $row['time_fmt'] = $time ? Carbon::parse($time)->format('h:i A') : '-';
        }
        unset($row);

        $cashCollected = round($cashCollected, 2);
        $cashRefunded = round($cashRefunded, 2);
        $cashPaidToDoctors = round($cashPaidToDoctors, 2);

        return [
            'ledger' => $ledger,
            'doctor_payments' => $doctorPaymentDetail,
            'group_breakdown' => $groupBreakdown,
            'summary' => [
                'invoice_count' => $invoices->count(),
                'cash_collected' => $cashCollected,
                'non_cash_collected' => round((float) $nonCashCollected, 2),
                'cash_refunded' => $cashRefunded,
                'cash_paid_to_doctors' => $cashPaidToDoctors,
                'net_cash_to_deposit' => round($cashCollected - $cashRefunded - $cashPaidToDoctors, 2),
            ],
        ];
    }

    /**
     * Allocate every signed ledger amount (collection +, refund/doctor payment -)
     * across reporting groups, using invoice_details.item_code as the link.
     * An invoice with multiple line items splits its amount proportionally
     * by each line's amount. Items with no reporting group land in an
     * "Unassigned" bucket so the breakdown always reconciles to the total.
     */
    private function allocateLedgerToReportingGroups(array $ledger): array
    {
        $invoiceNos = collect($ledger)->pluck('invoice_no')->unique()->values();

        $detailsByInvoice = DB::table('invoice_details')
            ->whereIn('invoice_no', $invoiceNos)
            ->get(['invoice_no', 'item_code', 'item_description', 'amount'])
            ->groupBy('invoice_no');

        $itemToGroupId = DB::table('reporting_group_items')->pluck('reporting_group_id', 'item_code');

        $groupNames = DB::table('reporting_groups')->pluck('group_name', 'id');

        $totals = [];

        // Tracks which specific items/invoices actually fell into the
        // Unassigned bucket, keyed to dedupe, so the UI can tell someone
        // exactly what to map into a reporting group instead of just
        // showing an unexplained leftover total.
        $unassignedItems = [];

        foreach ($ledger as $row) {

            $amount = (float) $row['amount'];

            $items = $detailsByInvoice->get($row['invoice_no'], collect());

            $totalLineAmount = $items->sum('amount');

            if ($items->isEmpty() || $totalLineAmount <= 0) {

                $fallbackCode = self::FALLBACK_ITEM_CODE_BY_INVOICE_TYPE[$row['invoice_type'] ?? null] ?? null;

                $groupId = $fallbackCode ? ($itemToGroupId[$fallbackCode] ?? null) : null;

                if ($groupId) {

                    $label = $groupNames[$groupId] ?? self::UNASSIGNED_GROUP_LABEL;

                    $totals[$label] = ($totals[$label] ?? 0) + $amount;

                    continue;
                }

                $totals[self::UNASSIGNED_GROUP_LABEL] = ($totals[self::UNASSIGNED_GROUP_LABEL] ?? 0) + $amount;

                $key = 'no-billing-detail:' . $row['invoice_no'];
                $unassignedItems[$key] = [
                    'item_code' => null,
                    'item_description' => '(No billing line items found on this invoice)',
                    'invoice_no' => $row['invoice_no'],
                ];

                continue;
            }

            foreach ($items as $item) {

                $share = $amount * ((float) $item->amount / $totalLineAmount);

                $groupId = $itemToGroupId[$item->item_code] ?? null;

                $label = $groupId ? ($groupNames[$groupId] ?? self::UNASSIGNED_GROUP_LABEL) : self::UNASSIGNED_GROUP_LABEL;

                $totals[$label] = ($totals[$label] ?? 0) + $share;

                if ($label === self::UNASSIGNED_GROUP_LABEL) {

                    $key = $item->item_code . '|' . $row['invoice_no'];
                    $unassignedItems[$key] = [
                        'item_code' => $item->item_code,
                        'item_description' => $item->item_description,
                        'invoice_no' => $row['invoice_no'],
                    ];
                }
            }
        }

        $breakdown = collect($totals)
            ->map(fn($amount, $label) => [
                'group_name' => $label,
                'amount' => round($amount, 2),
                'items' => $label === self::UNASSIGNED_GROUP_LABEL
                    ? array_values($unassignedItems)
                    : [],
            ])
            ->values()
            ->sortBy('group_name')
            ->values()
            ->all();

        return $breakdown;
    }
}
