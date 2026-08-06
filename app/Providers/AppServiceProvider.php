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
        //
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
