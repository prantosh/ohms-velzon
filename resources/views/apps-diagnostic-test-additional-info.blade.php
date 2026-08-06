@extends('layouts.master')

@section('title')
    Diagnostic Test Additional Info
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Diagnostic Test
    @endslot

    @slot('title')
        Additional Info
    @endslot

@endcomponent

@if($tabs->isEmpty())

<div class="alert alert-warning">
    You do not have access to any of the Diagnostic Test Additional Info
    sub-pages. Contact your administrator.
</div>

@else

<div class="row">

    <div class="col-lg-12">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">

            <ul class="nav nav-tabs nav-tabs-custom mb-0 flex-grow-1" id="additionalInfoTabs" role="tablist">

                @foreach($tabs as $index => $tab)

                <li class="nav-item">
                    <a class="nav-link {{ $index === 0 ? 'active' : '' }}"
                       data-bs-toggle="tab"
                       href="#{{ $tab->slug }}"
                       role="tab"
                       data-src="{{ $tab->url }}">
                        {{ $tab->label }}
                    </a>
                </li>

                @endforeach

            </ul>

            @if($isAdmin)
            <button type="button"
                    class="btn btn-soft-primary btn-sm flex-shrink-0"
                    data-bs-toggle="modal"
                    data-bs-target="#manageTabsModal"
                    id="manageTabsBtn">
                <i class="ri-settings-3-line align-middle me-1"></i>
                Manage Tabs
            </button>
            @endif

        </div>

        <div class="tab-content">

            @foreach($tabs as $index => $tab)

            <div class="tab-pane {{ $index === 0 ? 'active' : '' }}"
                 id="{{ $tab->slug }}"
                 role="tabpanel">

                <div class="text-center py-5 tab-loading">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>

                <iframe class="w-100 border-0 tab-iframe"
                        style="min-height: 78vh; display:none;"></iframe>

            </div>

            @endforeach

        </div>

    </div>

</div>

@endif

@if($isAdmin)

<!-- MANAGE TABS MODAL -->

<div class="modal fade"
     id="manageTabsModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title">
                    Manage Tabs
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <div class="table-responsive mb-3">

                    <table class="table table-bordered align-middle mb-0" id="manageTabsTable">

                        <thead class="table-light">
                            <tr>
                                <th>Caption</th>
                                <th>Route Index Name</th>
                                <th width="140">Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($allTabsForManagement as $tab)
                            <tr data-id="{{ $tab->id }}">
                                <td class="tab-row-label">{{ $tab->label }}</td>
                                <td class="tab-row-route"><code>{{ $tab->route_name }}</code></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-soft-secondary move-up-btn" title="Move Up">
                                        <i class="ri-arrow-up-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-soft-secondary move-down-btn" title="Move Down">
                                        <i class="ri-arrow-down-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-soft-success edit-tab-btn" title="Edit">
                                        <i class="ri-pencil-line"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-soft-danger delete-tab-btn" title="Delete">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @endforeach

                        </tbody>

                    </table>

                </div>

                <button type="button" class="btn btn-primary btn-sm" id="showAddTabFormBtn">
                    <i class="ri-add-line align-middle me-1"></i>
                    Add New Tab
                </button>

                <div class="card mt-3" id="tabFormWrap" style="display:none;">

                    <div class="card-body">

                        <h6 class="card-title mb-3" id="tabFormTitle">Add New Tab</h6>

                        <input type="hidden" id="tabFormId">

                        <div class="mb-3">
                            <label class="form-label">Caption <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tabFormLabel" placeholder="e.g. Sample Type Master">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Route Index Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="tabFormRoute" placeholder="e.g. sample-type-master.index">
                            <div class="form-text">
                                This must be the exact name of an existing page's index route (a developer can
                                confirm it), e.g. <code>sample-type-master.index</code>. It has to be a plain
                                listing page -- not one that needs an ID or other parameter in the URL.
                            </div>
                        </div>

                        <button type="button" class="btn btn-success btn-sm" id="saveTabBtn">Save</button>
                        <button type="button" class="btn btn-light btn-sm" id="cancelTabFormBtn">Cancel</button>

                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>

        </div>

    </div>

</div>

