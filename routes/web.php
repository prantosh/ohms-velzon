<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\DoctorScheduleController;
use App\Http\Controllers\DoctorScheduleExceptionController;
use App\Http\Controllers\DoctorAppointmentController;
use App\Http\Controllers\PublicAppointmentController;
use App\Http\Controllers\DoctorVisitInvoiceController;
use App\Http\Controllers\DiagnosticInvoiceController;
use App\Http\Controllers\DoctorTestPaymentMasterController;
use App\Http\Controllers\SpecialisationController;
use App\Http\Controllers\DoctorHistoryController;
use App\Http\Controllers\DoctorPayableController;
use App\Http\Controllers\DoctorPayableByUserController;
use App\Http\Controllers\DoctorSettlementController;
use App\Http\Controllers\InvoiceItemMasterController;
use App\Http\Controllers\InvoiceItemDetailController;
use App\Http\Controllers\EquipmentCategoryController;
use App\Http\Controllers\EquipmentRentalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RolePageAccessController;
use App\Http\Controllers\TestGroupMasterController;
use App\Http\Controllers\UsgReportTemplateController;
use App\Http\Controllers\TestReportTemplateController;
use App\Http\Controllers\SampleMasterController;
use App\Http\Controllers\UomMasterController;
use App\Http\Controllers\TestExtraFieldTypeController;
use App\Http\Controllers\DiagnosticTestAdditionalInfoController;
use App\Http\Controllers\InstrumentMasterController;
use App\Http\Controllers\KitMasterController;
use App\Http\Controllers\NoteMasterController;
use App\Http\Controllers\RemarksMasterController;
use App\Http\Controllers\MicroscopyMasterController;
use App\Http\Controllers\ImpressionMasterController;
use App\Http\Controllers\DetailRangeMasterController;
use App\Http\Controllers\TestParameterController;
use App\Http\Controllers\TestResultEntryController;
use App\Http\Controllers\UsgReportController;
use App\Http\Controllers\NonPathologyReportController;
use App\Http\Controllers\TestReportDashboardController;
use App\Http\Controllers\DiagnosticTestReportController;
use App\Http\Controllers\AmbulanceDestinationController;
use App\Http\Controllers\AmbulanceRentalController;
use App\Http\Controllers\MembershipFeeRateController;
use App\Http\Controllers\MembershipFeeController;
use App\Http\Controllers\PatientHistoryController;
use App\Http\Controllers\InventoryCategoryController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\StockIssueController;
use App\Http\Controllers\StockLedgerController;
use App\Http\Controllers\PurchaseOrderReportController;
use App\Http\Controllers\GoodsReceiptReportController;
use App\Http\Controllers\GoodsIssueReportController;
use App\Http\Controllers\StockAsOnDateController;
use App\Http\Controllers\CloudBackupController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\PatientCardController;
use App\Http\Controllers\ExpenditureCategoryController;
use App\Http\Controllers\ExpenditureAgencyController;
use App\Http\Controllers\ExpenditureController;
use App\Http\Controllers\IncomeCategoryController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\InvoiceCancellationController;
use App\Http\Controllers\CancellationPermissionController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\MembershipFeeStatusController;
use App\Http\Controllers\DoctorConsultationListController;
use App\Http\Controllers\DoctorVisitPaymentReportController;
use App\Http\Controllers\DoctorTestPaymentReportController;
use App\Http\Controllers\DoctorPaymentReportController;
use App\Http\Controllers\DoctorPayableNonCashReportController;
use App\Http\Controllers\DoctorWisePaymentReportController;
use App\Http\Controllers\DailyTransactionReportController;
use App\Http\Controllers\WhatsappMessageReportController;
use App\Http\Controllers\ItemWiseReportController;
use App\Http\Controllers\ItemWiseSummaryReportController;
use App\Http\Controllers\MonthlyReconciliationReportController;
use App\Http\Controllers\UserInvoiceReportController;
use App\Http\Controllers\AllInvoicesReportController;
use App\Http\Controllers\CashSubmissionReportController;
use App\Http\Controllers\EmployeePerformanceController;
use App\Http\Controllers\ReportingGroupController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes(['reset' => false]);

/*
|--------------------------------------------------------------------------
| STAFF PASSWORD RESET -- mobile number + WhatsApp OTP
|--------------------------------------------------------------------------
| Replaces Laravel's stock emailed reset-link routes (disabled above via
| 'reset' => false) with the same mobile-OTP mechanism used for public
| doctor booking (see PublicAppointmentController).
*/

