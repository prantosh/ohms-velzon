<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Enabled
    |--------------------------------------------------------------------------
    */

    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Store Request Information
    |--------------------------------------------------------------------------
    */

    'store_ip' => true,
    'store_user_agent' => true,
    'store_request_url' => true,
    'store_request_id' => true,

    /*
    |--------------------------------------------------------------------------
    | Ignored Fields
    |--------------------------------------------------------------------------
    */

    'ignored_fields' => [

        'created_at',
        'updated_at',
        'deleted_at',

        'created_by',
        'updated_by',

        '_token',
        '_method',

        'remember_token',

    ],

    /*
    |--------------------------------------------------------------------------
    | Ignore Empty Changes
    |--------------------------------------------------------------------------
    */

    'ignore_empty_update' => true,

    /*
    |--------------------------------------------------------------------------
    | JSON Pretty Print
    |--------------------------------------------------------------------------
    */

    'json_pretty_print' => true,

    /*
    |--------------------------------------------------------------------------
    | Maximum Stored Value Length
    |--------------------------------------------------------------------------
    */

    'max_value_length' => 5000,

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    'actions' => [

        'CREATE',
        'UPDATE',
        'DELETE',

        'LOGIN',
        'LOGOUT',

        'PRINT',

        'CONFIRM',

        'EXPORT_EXCEL',
        'EXPORT_PDF',

        'WHATSAPP',

        'CUSTOM',

    ],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    */

    'modules' => [

    'USER'                => 'User',

    'DOCTOR'              => 'Doctor',

    'PATIENT'             => 'Patient',

    'SPECIALISATION'      => 'Specialisation',

    'INVOICE_ITEM_MASTER' => 'Invoice Item Master',

    'INVOICE_ITEM_DETAIL' => 'Invoice Item Detail',

    'DIAGNOSTIC_INVOICE'  => 'Diagnostic Invoice',

    'DOCTOR_VISIT'        => 'Doctor Visit',

    'DOCTOR_PAYMENT'      => 'Doctor Payment',

    'TEST_RESULT_ENTRY'   => 'Test Result Entry',

    'USG_REPORT'          => 'USG Report',
    'NON_PATHOLOGY_REPORT' => 'Non-Pathology Report',
    'CARDIOLOGY_REPORT' => 'Cardiology Report',

    'DIAGNOSTIC_TEST_REPORT' => 'Diagnostic Test Report',

    'TEST_ANALYTE_MASTER' => 'Test Analyte Master',

    'TEST_EXTRA_FIELD_TYPE' => 'Test Extra Field Type',

    'INSTRUMENT_MASTER' => 'Instrument Master',

    'KIT_MASTER' => 'Kit Master',

    'NOTE_MASTER' => 'Note Master',
    'REMARKS_MASTER' => 'Remarks Master',

    'MICROSCOPY_MASTER' => 'Microscopy Master',

    'IMPRESSION_MASTER' => 'Impression Master',

    'DETAIL_RANGE_MASTER' => 'Detail Range Master',

    'UOM_MASTER' => 'UOM Master',

    'SAMPLE_MASTER' => 'Sample Master',

    'TEST_GROUP_MASTER' => 'Test Group Master',
    'USG_REPORT_TEMPLATE' => 'USG Report Template',
    'CARDIOLOGY_REPORT_TEMPLATE' => 'Cardiology Report Template',
    'TEST_REPORT_TEMPLATE' => 'Test Report Template',

    'TEST_SUB_GROUP' => 'Test Sub Group',

    'EQUIPMENT_CATEGORY' => 'Equipment Category',

    'REPORTING_GROUP' => 'Reporting Group',

    'INCOME_CATEGORY' => 'Income Category',

    'EXPENDITURE_CATEGORY' => 'Expenditure Category',

    'EXPENDITURE_AGENCY' => 'Expenditure Agency',

    'DOCTOR_TEST_PAYMENT_MASTER' => 'Doctor Test Payment Master',

    'PATIENT_CARD' => 'Patient Card',

    'ROLE_PAGE_ACCESS' => 'Role Page Access',

    'AMBULANCE_DESTINATION_MASTER' => 'Ambulance Destination Master',

    'AMBULANCE_RENTAL'    => 'Ambulance Rental',

    'MEMBERSHIP_FEE_RATE_MASTER' => 'Membership Fee Rate Master',

    'MEMBERSHIP_FEE'      => 'Membership Fee',

    'INCOME_OTHER_SOURCE' => 'Income from Other Source',

    'INVOICE_CANCELLATION' => 'Invoice Cancellation',

    'CANCELLATION_PERMISSION' => 'Cancellation Permission',

    'INVENTORY_CATEGORY'  => 'Inventory Category',

    'INVENTORY_ITEM'      => 'Inventory Item',

    'PURCHASE_ORDER'      => 'Purchase Order',

    'GOODS_RECEIPT'       => 'Goods Receipt',

    'STOCK_ISSUE'         => 'Stock Issue',

    'DOCTOR_SCHEDULE'     => 'Doctor Schedule',

    'DOCTOR_APPOINTMENT'  => 'Doctor Appointment',

    'DOCTOR_SETTLEMENT'   => 'Doctor Settlement',

    'EQUIPMENT_RENTAL'    => 'Equipment Rental',

    'EXPENDITURE'         => 'Expenditure',

    'MAINTENANCE_MODE'    => 'Maintenance Mode',

    'WHATSAPP_AUTO_SEND_SETTINGS' => 'WhatsApp Auto-Send Settings',

    'CLOUD_BACKUP'        => 'Cloud Backup',

    'AUDIT'               => 'Audit',

],

    /*
    |--------------------------------------------------------------------------
    | Critical Fields
    |--------------------------------------------------------------------------
    */

    'critical_fields' => [

        'rate',

        'consultation_fee_total',

        'consultation_fee_doctor',

        'consultation_fee_doctor_discounted_patient',

        'discount_percent',

        'member_discount',

        'discount_other_lab',

        'discount_member_other_lab',

        'status',

    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Alerts
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [

        'enabled' => env('AUDIT_WHATSAPP_ENABLED', false),

        'only_critical_fields' => true,

        'template' => env(
            'AUDIT_WHATSAPP_TEMPLATE',
            'audit_alert'
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'retention_days' => env(
        'AUDIT_RETENTION_DAYS',
        3650
    ),

];
