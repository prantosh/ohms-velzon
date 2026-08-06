<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /**
     * Override the trait's default: the stock version has no error handling
     * around the actual mail send, so any mail transport failure (wrong
     * SMTP/mail() config, an unverified sending domain, etc.) throws
     * uncaught and shows the user a raw 500 page instead of a message --
     * exactly what happened here. Catch it, log the real reason, and show
     * a normal "please try again" error instead.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);

        try {

            $response = $this->broker()->sendResetLink(
                $this->credentials($request)
            );

        } catch (\Throwable $e) {

            Log::error('Password reset email failed to send: ' . $e->getMessage());

            return back()->withErrors([
                'email' => 'We could not send the password reset email right now. Please try again later or contact support.',
            ]);
        }

        return $response == Password::RESET_LINK_SENT
                    ? $this->sendResetLinkResponse($request, $response)
                    : $this->sendResetLinkFailedResponse($request, $response);
    }
}
