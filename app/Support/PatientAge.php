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

        $r = self::rollForward($years, $months, $recorded, $today);

        return ['years' => $r['years'], 'months' => $r['months']];
    }

    /**
     * The one rule for ageing a recorded age:
     *  - under 5 years: +1 month each time a month has passed since the anchor date;
     *  - 5 years and over: +1 year on each anniversary of the anchor date.
     * Months are stepped from the anchor (not date-to-date) so 31st-of-month
     * anchors don't drift. Returns the new age plus the anchor date it now
     * counts from (the date of the last step taken), so the stored
     * age_recorded_on can be moved forward by the same amount.
     *
     * @return array{years:int,months:?int,anchor:Carbon}
     */
    public static function rollForward(int $years, ?int $months, $recordedOn, $today = null): array
    {
        $today = ($today ? Carbon::parse($today) : now())->startOfDay();
        $anchor = Carbon::parse($recordedOn)->startOfDay();

        // months phase (under 5)
        if ($years < 5) {
            $origin = $anchor->copy();
            $k = 0;

            while ($years < 5) {
                $next = $origin->copy()->addMonthsNoOverflow($k + 1);

                if ($next->gt($today)) {
                    break;
                }

                $k++;
                $months = ($months ?? 0) + 1;

                if ($months >= 12) {
                    $years++;
                    $months = 0;
                }

                $anchor = $next;
            }

            if ($years < 5) {
                return ['years' => $years, 'months' => $months, 'anchor' => $anchor];
            }
        }

        // years phase (5 and over)
        $origin = $anchor->copy();
        $j = 0;

        while (true) {
            $next = $origin->copy()->addYearsNoOverflow($j + 1);

            if ($next->gt($today)) {
                break;
            }

            $j++;
            $years++;
            $anchor = $next;
        }

        return ['years' => $years, 'months' => $months, 'anchor' => $anchor];
    }
}
