<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\Transport\PhpNativeMailTransport;
use App\Models\RolePageAccess;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Keep invoices.report_status current whenever a report changes.
        foreach ([
            \App\Models\PathologyReportFinding::class,
            \App\Models\PathologyReportFindingItem::class,
            \App\Models\UsgReportFinding::class,
            \App\Models\CardiologyReportFinding::class,
            \App\Models\NonPathologyReportFinding::class,
            \App\Models\TestReportConfirmation::class,
            \App\Models\XrayReportUpload::class,
        ] as $reportModel) {
            $reportModel::observe(\App\Observers\ReportStatusObserver::class);
        }

        // Changing whether an item needs a report / is outsourced / is a
        // package changes which billed lines count toward every invoice that
        // carries it -- mark those invoices stale (one UPDATE); each is
        // recomputed the next time it is read.
        \App\Models\InvoiceItemDetail::saved(function ($item) {
            if ($item->wasChanged(['is_package', 'is_outsourced', 'is_report_not_required'])) {
                app(\App\Services\InvoiceReportStatusRecorder::class)
                    ->invalidateForItem($item->item_code, $item->item_code_sub);
            }
        });

        Mail::extend('phpmail', function () {
            return new PhpNativeMailTransport();
        });

        Schema::defaultStringLength(191);

        date_default_timezone_set('Asia/Kolkata');

        Schema::defaultStringLength(191);

        date_default_timezone_set('Asia/Kolkata');

        try {
            DB::statement("SET time_zone = '+05:30'");
        } catch (\Exception $e) {
            //
        }

        View::composer('layouts.sidebar', function ($view) {

            $allowedPages = null;

            if (Auth::check() && Auth::user()->role !== 'Admin') {

                $allowedPages = RolePageAccess::where('role', Auth::user()->role)
                    ->value('page_access') ?? [];
            }

            $view->with('allowedPages', $allowedPages);
        });
    }
}
