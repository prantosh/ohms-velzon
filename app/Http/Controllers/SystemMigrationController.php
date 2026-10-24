<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Runs database migrations from the browser -- production has no SSH
 * access, so `php artisan migrate` can't be run there directly.
 *
 * The production database was built from an imported SQL dump, not from
 * migrations, so many old migrations show as pending even though their
 * tables/columns already exist, and `migrate` fails on the first of them
 * ("table already exists"). This page checks each pending migration
 * against the live schema so those can be marked as already run (recorded
 * in the migrations table WITHOUT executing them) before running the rest
 * for real. Admin-only regardless of role_page_access, same as
 * SystemLogController.
 */
class SystemMigrationController extends Controller
{
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can run database migrations.');
        }
    }

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-system-migration', [
            'pending' => $this->pendingWithChecks(),
            'output' => session('migration_output'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MARK APPLIED -- records the ticked pending migrations as run, in one new
    | batch, without executing them.
    |--------------------------------------------------------------------------
    */
    public function markApplied(Request $request)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'migrations' => ['required', 'array', 'min:1'],
            'migrations.*' => ['required', 'string'],
        ]);

        $pendingNames = array_keys($this->pendingFiles());
        $toMark = array_values(array_intersect($validated['migrations'], $pendingNames));

        if (empty($toMark)) {
            return redirect()->route('system-migration.index')
                ->with('migration_output', 'Nothing marked -- none of the selected migrations is still pending.');
        }

        $batch = (int) DB::table('migrations')->max('batch') + 1;

        DB::table('migrations')->insert(array_map(
            fn ($name) => ['migration' => $name, 'batch' => $batch],
            $toMark
        ));

        Log::info('Migrations marked as applied from web', [
            'user_id' => Auth::id(),
            'batch' => $batch,
            'migrations' => $toMark,
        ]);

        return redirect()->route('system-migration.index')
            ->with('migration_output', 'Marked ' . count($toMark) . " migration(s) as already run (batch {$batch}), without executing them:\n" . implode("\n", $toMark));
    }

    /*
    |--------------------------------------------------------------------------
    | RUN -- `php artisan migrate --force`, output shown back on the page.
    |--------------------------------------------------------------------------
    */
    public function run()
    {
        $this->ensureAdmin();

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
        } catch (\Throwable $e) {
            $output = Artisan::output() . "\nFAILED: " . $e->getMessage();
        }

        Log::info('Migrations run from web', ['user_id' => Auth::id(), 'output' => $output]);

        return redirect()->route('system-migration.index')
            ->with('migration_output', trim($output) ?: 'Done.');
    }

    /**
     * migration name => file path, for every migration file not yet
     * recorded in the migrations table, oldest first.
     */
    private function pendingFiles(): array
    {
        $ran = DB::table('migrations')->pluck('migration')->all();

        $files = [];
        foreach (glob(database_path('migrations/*.php')) as $path) {
            $name = basename($path, '.php');
            if (!in_array($name, $ran, true)) {
                $files[$name] = $path;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * Each pending migration with a best-effort reading of its up() against
     * the live schema: tables it creates, columns it adds or drops, tables
     * it renames. status is 'applied' when every detected change is already
     * in place, 'not_applied' when any is missing, 'unknown' when nothing
     * could be detected (data fixes, index/foreign-key changes, raw SQL) --
     * those need a human look at the file.
     */
    private function pendingWithChecks(): array
    {
        $result = [];

        foreach ($this->pendingFiles() as $name => $path) {

            $up = preg_split('/function\s+down\s*\(/', file_get_contents($path))[0];
            $checks = [];

            preg_match_all("/Schema::create\(\s*'([^']+)'/", $up, $m);
            foreach ($m[1] as $table) {
                $checks[] = ["create table {$table}", Schema::hasTable($table)];
            }

            preg_match_all("/Schema::table\(\s*'([^']+)'.*?\}\);/s", $up, $blocks, PREG_SET_ORDER);
            foreach ($blocks as $block) {
                $table = $block[1];

                preg_match_all("/->(?:string|integer|unsignedBigInteger|unsignedInteger|boolean|decimal|date|dateTime|timestamp|text|json|tinyInteger|smallInteger|bigInteger|enum|foreignId|char|longText|mediumText|time|float|double)\(\s*'([^']+)'/", $block[0], $cols);
                foreach ($cols[1] as $col) {
                    $checks[] = ["add {$table}.{$col}", Schema::hasTable($table) && Schema::hasColumn($table, $col)];
                }

                preg_match_all("/dropColumn\(\s*\[?\s*'([^']+)'/", $block[0], $drops);
                foreach ($drops[1] as $col) {
                    $checks[] = ["drop {$table}.{$col}", !Schema::hasTable($table) || !Schema::hasColumn($table, $col)];
                }
            }

            preg_match_all("/Schema::rename\(\s*'([^']+)'\s*,\s*'([^']+)'/", $up, $renames);
            foreach ($renames[1] as $i => $from) {
                $checks[] = ["rename {$from} to {$renames[2][$i]}", Schema::hasTable($renames[2][$i])];
            }

            $status = empty($checks)
                ? 'unknown'
                : (collect($checks)->every(fn ($c) => $c[1]) ? 'applied' : 'not_applied');

            $result[] = [
                'name' => $name,
                'status' => $status,
                'checks' => $checks,
            ];
        }

        return $result;
    }
}
