<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireAuthentication
{
    /**
     * Most routes in routes/web.php were never wrapped in 'auth', so a guest
     * opening a URL directly reached the controller and the layout then
     * crashed on Auth::user()->avatar. This closes that gap globally: every
     * web request from a guest is refused unless its path is one of the
     * deliberately public flows below (auth itself, the public appointment
     * booking embedded on the marketing site, and the public schedule PDF).
     */
    private const EXEMPT_PREFIXES = [
        'login',
        'logout',
        'register',
        'password',
        'book-appointment',
        'doctor-schedule.pdf',
        'index', // language switch: index/{locale}
    ];

    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() || $this->isExempt($request)) {
            return $next($request);
        }

        // The bare domain is the normal way in -- send it to the login form
        // rather than showing an error.
        if ($request->path() === '/') {
            return redirect()->guest(route('login'));
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'status' => false,
                'message' => 'You are not authorised to access this page. Please log in.',
            ], 401);
        }

        return response()->view('errors.401', [], 401);
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
