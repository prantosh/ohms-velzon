<?php

namespace App\Console\Commands;

use App\Support\PatientAge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ages every patient forward from age_recorded_on (see PatientAge::rollForward):
 * +1 month per elapsed month under 5 years, +1 year per anniversary from 5 up.
 * age_recorded_on moves forward by the same steps, so running it daily (or
 * catching up after missed days) never double counts, and PatientAge::current()
 * on screen only has the partial period left to add.
 *
 * Uses the query builder on purpose: the Patient model's saving hook would
 * re-stamp age_recorded_on to today.
 */
class RollPatientAges extends Command
{
    protected $signature = 'patients:roll-age';

    protected $description = 'Advance patients\' stored age (months under 5, years from 5) based on age_recorded_on';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $updated = 0;

        DB::table('patients')
            ->whereNotNull('age')
            ->whereNotNull('age_recorded_on')
            ->where('age_recorded_on', '<', $today->toDateString())
            ->orderBy('id')
            ->chunkById(500, function ($patients) use ($today, &$updated) {

                foreach ($patients as $p) {

                    $months = $p->age_months === null ? null : (int) $p->age_months;
                    $r = PatientAge::rollForward((int) $p->age, $months, $p->age_recorded_on, $today);

                    if ($r['years'] === (int) $p->age && $r['months'] === $months) {
                        continue;
                    }

                    DB::table('patients')->where('id', $p->id)->update([
                        'age' => $r['years'],
                        'age_months' => $r['months'],
                        'age_recorded_on' => $r['anchor']->toDateString(),
                    ]);

                    $updated++;
                }
            });

        $this->info($updated . ' patient(s) aged.');

        return self::SUCCESS;
    }
}