Route::get('/password/reset', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showLinkRequestForm'])
    ->middleware('guest')
    ->name('password.request');

Route::post('/password/send-otp', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendOtp'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('password.send-otp');

Route::post('/password/verify-otp', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'verifyOtp'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('password.verify-otp');

Route::post('/password/reset', [App\Http\Controllers\Auth\ResetPasswordController::class, 'reset'])
    ->middleware('guest')
    ->name('password.update');

//Language Translation


Route::get('/customer/list', [CustomerController::class, 'index']);
Route::post('/customer/add', [CustomerController::class, 'store']);
Route::post('/customer/update/{id}', [CustomerController::class, 'update']);
Route::delete('/customer/delete/{id}', [CustomerController::class, 'destroy']);

Route::get('/customer/export/excel', [CustomerController::class, 'exportExcel']);
Route::get('/customer/export/print', [CustomerController::class, 'printCustomers']);


/*
|--------------------------------------------------------------------------
| DOCTOR PAGE
|--------------------------------------------------------------------------
*/

Route::get(
    '/doctor',
    [DoctorController::class, 'index']
)->name('doctors.index');

/*
|--------------------------------------------------------------------------
| DOCTOR AJAX LIST
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| DOCTOR PAGE
|--------------------------------------------------------------------------
*/

Route::get('/doctor', [DoctorController::class, 'index'])
    ->name('doctors.index');

/*
|--------------------------------------------------------------------------
| DOCTOR AJAX LIST
|--------------------------------------------------------------------------
*/

Route::get('/doctor/list', [DoctorController::class, 'getDoctors']);

Route::post('/doctor/add', [DoctorController::class, 'store']);

Route::post('/doctor/update/{id}', [DoctorController::class, 'update']);

Route::delete('/doctor/delete/{id}', [DoctorController::class, 'destroy']);

Route::get('/doctor/export/excel', [DoctorController::class, 'exportExcel']);

Route::get('/doctor/export/print', [DoctorController::class, 'printDoctors']);
/* KEEP THIS LAST */

Route::get('index/{locale}', [App\Http\Controllers\HomeController::class, 'lang']);

Route::get('/', [App\Http\Controllers\HomeController::class, 'root'])->name('root');

//Update User Details
Route::post('/update-profile/{id}', [App\Http\Controllers\HomeController::class, 'updateProfile'])->name('updateProfile');
Route::post('/update-password/{id}', [App\Http\Controllers\HomeController::class, 'updatePassword'])->name('updatePassword');
Route::post('/unlock-screen', [App\Http\Controllers\HomeController::class, 'unlockScreen'])->name('unlockScreen');
Route::post('/lock-screen', [App\Http\Controllers\HomeController::class, 'lockScreen'])->name('lockScreen');
Route::get('/live-users', [App\Http\Controllers\HomeController::class, 'liveUsers'])->name('liveUsers');



Route::get(
    '/doctor-schedules',
    [DoctorScheduleController::class, 'index']
)->name('doctor-schedules.index');

Route::get(
    '/doctor-schedules/list',
    [DoctorScheduleController::class, 'getSchedules']
);

Route::post(
    '/doctor-schedules/add',
    [DoctorScheduleController::class, 'store']
);

Route::post(
    '/doctor-schedules/update/{id}',
    [DoctorScheduleController::class, 'update']
);

Route::delete(
    '/doctor-schedules/delete/{id}',
    [DoctorScheduleController::class, 'destroy']
);
// Internal staff-only booking tool -- wrapped in auth since none of these
// routes were previously gated at all (confirmed live: reachable by any
// unauthenticated request with a CSRF token, several leaking patient PII).
Route::middleware(['auth'])->group(function () {

    Route::post(
        '/doctor-appointments/get-doctors-by-specialisation',
        [DoctorAppointmentController::class, 'getDoctorsBySpecialisation']
    );

    Route::post(
        '/doctor-appointments/get-available-days',
        [DoctorAppointmentController::class, 'getAvailableDays']
    );

    Route::resource('doctor-appointments', DoctorAppointmentController::class);

    Route::post(
        'doctor-appointments/get-slots',
        [DoctorAppointmentController::class, 'getAvailableSlots']
    )->name('doctor-appointments.get-slots');
    Route::post(
        '/doctor-appointments/get-total-patients',
        [DoctorAppointmentController::class, 'getTotalPatients']
    );
    Route::post(
        '/doctor-appointments/get-patients-by-mobile',
        [DoctorAppointmentController::class, 'getPatientsByMobile']
    );

    Route::post(
        '/doctor-appointments/get-patient-details',
        [DoctorAppointmentController::class, 'getPatientDetails']
    );

    Route::post(
        '/doctor-appointments/get-patient-appointments',
        [
            DoctorAppointmentController::class,
            'getPatientAppointments'
        ]
    );

    Route::get(
        '/doctor-schedule-exceptions',
        [DoctorScheduleExceptionController::class, 'index']
    );

    Route::post(
        '/doctor-schedule-exceptions/add',
        [DoctorScheduleExceptionController::class, 'store']
    );

    Route::delete(
        '/doctor-schedule-exceptions/delete/{id}',
        [DoctorScheduleExceptionController::class, 'destroy']
    );
});

// Public (no login) patient appointment booking -- embedded from the
// organisation's separate marketing website. Deliberately NOT added to
// config/page_route_map.php so CheckPageAccess lets it through unauthenticated.
Route::prefix('book-appointment')->controller(PublicAppointmentController::class)->group(function () {

    Route::get('/', 'index')->name('public-appointment.index');

    Route::post('/doctors-by-specialisation', 'getDoctorsBySpecialisation')->middleware('throttle:60,1');
    Route::post('/available-days', 'getAvailableDays')->middleware('throttle:60,1');
    Route::post('/available-slots', 'getAvailableSlots')->middleware('throttle:60,1');

    Route::post('/send-otp', 'sendOtp')->middleware('throttle:5,1')->name('public-appointment.send-otp');
    Route::post('/verify-otp', 'verifyOtp')->middleware('throttle:10,1')->name('public-appointment.verify-otp');
    Route::post('/store', 'store')->middleware('throttle:20,1')->name('public-appointment.store');

    Route::get('/confirmation/{appointmentNo}', 'confirmation')->name('public-appointment.confirmation');
});

Route::get('/doctor-schedule.pdf', [App\Http\Controllers\PublicDoctorScheduleController::class, 'pdf'])
    ->middleware('throttle:30,1')
    ->name('public-doctor-schedule.pdf');



Route::get(
    '/doctor-visit-invoice/appointments',
    [DoctorVisitInvoiceController::class, 'getAppointments']
);
Route::get(
    '/doctor-visit-invoice',
    [DoctorVisitInvoiceController::class, 'index']
)->name('doctor-visit-invoices.index');

Route::get(
    '/doctor-visit-invoice/list',
    [DoctorVisitInvoiceController::class, 'getInvoices']
);

Route::post(
    '/doctor-visit-invoice/add',
    [DoctorVisitInvoiceController::class, 'store']
);

Route::post(
    '/doctor-visit-invoice/update/{id}',
    [DoctorVisitInvoiceController::class, 'update']
);

Route::delete(
    '/doctor-visit-invoice/delete/{id}',
    [DoctorVisitInvoiceController::class, 'destroy']
);

Route::get(
    '/doctor-visit-invoice/print/{id}',
    [DoctorVisitInvoiceController::class, 'printInvoice']
)->name('doctor-visit-invoice.print');

Route::get(
    '/doctor-visit-invoice/print-prescription/{id}',
    [DoctorVisitInvoiceController::class, 'printPrescription']
)->name('doctor-visit-invoice.print-prescription');

Route::post(
    '/doctor-visit-invoice/validate-card',
    [DoctorVisitInvoiceController::class, 'validateCard']
);




Route::prefix('diagnostic-invoice')->group(function () {

    Route::get(
        '/',
        [DiagnosticInvoiceController::class, 'index']
    )->name('diagnostic-invoice.index');

    Route::get(
        '/create',
        [DiagnosticInvoiceController::class, 'create']
    )->name('diagnostic-invoice.create');

    Route::post(
        '/store',
        [DiagnosticInvoiceController::class, 'store']
    )->name('diagnostic-invoice.store');

    Route::get(
        '/edit/{id}',
        [DiagnosticInvoiceController::class, 'edit']
    )->name('diagnostic-invoice.edit');

    Route::post(
        '/update/{id}',
        [DiagnosticInvoiceController::class, 'update']
    )->name('diagnostic-invoice.update');

    Route::delete(
        '/delete/{id}',
        [DiagnosticInvoiceController::class, 'destroy']
    )->name('diagnostic-invoice.delete');

    Route::get(
        '/tests/{itemCode}',
        [DiagnosticInvoiceController::class, 'getTests']
    )->name('diagnostic-invoice.tests');

    Route::get(
        '/print/{id}',
        [DiagnosticInvoiceController::class, 'printInvoice']
    )->name('diagnostic-invoice.print');
});
Route::post(
    '/diagnostic-invoice/search-patient',
    [DiagnosticInvoiceController::class, 'searchPatient']
);

Route::get(
    '/diagnostic-invoice/get-patient/{id}',
    [DiagnosticInvoiceController::class, 'getPatient']
);

Route::get(
    '/patient/generate-id',
    [DiagnosticInvoiceController::class, 'generatePatientId']
);

Route::get(
    '/diagnostic-invoice/patient-discount/{patientId}',
    [
        DiagnosticInvoiceController::class,
        'getPatientDiscountEligibility'
    ]
);
Route::get(
    '/diagnostic-invoice/check-member/{patientId}',
    [
        DiagnosticInvoiceController::class,
        'checkEmployeeMember'
    ]
);

Route::get(
    '/diagnostic-invoice/list',
    [DiagnosticInvoiceController::class, 'getInvoices']
);

Route::post(
    '/diagnostic-invoice/new-patient-discount',
    [
        DiagnosticInvoiceController::class,
        'getNewPatientDiscountEligibility'
    ]
);

Route::post(
    '/diagnostic-invoice/save-patient',
    [DiagnosticInvoiceController::class, 'savePatient']
);


Route::post(
    '/diagnostic-invoice/save-patient',
    [DiagnosticInvoiceController::class, 'savePatient']
);

Route::post(
    '/patient/add-mobile',
    [DiagnosticInvoiceController::class, 'addMobileNumber']
);

Route::post(
    '/patient/change-primary-mobile',
    [DiagnosticInvoiceController::class, 'changePrimaryMobile']
);

Route::post(
    '/patient/deactivate',
    [DiagnosticInvoiceController::class, 'deactivatePatient']
);






Route::prefix(
    'doctor-test-payment-master'
)
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/',
            [
                DoctorTestPaymentMasterController::class,
                'index'
            ]
        )->name('doctor-test-payment-master.index');

        /*
        |--------------------------------------------------------------------------
        | LIST
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/list',
            [
                DoctorTestPaymentMasterController::class,
                'list'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | LOAD TESTS
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/tests/{itemCode}',
            [
                DoctorTestPaymentMasterController::class,
                'getTests'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | LOAD DOCTORS BY SPECIALISATION
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/doctors-by-specialisation',
            [
                DoctorTestPaymentMasterController::class,
                'doctorsBySpecialisation'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | SINGLE RECORD
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/show/{id}',
            [
                DoctorTestPaymentMasterController::class,
                'show'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | SAVE / UPDATE
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/store',
            [
                DoctorTestPaymentMasterController::class,
                'store'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */

        Route::delete(
            '/delete/{id}',
            [
                DoctorTestPaymentMasterController::class,
                'destroy'
            ]
        );
    });

