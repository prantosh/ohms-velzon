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

class CancellationPermissionController extends Controller
{
    private const MODULE_CODE = 'CANCELLATION_PERMISSION';

    private const APPROVER_ROLES = ['Admin', 'Supervisor'];

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
    | ACCESS GUARD (Admin / Supervisor only, enforced regardless of page-access config)
    |--------------------------------------------------------------------------
    */

    private function ensureApprover()
    {
        if (!in_array(optional(Auth::user())->role, self::APPROVER_ROLES)) {
            abort(403, 'Only a Supervisor or Admin can access the Cancellation Permission dashboard.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->ensureApprover();

        return view('apps-cancellation-permission');
    }

    /*
    |--------------------------------------------------------------------------
    | PENDING REQUESTS -- the dashboard's main list, no invoice number needed
    |--------------------------------------------------------------------------
    | A user requests permission from the Invoice Cancellation page (see
    | InvoiceCancellationController::requestPermission()); this is where a
    | Supervisor/Admin discovers and acts on those requests without having
    | to already know which invoice numbers to look up.
    */

    public function pendingRequests()
    {
        $this->ensureApprover();

        $rows = InvoiceCancellationPermission::with('requestedByUser')
            ->where('status', InvoiceCancellationPermission::STATUS_PENDING)
            ->orderBy('created_at')
            ->get();

        $invoicesByNo = Invoice::whereIn('invoice_no', $rows->pluck('invoice_no'))
            ->get()
            ->keyBy('invoice_no');

        $data = $rows->map(function ($permission) use ($invoicesByNo) {

            $invoice = $invoicesByNo->get($permission->invoice_no);

            return [
                'permission_id' => $permission->id,
                'invoice_no' => $permission->invoice_no,
                'invoice_type_label' => $invoice
                    ? (self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type)
                    : null,
                'invoice_date_fmt' => $invoice && $invoice->invoice_date
                    ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : null,
                'patient_name' => $invoice->patient_name ?? null,
                'total_amount' => $invoice->total_amount ?? null,
                'paid_amount' => $invoice->paid_amount ?? null,
                'already_cancelled' => $invoice ? $invoice->cancelled === 'Y' : false,
                'requested_by_name' => optional($permission->requestedByUser)->name,
                'reason' => $permission->reason,
                'requested_at' => $permission->created_at,
            ];
        })
        // An invoice could theoretically get cancelled through some other
        // path while its request is still sitting PENDING -- don't offer to
        // grant permission for something that no longer needs it.
        ->reject(fn ($row) => $row['already_cancelled'])
        ->values();

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH INVOICE BY NUMBER -- ad-hoc lookup of one invoice's request/grant
    | history, separate from the pending-requests list above.
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {
        $this->ensureApprover();

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

        $isToday = $invoice->invoice_date
            ? \Carbon\Carbon::parse($invoice->invoice_date)->isToday()
            : false;

        $permission = InvoiceCancellationPermission::with(['requestedByUser', 'grantedByUser'])
            ->where('invoice_id', $invoice->id)
            ->first();

        return response()->json([
            'status' => true,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_type_label' => self::INVOICE_TYPE_LABELS[$invoice->invoice_type] ?? $invoice->invoice_type,
                'invoice_date_fmt' => $invoice->invoice_date
                    ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
                    : null,
                'patient_name' => $invoice->patient_name,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
                'is_today' => $isToday,
                'already_cancelled' => $invoice->cancelled === 'Y',
                'needs_permission' => !$isToday && $invoice->cancelled !== 'Y',
            ],
            'permission' => $permission ? $this->permissionPayload($permission) : null,
        ]);
    }

    private function permissionPayload(InvoiceCancellationPermission $permission): array
    {
        return [
            'id' => $permission->id,
            'status' => $permission->status,
            'requested_by_name' => optional($permission->requestedByUser)->name,
            'reason' => $permission->reason,
            'requested_at' => $permission->created_at,
            'granted_by_name' => optional($permission->grantedByUser)->name,
            'remarks' => $permission->remarks,
            'granted_at' => $permission->granted_at,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | GRANT PERMISSION -- acts on an existing PENDING request
    |--------------------------------------------------------------------------
    */

    public function grant(Request $request, AuditService $auditService)
    {
        $this->ensureApprover();

        $request->validate([
            'permission_id' => 'required|exists:invoice_cancellation_permissions,id',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {

            $permission = InvoiceCancellationPermission::findOrFail($request->permission_id);

            if ($permission->status === InvoiceCancellationPermission::STATUS_GRANTED) {

                return response()->json([
                    'status' => false,
                    'message' => 'This request has already been granted.'
                ], 422);
            }

            $invoice = Invoice::find($permission->invoice_id);

            if ($invoice && $invoice->cancelled === 'Y') {

                return response()->json([
                    'status' => false,
                    'message' => 'This invoice has already been cancelled. Permission is no longer needed.'
                ], 422);
            }

            $oldData = $permission->only($permission->getFillable());

            $permission->update([
                'status' => InvoiceCancellationPermission::STATUS_GRANTED,
                'granted_by' => Auth::id(),
                'granted_at' => now(),
                'remarks' => $request->remarks,
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $permission,
                $oldData,
                $permission->only($permission->getFillable()),
                'Cancellation permission granted for invoice ' . $permission->invoice_no
                    . ' (requested by ' . optional($permission->requestedByUser)->name . ')'
            );

            return response()->json([
                'status' => true,
                'message' => 'Permission granted. Only ' . optional($permission->requestedByUser)->name
                    . ' can now cancel this invoice from the Invoice Cancellation page.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to grant cancellation permission.'
            ], 500);
        }
    }
}
