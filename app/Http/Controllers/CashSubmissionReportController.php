<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CashLedgerService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CashSubmissionReportController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    public function __construct(private CashLedgerService $cashLedgerService)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return view('apps-cash-submission-report', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $result = $this->cashLedgerService->buildLedger($request->user_id, $request->date, $request->date);

        return response()->json([
            'status' => true,
            'ledger' => $result['ledger'],
            'doctor_payments' => $result['doctor_payments'],
            'group_breakdown' => $result['group_breakdown'],
            'summary' => $result['summary'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORY WISE SUMMARY -- broken down by invoice_item_masters.item_name
    | (Diagnostic Test Category Master: Pathology, USG, Doctor Visit, etc.),
    | not a flat single-row summary.
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $result = $this->cashLedgerService->buildCategoryWiseSummary($request->user_id, $request->date, $request->date);

        return response()->json([
            'status' => true,
            'rows' => $result['rows'],
            'grand_total' => $result['grand_total'],
        ]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $user = User::findOrFail($request->user_id);

        $result = $this->cashLedgerService->buildCategoryWiseSummary($request->user_id, $request->date, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-cash-submission-summary-pdf',
            [
                'user' => $user,
                'rows' => $result['rows'],
                'grandTotal' => $result['grand_total'],
                'date' => $date,
            ]
        );

        $pdf->setPaper('A4', 'landscape');

        $fileName = 'Cash-Submission-Category-Summary-' .
            str_replace(' ', '-', $user->name) .
            '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT (PDF cash submission slip)
    |--------------------------------------------------------------------------
    */

    public function print(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $user = User::findOrFail($request->user_id);

        $result = $this->cashLedgerService->buildLedger($request->user_id, $request->date, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-cash-submission-report-pdf',
            [
                'user' => $user,
                'ledger' => $result['ledger'],
                'doctorPayments' => $result['doctor_payments'],
                'groupBreakdown' => $result['group_breakdown'],
                'summary' => $result['summary'],
                'date' => $date,
            ]
        );

        $fileName = 'Cash-Submission-Slip-' .
            str_replace(' ', '-', $user->name) .
            '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }
}
