<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\AuditService;
use App\Services\CashInHandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin-only screen to correct the payment mode (Cash / Card / UPI) of one
 * collection that an employee recorded wrongly.
 *
 * One collection = one ACTIVE RECEIVED row in daily_transactions. Changing it
 * keeps every place that stores the mode in step, in one transaction:
 *
 *  - daily_transactions.payment_mode (+ payment_reference)
 *  - invoices: the matching per-payment column (payment_mode_initial /
 *    payment_mode_1 / payment_mode_2 for a Diagnostic invoice's initial /
 *    Phase 1 / Phase 2 payment) and invoices.payment_mode, which always
 *    holds the LATEST payment's mode.
 *  - users.cash_in_hand / cash_day_closings of whoever recorded the row, when
 *    the change crosses Cash <-> non-cash (CashInHandService::applyAdjustment,
 *    dated to the transaction's own day so a past day's closing is corrected,
 *    not today's balance).
 *  - audit_logs.
 *
 * The amount, date, operator and invoice totals never change. Refunds and
 * doctor payments are not corrected here, and a cancelled invoice is refused.
 *
 * Deliberately NOT in config/access_pages, so it cannot be granted to other
 * roles.
 */
class PaymentModeCorrectionController extends Controller
{
    private const MODULE_CODE = 'PAYMENT_MODE_CORRECTION';

    private const MODES = ['Cash', 'Card', 'UPI'];

    private function ensureAdmin(): void
    {
        abort_unless(
            optional(Auth::user())->role === 'Admin',
            403,
            'Only an Admin can access Payment Mode Correction.'
        );
    }

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-payment-mode-correction', ['modes' => self::MODES]);
    }

    /*
    |--------------------------------------------------------------------------
    | Search -- invoice no, transaction no, or patient mobile no
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['q' => 'required|string|max:50']);

        $q = trim($request->q);

        $invoice = Invoice::where('invoice_no', $q)->first();

        if (!$invoice) {
            $invoiceNo = DB::table('daily_transactions')
                ->where('transaction_no', $q)
                ->value('invoice_reference');

            $invoice = $invoiceNo ? Invoice::where('invoice_no', $invoiceNo)->first() : null;
        }

        if ($invoice) {
            return response()->json(['status' => true, 'mode' => 'invoice', 'invoice' => $this->invoicePayload($invoice)]);
        }

        $digits = preg_replace('/\D/', '', $q);

        if (strlen($digits) >= 10) {

            $invoices = Invoice::where('patient_mobile_no', 'like', '%' . substr($digits, -10))
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'invoice_no', 'invoice_type', 'invoice_date', 'patient_name', 'paid_amount', 'payment_mode', 'cancelled']);

            if ($invoices->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'mode' => 'list',
                    'invoices' => $invoices->map(fn ($i) => [
                        'invoice_no' => $i->invoice_no,
                        'invoice_date' => $i->invoice_date ? \Carbon\Carbon::parse($i->invoice_date)->format('d-m-Y') : '',
                        'patient_name' => $i->patient_name,
                        'paid_amount' => (float) $i->paid_amount,
                        'payment_mode' => $i->payment_mode,
                        'cancelled' => $i->cancelled === 'Y',
                    ]),
                ]);
            }
        }

        return response()->json(['status' => false, 'message' => 'No invoice found for this Invoice No / Transaction No / Mobile No.'], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Change the mode of one collection
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, AuditService $auditService, CashInHandService $cashInHand)
    {
        $this->ensureAdmin();

        $request->validate([
            'transaction_id' => 'required|integer',
            'payment_mode' => 'required|in:' . implode(',', self::MODES),
            'payment_reference' => 'nullable|required_if:payment_mode,Card,UPI|digits:4',
            'reason' => 'required|string|min:5|max:500',
        ]);

        $newMode = $request->payment_mode;
        $newReference = $newMode === 'Cash' ? null : $request->payment_reference;
        $reason = trim($request->reason);

        DB::beginTransaction();

        try {

            $txn = DB::table('daily_transactions')
                ->where('id', $request->transaction_id)
                ->lockForUpdate()
                ->first();

            if (!$txn || $txn->transaction_type !== 'RECEIVED' || $txn->status !== 'ACTIVE') {
                DB::rollBack();
                return $this->fail('Only an active collection can be corrected.', 404);
            }

            $invoice = Invoice::where('invoice_no', $txn->invoice_reference)->lockForUpdate()->first();

            if (!$invoice) {
                DB::rollBack();
                return $this->fail('This collection is not linked to an invoice, so it cannot be corrected here.');
            }

            if ($invoice->cancelled === 'Y') {
                DB::rollBack();
                return $this->fail('Invoice is cancelled.');
            }

            $oldMode = $txn->payment_mode;
            $oldReference = $txn->payment_reference;

            if ($oldMode === $newMode && (string) $oldReference === (string) $newReference) {
                DB::rollBack();
                return $this->fail('The payment mode is already ' . $newMode . ' -- nothing to change.');
            }

            $amount = (float) $txn->received_amount;
            $note = 'Payment mode changed by Admin ' . ($oldMode ?: '-') . ' -> ' . $newMode . ': ' . $reason;

            // ---- cash in hand (only when Cash <-> non-cash) ----------------
            // First on purpose: invoices / daily_transactions / users can be
            // MyISAM (no rollback), so the step most likely to throw runs
            // before any of those rows are written.
            $delta = ($newMode === 'Cash' ? $amount : 0) - ($oldMode === 'Cash' ? $amount : 0);

            if ($delta != 0.0 && $txn->created_by) {
                $cashInHand->applyAdjustment(
                    (int) $txn->created_by,
                    (string) $txn->transaction_date,
                    $delta
                );
            }

            // ---- daily transaction ---------------------------------------
            DB::table('daily_transactions')
                ->where('id', $txn->id)
                ->update([
                    'payment_mode' => $newMode,
                    'payment_reference' => $newReference,
                    'remarks' => trim(($txn->remarks ? $txn->remarks . ' | ' : '') . $note),
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            // ---- invoice -------------------------------------------------
            $oldInvoiceData = $invoice->only(['payment_mode', 'payment_mode_initial', 'payment_mode_1', 'payment_mode_2']);

            $this->applyToInvoice($invoice, $txn, $newMode);

            $invoice->updated_by = Auth::id();
            $invoice->save();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData + [
                    'transaction_no' => $txn->transaction_no,
                    'txn_payment_mode' => $oldMode,
                    'txn_payment_reference' => $oldReference,
                    'amount' => $amount,
                ],
                $invoice->only(['payment_mode', 'payment_mode_initial', 'payment_mode_1', 'payment_mode_2']) + [
                    'transaction_no' => $txn->transaction_no,
                    'txn_payment_mode' => $newMode,
                    'txn_payment_reference' => $newReference,
                    'amount' => $amount,
                ],
                $note
            );

            DB::commit();

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Payment Mode Correction failed', [
                'transaction_id' => $request->transaction_id,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('Could not save: ' . $e->getMessage(), 500);
        }

        $invoice->refresh();

        return response()->json([
            'status' => true,
            'message' => $txn->transaction_no . ' changed from ' . ($oldMode ?: '-') . ' to ' . $newMode . '.',
            'invoice' => $this->invoicePayload($invoice),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Writes the new mode to the invoice column that mirrors this collection,
     * then re-derives invoices.payment_mode (the latest payment's mode).
     */
    private function applyToInvoice(Invoice $invoice, object $txn, string $newMode): void
    {
        $column = $this->invoiceColumnFor($invoice, $txn);

        if ($column) {
            $invoice->{$column} = $newMode;
        }

        if ($invoice->invoice_type === 'DIAGNOSTIC') {
            $invoice->payment_mode = $invoice->payment_mode_2
                ?: ($invoice->payment_mode_1 ?: ($invoice->payment_mode_initial ?: $newMode));
        } else {
            $invoice->payment_mode = $newMode;
        }
    }

    /** payment_mode_initial / _1 / _2 for a Diagnostic collection, null for any other type. */
    private function invoiceColumnFor(Invoice $invoice, object $txn): ?string
    {
        if ($invoice->invoice_type !== 'DIAGNOSTIC') {
            return null;
        }

        $remarks = (string) $txn->remarks;

        if (preg_match('/Diagnostic Due Payment - Phase ([12])/', $remarks, $m)) {
            return 'payment_mode_' . $m[1];
        }

        return 'payment_mode_initial';
    }

    private function invoicePayload(Invoice $invoice): array
    {
        $operators = DB::table('users')->pluck('name', 'id');

        $payments = DB::table('daily_transactions')
            ->where('invoice_reference', $invoice->invoice_no)
            ->where('transaction_type', 'RECEIVED')
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get(['id', 'transaction_no', 'transaction_date', 'received_amount', 'payment_mode', 'payment_reference', 'created_by', 'operator_name', 'remarks', 'created_at'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'transaction_no' => $t->transaction_no,
                'date' => $t->transaction_date ? \Carbon\Carbon::parse($t->transaction_date)->format('d-m-Y') : '',
                'time' => $t->created_at ? \Carbon\Carbon::parse($t->created_at)->format('h:i A') : '',
                'amount' => (float) $t->received_amount,
                'payment_mode' => $t->payment_mode,
                'payment_reference' => $t->payment_reference,
                'operator' => $t->operator_name ?: ($operators[$t->created_by] ?? '-'),
                'remarks' => $t->remarks,
            ])
            ->values();

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_type' => $invoice->invoice_type,
            'invoice_date' => $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') : '',
            'patient_name' => $invoice->patient_name,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'due_amount' => (float) $invoice->due_amount,
            'cancelled' => $invoice->cancelled === 'Y',
            'payments' => $payments,
        ];
    }

    private function fail(string $message, int $code = 422)
    {
        return response()->json(['status' => false, 'message' => $message], $code);
    }
}
