<?php

namespace App\Http\Controllers;

use App\Models\RolePageAccess;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class RolePageAccessController extends Controller
{
    private const MODULE_CODE = 'ROLE_PAGE_ACCESS';

    public function index()
    {
        return view('apps-role-page-access', [
            'pages' => config('access_pages'),
            'roles' => RolePageAccess::orderBy('role')->pluck('role')
        ]);
    }

    public function roles()
    {
        return response()->json([
            'status' => true,
            'roles' => RolePageAccess::orderBy('role')->pluck('role')
        ]);
    }

    public function createRole(Request $request, AuditService $auditService)
    {
        $request->validate([
            'role' => 'required|string|max:50'
        ]);

        $role = trim($request->role);

        $exists = RolePageAccess::whereRaw('LOWER(role) = ?', [strtolower($role)])
            ->exists();

        if ($exists) {

            return response()->json([
                'status' => false,
                'message' => "Role \"{$role}\" already exists."
            ], 422);
        }

        try {

            $access = RolePageAccess::create([
                'role' => $role,
                'page_access' => [],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $access,
                $access->only($access->getFillable()),
                'Role created'
            );

            return response()->json([
                'status' => true,
                'role' => $role,
                'message' => "Role \"{$role}\" created successfully."
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to create role.'
            ], 500);
        }
    }

    public function get($role)
    {
        $access = RolePageAccess::where('role', $role)->first();

        if (!$access) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid role.'
            ], 422);
        }

        return response()->json([
            'status' => true,
            'role' => $role,
            'page_access' => $access->page_access ?? []
        ]);
    }

    public function update(Request $request, $role, AuditService $auditService)
    {
        $access = RolePageAccess::where('role', $role)->first();

        if (!$access) {

            return response()->json([
                'status' => false,
                'message' => 'Invalid role.'
            ], 422);
        }

        $oldData = $access->only($access->getFillable());

        DB::beginTransaction();

        try {

            $access->page_access = $request->page_access ?? [];
            $access->updated_by = Auth::id();
            $access->save();

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $access,
                $oldData,
                $access->only($access->getFillable()),
                'Role page access updated'
            );

            return response()->json([
                'status' => true,
                'message' => "Page access for {$role} updated successfully."
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update page access.'
            ], 500);
        }
    }
}
