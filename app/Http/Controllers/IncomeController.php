<?php

namespace App\Http\Controllers;

use App\Models\IncomeCategory;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class IncomeController extends Controller
{
    private const MODULE_CODE = 'INCOME_OTHER_SOURCE';

    private const ITEM_CODE = 'DON001';

    private const PAYMENT_MODES = ['Cash', 'Card', 'UPI', 'Bank', 'Cheque'];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $categories = IncomeCategory::orderBy('description')->get(['id', 'description']);
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('apps-income', compact('categories', 'users'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = Invoice::where('invoices.invoice_type', 'OTHER_INCOME')
            ->leftJoin('invoice_details', 'invoice_details.invoice_no', '=', 'invoices.invoice_no')
            ->leftJoin('income_categories', 'income_categories.id', '=', 'invoice_details.income_category_id')
            ->leftJoin('users as income_receivers', 'income_receivers.id', '=', 'invoices.received_by')
            ->select(
                'invoices.*',
                'income_categories.description as category_name',
                'invoice_details.reference_number',
                'invoice_details.cheque_number',
                'invoice_details.bank_name',
                'invoice_details.cheque_date',
                'income_receivers.name as received_by_name'
            );

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('invoices.invoice_no', 'like', "%{$search}%")
                    ->orWhere('invoices.patient_name', 'like', "%{$search}%")
                    ->orWhere('invoice_details.reference_number', 'like', "%{$search}%")
                    ->orWhere('invoices.remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('invoice_details.income_category_id', $request->category_id);
        }

        $transactions = $query
            ->orderByDesc('invoices.id')
            ->paginate($perPage);

        $transactions->getCollection()->transform(function ($row) {

            $row->invoice_date_fmt = $row->invoice_date
                ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                : null;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'total' => $transactions->total()
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'income_category_id' => 'required|exists:income_categories,id',
            'received_from' => 'nullable|string|max:150',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'cheque_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:50',
            'cheque_date' => 'nullable|date',
            'received_by' => 'required|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            $category = IncomeCategory::findOrFail($request->income_category_id);

            $amount = round((float) $request->amount, 2);

            $invoiceNo = $this->generateInvoiceNo();

            $invoice = Invoice::create([

                'invoice_no' => $invoiceNo,
                'invoice_type' => 'OTHER_INCOME',
                'invoice_category' => 'NON_PATHOLOGY',

                'patient_name' => $request->received_from,

                'invoice_date' => $request->transaction_date,

                'total_amount' => $amount,
                'discount' => 0,
                'paid_amount' => $amount,
                'due_amount' => 0,

                'payment_mode' => $request->payment_mode,
                'received_by' => $request->received_by,
                'remarks' => $request->remarks,

                'status' => 'Active',

                'created_by' => Auth::id()
            ]);

            DB::table('invoice_details')->insert([

                'invoice_no' => $invoiceNo,
                'item_code' => self::ITEM_CODE,
                'item_description' => $category->description,
                'income_category_id' => $category->id,

                'cheque_number' => $request->cheque_number,
                'bank_name' => $request->bank_name,
                'cheque_date' => $request->cheque_date,
                'reference_number' => $request->reference_number,

                'quantity' => 1,
                'rate' => $amount,
                'amount' => $amount,

                'remarks' => $request->remarks,

                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $this->recordLedgerEntry(
                'RECEIVED',
                $amount,
                $invoiceNo,
                null,
                $request->received_from,
                $request->payment_mode,
                'Income from Other Source - ' . $category->description
            );

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $invoice,
                $invoice->only($invoice->getFillable()),
                'Income from other source entry created'
            );

            return response()->json([
                'status' => true,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoiceNo,
                'message' => 'Income from Other Source entry recorded successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to record income from other source entry.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    public function cancel($id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $invoice = Invoice::where('invoice_type', 'OTHER_INCOME')->findOrFail($id);

            if ($invoice->cancelled === 'Y') {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'This entry has already been cancelled.'
                ], 422);
            }

            $approverId = \App\Support\InvoiceCancellationApproval::resolveApprover($invoice);

            if ($approverId === false) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => \App\Support\InvoiceCancellationApproval::NOT_TODAY_MESSAGE
                ], 422);
            }

            $oldData = $invoice->only($invoice->getFillable());

            $invoice->update([
                'status' => 'Cancelled',
                'cancelled' => 'Y',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_approved_by' => $approverId
            ]);

            if ($invoice->paid_amount > 0) {

                $this->recordLedgerEntry(
                    'REFUND',
                    $invoice->paid_amount,
                    $invoice->invoice_no,
                    null,
                    $invoice->patient_name,
                    $invoice->payment_mode,
                    'Income from Other Source Entry Cancelled'
                );
            }

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldData,
                $invoice->only($invoice->getFillable()),
                'Income from other source entry cancelled'
            );

            return response()->json([
                'status' => true,
                'message' => 'Income from Other Source entry cancelled successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to cancel income from other source entry.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT RECEIPT
    |--------------------------------------------------------------------------
    */

    public function printReceipt($id)
    {
        $invoice = Invoice::where('invoice_type', 'OTHER_INCOME')->findOrFail($id);

        $detail = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->first();

        $category = $detail && $detail->income_category_id
            ? IncomeCategory::find($detail->income_category_id)
            : null;

        $receivedByName = $invoice->received_by
            ? DB::table('users')->where('id', $invoice->received_by)->value('name')
            : null;

        $pdf = Pdf::loadView(
            'apps-income-pdf',
            compact('invoice', 'detail', 'category', 'receivedByName')
        );

        $safeFileName = str_replace(['/', '\\'], '-', $invoice->invoice_no) . '.pdf';

        return $pdf->stream($safeFileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function generateInvoiceNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('INC/') .
            now()->format('my') .
            '/' .
            str_pad(Auth::id(), 3, '0', STR_PAD_LEFT);

        $lastInvoice = Invoice::where('invoice_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $serial = 1;

        if ($lastInvoice) {

            $explode = explode('/', $lastInvoice->invoice_no);

            $serial = intval(end($explode)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }

    private function recordLedgerEntry(
        string $type,
        float $amount,
        string $invoiceNo,
        ?string $patientId,
        ?string $patientName,
        string $paymentMode,
        string $remarks
    ): void {

        $transactionNo = \App\Support\OfflineMode::prefix('TRN/') .
            now()->format('ymd') .
            '/' .
            str_pad(DB::table('daily_transactions')->count() + 1, 6, '0', STR_PAD_LEFT);

        DB::table('daily_transactions')->insert([

            'transaction_date' => now()->toDateString(),
            'transaction_no' => $transactionNo,
            'transaction_type' => $type,

            'received_amount' => $type === 'RECEIVED' ? $amount : 0,
            'refund_amount' => $type === 'REFUND' ? $amount : 0,

            'invoice_reference' => $invoiceNo,

            'patient_id' => $patientId,
            'patient_name' => $patientName,

            'operator_id' => Auth::id(),
            'operator_name' => Auth::user()->name,

            'payment_mode' => $paymentMode,

            'remarks' => $remarks,
            'status' => 'ACTIVE',

            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
