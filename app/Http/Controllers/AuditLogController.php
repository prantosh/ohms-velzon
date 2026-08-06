<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AuditLogController extends Controller
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

        return view('apps-audit-logs', [
            'users' => $users,
            'modules' => config('audit.modules', []),
            'actions' => config('audit.actions', []),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        [$from, $to] = $this->resolvePeriod($request);

        $query = AuditLog::query()
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->user($request->user_id ?: null)
            ->module($request->module_code ?: null)
            ->action($request->action ?: null);

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', '%' . trim($request->ip_address) . '%');
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('remarks', 'like', "%{$search}%")
                    ->orWhere('record_id', 'like', "%{$search}%")
                    ->orWhere('table_name', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->latestFirst()->paginate($perPage);

        $roleByUserId = User::whereIn('id', $logs->getCollection()->pluck('user_id')->filter()->unique())
            ->pluck('role', 'id');

        $logs->getCollection()->transform(function ($row) use ($roleByUserId) {

            $row->created_at_fmt = optional($row->created_at)->format('d-m-Y h:i A');

            $row->module_name_display = $row->module_name
                ?: config("audit.modules.{$row->module_code}", $row->module_code);

            $row->user_role_snapshot = $roleByUserId->get($row->user_id);

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (old vs new data)
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $log = AuditLog::with('user:id,name,role')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $log->id,
                'created_at_fmt' => optional($log->created_at)->format('d-m-Y h:i:s A'),
                'module_name' => $log->module_name,
                'action' => $log->action,
                'table_name' => $log->table_name,
                'record_id' => $log->record_id,
                'user_name' => $log->user_name,
                'user_role' => optional($log->user)->role,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'request_url' => $log->request_url,
                'remarks' => $log->remarks,
                'old_data' => $log->old_data,
                'new_data' => $log->new_data,
                'changed_data' => $log->changed_data,
            ],
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
