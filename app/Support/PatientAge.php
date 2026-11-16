<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Single place for patient age as years + months.
 *
 * Forms have two boxes: Age (years) and Months. See fromInput().
 */
class PatientAge
{
    /**
     * Age from the two separate form boxes (years, months).
     * A blank box counts as 0; both blank is invalid.
     *
     * @return array{years:int,months:int}
     * @throws \InvalidArgumentException
     */
    public static function fromInput($years, $months): array
    {
        $years = trim((string) $years);
        $months = trim((string) $months);

        if ($years === '' && $months === '') {
            throw new \InvalidArgumentException('Enter the patient\'s age in years and/or months.');
        }

        if (($years !== '' && !ctype_digit($years)) || ($months !== '' && !ctype_digit($months))) {
            throw new \InvalidArgumentException('Age and months must be whole numbers.');
        }

        $y = $years === '' ? 0 : (int) $years;
        $m = $months === '' ? 0 : (int) $months;

        if ($y > 150) {
            throw new \InvalidArgumentException('Age cannot be more than 150 years.');
        }

        if ($m > 11) {
            throw new \InvalidArgumentException('Months must be between 0 and 11.');
        }

        return ['years' => $y, 'months' => $m];
    }


    /** Human text: "7 Months", "1 Year 7 Months"; years-only stays "34". */
    public static function format($years, $months): ?string
    {
        if ($years === null || $years === '') {
            return null;
        }

        $years = (int) $years;
        $months = ($months === null || $months === '') ? null : (int) $months;

        if ($years === 0 && $months !== null) {
            return $months . ($months === 1 ? ' Month' : ' Months');
        }

        if ($months !== null && $months > 0 && $years < 5) {
            return $years . ($years === 1 ? ' Year ' : ' Years ') . $months . ($months === 1 ? ' Month' : ' Months');
        }

        return (string) $years;
    }

    /**
     * Roll a recorded age forward to today.
     * With months recorded: whole months elapsed are added. Years-only: whole
     * years elapsed are added. No recorded date: returned as-is (legacy rows).
     *
     * @return array{years:?int,months:?int}
     */
    public static function current($years, $months, $recordedOn): array
    {
        if ($years === null || $years === '') {
            return ['years' => null, 'months' => null];
        }

        $years = (int) $years;
        $months = ($months === null || $months === '') ? null : (int) $months;

        if (!$recordedOn) {
            return ['years' => $years, 'months' => $months];
        }

        $recorded = Carbon::parse($recordedOn)->startOfDay();
        $today = now()->startOfDay();

        if ($recorded->gte($today)) {
            return ['years' => $years, 'months' => $months];
        }

        if ($months === null) {
            return ['years' => $years + $recorded->diffInYears($today), 'months' => null];
        }

        $total = $years * 12 + $months + $recorded->diffInMonths($today);

        return ['years' => intdiv($total, 12), 'months' => $total % 12];
    }
}
