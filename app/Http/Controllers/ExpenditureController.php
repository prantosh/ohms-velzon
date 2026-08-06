<?php

namespace App\Http\Controllers;

use App\Models\ExpenditureAgency;
use App\Models\ExpenditureCategory;
use App\Models\ExpenditureTransaction;
use App\Models\User;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ExpenditureController extends Controller
{
    private const MODULE_CODE = 'EXPENDITURE';

    private const PAYMENT_MODES = ['Cash', 'Card', 'UPI', 'Bank', 'Cheque'];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $categories = ExpenditureCategory::orderBy('description')->get(['id', 'description']);
        $agencies = ExpenditureAgency::orderBy('description')->get(['id', 'description']);
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('apps-expenditure', compact('categories', 'agencies', 'users'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = ExpenditureTransaction::with('category', 'agency', 'expenditureByUser');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('voucher_number', 'like', "%{$search}%")
                    ->orWhere('bill_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('expenditure_category_id', $request->category_id);
        }

        if ($request->filled('agency_id')) {
            $query->where('expenditure_agency_id', $request->agency_id);
        }

        $transactions = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $transactions->getCollection()->transform(function ($row) {

            $row->transaction_date_fmt = $row->transaction_date
                ? \Carbon\Carbon::parse($row->transaction_date)->format('d-m-Y')
                : null;

            $row->category_name = optional($row->category)->description;
            $row->agency_name = optional($row->agency)->description;
            $row->expenditure_by_name = optional($row->expenditureByUser)->name;

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
            'expenditure_category_id' => 'required|exists:expenditure_categories,id',
            'expenditure_agency_id' => 'required|exists:expenditure_agencies,id',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'cheque_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:150',
            'bill_number' => 'nullable|string|max:50',
            'cheque_date' => 'nullable|date',
            'expenditure_by' => 'required|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {

            $transaction = ExpenditureTransaction::create([

                'voucher_number' => $this->generateVoucherNo(),
                'expenditure_category_id' => $request->expenditure_category_id,
                'expenditure_agency_id' => $request->expenditure_agency_id,
                'transaction_date' => $request->transaction_date,
                'amount' => $request->amount,
                'payment_mode' => $request->payment_mode,
                'cheque_number' => $request->cheque_number,
                'bank_name' => $request->bank_name,
                'bill_number' => $request->bill_number,
                'cheque_date' => $request->cheque_date,
                'expenditure_by' => $request->expenditure_by,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $transaction,
                $transaction->only($transaction->getFillable()),
                'Expenditure entry recorded'
            );

            return response()->json([
                'status' => true,
                'id' => $transaction->id,
                'voucher_number' => $transaction->voucher_number,
                'message' => 'Expenditure entry recorded successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to record expenditure entry.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT / UPDATE
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $transaction = ExpenditureTransaction::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $transaction
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'expenditure_category_id' => 'required|exists:expenditure_categories,id',
            'expenditure_agency_id' => 'required|exists:expenditure_agencies,id',
            'transaction_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_mode' => 'required|in:' . implode(',', self::PAYMENT_MODES),
            'cheque_number' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:150',
            'bill_number' => 'nullable|string|max:50',
            'cheque_date' => 'nullable|date',
            'expenditure_by' => 'required|exists:users,id',
            'remarks' => 'nullable|string',
        ]);

        try {

            $transaction = ExpenditureTransaction::findOrFail($id);

            $oldData = $transaction->only($transaction->getFillable());

            $transaction->update([

                'expenditure_category_id' => $request->expenditure_category_id,
                'expenditure_agency_id' => $request->expenditure_agency_id,
                'transaction_date' => $request->transaction_date,
                'amount' => $request->amount,
                'payment_mode' => $request->payment_mode,
                'cheque_number' => $request->cheque_number,
                'bank_name' => $request->bank_name,
                'bill_number' => $request->bill_number,
                'cheque_date' => $request->cheque_date,
                'expenditure_by' => $request->expenditure_by,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $transaction,
                $oldData,
                $transaction->only($transaction->getFillable()),
                'Expenditure entry updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure entry updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update expenditure entry.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        try {

            $transaction = ExpenditureTransaction::findOrFail($id);

            $oldData = $transaction->only($transaction->getFillable());

            $transaction->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $transaction,
                $oldData,
                'Expenditure entry deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Expenditure entry deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete expenditure entry.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT VOUCHER
    |--------------------------------------------------------------------------
    */

    public function printVoucher($id)
    {
        $transaction = ExpenditureTransaction::with('category', 'agency', 'expenditureByUser')->findOrFail($id);

        $preparedByName = $transaction->created_by
            ? DB::table('users')->where('id', $transaction->created_by)->value('name')
            : null;

        $pdf = Pdf::loadView(
            'apps-expenditure-pdf',
            compact('transaction', 'preparedByName')
        );

        $safeFileName = str_replace(['/', '\\'], '-', $transaction->voucher_number) . '.pdf';

        return $pdf->stream($safeFileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function generateVoucherNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('EXP/') .
            now()->format('my') .
            '/' .
            str_pad(Auth::id(), 3, '0', STR_PAD_LEFT);

        $lastTransaction = ExpenditureTransaction::where('voucher_number', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $serial = 1;

        if ($lastTransaction) {

            $explode = explode('/', $lastTransaction->voucher_number);

            $serial = intval(end($explode)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
