<?php

namespace App\Http\Controllers;

use App\Models\PatientOtp;
use App\Models\Specialisation;
use App\Services\AppointmentBookingService;
use App\Services\DoctorScheduleQueryService;
use App\Services\WatiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Public (no login) patient appointment booking. Every route here is
 * intentionally left out of config/page_route_map.php so CheckPageAccess
 * lets it through unauthenticated -- see routes/web.php for the group.
 *
 * Depends only on DoctorScheduleQueryService/AppointmentBookingService, not
 * on DoctorAppointmentController or any of its routes, so the internal
 * controller's routes can be (and are) fully auth-gated without risk of
 * breaking this page.
 */
class PublicAppointmentController extends Controller
{
    private const OTP_PURPOSE = 'appointment_booking';
    private const OTP_EXPIRY_MINUTES = 3;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;
    private const OTP_MAX_PER_HOUR = 5;
    private const OTP_MAX_ATTEMPTS = 5;
    private const VERIFICATION_WINDOW_MINUTES = 30;

    public function __construct(
        private DoctorScheduleQueryService $scheduleQuery,
        private AppointmentBookingService $bookingService,
        private WatiService $wati
    ) {
    }

    public function index()
    {
        $specialisations = Specialisation::orderBy('category')->get();

        return view('public-appointment-booking', compact('specialisations'));
    }

    public function getDoctorsBySpecialisation(Request $request)
    {
        return response()->json(
            $this->scheduleQuery->getDoctorsBySpecialisation($request->specialisation_id)
        );
    }

    public function getAvailableDays(Request $request)
    {
        // Public patients can never book a "By Appointment" day -- exclude
        // it here so the day picker doesn't offer a day with no bookable slot.
        return response()->json(
            $this->scheduleQuery->getAvailableDays($request->doctor_id, excludeByAppointment: true)
        );
    }

    public function getAvailableSlots(Request $request)
    {
        return response()->json(
            $this->scheduleQuery->getAvailableSlots(
                $request->doctor_id,
                $request->appointment_date,
                excludeByAppointment: true
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | OTP
    |--------------------------------------------------------------------------
    */

    public function sendOtp(Request $request)
    {
        $request->validate([
            'mobile_no' => 'required|regex:/^[1-9][0-9]{9}$/',
        ]);

        $mobile = $request->mobile_no;

        $recentlySent = PatientOtp::where('mobile_no', $mobile)
            ->where('purpose', self::OTP_PURPOSE)
            ->where('created_at', '>', now()->subSeconds(self::OTP_RESEND_COOLDOWN_SECONDS))
            ->exists();

        if ($recentlySent) {
            return response()->json([
                'status' => false,
                'message' => 'Please wait a minute before requesting another OTP.',
            ]);
        }

        $sentThisHour = PatientOtp::where('mobile_no', $mobile)
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

        $otp = PatientOtp::create([
            'mobile_no' => $mobile,
            'otp_code_hash' => hash('sha256', $code),
            'purpose' => self::OTP_PURPOSE,
            'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);

        if (app()->environment(['local', 'staging'])) {
            Log::debug('Public appointment OTP generated (dev only)', ['mobile' => $mobile, 'code' => $code]);
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

            Log::error('Public Appointment OTP WhatsApp Error: ' . $e->getMessage());

            $whatsappResponse = ['error' => $e->getMessage()];
        }

        DB::table('whatsapp_message_logs')->insert([
            'invoice_no' => 'OTP-' . $otp->id,
            'mobile_no' => $mobile,
            'message_type' => 'OTP',
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

        $otp = PatientOtp::active($request->mobile_no, self::OTP_PURPOSE)
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

    /*
    |--------------------------------------------------------------------------
    | BOOK
    |--------------------------------------------------------------------------
    */

    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required',
            'doctor_schedule_session_id' => 'required|exists:doctor_schedule_sessions,id',
            'patient_name' => 'required',
            'patient_mobile_no' => 'required|regex:/^[1-9][0-9]{9}$/',
            'patient_age' => 'required',
            'patient_gender' => 'required',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'verification_token' => 'required|string',
        ]);

        $otp = PatientOtp::where('mobile_no', $request->patient_mobile_no)
            ->where('purpose', self::OTP_PURPOSE)
            ->where('verification_token', $request->verification_token)
            ->whereNotNull('verified_at')
            ->whereNull('consumed_at')
            ->where('verified_at', '>', now()->subMinutes(self::VERIFICATION_WINDOW_MINUTES))
            ->first();

        if (!$otp) {
            return response()->json([
                'status' => false,
                'message' => 'Mobile number verification has expired. Please verify your number again.',
            ]);
        }

        $result = $this->bookingService->book([
            'doctor_id' => $request->doctor_id,
            'doctor_schedule_session_id' => $request->doctor_schedule_session_id,
            'patient_name' => $request->patient_name,
            'patient_mobile_no' => $request->patient_mobile_no,
            'patient_age' => $request->patient_age,
            'patient_gender' => $request->patient_gender,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'remarks' => null,
            'created_by' => null,
        ]);

        if ($result['status']) {
            $otp->consumed_at = now();
            $otp->save();
        }

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'appointment_no' => optional($result['appointment'])->appointment_no,
        ]);
    }

    public function confirmation(string $appointmentNo)
    {
        $appointment = DB::table('doctor_appointments')
            ->join('doctors', 'doctors.id', '=', 'doctor_appointments.doctor_id')
            ->where('doctor_appointments.appointment_no', $appointmentNo)
            ->select(
                'doctor_appointments.appointment_no',
                'doctor_appointments.appointment_date',
                'doctor_appointments.appointment_time',
                'doctor_appointments.token_no',
                'doctor_appointments.patient_name',
                'doctors.doctor_name'
            )
            ->first();

        if (!$appointment) {
            abort(404);
        }

        return view('public-appointment-confirmation', compact('appointment'));
    }
}
