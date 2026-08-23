<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CashLedgerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Per-staff-user activity history: pick a user from a dropdown of every
 * staff account, see one row per day (invoices created, pending/due
 * amount, doctor payments settled, cash submitted) with a "Show Detail"
 * drill-down into that day's full ledger, plus a login/logout activity
 * table.
 *
 * The day-level figures are built entirely from CashLedgerService, the same
 * shared service that already powers the Cash Submission Report and the
 * Employee Performance Dashboard -- no separate calculation logic here, so
 * this dashboard can never quietly drift from those numbers.
 */
class UserHistoryController extends Controller
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

    /**
     * No search step -- the dropdown is simply every staff user, ordered by
     * name, with role and mobile shown alongside each option. mobile_no is
     * unique per user so it doubles as the disambiguator when two staff
     * members happen to share a name.
     */
    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'mobile_no']);

        return view('apps-user-history', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | DAILY SUMMARY -- ONE ROW PER DAY IN THE SELECTED RANGE
    |--------------------------------------------------------------------------
    */

    public function getDailySummary(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $userId = $request->user_id;

        $from = Carbon::parse($request->from_date)->startOfDay();
        $to = Carbon::parse($request->to_date)->startOfDay();

        // Bounded to 62 days -- one buildSummary() call per day is cheap
        // individually, but this is a live dashboard request, not a batch
        // job; an unbounded range (e.g. "All") would make it impractical.
        if ($from->diffInDays($to) > 62) {
            return response()->json([
                'status' => false,
                'message' => 'Please select a date range of 62 days or less.',
            ]);
        }

        $rows = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {

            $dateStr = $day->toDateString();

            $summary = $this->cashLedgerService->buildSummary($userId, $dateStr, $dateStr);

            $rows[] = array_merge(['date' => $dateStr, 'date_fmt' => $day->format('d-m-Y (D)')], $summary);
        }

        // Most recent day first, matching every other dashboard's ordering.
        $rows = array_reverse($rows);

        return response()->json([
            'status' => true,
            'data' => $rows,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW DETAIL -- ONE DAY'S FULL LEDGER (PATIENT + FINANCIAL DETAIL)
    |--------------------------------------------------------------------------
    */

    public function getDayDetail(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
        ]);

        $user = User::findOrFail($request->user_id);

        $result = $this->cashLedgerService->buildLedger($request->user_id, $request->date, $request->date);

        return response()->json([
            'status' => true,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
            'date' => $request->date,
            'ledger' => $result['ledger'],
            'doctor_payments' => $result['doctor_payments'],
            'group_breakdown' => $result['group_breakdown'],
            'summary' => $result['summary'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN / LOGOUT ACTIVITY
    |--------------------------------------------------------------------------
    */

    public function getLoginLogs(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $from = Carbon::parse($request->from_date)->startOfDay();
        $to = Carbon::parse($request->to_date)->endOfDay();

        $logs = DB::table('login_logs')
            ->where('user_id', $request->user_id)
            ->whereBetween('login_time', [$from, $to])
            ->orderByDesc('login_time')
            ->get();

        $logs->transform(function ($row) {

            $row->login_time_fmt = $row->login_time
                ? Carbon::parse($row->login_time)->format('d-m-Y h:i A')
                : null;

            $row->logout_time_fmt = $row->logout_time
                ? Carbon::parse($row->logout_time)->format('d-m-Y h:i A')
                : null;

            $row->still_active = empty($row->logout_time);

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $logs,
        ]);
    }
}