Route::get(
    '/diagnostic-invoice/doctor-payments/{itemCodeSub}',
    [DiagnosticInvoiceController::class, 'getDoctorPayments']
);

Route::get(
    '/diagnostic-invoice/doctors-by-specialisation/{specialisation}',
    [DiagnosticInvoiceController::class, 'getDoctorsBySpecialisation']
);

Route::get(
    '/diagnostic-invoice/print/{id}',
    [DiagnosticInvoiceController::class, 'printInvoice']
)->name('diagnostic-invoice.print');

Route::get(
    '/diagnostic-invoice/patient-appointments/{patientId}',
    [DiagnosticInvoiceController::class, 'getPatientAppointments']
);
Route::get(
    '/diagnostic-invoice/patient-doctor-visits/{patientId}',
    [DiagnosticInvoiceController::class, 'getPatientDoctorVisits']
);
Route::post(
    '/diagnostic-invoice/send-whatsapp/{id}',
    [DiagnosticInvoiceController::class, 'sendWhatsapp']
)->name('diagnostic-invoice.send-whatsapp');



Route::get(
    '/doctor-visit-invoice/send-whatsapp/{id}',
    [DoctorVisitInvoiceController::class, 'sendWhatsapp']
)->name('doctor-visit-invoice.send-whatsapp');

Route::get(
    '/doctor-schedule-calendar',
    [DoctorScheduleController::class, 'calendar']
)->name('doctor-schedule.calendar');

Route::get(
    '/doctor-schedule-calendar/events',
    [DoctorScheduleController::class, 'calendarEvents']
)->name('doctor-schedule.calendar.events');

Route::get(
    '/specialisation',
    [SpecialisationController::class, 'index']
)->name('specialisation.index');

Route::get(
    '/specialisation/list',
    [SpecialisationController::class, 'getSpecialisations']
);

Route::post(
    '/specialisation/add',
    [SpecialisationController::class, 'store']
);

Route::post(
    '/specialisation/update/{id}',
    [SpecialisationController::class, 'update']
);

Route::delete(
    '/specialisation/delete/{id}',
    [SpecialisationController::class, 'destroy']
);

Route::get(
    '/specialisation/export/excel',
    [SpecialisationController::class, 'exportExcel']
);

Route::get(
    '/specialisation/export/print',
    [SpecialisationController::class, 'printSpecialisations']
);


Route::prefix('doctor-history')->group(function () {

    Route::get('/', [DoctorHistoryController::class, 'index'])
        ->name('doctor-history.index');

    Route::post('/search', [DoctorHistoryController::class, 'search'])
        ->name('doctor-history.search');

    Route::get('/pdf', [DoctorHistoryController::class, 'printPdf'])
        ->name('doctor-history.pdf');

    Route::get('/excel', [DoctorHistoryController::class, 'exportExcel'])
        ->name('doctor-history.excel');



});



Route::prefix('doctor-payable')->group(function () {

    Route::get(
        '/',
        [DoctorPayableController::class, 'index']
    )->name('doctor-payable.index');

    Route::post(
        '/search',
        [DoctorPayableController::class, 'search']
    )->name('doctor-payable.search');

    Route::get(
        '/pdf',
        [DoctorPayableController::class, 'printPdf']
    )->name('doctor-payable.pdf');

    Route::get(
        '/excel',
        [DoctorPayableController::class, 'exportExcel']
    )->name('doctor-payable.excel');

    Route::get(
        '/print',
        [DoctorPayableController::class, 'printReport']
    )->name('doctor-payable.print');
});

