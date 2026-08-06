@extends('layouts.master')

@section('title')
    Maintenance Mode
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Admin
    @endslot

    @slot('title')
        Maintenance Mode
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-8">

        <div class="alert alert-warning">
            <i class="ri-alert-line"></i>
            When maintenance mode is <b>ON</b>, every user except Admin is blocked from using the application
            (including background actions, not just page views) and sees the "Site is Under Maintenance" page
            instead. Admin can still use the app fully, including turning this back off.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Maintenance Mode Status</h5>
            </div>

            <div class="card-body">

                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="form-check form-switch fs-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="maintenanceToggle"
                               {{ $setting->is_enabled ? 'checked' : '' }}>
                    </div>
                    <div>
                        <h5 class="mb-0" id="maintenanceStatusLabel">
                            @if($setting->is_enabled)
                                <span class="text-danger">Currently ON</span>
                            @else
                                <span class="text-success">Currently OFF</span>
                            @endif
                        </h5>
                        <small class="text-muted" id="maintenanceStatusMeta">
                            @if($setting->is_enabled && $setting->enabled_at)
                                Enabled {{ \Carbon\Carbon::parse($setting->enabled_at)->format('d M, Y h:i A') }}
                            @else
                                The application is accessible to everyone.
                            @endif
                        </small>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="maintenanceMessage-field" class="form-label">Message shown to users (optional)</label>
                    <textarea class="form-control" id="maintenanceMessage-field" rows="2"
                              placeholder="Please check back in sometime">{{ $setting->message }}</textarea>
                </div>

                <button type="button" class="btn btn-primary" id="btnSaveMaintenance">
                    <i class="ri-save-line"></i>
                    Save
                </button>

                <div id="resultAlert" class="alert mt-3" style="display:none;"></div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/maintenance-mode.init.js') }}"></script>

@endsection
