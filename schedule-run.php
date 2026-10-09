<?php

/*
|--------------------------------------------------------------------------
| Cron entry point for the Laravel scheduler
|--------------------------------------------------------------------------
| Same as `php artisan schedule:run`, for hosts whose cPanel cron screen
| wants a .php file. Point a every-minute cron at this file, e.g.
|
|   * * * * *  php /home/<account>/dmsapp/schedule-run.php >/dev/null 2>&1
|
| CLI only -- refuses to run if it ever gets placed in a web-served folder.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$status = $kernel->call('schedule:run');

exit($status);
