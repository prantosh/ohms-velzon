<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientMobileNumber;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Services\WatiService;
use App\Models\DoctorPayable;
use App\Models\Doctor;
use App\Models\InvoiceItemDetail;
use App\Models\User;
use App\Models\WhatsappAutoSendSetting;
use App\Mail\DiscountApprovedMail;
use App\Services\AuditService;
use App\Services\CashInHandService;
use App\Services\PatientIdentityGuard;
use App\Services\PaymentReversalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
class DiagnosticInvoiceController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_INVOICE';
    private const PATIENT_MODULE_CODE = 'PATIENT';

    /**
     * Above this many billable test rows, the invoice PDF falls back to
     * the original (STANDARD) content layout instead of the compacted
     * SHORT one.
     */
    private const PDF_SHORT_MAX_TESTS = 4;

    /**
     * The page itself stays standard A4 either way -- SHORT invoices
     * print on ordinary A4 paper, not a custom/half-sized sheet, so no
     * special printer paper-tray setup is ever needed. What changes for
     * SHORT is only the CONTENT: apps-diagnostic-invoice-pdf.blade.php
     * compacts it (7px font, 1-2px cell padding, tight margins) so it
     * naturally ends well within the top half of the A4 page -- html
     * content height is independent of the page's total height, so it
     * lands in the top ~420.94pt (half of A4's 841.89pt height) and
     * leaves the rest of the sheet blank. Empirically verified: the
     * worst case within the 6-test budget (6 test rows, all 3 payment
     * phases populated -- initial + phase 1 + phase 2 -- with long
     * wrapping test descriptions) fits in well under that half-height
     * budget with room to spare.
     */

    private PatientIdentityGuard $identityGuard;

    public function __construct(PatientIdentityGuard $identityGuard)
    {
        $this->identityGuard = $identityGuard;
    }

    /**
    * A patient qualifies for the member-tier discount if a Member,
    * Supervisor, or Employee user (MEMBER_TIER_ROLES convention, see
    * UserController) shares the
     * patient's mobile number and the patient's name matches either that
     * user's own name or one of their registered family members
     * (family_member_1/2/3 -- see the migration comment on that column).
     */
    private function resolveMemberEligibility(string $mobileNo, string $patientName): bool
    {
        $normalizedName = strtolower(trim($patientName));

        if ($normalizedName === '') {
            return false;
        }

        return User::where('mobile_no', trim($mobileNo))
            ->whereIn('role', ['Member', 'Supervisor', 'Employee'])
            ->get()
            ->contains(function ($u) use ($normalizedName) {
                return in_array($normalizedName, array_filter([
                    strtolower(trim($u->name)),
                    strtolower(trim($u->family_member_1 ?? '')),
                    strtolower(trim($u->family_member_2 ?? '')),
                    strtolower(trim($u->family_member_3 ?? '')),
                ]), true);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-diagnostic-invoice');
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PAGE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        $categories =
            DB::table('invoice_item_masters')
                ->where('status', 1)
                ->where('item_type', '=', 'LAB')
                ->orderBy('item_name')
                ->get();

        $discountApprovers =
            DB::table('users')
                ->where('authorised', 'YES')
                ->orderBy('name')
                ->get();

        return view(
            'apps-diagnostic-invoice-create',
            compact(
                'categories',
                'discountApprovers'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT PAGE
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $invoice =
            Invoice::findOrFail($id);

        $categories =
            DB::table('invoice_item_masters')
                ->where('status', 1)
                ->where('item_code', '<>', 'DOC001')
                ->orderBy('item_name')
                ->get();

        $tests =
            DB::table('invoice_details as d')
                ->leftJoin('doctors', 'd.doctor_id', '=', 'doctors.id')
                ->where('d.invoice_no', $invoice->invoice_no)
                ->select(
                    'd.*',
                    DB::raw("
                        CASE
                            WHEN d.doctor_id = 999 THEN 'ALL DOCTORS'
                            ELSE doctors.doctor_name
                        END AS doctor_name
                    ")
                )
                ->get();

        return view(
            'apps-diagnostic-invoice-edit',
            compact(
                'invoice',
                'categories',
                'tests'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function getInvoices(Request $request)
    {
        try {

            $category =
                $request->get('category', 'PATHOLOGY') === 'NON_PATHOLOGY'
                    ? 'NON_PATHOLOGY'
                    : 'PATHOLOGY';

            $allowedDays = [3, 7, 15, 30, 60];

            $days = (int) $request->get('days', 3);

            if (!in_array($days, $allowedDays)) {
                $days = 3;
            }

            $invoices =
                Invoice::where(
                    'invoice_type',
                    'DIAGNOSTIC'
                )

                    ->where(
                        'invoice_category',
                        $category
                    )

                    ->where(
                        'created_at',
                        '>=',
                        now()->subDays($days - 1)->startOfDay()
                    )

                    ->orderBy('id', 'desc')

                    ->get();

            $protectedUsers = $this->identityGuard->protectedUsersForMobiles(
                $invoices->pluck('patient_mobile_no')
            );

            $invoices->transform(function ($row) use ($protectedUsers) {

                $row->invoice_date = $row->invoice_date
                    ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                    : null;

                $row->test_date = $row->test_date
                    ? \Carbon\Carbon::parse($row->test_date)->format('d-m-Y')
                    : null;

                // Quick typo-fix eligibility -- see PatientIdentityGuard.
                // Skipped for cancelled rows, which show no action buttons.
                $row->can_edit_patient_name = $row->cancelled !== 'Y'
                    && !$this->identityGuard->isProtected($row->patient_mobile_no, $row->patient_name, $protectedUsers);

                return $row;
            });

            return response()->json([

                'status' => true,

                'data' => $invoices
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);
        }
    }
    public function checkEmployeeMember($patientId)
    {
        $exists =
            DB::table('users')
                ->where(
                    'patient_id',
                    $patientId
                )
                ->exists();

        return response()->json([
            'status' => true,
            'is_member' => $exists
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | LOAD TESTS
    |--------------------------------------------------------------------------
    */

    public function getTests($itemCode)
    {
        $query = InvoiceItemDetail::where('item_code', $itemCode)
            ->where('status', 'Y');

        // Manually priced categories intentionally use a zero master rate;
        // their amount is entered during invoice creation. For regular
        // categories, zero-rated items are report-only and must not be
        // offered as individually selectable invoice lines.
        if (!$this->isManualDiagnosticItem($itemCode)) {
            $query->where('rate', '<>', 0);
        }

        $tests = $query
            ->orderBy('item_description_sub')
            ->get([
                'id',
                'item_code_sub',
                'item_description_sub',
                'rate',
                'is_package',

                'discount_percent',
                'discount_other_lab',

                'member_discount',
                'discount_member_other_lab',
                'discount_senior_citizen',
            ]);

        $tests = $tests->map(function ($test) {

            $test->is_package = (bool) $test->is_package;

            $test->components = $test->is_package
                ? $test->packageComponents()
                    ->get()
                    ->pluck('component')
                    ->map(fn ($component) => [
                        'item_code_sub' => $component->item_code_sub,
                        'item_description_sub' => $component->item_description_sub,
                        'rate' => $component->rate,
                        'discount_percent' => $component->discount_percent,
                        'discount_other_lab' => $component->discount_other_lab,
                        'member_discount' => $component->member_discount,
                        'discount_member_other_lab' => $component->discount_member_other_lab,
                        'discount_senior_citizen' => $component->discount_senior_citizen,
                    ])
                : [];

            return $test;
        });

        return response()->json($tests);
    }


    private function generatePayableNo()
    {
        $prefix = \App\Support\OfflineMode::prefix('PAY/') . now()->format('my') . '/';

        $last = DoctorPayable::where('payable_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('payable_no');

        if ($last) {
            $serial = (int) substr($last, -4) + 1;
        } else {
            $serial = 1;
        }

        return $prefix . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Mirrors EquipmentRentalController/AmbulanceRentalController's discount
     * approval notification -- reuses the same shared DiscountApprovedMail.
     * Diagnostic invoices can have a different manual discount + approver
     * per test line (unlike those single-line invoice types), so this is
     * called once per discounted test row rather than once per invoice.
     */
    private function sendDiscountApprovalEmail(
        $approverId,
        string $invoiceNo,
        string $patientName,
        float $discountAmount,
        ?string $testName,
        ?string $remarks
    ): void {

        try {

            $approver = DB::table('users')->where('id', $approverId)->first();

            if (!$approver || !$approver->email) {
                return;
            }

            $noteParts = array_filter([$testName, $remarks]);

            Mail::to($approver->email)->send(new DiscountApprovedMail(
                $approver->name,
                $invoiceNo,
                $patientName,
                $discountAmount,
                $noteParts ? implode(' - ', $noteParts) : null,
                optional(Auth::user())->name
            ));

        } catch (\Exception $e) {

            Log::error('Discount approval email failed: ' . $e->getMessage());
        }
    }
    public function getDoctorPayments($itemCodeSub)
    {
        $rows = DB::table('doctor_test_payment_masters as p')
            ->leftJoin(
                'doctors as d',
                'p.doctor_id',
                '=',
                'd.id'
            )
            ->where('p.item_code_sub', $itemCodeSub)
            ->where('p.status', 'A')
            ->select(
                'p.doctor_id',
                'p.payment_value',
                DB::raw("
                CASE
                    WHEN p.doctor_id = 999
                    THEN 'ALL DOCTORS'
                    ELSE d.doctor_name
                END AS doctor_name
            ")
            )
            ->get();

        return response()->json($rows);
    }

    /*
    |--------------------------------------------------------------------------
    | DOCTORS BY SPECIALISATION -- for manual-rate categories (e.g. "Dental
    | Manual") that have no doctor_test_payment_masters rule at all, so
    | getDoctorPayments() above always returns empty and the doctor dropdown
    | has nothing to show. Lets the operator optionally attribute the test
    | to any doctor of the matching specialisation and type the payment
    | amount by hand instead of it being preset.
    |--------------------------------------------------------------------------
    */

    public function getDoctorsBySpecialisation($specialisation)
    {
        $doctors = Doctor::where('status', 1)
            ->where(function ($query) use ($specialisation) {

                $query->where('specialisation', $specialisation)
                    ->orWhereHas('specialisations', function ($q) use ($specialisation) {
                        $q->where('category', $specialisation);
                    });
            })
            ->orderBy('doctor_name')
            ->get(['id', 'doctor_name']);

        return response()->json($doctors);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {

        $request->validate([
            'patient_age' => 'nullable|digits_between:1,2',
        ], [
            'patient_age.digits_between' => 'Age must be a number of up to 2 digits.',
        ]);

        DB::beginTransaction();

        try {


            $tests = $request->input('tests', []);

            $authorisedApproverIds = DB::table('users')
                ->where('authorised', 'YES')
                ->pluck('id')
                ->all();
            $doctorPaymentRulesByItem = [];

            foreach ($tests as &$test) {

                $manualDiscount =
                    (float) ($test['additional_discount_percent'] ?? 0) +
                    (float) ($test['additional_discount_amount'] ?? 0);

                if ($manualDiscount > 0 &&
                    !in_array($test['discount_approved_by'] ?? null, $authorisedApproverIds)) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'Select a valid approving authority before applying a manual discount on "' . ($test['test_name'] ?? 'a test') . '".'
                    ], 422);
                }

                $isManualTest = $this->isManualDiagnosticItem($test['item_code'] ?? null);
                $hasDoctorRule = false;

                if (!$isManualTest) {
                    $itemCodeSub = $test['item_code_sub'] ?? '';
                    if (!array_key_exists($itemCodeSub, $doctorPaymentRulesByItem)) {
                        $doctorPaymentRulesByItem[$itemCodeSub] = DB::table('doctor_test_payment_masters')
                            ->where('item_code_sub', $itemCodeSub)
                            ->where('status', 'A')
                            ->lockForUpdate()
                            ->get(['doctor_id', 'payment_value']);
                    }

                    $paymentRules = $doctorPaymentRulesByItem[$itemCodeSub];
                    $hasDoctorRule = $paymentRules->isNotEmpty();

                    if ($hasDoctorRule) {
                        $selectedDoctorId = (string) ($test['doctor_id'] ?? '');
                        if ($selectedDoctorId === '') {
                            DB::rollBack();

                            return response()->json([
                                'status' => false,
                                'message' => 'Select a Doctor for "' . ($test['test_name'] ?? 'a test') .
                                    '" -- it has a doctor payment rule configured.'
                            ], 422);
                        }

                        $doctorRule = $paymentRules->first(
                            fn ($rule) => (string) $rule->doctor_id === $selectedDoctorId
                        );

                        if (!$doctorRule) {
                            DB::rollBack();

                            return response()->json([
                                'status' => false,
                                'message' => 'The selected doctor does not have an active payment rule for "' .
                                    ($test['test_name'] ?? $itemCodeSub) . '". Select a doctor with a configured rate and try again.'
                            ], 422);
                        }

                        $configuredPayable = round((float) $doctorRule->payment_value, 2);
                        $submittedPayable = round((float) ($test['payment_value'] ?? 0), 2);
                        $isWaived = !empty($test['doctor_payment_waived']);

                        if (!$isWaived && $submittedPayable !== $configuredPayable) {
                            DB::rollBack();

                            return response()->json([
                                'status' => false,
                                'message' => 'The doctor payment for "' . ($test['test_name'] ?? $itemCodeSub) .
                                    '" must match the configured rate of ' . number_format($configuredPayable, 2) .
                                    '. Refresh the invoice and try again.'
                            ], 422);
                        }

                        // Use the server-validated rule for all subsequent
                        // invoice and payable calculations, including waivers.
                        $test['payment_value'] = $configuredPayable;
                    } elseif (!empty($test['doctor_id'])
                        || round((float) ($test['payment_value'] ?? 0), 2) !== 0.0) {
                        DB::rollBack();

                        return response()->json([
                            'status' => false,
                            'message' => 'No active doctor payment rule exists for "' .
                                ($test['test_name'] ?? $itemCodeSub) . '". Remove the doctor payment or configure an active rule before saving.'
                        ], 422);
                    }
                }

                // The doctor's payable for a configured test comes from its
                // selected doctor's rule, not from the submitted form value.
                $doctorPayable = (float) ($test['payment_value'] ?? 0);

                // Recomputed from rate/discounts (rounded to the nearest 10)
                // rather than trusting the submitted 'amount' -- see
                // computeDiagnosticLineAmount().
                $itemAmount = $this->computeDiagnosticLineAmount($test, $isManualTest);

                $isWaived = !empty($test['doctor_payment_waived']);

                if (!$isWaived && $doctorPayable > 0 && $itemAmount < $doctorPayable) {

                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'Discount on "' . ($test['test_name'] ?? 'a test') .
                            '" is too high -- the payable amount cannot go below the doctor\'s fixed payable of ' .
                            number_format($doctorPayable, 2) . '.'
                    ], 422);
                }

            }
            unset($test);
            $request->merge(['tests' => $tests]);

            $firstIsPathology = null;

            foreach ($tests as $test) {

                $categoryName = DB::table('invoice_item_masters')
                    ->where('item_code', $test['item_code'])
                    ->value('item_name');

                $isPathology =
                    stripos($categoryName, 'PATHOLOGY') !== false;

                if ($firstIsPathology === null) {

                    $firstIsPathology = $isPathology;

                    continue;
                }

                if ($firstIsPathology !== $isPathology) {

                    return response()->json([
                        'status' => false,
                        'message' =>
                            'Pathology and Non-Pathology categories cannot be mixed in the same invoice.'
                    ], 422);
                }
            }



            $prefix =
                \App\Support\OfflineMode::prefix('LAB/') .
                now()->format('my') .
                '/' .
                str_pad(
                    Auth::id(),
                    3,
                    '0',
                    STR_PAD_LEFT
                );

            $lastInvoice =
                Invoice::where(
                    'invoice_no',
                    'like',
                    $prefix . '%'
                )
                    ->latest('id')
                    ->first();

            $serial = 1;

            if ($lastInvoice) {

                $explode =
                    explode(
                        '/',
                        $lastInvoice->invoice_no
                    );

                $serial =
                    intval(
                        end($explode)
                    ) + 1;
            }

            $invoiceNo =
                $prefix .
                '/' .
                str_pad(
                    $serial,
                    4,
                    '0',
                    STR_PAD_LEFT
                );
            $patient =
                DB::table('patients')
                    ->where(
                        'patient_id',
                        $request->patient_id
                    )
                    ->first();


            if (!$patient) {
                DB::table('patients')
                    ->insert([

                        'patient_id' =>
                            $request->patient_id,

                        'patient_name' =>
                            $request->patient_name,

                        'mobile_no' =>
                            $request->patient_mobile_no,

                        'age' =>
                            $request->patient_age,

                        'gender' =>
                            $request->patient_gender,


                        'status' => 'A',

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now()
                    ]);

            } else {

                // A returning patient's Name/Age/Gender fields on this form
                // are editable (e.g. the record had no age/gender on file
                // yet), but nothing previously wrote that correction back to
                // the shared patients record -- it only ever landed on this
                // invoice. Mirrors the same correction pattern already used
                // in DoctorVisitInvoiceController::store().
                $patientModel =
                    Patient::where('patient_id', $request->patient_id)->first();

                if ($patientModel) {

                    $oldPatientData =
                        $patientModel->only(['patient_name', 'age', 'gender']);

                    $patientModel->update([
                        'patient_name' => $request->patient_name,
                        'age' => $request->patient_age,
                        'gender' => $request->patient_gender,
                    ]);

                    $newPatientData =
                        $patientModel->only(['patient_name', 'age', 'gender']);

                    if ($oldPatientData !== $newPatientData) {

                        $auditService->logUpdate(
                            self::PATIENT_MODULE_CODE,
                            $patientModel,
                            $oldPatientData,
                            $newPatientData,
                            'Patient details corrected during diagnostic invoice creation'
                        );
                    }
                }
            }

            $totalAmount = (float) $request->total_amount;
            $paidAmount = (float) $request->paid_amount;

            $totalDoctorPayment = 0;

            foreach ($request->tests as $row) {

                // A waived line has nothing to protect here -- the doctor
                // isn't being paid, so it shouldn't constrain how small an
                // initial part-payment can be.
                if (!empty($row['doctor_payment_waived'])) {
                    continue;
                }

                $totalDoctorPayment +=
                    (float) ($row['payment_value'] ?? 0);
            }

            $maxPartialPayment =
                $totalAmount - $totalDoctorPayment;

            if (
                $totalDoctorPayment > 0 &&
                $paidAmount < $totalAmount &&
                $paidAmount > $maxPartialPayment
            ) {

                return response()->json([
                    'status' => false,
                    'message' =>
                        'Maximum allowed part payment is ' .
                        number_format($maxPartialPayment, 2)
                ], 422);
            }

            if ($paidAmount >= $totalAmount) {

                $status = 'Paid';

            } elseif ($paidAmount > 0) {

                $status = 'Partial';

            } else {

                $status = 'Due';
            }

            /*
|--------------------------------------------------------------------------
| ALL TESTS MUST BELONG TO SAME CATEGORY
|--------------------------------------------------------------------------
*/

            $tests = $request->input('tests', []);

            
            $invoiceCategory = 'NON_PATHOLOGY';

            if (!empty($request->tests)) {

                $tests = $request->input('tests', []);

                $firstTest = reset($tests);

                $firstItemCode =
                    $firstTest['item_code'] ?? null;

                $firstItemCode =
                    $firstTest['item_code'] ?? null;

                if ($firstItemCode) {

                    $categoryMaster =
                        DB::table('invoice_item_masters')
                            ->where('item_code', $firstItemCode)
                            ->first();

                    if (
                            stripos(
                                strtoupper($categoryMaster->item_name),
                                'PATHOLOGY'
                            ) !== false
                        ) {

                            $invoiceCategory = 'PATHOLOGY';
                        }
                    
                }
            }
            $totalDoctorPayment = 0;

                foreach ($request->tests as $row) {

                    if (!empty($row['doctor_payment_waived'])) {
                        continue;
                    }

                    $totalDoctorPayment +=
                        (float) (
                            $row['payment_value']
                            ?? 0
                        );
                }
            $invoice =
                Invoice::create([

                    'invoice_no' =>
                        $invoiceNo,

                    'invoice_type' =>
                        'DIAGNOSTIC',
                    'invoice_category' => $invoiceCategory,
                    'appointment_id' =>
                        null,

                    'appointment_no' =>
                        null,

                    'doctor_id' =>
                        null,

                    'patient_id' =>
                        $request->patient_id,

                    'patient_name' =>
                        $request->patient_name,

                    'patient_age' =>
                        $request->patient_age,

                    'patient_gender' =>
                        $request->patient_gender,

                    'patient_mobile_no' =>
                        $request->patient_mobile_no,

                    'invoice_date' =>
                        $request->invoice_date ?: now()->toDateString(),

                    'test_date' =>
                        $request->test_date,
                    'referred_doctor' =>
                        $request->referred_doctor,

                    'total_amount' =>
                        $request->total_amount,

                    'discount' =>
                        $request->discount,

                    'paid_amount' =>
                        $request->paid_amount,

                    'due_amount' =>
                        $request->due_amount,
                     'doctor_payment_amount' =>
                        $totalDoctorPayment,

                    'doctor_amount_collected_by' =>
                        ($totalDoctorPayment > 0 && $paidAmount >= $totalAmount)
                        ? Auth::id()
                        : null,

                    'payment_mode' =>
                        $request->payment_mode,

                    'remarks' =>
                        $request->remarks,


                    'status' =>
                        $status,

                    'created_by' =>
                        Auth::id()
                ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $invoice,
                $invoice->only($invoice->getFillable()),
                'Diagnostic invoice created'
            );


            $lineNo = 1;
            foreach ($request->tests as $row) {

                $isWaived = !empty($row['doctor_payment_waived']);

                $isManual = $this->isManualDiagnosticItem($row['item_code'] ?? null);

                // Recomputed from rate/discounts (rounded to the nearest
                // 10) rather than trusting the submitted 'amount' -- see
                // computeDiagnosticLineAmount(). The doctor-payable floor
                // was already enforced (and this request rejected if
                // violated) by the validation loop above using the same
                // formula; a waived doctor fee reduces the patient's
                // payable by that fee on top of it, same as the browser.
                $amount = $this->computeDiagnosticLineAmount($row, $isManual);

                if (!$isManual && $isWaived) {
                    $amount = max(0, $amount - (float) ($row['payment_value'] ?? 0));
                }

                DB::table('invoice_details')->insert([

                    'invoice_no' => $invoice->invoice_no,

                    'item_code' => $row['item_code'],

                    'item_code_sub' => $row['item_code_sub'],
                    'doctor_id' => $row['doctor_id'] ?? null,
                    'payment_value' => $row['payment_value'] ?? 0,

                    'item_description' => $row['test_name'] ?? '',

                    'quantity' => 1,

                    'rate' => $isManual
                        ? $amount
                        : ($row['rate'] ?? 0),

                    'standard_discount' =>
                        $isManual
                        ? 0
                        : ($row['standard_discount'] ?? 0),

                    'additional_discount_percent' =>
                        $isManual
                        ? 0
                        : ($row['additional_discount_percent'] ?? 0),

                    'additional_discount_amount' =>
                        $isManual
                        ? 0
                        : ($row['additional_discount_amount'] ?? 0),

                    'discount_approved_by' =>
                        $isManual
                        ? null
                        : ($row['discount_approved_by'] ?? null),

                    'discount_approval_remarks' =>
                        $isManual
                        ? null
                        : ($row['remarks'] ?? null),

                    'amount' => $amount,
                    'doctor_id' =>
                        $row['doctor_id'] ?? null,

                    // Kept as the originally scheduled figure even when
                    // waived -- doctor_payment_waived is the single source
                    // of truth for whether it was actually paid.
                    'payment_value' =>
                        $row['payment_value'] ?? 0,

                    'doctor_payment_waived' => $isWaived,

                    // Set only on an auto-added package component row --
                    // holds the item_code_sub of the package's own row on
                    // this same invoice. Lets invoice print/PDF show just
                    // the package name while keeping each component's row
                    // intact for Test Result Entry / test report generation.
                    'package_parent_item_code_sub' =>
                        $row['package_parent_item_code_sub'] ?: null,

                    // This test's own date (defaults to the invoice's test
                    // date; editable per test here and later on the report
                    // screens). A package component takes its package's date.
                    'test_date' =>
                        $this->resolveLineTestDate($row, $request->tests, $request->test_date),

                    'remarks' => $row['remarks'] ?? null,

                    'created_by' => Auth::id(),

                    'created_at' => now(),

                    'updated_at' => now()
                ]);


                $doctor = null;

                if (!empty($row['doctor_id']) && $row['doctor_id'] != 999) {

                    $doctor = Doctor::find($row['doctor_id']);

                }

                DoctorPayable::create([

                    'payable_no' => $this->generatePayableNo(),

                    'invoice_id' => $invoice->id,

                    'invoice_no' => $invoice->invoice_no,

                    'invoice_type' => 'DIAGNOSTIC',

                    'transaction_type' => 'RECEIVED',

                    'doctor_id' => $row['doctor_id'] ?? null,

                    'doctor_code' => $doctor?->doctor_code,

                    'doctor_name' => $doctor?->doctor_name ?? 'ALL DOCTORS',

                    'patient_id' => $invoice->patient_id,

                    'patient_name' => $invoice->patient_name,

                    'item_code' => $row['item_code'],

                    'item_code_sub' => $row['item_code_sub'],

                    'item_description' => $row['test_name'],

                    'gross_amount' => $amount,

                    'payment_rule' => 'TEST_PAYMENT_MASTER',

                    'payment_rate' => $isWaived
                        ? 0
                        : (float) ($row['payment_value'] ?? 0),

                    'payable_amount' => $isWaived
                        ? 0
                        : (float) ($row['payment_value'] ?? 0),

                    'payment_status' => DoctorPayable::STATUS_PENDING,

                    'remarks' => $isWaived
                        ? trim('Doctor payment waived. ' . ($row['remarks'] ?? ''))
                        : ($row['remarks'] ?? null),

                    'created_by' => Auth::id(),

                ]);
            }





            /*
|--------------------------------------------------------------------------
| ALLOCATE INITIAL PAYMENT
|--------------------------------------------------------------------------
*/

            $this->allocatePaymentToInvoiceItems(
                $invoice->invoice_no,
                $request->paid_amount,
                'INITIAL'
            );
            \App\Support\DailyTransactionRecorder::insert([

                        'transaction_date' =>
                            $request->invoice_date ?: now()->toDateString(),

                        'transaction_type' =>
                            'RECEIVED',

                        'received_amount' =>
                            $request->paid_amount,

                        'refund_amount' =>
                            0,

                        'invoice_reference' =>
                            $invoice->invoice_no,

                        'patient_id' =>
                            $request->patient_id,

                        'patient_name' =>
                            $request->patient_name,

                        'operator_id' =>
                            Auth::id(),

                        'operator_name' =>
                            Auth::user()->name,

                        'payment_mode' =>
                            $request->payment_mode,

                        'payment_reference' =>
                            $request->payment_reference,

                        'remarks' =>
                            'Diagnostic Invoice',

                        'status' =>
                            'ACTIVE',

                        'created_by' =>
                            Auth::id(),

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now()
                    ]);

            app(CashInHandService::class)->apply(
                Auth::id(),
                'RECEIVED',
                $request->payment_mode,
                (float) $request->paid_amount
            );

            DB::commit();

            foreach ($tests as $row) {

                $manualDiscount =
                    $this->roundToNearestTen(
                        (float) ($row['additional_discount_percent'] ?? 0) / 100 * (float) ($row['rate'] ?? 0)
                    ) +
                    (float) ($row['additional_discount_amount'] ?? 0);

                if ($manualDiscount > 0 && !empty($row['discount_approved_by'])) {

                    $itemName = DB::table('invoice_item_masters')
                        ->where('item_code', $row['item_code'] ?? null)
                        ->value('item_name');

                    $itemDescriptionSub = DB::table('invoice_item_details')
                        ->where('item_code', $row['item_code'] ?? null)
                        ->where('item_code_sub', $row['item_code_sub'] ?? null)
                        ->value('item_description_sub');

                    $testLabel = trim(
                        ($itemName ?? '') .
                        ($itemDescriptionSub ? ' / ' . $itemDescriptionSub : '')
                    );

                    $this->sendDiscountApprovalEmail(
                        $row['discount_approved_by'],
                        $invoice->invoice_no,
                        $invoice->patient_name,
                        $manualDiscount,
                        $testLabel ?: null,
                        $row['remarks'] ?? null
                    );
                }
            }

            $this->autoSendInvoiceWhatsapp(
                $invoice->id
            );
            return response()->json([

                'status' => true,

                'invoice_id' =>
                    $invoice->id,

                'invoice_no' =>
                    $invoice->invoice_no,

                'message' =>
                    'Invoice created successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([

                'status' => false,

                'message' =>
                    $e->getMessage()
            ], 500);
        }
    }


    /**
     * The test date to store on one billed line: its own (when a valid date
     * was sent), else the invoice's test date. A package component row takes
     * the date of the package line it belongs to.
     */
    private function resolveLineTestDate(array $row, array $allRows, $invoiceTestDate): ?string
    {
        $valid = function ($value) {

            $text = substr((string) $value, 0, 10);

            return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $text, $m)
                && (int) $m[1] > 1000
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? $text
                : null;
        };

        // A package component's own date (set by hand on the form) wins; the
        // form pre-fills it with the package line's date, so untouched
        // components still match the package.
        $own = $valid($row['test_date'] ?? null);

        if ($own) {
            return $own;
        }

        $parentSub = $row['package_parent_item_code_sub'] ?? null;

        if ($parentSub) {

            foreach ($allRows as $other) {

                if (empty($other['package_parent_item_code_sub']) && ($other['item_code_sub'] ?? null) === $parentSub) {

                    $parentDate = $valid($other['test_date'] ?? null);

                    if ($parentDate) {
                        return $parentDate;
                    }
                }
            }
        }

        return $valid($row['test_date'] ?? null) ?? $valid($invoiceTestDate);
    }

    public function generatePatientId(Request $request)
    {
        $count = DB::table('patients')
            ->where(
                'mobile_no',
                $request->mobile_no
            )
            ->count() + 1;

        $patientId =
            \App\Support\OfflineMode::prefix('P') .
            $request->mobile_no .
            '-' .
            str_pad(
                $count,
                2,
                '0',
                STR_PAD_LEFT
            );

        return response()->json([
            'status' => true,
            'patient_id' => $patientId
        ]);
    }
    public function getPatientDiscountEligibility($patientId)
    {
        $patient = DB::table('patients')
            ->where('patient_id', $patientId)
            ->first();

        $messages = [];

        if (!$patient) {

            return response()->json([
                'status' => false
            ]);
        }

        if ((int) $patient->age >= 60) {

            $messages[] =
                'Patient is eligible for discount under Senior Citizen category.';
        }

        $isMember = $this->resolveMemberEligibility(
            $patient->mobile_no ?? '',
            $patient->patient_name ?? ''
        );

        if ($isMember) {

            $messages[] =
                'Patient is eligible for discount under Member / Employee category.';
        }

        return response()->json([
            'status' => true,
            'messages' => $messages,
            'is_member' => $isMember
        ]);
    }



    /**
     * Gated entry point for the two automatic triggers (store() and the
     * update()/due-payment-collection path) -- both funnel through here so
     * the toggle can't be bypassed by one site while forgetting the other.
     * The manual sendWhatsapp($id) route calls sendInvoiceWhatsapp()
     * directly, ungated, so staff always retain a working resend button.
     */
    private function autoSendInvoiceWhatsapp($invoiceId): array
    {
        if (!WhatsappAutoSendSetting::isEnabled('INVOICE')) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                WhatsappAutoSendSetting::logSkipped('INVOICE', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            }
            return ['success' => false, 'skipped' => true];
        }

        return $this->sendInvoiceWhatsapp($invoiceId);
    }

    private function sendInvoiceWhatsapp($invoiceId): array
    {
        try {

            $invoice =
                Invoice::findOrFail(
                    $invoiceId
                );

            $fileName =
                str_replace('/', '_', $invoice->invoice_no)
                . '.pdf';

            /*
            |--------------------------------------------------------------------------
            | Generate PDF
            |--------------------------------------------------------------------------
            */

            $tests = DB::table('invoice_details')
                ->where(
                    'invoice_no',
                    $invoice->invoice_no
                )
                // Package component rows (Test Result Entry needs its own
                // row per component) aren't separately billed -- the
                // invoice should only show the package's own line.
                ->whereNull('package_parent_item_code_sub')
                ->get();

            $testCategoryNames = DB::table('invoice_item_masters')
                ->whereIn('item_code', $tests->pluck('item_code')->unique())
                ->pluck('item_name', 'item_code');

            $tests = $tests->map(function ($row) use ($testCategoryNames) {

                    $row->test_name =
                        $row->item_description;

                    $row->test_category =
                        $testCategoryNames->get($row->item_code);

                    $row->discount =
                        $this->roundToNearestTen(
                            ($row->rate ?? 0) * ($row->standard_discount ?? 0) / 100
                        )
                        +
                        $this->roundToNearestTen(
                            ($row->rate ?? 0) * ($row->additional_discount_percent ?? 0) / 100
                        )
                        +
                        ($row->additional_discount_amount ?? 0);

                    return $row;
                });

            $paperSize = $this->invoicePdfPaperSize($tests->count());

            $testDateText = \App\Support\ReportTestDates::applyToInvoiceLines($tests, $invoice);

            $pdf =
                Pdf::loadView(
                    'apps-diagnostic-invoice-pdf',
                    [
                        'invoice' => $invoice,
                        'tests' => $tests,
                        'paperSize' => $paperSize,
                        'testDateText' => $testDateText,
                    ]
                );

            $this->applyInvoicePdfPaperSize($pdf, $paperSize);

            $pdfPath =
                public_path(
                    'invoices/' . $fileName
                );

            $pdf->save($pdfPath);
           
            /*
            |--------------------------------------------------------------------------
            | Public URL
            |--------------------------------------------------------------------------
            */

            $pdfUrl =
                asset(
                    'invoices/' . $fileName
                );

            $response =
                WatiService::sendInvoice(

                    $invoice->patient_mobile_no,

                    $invoice->patient_name,

                    $invoice->invoice_no,

                    $pdfUrl,

                    now()->format(
                        'd-m-Y H:i'
                    )
                );

            DB::table(
                'whatsapp_message_logs'
            )->insert([

                        'invoice_no' =>
                            $invoice->invoice_no,

                        'mobile_no' =>
                            $invoice->patient_mobile_no,

                        'patient_name' =>
                            $invoice->patient_name,

                        'message_type' =>
                            'INVOICE',

                        'status' =>
                            $response->successful()
                            ? 'SENT'
                            : 'FAILED',

                        'response' =>
                            $response->body(),

                        'created_at' =>
                            now(),

                        'updated_at' =>
                            now()
                    ]);

            $invoice->update([

                'whatsapp_sent_at' =>
                    now(),

                'whatsapp_status' =>
                    $response->successful()
                    ? 'SENT'
                    : 'FAILED',

                'whatsapp_send_count' =>
                    DB::raw(
                        'whatsapp_send_count + 1'
                    )
            ]);

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'WhatsApp sent successfully.'
                    : 'WhatsApp API did not accept the message: ' . $response->body(),
            ];

        } catch (\Exception $e) {

            \Log::error(
                'WhatsApp Send Failed: '
                . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
    public function searchPatient(Request $request)
    {
        $mobile = trim($request->mobile_no);

        $patients = DB::table('patients')

            ->where('status', 'A')

            ->where(function ($q) use ($mobile) {

                $q->where('mobile_no', $mobile)

                    ->orWhereIn(
                        'patient_id',
                        DB::table('patient_mobile_numbers')
                            ->where('mobile_no', $mobile)
                            ->pluck('patient_id')
                    );
            })

            ->orderBy('patient_name')

            ->get([
                'patient_id',
                'patient_name',
                'age',
                'gender'
            ]);

        return response()->json([
            'status' => true,
            'patients' => $patients
        ]);
    }
    public function sendWhatsapp($id)
    {
        if (!WhatsappAutoSendSetting::isEnabled('INVOICE')) {

            $invoice = Invoice::find($id);
            if ($invoice) {
                WhatsappAutoSendSetting::logSkipped('INVOICE', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            }

            return response()->json([
                'success' => false,
                'message' => 'WhatsApp sending for Invoice is currently disabled by Admin. Enable it in WhatsApp Auto-Send settings to send.'
            ], 500);
        }

        $result = $this->sendInvoiceWhatsapp($id);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message']
        ], $result['success'] ? 200 : 500);
    }
    public function getPatientAppointments($patientId)
    {
        $rows =
            DB::table('doctor_appointments as a')
                ->leftJoin(
                    'doctors as d',
                    'a.doctor_id',
                    '=',
                    'd.id'
                )
                ->where(
                    'a.patient_id',
                    $patientId
                )
                ->whereDate(
                    'a.appointment_date',
                    '>=',
                    now()->subMonths(3)
                )
                ->select(
                    'a.id',
                    'a.appointment_no',
                    'a.appointment_date',
                    'd.doctor_name'
                )
                ->orderByDesc('a.appointment_date')
                ->get();

        return response()->json($rows);
    }

    /**
     * Feeds the "Doctor Visit Reference" dropdown on diagnostic invoice
     * creation -- lets staff link the test invoice back to a prior
     * DOCTOR_VISIT invoice for the same patient, and copy that visit's
     * doctor into the Referring Doctor field.
     */
    public function getPatientDoctorVisits($patientId)
    {
        // invoices.doctor_name is an unreliable snapshot (often left blank
        // at creation) -- join doctors via doctor_id for the true name,
        // same reasoning as the doctor_payables.doctor_name staleness.
        $rows = DB::table('invoices as i')
            ->leftJoin('doctors as d', 'i.doctor_id', '=', 'd.id')
            ->where('i.patient_id', $patientId)
            ->where('i.invoice_type', 'DOCTOR_VISIT')
            ->where(function ($q) {
                $q->whereNull('i.cancelled')->orWhere('i.cancelled', '!=', 'Y');
            })
            ->orderByDesc('i.invoice_date')
            ->get(['i.id', 'i.invoice_no', 'i.invoice_date', 'd.doctor_name']);

        $rows->transform(function ($row) {
            $row->invoice_date_fmt = $row->invoice_date
                ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                : '';
            return $row;
        });

        return response()->json($rows);
    }
    public function getPatient($patientId)
    {
        $patient = DB::table('patients')
            ->where('patient_id', $patientId)
            ->first();

        return response()->json([
            'status' => true,
            'patient' => $patient
        ]);
    }


    public function addMobileNumber(Request $request)
    {
        $exists = DB::table('patients')
            ->where('mobile_no', $request->mobile_no)
            ->exists();

        if ($exists) {

            return response()->json([
                'status' => false,
                'message' => 'Mobile already assigned'
            ]);
        }

        $exists = DB::table('patient_mobile_numbers')
            ->where('mobile_no', $request->mobile_no)
            ->exists();

        if ($exists) {

            return response()->json([
                'status' => false,
                'message' => 'Mobile already assigned'
            ]);
        }

        DB::table('patient_mobile_numbers')
            ->insert([

                'patient_id' =>
                    $request->patient_id,

                'mobile_no' =>
                    $request->mobile_no,

                'is_primary' =>
                    'N',

                'created_at' =>
                    now(),

                'updated_at' =>
                    now()
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Mobile number added'
        ]);
    }

    public function changePrimaryMobile(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|string',
            'mobile_no' => 'required|regex:/^[1-9][0-9]{9}$/',
        ]);

        DB::beginTransaction();

        try {

            $patient = DB::table('patients')
                ->where(
                    'patient_id',
                    $request->patient_id
                )
                ->first();

            if (!$patient) {

                throw new \Exception(
                    'Patient not found'
                );
            }

            // Unlike addMobileNumber(), this previously never checked
            // whether the new number already belongs to a DIFFERENT
            // patient -- neither patients.mobile_no nor
            // patient_mobile_numbers.mobile_no is unique at the DB level,
            // so without this check it would silently hand one patient's
            // primary number to two different patient records. Excludes
            // this same patient's own rows so re-promoting one of their
            // existing secondary numbers still works.
            if ($request->mobile_no !== $patient->mobile_no) {

                $usedByAnotherPatient =
                    DB::table('patients')
                        ->where('mobile_no', $request->mobile_no)
                        ->where('patient_id', '!=', $patient->patient_id)
                        ->exists()
                    || DB::table('patient_mobile_numbers')
                        ->where('mobile_no', $request->mobile_no)
                        ->where('patient_id', '!=', $patient->patient_id)
                        ->exists();

                if ($usedByAnotherPatient) {

                    throw new \Exception(
                        'This mobile number is already assigned to a different patient.'
                    );
                }
            }

            DB::table('patient_mobile_numbers')
                ->updateOrInsert(

                    [
                        'patient_id' =>
                            $patient->patient_id,

                        'mobile_no' =>
                            $patient->mobile_no
                    ],

                    [
                        'is_primary' => 'N'
                    ]
                );

            DB::table('patients')

                ->where(
                    'patient_id',
                    $patient->patient_id
                )

                ->update([

                    'mobile_no' =>
                        $request->mobile_no
                ]);

            DB::table('patient_mobile_numbers')

                ->where(
                    'patient_id',
                    $patient->patient_id
                )

                ->update([
                    'is_primary' => 'N'
                ]);

            DB::table('patient_mobile_numbers')

                ->updateOrInsert(

                    [
                        'patient_id' =>
                            $patient->patient_id,

                        'mobile_no' =>
                            $request->mobile_no
                    ],

                    [
                        'is_primary' => 'Y'
                    ]
                );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Primary mobile number updated.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    public function deactivatePatient(Request $request)
    {
        DB::table('patients')

            ->where(
                'patient_id',
                $request->patient_id
            )

            ->update([
                'status' => 'I'
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Patient deactivated'
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id, AuditService $auditService)
    {
        // invoices is a MyISAM table (no transactions, no row locking --
        // DB::beginTransaction()/lockForUpdate() are silently no-ops on it),
        // so two near-simultaneous submissions for the same invoice (a
        // double-click, or a slow network prompting a second click) could
        // both read payment_edit_count before either had written back,
        // both pass the same "== 0" / "== 1" check, and both record a
        // payment -- duplicating the daily_transactions row and the
        // cash-in-hand credit even though payment_edit_count only reflects
        // one increment (both writes set the same value). A cache-backed
        // lock serializes this instead, independent of the table engine;
        // a genuine second click just waits briefly and then correctly
        // sees the first payment's result rather than racing it.
        $lock = Cache::lock("diagnostic_invoice_update_{$id}", 15);

        try {
            return $lock->block(10, function () use ($request, $id, $auditService) {
                return $this->updateDuePayment($request, $id, $auditService);
            });
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            return response()->json([
                'status' => false,
                'message' => 'This invoice is already being updated -- please wait a moment and try again.'
            ], 409);
        }
    }

    private function updateDuePayment(Request $request, $id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $invoice = Invoice::findOrFail($id);

            $oldInvoiceData = $invoice->only($invoice->getFillable());

            $isPathology =
                $invoice->invoice_category === 'PATHOLOGY';

           

            if ($invoice->cancelled == 'Y') {

                return response()->json([
                    'status' => false,
                    'message' => 'Cancelled invoice cannot be edited'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | DUE PAYMENT COLLECTION MODE
            |--------------------------------------------------------------------------
            */

            if ($invoice->due_amount <= 0) {

                return response()->json([
                    'status' => false,
                    'message' => 'No due amount pending'
                ]);
            }
            
            if ($invoice->payment_edit_count >= 2) {

                return response()->json([
                    'status' => false,
                    'message' => 'Maximum payment updates reached'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | DOCTOR PAYMENT WAIVER
            |--------------------------------------------------------------------------
            */

            $waiverReduction = 0;
            $linesToWaive = collect();

            if ($request->filled('waive_lines')) {

                $linesToWaive = DB::table('invoice_details')
                    ->where('invoice_no', $invoice->invoice_no)
                    ->whereIn('id', $request->waive_lines)
                    ->where('doctor_payment_waived', false)
                    ->where('payment_value', '>', 0)
                    ->get();

                $waiverReduction = $linesToWaive->sum('payment_value');
            }

            if ($waiverReduction > 0) {

                $invoice->total_amount -= $waiverReduction;
                $invoice->due_amount -= $waiverReduction;

                // This is the aggregate "how much is owed to doctors for
                // this invoice" figure (shown on the invoice list/CRUD
                // grid and used by doctor settlement reporting) -- it must
                // drop along with the per-line DoctorPayable rows below,
                // or a waived line keeps showing as still owed.
                $invoice->doctor_payment_amount =
                    max(0, ($invoice->doctor_payment_amount ?? 0) - $waiverReduction);

                if ($invoice->due_amount < 0) {
                    $invoice->due_amount = 0;
                }

                // Persist the line amounts, the DoctorPayable zeroing, and
                // the reduced invoice totals now -- allocatePaymentToInvoiceItems()
                // (called below by the phase logic) re-reads both
                // invoice_details and the invoices row fresh from the DB, so
                // its proportional split must see post-waiver figures, not
                // the stale pre-waiver ones.
                foreach ($linesToWaive as $line) {

                    $newAmount =
                        max(0, (float) $line->amount - (float) $line->payment_value);

                    DB::table('invoice_details')
                        ->where('id', $line->id)
                        ->update([
                            'amount' => $newAmount,
                            'doctor_payment_waived' => true,
                        ]);

                    $doctorPayable =
                        DoctorPayable::where('invoice_id', $invoice->id)
                            ->where('item_code_sub', $line->item_code_sub)
                            ->first();

                    if ($doctorPayable) {

                        $oldPayableData =
                            $doctorPayable->only($doctorPayable->getFillable());

                        $doctorPayable->payment_rate = 0;
                        $doctorPayable->payable_amount = 0;
                        $doctorPayable->remarks =
                            trim('Doctor payment waived. ' . ($doctorPayable->remarks ?? ''));
                        $doctorPayable->updated_by = Auth::id();
                        $doctorPayable->save();

                        $auditService->logUpdate(
                            self::MODULE_CODE,
                            $doctorPayable,
                            $oldPayableData,
                            $doctorPayable->only($doctorPayable->getFillable()),
                            'Doctor payment waived on due payment'
                        );
                    }
                }

                $invoice->save();
            }

            /*
            |--------------------------------------------------------------------------
            | DOCTOR REPLACEMENT (USG ONLY) -- AT FINAL/DUE PAYMENT
            |--------------------------------------------------------------------------
            | The doctor originally selected when the invoice was raised can be
            | swapped for a different USG doctor at the point the remaining due
            | amount is collected. The patient's line amount (and therefore the
            | invoice total/paid/due) never changes here -- only how that fixed
            | amount splits between the clinic's own charge and the doctor's
            | payable, which is recalculated from the NEW doctor's own rate for
            | this exact test (doctor_test_payment_masters), looked up
            | server-side rather than trusted from the request, since this is
            | recomputing money, not just accepting a fresh line like store() does.
            | Restricted to USG001 lines only, per the requirement.
            |--------------------------------------------------------------------------
            */

            if ($request->filled('doctor_replacements')) {

                foreach ($request->doctor_replacements as $invoiceDetailId => $newDoctorId) {

                    $newDoctorId = (int) $newDoctorId;

                    $line = DB::table('invoice_details')
                        ->where('id', $invoiceDetailId)
                        ->where('invoice_no', $invoice->invoice_no)
                        ->first();

                    // Not this invoice's line, not USG, or nothing actually
                    // changed -- silently skip rather than error, since the
                    // dropdown always submits its currently-selected value
                    // whether or not the operator touched it.
                    if (!$line || $line->item_code !== 'USG001') {
                        continue;
                    }

                    if ((int) $line->doctor_id === $newDoctorId) {
                        continue;
                    }

                    if ($line->doctor_payment_waived) {

                        DB::rollBack();

                        return response()->json([
                            'status' => false,
                            'message' => 'Cannot change doctor on "' . $line->item_description . '" -- its doctor payment was waived.'
                        ]);
                    }

                    $newRate = DB::table('doctor_test_payment_masters')
                        ->where('item_code_sub', $line->item_code_sub)
                        ->where('doctor_id', $newDoctorId)
                        ->where('status', 'A')
                        ->value('payment_value');

                    if ($newRate === null) {

                        DB::rollBack();

                        return response()->json([
                            'status' => false,
                            'message' => 'The selected doctor has no defined payment rate for "' . $line->item_description . '".'
                        ]);
                    }

                    $newRate = (float) $newRate;

                    if ($newRate > (float) $line->amount) {

                        DB::rollBack();

                        return response()->json([
                            'status' => false,
                            'message' => 'The selected doctor\'s rate (' . number_format($newRate, 2) . ') exceeds the billed amount (' . number_format($line->amount, 2) . ') for "' . $line->item_description . '".'
                        ]);
                    }

                    $oldPaymentValue = (float) $line->payment_value;

                    $newDoctor = $newDoctorId !== 999 ? Doctor::find($newDoctorId) : null;

                    DB::table('invoice_details')
                        ->where('id', $line->id)
                        ->update([
                            'doctor_id' => $newDoctorId,
                            'payment_value' => $newRate,
                            'updated_by' => Auth::id(),
                            'updated_at' => now(),
                        ]);

                    // Aggregate figure used by reports/Cash Ledger -- shift by
                    // the net difference only, the same pattern the waiver
                    // block above uses.
                    $invoice->doctor_payment_amount = max(
                        0,
                        ((float) $invoice->doctor_payment_amount) - $oldPaymentValue + $newRate
                    );

                    $doctorPayable = DoctorPayable::where('invoice_id', $invoice->id)
                        ->where('item_code_sub', $line->item_code_sub)
                        ->first();

                    if ($doctorPayable) {

                        if ($doctorPayable->payment_status !== DoctorPayable::STATUS_PENDING) {

                            DB::rollBack();

                            return response()->json([
                                'status' => false,
                                'message' => 'Cannot change doctor on "' . $line->item_description . '" -- its doctor payment has already been settled.'
                            ]);
                        }

                        $oldPayableData = $doctorPayable->only($doctorPayable->getFillable());

                        $doctorPayable->doctor_id = $newDoctorId;
                        $doctorPayable->doctor_code = $newDoctor?->doctor_code;
                        $doctorPayable->doctor_name = $newDoctorId === 999 ? 'ALL DOCTORS' : ($newDoctor?->doctor_name ?? 'ALL DOCTORS');
                        $doctorPayable->payment_rate = $newRate;
                        $doctorPayable->payable_amount = $newRate;
                        $doctorPayable->updated_by = Auth::id();
                        $doctorPayable->save();

                        $auditService->logUpdate(
                            self::MODULE_CODE,
                            $doctorPayable,
                            $oldPayableData,
                            $doctorPayable->only($doctorPayable->getFillable()),
                            'Doctor changed at final payment (USG)'
                        );
                    }
                }
            }

            $additionalPaid =
                (float) $request->additional_paid_amount;

            if ($additionalPaid <= 0 && $invoice->due_amount > 0) {

                return response()->json([
                    'status' => false,
                    'message' => 'Enter additional paid amount'
                ]);
            }

            /*
 |--------------------------------------------------------------------------
 | PAYMENT RULES
 |--------------------------------------------------------------------------
 */

            if ($isPathology) {

                /*
                |--------------------------------------------------------------------------
                | PHASE 1
                |--------------------------------------------------------------------------
                */
                if ($invoice->payment_edit_count == 0) {

                    if ($additionalPaid > $invoice->due_amount) {

                        return response()->json([
                            'status' => false,
                            'message' =>
                                'Payment cannot exceed due amount'
                        ]);
                    }

                    $invoice->paid_amount_1 = $additionalPaid;
                    $invoice->paid_date_1 = now();

                    $this->allocatePaymentToInvoiceItems(
                        $invoice->invoice_no,
                        $additionalPaid,
                        'PHASE1'
                    );

                    $invoice->payment_edit_count = 1;
                }

                /*
                |--------------------------------------------------------------------------
                | PHASE 2
                |--------------------------------------------------------------------------
                */ elseif ($invoice->payment_edit_count == 1) {

                    if ($additionalPaid != $invoice->due_amount) {

                        return response()->json([
                            'status' => false,
                            'message' =>
                                'Final payment must clear entire balance'
                        ]);
                    }

                    $invoice->paid_amount_2 = $additionalPaid;
                    $invoice->paid_date_2 = now();

                    $this->allocatePaymentToInvoiceItems(
                        $invoice->invoice_no,
                        $additionalPaid,
                        'PHASE2'
                    );

                    $invoice->payment_edit_count = 2;
                }

            } else {

                /*
                |--------------------------------------------------------------------------
                | NON-PATHOLOGY
                |--------------------------------------------------------------------------
                */

                if ($invoice->payment_edit_count >= 1) {

                    return response()->json([
                        'status' => false,
                        'message' =>
                            'Phase 2 payment is not allowed for this category'
                    ]);
                }

                if ($additionalPaid != $invoice->due_amount) {

                    return response()->json([
                        'status' => false,
                        'message' =>
                            'Payment must clear entire due amount'
                    ]);
                }

                $invoice->paid_amount_1 = $additionalPaid;
                $invoice->paid_date_1 = now();

                $this->allocatePaymentToInvoiceItems(
                    $invoice->invoice_no,
                    $additionalPaid,
                    'PHASE1'
                );

                $invoice->payment_edit_count = 1;
            }

            /*
            |--------------------------------------------------------------------------
            | RECALCULATE PAID & DUE
            |--------------------------------------------------------------------------
            */

            $invoice->paid_amount =
                ($invoice->paid_amount ?? 0)
                + $additionalPaid;

            $invoice->due_amount =
                $invoice->total_amount
                - $invoice->paid_amount;

            if ($invoice->due_amount < 0) {

                $invoice->due_amount = 0;
            }

            $invoice->status =
                $invoice->due_amount == 0
                ? 'Paid'
                : 'Partial';

            if ($invoice->due_amount == 0 &&
                $invoice->doctor_payment_amount > 0 &&
                empty($invoice->doctor_amount_collected_by)) {

                $invoice->doctor_amount_collected_by = Auth::id();
            }

            if ($request->filled('payment_mode')) {
                $invoice->payment_mode = $request->payment_mode;
            }

            if ($request->filled('remarks')) {
                $invoice->remarks = $request->remarks;
            }

            $invoice->updated_by = Auth::id();

            $invoice->save();

            /*
            |--------------------------------------------------------------------------
            | DAILY TRANSACTION ENTRY
            |--------------------------------------------------------------------------
            */

            \App\Support\DailyTransactionRecorder::insert([

                'transaction_date' =>
                    now()->toDateString(),

                'transaction_type' =>
                    'RECEIVED',

                'received_amount' =>
                    $additionalPaid,

                'refund_amount' =>
                    0,

                'invoice_reference' =>
                    $invoice->invoice_no,

                'patient_id' =>
                    $invoice->patient_id,

                'patient_name' =>
                    $invoice->patient_name,

                'operator_id' =>
                    Auth::id(),

                'operator_name' =>
                    Auth::user()->name,

                'payment_mode' =>
                    $invoice->payment_mode,

                'payment_reference' =>
                    $request->payment_reference,

                'remarks' =>
                    ($invoice->payment_edit_count == 1
                        ? 'Diagnostic Due Payment - Phase 1'
                        : 'Diagnostic Due Payment - Phase 2')
                    . ($request->filled('remarks')
                        ? ' - ' . $request->remarks
                        : ''),

                'status' =>
                    'ACTIVE',

                'created_by' =>
                    Auth::id(),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now()
            ]);

            app(CashInHandService::class)->apply(
                Auth::id(),
                'RECEIVED',
                $invoice->payment_mode,
                (float) $additionalPaid
            );

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Diagnostic invoice due payment collected'
            );

            $this->autoSendInvoiceWhatsapp(
                $invoice->id
            );

            // Reports whose WhatsApp was held for this payment go out now
            // that nothing is due (no-op while any due remains).
            \App\Services\ReportWhatsappDueGate::releaseAll($invoice->invoice_no);

            return response()->json([
                'status' => true,
                'invoice_id' => $invoice->id,
                'message' => 'Due payment updated successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE / CANCEL
    |--------------------------------------------------------------------------
    */

    public function destroy(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {

            $invoice =
                Invoice::findOrFail($id);

            if ($invoice->cancelled === 'Y') {

                return response()->json([
                    'status' => false,
                    'message' => 'Invoice already cancelled'
                ]);
            }

            $check = \App\Support\InvoiceCancellationApproval::check($invoice, Auth::id());

            if (!$check['allowed']) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => $check['message']
                ], 422);
            }

            $approverId = $check['approver_id'];

            $oldInvoiceData = $invoice->only($invoice->getFillable());

            $invoice->cancelled = 'Y';

            $invoice->cancelled_by =
                Auth::id();

            $invoice->cancelled_at =
                now();

            $invoice->cancellation_approved_by =
                $approverId;

            $invoice->cancellation_remarks =
                $request->reason;

            $invoice->refund_amount =
                $invoice->paid_amount;

            $invoice->refund_date =
                now();

            $invoice->save();

            // its doctor's share will never be paid -- take the unpaid payables out of every report
            \App\Support\DoctorPayableCancellation::cancelUnpaidForInvoice($invoice->id);

            \App\Support\DailyTransactionRecorder::insert([

                    'transaction_date' =>
                        now()->toDateString(),

                    'transaction_type' =>
                        'REFUND',

                    'received_amount' =>
                        0,

                    'refund_amount' =>
                        $invoice->paid_amount,

                    'invoice_reference' =>
                        $invoice->invoice_no,

                    'patient_id' =>
                        $invoice->patient_id,

                    'patient_name' =>
                        $invoice->patient_name,

                    'operator_id' =>
                        Auth::id(),

                    'operator_name' =>
                        Auth::user()->name,

                    'payment_mode' =>
                        $invoice->payment_mode,

                    'remarks' =>
                        'Diagnostic Invoice Cancelled',

                    'status' =>
                        'ACTIVE',

                    'created_by' =>
                        Auth::id(),

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now()
                ]);

            app(CashInHandService::class)->apply(
                Auth::id(),
                'REFUND',
                $invoice->payment_mode,
                (float) $invoice->paid_amount
            );

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Diagnostic invoice cancelled'
            );

            app(\App\Services\InvoiceCancellationNotifier::class)->notify(
                $invoice,
                (float) $invoice->paid_amount,
                $request->reason,
                Auth::id(),
                $approverId
            );

            return response()->json([
                'status' => true,
                'message' => 'Invoice cancelled successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MARK PAYMENT FAILED
    |--------------------------------------------------------------------------
    | A payment already recorded against this invoice (initial, Phase 1, or
    | Phase 2 -- whichever was collected MOST RECENTLY, per
    | payment_edit_count's current value) turns out to have failed after the
    | fact (a bounced UPI/card transaction, discovered later). Reopens
    | due_amount by that exact amount, cancels the matching
    | daily_transactions row and reverses cash_in_hand (via
    | PaymentReversalService), and steps payment_edit_count back down --
    | which is also what lets this be called again to unwind an EARLIER
    | phase too, one call at a time, oldest last. The invoice itself is
    | never cancelled -- the diagnostic work already done stays valid, only
    | its payment state is corrected.
    |--------------------------------------------------------------------------
    */

    public function markPaymentFailed(Request $request, $id, AuditService $auditService, PaymentReversalService $reversalService)
    {
        if (optional(Auth::user())->role !== 'Admin') {

            return response()->json([
                'status' => false,
                'message' => 'Only an Admin can mark a payment as failed.'
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();

        try {

            $invoice = Invoice::lockForUpdate()->findOrFail($id);

            if ($invoice->cancelled === 'Y') {

                throw ValidationException::withMessages([
                    'payment' => ['This invoice is already cancelled.'],
                ]);
            }

            if ((float) $invoice->paid_amount <= 0) {

                throw ValidationException::withMessages([
                    'payment' => ['Nothing has been paid on this invoice to reverse.'],
                ]);
            }

            $oldInvoiceData = $invoice->only($invoice->getFillable());

            // The amount tied to the MOST RECENT phase only -- payment_edit_count
            // 2 means Phase 2 was the last thing collected, 1 means Phase 1, 0
            // means no due-payment phase has ever run and the only payment on
            // record is the initial one (paid_amount with phase 1/2 both still
            // null/0 collapses this formula to paid_amount itself).
            if ($invoice->payment_edit_count == 2) {
                $expectedAmount = (float) $invoice->paid_amount_2;
            } elseif ($invoice->payment_edit_count == 1) {
                $expectedAmount = (float) $invoice->paid_amount_1;
            } else {
                $expectedAmount = (float) $invoice->paid_amount
                    - (float) ($invoice->paid_amount_1 ?? 0)
                    - (float) ($invoice->paid_amount_2 ?? 0);
            }

            $reversalService->reverseLatestReceived($invoice, $expectedAmount);

            $invoice->paid_amount = (float) $invoice->paid_amount - $expectedAmount;
            $invoice->due_amount = (float) $invoice->due_amount + $expectedAmount;

            if ($invoice->payment_edit_count == 2) {
                $invoice->paid_amount_2 = null;
                $invoice->paid_date_2 = null;
            } elseif ($invoice->payment_edit_count == 1) {
                $invoice->paid_amount_1 = null;
                $invoice->paid_date_1 = null;
            }

            $invoice->payment_edit_count = max(0, $invoice->payment_edit_count - 1);

            $invoice->status = $invoice->due_amount == 0
                ? 'Paid'
                : ($invoice->paid_amount > 0 ? 'Partial' : 'Due');

            // Mirrors the exact inverse of updateDuePayment()'s write-once-if-empty
            // guard (only ever set when due_amount hit 0) -- the reversal just
            // reopened due_amount above, so the condition that set this no longer
            // holds; letting the next genuine full payment set it again.
            $invoice->doctor_amount_collected_by = null;

            $invoice->payment_failed_reason = $request->reason;
            $invoice->payment_failed_by = Auth::id();
            $invoice->payment_failed_at = now();

            $invoice->updated_by = Auth::id();

            $invoice->save();

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Payment marked failed: ' . $request->reason
            );

            return response()->json([
                'status' => true,
                'message' => 'Payment marked as failed. Due amount reopened by ₹' . number_format($expectedAmount, 2) . '.'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function allocatePaymentToInvoiceItems(
        $invoiceNo,
        $paymentAmount,
        $phase
    ) {

        $invoice = Invoice::where(
            'invoice_no',
            $invoiceNo
        )->first();

        if (!$invoice || $paymentAmount <= 0) {
            return;
        }

        $details =
            DB::table('invoice_details')
                ->where(
                    'invoice_no',
                    $invoiceNo
                )
                ->orderBy('id')
                ->get();

        if ($details->count() == 0) {
            return;
        }

        $totalAmount =
            (float) $invoice->total_amount;

        $allocatedTotal = 0;

        foreach ($details as $index => $detail) {

            if ($index == ($details->count() - 1)) {

                // Absorbs whatever remainder keeps the sum of every item's
                // allocation equal to the actual amount collected -- since
                // every earlier item was just rounded to the nearest 10,
                // this naturally comes out to a multiple of 10 too whenever
                // the payment itself is one. round(..., 2) here is only a
                // floating-point-noise guard, not a real rounding rule.
                $allocated =
                    round($paymentAmount - $allocatedTotal, 2);

            } else {

                $allocated =
                    $this->roundToNearestTen(
                        (
                            $detail->amount /
                            $totalAmount
                        ) * $paymentAmount
                    );

                $allocatedTotal += $allocated;
            }

            if ($phase === 'INITIAL') {

                DB::table('invoice_details')
                    ->where('id', $detail->id)
                    ->update([
                        'initial_paid_amount' => $allocated,
                        'initial_paid_date' => now()
                    ]);
            } elseif ($phase === 'PHASE1') {

                DB::table('invoice_details')
                    ->where('id', $detail->id)
                    ->update([
                        'paid_amount_1' => $allocated,
                        'paid_date_1' => now()
                    ]);
            } elseif ($phase === 'PHASE2') {

                DB::table('invoice_details')
                    ->where('id', $detail->id)
                    ->update([
                        'paid_amount_2' => $allocated,
                        'paid_date_2' => now()
                    ]);
            }
        }
    }
    private function generatePatientCode()
    {
        $last =
            DB::table('patients')
                ->latest('id')
                ->first();

        $next =
            $last
            ? $last->id + 1
            : 1;

        return
            'PAT' .
            date('ym') .
            str_pad(
                $next,
                5,
                '0',
                STR_PAD_LEFT
            );
    }
    public function savePatient(Request $request)
    {
        $request->validate([
            'age' => 'nullable|digits_between:1,2',
        ], [
            'age.digits_between' => 'Age must be a number of up to 2 digits.',
        ]);

        DB::table('patients')->updateOrInsert(
            [
                'patient_id' => $request->patient_id
            ],
            [
                'patient_name' => $request->patient_name,
                'mobile_no' => $request->mobile_no,
                'age' => $request->age,
                'gender' => $request->gender,
                'status' => 'A',
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        DB::table('patient_mobile_numbers')
            ->updateOrInsert(
                [
                    'patient_id' => $request->patient_id,
                    'mobile_no' => $request->mobile_no
                ],
                [
                    'is_primary' => 'Y',
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );

        return response()->json([
            'status' => true
        ]);
    }

    public function getNewPatientDiscountEligibility(Request $request)
    {
        $messages = [];

        $age = (int) $request->age;

        if ($age >= 60) {

            $messages[] =
                'Patient is eligible for discount under Senior Citizen category.';
        }

        $isMember = $this->resolveMemberEligibility(
            $request->mobile_no ?? '',
            $request->patient_name ?? ''
        );

        if ($isMember) {

            $messages[] =
                'Patient is eligible for discount under Member / Employee category.';
        }

        return response()->json([
            'status' => true,
            'messages' => $messages,
            'is_member' => $isMember
        ]);
    }
   
    /*
    |--------------------------------------------------------------------------
    | PDF PRINT
    |--------------------------------------------------------------------------
    */

    public function printInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);

        $tests = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            // Package component rows aren't separately billed -- see the
            // identical filter in the WhatsApp PDF generation above.
            ->whereNull('package_parent_item_code_sub')
            ->get();

        $testCategoryNames = DB::table('invoice_item_masters')
            ->whereIn('item_code', $tests->pluck('item_code')->unique())
            ->pluck('item_name', 'item_code');

        $tests = $tests->map(function ($row) use ($testCategoryNames) {

                $row->test_name = $row->item_description;

                $row->test_category = $testCategoryNames->get($row->item_code);

                $row->discount =
                    $this->roundToNearestTen(($row->rate ?? 0) * ($row->standard_discount ?? 0) / 100)
                    +
                    $this->roundToNearestTen(($row->rate ?? 0) * ($row->additional_discount_percent ?? 0) / 100)
                    +
                    ($row->additional_discount_amount ?? 0);

                return $row;
            });

        $paperSize = $this->invoicePdfPaperSize($tests->count());

        $testDateText = \App\Support\ReportTestDates::applyToInvoiceLines($tests, $invoice);

        $pdf = Pdf::loadView(
            'apps-diagnostic-invoice-pdf',
            compact('invoice', 'tests', 'paperSize', 'testDateText')
        );

        $this->applyInvoicePdfPaperSize($pdf, $paperSize);

        $safeFileName =
            str_replace(
                ['/', '\\'],
                '-',
                $invoice->invoice_no
            ) . '.pdf';

        return $pdf->stream($safeFileName);
    }

    /**
     * @see self::PDF_SHORT_MAX_TESTS
     */
    private function invoicePdfPaperSize(int $testCount): string
    {
        return $testCount <= self::PDF_SHORT_MAX_TESTS ? 'SHORT' : 'STANDARD';
    }

    /**
     * @see self::PDF_SHORT_WIDTH_PT
     */
    /**
     * The page is always standard A4 -- $paperSize (SHORT/STANDARD) only
     * controls the view's content compacting, not the physical page size.
     * @see self::PDF_SHORT_MAX_TESTS
     */
    private function applyInvoicePdfPaperSize($pdf, string $paperSize): void
    {
        $pdf->setPaper('A4', 'portrait');
    }

    // Every percent-based discount (Standard Discount %, Additional Discount
    // %) is rounded to the nearest multiple of 10 once converted to a
    // currency amount, independently of any other discount on the same line
    // -- e.g. a 20% discount on a rate of 80 is 16, rounded to 20. Mirrors
    // roundToNearestTen() in apps-diagnostic-invoice.init.js.
    private function roundToNearestTen(float $value): float
    {
        return round($value / 10) * 10;
    }

    /**
     * A "manual" category item has no discount concept at all -- its rate
     * IS the staff-typed amount (see store()'s insert loop). Shared by the
     * floor-validation loop and the insert loop so both agree on which
     * items skip the discount formula.
     */
    private function isManualDiagnosticItem(?string $itemCode): bool
    {
        if (!$itemCode) {
            return false;
        }

        $itemMaster = DB::table('invoice_item_masters')
            ->where('item_code', $itemCode)
            ->first();

        return $itemMaster && stripos($itemMaster->item_name, 'manual') !== false;
    }

    /**
     * Server-side mirror of the amount formula in
     * apps-diagnostic-invoice.init.js's ".standardDiscount, ..." change
     * handler -- recomputed independently rather than trusting the
     * submitted 'amount', so a stale page or a request that bypasses the
     * browser entirely can't save an un-rounded or arithmetically wrong
     * line amount. Returns the discount-formula result only, rounded to
     * the nearest 10 -- the doctor-payable floor/waiver adjustment is
     * applied separately by the caller (store()), since the floor check
     * needs this "natural" figure to decide whether to reject the request.
     */
    private function computeDiagnosticLineAmount(array $row, bool $isManual): float
    {
        if ($isManual) {
            return (float) ($row['amount'] ?? 0);
        }

        $rate = (float) ($row['rate'] ?? 0);
        $standardDiscount = (float) ($row['standard_discount'] ?? 0);
        $additionalPercent = (float) ($row['additional_discount_percent'] ?? 0);
        $additionalAmount = (float) ($row['additional_discount_amount'] ?? 0);

        $standardDiscountAmount = $this->roundToNearestTen($rate * $standardDiscount / 100);
        $percentAmount = $this->roundToNearestTen($rate * $additionalPercent / 100);

        $amount = max(0, $rate - $standardDiscountAmount - $percentAmount - $additionalAmount);

        return $this->roundToNearestTen($amount);
    }

}
