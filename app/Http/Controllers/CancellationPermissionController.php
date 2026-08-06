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
    | SEARCH INVOICE BY NUMBER
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

        $permission = InvoiceCancellationPermission::where('invoice_id', $invoice->id)->first();

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
            'permission' => $permission ? [
                'granted_by_name' => optional($permission->grantedByUser)->name,
                'remarks' => $permission->remarks,
                'created_at' => $permission->created_at,
            ] : null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | GRANT PERMISSION
    |--------------------------------------------------------------------------
    */

    public function grant(Request $request, AuditService $auditService)
    {
        $this->ensureApprover();

        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'remarks' => 'required|string|max:500',
        ]);

        try {

            $invoice = Invoice::findOrFail($request->invoice_id);

            if ($invoice->cancelled === 'Y') {

                return response()->json([
                    'status' => false,
                    'message' => 'This invoice has already been cancelled. Permission is not needed.'
                ], 422);
            }

            $isToday = $invoice->invoice_date
                ? \Carbon\Carbon::parse($invoice->invoice_date)->isToday()
                : false;

            if ($isToday) {

                return response()->json([
                    'status' => false,
                    'message' => 'This invoice was created today. Any user can cancel it directly without permission.'
                ], 422);
            }

            if (InvoiceCancellationPermission::where('invoice_id', $invoice->id)->exists()) {

                return response()->json([
                    'status' => false,
                    'message' => 'Cancellation permission has already been granted for this invoice.'
                ], 422);
            }

            $permission = InvoiceCancellationPermission::create([
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'granted_by' => Auth::id(),
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $permission,
                $permission->only($permission->getFillable()),
                'Cancellation permission granted for invoice ' . $invoice->invoice_no
            );

            return response()->json([
                'status' => true,
                'message' => 'Cancellation permission granted. Any user can now cancel this invoice from the Invoice Cancellation page.'
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
