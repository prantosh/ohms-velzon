<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Folds a duplicate patients row into the one being kept: every table that
 * stores the patient's string code (patients.patient_id, e.g.
 * P9874655758-04) is pointed at the kept code, then the duplicate is set
 * Inactive. It is deliberately NOT deleted -- patient codes are numbered
 * from COUNT(*) + 1 per mobile number, so removing a row can make the next
 * new patient collide with an existing code.
 *
 * Only for a duplicate of the same person (same mobile number); anything
 * else is refused.
 */
class PatientMergeService
{
    /**
     * Tables holding the patient's string code, as [table, column].
     */
    private const REFERENCES = [
        ['invoices', 'patient_id'],
        ['doctor_appointments', 'patient_id'],
        ['daily_transactions', 'patient_id'],
        ['doctor_payables', 'patient_id'],
        ['doctor_settlement_items', 'patient_id'],
        ['doctor_test_payments', 'patient_id'],
        ['patient_key_parameter_values', 'patient_id'],
    ];

    /**
     * Same mobile + same name (ignoring case/spacing) + same age and gender
     * where both are recorded -- the pairs that are plainly one person.
     *
     * @return array<int, array<string, mixed>>
     */
    public function duplicateGroups(): array
    {
        $rows = DB::select("
            SELECT mobile_no,
                   UPPER(TRIM(patient_name)) AS name,
                   COUNT(*) AS total,
                   GROUP_CONCAT(patient_id ORDER BY id SEPARATOR ',') AS codes,
                   COUNT(DISTINCT COALESCE(age, -1)) AS ages,
                   COUNT(DISTINCT COALESCE(gender, '')) AS genders
            FROM patients
            WHERE status = 'A'
            GROUP BY mobile_no, UPPER(TRIM(patient_name))
            HAVING COUNT(*) > 1
            ORDER BY total DESC, mobile_no
        ");

        return array_map(fn ($r) => [
            'mobile_no' => $r->mobile_no,
            'name' => $r->name,
            'codes' => explode(',', $r->codes),
            // A differing age or gender means two real people may share a
            // name on one family mobile -- listed, but flagged for a human.
            'same_details' => $r->ages === 1 && $r->genders === 1,
        ], $rows);
    }

    /**
     * Merge every duplicate group whose members share age and gender (the
     * oldest code in each group is kept). Dry run unless $apply. Groups on
     * a mobile in $skipMobiles are left alone.
     *
     * @param  array<int, string> $skipMobiles
     * @return array<int, array<string, mixed>> one entry per group
     */
    public function mergeAllSameDetails(bool $apply, array $skipMobiles = [], ?int $userId = null): array
    {
        $report = [];

        foreach ($this->duplicateGroups() as $group) {

            if (!$group['same_details'] || in_array($group['mobile_no'], $skipMobiles, true)) {
                continue;
            }

            $keepCode = $group['codes'][0];
            $entry = ['mobile_no' => $group['mobile_no'], 'name' => $group['name'], 'keep' => $keepCode, 'merged' => [], 'error' => null];

            foreach (array_slice($group['codes'], 1) as $dropCode) {

                try {
                    $plan = $apply
                        ? $this->merge($keepCode, $dropCode, $userId)
                        : $this->plan($keepCode, $dropCode);

                    $entry['merged'][] = ['drop' => $dropCode, 'rows' => array_sum($plan['rows'])];

                } catch (InvalidArgumentException $e) {
                    $entry['error'] = "{$dropCode}: " . $e->getMessage();
                }
            }

            $report[] = $entry;
        }

        return $report;
    }

    /**
     * What merging $drop into $keep would touch (nothing is changed).
     *
     * @return array{keep: Patient, drop: Patient, rows: array<string,int>}
     */
    public function plan(string $keepCode, string $dropCode): array
    {
        [$keep, $drop] = $this->resolve($keepCode, $dropCode);

        $rows = [];
        foreach (self::REFERENCES as [$table, $column]) {
            $rows["{$table}.{$column}"] = DB::table($table)->where($column, $drop->patient_id)->count();
        }

        return ['keep' => $keep, 'drop' => $drop, 'rows' => $rows];
    }

    public function merge(string $keepCode, string $dropCode, ?int $userId = null): array
    {
        $plan = $this->plan($keepCode, $dropCode);

        /** @var Patient $keep */
        $keep = $plan['keep'];
        /** @var Patient $drop */
        $drop = $plan['drop'];

        DB::transaction(function () use ($keep, $drop, $userId) {

            foreach (self::REFERENCES as [$table, $column]) {
                DB::table($table)->where($column, $drop->patient_id)->update([$column => $keep->patient_id]);
            }

            // Details only the duplicate had.
            $fill = [];
            if ($keep->age === null && $drop->age !== null) {
                $fill['age'] = $drop->age;
            }
            if (empty($keep->gender) && !empty($drop->gender)) {
                $fill['gender'] = $drop->gender;
            }
            if ($fill) {
                $keep->update($fill);
            }

            $drop->update(['status' => 'I']);

            app(AuditService::class)->logAction(
                'PATIENT',
                $keep,
                'CUSTOM',
                "Duplicate patient {$drop->patient_id} merged into {$keep->patient_id}" . ($userId ? " by user {$userId}" : '')
            );
        });

        return $plan;
    }

    private function resolve(string $keepCode, string $dropCode): array
    {
        if ($keepCode === $dropCode) {
            throw new InvalidArgumentException('Keep and drop are the same patient.');
        }

        $keep = Patient::where('patient_id', $keepCode)->first();
        $drop = Patient::where('patient_id', $dropCode)->first();

        if (!$keep || !$drop) {
            throw new InvalidArgumentException('Patient code not found.');
        }

        if ($keep->mobile_no !== $drop->mobile_no) {
            throw new InvalidArgumentException('The two patients have different mobile numbers -- not merged.');
        }

        if (strcasecmp(trim($keep->patient_name), trim($drop->patient_name)) !== 0) {
            throw new InvalidArgumentException('The two patients have different names -- not merged.');
        }

        return [$keep, $drop];
    }
}
