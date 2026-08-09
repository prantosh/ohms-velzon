<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'wati' => [
        'url' => env('WATI_API_URL'),
        'token' => env('WATI_API_TOKEN'),
        'otp_template_name' => env('WATI_OTP_TEMPLATE_NAME'),
        'otp_broadcast_name' => env('WATI_OTP_BROADCAST_NAME'),
        'oxygen_template_name' => env('WATI_OXYGEN_TEMPLATE_NAME', 'oxygen_rent'),
        'oxygen_broadcast_name' => env('WATI_OXYGEN_BROADCAST_NAME', 'oxygen_rent'),
        'concentrator_template_name' => env('WATI_CONCENTRATOR_TEMPLATE_NAME', 'concentrator_rent_new'),
        'concentrator_broadcast_name' => env('WATI_CONCENTRATOR_BROADCAST_NAME', 'concentrator_rent_new'),
        'test_report_template_name' => env('WATI_TEST_REPORT_TEMPLATE_NAME', 'diagnostic_test_report'),
        'test_report_broadcast_name' => env('WATI_TEST_REPORT_BROADCAST_NAME', 'diagnostic_test_report'),
        'usg_report_template_name' => env('WATI_USG_REPORT_TEMPLATE_NAME', 'usg_report'),
        'usg_report_broadcast_name' => env('WATI_USG_REPORT_BROADCAST_NAME', 'usg_report'),
        'non_pathology_report_template_name' => env('WATI_NON_PATHOLOGY_REPORT_TEMPLATE_NAME', 'non_pathology_report'),
        'non_pathology_report_broadcast_name' => env('WATI_NON_PATHOLOGY_REPORT_BROADCAST_NAME', 'non_pathology_report'),
    ],

];
