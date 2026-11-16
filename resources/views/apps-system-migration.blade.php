@extends('layouts.master')

@section('title')
    Database Migrations
@endsection

@section('css')
<style>
#migrationOutput {
    background: #1e1e1e;
    color: #d4d4d4;
    font-family: "Consolas", "Courier New", monospace;
    font-size: 12px;
    white-space: pre-wrap;
    word-break: break-word;
    padding: 12px;
    border-radius: 4px;
    max-height: 50vh;
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
        Database Migrations
    @endslot
@endcomponent

<div class="alert alert-info">
    <i class="ri-information-line"></i>
    Runs <code>php artisan migrate</code> from the browser. Old migrations whose tables/columns already exist
    (database imported from a dump) must first be <strong>marked as already run</strong>, or migrate stops at the
    first one with "table already exists". Take a database backup before either action.
</div>

@if ($output)
    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Result</h5></div>
        <div class="card-body"><div id="migrationOutput">{{ $output }}</div></div>
    </div>
@endif

@if (isset($errors) && $errors->any())
    <div class="alert alert-danger">{{ implode(' ', $errors->all()) }}</div>
@endif

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="card-title mb-0">Pending migrations ({{ count($pending) }})</h5>
        <form method="POST" action="{{ route('system-migration.run') }}"
              onsubmit="return confirm('Run all pending migrations now? Make sure a database backup exists.');">
            @csrf
            <button class="btn btn-danger" type="submit" @disabled(empty($pending))>
                <i class="ri-play-line"></i> Run pending migrations
            </button>
        </form>
    </div>
    <div class="card-body">

        @if (empty($pending))
            <div class="text-muted text-center py-4">Nothing to migrate -- the database is up to date.</div>
        @else
            <p class="text-muted mb-2">
                <span class="badge bg-success">Already in database</span> every change it makes already exists -- safe to mark as run (ticked by default).
                <span class="badge bg-danger">Not applied</span> something it adds is missing -- normally left to <em>Run</em>.
                <span class="badge bg-warning text-dark">Check manually</span> data fix / index change that can't be detected automatically.
            </p>

            <form method="POST" action="{{ route('system-migration.mark-applied') }}"
                  onsubmit="return confirm('Record the ticked migrations as already run WITHOUT executing them?');">
                @csrf
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;"><input type="checkbox" class="form-check-input" id="toggleAll"></th>
                                <th>Migration</th>
                                <th>Status</th>
                                <th>Checks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $migration)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input migration-check" name="migrations[]"
                                               value="{{ $migration['name'] }}" @checked($migration['status'] === 'applied')>
                                    </td>
                                    <td><code>{{ $migration['name'] }}</code></td>
                                    <td>
                                        @if ($migration['status'] === 'applied')
                                            <span class="badge bg-success">Already in database</span>
                                        @elseif ($migration['status'] === 'not_applied')
                                            <span class="badge bg-danger">Not applied</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Check manually</span>
                                        @endif
                                    </td>
                                    <td class="small">
                                        @foreach ($migration['checks'] as [$label, $ok])
                                            <div class="{{ $ok ? 'text-success' : 'text-danger' }}">{{ $ok ? '✓' : '✗' }} {{ $label }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-warning" type="submit">
                    <i class="ri-check-double-line"></i> Mark ticked as already run
                </button>
            </form>
        @endif

    </div>
</div>

@endsection

@section('script')
<script>
document.getElementById('toggleAll')?.addEventListener('change', function () {
    document.querySelectorAll('.migration-check').forEach(box => box.checked = this.checked);
});
</script>
@endsection
