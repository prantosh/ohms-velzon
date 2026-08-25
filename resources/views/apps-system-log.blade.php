@extends('layouts.master')

@section('title')
    System Log Viewer
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<style>

#logOutput {
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: "Consolas", "Courier New", monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-word;
    padding: 12px;
    border-radius: 4px;
    max-height: 70vh;
    overflow-y: auto;
}

</style>

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Admin
    @endslot

    @slot('title')
        System Log Viewer
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-info">
            <i class="ri-information-line"></i>
            Reads <code>storage/logs/laravel.log</code> directly -- the only way to see a real server error's stack
            trace on a host with no SSH access.
        </div>

        <div class="card">

            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">

                <div class="d-flex align-items-center gap-2 flex-wrap">

                    <input type="text"
                           id="searchInput"
                           class="form-control form-control-sm"
                           style="width:260px"
                           placeholder="Filter by text (e.g. a class or route name)">

                    <select id="charsSelect" class="form-select form-select-sm" style="width:160px">
                        <option value="10000">Last ~10,000 chars</option>
                        <option value="20000" selected>Last ~20,000 chars</option>
                        <option value="60000">Last ~60,000 chars</option>
                        <option value="150000">Last ~150,000 chars</option>
                    </select>

                    <span id="logMeta" class="text-muted small"></span>

                </div>

                <div class="d-flex align-items-center gap-2">

                    <button type="button" class="btn btn-sm btn-primary" id="refreshBtn">
                        <i class="ri-refresh-line"></i>
                        Refresh
                    </button>

                    <button type="button" class="btn btn-sm btn-outline-danger" id="clearBtn">
                        <i class="ri-delete-bin-line"></i>
                        Clear Log
                    </button>

                </div>

            </div>

            <div class="card-body">

                <pre id="logOutput">Loading...</pre>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/system-log.init.js') }}"></script>

@endsection
