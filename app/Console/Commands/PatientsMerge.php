<?php

namespace App\Console\Commands;

use App\Services\PatientMergeService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PatientsMerge extends Command
{
    protected $signature = 'patients:merge {keep : patient code to keep} {drop : duplicate patient code to fold into it} {--apply : actually merge (default is a dry run)}';

    protected $description = 'Merge a duplicate patient into another (dry run unless --apply)';

    public function handle(PatientMergeService $service): int
    {
        try {
            $plan = $this->option('apply')
                ? $service->merge($this->argument('keep'), $this->argument('drop'))
                : $service->plan($this->argument('keep'), $this->argument('drop'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line("Keep: {$plan['keep']->patient_id} ({$plan['keep']->patient_name}, {$plan['keep']->age}, {$plan['keep']->gender})");
        $this->line("Drop: {$plan['drop']->patient_id} ({$plan['drop']->patient_name}, {$plan['drop']->age}, {$plan['drop']->gender}) -> set Inactive");

        $this->table(['Table.column', 'Rows ' . ($this->option('apply') ? 'moved' : 'to move')], collect($plan['rows'])->map(fn ($n, $k) => [$k, $n])->values()->all());

        if (!$this->option('apply')) {
            $this->warn('Dry run -- nothing changed. Re-run with --apply to merge.');
        }

        return self::SUCCESS;
    }
}
