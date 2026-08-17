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

        return $settings[$messageType] ?? true;
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
