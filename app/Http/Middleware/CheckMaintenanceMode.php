<?php

namespace App\Http\Middleware;

use App\Models\MaintenanceSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckMaintenanceMode
{
    /**
     * Unlike the screen-lock check, this blocks AJAX/POST too -- maintenance
     * mode exists to stop further activity (e.g. during a DB migration), so
     * letting background writes continue while it's "on" would defeat the
     * purpose. Admin always bypasses; everyone else (including guests, so
     * they see the maintenance page immediately rather than a login form
     * that dead-ends) is blocked except for the auth flow itself, so a
     * session that expires mid-maintenance can still log back in as Admin.
     */
    private const EXEMPT_PREFIXES = ['login', 'logout', 'password', 'register', 'maintenance-mode'];

    public function handle(Request $request, Closure $next)
    {
        $setting = MaintenanceSetting::current();

        $blocked = $setting->is_enabled
            && !$this->isExempt($request)
            && (!Auth::check() || Auth::user()->role !== 'Admin');

        if ($blocked) {

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'status' => false,
                    'message' => $setting->message ?: 'The application is currently under maintenance. Please check back later.',
                ], 503);
            }

            return response()->view('pages-maintenance', [
                'maintenanceMessage' => $setting->message,
            ], 503);
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        $path = $request->path();

        foreach (self::EXEMPT_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
