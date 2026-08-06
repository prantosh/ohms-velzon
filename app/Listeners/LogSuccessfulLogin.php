<?php
namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\LoginLog;

class LogSuccessfulLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        $loginTime = now();

        // Users have no way to explicitly close a previous session (no
        // "logout other sessions" option), so any prior login for this
        // user that never got a logout_time is stale by the time a new
        // one starts -- close it out 1 second before this login so it
        // stops counting as a duplicate "live" session.
        LoginLog::where('user_id', $event->user->id)
            ->whereNull('logout_time')
            ->update(['logout_time' => (clone $loginTime)->subSecond()]);

        LoginLog::create([
            'user_id' => $event->user->id,
            'name' => $event->user->name,
            'email' => $event->user->email,
            'login_time' => $loginTime,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
