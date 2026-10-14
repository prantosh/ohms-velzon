<?php

namespace App\Support;

/**
 * The main heading of a test report. By default it is the test description saved on
 * the invoice line; staff can edit it on the report card. Only a heading that differs
 * from the default is stored, so a report left on the default keeps following the
 * test description.
 */
class ReportTitle
{
    /** @return string|null the heading to store, or null for "use the default" */
    public static function normalise(?string $typed, string $default): ?string
    {
        $text = self::clean($typed);

        if ($text === '' || mb_strtolower($text) === mb_strtolower(self::clean($default))) {
            return null;
        }

        return $text;
    }

    private static function clean(?string $text): string
    {
        $text = strip_tags((string) $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, 255);
    }
}
