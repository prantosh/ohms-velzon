<!doctype html >
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="horizontal" container="fluid" data-topbar="dark" data-sidebar="dark" data-sidebar-size="lg" data-sidebar-image="none" data-bs-theme="light" data-body-image="img-1" data-preloader="disable">

<head>
    <meta charset="utf-8" />
    <title>@yield('title') | ABSSRK - Admin & Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Premium Multipurpose Admin & Dashboard Template" name="description" />
    <meta content="Themesbrand" name="author" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ URL::asset('build/images/favicon.ico')}}">
    @include('layouts.head-css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
@section('çss')
<style>
    #globalLoader {
        position: fixed;
        inset: 0;
        z-index: 999999;
    }

    .loader-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,.75);
        backdrop-filter: blur(2px);
    }

    .loader-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%,-50%);
        text-align: center;
        background: #fff;
        padding: 25px 35px;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0,0,0,.15);
        min-width: 260px;
    }
</style>
@endsection
@section('body')
    @include('layouts.body')
@show
@if(request()->boolean('embed'))
<style>
    /* Embedded mode (e.g. inside the Diagnostic Test Additional Info
       dashboard's tab iframes) -- chrome (topbar/sidebar/footer) is
       skipped below, so the content area shouldn't reserve space for it. */
    #layout-wrapper .main-content {
        margin-left: 0 !important;
    }
    #layout-wrapper .page-content {
        padding-top: 12px !important;
    }
</style>
@endif
    <!-- Begin page -->
    <div id="layout-wrapper">
        @unless(request()->boolean('embed'))
        @include('layouts.topbar')
        @include('layouts.sidebar')
        @endunless
        <!-- ============================================================== -->
        <!-- Start right Content here -->
        <!-- ============================================================== -->
        <div class="main-content">
            <div class="page-content">
                <div class="container-fluid">
                    @yield('content')
                </div>
                <!-- container-fluid -->
            </div>
            <!-- End Page-content -->
            @unless(request()->boolean('embed'))
            @include('layouts.footer')
            @endunless
        </div>
        <!-- end main content-->
    </div>
    <!-- END layout-wrapper -->

    @unless(request()->boolean('embed'))
    @include('layouts.customizer')
    @endunless

    <!-- JAVASCRIPT -->
    @include('layouts.vendor-scripts')
    @yield('script')

<!-- ===========================================================
GLOBAL LOADER
=========================================================== -->

<div id="globalLoader" style="display:none;">
    <div class="loader-backdrop"></div>

    <div class="loader-content">

        <div class="spinner-border text-primary"
             style="width:3rem;height:3rem;"
             role="status">
        </div>

        <h5 class="mt-3 mb-1">
            Please wait...
        </h5>

        <p id="globalLoaderText"
           class="text-muted mb-0">
            Loading...
        </p>

    </div>
</div>






</body>

</html>
