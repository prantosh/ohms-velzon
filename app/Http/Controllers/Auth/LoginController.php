<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\MaintenanceSetting;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * Staff log in with their mobile number instead of email.
     */
    public function username()
    {
        return 'mobile_no';
    }

    /**
     * CheckMaintenanceMode exempts the login route itself (so the form and
     * the POST both still work -- otherwise Admin could never get back in
     * during maintenance), which means the block has to happen here, right
     * after credentials are verified, instead. Only then do we know the
     * authenticating user's role, which is what actually decides it.
     */
    protected function authenticated(Request $request, $user)
    {
        $setting = MaintenanceSetting::current();

        if (!$setting->is_enabled || $user->role === 'Admin') {
            return null;
        }

        // LogSuccessfulLogin (Illuminate\Auth\Events\Login listener) already
        // wrote a login_logs row for this attempt by this point -- it never
        // became a real session, so it shouldn't linger as one.
        LoginLog::where('user_id', $user->id)
            ->whereNull('logout_time')
            ->latest('id')
            ->limit(1)
            ->delete();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->view('pages-maintenance', [
            'maintenanceMessage' => $setting->message,
        ], 503);
    }
}
