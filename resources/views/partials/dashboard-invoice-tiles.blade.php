{{--
    Shared tile grid for the "Today's Invoices By <Name>" dashboard card --
    used both for the single non-Admin "By Me" card and for each per-user
    card Admin sees instead (see index.blade.php and
    HomeController::buildInvoiceSummaryTiles()). Expects $tiles: array of
    ['label','count','amount','color','icon','amount_caption'].
--}}
<div class="row g-3 row-cols-xl-5 row-cols-lg-3 row-cols-md-2 row-cols-sm-1">

    @foreach($tiles as $tile)

    <div class="col">

        <div class="card stat-card h-100 mb-0">

            <div class="card-body">

                <div class="d-flex align-items-center justify-content-between mb-3">

                    <span class="stat-icon-badge bg-{{ $tile['color'] }}-subtle text-{{ $tile['color'] }}">
                        <i class="{{ $tile['icon'] }}"></i>
                    </span>

                    <span class="stat-label text-muted">
                        {{ $tile['label'] }}
                    </span>

                </div>

                <div class="d-flex align-items-end justify-content-between">

                    <div>
                        <div class="stat-value">
                            {{ $tile['count'] }}
                        </div>
                        <small class="text-muted">Transactions</small>
                    </div>

                    <div class="text-end">
                        <div class="fw-bold text-{{ $tile['color'] }}">
                            ₹ {{ number_format($tile['amount'], 0) }}
                        </div>
                        <small class="text-muted">{{ $tile['amount_caption'] }}</small>
                    </div>

                </div>

            </div>

        </div>

    </div>

    @endforeach

</div>
