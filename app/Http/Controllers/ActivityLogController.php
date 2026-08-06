<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ActivityLogController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

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

        $modules = config('audit.modules', []);
        $actions = config('audit.actions', []);

        return view('apps-activity-log', compact('users', 'modules', 'actions'));
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN ACTIVITY
    |--------------------------------------------------------------------------
    */

    public function loginActivity(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        [$from, $to] = $this->resolvePeriod($request);

        $query = DB::table('login_logs')
            ->join('users', 'users.id', '=', 'login_logs.user_id')
            ->whereIn('users.role', self::STAFF_ROLES)
            ->whereBetween('login_logs.login_time', [$from, $to])
            ->select('login_logs.*', 'users.role as user_role');

        if ($request->filled('user_id')) {
            $query->where('login_logs.user_id', $request->user_id);
        }

        $logs = $query->orderByDesc('login_logs.login_time')->paginate($perPage);

        $logs->getCollection()->transform(function ($row) {

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
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT / ACTION ACTIVITY
    |--------------------------------------------------------------------------
    */

    public function auditActivity(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        [$from, $to] = $this->resolvePeriod($request);

        $query = DB::table('audit_logs')
            ->join('users', 'users.id', '=', 'audit_logs.user_id')
            ->leftJoin('invoices', function ($join) {
                $join->on('invoices.id', '=', DB::raw('CAST(audit_logs.record_id AS UNSIGNED)'))
                    ->where('audit_logs.table_name', '=', 'invoices');
            })
            ->whereIn('users.role', self::STAFF_ROLES)
            ->whereBetween('audit_logs.created_at', [$from, $to])
            ->select('audit_logs.*', 'users.role as user_role', 'invoices.invoice_no as related_invoice_no');

        if ($request->filled('user_id')) {
            $query->where('audit_logs.user_id', $request->user_id);
        }

        if ($request->filled('module_code')) {
            $query->where('audit_logs.module_code', $request->module_code);
        }

        if ($request->filled('action')) {
            $query->where('audit_logs.action', $request->action);
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('audit_logs.remarks', 'like', "%{$search}%")
                    ->orWhere('audit_logs.record_id', 'like', "%{$search}%")
                    ->orWhere('audit_logs.table_name', 'like', "%{$search}%")
                    ->orWhere('invoices.invoice_no', 'like', "%{$search}%");
            });
        }

        $logs = $query->orderByDesc('audit_logs.id')->paginate($perPage);

        $logs->getCollection()->transform(function ($row) {

            $row->created_at_fmt = $row->created_at
                ? Carbon::parse($row->created_at)->format('d-m-Y h:i A')
                : null;

            $row->module_name_display = config("audit.modules.{$row->module_code}", $row->module_code);

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total()
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function resolvePeriod(Request $request): array
    {
        $from = $request->filled('from_date')
            ? Carbon::parse($request->from_date)->startOfDay()
            : Carbon::now()->subDays(7)->startOfDay();

        $to = $request->filled('to_date')
            ? Carbon::parse($request->to_date)->endOfDay()
            : Carbon::now()->endOfDay();

        return [$from, $to];
    }
}
