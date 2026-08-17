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
use Illuminate\Support\Facades\Log;
class DiagnosticInvoiceController extends Controller
{
    private const MODULE_CODE = 'DIAGNOSTIC_INVOICE';
    private const PATIENT_MODULE_CODE = 'PATIENT';

    /**
     * A patient qualifies for the member-tier discount if a Member/Supervisor
     * user (MEMBER_TIER_ROLES convention, see UserController) shares the
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
            ->whereIn('role', ['Member', 'Supervisor'])
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

            $invoices->transform(function ($row) {

                $row->invoice_date = $row->invoice_date
                    ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                    : null;

                $row->test_date = $row->test_date
                    ? \Carbon\Carbon::parse($row->test_date)->format('d-m-Y')
                    : null;

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
        $tests = InvoiceItemDetail::where('item_code', $itemCode)

            ->where('status', 'Y')

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

       


        DB::beginTransaction();

        try {


            $tests = $request->input('tests', []);

            $authorisedApproverIds = DB::table('users')
                ->where('authorised', 'YES')
                ->pluck('id')
                ->all();

            foreach ($tests as $test) {

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

                // The doctor's payable for this item is fixed per the
                // master table regardless of discount, so a discount can
                // never push the patient's payable for that item below
                // what's owed to the doctor -- otherwise ABSSRK would be
                // paying the doctor more than it collected for the item.
                // Exempted when the doctor is waiving their fee for this
                // line entirely (doctor_payment_waived) -- there the patient
                // amount is *expected* to drop below the scheduled figure,
                // by design.
                $doctorPayable = (float) ($test['payment_value'] ?? 0);

                $itemAmount = (float) ($test['amount'] ?? 0);

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

                $amount = (float) ($row['amount'] ?? 0);

                $isWaived = !empty($row['doctor_payment_waived']);

                $isManual = false;

                $itemMaster = DB::table('invoice_item_masters')
                    ->where('item_code', $row['item_code'])
                    ->first();

                if (
                    $itemMaster &&
                    stripos($itemMaster->item_name, 'manual') !== false
                ) {
                    $isManual = true;
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
            $transactionNo =
                \App\Support\OfflineMode::prefix('TRN/') .
                now()->format('ymd') .
                '/' .
                str_pad(
                    DB::table(
                        'daily_transactions'
                    )->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            DB::table(
                'daily_transactions'
            )->insert([

                        'transaction_date' =>
                            $request->invoice_date ?: now()->toDateString(),

                        'transaction_no' =>
                            $transactionNo,

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
                'Patient is eligible for discount under Member category.';
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

            $pdf =
                Pdf::loadView(
                    'apps-diagnostic-invoice-pdf',
                    [
                        'invoice' => $invoice,
                        'tests' => $tests
                    ]
                );

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
                'status' => true
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

            $transactionNo =
                \App\Support\OfflineMode::prefix('TRN/') .
                now()->format('ymd') .
                '/' .
                str_pad(
                    DB::table('daily_transactions')->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            DB::table('daily_transactions')->insert([

                'transaction_date' =>
                    now()->toDateString(),

                'transaction_no' =>
                    $transactionNo,

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

    public function destroy($id, AuditService $auditService)
    {
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

            $approverId = \App\Support\InvoiceCancellationApproval::resolveApprover($invoice);

            if ($approverId === false) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => \App\Support\InvoiceCancellationApproval::NOT_TODAY_MESSAGE
                ], 422);
            }

            $oldInvoiceData = $invoice->only($invoice->getFillable());

            $invoice->cancelled = 'Y';

            $invoice->cancelled_by =
                Auth::id();

            $invoice->cancelled_at =
                now();

            $invoice->cancellation_approved_by =
                $approverId;

            $invoice->refund_amount =
                $invoice->paid_amount;

            $invoice->refund_date =
                now();

            $invoice->save();

            $transactionNo =
                \App\Support\OfflineMode::prefix('TRN/') .
                now()->format('ymd') .
                '/' .
                str_pad(
                    DB::table('daily_transactions')->count() + 1,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

            DB::table('daily_transactions')
                ->insert([

                    'transaction_date' =>
                        now()->toDateString(),

                    'transaction_no' =>
                        $transactionNo,

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

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Diagnostic invoice cancelled'
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

                $allocated =
                    round(
                        $paymentAmount - $allocatedTotal,
                        2
                    );

            } else {

                $allocated =
                    round(
                        (
                            $detail->amount /
                            $totalAmount
                        ) * $paymentAmount,
                        2
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
                'Patient is eligible for discount under Member category.';
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

        $pdf = Pdf::loadView(
            'apps-diagnostic-invoice-pdf',
            compact('invoice', 'tests')
        );

        $safeFileName =
            str_replace(
                ['/', '\\'],
                '-',
                $invoice->invoice_no
            ) . '.pdf';

        return $pdf->stream($safeFileName);
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

}
