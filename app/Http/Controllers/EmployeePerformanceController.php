<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CashLedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeePerformanceController extends Controller
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
        return view('apps-employee-performance');
    }

    /*
    |--------------------------------------------------------------------------
    | LIST -- ONE ROW PER EMPLOYEE FOR THE SELECTED DURATION
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        return response()->json([
            'status' => true,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'data' => $this->buildAllEmployeeRows($request->from_date, $request->to_date),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL -- ONE EMPLOYEE, FULL LEDGER + GROUP BREAKDOWN, FOR THE DURATION
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $user = User::findOrFail($request->user_id);

        $result = $this->cashLedgerService->buildLedger(
            $request->user_id,
            $request->from_date,
            $request->to_date
        );

        return response()->json([
            'status' => true,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'ledger' => $result['ledger'],
            'doctor_payments' => $result['doctor_payments'],
            'group_breakdown' => $result['group_breakdown'],
            'summary' => $result['summary'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT -- ALL EMPLOYEES SUMMARY (PDF)
    |--------------------------------------------------------------------------
    */

    public function printAll(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $rows = $this->buildAllEmployeeRows($request->from_date, $request->to_date);

        $totals = [
            'invoice_count' => array_sum(array_column($rows, 'invoice_count')),
            'cash_collected' => round(array_sum(array_column($rows, 'cash_collected')), 2),
            'non_cash_collected' => round(array_sum(array_column($rows, 'non_cash_collected')), 2),
            'cash_refunded' => round(array_sum(array_column($rows, 'cash_refunded')), 2),
            'cash_paid_to_doctors' => round(array_sum(array_column($rows, 'cash_paid_to_doctors')), 2),
            'net_cash_to_deposit' => round(array_sum(array_column($rows, 'net_cash_to_deposit')), 2),
        ];

        $fromDate = Carbon::parse($request->from_date);
        $toDate = Carbon::parse($request->to_date);

        $pdf = Pdf::loadView('apps-employee-performance-all-pdf', [
            'rows' => $rows,
            'totals' => $totals,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);

        $fileName = 'Employee-Performance-' .
            $fromDate->format('d-m-Y') . '-to-' . $toDate->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT -- SINGLE EMPLOYEE DETAIL (PDF)
    |--------------------------------------------------------------------------
    */

    public function printDetail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $user = User::findOrFail($request->user_id);

        $result = $this->cashLedgerService->buildLedger(
            $request->user_id,
            $request->from_date,
            $request->to_date
        );

        $fromDate = Carbon::parse($request->from_date);
        $toDate = Carbon::parse($request->to_date);

        $pdf = Pdf::loadView('apps-employee-performance-detail-pdf', [
            'user' => $user,
            'ledger' => $result['ledger'],
            'doctorPayments' => $result['doctor_payments'],
            'groupBreakdown' => $result['group_breakdown'],
            'summary' => $result['summary'],
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);

        $fileName = 'Employee-Performance-' .
            str_replace(' ', '-', $user->name) .
            '-' . $fromDate->format('d-m-Y') . '-to-' . $toDate->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildAllEmployeeRows(string $fromDate, string $toDate): array
    {
        $users = User::whereIn('role', self::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return $users->map(function ($user) use ($fromDate, $toDate) {

            $summary = $this->cashLedgerService->buildSummary($user->id, $fromDate, $toDate);

            return array_merge([
                'user_id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ], $summary);
        })->values()->all();
    }
}
