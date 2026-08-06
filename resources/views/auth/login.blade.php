@extends('layouts.master-without-nav')

@section('title')
ABSSRK Login
@endsection

@section('css')
<style>

:root{
    --primary:#0d6efd;
    --primary-dark:#0a4fc7;
    --accent:#42a5f5;
    --accent-2:#22d3ee;
    --light:#f8fbff;
}

*{
    box-sizing:border-box;
}

body{
    overflow-x:hidden;
}

/* ==========================================================
   PAGE WRAPPER + ANIMATED BACKGROUND
========================================================== */

.auth-page-wrapper{
    min-height:100vh;
    background:linear-gradient(135deg,var(--primary-dark) 0%,var(--primary) 45%,var(--accent-2) 100%);
    position:relative;
    overflow:hidden;
    display:flex;
    align-items:center;
}

.auth-page-wrapper::before{
    content:'';
    position:absolute;
    inset:0;
    background:
    radial-gradient(circle at 20% 20%, rgba(255,255,255,.14), transparent 32%),
    radial-gradient(circle at 80% 15%, rgba(255,255,255,.10), transparent 28%),
    radial-gradient(circle at 90% 90%, rgba(0,0,0,.08), transparent 35%);
    pointer-events:none;
}

.auth-page-wrapper::after{
    content:'';
    position:absolute;
    inset:0;
    background-image:radial-gradient(rgba(255,255,255,.16) 1px, transparent 1px);
    background-size:26px 26px;
    opacity:.35;
    pointer-events:none;
}

.blob{
    position:absolute;
    border-radius:50%;
    filter:blur(70px);
    opacity:.35;
    pointer-events:none;
    z-index:0;
}

.blob-1{
    width:420px;
    height:420px;
    right:-120px;
    top:-140px;
    background:#ffffff;
    animation:floatBlob 14s ease-in-out infinite;
}

.blob-2{
    width:360px;
    height:360px;
    left:-140px;
    bottom:-160px;
    background:var(--accent-2);
    animation:floatBlob 18s ease-in-out infinite reverse;
}

.blob-3{
    width:260px;
    height:260px;
    left:45%;
    top:60%;
    background:#ffffff;
    opacity:.12;
    animation:floatBlob 22s ease-in-out infinite;
}

@keyframes floatBlob{
    0%,100%{ transform:translate(0,0) scale(1); }
    50%{ transform:translate(30px,-30px) scale(1.08); }
}

@keyframes fadeInUp{
    from{ opacity:0; transform:translateY(28px); }
    to{ opacity:1; transform:translateY(0); }
}

@keyframes fadeInLeft{
    from{ opacity:0; transform:translateX(-30px); }
    to{ opacity:1; transform:translateX(0); }
}

/* ==========================================================
   BRANDING PANEL (LEFT)
========================================================== */

.branding-panel{
    color:#fff;
    padding:40px;
    position:relative;
    z-index:2;
    animation:fadeInLeft .7s ease both;
}

.branding-panel .logo-wrap{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:150px;
    height:150px;
    border-radius:28px;
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.18);
    backdrop-filter:blur(6px);
    margin-bottom:24px;
    box-shadow:0 20px 40px rgba(0,0,0,.15);
}

.branding-panel .logo-wrap img{
    max-height:104px;
    max-width:104px;
}

.branding-panel h1{
    font-size:3.4rem;
    font-weight:800;
    letter-spacing:3px;
    margin-bottom:.5rem;
}

.branding-panel h5{
    font-weight:500;
    line-height:1.6;
    opacity:.95;
}

.branding-panel p.lead-text{
    max-width:460px;
    opacity:.85;
}

.branding-panel .feature{
    display:flex;
    align-items:center;
    gap:12px;
    background:rgba(255,255,255,.10);
    border:1px solid rgba(255,255,255,.14);
    border-radius:14px;
    padding:12px 16px;
    margin-bottom:12px;
    backdrop-filter:blur(10px);
    transition:transform .25s ease,background .25s ease;
    max-width:420px;
}

.branding-panel .feature:hover{
    transform:translateX(6px);
    background:rgba(255,255,255,.16);
}

.branding-panel .feature .icon-badge{
    display:flex;
    align-items:center;
    justify-content:center;
    width:36px;
    height:36px;
    min-width:36px;
    border-radius:10px;
    background:rgba(255,255,255,.18);
    font-size:18px;
}

.branding-panel .feature span.label{
    font-size:14px;
    font-weight:500;
}

/* ==========================================================
   LOGIN CARD (RIGHT)
========================================================== */

.login-card{
    background:#fff;
    border-radius:26px;
    border:none;
    overflow:hidden;
    box-shadow:0 30px 70px rgba(9,30,66,.28);
    position:relative;
    z-index:2;
    animation:fadeInUp .7s ease .1s both;
}