@endif

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script>

/*
|--------------------------------------------------------------------------
| LAZY-LOAD TAB IFRAMES
|--------------------------------------------------------------------------
*/

function loadAdditionalInfoTab(anchor) {

    let pane = document.querySelector(anchor.getAttribute('href'));

    let iframe = pane.querySelector('.tab-iframe');
    let loading = pane.querySelector('.tab-loading');

    if (iframe.dataset.loaded === '1') {
        return;
    }

    iframe.addEventListener('load', function () {
        loading.style.display = 'none';
        iframe.style.display = 'block';
    }, { once: true });

    iframe.src = anchor.getAttribute('data-src');
    iframe.dataset.loaded = '1';
}

document.addEventListener('DOMContentLoaded', function () {

    let firstTab = document.querySelector('#additionalInfoTabs .nav-link.active');

    if (firstTab) {
        loadAdditionalInfoTab(firstTab);
    }

    document.querySelectorAll('#additionalInfoTabs [data-bs-toggle="tab"]').forEach(function (anchor) {

        anchor.addEventListener('shown.bs.tab', function (e) {
            loadAdditionalInfoTab(e.target);
        });
    });
});

@if($isAdmin)
/*
|--------------------------------------------------------------------------
| MANAGE TABS (ADMIN ONLY)
|--------------------------------------------------------------------------
*/

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function showTabForm(mode, row) {

    document.querySelector('#tabFormWrap').style.display = 'block';
    document.querySelector('#tabFormTitle').textContent = mode === 'edit' ? 'Edit Tab' : 'Add New Tab';

    document.querySelector('#tabFormId').value = row ? row.dataset.id : '';
    document.querySelector('#tabFormLabel').value = row ? row.querySelector('.tab-row-label').textContent.trim() : '';
    document.querySelector('#tabFormRoute').value = row ? row.querySelector('.tab-row-route').textContent.trim() : '';
}

function hideTabForm() {
    document.querySelector('#tabFormWrap').style.display = 'none';
}

document.getElementById('showAddTabFormBtn').addEventListener('click', function () {
    showTabForm('add', null);
});

document.getElementById('cancelTabFormBtn').addEventListener('click', hideTabForm);

document.getElementById('manageTabsTable').addEventListener('click', async function (e) {

    let row = e.target.closest('tr[data-id]');
    if (!row) return;

    let id = row.dataset.id;

    if (e.target.closest('.edit-tab-btn')) {

        showTabForm('edit', row);
        return;
    }

    if (e.target.closest('.delete-tab-btn')) {

        let confirmResult = await Swal.fire({
            icon: 'warning',
            title: 'Remove this tab?',
            text: 'The underlying page is not affected -- only removed from this dashboard.',
            showCancelButton: true,
            confirmButtonText: 'Yes, Remove'
        });

        if (!confirmResult.isConfirmed) return;

        const response = await fetch('/diagnostic-test-additional-info/delete/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        if (!response.ok || !result.status) {
            Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to remove tab.' });
            return;
        }

        window.location.reload();
        return;
    }

    if (e.target.closest('.move-up-btn') || e.target.closest('.move-down-btn')) {

        let direction = e.target.closest('.move-up-btn') ? 'up' : 'down';

        const response = await fetch(`/diagnostic-test-additional-info/move/${id}/${direction}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        });

        const result = await response.json();

        if (!response.ok || !result.status) {
            Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to reorder.' });
            return;
        }

        window.location.reload();
        return;
    }
});

document.getElementById('saveTabBtn').addEventListener('click', async function () {

    let id = document.querySelector('#tabFormId').value;
    let label = document.querySelector('#tabFormLabel').value.trim();
    let routeName = document.querySelector('#tabFormRoute').value.trim();

    if (!label || !routeName) {
        Swal.fire({ icon: 'warning', title: 'Both fields are required.' });
        return;
    }

    let url = id
        ? '/diagnostic-test-additional-info/update/' + id
        : '/diagnostic-test-additional-info/store';

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ label: label, route_name: routeName })
    });

    const result = await response.json();

    if (!response.ok || !result.status) {
        Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to save tab.' });
        return;
    }

    window.location.reload();
});
@endif

</script>

@endsection
