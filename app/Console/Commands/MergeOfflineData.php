<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\OfflineMergeService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 of docs/OFFLINE_DISASTER_RECOVERY.md -- run on the WAMP machine
 * once internet is restored, after taking fresh backups of both sides
 * (Phase 3 steps 1-2 of that doc -- this command does not do that for you).
 *
 * Always computes and prints the full merge plan first. Without --commit,
 * that's all it does -- nothing is written. With --commit, it additionally
 * asks for an explicit confirmation before writing anything.
 */
class MergeOfflineData extends Command
{
    protected $signature = 'offline:merge
        {--host= : Production DB host}
        {--port=3306 : Production DB port}
        {--database= : Production DB name}
        {--username= : Production DB username}
        {--password= : Production DB password}
        {--run-by= : Production user id to attribute merged records to in the audit log}
        {--since= : Cutover timestamp (Y-m-d H:i:s) from Phase 1 step 7, sharpens pre-existing-row detection}
        {--commit : Actually write to production. Without this flag, only the report is shown.}';

    protected $description = 'Merge OFF-prefixed offline records from this WAMP database into production';

    public function handle(AuditService $auditService): int
    {
        // Captured before anything below can change it -- this is WAMP's
        // own real connection, used as the read side for the whole merge.
        $sourceConnection = config('database.default');

        $host = $this->option('host') ?: $this->ask('Production DB host');
        $port = $this->option('port') ?: $this->ask('Production DB port', '3306');
        $database = $this->option('database') ?: $this->ask('Production DB name');
        $username = $this->option('username') ?: $this->ask('Production DB username');
        $password = $this->option('password') ?: $this->secret('Production DB password');

        $runBy = $this->option('run-by') ?: $this->ask('Production user id to attribute this merge to (for the audit log)');

        if (!$runBy || !is_numeric($runBy)) {
            $this->error('A valid --run-by user id is required so merged records are attributed to a real user in the audit log.');
            return self::FAILURE;
        }

        $since = null;

        if ($sinceOption = $this->option('since')) {
            try {
                $since = Carbon::parse($sinceOption);
            } catch (\Exception $e) {
                $this->error("Could not parse --since=\"{$sinceOption}\" as a date/time.");
                return self::FAILURE;
            }
        }

        config(['database.connections.production' => [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]]);

        try {
            DB::connection('production')->getPdo();
        } catch (\Exception $e) {
            $this->error('Could not connect to the production database: ' . $e->getMessage());
            return self::FAILURE;
        }

        Auth::loginUsingId((int) $runBy);

        if (!Auth::check()) {
            $this->error("No user found with id {$runBy} on this (offline/WAMP) database -- --run-by must be a user id that also exists here, since AuditService reads the currently-authenticated user.");
            return self::FAILURE;
        }

        $this->info('Computing merge plan (read-only, nothing written yet)...');

        $service = new OfflineMergeService(
            $auditService,
            $sourceConnection,
            $this->option('since') ? "since {$since}" : null
        );

        $plan = $service->plan($since);

        $this->printReport($plan);

        if (!$this->option('commit')) {
            $this->newLine();
            $this->info('Dry run only -- nothing was written. Re-run with --commit to actually merge these records.');
            return self::SUCCESS;
        }

        $totalToCreate = collect($plan['entities'])->sum('created');

        if ($totalToCreate === 0) {
            $this->info('Nothing to merge -- all offline records are already present on production.');
            return self::SUCCESS;
        }

        $this->newLine();

        if (!$this->confirm("Commit {$totalToCreate} record(s) to production? This cannot be undone.")) {
            $this->warn('Cancelled -- nothing was written.');
            return self::SUCCESS;
        }

        $this->info('Writing to production...');

        $result = $service->commit($since);

        $this->printReport($result);

        $this->newLine();
        $this->info('Merge complete.');

        return self::SUCCESS;
    }

    private function printReport(array $plan): void
    {
        $this->newLine();
        $this->table(
            ['Entity', 'To create / created', 'Already present'],
            collect($plan['entities'])->map(fn ($counts, $name) => [
                $name,
                $counts['created'],
                $counts['already_present'],
            ])->values()->all()
        );

        if (!empty($plan['flagged'])) {

            $this->newLine();
            $this->warn('Pre-existing records modified offline -- NOT auto-merged, reconcile these manually on production:');

            $this->table(
                ['Table', 'Document number', 'Updated at (offline)', 'Updated by (offline user id)'],
                collect($plan['flagged'])->map(fn ($f) => [
                    $f['table'], $f['number'], $f['updated_at'], $f['updated_by'],
                ])->all()
            );
        }
    }
}
