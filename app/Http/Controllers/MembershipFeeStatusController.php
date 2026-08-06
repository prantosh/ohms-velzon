<?php

namespace App\Http\Controllers;

use App\Models\MembershipFeePayment;
use App\Models\MembershipFeeRate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class MembershipFeeStatusController extends Controller
{
    private const TRACKED_ROLES = ['Member', 'Admin', 'Supervisor'];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-membership-fee-status');
    }

    /*
    |--------------------------------------------------------------------------
    | LIST (computed present payment status)
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 10);

        $page = (int) $request->get('page', 1);

        $users = User::whereIn('role', self::TRACKED_ROLES)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name', 'mobile_no', 'role', 'date_of_joining']);

        $lastPaidMap = MembershipFeePayment::select('user_id', DB::raw('MAX(payment_month) as last_paid'))
            ->groupBy('user_id')
            ->pluck('last_paid', 'user_id');

        $rates = MembershipFeeRate::orderBy('effective_from')->get(['effective_from', 'monthly_rate']);

        $today = Carbon::now()->startOfMonth();

        $rows = $users->map(function ($user) use ($lastPaidMap, $rates, $today) {

            $lastPaid = $lastPaidMap[$user->id] ?? null;

            $nextDue = $lastPaid
                ? Carbon::parse($lastPaid)->addMonthNoOverflow()->startOfMonth()
                : $this->safeJoiningMonth($user);

            $overdueMonths = $this->overdueMonths($nextDue, $today);

            $totalDue = 0;

            foreach ($overdueMonths as $month) {
                $totalDue += $this->rateForMonth($month, $rates);
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'mobile_no' => $user->mobile_no,
                'role' => $user->role,
                'last_paid_month' => $lastPaid ? Carbon::parse($lastPaid)->format('F Y') : null,
                'next_due_month' => $nextDue->format('F Y'),
                'months_overdue' => count($overdueMonths),
                'total_due_amount' => round($totalDue, 2),
                'status' => count($overdueMonths) > 0 ? 'overdue' : 'up_to_date',
            ];
        });

        if ($request->filled('role')) {
            $rows = $rows->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $rows = $rows->where('status', $request->status);
        }

        if ($request->filled('search')) {

            $search = strtolower(trim($request->search));

            $rows = $rows->filter(function ($row) use ($search) {

                return str_contains(strtolower($row['name']), $search) ||
                    str_contains(strtolower((string) $row['mobile_no']), $search);
            });
        }

        $rows = $rows->values();

        $total = $rows->count();

        $paged = $rows->forPage($page, $perPage)->values();

        $paginator = new LengthAwarePaginator($paged, $total, $perPage, $page);

        return response()->json([
            'status' => true,
            'data' => $paged,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => max($paginator->lastPage(), 1),
                'total' => $total
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function safeJoiningMonth(User $user): Carbon
    {
        if (!$user->date_of_joining) {
            return Carbon::now()->startOfMonth();
        }

        try {

            $date = Carbon::parse($user->date_of_joining);

            if ($date->year < 1990) {
                return Carbon::now()->startOfMonth();
            }

            return $date->startOfMonth();

        } catch (Exception $e) {

            return Carbon::now()->startOfMonth();
        }
    }

    private function overdueMonths(Carbon $nextDue, Carbon $today): array
    {
        $months = [];

        $cursor = $nextDue->copy();

        $guard = 0;

        while ($cursor->lte($today) && $guard < 600) {

            $months[] = $cursor->format('Y-m');

            $cursor->addMonthNoOverflow();

            $guard++;
        }

        return $months;
    }

    private function rateForMonth(string $yearMonth, $rates): float
    {
        $applicable = $rates
            ->filter(fn($r) => $r->effective_from->format('Y-m-d') <= $yearMonth . '-01')
            ->sortByDesc('effective_from')
            ->first();

        return $applicable ? (float) $applicable->monthly_rate : 0;
    }
}
