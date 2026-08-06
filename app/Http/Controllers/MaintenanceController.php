<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MaintenanceController extends Controller
{
    private const MODULE_CODE = 'MAINTENANCE_MODE';

    /**
     * Toggling this affects every user of the application, so it is
     * restricted to Admin regardless of role_page_access -- same defense
     * in depth used for Cloud Database Backup and the UserController fix.
     */
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can control maintenance mode.');
        }
    }

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-maintenance-mode', [
            'setting' => MaintenanceSetting::current(),
        ]);
    }

    public function toggle(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate([
            'is_enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        $setting = MaintenanceSetting::current();

        $oldData = $setting->only($setting->getFillable());

        $setting->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'message' => $request->message,
            'enabled_by' => $request->boolean('is_enabled') ? Auth::id() : $setting->enabled_by,
            'enabled_at' => $request->boolean('is_enabled') ? now() : $setting->enabled_at,
        ]);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $setting,
            $oldData,
            $setting->only($setting->getFillable()),
            $request->boolean('is_enabled') ? 'Maintenance mode enabled' : 'Maintenance mode disabled'
        );

        MaintenanceSetting::forgetCache();

        return response()->json([
            'status' => true,
            'message' => $request->boolean('is_enabled')
                ? 'Maintenance mode is now ON. Only Admin can access the application.'
                : 'Maintenance mode is now OFF. The application is accessible to everyone again.',
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
    }
}
