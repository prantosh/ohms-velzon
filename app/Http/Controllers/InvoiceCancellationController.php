<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceCancellationPermission;
use App\Services\AuditService;
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

        $receivedByName = $invoice->received_by
            ? DB::table('users')->where('id', $invoice->received_by)->value('name')
            : null;

        $cancelledByName = $invoice->cancelled_by
            ? DB::table('users')->where('id', $invoice->cancelled_by)->value('name')
            : null;

        $approvedByName = $invoice->cancellation_approved_by
            ? DB::table('users')->where('id', $invoice->cancellation_approved_by)->value('name')
            : null;

        $permission = $isToday
            ? null
            : InvoiceCancellationPermission::where('invoice_id', $invoice->id)->first();

        $canCancel = $invoice->cancelled !== 'Y' && ($isToday || $permission !== null);

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
            ],
            'permission' => $permission ? [
                'granted_by_name' => optional($permission->grantedByUser)->name,
                'remarks' => $permission->remarks,
                'created_at' => $permission->created_at,
            ] : null,
            'details' => $details
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

            $isToday = $invoice->invoice_date
                ? \Carbon\Carbon::parse($invoice->invoice_date)->isToday()
                : false;

            $approverId = null;

            if (!$isToday) {

                $permission = InvoiceCancellationPermission::where('invoice_id', $invoice->id)->first();

                if (!$permission) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'This invoice was not created today. A Supervisor or Admin must first grant cancellation permission for it via the Cancellation Permission dashboard.'
                    ], 422);
                }

                $approverId = $permission->granted_by;
            }

            $oldData = $invoice->only($invoice->getFillable());

            $invoice->update([
                'status' => 'Cancelled',
                'cancelled' => 'Y',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_approved_by' => $approverId,
                'cancellation_remarks' => $request->cancellation_remarks,
            ]);

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

        $transactionNo = \App\Support\OfflineMode::prefix('TRN/') .
            now()->format('ymd') .
            '/' .
            str_pad(DB::table('daily_transactions')->count() + 1, 6, '0', STR_PAD_LEFT);

        DB::table('daily_transactions')->insert([

            'transaction_date' => now()->toDateString(),
            'transaction_no' => $transactionNo,
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
    }
}