/*
|--------------------------------------------------------------------------
| Doctor Settlement
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('doctor-settlement')
    ->name('doctor-settlement.')
    ->controller(DoctorSettlementController::class)
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Settlement Entry
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/',
            'index'
        )->name('index');

        /*
        |--------------------------------------------------------------------------
        | Outstanding Payables
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/outstanding',
            'getOutstandingPayables'
        )->name('outstanding');

        /*
        |--------------------------------------------------------------------------
        | Settlement Preview
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/preview',
            'previewSettlement'
        )->name('preview');

        /*
        |--------------------------------------------------------------------------
        | Save Settlement
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/store',
            'store'
        )->name('store');

        /*
        |--------------------------------------------------------------------------
        | Settlement Register
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/register',
            'register'
        )->name('register');

        /*
        |--------------------------------------------------------------------------
        | Settlement Status (by user + invoice date)
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settlement-status',
            'getSettlementStatusByUserDate'
        )->name('settlement-status');

        /*
        |--------------------------------------------------------------------------
        | Settlement History
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/history/{payable}',
            'history'
        )->name('history');

        /*
        |--------------------------------------------------------------------------
        | Voucher
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/voucher/{settlement}',
            'printVoucher'
        )->name('voucher');

        /*
        |--------------------------------------------------------------------------
        | PDF
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/pdf/{settlement}',
            'pdfVoucher'
        )->name('pdf');

        /*
        |--------------------------------------------------------------------------
        | WhatsApp
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/whatsapp/{settlement}',
            'sendWhatsapp'
        )->name('whatsapp');

        /*
        |--------------------------------------------------------------------------
        | Cancel Settlement
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/cancel/{settlement}',
            'cancel'
        )->name('cancel');

    });

/*
|--------------------------------------------------------------------------
| Doctor Settlement (by User + Invoice Date)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('doctor-settlement-by-user')
    ->name('doctor-settlement-by-user.')
    ->controller(DoctorSettlementController::class)
    ->group(function () {

        Route::get('/', 'indexByUser')->name('index');

        Route::post('/outstanding', 'getOutstandingPayablesByUserDate')->name('outstanding');

    });

/*
|--------------------------------------------------------------------------
| Doctor Payable (by User) Dashboard
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('doctor-payable-by-user')
    ->name('doctor-payable-by-user.')
    ->controller(DoctorPayableByUserController::class)
    ->group(function () {

        Route::get('/', 'index')->name('index');

        Route::get('/list', 'list')->name('list');

        Route::get('/print', 'print')->name('print');

    });
// Preview before saving

Route::prefix('invoice-item-masters')

    ->middleware('auth')

    ->controller(InvoiceItemMasterController::class)

    ->name('invoice-item-masters.')

    ->group(function () {

        Route::get('/','index')->name('index');

        Route::post('/list','list')->name('list');

        Route::post('/store','store')->name('store');

        Route::get('/{id}','edit')->name('edit');

        Route::put('/{id}','update')->name('update');

        Route::delete('/{id}','destroy')->name('destroy');

        Route::get('/excel','exportExcel')->name('excel');

    });


        Route::get(
            '/invoice-item-details',
            [InvoiceItemDetailController::class, 'index']
        )->name('invoice-item-details.index');

        Route::get(
            '/invoice-item-details/excel',
            [InvoiceItemDetailController::class, 'excel']
        )->name('invoice-item-details.excel');

        Route::post('/invoice-item-details/list',
            [InvoiceItemDetailController::class, 'list']
        )->name('invoice-item-details.list');

        Route::get('/invoice-item-details/master/{item_code}',
            [InvoiceItemDetailController::class, 'getItemMaster']
        )->name('invoice-item-details.master');

        Route::get('/invoice-item-details/components/{item_code}/{excludeId?}',
            [InvoiceItemDetailController::class, 'componentCandidates']
        )->name('invoice-item-details.components');

        Route::post('/invoice-item-details',
            [InvoiceItemDetailController::class, 'store']
        )->name('invoice-item-details.store');

        Route::get(
            '/invoice-item-details/{id}',
            [InvoiceItemDetailController::class, 'edit']
        )
            ->whereNumber('id');

        Route::put(
            '/invoice-item-details/{id}',
            [InvoiceItemDetailController::class, 'update']
        )
            ->whereNumber('id');

        Route::delete(
            '/invoice-item-details/{id}',
            [InvoiceItemDetailController::class, 'destroy']
        )
            ->whereNumber('id');

Route::prefix('equipment-category')->group(function () {

    Route::get(
        '/',
        [EquipmentCategoryController::class, 'index']
    )->name('equipment-category.index');

    Route::get(
        '/list',
        [EquipmentCategoryController::class, 'list']
    );

    Route::post(
        '/store',
        [EquipmentCategoryController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [EquipmentCategoryController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [EquipmentCategoryController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [EquipmentCategoryController::class, 'destroy']
    );

});

// Wrapped in auth -- these AJAX routes were previously unnamed and so
// bypassed CheckPageAccess entirely (same gap fixed for doctor-appointments
// and patient-history), letting an anonymous visitor issue/return/cancel
// rentals and read patient PII.
Route::middleware(['auth'])->prefix('equipment-rental')->group(function () {

    Route::get(
        '/',
        [EquipmentRentalController::class, 'index']
    )->name('equipment-rental.index');

    Route::get(
        '/list',
        [EquipmentRentalController::class, 'list']
    );

    Route::get(
        '/settings',
        [EquipmentRentalController::class, 'settings']
    );

    Route::post(
        '/issue',
        [EquipmentRentalController::class, 'issue']
    );

    Route::post(
        '/return/{id}',
        [EquipmentRentalController::class, 'returnEquipment']
    );

    Route::post(
        '/preview-settlement/{id}',
        [EquipmentRentalController::class, 'previewSettlement']
    );

    Route::delete(
        '/cancel/{id}',
        [EquipmentRentalController::class, 'cancel']
    );

    Route::get(
        '/print/{id}',
        [EquipmentRentalController::class, 'printInvoice']
    );

    Route::post(
        '/send-whatsapp/{id}',
        [EquipmentRentalController::class, 'sendWhatsapp']
    );

});

Route::prefix('ambulance-destination')->group(function () {

    Route::get(
        '/',
        [AmbulanceDestinationController::class, 'index']
    )->name('ambulance-destination.index');

    Route::get(
        '/list',
        [AmbulanceDestinationController::class, 'list']
    );

    Route::post(
        '/store',
        [AmbulanceDestinationController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [AmbulanceDestinationController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [AmbulanceDestinationController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [AmbulanceDestinationController::class, 'destroy']
    );

});

Route::prefix('ambulance-rental')->group(function () {

    Route::get(
        '/',
        [AmbulanceRentalController::class, 'index']
    )->name('ambulance-rental.index');

    Route::get(
        '/list',
        [AmbulanceRentalController::class, 'list']
    );

    Route::get(
        '/settings',
        [AmbulanceRentalController::class, 'settings']
    );

    Route::post(
        '/store',
        [AmbulanceRentalController::class, 'store']
    );

    Route::delete(
        '/cancel/{id}',
        [AmbulanceRentalController::class, 'cancel']
    );

    Route::get(
        '/print/{id}',
        [AmbulanceRentalController::class, 'printInvoice']
    );

    Route::post(
        '/send-whatsapp/{id}',
        [AmbulanceRentalController::class, 'sendWhatsapp']
    );

});

Route::prefix('membership-fee-rate')->group(function () {

    Route::get(
        '/',
        [MembershipFeeRateController::class, 'index']
    )->name('membership-fee-rate.index');

    Route::get(
        '/list',
        [MembershipFeeRateController::class, 'list']
    );

    Route::post(
        '/store',
        [MembershipFeeRateController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [MembershipFeeRateController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [MembershipFeeRateController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [MembershipFeeRateController::class, 'destroy']
    );

});

Route::prefix('membership-fee')->group(function () {

    Route::get(
        '/',
        [MembershipFeeController::class, 'index']
    )->name('membership-fee.index');

    Route::post(
        '/search-member',
        [MembershipFeeController::class, 'searchMember']
    );

    Route::post(
        '/preview-months',
        [MembershipFeeController::class, 'previewMonths']
    );

    Route::post(
        '/store',
        [MembershipFeeController::class, 'store']
    );

    Route::post(
        '/adjust',
        [MembershipFeeController::class, 'adjust']
    );

    Route::get(
        '/history/{userId}',
        [MembershipFeeController::class, 'history']
    );

    Route::get(
        '/print/{id}',
        [MembershipFeeController::class, 'printInvoice']
    );

    Route::get(
        '/send-whatsapp/{id}',
        [MembershipFeeController::class, 'sendWhatsapp']
    );

});

// Wrapped in auth -- search-by-mobile/get-history were previously reachable
// unauthenticated (unnamed routes bypass CheckPageAccess entirely, same gap
// fixed for doctor-appointments), which would have let an anonymous visitor
// both read patient PII and, once update-patient was added, rewrite it.
Route::middleware(['auth'])->prefix('patient-history')->group(function () {

    Route::get(
        '/',
        [PatientHistoryController::class, 'index']
    )->name('patient-history.index');

    Route::post(
        '/search-by-mobile',
        [PatientHistoryController::class, 'searchByMobile']
    );

    Route::post(
        '/get-history',
        [PatientHistoryController::class, 'getHistory']
    );

    Route::post(
        '/update-patient',
        [PatientHistoryController::class, 'updatePatient']
    );

});

/*
|--------------------------------------------------------------------------
| INVENTORY MODULE
|--------------------------------------------------------------------------
*/

Route::prefix('inventory-category')->group(function () {

    Route::get('/', [InventoryCategoryController::class, 'index'])
        ->name('inventory-category.index');

    Route::get('/list', [InventoryCategoryController::class, 'list']);
    Route::post('/store', [InventoryCategoryController::class, 'store']);
    Route::get('/edit/{id}', [InventoryCategoryController::class, 'edit']);
    Route::post('/update/{id}', [InventoryCategoryController::class, 'update']);
    Route::delete('/delete/{id}', [InventoryCategoryController::class, 'destroy']);

});

Route::prefix('inventory-item')->group(function () {

    Route::get('/', [InventoryItemController::class, 'index'])
        ->name('inventory-item.index');

    Route::get('/list', [InventoryItemController::class, 'list']);
    Route::get('/search-by-name', [InventoryItemController::class, 'searchByName']);
    Route::post('/store', [InventoryItemController::class, 'store']);
    Route::get('/edit/{id}', [InventoryItemController::class, 'edit']);
    Route::post('/update/{id}', [InventoryItemController::class, 'update']);
    Route::delete('/delete/{id}', [InventoryItemController::class, 'destroy']);

});

