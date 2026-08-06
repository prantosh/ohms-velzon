<?php

namespace App\Support;

/**
 * When the app is running as the offline disaster-recovery fallback
 * (APP_OFFLINE_MODE=true, set only on that instance's .env — never on
 * production), every generated document number/code gets an 'OFF' prefix
 * so it can never collide with a number production generated, no matter
 * how stale the restored backup was. See docs/OFFLINE_DISASTER_RECOVERY.md.
 */
class OfflineMode
{
    public static function isActive(): bool
    {
        return (bool) config('app.offline_mode');
    }

    public static function prefix(string $prefix): string
    {
        return self::isActive() ? 'OFF' . $prefix : $prefix;
    }
}
