<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\DailyTransactionRecorder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partial cancellation of a DIAGNOSTIC invoice.
 *
 * One or more billed lines are cancelled; the original invoice is marked
 * Cancelled/superseded and a "Modified Invoice" (new number) is issued for
 * the remaining lines. The remaining lines are MOVED (same invoice_details
 * ids) to the new invoice, so any report already written against them stays
 * linked -- report tables' invoice_no is re-pointed to the new invoice.
 * The cancelled lines stay on the original invoice, which is where the
 * Modified Invoice PDF reads them from (invoice_partial_cancellations).
 *
 * Money: the new total is recomputed from the remaining lines. Whatever
 * the patient already paid stays with them: refund = max(0, paid - newTotal),
 * due = max(0, newTotal - paid). Only the refund is written to the ledger.
 * Each remaining line's stored paid share is re-allocated over the new total.
 */
class PartialInvoiceCancellationService
{
    /** Tables holding report data for a billed line (invoice_detail_id). */
    private const LINE_REPORT_TABLES = [
        'test_result_entries',
        'usg_report_findings',
        'non_pathology_report_findings',
        'cardiology_report_findings',
        'pathology_report_finding_items',
        'report_created_offline',
    ];

    /** Tables whose rows are tied to the invoice_no as a whole and follow the remaining lines. */
    private const INVOICE_NO_TABLES = [
        'pathology_report_findings',
        'cardiology_report_findings',
        'non_pathology_report_findings',
        'usg_report_findings',
        'test_result_entries',
        'xray_report_uploads',
        'report_created_offline',
        'test_report_confirmations',
        'test_report_deliveries',
    ];

    public static function lineDiscount($row): float
    {
        $r = (float) ($row->rate ?? 0);

        return self::roundToTen($r * (float) ($row->standard_discount ?? 0) / 100)
            + self::roundToTen($r * (float) ($row->additional_discount_percent ?? 0) / 100)
            + (float) ($row->additional_discount_amount ?? 0);
    }

    private static function roundToTen(float $v): float
    {
        return round($v / 10) * 10;
    }

    /**
     * Billable lines of an invoice with whether each can be cancelled.
     * Package component rows travel with their parent package line.
     *
     * @return array{eligible: bool, reason: ?string, lines: array}
     */
    public function describe(Invoice $invoice): array
    {
        if ($invoice->invoice_type !== 'DIAGNOSTIC') {
            return ['eligible' => false, 'reason' => 'Partial cancellation is available for Diagnostic invoices only.', 'lines' => []];
        }

        if ($invoice->cancelled === 'Y') {
            return ['eligible' => false, 'reason' => 'This invoice is already cancelled.', 'lines' => []];
        }

        $rows = DB::table('invoice_details')->where('invoice_no', $invoice->invoice_no)->orderBy('id')->get();
        $billable = $rows->whereNull('package_parent_item_code_sub');

        if ($billable->count() < 2) {
            return ['eligible' => false, 'reason' => 'Only one item on this invoice. Use full invoice cancellation.', 'lines' => []];
        }

        $xrayUploaded = DB::table('xray_report_uploads')->where('invoice_no', $invoice->invoice_no)->exists();

        $lines = [];
        foreach ($billable as $row) {
            $ids = $this->groupIds($rows, $row);
            $reason = null;

            if ($this->reportExistsForLines($ids)) {
                $reason = 'Report Already Generated';
            } elseif ($xrayUploaded && $invoice->invoice_category !== 'PATHOLOGY') {
                $reason = 'X-ray Report Already Uploaded';
            }

            $lines[] = [
                'id' => $row->id,
                'item_description' => $row->item_description,
                'rate' => (float) $row->rate,
                'discount' => self::lineDiscount($row),
                'amount' => (float) $row->amount,
                'can_cancel' => $reason === null,
                'block_reason' => $reason,
            ];
        }

        return ['eligible' => true, 'reason' => null, 'lines' => $lines];
    }

