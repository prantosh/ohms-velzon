<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Doctor;
use App\Models\DoctorTestPaymentMaster;
use App\Services\AuditService;
use Carbon\Carbon;
use App\Models\DoctorTestPaymentHistory;
class DoctorTestPaymentMasterController extends Controller
{
    private const MODULE_CODE = 'DOCTOR_TEST_PAYMENT_MASTER';

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $specialisations =
            Doctor::whereNotNull('specialisation')
                ->where('specialisation', '!=', '')
                ->distinct()
                ->orderBy('specialisation')
                ->pluck('specialisation');

        $categories =
            DB::table('invoice_item_masters')
                ->where('status', 1)
                ->orderBy('item_name')
                ->get();

        $staffDoctor =
            Doctor::where('doctor_name', 'STAFF')
                ->first(['id', 'doctor_code', 'doctor_name']);

        return view(
            'apps-doctor-test-payment-master',
            compact(
                'specialisations',
                'categories',
                'staffDoctor'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD DOCTORS BY SPECIALISATION
    |--------------------------------------------------------------------------
    */

    public function doctorsBySpecialisation(Request $request)
    {
        $specialisation = $request->query('specialisation');

        return response()->json(
            Doctor::where('specialisation', $specialisation)
                ->orWhereHas('specialisations', function ($query) use ($specialisation) {
                    $query->where('category', $specialisation);
                })
                ->orderBy('doctor_name')
                ->distinct()
                ->get(['id', 'doctor_code', 'doctor_name'])
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list()
    {
        try {

            $data =
                DoctorTestPaymentMaster::query()

                    ->leftJoin(
                        'doctors',
                        'doctor_test_payment_masters.doctor_id',
                        '=',
                        'doctors.id'
                    )

                    ->leftJoin(
                        'invoice_item_masters',
                        'doctor_test_payment_masters.item_code',
                        '=',
                        'invoice_item_masters.item_code'
                    )

                    ->select(
                        'doctor_test_payment_masters.*',

                        DB::raw("
                            CASE
                                WHEN doctor_test_payment_masters.doctor_id = 999
                                THEN 'ALL DOCTORS'
                                ELSE IFNULL(doctors.doctor_name,'')
                            END AS doctor_name
                        "),

                        'invoice_item_masters.item_name',

                        DB::raw("
        DATE_FORMAT(
            doctor_test_payment_masters.effective_from,
            '%d-%m-%Y %H:%i'
        ) AS effective_from_display
    ")
                    )

                    ->orderBy(
                        'doctor_test_payment_masters.id',
                        'desc'
                    )

                    ->get()
                    ->map(function ($row) {

                        if ($row->doctor_id == 999) {

                            $row->doctor_name = 'ALL DOCTORS';
                        }

                        $row->effective_from_display =
                            $row->effective_from
                            ? Carbon::parse($row->effective_from)
                                ->format('d-m-Y H:i')
                            : '';

                        return $row;
                    });

            return response()->json([

                'status' => true,

                'data' => $data
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,

                'message' =>
                    $e->getMessage()

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD TESTS BY CATEGORY
    |--------------------------------------------------------------------------
    */

    public function getTests($itemCode)
    {
        return response()->json(

            DB::table(
                'invoice_item_details'
            )

                ->where(
                    'item_code',
                    $itemCode
                )

                ->where(
                    'status',
                    'Y'
                )

                ->select(

                    'item_code_sub',

                    'item_description_sub'
                )

                ->orderBy(
                    'item_description_sub'
                )

                ->get()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STORE / UPDATE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        if ($request->id) {

            return $this->updatePayment($request, $auditService);
        }

        return $this->createPayments($request, $auditService);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE (one row per checked doctor)
    |--------------------------------------------------------------------------
    */

    private function createPayments(Request $request, AuditService $auditService)
    {
        $request->validate([

            'doctor_ids' => 'required|array|min:1',
            'item_code' => 'required',
            'item_code_sub' => 'required',

            'payment_type' => 'required',
            'payment_value' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $test =
                DB::table('invoice_item_details')
                    ->where('item_code', $request->item_code)
                    ->where('item_code_sub', $request->item_code_sub)
                    ->first();

            $created = 0;

            $skipped = [];

            $createdRows = [];

            foreach (array_unique($request->doctor_ids) as $doctorIdRaw) {

                $doctorId = $doctorIdRaw == 'ALL' ? '999' : $doctorIdRaw;

                $duplicate = DoctorTestPaymentMaster::where('doctor_id', $doctorId)
                    ->where('item_code_sub', $request->item_code_sub)
                    ->exists();

                if ($duplicate) {

                    $skipped[] = $doctorId == '999'
                        ? 'ALL DOCTORS'
                        : optional(Doctor::find($doctorId))->doctor_name ?? $doctorId;

                    continue;
                }

                $payment = DoctorTestPaymentMaster::create([

                    'doctor_id' => $doctorId,

                    'item_code' => $request->item_code,

                    'item_code_sub' => $request->item_code_sub,

                    'item_description_sub' =>
                        $test ? $test->item_description_sub : null,

                    'payment_type' => $request->payment_type,

                    'payment_value' => $request->payment_value,

                    'effective_from' => Carbon::today()->format('Y-m-d'),

                    'status' => $request->status,

                    'created_by' => Auth::id(),

                    'updated_by' => Auth::id()
                ]);

                $createdRows[] = $payment;

                $created++;
            }

            DB::commit();

            foreach ($createdRows as $payment) {

                $auditService->logCreate(
                    self::MODULE_CODE,
                    $payment,
                    $payment->only($payment->getFillable()),
                    'Doctor test payment rule created'
                );
            }

            $message = "Payment rule saved for {$created} doctor(s).";

            if (!empty($skipped)) {

                $message .= ' Skipped (already exists): ' . implode(', ', $skipped) . '.';
            }

            return response()->json([

                'status' => true,

                'message' => $message
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
    | UPDATE (single existing row -- doctor/item/test remain locked)
    |--------------------------------------------------------------------------
    */

    private function updatePayment(Request $request, AuditService $auditService)
    {
        $request->validate([

            'payment_type' => 'required',
            'payment_value' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $payment =
                DoctorTestPaymentMaster::findOrFail(
                    $request->id
                );

            $oldData = $payment->only($payment->getFillable());

            $effectiveFrom =
                $payment->effective_from;

            if (
                $payment->payment_value !=
                $request->payment_value
            ) {

                DoctorTestPaymentHistory::create([

                    'doctor_test_payment_master_id'
                    => $payment->id,

                    'doctor_id'
                    => $payment->doctor_id,

                    'item_code'
                    => $payment->item_code,

                    'item_code_sub'
                    => $payment->item_code_sub,

                    'item_description_sub'
                    => $payment->item_description_sub,

                    'payment_type'
                    => $payment->payment_type,

                    'old_value'
                    => $payment->payment_value,

                    'new_value'
                    => $request->payment_value,

                    'changed_on'
                    => now(),

                    'changed_by'
                    => Auth::id()
                ]);

                $effectiveFrom =
                    Carbon::today()->format('Y-m-d');
            }

            $payment->update([

                'payment_type'
                => $request->payment_type,

                'payment_value'
                => $request->payment_value,

                'effective_from'
                => $effectiveFrom,

                'status'
                => $request->status,

                'updated_by'
                => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $payment,
                $oldData,
                $payment->only($payment->getFillable()),
                'Doctor test payment rule updated'
            );

            return response()->json([

                'status' => true,

                'message' =>
                    'Doctor Payment rule saved successfully.'
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

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        try {

            $row =
                DoctorTestPaymentMaster::findOrFail(
                    $id
                );

            $oldData = $row->only($row->getFillable());

            $row->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $row,
                $oldData,
                'Doctor test payment rule deleted'
            );

            return response()->json([

                'status' => true,

                'message' =>
                    'Deleted successfully.'
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,

                'message' =>
                    $e->getMessage()

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET SINGLE RECORD
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        try {

            $row =
                DoctorTestPaymentMaster::findOrFail(
                    $id
                );

            return response()->json([

                'status' => true,

                'data' => $row
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,

                'message' =>
                    $e->getMessage()

            ], 500);
        }
    }
}
