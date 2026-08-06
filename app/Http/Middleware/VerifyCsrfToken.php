<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Public, anonymous, no-login patient appointment booking -- session
        // cookies may not round-trip if the marketing site embeds this page
        // in an iframe (cross-site SameSite=Lax restrictions). These routes
        // are already rate-limited and gated by WhatsApp-OTP possession, so
        // CSRF's threat model (abusing a victim's authenticated session)
        // doesn't apply here.
        'book-appointment/*',
    ];
}
