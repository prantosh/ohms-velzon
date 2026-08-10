@extends('layouts.master-without-nav')
@section('title')
    @lang('translation.password-reset')
@endsection
@section('css')
<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
<style>
    #otpInputs input {
        width: 45px;
        text-align: center;
        font-size: 20px;
        font-weight: 700;
    }

    .reset-step {
        display: none;
    }

    .reset-step.active {
        display: block;
    }
</style>
@endsection
@section('content')
    <div class="auth-page-wrapper pt-5">
        <!-- auth page content -->
        <div class="auth-page-content">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center mt-sm-5 mb-4 text-white-50">
                            <div>
                                <a href="index" class="d-inline-block auth-logo">
                                    <img src="{{ URL::asset('build/images/logo-light.png') }}" alt="" height="20">
                                </a>
                            </div>
                            <p class="mt-3 fs-15 fw-medium">Dr. Amitava Basu Smriti Swastha Raksha Kendra</p>
                        </div>
                    </div>
                </div>
                <!-- end row -->

                <div class="row justify-content-center">
                    <div class="col-md-8 col-lg-6 col-xl-5">
                        <div class="card mt-4 card-bg-fill">

                            <div class="card-body p-4">
                                <div class="text-center mt-2">
                                    <h5 class="text-primary">Forgot Password?</h5>
                                    <p class="text-muted">Reset password with ABSSRK</p>

                                    <lord-icon src="https://cdn.lordicon.com/rhvddzym.json" trigger="loop"
                                        colors="primary:#8c68cd" class="avatar-xl">
                                    </lord-icon>

                                </div>

                                @if (session('status'))
                                    <div class="alert alert-success text-center mb-4" role="alert">
                                        {{ session('status') }}
                                    </div>
                                @endif

                                <!-- STEP 1: MOBILE NUMBER -->

                                <div class="reset-step active" id="step-mobile">

                                    <div class="alert alert-borderless alert-warning text-center mb-3 mx-2" role="alert">
                                        Enter your registered mobile number and we'll send you an OTP via WhatsApp.
                                    </div>

                                    <div class="p-2">
                                        <div class="mb-3">
                                            <label for="mobile_no" class="form-label">Mobile Number</label>
                                            <input type="text" class="form-control" id="mobile_no" maxlength="10"
                                                inputmode="numeric" placeholder="Enter 10 digit mobile number">
                                        </div>

                                        <div class="text-end">
                                            <button class="btn btn-primary w-md waves-effect waves-light" type="button"
                                                id="sendOtpBtn">Send OTP</button>
                                        </div>
                                    </div>

                                </div>

                                <!-- STEP 2: OTP -->

                                <div class="reset-step" id="step-otp">

                                    <div class="alert alert-borderless alert-warning text-center mb-3 mx-2" role="alert">
                                        Enter the 6-digit OTP sent to your WhatsApp.
                                    </div>

                                    <div class="p-2">
                                        <div class="mb-3 text-center">
                                            <div class="d-flex justify-content-center gap-2" id="otpInputs">
                                                <input type="text" maxlength="1" class="form-control otp-box">
                                                <input type="text" maxlength="1" class="form-control otp-box">
                                                <input type="text" maxlength="1" class="form-control otp-box">
                                                <input type="text" maxlength="1" class="form-control otp-box">
                                                <input type="text" maxlength="1" class="form-control otp-box">
                                                <input type="text" maxlength="1" class="form-control otp-box">
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between">
                                            <button class="btn btn-outline-secondary waves-effect" type="button"
                                                id="resendOtpBtn">Change Number / Resend</button>
                                            <button class="btn btn-primary w-md waves-effect waves-light" type="button"
                                                id="verifyOtpBtn">Verify OTP</button>
                                        </div>
                                    </div>

                                </div>

                                <!-- STEP 3: NEW PASSWORD -->

                                <div class="reset-step" id="step-password">

                                    <div class="p-2">

                                        <form method="POST" action="{{ route('password.update') }}" id="resetPasswordForm">
                                            @csrf
                                            <input type="hidden" name="mobile_no" id="reset_mobile_no">
                                            <input type="hidden" name="verification_token" id="reset_verification_token">

                                            <div class="mb-3">
                                                <label for="password" class="form-label">New Password</label>
                                                <input type="password"
                                                    class="form-control @error('password') is-invalid @enderror"
                                                    name="password" id="password" placeholder="Enter new password">
                                                @error('password')
                                                    <span class="invalid-feedback" role="alert">
                                                        <strong>{{ $message }}</strong>
                                                    </span>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="password_confirmation" class="form-label">Confirm
                                                    Password</label>
                                                <input type="password" class="form-control"
                                                    name="password_confirmation" id="password_confirmation"
                                                    placeholder="Confirm new password">
                                            </div>

                                            @error('mobile_no')
                                                <div class="alert alert-danger text-center">{{ $message }}</div>
                                            @enderror

                                            <div class="text-end">
                                                <button class="btn btn-primary w-md waves-effect waves-light"
                                                    type="submit">Reset Password</button>
                                            </div>
                                        </form>
                                    </div>

                                </div>

                            </div>
                            <!-- end card body -->
                        </div>
                        <!-- end card -->

                        <div class="mt-4 text-center">
                            <p class="mb-0">Wait, I remember my password... <a href="{{ route('login') }}"
                                    class="fw-semibold text-primary text-decoration-underline"> Click here </a> </p>
                        </div>

                    </div>
                </div>
                <!-- end row -->
            </div>
            <!-- end container -->
        </div>
        <!-- end auth page content -->

        <!-- footer -->
        <footer class="footer">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="text-center">
                            <script>
                                document.write(new Date().getFullYear())
                            </script> ABSSRK. Crafted with <i class="mdi mdi-heart text-danger"></i> by
                            Prantosh Deb, BE (Mech), MBA, MCA</p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <!-- end Footer -->
    </div>
    <!-- end auth-page-wrapper -->
@endsection
@section('script')
    <script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="{{ URL::asset('build/js/pages/eva-icon.init.js') }}"></script>
    <script src="{{ URL::asset('build/js/pages/staff-password-reset.init.js') }}"></script>
@endsection
