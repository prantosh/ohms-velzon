<?php

namespace App\Console\Commands;

use App\Services\PatientMergeService;
use Illuminate\Console\Command;

class PatientsDuplicates extends Command
{
    protected $signature = 'patients:duplicates';

    protected $description = 'List active patients sharing a mobile number and name (likely duplicates)';

    public function handle(PatientMergeService $service): int
    {
        $groups = $service->duplicateGroups();

        $this->table(
            ['Mobile', 'Name', 'Codes', 'Same age/gender'],
            array_map(fn ($g) => [
                $g['mobile_no'],
                $g['name'],
                implode(', ', $g['codes']),
                $g['same_details'] ? 'yes' : 'NO - check',
            ], $groups)
        );

        $this->info(count($groups) . ' group(s), ' . count(array_filter($groups, fn ($g) => $g['same_details'])) . ' with the same age and gender.');

        return self::SUCCESS;
    }
}
