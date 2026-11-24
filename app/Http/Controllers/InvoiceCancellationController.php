<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceCancellationPermission;
use App\Services\AuditService;
use App\Services\CashInHandService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class InvoiceCancellationController extends Controller
{
    private const MODULE_CODE = 'INVOICE_CANCELLATION';

    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Visit',
        'DIAGNOSTIC' => 'Diagnostic',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-invoice-cancellation');
    }

    public function partialIndex()
    {
        return view('apps-invoice-partial-cancellation');
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH INVOICE BY NUMBER
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {
        $request->validate([
            'invoice_no' => 'required|string'
        ]);

        $invoice = Invoice::where('invoice_no', trim($request->invoice_no))->first();

        if (!$invoice) {

            return response()->json([
                'status' => false,
                'message' => 'No invoice found with this invoice number.'
            ]);
        }

        $details = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->orderBy('id')
            ->get();

        $isToday = $invoice->invoice_date
            ? \Carbon\Carbon::parse($invoice->invoice_date)->isToday()
            : false;

        // received_by is only set by some invoice types; diagnostic invoices
        // record who took the money on the RECEIVED ledger row / created_by.
        $receivedByName = DB::table('users')
            ->where('id', $invoice->received_by ?: $invoice->created_by)
            ->value('name');

        if (!$invoice->received_by) {
            $receivedByName = DB::table('daily_transactions')
                ->where('invoice_reference', $invoice->invoice_no)
                ->where('transaction_type', 'RECEIVED')
                ->orderBy('id')
                ->value('operator_name') ?: $receivedByName;
        }

        $cancelledByName = $invoice->cancelled_by
            ? DB::table('users')->where('id', $invoice->cancelled_by)->value('name')
            : null;

        $approvedByName = $invoice->cancellation_approved_by
            ? DB::table('users')->where('id', $invoice->cancellation_approved_by)->value('name')
            : null;

        $permission = $isToday
            ? null
            : InvoiceCancellationPermission::with(['requestedByUser', 'grantedByUser'])
                ->where('invoice_id', $invoice->id)
                ->first();

        $isGrantedToMe = $permission
            && $permission->status === InvoiceCancellationPermission::STATUS_GRANTED
            && (int) $permission->requested_by === (int) Auth::id();

        $canCancel = $invoice->cancelled !== 'Y' && ($isToday || $isGrantedToMe);

        return response()->json([
            'status' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_type' => $invoice->invoice_type,
                'invoice_type_label' => self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type,
                'invoice_date' => $invoice->invoice_date,
                'invoice_date_fmt' => $invoice->invoice_date
                    ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : null,
                'patient_name' => $invoice->patient_name,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'total_amount' => $invoice->total_amount,
                'discount' => $invoice->discount,
                'paid_amount' => $invoice->paid_amount,
                'due_amount' => $invoice->due_amount,
                'payment_mode' => $invoice->payment_mode,
                'received_by_name' => $receivedByName,
                'remarks' => $invoice->remarks,
                'status' => $invoice->status,
                'cancelled' => $invoice->cancelled,
                'cancelled_by_name' => $cancelledByName,
                'cancelled_at' => $invoice->cancelled_at,
                'cancellation_approved_by_name' => $approvedByName,
                'cancellation_remarks' => $invoice->cancellation_remarks,
                'is_today' => $isToday,
                'already_cancelled' => $invoice->cancelled === 'Y',
                'can_cancel' => $canCancel,
                'needs_request' => !$isToday && $invoice->cancelled !== 'Y' && !$permission,
            ],
            'permission' => $permission ? [
                'status' => $permission->status,
                'requested_by_name' => optional($permission->requestedByUser)->name,
                'requested_by_me' => (int) $permission->requested_by === (int) Auth::id(),
                'reason' => $permission->reason,
                'requested_at' => $permission->created_at,
                'granted_by_name' => optional($permission->grantedByUser)->name,
                'remarks' => $permission->remarks,
                'granted_at' => $permission->granted_at,
                'is_granted_to_me' => $isGrantedToMe,
            ] : null,
            'details' => $details,
            'partial' => app(\App\Services\PartialInvoiceCancellationService::class)->describe($invoice),
            'superseded_by' => $invoice->superseded_by_invoice_no,
            'parent_invoice_no' => $invoice->parent_invoice_no,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REQUEST CANCELLATION PERMISSION
    |--------------------------------------------------------------------------
    | Any user who wants to cancel a not-today invoice submits a reason here
    | instead of a Supervisor/Admin proactively granting one blind. Appears
    | on the Cancellation Permission dashboard for them to act on.
    */

    public function requestPermission(Request $request, AuditService $auditService)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'reason' => 'required|string|max:500',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->cancelled === 'Y') {

            return response()->json([
                'status' => false,
                'message' => 'This invoice has already been cancelled.'
            ], 422);
        }

        $isToday = $invoice->invoice_date
            ? \Carbon\Carbon::parse($invoice->invoice_date)->isToday()
            : false;

        if ($isToday) {

            return response()->json([
                'status' => false,
                'message' => 'This invoice was created today. You can cancel it directly, no permission needed.'
            ], 422);
        }

        if (InvoiceCancellationPermission::where('invoice_id', $invoice->id)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'A cancellation request already exists for this invoice.'
            ], 422);
        }

        $permission = InvoiceCancellationPermission::create([
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'requested_by' => Auth::id(),
            'reason' => $request->reason,
            'status' => InvoiceCancellationPermission::STATUS_PENDING,
            'created_by' => Auth::id(),
        ]);

        $auditService->logCreate(
            self::MODULE_CODE,
            $permission,
            $permission->only($permission->getFillable()),
            'Cancellation permission requested for invoice ' . $invoice->invoice_no
        );

        return response()->json([
            'status' => true,
            'message' => 'Cancellation permission requested. A Supervisor or Admin will need to approve it before you can cancel this invoice.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    public function cancel(Request $request, AuditService $auditService)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'cancellation_remarks' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {

            $invoice = Invoice::findOrFail($request->invoice_id);

            if ($invoice->cancelled === 'Y') {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'This invoice has already been cancelled.'
                ], 422);
            }

            $check = \App\Support\InvoiceCancellationApproval::check($invoice, Auth::id());

            if (!$check['allowed']) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => $check['message']
                ], 422);
            }

            $approverId = $check['approver_id'];

            $oldData = $invoice->only($invoice->getFillable());

            $invoice->update([
                'status' => 'Cancelled',
                'cancelled' => 'Y',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_approved_by' => $approverId,
                'cancellation_remarks' => $request->cancellation_remarks,
            ]);

            // its doctor's share will never be paid -- take the unpaid payables out of every report
            \App\Support\DoctorPayableCancellation::cancelUnpaidForInvoice($invoice->id);

            if ($invoice->paid_amount > 0) {

                $this->recordLedgerEntry(
                    $invoice->paid_amount,
                    $invoice->invoice_no,
                    $invoice->patient_id,
                    $invoice->patient_name,
                    $invoice->payment_mode,
                    'Invoice cancelled via Invoice Cancellation module' .
                        ($approverId ? ' (pre-approved by Supervisor/Admin)' : '')
                );
            }

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldData,
                $invoice->only($invoice->getFillable()),
                'Invoice cancelled' . ($approverId ? ' using pre-granted supervisor/admin permission' : ' (same-day, no permission required)')
            );

            app(\App\Services\InvoiceCancellationNotifier::class)->notify(
                $invoice,
                (float) $invoice->paid_amount,
                $request->cancellation_remarks,
                Auth::id(),
                $approverId
            );

            return response()->json([
                'status' => true,
                'message' => 'Invoice cancelled and amount refunded in the ledger successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to cancel invoice.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PARTIAL CANCEL (cancel individual items, issue a Modified Invoice)
    |--------------------------------------------------------------------------
    */

    public function partialCancel(Request $request, AuditService $auditService)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'detail_ids' => 'required|array|min:1',
            'detail_ids.*' => 'integer',
            'cancellation_remarks' => 'required|string|max:500',
            'collect_amount' => 'nullable|numeric|min:0',
            'collect_mode' => 'nullable|required_with:collect_amount|string|in:Cash,Card,UPI',
        ]);

        $service = app(\App\Services\PartialInvoiceCancellationService::class);

        DB::beginTransaction();

        try {

            $invoice = Invoice::findOrFail($request->invoice_id);

            if ($invoice->cancelled === 'Y') {
                DB::rollBack();

                return response()->json(['status' => false, 'message' => 'This invoice has already been cancelled.'], 422);
            }

            // Reports are checked per item by the service, not per invoice.
            $check = \App\Support\InvoiceCancellationApproval::check($invoice, Auth::id(), true);

            if (!$check['allowed']) {
                DB::rollBack();

                return response()->json(['status' => false, 'message' => $check['message']], 422);
            }

            $oldData = $invoice->only($invoice->getFillable());

            $result = $service->cancel(
                $invoice,
                $request->detail_ids,
                $request->cancellation_remarks,
                Auth::id(),
                $check['approver_id'],
                (float) $request->input('collect_amount', 0),
                $request->input('collect_mode')
            );

            DB::commit();

        } catch (\RuntimeException $e) {

            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json(['status' => false, 'message' => 'Unable to partially cancel invoice.'], 500);
        }

        $newInvoice = $result['new_invoice'];
        $invoice->refresh();

        $auditService->logUpdate(
            self::MODULE_CODE,
            $invoice,
            $oldData,
            $invoice->only($invoice->getFillable()),
            'Invoice partially cancelled; superseded by Modified Invoice ' . $newInvoice->invoice_no
        );

        app(\App\Services\InvoiceCancellationNotifier::class)->notify(
            $invoice,
            $result['refund'],
            'Partial cancellation of: ' . $result['cancelled_summary'] . ' (Modified Invoice ' . $newInvoice->invoice_no . '). ' . $request->cancellation_remarks,
            Auth::id(),
            $check['approver_id']
        );

        // Fresh invoice PDF to the patient again, same gate as any invoice send.
        $whatsapp = app(\App\Http\Controllers\DiagnosticInvoiceController::class)
            ->autoSendInvoiceWhatsapp($newInvoice->id);

        $msg = 'Item(s) cancelled. Modified Invoice ' . $newInvoice->invoice_no . ' generated. New total Rs. '
            . number_format($result['new_total'], 2);

        if ($result['refund'] > 0) {
            $msg .= '; REFUND Rs. ' . number_format($result['refund'], 2) . ' to patient.';
        } elseif ($result['due'] > 0) {
            $msg .= ($result['collected'] > 0 ? '; COLLECTED Rs. ' . number_format($result['collected'], 2) : '')
                . '; DUE Rs. ' . number_format($result['due'], 2) . ' to be collected.';
        } elseif ($result['collected'] > 0) {
            $msg .= '; COLLECTED Rs. ' . number_format($result['collected'], 2) . ' - fully paid.';
        } else {
            $msg .= '; nothing to refund or collect.';
        }

        $msg .= !empty($whatsapp['success']) ? ' WhatsApp sent.' : ' WhatsApp not sent.';

        return response()->json([
            'status' => true,
            'message' => $msg,
            'new_invoice_no' => $newInvoice->invoice_no,
            'new_invoice_id' => $newInvoice->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function recordLedgerEntry(
        float $amount,
        string $invoiceNo,
        ?string $patientId,
        ?string $patientName,
        ?string $paymentMode,
        string $remarks
    ): void {

        \App\Support\DailyTransactionRecorder::insert([

            'transaction_date' => now()->toDateString(),
            'transaction_type' => 'REFUND',

            'received_amount' => 0,
            'refund_amount' => $amount,

            'invoice_reference' => $invoiceNo,

            'patient_id' => $patientId,
            'patient_name' => $patientName,

            'operator_id' => Auth::id(),
            'operator_name' => Auth::user()->name,

            'payment_mode' => $paymentMode ?? 'Cash',

            'remarks' => $remarks,
            'status' => 'ACTIVE',

            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        app(CashInHandService::class)->apply(Auth::id(), 'REFUND', $paymentMode ?? 'Cash', $amount);
    }
}
