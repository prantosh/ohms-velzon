<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WhatsappAutoSendSetting extends Model
{
    protected $fillable = [
        'message_type',
        'is_enabled',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public const CACHE_KEY = 'whatsapp_auto_send_settings';

    /** new message type => the type it was split from */
    private const INHERITS_SETTING_FROM = [
        'DOCTOR_VISIT_INVOICE' => 'INVOICE',
    ];

    /**
     * A message_type with no row yet defaults to enabled -- new categories
     * introduced by future features stay on until an Admin explicitly turns
     * them off, mirroring how new types just work elsewhere in this feature.
     */
    public static function isEnabled(string $messageType): bool
    {
        $settings = Cache::rememberForever(self::CACHE_KEY, function () {
            return self::pluck('is_enabled', 'message_type')->all();
        });

        if (array_key_exists($messageType, $settings)) {
            return $settings[$messageType];
        }

        // A type that was split out of another one inherits the old type's
        // switch until an Admin sets its own, so splitting a category never
        // silently turns sending back on (or off).
        $inheritsFrom = self::INHERITS_SETTING_FROM[$messageType] ?? null;

        if ($inheritsFrom !== null && array_key_exists($inheritsFrom, $settings)) {
            return $settings[$inheritsFrom];
        }

        return true;
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Records that an automatic send was suppressed by an Admin toggle --
     * $referenceNo is whatever this category normally logs there (a real
     * invoice number, an appointment_no, or a synthetic id), not
     * necessarily an actual invoice.
     */
    public static function logSkipped(
        string $messageType,
        string $referenceNo,
        ?string $mobileNo,
        ?string $patientName
    ): void {
        DB::table('whatsapp_message_logs')->insert([
            'invoice_no' => $referenceNo,
            'mobile_no' => $mobileNo,
            'patient_name' => $patientName,
            'message_type' => $messageType,
            'status' => 'SKIPPED',
            'response' => 'Automatic sending is turned off by Admin for this message type.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
