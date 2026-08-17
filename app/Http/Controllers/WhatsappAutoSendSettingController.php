<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Support\WhatsappMessageTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsappAutoSendSettingController extends Controller
{
    private const MODULE_CODE = 'WHATSAPP_AUTO_SEND_SETTINGS';

    /**
     * Toggling these affects whether patients/staff get notified at all
     * (and, for the two OTP categories, whether login/booking works over
     * WhatsApp), so this is restricted to Admin regardless of
     * role_page_access -- same defense in depth used for Maintenance Mode.
     */
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can control WhatsApp auto-send settings.');
        }
    }

    public function index()
    {
        $this->ensureAdmin();

        $labels = WhatsappMessageTypeRegistry::controllableTypes();

        foreach ($labels as $type => $label) {
            WhatsappAutoSendSetting::firstOrCreate(
                ['message_type' => $type],
                ['is_enabled' => true]
            );
        }

        $settings = WhatsappAutoSendSetting::whereIn('message_type', array_keys($labels))
            ->get()
            ->keyBy('message_type');

        $rows = collect($labels)->map(function ($label, $type) use ($settings) {
            return [
                'message_type' => $type,
                'label' => $label,
                'is_enabled' => (bool) $settings[$type]->is_enabled,
            ];
        })->values();

        return view('apps-whatsapp-auto-send-settings', ['rows' => $rows]);
    }

    public function toggle(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate([
            'message_type' => 'required|string|max:60',
            'is_enabled' => 'required|boolean',
        ]);

        if (!array_key_exists($request->message_type, WhatsappMessageTypeRegistry::controllableTypes())) {
            abort(422, 'Unknown message type.');
        }

        $setting = WhatsappAutoSendSetting::firstOrCreate(
            ['message_type' => $request->message_type],
            ['is_enabled' => true]
        );

        $oldData = $setting->only($setting->getFillable());

        $setting->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'updated_by' => Auth::id(),
        ]);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $setting,
            $oldData,
            $setting->only($setting->getFillable()),
            ($request->boolean('is_enabled') ? 'Enabled' : 'Disabled') . " automatic WhatsApp send for {$request->message_type}"
        );

        WhatsappAutoSendSetting::forgetCache();

        return response()->json([
            'status' => true,
            'message' => $request->boolean('is_enabled')
                ? 'Automatic sending turned ON for this category.'
                : 'Automatic sending turned OFF for this category. The manual "Send WhatsApp" button still works.',
            'is_enabled' => $request->boolean('is_enabled'),
        ]);
    }
}
