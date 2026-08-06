@extends('layouts.master')

@section('title')
    Cloud Database Backup
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
        Cloud Database Backup
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="alert alert-warning">
            <i class="ri-shield-keyhole-line"></i>
            Enter the credentials of the remote (cloud) MySQL database you want to back up. Credentials are used
            only for this one backup run and are <b>never stored</b>. The backup file is saved on this server
            under <code>storage/app/backups</code> and can be downloaded to your machine below. This screen does
            <b>not</b> touch or overwrite the local <code>{{ config('database.connections.mysql.database') }}</code> database.
        </div>

        <div class="card">

            <div class="card-header">
                <h5 class="card-title mb-0">Remote Database Connection</h5>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Host <span class="text-danger">*</span></label>
                        <input type="text" id="host-field" class="form-control" placeholder="e.g. db.example.com or an IP address" required>
                    </div>

                    <div class="col-md-2 mb-3">
                        <label class="form-label">Port</label>
                        <input type="number" id="port-field" class="form-control" placeholder="3306" value="3306">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Database Name <span class="text-danger">*</span></label>
                        <input type="text" id="database-field" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" id="username-field" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" id="password-field" class="form-control" autocomplete="new-password">
                    </div>

                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btnTestConnection">
                        <i class="ri-plug-line"></i>
                        Test Connection
                    </button>

                    <button type="button" class="btn btn-primary" id="btnRunBackup">
                        <i class="ri-download-cloud-2-line"></i>
                        Backup Now
                    </button>
                </div>

                <div id="backupProgress" class="mt-3" style="display:none;">
                    <div class="d-flex align-items-center gap-2 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span>Backing up... this may take several minutes for large databases. Please don't close this page.</span>
                    </div>
                </div>

                <div id="resultAlert" class="alert mt-3" style="display:none;"></div>

            </div>

        </div>

        <div class="card">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Backups Saved on This Machine</h5>
                <button type="button" class="btn btn-sm btn-soft-primary" id="btnRefreshList">
                    <i class="ri-refresh-line"></i>
                    Refresh
                </button>
            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>File Name</th>
                                <th width="130">Size</th>
                                <th width="160">Created At</th>
                                <th width="160">Action</th>
                            </tr>
                        </thead>

                        <tbody id="backupListBody"></tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/cloud-backup.init.js') }}"></script>

@endsection
