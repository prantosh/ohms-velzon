<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for WhatsApp message_type categories -- shared by
 * the WhatsApp Message Report (which discovers types actually present in
 * whatsapp_message_logs) and the auto-send toggle screen (which needs the
 * full curated list up front, before a category's first message ever sends).
 */
class WhatsappMessageTypeRegistry
{
    // Preferred display order for well-known types. Any message_type found in
    // the table but not listed here (e.g. a brand-new type introduced by a
    // future feature) is appended automatically, sorted alphabetically --
    // no code change is required for it to show up as its own column/row.
    public const TYPE_ORDER = [
        'INVOICE', 'TEST_REPORT', 'APPOINTMENT',
        'OTP_APPOINTMENT_BOOKING', 'OTP_FORGOT_PASSWORD', 'OTP',
        'DOCTOR_APPOINTMENT_REASSIGNED', 'DOCTOR_SCHEDULE_CHANGE',
        'NON_PATHOLOGY_REPORT', 'USG_REPORT', 'CARDIOLOGY_REPORT',
    ];

    public const TYPE_LABELS = [
        'INVOICE' => 'Invoice',
        'TEST_REPORT' => 'Test Report',
        'APPOINTMENT' => 'Appointment',
        // Kept for old rows logged before OTP was split into the two types
        // below -- new sends never write this type anymore.
        'OTP' => 'OTP (Legacy)',
        'OTP_APPOINTMENT_BOOKING' => 'Appointment Booking OTP',
        'OTP_FORGOT_PASSWORD' => 'Password Reset OTP',
        'DOCTOR_APPOINTMENT_REASSIGNED' => 'Appointment Reassigned (Blackout)',
        'DOCTOR_SCHEDULE_CHANGE' => 'Doctor Schedule Change',
        'NON_PATHOLOGY_REPORT' => 'Non-Pathology Report',
        'USG_REPORT' => 'USG Report',
        'CARDIOLOGY_REPORT' => 'Cardiology (Echo) Report',
    ];

    /**
     * Every message_type actually present in whatsapp_message_logs, mapped
     * to a display label -- known types get their curated TYPE_LABELS
     * entry, unknown ones get an auto-generated title-cased label.
     *
     * @return array<string,string> ['INVOICE' => 'Invoice', ...]
     */
    public static function resolveTypeColumns(): array
    {
        $existing = DB::table('whatsapp_message_logs')
            ->distinct()
            ->whereNotNull('message_type')
            ->pluck('message_type')
            ->all();

        $ordered = array_values(array_intersect(self::TYPE_ORDER, $existing));
        $extra = array_diff($existing, self::TYPE_ORDER);
        sort($extra);

        $types = array_merge($ordered, $extra);

        return collect($types)
            ->mapWithKeys(fn($type) => [$type => self::label($type)])
            ->all();
    }

    /**
     * Every curated, controllable message type this codebase knows about --
     * excludes the legacy 'OTP' type, which nothing writes anymore and no
     * auto-send gate ever checks, so it must not get a toggle row.
     *
     * @return array<string,string> ['INVOICE' => 'Invoice', ...]
     */
    public static function controllableTypes(): array
    {
        $types = array_values(array_diff(self::TYPE_ORDER, ['OTP']));

        return collect($types)
            ->mapWithKeys(fn($type) => [$type => self::label($type)])
            ->all();
    }

    public static function label(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? self::humanize($type);
    }

    public static function humanize(string $type): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $type)));
    }
}
