<?php

namespace App\Http\Controllers;

use App\Services\WhatsappDeliveryStatusService;
use Illuminate\Http\Request;

/**
 * Receives WATI's webhook calls. WATI's webhook settings take only a URL
 * (no signing secret), so the shared secret travels in the URL path:
 *
 *     https://<app-host>/webhooks/wati/<WATI_WEBHOOK_SECRET>
 *
 * Disabled (404) until WATI_WEBHOOK_SECRET is set. Always answers 200 for
 * an authorised call -- even if processing fails -- so WATI doesn't keep
 * retrying an event we've already logged the error for.
 */
class WatiWebhookController extends Controller
{
    public function handle(Request $request, string $secret, WhatsappDeliveryStatusService $service)
    {
        $expected = (string) config('services.wati.webhook_secret');

        if ($expected === '' || !hash_equals($expected, $secret)) {
            abort(404);
        }

        $payload = $request->json()->all();

        if (!$payload) {
            return response()->json(['ok' => true, 'ignored' => 'empty payload']);
        }

        try {
            $result = $service->handle($payload);
        } catch (\Throwable $e) {
            \Log::error('WATI webhook processing failed: ' . $e->getMessage(), [
                'event_type' => $payload['eventType'] ?? null,
            ]);

            return response()->json(['ok' => false]);
        }

        return response()->json(['ok' => true] + $result);
    }
}
