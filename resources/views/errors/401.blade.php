@extends('layouts.master-without-nav')

@section('title')
    Not Authorised
@endsection

@section('body')

    <body>
    @endsection
    @section('content')
        <div class="auth-page-wrapper pt-5">

            <!-- auth page content -->
            <div class="auth-page-content">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="text-center pt-4">
                                <div class="">
                                    <img src="{{ URL::asset('build/images/error.svg') }}" alt="" class="error-basic-img move-animation">
                                </div>
                                <div class="mt-n4">
                                    <h1 class="display-1 fw-medium">401</h1>
                                    <h3 class="text-uppercase">Not Authorised</h3>
                                    <p class="text-muted mb-4">You are not authorised to access this page. Please log in to continue.</p>
                                    <a href="{{ route('login') }}" class="btn btn-success"><i class="mdi mdi-login me-1"></i>Go to Login</a>
                                </div>
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
                                <p class="mb-0 text-muted">&copy;
                                    <script>
                                        document.write(new Date().getFullYear())
                                    </script> ABSSRK. Crafted with <i class="mdi mdi-heart text-danger"></i>
                                    by Prantosh Deb, BE (Mech), MBA, MCA
                                </p>
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
        <!-- particles js -->
        <script src="{{ URL::asset('build/libs/particles.js/particles.js') }}"></script>
        <!-- particles app js -->
        <script src="{{ URL::asset('build/js/pages/particles.app.js') }}"></script>
    @endsection