Route::prefix('purchase-order')->group(function () {

    Route::get('/', [PurchaseOrderController::class, 'index'])
        ->name('purchase-order.index');

    Route::get('/list', [PurchaseOrderController::class, 'list']);
    Route::get('/open-orders', [PurchaseOrderController::class, 'openOrders']);
    Route::get('/pending-items/{id}', [PurchaseOrderController::class, 'pendingItems']);
    Route::get('/show/{id}', [PurchaseOrderController::class, 'show']);
    Route::post('/store', [PurchaseOrderController::class, 'store']);
    Route::post('/cancel/{id}', [PurchaseOrderController::class, 'cancel']);

});

Route::prefix('goods-receipt')->group(function () {

    Route::get('/', [GoodsReceiptController::class, 'index'])
        ->name('goods-receipt.index');

    Route::get('/list', [GoodsReceiptController::class, 'list']);
    Route::get('/show/{id}', [GoodsReceiptController::class, 'show']);
    Route::post('/store', [GoodsReceiptController::class, 'store']);

});

Route::prefix('stock-issue')->group(function () {

    Route::get('/', [StockIssueController::class, 'index'])
        ->name('stock-issue.index');

    Route::get('/list', [StockIssueController::class, 'list']);
    Route::get('/show/{id}', [StockIssueController::class, 'show']);
    Route::post('/store', [StockIssueController::class, 'store']);

});

Route::prefix('stock-ledger')->group(function () {

    Route::get('/', [StockLedgerController::class, 'index'])
        ->name('stock-ledger.index');

    Route::get('/summary', [StockLedgerController::class, 'summary']);
    Route::post('/detail', [StockLedgerController::class, 'detail']);
    Route::get('/print-detail', [StockLedgerController::class, 'printDetail']);

});

Route::prefix('purchase-order-report')->group(function () {

    Route::get('/', [PurchaseOrderReportController::class, 'index'])
        ->name('purchase-order-report.index');

    Route::get('/detail', [PurchaseOrderReportController::class, 'detail']);
    Route::get('/print-detail', [PurchaseOrderReportController::class, 'printDetail']);
    Route::get('/summary', [PurchaseOrderReportController::class, 'summary']);
    Route::get('/print-summary', [PurchaseOrderReportController::class, 'printSummary']);

});

Route::prefix('goods-receipt-report')->group(function () {

    Route::get('/', [GoodsReceiptReportController::class, 'index'])
        ->name('goods-receipt-report.index');

    Route::get('/detail', [GoodsReceiptReportController::class, 'detail']);
    Route::get('/print-detail', [GoodsReceiptReportController::class, 'printDetail']);
    Route::get('/summary', [GoodsReceiptReportController::class, 'summary']);
    Route::get('/print-summary', [GoodsReceiptReportController::class, 'printSummary']);

});

Route::prefix('goods-issue-report')->group(function () {

    Route::get('/', [GoodsIssueReportController::class, 'index'])
        ->name('goods-issue-report.index');

    Route::get('/detail', [GoodsIssueReportController::class, 'detail']);
    Route::get('/print-detail', [GoodsIssueReportController::class, 'printDetail']);
    Route::get('/summary', [GoodsIssueReportController::class, 'summary']);
    Route::get('/print-summary', [GoodsIssueReportController::class, 'printSummary']);

});

Route::prefix('stock-as-on-date')->group(function () {

    Route::get('/', [StockAsOnDateController::class, 'index'])
        ->name('stock-as-on-date.index');

    Route::get('/report', [StockAsOnDateController::class, 'report']);
    Route::get('/print', [StockAsOnDateController::class, 'print']);

});

Route::prefix('patient-card')->group(function () {

    Route::get('/', [PatientCardController::class, 'index'])
        ->name('patient-card.index');

    Route::get('/list', [PatientCardController::class, 'list']);
    Route::post('/store', [PatientCardController::class, 'store']);
    Route::get('/edit/{id}', [PatientCardController::class, 'edit']);
    Route::post('/update/{id}', [PatientCardController::class, 'update']);
    Route::delete('/delete/{id}', [PatientCardController::class, 'destroy']);

});

Route::prefix('expenditure-category')->group(function () {

    Route::get('/', [ExpenditureCategoryController::class, 'index'])
        ->name('expenditure-category.index');

    Route::get('/list', [ExpenditureCategoryController::class, 'list']);
    Route::post('/store', [ExpenditureCategoryController::class, 'store']);
    Route::get('/edit/{id}', [ExpenditureCategoryController::class, 'edit']);
    Route::post('/update/{id}', [ExpenditureCategoryController::class, 'update']);
    Route::delete('/delete/{id}', [ExpenditureCategoryController::class, 'destroy']);

});

Route::prefix('expenditure-agency')->group(function () {

    Route::get('/', [ExpenditureAgencyController::class, 'index'])
        ->name('expenditure-agency.index');

    Route::get('/list', [ExpenditureAgencyController::class, 'list']);
    Route::post('/store', [ExpenditureAgencyController::class, 'store']);
    Route::get('/edit/{id}', [ExpenditureAgencyController::class, 'edit']);
    Route::post('/update/{id}', [ExpenditureAgencyController::class, 'update']);
    Route::delete('/delete/{id}', [ExpenditureAgencyController::class, 'destroy']);

});

Route::prefix('expenditure')->group(function () {

    Route::get('/', [ExpenditureController::class, 'index'])
        ->name('expenditure.index');

    Route::get('/list', [ExpenditureController::class, 'list']);
    Route::post('/store', [ExpenditureController::class, 'store']);
    Route::get('/edit/{id}', [ExpenditureController::class, 'edit']);
    Route::post('/update/{id}', [ExpenditureController::class, 'update']);
    Route::delete('/delete/{id}', [ExpenditureController::class, 'destroy']);
    Route::get('/print/{id}', [ExpenditureController::class, 'printVoucher']);

});

Route::prefix('income-category')->group(function () {

    Route::get('/', [IncomeCategoryController::class, 'index'])
        ->name('income-category.index');

    Route::get('/list', [IncomeCategoryController::class, 'list']);
    Route::post('/store', [IncomeCategoryController::class, 'store']);
    Route::get('/edit/{id}', [IncomeCategoryController::class, 'edit']);
    Route::post('/update/{id}', [IncomeCategoryController::class, 'update']);
    Route::delete('/delete/{id}', [IncomeCategoryController::class, 'destroy']);

});

Route::prefix('income')->group(function () {

    Route::get('/', [IncomeController::class, 'index'])
        ->name('income.index');

    Route::get('/list', [IncomeController::class, 'list']);
    Route::post('/store', [IncomeController::class, 'store']);
    Route::delete('/cancel/{id}', [IncomeController::class, 'cancel']);
    Route::get('/print/{id}', [IncomeController::class, 'printReceipt']);

});

Route::prefix('invoice-cancellation')->group(function () {

    Route::get('/', [InvoiceCancellationController::class, 'index'])
        ->name('invoice-cancellation.index');

    Route::get('/search', [InvoiceCancellationController::class, 'search']);
    Route::post('/cancel', [InvoiceCancellationController::class, 'cancel']);

});

Route::prefix('cancellation-permission')->group(function () {

    Route::get('/', [CancellationPermissionController::class, 'index'])
        ->name('cancellation-permission.index');

    Route::get('/search', [CancellationPermissionController::class, 'search']);
    Route::post('/grant', [CancellationPermissionController::class, 'grant']);

});

Route::prefix('activity-log')->group(function () {

    Route::get('/', [ActivityLogController::class, 'index'])
        ->name('activity-log.index');

    Route::get('/login-activity', [ActivityLogController::class, 'loginActivity']);
    Route::get('/audit-activity', [ActivityLogController::class, 'auditActivity']);

});

