<?php

namespace App\Http\Middleware;

use App\Models\RolePageAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckPageAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()?->getName();

        $routeMap = config('page_route_map');

        $pageKey = $routeName ? ($routeMap[$routeName] ?? null) : null;

        if (!$pageKey) {
            return $next($request);
        }

        if (!Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $user = Auth::user();

        if ($user->role === 'Admin') {
            return $next($request);
        }

        $allowedPages = RolePageAccess::where('role', $user->role)
            ->value('page_access') ?? [];

        if (!in_array($pageKey, $allowedPages)) {

            abort(403, 'You do not have access to this page. Contact your administrator.');
        }

        return $next($request);
    }
}
