<?php

/*
|--------------------------------------------------------------------------
| Mobile Admin Menu
|--------------------------------------------------------------------------
| The full top menu is desktop-only (the horizontal layout hides it below
| 1025px). On phones/tablets the topbar shows a menu button that opens
| this short list instead -- only pages that work on a small screen.
|
| The mobile menu (button + panel) is shown to Admin users only.
|
| page:  the role-page-access key (same one the sidebar checks). Kept so
|        the menu can be opened to other roles later by removing the
|        Admin check in layouts/mobile-menu + layouts/topbar.
| admin_only: true for pages that are hard-wired to Admin (not in the
|        role-page-access list), e.g. Report Print With Due Log.
|
| To add a page: add one entry here and make the page itself mobile-friendly.
*/

return [

    [
        'section' => 'Approvals & Corrections',
        'items' => [
            ['label' => 'Cancellation Permission', 'icon' => 'ri-shield-check-line', 'route' => 'cancellation-permission.index', 'page' => 'cancellation-permission'],
            ['label' => 'Patient Detail Correction', 'icon' => 'ri-user-settings-line', 'route' => 'patient-detail-correction.index', 'page' => 'patient-detail-correction'],
            ['label' => 'Report Print With Due Log', 'icon' => 'ri-printer-line', 'route' => 'report-print-due-log.index', 'admin_only' => true],
        ],
    ],

    [
        'section' => 'Doctors',
        'items' => [
            ['label' => 'Doctor Appointments', 'icon' => 'ri-calendar-check-line', 'route' => 'doctor-appointments.index', 'page' => 'doctor-appointments'],
            ['label' => 'Doctor Unavailable Dates', 'icon' => 'ri-calendar-close-line', 'route' => 'doctor-unavailable-dates.index', 'page' => 'doctor-unavailable-dates'],
        ],
    ],

    [
        'section' => 'WhatsApp & System',
        'items' => [
            ['label' => 'WhatsApp Message Report', 'icon' => 'ri-whatsapp-line', 'route' => 'whatsapp-message-report.index', 'page' => 'whatsapp-message-report'],
            ['label' => 'WhatsApp Auto-Send Settings', 'icon' => 'ri-toggle-line', 'route' => 'whatsapp-auto-send-settings.index', 'page' => 'whatsapp-auto-send-settings'],
            ['label' => 'Maintenance Mode', 'icon' => 'ri-tools-line', 'route' => 'maintenance-mode.index', 'page' => 'maintenance-mode'],
        ],
    ],

];
