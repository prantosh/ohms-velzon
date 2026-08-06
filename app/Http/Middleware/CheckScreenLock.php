<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckScreenLock
{
    private const EXEMPT_PATHS = [
        'auth-lockscreen-basic',
        'auth-lockscreen-cover',
        'unlock-screen',
        'lock-screen',
        'logout',
    ];

    /**
     * Once a session is flagged screen_locked, any full-page browser
     * navigation (including the browser Back/Forward button, which would
     * otherwise re-render a page without the lock screen intervening) is
     * redirected back to the lock screen until unlockScreen() clears the
     * flag. AJAX/JSON requests are left alone so in-page functionality on
     * an already-open tab keeps working -- this only closes the "navigate
     * away from the lock screen" bypass, not a full request-level lockdown.
     */
    public function handle(Request $request, Closure $next)
    {
        if (
            Auth::check()
            && session('screen_locked')
            && $request->isMethod('GET')
            && !$request->ajax()
            && !$request->wantsJson()
            && !in_array($request->path(), self::EXEMPT_PATHS, true)
        ) {
            return redirect('auth-lockscreen-basic');
        }

        $response = $next($request);

        if (Auth::check()) {
            // Without this, a browser can restore a previously rendered
            // authenticated page straight from its back/forward cache on
            // Back navigation without ever asking the server again, which
            // would bypass the redirect above entirely.
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