.login-header{
    background:linear-gradient(120deg,var(--primary-dark),var(--primary) 55%,var(--accent-2));
    text-align:center;
    color:#fff;
    padding:38px 32px 46px;
    position:relative;
}

.login-header::after{
    content:'';
    position:absolute;
    left:0;
    right:0;
    bottom:-1px;
    height:28px;
    background:#fff;
    border-radius:50% 50% 0 0 / 100% 100% 0 0;
}

.login-header .badge-logo{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:76px;
    height:76px;
    border-radius:20px;
    background:rgba(255,255,255,.95);
    box-shadow:0 12px 26px rgba(0,0,0,.18);
    margin-bottom:14px;
}

.login-header .badge-logo img{
    max-height:48px;
    max-width:48px;
}

.login-header h4{
    margin-top:0;
    margin-bottom:4px;
    font-weight:700;
    letter-spacing:.3px;
}

.login-header p{
    opacity:.9;
    font-size:14px;
}

.login-card .card-body{
    padding-top:12px !important;
}

.form-label{
    font-weight:600;
    color:#334155;
    font-size:13.5px;
}

.input-group{
    border-radius:12px;
    transition:box-shadow .25s ease;
}

.input-group:focus-within{
    box-shadow:0 0 0 .18rem rgba(13,110,253,.16);
    border-radius:12px;
}

.input-group-text{
    background:#f5f7fa !important;
    border:1px solid #e2e8f0 !important;
    border-right:none !important;
    border-radius:12px 0 0 12px !important;
    color:#64748b !important;
    transition:color .25s ease;
}

.input-group:focus-within .input-group-text{
    color:var(--primary) !important;
    background:#eef5ff !important;
}

.form-control{
    height:52px;
    border:1px solid #e2e8f0 !important;
    border-left:none !important;
    border-radius:0 12px 12px 0 !important;
    background:#fff !important;
    color:#1e293b !important;
}

.form-control::placeholder{
    color:#a3aebc !important;
}

.form-control:focus{
    box-shadow:none;
    border-color:#e2e8f0 !important;
    background:#fff !important;
    color:#1e293b !important;
}

.password-input.form-control{
    border-right:none !important;
}

.password-addon{
    border:1px solid #e2e8f0 !important;
    border-left:none !important;
    border-radius:0 12px 12px 0 !important;
    background:#fff !important;
    color:#64748b !important;
}

.input-group:focus-within .password-addon{
    color:var(--primary);
}

.form-check-input:checked{
    background-color:var(--primary);
    border-color:var(--primary);
}

.form-check-label{
    font-size:14px;
    color:#475569;
}

a.forgot-link{
    font-size:13.5px;
    font-weight:600;
    color:var(--primary);
    text-decoration:none;
}

a.forgot-link:hover{
    text-decoration:underline;
}

.btn-login{
    height:52px;
    border:none;
    border-radius:12px;
    background:linear-gradient(120deg,var(--primary-dark),var(--primary),var(--accent-2));
    background-size:200% 100%;
    background-position:0% 0%;
    font-size:16px;
    font-weight:600;
    color:#fff;
    transition:background-position .4s ease,transform .2s ease,box-shadow .2s ease;
    position:relative;
    overflow:hidden;
}

.btn-login:hover{
    background-position:100% 0%;
    transform:translateY(-2px);
    box-shadow:0 14px 26px rgba(13,110,253,.30);
    color:#fff;
}

.btn-login:active{
    transform:translateY(0);
}

.security-note{
    background:#f8fbff;
    border:1px solid #eaf2ff;
    border-radius:12px;
    padding:10px 14px;
    font-size:13px;
    color:#475569;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:6px;
}

.footer-text{
    color:rgba(255,255,255,.9);
    text-align:center;
    margin-top:22px;
    position:relative;
    z-index:2;
}

.footer-text strong{
    letter-spacing:1px;
}

.footer-text small{
    opacity:.8;
}

@media(max-width:991px){

    .branding-panel{
        text-align:center;
        padding-bottom:10px;
    }

    .branding-panel .feature{
        margin-left:auto;
        margin-right:auto;
    }

    .branding-panel h1{
        font-size:2.4rem;
    }

    .auth-page-wrapper{
        padding-top:24px;
        padding-bottom:24px;
    }
}

</style>
@endsection

@section('content')

