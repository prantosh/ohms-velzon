<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * User Reconciliation Dashboard: month-by-month collection / refund / doctor
 * payment per user (or all users), each month also carrying the running total
 * since the system began and the doctor payment still pending. Completed months
 * are the rows saved on the 1st of each month; the current month is month-to-date.
 */
class UserReconciliationController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private const NUMERIC_COLUMNS = [
        'period_collection_cash', 'period_collection_non_cash', 'period_refund', 'period_doctor_payable',
        'period_doctor_paid', 'period_net_cash',
        'cum_collection_cash', 'cum_collection_non_cash', 'cum_refund', 'cum_doctor_payable',
        'cum_doctor_paid', 'cum_net_cash', 'doctor_pending', 'doctor_pending_waiting',
    ];

    public function __construct(private UserReconciliationService $service)
    {
    }

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)->orderBy('name')->get(['id', 'name', 'role']);

        return view('apps-user-reconciliation', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |   user_id = EMPLOYEES -> one row per employee for one month (the default view)
    |   user_id = ALL       -> all users combined, one row per month
    |   user_id = <id>      -> that user, one row per month
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        [$mode, $userId] = $this->resolveSelection($request);

        return response()->json(array_merge(
            ['status' => true],
            $this->listData($mode, $userId, $request->query('month'))
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT (PDF) -- the list exactly as it is on screen
    |--------------------------------------------------------------------------
    */

    public function print(Request $request)
    {
        [$mode, $userId] = $this->resolveSelection($request);

        $data = $this->listData($mode, $userId, $request->query('month'));

        $label = match ($mode) {
            'employees' => 'All Employees -- ' . $data['month_label'],
            'all' => 'All Users (combined)',
            default => optional(User::find($userId))->name ?? 'User',
        };

        $pdf = Pdf::loadView('apps-user-reconciliation-pdf', [
            'mode' => $mode,
            'label' => $label,
            'data' => $data,
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('User-Reconciliation-' . str_replace(' ', '-', $label) . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL -- mode-wise and doctor-wise breakdown of one month
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        $userId = $this->resolveUser($request);

        return response()->json(array_merge(['status' => true], $this->detailData($userId, $request->month)));
    }

    public function printDetail(Request $request)
    {
        $request->validate(['month' => ['required', 'regex:/^\d{4}-\d{2}$/']]);

        $userId = $this->resolveUser($request);

        $detail = $this->detailData($userId, $request->month);

        $label = $userId === null ? 'All Users (combined)' : (optional(User::find($userId))->name ?? 'User');

        $pdf = Pdf::loadView('apps-user-reconciliation-detail-pdf', [
            'label' => $label,
            'detail' => $detail,
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('User-Reconciliation-Detail-' . str_replace(' ', '-', $label) . '-' . $request->month . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | DATA BUILDERS (shared by the screen and the PDFs)
    |--------------------------------------------------------------------------
    */

    private function listData(string $mode, ?int $userId, ?string $month): array
    {
        $this->service->ensureSnapshots();

        $base = [
            'mode' => $mode,
            'months' => $this->availableMonths(),
            'inception' => $this->service->inceptionDate(),
        ];

        if ($mode !== 'employees') {
            return $base + ['rows' => $this->monthRows($userId)];
        }

        $month = ($month && preg_match('/^\d{4}-\d{2}$/', $month)) ? $month : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();

        $rows = $this->employeeRows($start);

        return $base + [
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'range_fmt' => $this->rangeText($start),
            'rows' => $rows,
            'totals' => $this->sumColumns($rows),
        ];
    }

    /** one row per month for one user / all users combined */
    private function monthRows(?int $userId): array
    {
        $query = DB::table('user_monthly_reconciliations');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $rows = [];

        foreach ($query->get()->groupBy('month_start') as $monthStart => $items) {

            $row = $this->sumColumns($items->map(fn ($i) => (array) $i));

            $start = Carbon::parse($monthStart);

            $rows[] = array_merge($row, [
                'month' => $start->format('Y-m'),
                'month_label' => $start->format('F Y'),
                'range_fmt' => $this->rangeText($start),
                'is_live' => false,
            ]);
        }

        // current month, month-to-date
        $first = now()->startOfMonth();
        $live = $this->service->summarise($first->toDateString(), now()->toDateString());

        if ($userId !== null) {
            $live = isset($live[$userId]) ? [$userId => $live[$userId]] : [];
        }

        if (!empty($live) || !empty($rows)) {

            $rows[] = array_merge($this->sumColumns(collect($live)->map(fn ($r) => $r['columns'])), [
                'month' => $first->format('Y-m'),
                'month_label' => $first->format('F Y'),
                'range_fmt' => $this->rangeText($first),
                'is_live' => true,
            ]);
        }

        usort($rows, fn ($a, $b) => strcmp($b['month'], $a['month']));

        return $rows;
    }

    /** one row per employee (every user with activity up to the month's end) for one month */
    private function employeeRows(Carbon $start): array
    {
        if ($start->isSameMonth(now())) {

            $columnsByUser = collect($this->service->summarise($start->toDateString(), now()->toDateString()))
                ->map(fn ($r) => $r['columns']);

        } else {

            $columnsByUser = DB::table('user_monthly_reconciliations')
                ->where('month_start', $start->toDateString())
                ->get()
                ->keyBy('user_id')
                ->map(fn ($r) => (array) $r);
        }

        $users = User::whereIn('id', $columnsByUser->keys())->get(['id', 'name', 'role'])->keyBy('id');

        $rows = [];

        foreach ($columnsByUser as $id => $columns) {

            $rows[] = array_merge($this->sumColumns([$columns]), [
                'user_id' => (int) $id,
                'user_name' => $users[$id]->name ?? ('User #' . $id),
                'role' => $users[$id]->role ?? '',
            ]);
        }

        usort($rows, fn ($a, $b) => strcmp($a['user_name'], $b['user_name']));

        return $rows;
    }

    private function rangeText(Carbon $start): string
    {
        return $start->isSameMonth(now())
            ? $start->format('d-m-Y') . ' to ' . now()->format('d-m-Y') . ' (month to date)'
            : $start->format('d-m-Y') . ' to ' . $start->copy()->endOfMonth()->format('d-m-Y');
    }

    /** every month from the system's first data up to the current one, newest first */
    private function availableMonths(): array
    {
        $inception = $this->service->inceptionDate();

        if (!$inception) {
            return [];
        }

        $months = [];
        $m = Carbon::parse($inception)->startOfMonth();
        $last = now()->startOfMonth();

        while ($m->lte($last)) {
            $months[] = ['value' => $m->format('Y-m'), 'label' => $m->format('F Y')];
            $m->addMonth();
        }

        return array_reverse($months);
    }

    private function detailData(?int $userId, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfDay();

        $details = [];

        if ($start->isSameMonth(now())) {

            $live = $this->service->summarise($start->toDateString(), now()->toDateString());

            foreach ($live as $id => $row) {
                if ($userId === null || (int) $id === $userId) {
                    $details[] = $row['details'];
                }
            }

        } else {

            $this->service->ensureSnapshots();

            $query = DB::table('user_monthly_reconciliations')->where('month_start', $start->toDateString());

            if ($userId !== null) {
                $query->where('user_id', $userId);
            }

            foreach ($query->pluck('details') as $json) {
                $details[] = json_decode((string) $json, true) ?: [];
            }
        }

        $merged = $this->mergeDetails($details);

        // the invoice-wise lines are only meaningful for one user (across all users they cancel out)
        $items = $userId === null
            ? null
            : $this->service->crossUserItems(
                $userId,
                $start->isSameMonth(now()) ? now()->toDateString() : $start->copy()->endOfMonth()->toDateString()
            );

        return [
            'month' => $month,
            'month_label' => $start->format('F Y'),
            'range_fmt' => $this->rangeText($start),
            'modes' => $merged['modes'],
            'doctors' => $merged['doctors'],
            'reconciliation' => $merged['reconciliation'],
            'items' => $items,
        ];
    }
    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /** @return array{0:string,1:?int} [mode (employees|all|user), user id] */
    private function resolveSelection(Request $request): array
    {
        $request->validate(['user_id' => 'required']);

        if ($request->user_id === 'EMPLOYEES') {
            return ['employees', null];
        }

        $userId = $this->resolveUser($request);

        return [$userId === null ? 'all' : 'user', $userId];
    }

    /** null = all users */
    private function resolveUser(Request $request): ?int
    {
        $request->validate(['user_id' => 'required']);

        if ($request->user_id === 'ALL') {
            return null;
        }

        $request->validate(['user_id' => 'integer|exists:users,id']);

        return (int) $request->user_id;
    }

    private function sumColumns($items): array
    {
        $sum = array_fill_keys(self::NUMERIC_COLUMNS, 0.0);

        foreach ($items as $item) {
            foreach (self::NUMERIC_COLUMNS as $col) {
                $sum[$col] += (float) ($item[$col] ?? 0);
            }
        }

        return array_map(fn ($v) => round($v, 2), $sum);
    }

    private function mergeDetails(array $detailsList): array
    {
        $modes = [
            'collection' => ['period' => [], 'cum' => []],
            'refund' => ['period' => [], 'cum' => []],
            'doctor_paid' => ['period' => [], 'cum' => []],
        ];

        $doctors = [];

        $reconciliation = ['payable' => 0.0, 'paid' => 0.0, 'difference' => 0.0, 'pending' => 0.0, 'own_paid_by_others' => 0.0, 'paid_for_others' => 0.0];

        foreach ($detailsList as $d) {

            foreach ($reconciliation as $k => $v) {
                $reconciliation[$k] += (float) ($d['reconciliation'][$k] ?? 0);
            }

            foreach ($modes as $kind => $scopes) {
                foreach (['period', 'cum'] as $scope) {
                    foreach (($d['modes'][$kind][$scope] ?? []) as $mode => $amount) {
                        $modes[$kind][$scope][$mode] = ($modes[$kind][$scope][$mode] ?? 0) + (float) $amount;
                    }
                }
            }

            foreach (($d['doctors'] ?? []) as $doc) {

                $key = $doc['doctor_id'] ?? 0;

                $doctors[$key] ??= [
                    'doctor_id' => $key, 'doctor_name' => $doc['doctor_name'] ?? '-',
                    'payable' => 0.0, 'paid' => 0.0, 'pending' => 0.0, 'unpaid_count' => 0,
                ];

                $doctors[$key]['payable'] += (float) $doc['payable'];
                $doctors[$key]['paid'] += (float) $doc['paid'];
                $doctors[$key]['pending'] += (float) $doc['pending'];
                $doctors[$key]['unpaid_count'] += (int) $doc['unpaid_count'];
            }
        }

        foreach ($modes as $kind => $scopes) {
            foreach ($scopes as $scope => $byMode) {
                ksort($byMode);
                $modes[$kind][$scope] = array_map(fn ($v) => round($v, 2), $byMode);
            }
        }

        $doctors = array_values(array_map(function ($d) {
            foreach (['payable', 'paid', 'pending'] as $k) {
                $d[$k] = round($d[$k], 2);
            }
            return $d;
        }, $doctors));

        usort($doctors, fn ($a, $b) => strcmp((string) $a['doctor_name'], (string) $b['doctor_name']));

        return ['modes' => $modes, 'doctors' => $doctors, 'reconciliation' => array_map(fn ($v) => round($v, 2), $reconciliation)];
    }
}
