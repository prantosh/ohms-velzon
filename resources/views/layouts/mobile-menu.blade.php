{{--
    Mobile admin menu (see config/mobile_menu.php). Opened by the menu
    button in the topbar, which only shows below 1025px -- the width at
    which the horizontal layout hides the full top menu.
--}}
@php
    $isAdmin = optional(Auth::user())->role === 'Admin';
    $currentRoute = request()->route()?->getName();

    $mobileSections = collect(config('mobile_menu', []))
        ->map(function ($section) use ($allowedPages, $isAdmin) {
            $section['items'] = collect($section['items'])->filter(function ($item) use ($allowedPages, $isAdmin) {
                if (!\Illuminate\Support\Facades\Route::has($item['route'])) {
                    return false;
                }
                if (!empty($item['admin_only'])) {
                    return $isAdmin;
                }
                return is_null($allowedPages) || in_array($item['page'] ?? null, $allowedPages, true);
            })->values();
            return $section;
        })
        ->filter(fn ($section) => $section['items']->isNotEmpty());

    // Pages listed in the mobile menu get a compact phone layout (below).
    $isMobileMenuPage = collect(config('mobile_menu', []))
        ->pluck('items')->flatten(1)->pluck('route')
        ->contains($currentRoute);
@endphp

{{--
    Compact phone layout for mobile-menu pages only: smaller text and
    tighter spacing so more fits on screen without scrolling. Applies
    below 768px only, so desktop / tablet views are unchanged. Scoped to
    .page-content so the topbar and menu keep their normal size.
--}}
@if($isMobileMenuPage)
<style>
    @media (max-width: 767.98px) {
        .page-content {
            --vz-body-font-size: .75rem;
            font-size: .75rem;
        }

        .page-content .page-title-box { padding-top: .5rem; padding-bottom: .5rem; margin-bottom: .75rem; }
        .page-content .page-title-box h4 { font-size: .8rem; }
        .page-content .page-title-box .breadcrumb { font-size: .7rem; }

        .page-content .card { margin-bottom: .75rem; }
        .page-content .card-header,
        .page-content .card-body,
        .page-content .card-footer { padding: .6rem .65rem; }
        .page-content .card-title,
        .page-content h5 { font-size: .8rem; }
        .page-content h4 { font-size: .9rem; }
        .page-content h6 { font-size: .75rem; }

        .page-content .form-label { font-size: .7rem; margin-bottom: .2rem; }
        .page-content .form-control,
        .page-content .form-select,
        .page-content .input-group-text {
            font-size: .75rem;
            padding: .35rem .5rem;
            min-height: auto;
        }
        .page-content .form-select { padding-right: 1.75rem; }
        .page-content .btn { font-size: .75rem; padding: .35rem .65rem; }
        .page-content .btn-sm { font-size: .7rem; padding: .25rem .5rem; }

        .page-content .table { font-size: .72rem; }
        .page-content .table > :not(caption) > * > * { padding: .4rem .35rem; }
        .page-content .table-mobile-cards td { padding: .25rem .2rem; }
        .page-content .badge { font-size: .65rem; }

        .page-content .nav-link { font-size: .75rem; padding: .4rem .6rem; }
        .page-content .pagination { font-size: .72rem; flex-wrap: wrap; }
        .page-content .row { --vz-gutter-x: .75rem; }
    }
</style>
@endif

{{-- Mobile menu is Admin-only (the topbar button is gated the same way). --}}
@if($isAdmin)

<style>
    .mobile-menu-btn { display: none; }

    @media (max-width: 1024.98px) {
        .mobile-menu-btn { display: inline-flex; }
    }

    #mobileAdminMenu { max-width: 85vw; }

    /* Opt-in for wide tables on mobile-menu pages: below 768px each row
       becomes a card, each cell a "Label: value" line. Cells need a
       data-label attribute; a cell without one spans the full width. */
    @media (max-width: 767.98px) {
        .table-mobile-cards thead { display: none; }
        .table-mobile-cards,
        .table-mobile-cards tbody,
        .table-mobile-cards tr,
        .table-mobile-cards td { display: block; width: 100%; }
        .table-mobile-cards tr {
            border: 1px solid var(--vz-border-color);
            border-radius: .5rem;
            margin-bottom: .75rem;
            padding: .25rem .5rem;
        }
        .table-mobile-cards td {
            border: 0 !important;
            padding: .35rem .25rem;
            text-align: left !important;
            white-space: normal !important;
            overflow-wrap: anywhere;
        }
        .table-mobile-cards td[data-label]::before {
            content: attr(data-label);
            display: block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--vz-secondary-color);
            text-transform: uppercase;
        }
        .table-mobile-cards td .btn { width: 100%; }
    }

    #mobileAdminMenu .mobile-menu-link {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: .8rem 1rem;
        border-radius: .5rem;
        color: var(--vz-body-color);
        font-weight: 500;
    }

    #mobileAdminMenu .mobile-menu-link i { font-size: 1.25rem; color: #3b82f6; }

    #mobileAdminMenu .mobile-menu-link:hover,
    #mobileAdminMenu .mobile-menu-link.active {
        background: rgba(59, 130, 246, .1);
        color: #1e40af;
    }
</style>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileAdminMenu" aria-labelledby="mobileAdminMenuLabel">
    <div class="offcanvas-header text-white" style="background: linear-gradient(90deg, #0f172a 0%, #1e40af 100%);">
        <div>
            <h5 class="offcanvas-title text-white mb-0" id="mobileAdminMenuLabel">Menu</h5>
            <small class="text-white-50">{{ optional(Auth::user())->name }}</small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body p-2">

        <a href="{{ route('root') }}" class="mobile-menu-link {{ $currentRoute === 'root' ? 'active' : '' }}">
            <i class="ri-home-5-line"></i> Home
        </a>

        @foreach($mobileSections as $section)
            <div class="text-uppercase text-muted fw-semibold small px-3 mt-3 mb-1">{{ $section['section'] }}</div>

            @foreach($section['items'] as $item)
                <a href="{{ route($item['route']) }}" class="mobile-menu-link {{ $currentRoute === $item['route'] ? 'active' : '' }}">
                    <i class="{{ $item['icon'] }}"></i> {{ $item['label'] }}
                </a>
            @endforeach
        @endforeach

        @if($mobileSections->isEmpty())
            <p class="text-muted small px-3 mt-3">No pages are available on mobile for your role.</p>
        @endif

        <div class="border-top mt-3 pt-2 px-3">
            <small class="text-muted">Other pages are available on a desktop / laptop.</small>
        </div>

    </div>
</div>

@endif
