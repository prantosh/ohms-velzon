<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WatiService
{
    public static function sendInvoice(
        $mobile,
        $patientName,
        $invoiceNo,
        $pdfUrl,
        $generatedAt,
        $templateName = null,
        $broadcastName = null
    ) {

        $tenantId = env('WATI_TENANT_ID');

        $url =
            env('WATI_BASE_URL') .
            '/' .
            $tenantId .
            '/api/v1/sendTemplateMessage' .
            '?whatsappNumber=91' .
            preg_replace('/\D/', '', $mobile);

        $payload = [

            'template_name' => $templateName ?? env('WATI_TEMPLATE_NAME'),

            'broadcast_name' => $broadcastName ?? env('WATI_BROADCAST_NAME'),

            'parameters' => [

                [
                    'name' => '1',
                    'value' => $pdfUrl
                ],

                [
                    'name' => '2',
                    'value' => $patientName
                ],

                [
                    'name' => '3',
                    'value' => $generatedAt
                ],

                [
                    'name' => '4',
                    'value' => $invoiceNo
                ]
            ]
        ];
        \Log::info('WATI URL', [
            'base_url' => env('WATI_BASE_URL'),
            'tenant_id' => env('WATI_TENANT_ID'),
        ]);
        return Http::withHeaders([

            'Authorization' =>
                'Bearer ' . env('WATI_API_KEY'),

            'Content-Type' =>
                'application/json'

        ])->post($url, $payload);
    }

    public function sendTemplateMessage(
        $mobileNo,
        $templateName,
        $broadcastName,
        $parameters
    ) {

        $tenantId = env('WATI_TENANT_ID');

        $url =
            env('WATI_BASE_URL') .
            '/' .
            $tenantId .
            '/api/v1/sendTemplateMessage' .
            '?whatsappNumber=' .
            $mobileNo;

        \Log::info('WATI TEMPLATE URL', [
            'url' => $url
        ]);

        $payload = [

            'template_name' => $templateName,

            'broadcast_name' => $broadcastName,

            'parameters' => $parameters
        ];

        $response = Http::withHeaders([

            'Authorization' =>
                'Bearer ' . env('WATI_API_KEY'),

            'Content-Type' =>
                'application/json'

        ])->post($url, $payload);

        \Log::info('WATI TEMPLATE RESPONSE', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return $response->json();
    }
}
