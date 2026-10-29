<?php

namespace App\Console\Commands;

use App\Services\PatientMergeService;
use Illuminate\Console\Command;

class PatientsMergeAll extends Command
{
    protected $signature = 'patients:merge-all {--apply : actually merge (default is a dry run)} {--skip= : comma-separated mobile numbers to leave alone}';

    protected $description = 'Merge every duplicate patient group sharing mobile, name, age and gender (dry run unless --apply)';

    public function handle(PatientMergeService $service): int
    {
        $skip = array_filter(array_map('trim', explode(',', (string) $this->option('skip'))));

        $report = $service->mergeAllSameDetails((bool) $this->option('apply'), $skip);

        $this->table(
            ['Mobile', 'Name', 'Keep', 'Merged (drop: rows moved)', 'Error'],
            array_map(fn ($g) => [
                $g['mobile_no'],
                $g['name'],
                $g['keep'],
                implode(', ', array_map(fn ($m) => "{$m['drop']}: {$m['rows']}", $g['merged'])),
                $g['error'] ?? '',
            ], $report)
        );

        $this->info(count($report) . ' group(s) ' . ($this->option('apply') ? 'merged.' : '-- dry run, nothing changed. Re-run with --apply.'));

        return self::SUCCESS;
    }
}
