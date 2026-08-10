<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Completes the mobile-OTP password reset started in ForgotPasswordController:
 * takes the verification_token issued after a successful OTP verify and sets
 * the new password.
 */
class ResetPasswordController extends Controller
{
    private const OTP_PURPOSE = 'password_reset';
    private const VERIFICATION_WINDOW_MINUTES = 30;

    public function reset(Request $request)
    {
        $request->validate([
            'mobile_no' => 'required|regex:/^[1-9][0-9]{9}$/',
            'verification_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $otp = UserOtp::where('mobile_no', $request->mobile_no)
            ->where('purpose', self::OTP_PURPOSE)
            ->where('verification_token', $request->verification_token)
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->where('verified_at', '>', now()->subMinutes(self::VERIFICATION_WINDOW_MINUTES))
            ->first();

        if (!$otp) {
            return back()->withErrors([
                'mobile_no' => 'Mobile number verification has expired. Please verify your number again.',
            ]);
        }

        $user = User::where('mobile_no', $request->mobile_no)->first();

        if (!$user) {
            return back()->withErrors([
                'mobile_no' => 'No account found with this mobile number.',
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $otp->consumed_at = now();
        $otp->save();

        return redirect()->route('login')->with('status', 'Your password has been reset. Please log in.');
    }
}
