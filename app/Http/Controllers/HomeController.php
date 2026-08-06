<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use App\Models\Specialisation;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        if ($request->path() == 'doctor') {

            $specialisations =
                Specialisation::orderBy('category')->get();

            return view(
                'apps-ecommerce-doctors',
                compact('specialisations')
            );
        }

        if ($request->path() == 'pages-profile-settings') {

            $profileUser = Auth::user();

            $loginHistory = \App\Models\LoginLog::where('user_id', $profileUser->id)
                ->orderByDesc('login_time')
                ->limit(10)
                ->get()
                ->map(function ($log) {

                    $parsed = $this->parseUserAgent($log->user_agent);

                    return [
                        'label' => $parsed['label'],
                        'icon' => $parsed['icon'],
                        'ip_address' => $log->ip_address,
                        'login_time_fmt' => $log->login_time ? Carbon::parse($log->login_time)->format('d M, Y h:i A') : null,
                        'logout_time_fmt' => $log->logout_time ? Carbon::parse($log->logout_time)->format('d M, Y h:i A') : null,
                        'still_active' => empty($log->logout_time),
                    ];
                });

            return view(
                'pages-profile-settings',
                [
                    'profileUser' => $profileUser,
                    'createdByName' => optional(User::find($profileUser->created_by))->name,
                    'updatedByName' => optional(User::find($profileUser->updated_by))->name,
                    'loginHistory' => $loginHistory,
                ]
            );
        }

        if (in_array($request->path(), ['auth-lockscreen-basic', 'auth-lockscreen-cover'])) {

            return view(
                $request->path(),
                ['lockUser' => Auth::user()]
            );
        }

        if (view()->exists($request->path())) {

            return view($request->path());
        }

        return abort(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Login History Helper
    |--------------------------------------------------------------------------
    */

    private function parseUserAgent(?string $userAgent): array
    {
        $ua = $userAgent ?? '';

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'iOS') => 'iOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        $icon = match (true) {
            str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile')) => 'ri-tablet-line',
            str_contains($ua, 'iPhone') || (str_contains($ua, 'Android') && str_contains($ua, 'Mobile')) => 'ri-smartphone-line',
            default => 'ri-computer-line',
        };

        return [
            'label' => "{$browser} on {$os}",
            'icon' => $icon,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Unlock Screen
    |--------------------------------------------------------------------------
    */

    public function lockScreen()
    {
        session(['screen_locked' => true]);

        return redirect('auth-lockscreen-basic');
    }

    public function unlockScreen(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!Hash::check($request->get('password'), Auth::user()->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Incorrect password. Please try again.'
            ]);
        }

        session(['screen_locked' => false]);

        return response()->json([
            'status' => true,
            'redirect' => url('/'),
        ]);
    }

    public function root()
    {
        $today = Carbon::today();

        /*
        |--------------------------------------------------------------------------
        | TODAY INVOICE SUMMARY
        |--------------------------------------------------------------------------
        */

        $todayInvoices = DB::table('invoices')
            ->selectRaw('
            invoice_type,
            COUNT(*) total_count,
            SUM(paid_amount) total_amount
        ')
            ->whereDate('invoice_date', $today)
            ->where(function ($q) {
                $q->whereNull('cancelled')
                    ->orWhere('cancelled', '!=', 'Y');
            })
            ->groupBy('invoice_type')
            ->get();


        $totalPatientsToday =
            DB::table('doctor_appointments')
                ->whereDate('appointment_date', today())
                ->count();
        $todayPathology =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'PAT001')
                ->count();

        $todayXray =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'XRY001')
                ->count();

        $todayUSG =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'USG001')
                ->count();

        $todayEndoscopy =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'END001')
                ->count();

        $todayDental =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'DEN001')
                ->count();

        $todayEye =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'EYE001')
                ->count();

        $todayCardiology =
            DB::table('invoice_details')
                ->whereDate('created_at', today())
                ->where('item_code', 'CRD001')
                ->count();
        $doctorChart =
            DB::table('doctor_appointments as da')
                ->join('doctors as d', 'da.doctor_id', '=', 'd.id')
                ->selectRaw('d.doctor_name, count(*) total')
                ->whereDate('appointment_date', today())
                ->groupBy('d.doctor_name')
                ->get();

        /*
        |--------------------------------------------------------------------------
        | MY TODAY INVOICES
        |--------------------------------------------------------------------------
        */

        $myInvoices = DB::table('invoices')
            ->selectRaw('
            invoice_type,
            COUNT(*) total_count,
            SUM(paid_amount) total_amount
        ')
            ->whereDate('invoice_date', $today)
            ->where('created_by', Auth::id())
            ->where(function ($q) {
                $q->whereNull('cancelled')
                    ->orWhere('cancelled', '!=', 'Y');
            })
            ->groupBy('invoice_type')
            ->get();

        // "Today's Invoices By Me" widget tiles. Doctor Visit / Diagnostic /
        // Oxygen+Concentrator come from $myInvoices above (grouped by
        // invoice_type); Refund and Doctor Payment Made are separate money
        // movements recorded on daily_transactions, not on invoices at all
        // (a refund/doctor payment isn't itself an invoice row), so they're
        // queried independently here with the same today + created_by=me
        // filtering used for every other figure in this widget.
        $doctorVisitRow = $myInvoices->firstWhere('invoice_type', 'DOCTOR_VISIT');
        $diagnosticRow = $myInvoices->firstWhere('invoice_type', 'DIAGNOSTIC');

        $oxygenConcentratorCount = $myInvoices
            ->whereIn('invoice_type', ['OXYGEN_RENT', 'CONCENTRATOR_RENT'])
            ->sum('total_count');

        $oxygenConcentratorAmount = $myInvoices
            ->whereIn('invoice_type', ['OXYGEN_RENT', 'CONCENTRATOR_RENT'])
            ->sum('total_amount');

        $myRefunds = DB::table('daily_transactions')
            ->where('transaction_type', 'REFUND')
            ->whereDate('transaction_date', $today)
            ->where('created_by', Auth::id())
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw('COUNT(*) total_count, SUM(refund_amount) total_amount')
            ->first();

        $myDoctorPayments = DB::table('daily_transactions')
            ->where('transaction_type', 'PAYMENT')
            ->whereDate('transaction_date', $today)
            ->where('created_by', Auth::id())
            ->where('status', '!=', 'CANCELLED')
            ->selectRaw('COUNT(*) total_count, SUM(doctor_payment_amount) total_amount')
            ->first();

        $myInvoiceSummary = [
            [
                'label' => 'Doctor Visit Collection',
                'count' => $doctorVisitRow->total_count ?? 0,
                'amount' => $doctorVisitRow->total_amount ?? 0,
                'color' => 'primary',
                'icon' => 'ri-stethoscope-line',
                'amount_caption' => 'Collection',
            ],
            [
                'label' => 'Diagnostic Collection',
                'count' => $diagnosticRow->total_count ?? 0,
                'amount' => $diagnosticRow->total_amount ?? 0,
                'color' => 'success',
                'icon' => 'ri-test-tube-line',
                'amount_caption' => 'Collection',
            ],
            [
                'label' => 'Oxygen/Concentrator Collection',
                'count' => $oxygenConcentratorCount,
                'amount' => $oxygenConcentratorAmount,
                'color' => 'warning',
                'icon' => 'ri-gas-station-line',
                'amount_caption' => 'Collection',
            ],
            [
                'label' => 'Refund',
                'count' => $myRefunds->total_count ?? 0,
                'amount' => $myRefunds->total_amount ?? 0,
                'color' => 'danger',
                'icon' => 'ri-refund-2-line',
                'amount_caption' => 'Refunded',
            ],
            [
                'label' => 'Doctor Payment Made',
                'count' => $myDoctorPayments->total_count ?? 0,
                'amount' => $myDoctorPayments->total_amount ?? 0,
                'color' => 'dark',
                'icon' => 'ri-hand-coin-line',
                'amount_caption' => 'Paid',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | TODAY COLLECTION
        |--------------------------------------------------------------------------
        */

        $todayCollection =
            DB::table('daily_transactions')
                ->whereDate('transaction_date', $today)
                ->sum('received_amount');

        /*
        |--------------------------------------------------------------------------
        | PENDING DUES
        |--------------------------------------------------------------------------
        */

        $pendingDue =
            DB::table('invoices')
                ->where('due_amount', '>', 0)
                ->sum('due_amount');

        /*
        |--------------------------------------------------------------------------
        | DOCTOR WISE APPOINTMENT COUNT
        |--------------------------------------------------------------------------
        */

        $doctorAppointments =
            DB::table('doctor_appointments as da')
                ->join('doctors as d', 'da.doctor_id', '=', 'd.id')
                ->select(
                    'da.appointment_date',
                    'd.doctor_name',
                    'd.mobile_no',
                    'd.consultation_fee_total',
                    DB::raw('COUNT(*) as total_appointments')
                )
                ->whereDate('da.appointment_date', '>=', $today)
                ->groupBy(
                    'da.appointment_date',
                    'd.doctor_name',
                    'd.mobile_no',
                    'd.consultation_fee_total'
                )
                ->orderBy('da.appointment_date')
                ->orderBy('d.doctor_name')
                ->get();

        /*
        |--------------------------------------------------------------------------
        | RECENT TRANSACTIONS
        |--------------------------------------------------------------------------
        */
        $todayDoctorCount =
            DB::table('doctor_appointments')
                ->whereDate('appointment_date', $today)
                ->distinct()
                ->count('doctor_id');

        $todayBookingCount =
            DB::table('doctor_appointments')
                ->whereDate('appointment_date', $today)
                ->count();

        // Today onward (same as schedule table)
        $futureDoctorCount =
            DB::table('doctor_appointments')
                ->whereDate('appointment_date', '>=', $today)
                ->distinct()
                ->count('doctor_id');

        $futureBookingCount =
            DB::table('doctor_appointments')
                ->whereDate('appointment_date', '>=', $today)
                ->count();
        $recentTransactions =
            DB::table('daily_transactions')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        return view(
            'index',
            compact(
                'todayInvoices',
                'myInvoices',
                'myInvoiceSummary',
                'todayCollection',
                'pendingDue',
                'doctorAppointments',
                'recentTransactions',
                'totalPatientsToday',
                'doctorChart',
                'todayUSG',
                'todayXray',
                'todayPathology',
                'todayEndoscopy',
                'todayDental',
                'todayEye',
                'todayCardiology',
                'todayDoctorCount',
                'todayBookingCount',
                'futureDoctorCount',
                'futureBookingCount'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Live Users (for the dashboard marquee)
    |--------------------------------------------------------------------------
    | "Live" = latest login_log row per user has no logout_time recorded yet
    | -- the same "Still Active" signal already shown per-session on the
    | Profile Settings login-history page, just aggregated across users.
    */

    public function liveUsers()
    {
        $latestLoginIdPerUser = DB::table('login_logs')
            ->selectRaw('MAX(id) as id')
            ->groupBy('user_id');

        $liveUsers = DB::table('login_logs')
            ->joinSub($latestLoginIdPerUser, 'latest', 'latest.id', '=', 'login_logs.id')
            ->join('users', 'users.id', '=', 'login_logs.user_id')
            ->whereNull('login_logs.logout_time')
            ->where('users.role', '!=', 'Admin')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.avatar', 'users.user_image']);

        $liveUsers = $liveUsers->map(function ($user) {
            // user_image (set via the admin Users screen, UserController) is
            // how virtually every real staff photo actually gets set; avatar
            // (set via the self-service Profile screen, updateProfile() above)
            // is a separate field that UserController::store() explicitly
            // blanks to '' for every new user -- checking avatar alone (as
            // this did before) fell back to the generic placeholder for
            // almost everyone, even users with a real photo on file.
            $user->avatar_url = !empty($user->user_image)
                ? asset('uploads/users/' . $user->user_image)
                : (!empty($user->avatar)
                    ? asset('images/' . $user->avatar)
                    : asset('build/images/users/avatar-1.jpg'));
            return $user;
        });

        return response()->json([
            'status' => true,
            'users' => $liveUsers->values(),
        ]);
    }

    /*Language Translation*/
    public function lang($locale)
    {
        if ($locale) {
            App::setLocale($locale);
            Session::put('lang', $locale);
            Session::save();
            return redirect()->back()->with('locale', $locale);
        } else {
            return redirect()->back();
        }
    }

    private const USER_MODULE_CODE = 'USER';

    public function updateProfile(Request $request, $id, AuditService $auditService)
    {
        if ((int) $id !== (int) Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'unique:users,email,' . $id],
            'mobile_no' => ['nullable', 'string', 'max:10'],
            'date_of_joining' => ['nullable', 'string'],
            'date_of_birth' => ['nullable', 'string'],
            'gender' => ['nullable', 'in:Male,Female,Other'],
            'aadhar_no' => ['nullable', 'string', 'max:20'],
            'pan_no' => ['nullable', 'string', 'max:10'],
            'qualification' => ['nullable', 'string', 'max:50'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'guardian_name' => ['nullable', 'string', 'max:100'],
            'present_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'emergency_mobile_no' => ['nullable', 'string', 'max:10'],
            'bank_ac_no' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ]);

        $user = User::find($id);

        $oldData = collect($user->only($user->getFillable()))->except('password')->toArray();

        $user->name = $request->get('name');
        $user->email = $request->get('email');
        $user->mobile_no = $request->get('mobile_no');
        $user->gender = $request->get('gender');
        $user->aadhar_no = $request->get('aadhar_no');
        $user->pan_no = $request->get('pan_no');
        $user->qualification = $request->get('qualification');
        $user->blood_group = $request->get('blood_group');
        $user->father_name = $request->get('father_name');
        $user->guardian_name = $request->get('guardian_name');
        $user->present_address = $request->get('present_address');
        $user->permanent_address = $request->get('permanent_address');
        $user->emergency_mobile_no = $request->get('emergency_mobile_no');
        $user->bank_ac_no = $request->get('bank_ac_no');
        $user->bank_name = $request->get('bank_name');
        $user->bank_branch = $request->get('bank_branch');

        foreach (['date_of_joining', 'date_of_birth'] as $dateField) {

            if ($request->filled($dateField)) {

                try {
                    $user->{$dateField} = \Carbon\Carbon::parse($request->get($dateField))->format('Y-m-d');
                } catch (\Exception $e) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        $dateField => 'The ' . str_replace('_', ' ', $dateField) . ' is not a valid date.',
                    ]);
                }
            }
        }

        if ($request->file('avatar')) {
            $avatar = $request->file('avatar');
            $avatarName = time() . '.' . $avatar->getClientOriginalExtension();
            $avatarPath = public_path('/images/');
            $avatar->move($avatarPath, $avatarName);
            $user->avatar = $avatarName;

            // Keep the Users master dashboard (which reads user_image) in sync
            // with the photo uploaded here.
            $userImageDir = public_path('uploads/users');
            if (!is_dir($userImageDir)) {
                mkdir($userImageDir, 0755, true);
            }
            copy($avatarPath . $avatarName, $userImageDir . '/' . $avatarName);
            $user->user_image = $avatarName;
        }

        $user->save();

        $auditService->logUpdate(
            self::USER_MODULE_CODE,
            $user,
            $oldData,
            collect($user->only($user->getFillable()))->except('password')->toArray(),
            'User profile self-updated'
        );

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully!'
        ]);
    }

    public function updatePassword(Request $request, $id, AuditService $auditService)
    {
        if ((int) $id !== (int) Auth::id()) {
            abort(403);
        }

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (!Hash::check($request->get('current_password'), Auth::user()->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Your current password does not match the password you provided. Please try again.'
            ]);
        }

        $user = User::find($id);
        $user->password = Hash::make($request->get('password'));
        $user->save();

        $auditService->logAction(
            self::USER_MODULE_CODE,
            $user,
            \App\Models\AuditLog::ACTION_CUSTOM,
            'User self-updated their password'
        );

        return response()->json([
            'status' => true,
            'message' => 'Password updated successfully!'
        ]);
    }
}