    /**
     * @param  int[]  $detailIds  billable lines to cancel
     * @return array{new_invoice: Invoice, refund: float, due: float, new_total: float, cancelled_amount: float}
     * @throws \RuntimeException with a user-facing message
     */
    public function cancel(
        Invoice $invoice,
        array $detailIds,
        string $remarks,
        int $userId,
        ?int $approverId,
        float $collect = 0,
        ?string $collectMode = null
    ): array {
        $detailIds = array_values(array_unique(array_map('intval', $detailIds)));

        if (!$detailIds) {
            throw new \RuntimeException('Select at least one item to cancel.');
        }

        // lock the invoice so two simultaneous requests can't both run
        $invoice = Invoice::where('id', $invoice->id)->lockForUpdate()->firstOrFail();

        $info = $this->describe($invoice);

        if (!$info['eligible']) {
            throw new \RuntimeException($info['reason']);
        }

        $linesById = collect($info['lines'])->keyBy('id');

        foreach ($detailIds as $id) {
            $line = $linesById->get($id);

            if (!$line) {
                throw new \RuntimeException('An item selected is not on this invoice or was already cancelled.');
            }

            if (!$line['can_cancel']) {
                throw new \RuntimeException('"' . $line['item_description'] . '" cannot be cancelled: ' . $line['block_reason'] . '.');
            }
        }

        if (count($detailIds) >= count($info['lines'])) {
            throw new \RuntimeException('All items are selected. Use full invoice cancellation instead.');
        }

        $allRows = DB::table('invoice_details')->where('invoice_no', $invoice->invoice_no)->orderBy('id')->get();

        $cancelRows = collect();
        foreach ($allRows->whereNull('package_parent_item_code_sub') as $row) {
            if (in_array($row->id, $detailIds, true)) {
                $cancelRows->push($row);
            }
        }

        $cancelGroupIds = [];
        foreach ($cancelRows as $row) {
            $cancelGroupIds = array_merge($cancelGroupIds, $this->groupIds($allRows, $row));
        }

        $remainingRows = $allRows->reject(fn ($r) => in_array($r->id, $cancelGroupIds, true))->values();
        $remainingBillable = $remainingRows->whereNull('package_parent_item_code_sub');

        // ---- fresh totals -------------------------------------------------
        $newTotal = round((float) $remainingBillable->sum('amount'), 2);
        $cancelledAmount = round((float) $cancelRows->sum('amount'), 2);
        $cancelledDiscount = round((float) $cancelRows->sum(fn ($r) => self::lineDiscount($r)), 2);
        $newDiscount = max(0, round((float) $invoice->discount - $cancelledDiscount, 2));

        $paid = round((float) $invoice->paid_amount, 2);
        $refund = round(max(0, $paid - $newTotal), 2);
        $newPaid = round($paid - $refund, 2);
        $newDue = round(max(0, $newTotal - $newPaid), 2);

        // Optional collection of (part of) the remaining due, taken on the spot.
        $collect = round(max(0, $collect), 2);

        if ($collect > $newDue) {
            throw new \RuntimeException('Collection (Rs. ' . number_format($collect, 2)
                . ') cannot exceed the due amount (Rs. ' . number_format($newDue, 2) . ').');
        }

        if ($collect > 0 && !$collectMode) {
            throw new \RuntimeException('Select a payment mode for the amount collected.');
        }

        $newPaid = round($newPaid + $collect, 2);
        $newDue = round($newDue - $collect, 2);

        $status = $newDue <= 0 ? 'Paid' : ($newPaid > 0 ? 'Partial' : 'Due');

        $doctorPayment = round((float) $remainingBillable->sum(
            fn ($r) => !empty($r->doctor_payment_waived) ? 0 : (float) ($r->payment_value ?? 0)
        ), 2);

        // ---- new invoice --------------------------------------------------
        $newNo = $this->nextInvoiceNo($userId);

        $copy = Arr::only($invoice->getAttributes(), $invoice->getFillable());

        $newInvoice = Invoice::create(array_merge($copy, [
            'invoice_no' => $newNo,
            'total_amount' => $newTotal,
            'discount' => $newDiscount,
            'paid_amount' => $newPaid,
            'due_amount' => $newDue,
            'status' => $status,
            'doctor_payment_amount' => $doctorPayment,
            'doctor_amount_collected_by' => ($doctorPayment > 0 && $newDue <= 0)
                ? ($invoice->doctor_amount_collected_by ?: $userId)
                : null,
            // a collection taken here is recorded as due-payment phase 1, so later
            // due collections continue from phase 2 as on any other invoice
            'paid_amount_1' => $collect,
            'paid_date_1' => $collect > 0 ? now() : null,
            'paid_amount_2' => 0,
            'paid_date_2' => null,
            'payment_mode_1' => $collect > 0 ? $collectMode : null,
            'payment_mode_2' => null,
            'payment_mode' => $collect > 0 ? $collectMode : $invoice->payment_mode,
            'payment_edit_count' => $collect > 0 ? 1 : 0,
            'refund_amount' => 0,
            'refund_date' => null,
            'cancelled' => 'N',
            'cancelled_by' => null,
            'cancelled_at' => null,
            'cancellation_approved_by' => null,
            'cancellation_remarks' => null,
            'whatsapp_sent_at' => null,
            'whatsapp_status' => null,
            'whatsapp_send_count' => 0,
            'whatsapp_message_id' => null,
            'whatsapp_error' => null,
            'parent_invoice_no' => $invoice->invoice_no,
            'is_modified_invoice' => true,
            'remarks' => trim(($invoice->remarks ? $invoice->remarks . ' | ' : '')
                . 'Modified Invoice - supersedes ' . $invoice->invoice_no),
            'created_by' => $invoice->created_by,
            'updated_by' => $userId,
        ]));

        // ---- move remaining lines + their reports -------------------------
        $remainingIds = $remainingRows->pluck('id')->all();

        DB::table('invoice_details')->whereIn('id', $remainingIds)->update([
            'invoice_no' => $newNo,
            'initial_paid_amount' => 0,
            'initial_paid_date' => null,
            'paid_amount_1' => 0,
            'paid_date_1' => null,
            'paid_amount_2' => 0,
            'paid_date_2' => null,
            'updated_at' => now(),
        ]);

        $this->allocatePaid($remainingIds, $newTotal, $newPaid);

        foreach (self::INVOICE_NO_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'invoice_no')) {
                DB::table($table)->where('invoice_no', $invoice->invoice_no)->update(['invoice_no' => $newNo]);
            }
        }

        // report-delivery WhatsApp history follows the reports; the original INVOICE send stays
        DB::table('whatsapp_message_logs')
            ->where('invoice_no', $invoice->invoice_no)
            ->where('message_type', '!=', 'INVOICE')
            ->update(['invoice_no' => $newNo]);

        // ---- doctor payables ----------------------------------------------
        $cancelSubCodes = $allRows->whereIn('id', $cancelGroupIds)->pluck('item_code_sub')->filter()->unique()->all();

        if ($cancelSubCodes) {
            DB::table('doctor_payables')
                ->where('invoice_id', $invoice->id)
                ->whereIn('item_code_sub', $cancelSubCodes)
                ->whereIn('payment_status', ['PENDING', 'APPROVED'])
                ->update(['payment_status' => 'CANCELLED', 'updated_at' => now()]);
        }

        $movePayables = DB::table('doctor_payables')
            ->where('invoice_id', $invoice->id)
            ->when($cancelSubCodes, fn ($q) => $q->whereNotIn('item_code_sub', $cancelSubCodes))
            ->pluck('id')->all();

        if ($movePayables) {
            DB::table('doctor_payables')->whereIn('id', $movePayables)
                ->update(['invoice_id' => $newInvoice->id, 'invoice_no' => $newNo, 'updated_at' => now()]);

            if (Schema::hasTable('doctor_settlement_items')) {
                DB::table('doctor_settlement_items')->whereIn('payable_id', $movePayables)
                    ->update(['invoice_id' => $newInvoice->id, 'invoice_no' => $newNo]);
            }
        }

        // ---- supersede original -------------------------------------------
        $summary = $cancelRows->pluck('item_description')->implode(', ');

        $invoice->update([
            'status' => 'Cancelled',
            'cancelled' => 'Y',
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_approved_by' => $approverId,
            'cancellation_remarks' => 'Partial cancellation (' . $summary . ') - superseded by '
                . $newNo . '. ' . $remarks,
            'superseded_by_invoice_no' => $newNo,
        ]);

        foreach ($cancelRows as $row) {
            DB::table('invoice_partial_cancellations')->insert([
                'original_invoice_no' => $invoice->invoice_no,
                'new_invoice_no' => $newNo,
                'invoice_detail_id' => $row->id,
                'item_code' => $row->item_code,
                'item_code_sub' => $row->item_code_sub,
                'item_description' => $row->item_description,
                'rate' => $row->rate,
                'discount' => self::lineDiscount($row),
                'amount' => $row->amount,
                'refund_amount' => $refund,
                'remarks' => $remarks,
                'cancelled_by' => $userId,
                'approved_by' => $approverId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(InvoiceReportStatusRecorder::class)->refreshByInvoiceNo($newNo);

        // ---- collection ledger (new invoice) --------------------------------
        if ($collect > 0) {
            $user = DB::table('users')->where('id', $userId)->first();

            DailyTransactionRecorder::insert([
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'RECEIVED',
                'received_amount' => $collect,
                'refund_amount' => 0,
                'invoice_reference' => $newNo,
                'patient_id' => $invoice->patient_id,
                'patient_name' => $invoice->patient_name,
                'operator_id' => $userId,
                'operator_name' => $user->name ?? null,
                'payment_mode' => $collectMode,
                'remarks' => 'Diagnostic Due Payment - Phase 1 - Partial cancellation of ' . $invoice->invoice_no,
                'status' => 'ACTIVE',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(CashInHandService::class)->apply($userId, 'RECEIVED', $collectMode, $collect);
        }

        // ---- refund ledger -------------------------------------------------
        if ($refund > 0) {
            $mode = $invoice->payment_mode ?: 'Cash';
            $user = DB::table('users')->where('id', $userId)->first();

            DailyTransactionRecorder::insert([
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'REFUND',
                'received_amount' => 0,
                'refund_amount' => $refund,
                'invoice_reference' => $invoice->invoice_no,
                'patient_id' => $invoice->patient_id,
                'patient_name' => $invoice->patient_name,
                'operator_id' => $userId,
                'operator_name' => $user->name ?? null,
                'payment_mode' => $mode,
                'remarks' => 'Partial cancellation (' . $summary . ') - Modified Invoice ' . $newNo,
                'status' => 'ACTIVE',
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(CashInHandService::class)->apply($userId, 'REFUND', $mode, $refund);
        }

        return [
            'new_invoice' => $newInvoice,
            'refund' => $refund,
            'due' => $newDue,
            'collected' => $collect,
            'new_total' => $newTotal,
            'cancelled_amount' => $cancelledAmount,
            'cancelled_summary' => $summary,
        ];
    }

    /** The line's own id plus any package component rows under it. */
    private function groupIds($rows, $row): array
    {
        $ids = [$row->id];

        if ($row->item_code_sub) {
            foreach ($rows as $r) {
                if ($r->package_parent_item_code_sub === $row->item_code_sub) {
                    $ids[] = $r->id;
                }
            }
        }

        return $ids;
    }

    private function reportExistsForLines(array $detailIds): bool
    {
        foreach (self::LINE_REPORT_TABLES as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'invoice_detail_id')
                && DB::table($table)->whereIn('invoice_detail_id', $detailIds)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** Same share-by-amount, nearest-10, last-absorbs-remainder rule as invoice creation. */
    private function allocatePaid(array $detailIds, float $total, float $paid): void
    {
        if ($paid <= 0 || $total <= 0 || !$detailIds) {
            return;
        }

        $details = DB::table('invoice_details')->whereIn('id', $detailIds)->orderBy('id')->get();
        $allocatedTotal = 0;
        $last = $details->count() - 1;

        foreach ($details as $i => $d) {
            if ($i === $last) {
                $alloc = round($paid - $allocatedTotal, 2);
            } else {
                $alloc = self::roundToTen(((float) $d->amount / $total) * $paid);
                $allocatedTotal += $alloc;
            }

            DB::table('invoice_details')->where('id', $d->id)
                ->update(['initial_paid_amount' => $alloc, 'initial_paid_date' => now()]);
        }
    }

    private function nextInvoiceNo(int $userId): string
    {
        $prefix = \App\Support\OfflineMode::prefix('LAB/')
            . now()->format('my') . '/' . str_pad($userId, 3, '0', STR_PAD_LEFT);

        $last = Invoice::where('invoice_no', 'like', $prefix . '%')->latest('id')->first();

        $serial = 1;
        if ($last) {
            $parts = explode('/', $last->invoice_no);
            $serial = intval(end($parts)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
