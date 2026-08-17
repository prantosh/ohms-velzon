<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserOtp;
use App\Models\WhatsappAutoSendSetting;
use App\Services\WatiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Staff password reset -- mobile number + WhatsApp OTP, same mechanism as
 * the public doctor-booking flow in PublicAppointmentController, instead of
 * Laravel's stock emailed reset-link flow.
 */
class ForgotPasswordController extends Controller
{
    private const OTP_PURPOSE = 'password_reset';
    private const OTP_EXPIRY_MINUTES = 3;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;
    private const OTP_MAX_PER_HOUR = 5;
    private const OTP_MAX_ATTEMPTS = 5;
    private const VERIFICATION_WINDOW_MINUTES = 30;

    public function __construct(private WatiService $wati)
    {
    }

    public function showLinkRequestForm()
    {
        return view('auth.passwords.mobile');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile_no' => 'required|regex:/^[1-9][0-9]{9}$/',
        ]);

        $mobile = $request->mobile_no;

        $user = User::where('mobile_no', $mobile)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'No account found with this mobile number.',
            ]);
        }

        // Checked before the cooldown/quota reads and before any OTP row is
        // created -- a disabled category must not consume a user's resend
        // cooldown or hourly quota on a message that can never arrive.
        if (!WhatsappAutoSendSetting::isEnabled('OTP_FORGOT_PASSWORD')) {
            return response()->json([
                'status' => false,
                'message' => 'OTP delivery is temporarily unavailable. Please contact the Admin to reset your password.',
            ]);
        }

        $recentlySent = UserOtp::where('mobile_no', $mobile)
            ->where('purpose', self::OTP_PURPOSE)
            ->where('created_at', '>', now()->subSeconds(self::OTP_RESEND_COOLDOWN_SECONDS))
            ->exists();

        if ($recentlySent) {
            return response()->json([
                'status' => false,
                'message' => 'Please wait a minute before requesting another OTP.',
            ]);
        }

        $sentThisHour = UserOtp::where('mobile_no', $mobile)
            ->where('purpose', self::OTP_PURPOSE)
            ->where('created_at', '>', now()->subHour())
            ->count();

        if ($sentThisHour >= self::OTP_MAX_PER_HOUR) {
            return response()->json([
                'status' => false,
                'message' => 'Too many OTP requests for this number. Please try again later.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $otp = UserOtp::create([
            'mobile_no' => $mobile,
            'otp_code_hash' => hash('sha256', $code),
            'purpose' => self::OTP_PURPOSE,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        if (app()->environment(['local', 'staging'])) {
            Log::debug('Staff password reset OTP generated (dev only)', ['mobile' => $mobile, 'code' => $code]);
        }

        $whatsappResponse = null;
        $whatsappSent = false;

        try {

            $whatsappResponse = $this->wati->sendTemplateMessage(
                '91' . preg_replace('/\D/', '', $mobile),
                config('services.wati.otp_template_name'),
                config('services.wati.otp_broadcast_name'),
                [
                    ['name' => '1', 'value' => $code],
                ]
            );

            $whatsappSent = is_array($whatsappResponse) && ($whatsappResponse['result'] ?? false) === true;

        } catch (\Exception $e) {

            Log::error('Staff Password Reset OTP WhatsApp Error: ' . $e->getMessage());

            $whatsappResponse = ['error' => $e->getMessage()];
        }

        DB::table('whatsapp_message_logs')->insert([
            'invoice_no' => 'PWDOTP-' . $otp->id,
            'mobile_no' => $mobile,
            'patient_name' => $user->name,
            'message_type' => 'OTP_FORGOT_PASSWORD',
            'status' => $whatsappSent ? 'SENT' : 'FAILED',
            'response' => json_encode($whatsappResponse),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!$whatsappSent) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP. Please try again in a moment.',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'OTP sent via WhatsApp.',
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'mobile_no' => 'required|regex:/^[1-9][0-9]{9}$/',
            'otp_code' => 'required|digits:6',
        ]);

        $otp = UserOtp::active($request->mobile_no, self::OTP_PURPOSE)
            ->orderByDesc('id')
            ->first();

        if (!$otp) {
            return response()->json([
                'status' => false,
                'message' => 'OTP expired or not requested. Please request a new one.',
            ]);
        }

        if ($otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            return response()->json([
                'status' => false,
                'message' => 'Too many incorrect attempts. Please request a new OTP.',
            ]);
        }

        if (!hash_equals($otp->otp_code_hash, hash('sha256', $request->otp_code))) {

            $otp->increment('attempts');

            return response()->json([
                'status' => false,
                'message' => 'Incorrect OTP.',
            ]);
        }

        $otp->verified_at = now();
        $otp->verification_token = Str::random(40);
        $otp->save();

        return response()->json([
            'status' => true,
            'message' => 'Mobile number verified.',
            'verification_token' => $otp->verification_token,
        ]);
    }
}
