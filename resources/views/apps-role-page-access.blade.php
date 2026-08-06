@extends('layouts.master')

@section('title')
    Role Page Access
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Administration
    @endslot

    @slot('title')
        Role Page Access
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="card-body">

                <div class="row mb-3">

                    <div class="col-md-4">

                        <label class="form-label fw-semibold">Select Role</label>

                        <select id="role-select" class="form-select">

                            @foreach($roles as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach

                        </select>

                    </div>

                    <div class="col-md-5">

                        <label class="form-label fw-semibold">Add New Role</label>

                        <div class="input-group">

                            <input type="text"
                                   id="new-role-field"
                                   class="form-control"
                                   placeholder="e.g. Accountant">

                            <button type="button" id="add-role-btn" class="btn btn-primary">
                                <i class="ri-add-line align-bottom me-1"></i>
                                Add Role
                            </button>

                        </div>

                    </div>

                </div>

                <hr>

                <p class="text-muted">
                    Check the pages this role should be able to access. This is enforced -- unchecked pages
                    return "access denied" for users with this role (Admin always has access to everything).
                </p>

                <div class="row" id="page-access-list">

                    @foreach($pages as $key => $label)

                    <div class="col-md-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input page-access-checkbox"
                                   type="checkbox"
                                   value="{{ $key }}"
                                   id="role-page-{{ $key }}">
                            <label class="form-check-label" for="role-page-{{ $key }}">
                                {{ $label }}
                            </label>
                        </div>
                    </div>

                    @endforeach

                </div>

                <div class="mt-4">

                    <button type="button" id="save-btn" class="btn btn-success">
                        Save Access for this Role
                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/role-page-access.init.js') }}"></script>

@endsection
