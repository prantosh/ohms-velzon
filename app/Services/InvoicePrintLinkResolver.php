<?php

namespace App\Services;

class InvoicePrintLinkResolver
{
    // Invoice types whose print route streams a PDF directly (safe for a plain link).
    // DOCTOR_VISIT is handled separately -- its print route returns JSON, not a PDF stream.
    private const DIRECT_PRINT_ROUTES = [
        'DIAGNOSTIC' => '/diagnostic-invoice/print/',
        'OXYGEN_RENT' => '/equipment-rental/print/',
        'CONCENTRATOR_RENT' => '/equipment-rental/print/',
        'MEMBERSHIP_FEE' => '/membership-fee/print/',
        'AMBULANCE_RENT' => '/ambulance-rental/print/',
        'OTHER_INCOME' => '/income/print/',
    ];

    public static function resolve(?string $invoiceType, ?int $invoiceId): array
    {
        if ($invoiceId === null) {
            return ['print_url' => null, 'print_ajax_url' => null];
        }

        if ($invoiceType === 'DOCTOR_VISIT') {
            return ['print_url' => null, 'print_ajax_url' => '/doctor-visit-invoice/print/' . $invoiceId];
        }

        if (isset(self::DIRECT_PRINT_ROUTES[$invoiceType])) {
            return ['print_url' => self::DIRECT_PRINT_ROUTES[$invoiceType] . $invoiceId, 'print_ajax_url' => null];
        }

        return ['print_url' => null, 'print_ajax_url' => null];
    }
}