<div class="auth-page-wrapper">

    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <div class="container-fluid">

        <div class="row min-vh-100 align-items-center">

            <!-- LEFT PANEL -->

            <div class="col-lg-6 d-none d-lg-flex">

                <div class="branding-panel w-100">

                    <div class="logo-wrap">
                        <img
                            src="{{ asset('build/images/logo-light.png') }}"
                            alt="ABSSRK">
                    </div>

                    <h1>ABSSRK</h1>

                    <h5 class="mb-3">
                        Dr. Amitava Basu Smriti Swastha Raksha Kendra
                    </h5>

                    <p class="lead-text mb-5 fs-16">
                        Integrated Healthcare Management System for
                        Doctor Appointments, Diagnostics, Billing,
                        Patient Records and Reports.
                    </p>

                    <div class="feature">
                        <span class="icon-badge"><i class="ri-user-heart-line"></i></span>
                        <span class="label">Patient Management</span>
                    </div>

                    <div class="feature">
                        <span class="icon-badge"><i class="ri-stethoscope-line"></i></span>
                        <span class="label">Doctor Consultation</span>
                    </div>

                    <div class="feature">
                        <span class="icon-badge"><i class="ri-microscope-line"></i></span>
                        <span class="label">Diagnostic Services</span>
                    </div>

                    <div class="feature">
                        <span class="icon-badge"><i class="ri-file-list-3-line"></i></span>
                        <span class="label">Billing & Reports</span>
                    </div>

                    <div class="feature">
                        <span class="icon-badge"><i class="ri-whatsapp-line"></i></span>
                        <span class="label">WhatsApp Notifications</span>
                    </div>

                </div>

            </div>

            <!-- RIGHT PANEL -->

            <div class="col-lg-6 col-md-12">

                <div class="row justify-content-center">

                    <div class="col-xl-8 col-lg-10 col-md-8">

                        <div class="card login-card">

                            <div class="login-header">

                                <div class="badge-logo">
                                    <img
                                        src="{{ asset('build/images/logo-light.png') }}"
                                        alt="ABSSRK">
                                </div>

                                <h4>Welcome Back</h4>

                                <p class="mb-0">
                                    Sign in to continue
                                </p>

                            </div>

                            <div class="card-body p-4 p-lg-5">

                                <form action="{{ route('login') }}" method="POST">

                                    @csrf

                                    <div class="mb-3">

                                        <label class="form-label">
                                            Username
                                            <span class="text-danger">*</span>
                                        </label>

                                        <div class="input-group">

                                            <span class="input-group-text">
                                                <i class="ri-user-line"></i>
                                            </span>

                                            <input
                                                type="text"
                                                name="email"
                                                id="username"
                                                value="{{ old('email') }}"
                                                class="form-control @error('email') is-invalid @enderror"
                                                placeholder="Enter username">

                                        </div>

                                        @error('email')
                                        <span class="invalid-feedback d-block">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                        @enderror

                                    </div>

                                    <div class="mb-3">

                                        <div class="d-flex justify-content-between">
                                            <label class="form-label">
                                                Password
                                                <span class="text-danger">*</span>
                                            </label>

                                            <a href="{{ route('password.update') }}"
                                               class="forgot-link">
                                                Forgot Password?
                                            </a>
                                        </div>

                                        <div class="input-group auth-pass-inputgroup">

                                            <span class="input-group-text">
                                                <i class="ri-lock-line"></i>
                                            </span>

                                            <input
                                                type="password"
                                                name="password"
                                                id="password-input"
                                                class="form-control password-input @error('password') is-invalid @enderror"
                                                placeholder="Enter password">

                                            <button
                                                class="btn btn-light password-addon"
                                                type="button"
                                                id="password-addon">
                                                <i class="ri-eye-fill"></i>
                                            </button>

                                        </div>

                                        @error('password')
                                        <span class="invalid-feedback d-block">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                        @enderror

                                    </div>

                                    <div class="form-check mb-4">

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="auth-remember-check">

                                        <label
                                            class="form-check-label"
                                            for="auth-remember-check">
                                            Remember Me
                                        </label>

                                    </div>

                                    <button
                                        type="submit"
                                        class="btn btn-login w-100">

                                        <i class="ri-login-circle-line me-1"></i>
                                        Sign In

                                    </button>

                                    <div class="security-note mt-4">

                                        <i class="ri-shield-check-line text-success"></i>

                                        Secure access for authorized staff only

                                    </div>

                                </form>

                            </div>

                        </div>

                        <div class="footer-text">

                            <strong>ESTD : 2002</strong>

                            <br>

                            Dr. Amitava Basu Smriti Swastha Raksha Kendra

                            <br>

                            <small>
                                Doctor Appointment • Diagnostics • Billing • Reports
                            </small>

                        </div>
                        <div class="footer-text">

                            

                            <br>

                            

                            <br>

                            <small>
                                Designed & Developed by Prantosh Deb, BE (Mech), MBA, MCA
                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/js/pages/password-addon.init.js') }}"></script>

@endsection
