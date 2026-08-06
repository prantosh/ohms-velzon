<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Models\LoginLog;

class LogSuccessfulLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if (!$event->user) {
            return;
        }

        LoginLog::where('user_id', $event->user->id)
            ->whereNull('logout_time')
            ->latest()
            ->first()
                ?->update([
                'logout_time' => now()
            ]);
    }
}