Route::prefix('audit-logs')->group(function () {

    Route::get('/', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

    Route::get('/list', [AuditLogController::class, 'list']);
    Route::get('/show/{id}', [AuditLogController::class, 'show']);

});

Route::prefix('membership-fee-status')->group(function () {

    Route::get('/', [MembershipFeeStatusController::class, 'index'])
        ->name('membership-fee-status.index');

    Route::get('/list', [MembershipFeeStatusController::class, 'list']);

});

Route::prefix('cloud-backup')->group(function () {

    Route::get('/', [CloudBackupController::class, 'index'])
        ->name('cloud-backup.index');

    Route::post('/test-connection', [CloudBackupController::class, 'testConnection'])
        ->name('cloud-backup.test-connection');

    Route::post('/run', [CloudBackupController::class, 'backup'])
        ->name('cloud-backup.run');

    Route::get('/list', [CloudBackupController::class, 'list'])
        ->name('cloud-backup.list');

    Route::get('/download/{filename}', [CloudBackupController::class, 'download'])
        ->name('cloud-backup.download');

    Route::delete('/delete/{filename}', [CloudBackupController::class, 'destroy'])
        ->name('cloud-backup.destroy');

});

Route::prefix('maintenance-mode')->group(function () {

    Route::get('/', [MaintenanceController::class, 'index'])
        ->name('maintenance-mode.index');

    Route::post('/toggle', [MaintenanceController::class, 'toggle'])
        ->name('maintenance-mode.toggle');

});

Route::prefix('doctor-consultation-list')->group(function () {

    Route::get('/', [DoctorConsultationListController::class, 'index'])
        ->name('doctor-consultation-list.index');

    Route::get('/list', [DoctorConsultationListController::class, 'list']);
    Route::get('/print', [DoctorConsultationListController::class, 'print']);

});

Route::prefix('doctor-visit-payment-report')->group(function () {

    Route::get('/', [DoctorVisitPaymentReportController::class, 'index'])
        ->name('doctor-visit-payment-report.index');

    Route::get('/list', [DoctorVisitPaymentReportController::class, 'list']);
    Route::get('/print', [DoctorVisitPaymentReportController::class, 'print']);

});

Route::prefix('doctor-test-payment-report')->group(function () {

    Route::get('/', [DoctorTestPaymentReportController::class, 'index'])
        ->name('doctor-test-payment-report.index');

    Route::get('/list', [DoctorTestPaymentReportController::class, 'list']);
    Route::get('/print', [DoctorTestPaymentReportController::class, 'print']);

});

Route::prefix('doctor-payment-report')->group(function () {

    Route::get('/', [DoctorPaymentReportController::class, 'index'])
        ->name('doctor-payment-report.index');

    Route::get('/list', [DoctorPaymentReportController::class, 'list']);
    Route::get('/print', [DoctorPaymentReportController::class, 'print']);

});

Route::prefix('doctor-payable-non-cash-report')->group(function () {

    Route::get('/', [DoctorPayableNonCashReportController::class, 'index'])
        ->name('doctor-payable-non-cash-report.index');

    Route::get('/detail', [DoctorPayableNonCashReportController::class, 'detail']);
    Route::get('/print-detail', [DoctorPayableNonCashReportController::class, 'printDetail']);
    Route::get('/summary', [DoctorPayableNonCashReportController::class, 'summary']);
    Route::get('/print-summary', [DoctorPayableNonCashReportController::class, 'printSummary']);

});

Route::prefix('doctor-wise-payment-report')->group(function () {

    Route::get('/', [DoctorWisePaymentReportController::class, 'index'])
        ->name('doctor-wise-payment-report.index');

    Route::get('/detail', [DoctorWisePaymentReportController::class, 'detail']);
    Route::get('/print-detail', [DoctorWisePaymentReportController::class, 'printDetail']);
    Route::get('/summary', [DoctorWisePaymentReportController::class, 'summary']);
    Route::get('/print-summary', [DoctorWisePaymentReportController::class, 'printSummary']);

});

// Wrapped in auth -- same recurring gap fixed for several other route
// groups this session: unnamed sub-routes bypass CheckPageAccess entirely,
// exposing daily cash/refund/doctor-payment transaction data to anyone.
Route::middleware(['auth'])->prefix('daily-transaction-report')->group(function () {

    Route::get('/', [DailyTransactionReportController::class, 'index'])
        ->name('daily-transaction-report.index');

    Route::get('/summary', [DailyTransactionReportController::class, 'summary']);
    Route::get('/print-summary', [DailyTransactionReportController::class, 'printSummary']);
    Route::get('/detail', [DailyTransactionReportController::class, 'detail']);
    Route::get('/print-detail', [DailyTransactionReportController::class, 'printDetail']);

});

Route::middleware(['auth'])->prefix('whatsapp-message-report')->group(function () {

    Route::get('/', [WhatsappMessageReportController::class, 'index'])
        ->name('whatsapp-message-report.index');

    Route::get('/summary', [WhatsappMessageReportController::class, 'summary'])
        ->name('whatsapp-message-report.summary');

    Route::get('/print-summary', [WhatsappMessageReportController::class, 'printSummary'])
        ->name('whatsapp-message-report.print-summary');

    Route::get('/detail', [WhatsappMessageReportController::class, 'detail'])
        ->name('whatsapp-message-report.detail');

    Route::get('/print-detail', [WhatsappMessageReportController::class, 'printDetail'])
        ->name('whatsapp-message-report.print-detail');

});

Route::prefix('item-wise-report')->group(function () {

    Route::get('/', [ItemWiseReportController::class, 'index'])
        ->name('item-wise-report.index');

    Route::get('/detail', [ItemWiseReportController::class, 'detail']);
    Route::get('/print-detail', [ItemWiseReportController::class, 'printDetail']);
    Route::get('/summary', [ItemWiseReportController::class, 'summary']);
    Route::get('/print-summary', [ItemWiseReportController::class, 'printSummary']);

});

Route::prefix('item-wise-summary-report')->group(function () {

    Route::get('/', [ItemWiseSummaryReportController::class, 'index'])
        ->name('item-wise-summary-report.index');

    Route::get('/summary', [ItemWiseSummaryReportController::class, 'summary']);
    Route::get('/print-summary', [ItemWiseSummaryReportController::class, 'printSummary']);
    Route::get('/detail', [ItemWiseSummaryReportController::class, 'detail']);
    Route::get('/print-detail', [ItemWiseSummaryReportController::class, 'printDetail']);

});

Route::prefix('monthly-reconciliation-report')->group(function () {

    Route::get('/', [MonthlyReconciliationReportController::class, 'index'])
        ->name('monthly-reconciliation-report.index');

    Route::get('/report', [MonthlyReconciliationReportController::class, 'report']);
    Route::get('/print', [MonthlyReconciliationReportController::class, 'print']);

});

Route::prefix('user-invoice-report')->group(function () {

    Route::get('/', [UserInvoiceReportController::class, 'index'])
        ->name('user-invoice-report.index');

    Route::get('/list', [UserInvoiceReportController::class, 'list']);
    Route::get('/print', [UserInvoiceReportController::class, 'print']);

});

Route::prefix('all-invoices-report')->group(function () {

    Route::get('/', [AllInvoicesReportController::class, 'index'])
        ->name('all-invoices-report.index');

    Route::get('/list', [AllInvoicesReportController::class, 'list']);
    Route::get('/counts', [AllInvoicesReportController::class, 'counts']);

});

// Wrapped in auth -- /list and /print were unnamed and so bypassed
// CheckPageAccess entirely (same recurring gap fixed for
// doctor-appointments/patient-history/equipment-rental/test-result-entry),
// exposing per-employee cash collection/refund/doctor-payment figures to
// anyone unauthenticated.
Route::middleware(['auth'])->prefix('cash-submission-report')->group(function () {

    Route::get('/', [CashSubmissionReportController::class, 'index'])
        ->name('cash-submission-report.index');

    Route::get('/list', [CashSubmissionReportController::class, 'list']);
    Route::get('/print', [CashSubmissionReportController::class, 'print']);

    Route::get('/summary', [CashSubmissionReportController::class, 'summary']);
    Route::get('/print-summary', [CashSubmissionReportController::class, 'printSummary']);

});

Route::middleware(['auth'])->prefix('employee-performance')->group(function () {

    Route::get('/', [EmployeePerformanceController::class, 'index'])
        ->name('employee-performance.index');

    Route::get('/list', [EmployeePerformanceController::class, 'list'])
        ->name('employee-performance.list');

    Route::get('/detail', [EmployeePerformanceController::class, 'detail'])
        ->name('employee-performance.detail');

    Route::get('/print-all', [EmployeePerformanceController::class, 'printAll'])
        ->name('employee-performance.print-all');

    Route::get('/print-detail', [EmployeePerformanceController::class, 'printDetail'])
        ->name('employee-performance.print-detail');

});

Route::prefix('reporting-group')->group(function () {

    Route::get('/', [ReportingGroupController::class, 'index'])
        ->name('reporting-group.index');

    Route::get('/list', [ReportingGroupController::class, 'list']);
    Route::post('/store', [ReportingGroupController::class, 'store']);
    Route::get('/edit/{id}', [ReportingGroupController::class, 'edit']);
    Route::post('/update/{id}', [ReportingGroupController::class, 'update']);
    Route::delete('/delete/{id}', [ReportingGroupController::class, 'destroy']);

});

Route::prefix('users')->group(function () {

    Route::get(
        '/',
        [UserController::class, 'index']
    )->name('users.index');

    Route::get(
        '/list',
        [UserController::class, 'list']
    );

    Route::post(
        '/store',
        [UserController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [UserController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [UserController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [UserController::class, 'destroy']
    );

});

Route::prefix('role-page-access')->group(function () {

    Route::get(
        '/',
        [RolePageAccessController::class, 'index']
    )->name('role-page-access.index');

    Route::get(
        '/roles',
        [RolePageAccessController::class, 'roles']
    );

    Route::post(
        '/roles',
        [RolePageAccessController::class, 'createRole']
    );

    Route::get(
        '/{role}',
        [RolePageAccessController::class, 'get']
    );

    Route::post(
        '/{role}',
        [RolePageAccessController::class, 'update']
    );

});

Route::prefix('test-group-master')->group(function () {

    Route::get(
        '/',
        [TestGroupMasterController::class, 'index']
    )->name('test-group-master.index');

    Route::get(
        '/list',
        [TestGroupMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [TestGroupMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [TestGroupMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [TestGroupMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [TestGroupMasterController::class, 'destroy']
    );

});

Route::prefix('usg-report-template')->group(function () {

    Route::get(
        '/',
        [UsgReportTemplateController::class, 'index']
    )->name('usg-report-template.index');

    Route::get(
        '/list',
        [UsgReportTemplateController::class, 'list']
    );

    Route::post(
        '/store',
        [UsgReportTemplateController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [UsgReportTemplateController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [UsgReportTemplateController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [UsgReportTemplateController::class, 'destroy']
    );

    // Lightweight lookup used by the USG report entry screen's template
    // picker -- deliberately separate from the paginated admin /list above.
    Route::get(
        '/for-study/{itemCodeSub}',
        [UsgReportTemplateController::class, 'forStudy']
    );

    // Lightweight, unpaginated listing used by the "Copy from existing
    // template" picker in the Add Template modal.
    Route::get(
        '/all',
        [UsgReportTemplateController::class, 'all']
    );

});

Route::prefix('test-report-template')->group(function () {

    Route::get(
        '/',
        [TestReportTemplateController::class, 'index']
    )->name('test-report-template.index');

    Route::get(
        '/list',
        [TestReportTemplateController::class, 'list']
    );

    Route::post(
        '/store',
        [TestReportTemplateController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [TestReportTemplateController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [TestReportTemplateController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [TestReportTemplateController::class, 'destroy']
    );

    // Lightweight lookup used by the Test Result Entry screen's bundled
    // template picker -- deliberately separate from the paginated admin /list above.
    Route::get(
        '/for-test/{itemCodeSub}',
        [TestReportTemplateController::class, 'forTest']
    );

});

Route::prefix('sample-master')->group(function () {

    Route::get(
        '/',
        [SampleMasterController::class, 'index']
    )->name('sample-master.index');

    Route::get(
        '/list',
        [SampleMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [SampleMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [SampleMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [SampleMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [SampleMasterController::class, 'destroy']
    );

});

Route::prefix('uom-master')->group(function () {

    Route::get(
        '/',
        [UomMasterController::class, 'index']
    )->name('uom-master.index');

    Route::get(
        '/list',
        [UomMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [UomMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [UomMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [UomMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [UomMasterController::class, 'destroy']
    );

});

Route::middleware(['auth'])->prefix('diagnostic-test-additional-info')->group(function () {

    Route::get(
        '/',
        [DiagnosticTestAdditionalInfoController::class, 'index']
    )->name('diagnostic-test-additional-info.index');

    Route::post(
        '/store',
        [DiagnosticTestAdditionalInfoController::class, 'store']
    );

    Route::post(
        '/update/{id}',
        [DiagnosticTestAdditionalInfoController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [DiagnosticTestAdditionalInfoController::class, 'destroy']
    );

    Route::post(
        '/move/{id}/{direction}',
        [DiagnosticTestAdditionalInfoController::class, 'move']
    );
});

Route::prefix('test-extra-field-type')->group(function () {

    Route::get(
        '/',
        [TestExtraFieldTypeController::class, 'index']
    )->name('test-extra-field-type.index');

    Route::get(
        '/list',
        [TestExtraFieldTypeController::class, 'list']
    );

    Route::post(
        '/store',
        [TestExtraFieldTypeController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [TestExtraFieldTypeController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [TestExtraFieldTypeController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [TestExtraFieldTypeController::class, 'destroy']
    );

});

Route::prefix('instrument-master')->group(function () {

    Route::get(
        '/',
        [InstrumentMasterController::class, 'index']
    )->name('instrument-master.index');

    Route::get(
        '/list',
        [InstrumentMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [InstrumentMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [InstrumentMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [InstrumentMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [InstrumentMasterController::class, 'destroy']
    );

});

Route::prefix('kit-master')->group(function () {

    Route::get(
        '/',
        [KitMasterController::class, 'index']
    )->name('kit-master.index');

    Route::get(
        '/list',
        [KitMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [KitMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [KitMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [KitMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [KitMasterController::class, 'destroy']
    );

});

Route::prefix('note-master')->group(function () {

    Route::get(
        '/',
        [NoteMasterController::class, 'index']
    )->name('note-master.index');

    Route::get(
        '/list',
        [NoteMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [NoteMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [NoteMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [NoteMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [NoteMasterController::class, 'destroy']
    );

});

Route::prefix('remarks-master')->group(function () {

    Route::get(
        '/',
        [RemarksMasterController::class, 'index']
    )->name('remarks-master.index');

    Route::get(
        '/list',
        [RemarksMasterController::class, 'list']
    );

    Route::get(
        '/active-list',
        [RemarksMasterController::class, 'activeList']
    );

    Route::post(
        '/store',
        [RemarksMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [RemarksMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [RemarksMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [RemarksMasterController::class, 'destroy']
    );

});

Route::prefix('microscopy-master')->group(function () {

    Route::get(
        '/',
        [MicroscopyMasterController::class, 'index']
    )->name('microscopy-master.index');

    Route::get(
        '/list',
        [MicroscopyMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [MicroscopyMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [MicroscopyMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [MicroscopyMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [MicroscopyMasterController::class, 'destroy']
    );

});

Route::prefix('impression-master')->group(function () {

    Route::get(
        '/',
        [ImpressionMasterController::class, 'index']
    )->name('impression-master.index');

    Route::get(
        '/list',
        [ImpressionMasterController::class, 'list']
    );

    Route::post(
        '/store',
        [ImpressionMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [ImpressionMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [ImpressionMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [ImpressionMasterController::class, 'destroy']
    );

});

Route::prefix('detail-range-master')->group(function () {

    Route::get(
        '/',
        [DetailRangeMasterController::class, 'index']
    )->name('detail-range-master.index');

    Route::get(
        '/list',
        [DetailRangeMasterController::class, 'list']
    );

    Route::get(
        '/options',
        [DetailRangeMasterController::class, 'options']
    );

    Route::post(
        '/store',
        [DetailRangeMasterController::class, 'store']
    );

    Route::get(
        '/edit/{id}',
        [DetailRangeMasterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [DetailRangeMasterController::class, 'update']
    );

    Route::delete(
        '/delete/{id}',
        [DetailRangeMasterController::class, 'destroy']
    );

});

Route::prefix('test-parameter')->group(function () {

    Route::get(
        '/',
        [TestParameterController::class, 'index']
    )->name('test-parameter.index');

    Route::get(
        '/list/{itemCode}',
        [TestParameterController::class, 'list']
    );

    Route::get(
        '/edit/{id}',
        [TestParameterController::class, 'edit']
    );

    Route::post(
        '/update/{id}',
        [TestParameterController::class, 'update']
    );

    Route::get(
        '/analytes/{invoiceItemDetailId}',
        [TestParameterController::class, 'analyteList']
    );

    Route::post(
        '/analytes/{invoiceItemDetailId}/store',
        [TestParameterController::class, 'analyteStore']
    );

    Route::post(
        '/analytes/update/{id}',
        [TestParameterController::class, 'analyteUpdate']
    );

    Route::delete(
        '/analytes/delete/{id}',
        [TestParameterController::class, 'analyteDestroy']
    );

    Route::get(
        '/sub-groups/{invoiceItemDetailId}',
        [TestParameterController::class, 'subGroupList']
    );

    Route::post(
        '/sub-groups/{invoiceItemDetailId}/store',
        [TestParameterController::class, 'subGroupStore']
    );

    Route::post(
        '/sub-groups/update/{id}',
        [TestParameterController::class, 'subGroupUpdate']
    );

    Route::delete(
        '/sub-groups/delete/{id}',
        [TestParameterController::class, 'subGroupDestroy']
    );

    Route::post(
        '/analytes/{targetInvoiceItemDetailId}/copy',
        [TestParameterController::class, 'copyAnalytes']
    );

});

// Wrapped in auth -- these routes were named but not present in
// page_route_map.php, so CheckPageAccess let them through unauthenticated
// (same gap fixed for doctor-appointments/patient-history/equipment-rental
// -- naming a route doesn't protect it, only listing it in that map does).
Route::middleware(['auth'])->prefix('test-result-entry')->group(function () {

    Route::get(
        '/',
        [TestResultEntryController::class, 'index']
    )->name('test-result-entry.index');

    Route::get(
        '/list',
        [TestResultEntryController::class, 'list']
    )->name('test-result-entry.list');

    Route::post(
        '/search',
        [TestResultEntryController::class, 'search']
    )->name('test-result-entry.search');

    Route::post(
        '/save',
        [TestResultEntryController::class, 'store']
    )->name('test-result-entry.save');

    Route::delete(
        '/delete/{id}',
        [TestResultEntryController::class, 'destroy']
    )->name('test-result-entry.destroy');

    Route::get(
        '/print/{id}',
        [TestResultEntryController::class, 'printReport']
    )->name('test-result-entry.print');

    Route::post(
        '/send-whatsapp/{id}',
        [TestResultEntryController::class, 'sendWhatsapp']
    )->name('test-result-entry.send-whatsapp');

    Route::post(
        '/confirm',
        [TestResultEntryController::class, 'confirm']
    )->name('test-result-entry.confirm');

    Route::post(
        '/save-extra',
        [TestResultEntryController::class, 'saveExtraValue']
    )->name('test-result-entry.save-extra');

    Route::delete(
        '/delete-extra/{id}',
        [TestResultEntryController::class, 'destroyExtraValue']
    )->name('test-result-entry.delete-extra');

});

// USG's own narrative-report module (Clinical History/Findings/Impression,
// one report per billed line) -- deliberately separate from
// TestResultEntryController's tabular Pathology report above; see
// UsgReportController's class doc-comment.
Route::middleware(['auth'])->prefix('usg-report')->group(function () {

    Route::get(
        '/',
        [UsgReportController::class, 'index']
    )->name('usg-report.index');

    Route::get(
        '/list',
        [UsgReportController::class, 'list']
    )->name('usg-report.list');

    Route::post(
        '/search',
        [UsgReportController::class, 'search']
    )->name('usg-report.search');

    Route::post(
        '/save',
        [UsgReportController::class, 'store']
    )->name('usg-report.save');

    Route::post(
        '/preview',
        [UsgReportController::class, 'preview']
    )->name('usg-report.preview');

    Route::post(
        '/confirm',
        [UsgReportController::class, 'confirm']
    )->name('usg-report.confirm');

    Route::get(
        '/print/{id}',
        [UsgReportController::class, 'printReport']
    )->name('usg-report.print');

    Route::post(
        '/send-whatsapp/{id}',
        [UsgReportController::class, 'sendWhatsapp']
    )->name('usg-report.send-whatsapp');

});

// Narrative-report module for every Non-Pathology category that has no
// structured parameter grid, except USG (own module above). No index/list
// routes -- reached only through Test Result Entry's existing Non-Pathology
// tab; see NonPathologyReportController's class doc-comment.
Route::middleware(['auth'])->prefix('non-pathology-report')->group(function () {

    Route::post(
        '/search',
        [NonPathologyReportController::class, 'search']
    )->name('non-pathology-report.search');

    Route::post(
        '/save',
        [NonPathologyReportController::class, 'store']
    )->name('non-pathology-report.save');

    Route::post(
        '/confirm',
        [NonPathologyReportController::class, 'confirm']
    )->name('non-pathology-report.confirm');

    Route::get(
        '/print/{id}',
        [NonPathologyReportController::class, 'printReport']
    )->name('non-pathology-report.print');

    Route::post(
        '/send-whatsapp/{id}',
        [NonPathologyReportController::class, 'sendWhatsapp']
    )->name('non-pathology-report.send-whatsapp');

});

Route::prefix('diagnostic-test-report')->group(function () {

    Route::get(
        '/',
        [DiagnosticTestReportController::class, 'index']
    )->name('diagnostic-test-report.index');

    Route::post(
        '/search',
        [DiagnosticTestReportController::class, 'search']
    )->name('diagnostic-test-report.search');

    Route::get(
        '/print',
        [DiagnosticTestReportController::class, 'printGroup']
    )->name('diagnostic-test-report.print');

});

Route::middleware(['auth'])->prefix('test-report-dashboard')->group(function () {

    Route::get(
        '/',
        [TestReportDashboardController::class, 'index']
    )->name('test-report-dashboard.index');

    Route::get(
        '/list',
        [TestReportDashboardController::class, 'list']
    )->name('test-report-dashboard.list');

    Route::post(
        '/toggle-delivered/{id}',
        [TestReportDashboardController::class, 'toggleDelivered']
    )->name('test-report-dashboard.toggle-delivered');

});








Route::get('{any}', [App\Http\Controllers\HomeController::class, 'index'])
    ->where('any', '.*')
    ->name('index');
