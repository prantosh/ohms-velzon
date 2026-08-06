<?php


namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorPayable;
use App\Models\DoctorSettlement;
use App\Models\DoctorSettlementItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Barryvdh\DomPDF\Facade\Pdf;
class DoctorSettlementController extends Controller
{
    private const MODULE_CODE = 'DOCTOR_SETTLEMENT';

    /*
    |--------------------------------------------------------------------------
    | Settlement Entry Screen
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $doctors = Doctor::where('status', 'ACTIVE')
            ->orderBy('doctor_name')
            ->get([
                'id',
                'doctor_code',
                'doctor_name'
            ]);

        $users = \App\Models\User::whereIn('role', ['Admin', 'Supervisor', 'Employee'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $paymentModes = [

            'CASH'   => 'Cash',
            'BANK'   => 'Bank Transfer',
            'CHEQUE' => 'Cheque',
            'UPI'    => 'UPI'

        ];

        return view(
            'apps-doctor-settlement',
            compact(
                'doctors',
                'users',
                'paymentModes'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Outstanding Payables
    |--------------------------------------------------------------------------
    */

    public function getOutstandingPayables(Request $request)
    {
        try {

            $request->validate([

                'doctor_id' => 'required|exists:doctors,id',
                'user_id' => 'required|exists:users,id',
                'invoice_date' => 'required|date',

            ]);

            $rows = DoctorPayable::query()
                    ->join('invoices', 'doctor_payables.invoice_id', '=', 'invoices.id')
                    ->joinSub(
                        $this->finalCollectorSubquery(),
                        'fc',
                        'fc.invoice_reference',
                        '=',
                        'invoices.invoice_no'
                    )
                    ->select(
                        'doctor_payables.id as payable_id',
                        'doctor_payables.invoice_id',
                        'doctor_payables.invoice_no',
                        'invoices.invoice_date',
                        'doctor_payables.invoice_type',
                        'doctor_payables.transaction_type',
                        'doctor_payables.patient_id',
                        'doctor_payables.patient_name',
                        'doctor_payables.item_code',
                        'doctor_payables.item_description',
                        'doctor_payables.payable_amount',
                        'doctor_payables.paid_amount',
                        DB::raw('(doctor_payables.payable_amount - doctor_payables.paid_amount) as balance_amount')
                    )

                ->where('doctor_payables.doctor_id', $request->doctor_id)

                // The doctor's share isn't actually in anyone's hand until the
                // invoice itself is fully collected (due_amount = 0) -- a
                // partial collection is only ever allowed when it deliberately
                // stays within the non-doctor portion, so it must never be
                // attributed to whoever happened to make that partial payment.
                ->where('invoices.due_amount', 0)

                ->whereRaw(
                    'COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) = ?',
                    [$request->user_id]
                )

                ->whereDate('invoices.invoice_date', $request->invoice_date)

                ->whereRaw(
                    '(doctor_payables.payable_amount - IFNULL(doctor_payables.paid_amount,0)) > 0'
                )

                ->whereIn(
                    'doctor_payables.payment_status',
                    ['PENDING', 'APPROVED']
                )

                ->orderByDesc('doctor_payables.invoice_id')

                ->get();

            $rows->transform(function ($row) {

                $row->invoice_date = $row->invoice_date
                    ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                    : null;

                return $row;
            });

            return response()->json([

                'status' => true,

                'data' => $rows,

                'summary' => $this->getDoctorStatistics(
                    $request->doctor_id
                )

            ]);

        } catch (\Exception $e) {

            Log::error(
                'Doctor Settlement Outstanding Payables',
                [

                    'doctor_id' => $request->doctor_id,

                    'error' => $e->getMessage()

                ]
            );

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Final Collector Subquery
    |--------------------------------------------------------------------------
    | For a given invoice, multiple RECEIVED transactions may exist (phased
    | payments, possibly collected by different users). The doctor payment
    | for that invoice is the responsibility of whoever collected the LATEST
    | (final) installment -- earlier partial-payment collectors are excluded.
    */

    private function finalCollectorSubquery()
    {
        $ranked = DB::table('daily_transactions')
            ->select('invoice_reference', 'operator_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY invoice_reference ORDER BY created_at DESC) as rn')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('invoice_reference');

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->select('invoice_reference', 'operator_id as final_collector_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Settlement Status (by user + invoice date)
    |--------------------------------------------------------------------------
    | Shows every doctor payable (any status -- Pending/Approved/Paid/Cancelled)
    | for invoices dated on the selected date where the selected user collected
    | the final payment installment.
    */

    public function getSettlementStatusByUserDate(Request $request)
    {
        try {

            $request->validate([

                'user_id' => 'required|exists:users,id',
                'invoice_date' => 'required|date',

            ]);

            $rows = DoctorPayable::query()
                ->join('invoices', 'doctor_payables.invoice_id', '=', 'invoices.id')
                ->joinSub(
                    $this->finalCollectorSubquery(),
                    'fc',
                    'fc.invoice_reference',
                    '=',
                    'invoices.invoice_no'
                )
                ->select(
                    'doctor_payables.id as payable_id',
                    'doctor_payables.invoice_no',
                    'invoices.invoice_date',
                    'doctor_payables.doctor_name',
                    'doctor_payables.patient_name',
                    'doctor_payables.item_description',
                    'doctor_payables.payable_amount',
                    'doctor_payables.paid_amount',
                    'doctor_payables.payment_status',
                    'doctor_payables.last_settlement_no',
                    'doctor_payables.last_settlement_date',
                    DB::raw('(doctor_payables.payable_amount - IFNULL(doctor_payables.paid_amount,0)) as balance_amount')
                )
                ->where('invoices.due_amount', 0)
                ->whereRaw(
                    'COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) = ?',
                    [$request->user_id]
                )
                ->whereDate('invoices.invoice_date', $request->invoice_date)
                ->orderByDesc('doctor_payables.id')
                ->get();

            $rows->transform(function ($row) {

                $row->invoice_date = $row->invoice_date
                    ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                    : null;

                $row->last_settlement_date = $row->last_settlement_date
                    ? \Carbon\Carbon::parse($row->last_settlement_date)->format('d-m-Y')
                    : null;

                return $row;
            });

            return response()->json([

                'status' => true,

                'data' => $rows

            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Settlement Preview
    |--------------------------------------------------------------------------
    */

    public function previewSettlement(Request $request)
    {
        try {

            $summary =
                $this->calculateSettlementSummary(
                    $request
                );
            $this->validateSettlement($summary);
            return response()->json([

                'status' => true,

                'summary' => $summary

            ]);

        } catch (ValidationException $e) {

            return response()->json([

                'status' => false,

                'errors' => $e->errors()

            ], 422);

        } catch (\Exception $e) {

            Log::error(
                'Settlement Preview Failed',
                [

                    'user_id' => Auth::id(),

                    'error' => $e->getMessage()

                ]
            );

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }
        /*
|--------------------------------------------------------------------------
| Save Settlement
|--------------------------------------------------------------------------
*/

public function store(Request $request, AuditService $auditService)
{
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'settlement_date' => 'required|date',
            'payment_mode' => 'required|in:CASH,BANK,CHEQUE,UPI',
            'settlement_items' => 'required|array|min:1'
        ]);

    DB::beginTransaction();

    try {

        Log::info('Doctor Settlement Started', [
            'doctor_id' => $request->doctor_id,
            'user_id'   => Auth::id()
        ]);

        /*
        |--------------------------------------------------------------------------
        | Calculate & Validate
        |--------------------------------------------------------------------------
        */

        $summary = $this->calculateSettlementSummary($request);

        $this->validateSettlement($summary);

        /*
        |--------------------------------------------------------------------------
        | Create Settlement Master
        |--------------------------------------------------------------------------
        */

        $settlement = $this->createSettlement(
            $request,
            $summary
        );

        /*
        |--------------------------------------------------------------------------
        | Create Settlement Details
        |--------------------------------------------------------------------------
        */

        $this->createSettlementItems(
            $settlement,
            $summary['items']
        );

        /*
        |--------------------------------------------------------------------------
        | Update Doctor Payables
        |--------------------------------------------------------------------------
        */

        foreach ($summary['items'] as $item) {

            $this->updateDoctorPayable(
                $item,
                $settlement
            );

        }

        DB::commit();

        $auditService->logCreate(
            self::MODULE_CODE,
            $settlement,
            $settlement->only($settlement->getFillable()),
            'Doctor settlement created'
        );

        Log::info('Doctor Settlement Completed', [

            'settlement_no' => $settlement->settlement_no,

            'user_id' => Auth::id()

        ]);

        return response()->json([

            'status' => true,

            'message' => 'Settlement completed successfully.',

            'settlement_id' => $settlement->id,

            'settlement_no' => $settlement->settlement_no

        ]);

    }
    catch (ValidationException $e) {

        DB::rollBack();

        return response()->json([

            'status' => false,

            'errors' => $e->errors()

        ],422);

    }
    catch (\Exception $e) {

        DB::rollBack();

        Log::error(
            'Doctor Settlement Failed',
            [

                'user_id' => Auth::id(),

                'error' => $e->getMessage()

            ]
        );

        return response()->json([

            'status' => false,

            'message' => $e->getMessage()

        ],500);

    }

}
/*
|--------------------------------------------------------------------------
| Generate Settlement Number
|--------------------------------------------------------------------------
*/
    public const PREFIX_SETTLEMENT = 'SET';
private function generateSettlementNo()
{
    $monthYear = now()->format('my');

    $lastSettlement = DoctorSettlement::whereYear(
            'settlement_date',
            now()->year
        )
        ->whereMonth(
            'settlement_date',
            now()->month
        )
        ->lockForUpdate()          // <<<<<<<<<<<<<<
        ->orderByDesc('id')
        ->first();

    $runningNo = 1;

    if ($lastSettlement) {

        $parts = explode('/', $lastSettlement->settlement_no);

        $runningNo = intval(end($parts)) + 1;

    }

    return sprintf(
        \App\Support\OfflineMode::prefix('SET/') . '%s/%06d',
        $monthYear,
        $runningNo
    );
}

    /*
    |--------------------------------------------------------------------------
    | Validate Settlement
    |--------------------------------------------------------------------------
    */

    private function validateSettlement(array $summary)
    {
        if (empty($summary['items'])) {

            throw ValidationException::withMessages([

                'invoice' => [
                    'Please select at least one invoice.'
                ]

            ]);

        }

        if ($summary['gross_amount'] <= 0) {

            throw ValidationException::withMessages([

                'amount' => [
                    'Settlement amount must be greater than zero.'
                ]

            ]);

        }

        foreach ($summary['items'] as $item) {

            if (
                $item['settlement_amount'] <= 0
            ) {

                throw ValidationException::withMessages([

                    'amount' => [
                        'Invalid settlement amount.'
                    ]

                ]);

            }

            if (
                $item['settlement_amount'] >
                $item['balance_before_payment']
            ) {

                throw ValidationException::withMessages([

                    'amount' => [

                        'Settlement amount cannot exceed outstanding balance.'

                    ]

                ]);

            }

        }

    }

    /*
|--------------------------------------------------------------------------
| Calculate Settlement Summary
|--------------------------------------------------------------------------
*/

    private function calculateSettlementSummary(Request $request)
    {
        $gross = 0;

        $items = [];

        foreach ($request->settlement_items as $row) {

            $payable = DoctorPayable::findOrFail(
                $row['payable_id']
            );
            if ($payable->payable_amount - $payable->paid_amount <= 0) {

                throw ValidationException::withMessages([

                    'invoice' => [

                        'Invoice ' .
                        $payable->invoice_no .
                        ' has already been settled.'

                    ]

                ]);

            }
            


            if ($payable->doctor_id != $request->doctor_id) {
                throw ValidationException::withMessages([
                    'doctor_id' => ['Selected payable does not belong to the selected doctor.']
                ]);
            }
            $settlementAmount =
                (float) $row['settlement_amount'];

            $gross += $settlementAmount;

            $items[] = [

                'payable_id' => $payable->id,

                'invoice_id' => $payable->invoice_id,

                'invoice_no' => $payable->invoice_no,

                'invoice_type' => $payable->invoice_type,

                'transaction_type' => $payable->transaction_type,

                'patient_id' => $payable->patient_id,

                'patient_name' => $payable->patient_name,

                'item_code' => $payable->item_code,

                'item_description' => $payable->item_description,

                'payable_amount' => $payable->payable_amount,

                'previous_paid_amount' => $payable->paid_amount,

                'balance_before_payment' => $payable->payable_amount - $payable->paid_amount,

                'settlement_amount' => $settlementAmount,

                'balance_after_payment' =>
                    $payable->payable_amount - $payable->paid_amount
                    - $settlementAmount

            ];

        }

        $deduction =
            (float) $request->deduction_amount;

        return [

            'gross_amount' => $gross,

            'deduction_amount' => $deduction,

            'net_amount' => $gross - $deduction,

            'selected_invoice_count' =>
                count($items),

            'selected_payable_count' =>
                count($items),

            'items' => $items

        ];

    }
    /*
|--------------------------------------------------------------------------
| Create Settlement Master
|--------------------------------------------------------------------------
*/

    private function createSettlement(Request $request, array $summary)
    {
        $doctor = Doctor::findOrFail($request->doctor_id);

        return DoctorSettlement::create([

            'settlement_no' => $this->generateSettlementNo(),

            'voucher_no' => null,

            'settlement_date' => $request->settlement_date,

            'doctor_id' => $doctor->id,

            'doctor_code' => $doctor->doctor_code,

            'doctor_name' => $doctor->doctor_name,

            'payment_mode' => $request->payment_mode,

            'bank_name' => $request->bank_name,

            'cheque_no' => $request->cheque_no,

            'utr_no' => $request->utr_no,

            'gross_amount' => $summary['gross_amount'],

            'deduction_amount' => $summary['deduction_amount'],

            'net_amount' => $summary['net_amount'],

            'remarks' => $request->remarks,

            'internal_notes' => $request->internal_notes,

            'status' => 'COMPLETED',

            'created_by' => Auth::id()

        ]);
    }
    /*
|--------------------------------------------------------------------------
| Create Settlement Details
|--------------------------------------------------------------------------
*/

    private function createSettlementItems(
        DoctorSettlement $settlement,
        array $items
    ) {
        foreach ($items as $item) {

            DoctorSettlementItem::create([

                'settlement_id' => $settlement->id,

                'payable_id' => $item['payable_id'],

                'invoice_id' => $item['invoice_id'],

                'invoice_no' => $item['invoice_no'],

                'invoice_type' => $item['invoice_type'],

                'transaction_type' => $item['transaction_type'],

                'patient_id' => $item['patient_id'],

                'patient_name' => $item['patient_name'],

                'item_code' => $item['item_code'],

                'item_description' => $item['item_description'],

                'payable_amount' => $item['payable_amount'],

                'previous_paid_amount' => $item['previous_paid_amount'],

                'balance_before_payment' => $item['balance_before_payment'],

                'settlement_amount' => $item['settlement_amount'],

                'balance_after_payment' => $item['balance_after_payment'],

                'created_by' => Auth::id()

            ]);

            $this->recordDailyTransactionForSettlement(
                $settlement,
                $item
            );

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Record Daily Transaction (one row per settlement item, mirrors the
    | RECEIVED/REFUND insert convention used by invoice collection/cancellation)
    |--------------------------------------------------------------------------
    */

    private function recordDailyTransactionForSettlement(
        DoctorSettlement $settlement,
        array $item
    ) {
        $paymentModeMap = [
            'CASH' => 'Cash',
            'BANK' => 'Bank',
            'CHEQUE' => 'Cheque',
            'UPI' => 'UPI',
        ];

        $transactionNo =
            'TRN/' .
            now()->format('ymd') .
            '/' .
            str_pad(
                DB::table('daily_transactions')->count() + 1,
                6,
                '0',
                STR_PAD_LEFT
            );

        DB::table('daily_transactions')->insert([

            'transaction_date' => $settlement->settlement_date,

            'transaction_no' => $transactionNo,

            'transaction_type' => 'PAYMENT',

            'received_amount' => 0,

            'refund_amount' => 0,

            'doctor_payment_amount' => $item['settlement_amount'],

            'invoice_reference' => $item['invoice_no'],

            'patient_id' => $item['patient_id'],

            'patient_name' => $item['patient_name'],

            'operator_id' => Auth::id(),

            'operator_name' => Auth::user()->name,

            'payment_mode' => $paymentModeMap[$settlement->payment_mode] ?? 'Cash',

            'reference_no' => $settlement->settlement_no,

            'remarks' => 'Doctor Payment - Settlement ' . $settlement->settlement_no,

            'status' => 'ACTIVE',

            'created_by' => Auth::id(),

            'created_at' => now(),

            'updated_at' => now(),

        ]);
    }
    /*
|--------------------------------------------------------------------------
| Update Doctor Payable
|--------------------------------------------------------------------------
*/

    private function updateDoctorPayable(
        array $item,
        DoctorSettlement $settlement
    ) {
        $payable = DoctorPayable::lockForUpdate()
            ->findOrFail($item['payable_id']);

        $newPaid = round(
            $payable->paid_amount + $item['settlement_amount'],
            2
        );

        $newBalance =
            $payable->payable_amount -
            $newPaid;
        $newBalance = max(0, round($newBalance, 2));
        $payable->paid_amount = $newPaid;

        

        $newBalance = max(
            0,
            round($payable->payable_amount - $newPaid, 2)
        );

        $payable->paid_amount = $newPaid;

        if ($newBalance <= 0) {

            $payable->payment_status = 'PAID';

        } else {

            $payable->payment_status = 'APPROVED';

        }

        /*
        |--------------------------------------------------------------------------
        | Settlement Information
        |--------------------------------------------------------------------------
        */

        $payable->last_settlement_id =
            $settlement->id;

        if (empty($payable->first_settlement_date)) {

            $payable->first_settlement_date =
                $settlement->settlement_date;

        }

        $payable->last_settlement_no =
            $settlement->settlement_no;

        $payable->last_settlement_date =
            $settlement->settlement_date;

        $payable->settlement_count =
            ($payable->settlement_count ?? 0) + 1;

        $payable->updated_by = Auth::id();

        $payable->save();
    }
    /*
|--------------------------------------------------------------------------
| Reverse Doctor Payable
|--------------------------------------------------------------------------
*/

    private function reverseDoctorPayable(
        DoctorSettlementItem $item
    ) {
        $payable = DoctorPayable::lockForUpdate()
            ->findOrFail($item->payable_id);

        $payable->paid_amount -=
            $item->settlement_amount;

        $payable->payment_status = 'APPROVED';

        $payable->settlement_count =
            max(
                0,
                $payable->settlement_count - 1
            );

        $payable->updated_by = Auth::id();

        $payable->save();
    }
    /*
|--------------------------------------------------------------------------
| Settlement Register
|--------------------------------------------------------------------------
*/

    public function register()
    {
        $rows = DoctorSettlement::with('doctor')
            ->latest('settlement_date')
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([

            'status' => true,

            'data' => $rows

        ]);
    }
    /*
|--------------------------------------------------------------------------
| Cancel Settlement
|--------------------------------------------------------------------------
*/

    public function cancel(Request $request, $id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $settlement = DoctorSettlement::with('items')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($settlement->status == 'CANCELLED') {

                throw ValidationException::withMessages([

                    'settlement' => [
                        'Settlement already cancelled.'
                    ]

                ]);

            }

            $oldSettlementData = $settlement->only($settlement->getFillable());

            foreach ($settlement->items as $item) {

                $this->reverseDoctorPayable($item);

            }

            DB::table('daily_transactions')
                ->where('reference_no', $settlement->settlement_no)
                ->where('transaction_type', 'PAYMENT')
                ->update([
                    'status' => 'CANCELLED',
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            $settlement->status = 'CANCELLED';

            $settlement->cancellation_reason =
                $request->cancellation_reason;

            $settlement->cancelled_by = Auth::id();

            $settlement->cancelled_at = now();
            $settlement->updated_by = Auth::id();
            $settlement->save();

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $settlement,
                $oldSettlementData,
                $settlement->only($settlement->getFillable()),
                'Doctor settlement cancelled'
            );

            return response()->json([

                'status' => true,

                'message' =>
                    'Settlement cancelled successfully.'

            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Settlement Cancellation Failed', [

                'settlement_id' => $id,

                'error' => $e->getMessage()

            ]);

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);

        }

    }
    /*
|--------------------------------------------------------------------------
| Print Voucher
|--------------------------------------------------------------------------
*/

    public function printVoucher($id)
    {
        $settlement = DoctorSettlement::with('items')
            ->findOrFail($id);

        return view(

            'apps-doctor-settlement-voucher',

            compact('settlement')

        );
    }
    /*
|--------------------------------------------------------------------------
| Settlement PDF
|--------------------------------------------------------------------------
*/

    public function pdfVoucher($id)
    {
        $settlement = DoctorSettlement::with([
            'doctor',
            'items',
            'creator'
        ])->findOrFail($id);

        $pdf = Pdf::loadView(
            'apps-doctor-settlement-pdf',
            [
                'settlement' => $settlement,
                'doctor' => $settlement->doctor,
                'items' => $settlement->items
            ]
        );

        $pdf->setPaper('A4', 'portrait');

        $fileName = str_replace(
            ['/', '\\'],
            '-',
            $settlement->settlement_no
        ) . '.pdf';

        return Pdf::loadView(
            'apps-doctor-settlement-pdf',
            [
                'settlement' => $settlement,
                'doctor' => $settlement->doctor,
                'items' => $settlement->items
            ]
        )->stream($fileName);
    }
    /*
|--------------------------------------------------------------------------
| WhatsApp Voucher
|--------------------------------------------------------------------------
*/

    public function sendWhatsapp($id)
    {
        $settlement = DoctorSettlement::findOrFail($id);

        /*
        Generate PDF

        Upload PDF

        Call WatiService

        Log WhatsApp
        */

    }
    /*
|--------------------------------------------------------------------------
| Settlement History
|--------------------------------------------------------------------------
*/

    private function getSettlementHistory($payableId)
    {
        return DoctorSettlementItem::with('settlement')

            ->where('payable_id', $payableId)

            ->whereHas('settlement', function ($q) {

                $q->where(
                    'status',
                    'COMPLETED'
                );

            })

            ->orderBy('id')

            ->get();
    }
    private function getDoctorStatistics($doctorId)
    {
        $doctor = Doctor::find($doctorId);

        $rows = DoctorPayable::where('doctor_id', $doctorId)
            ->whereNotIn('payment_status', ['PAID', 'CANCELLED'])
            ->get();

        $outstandingAmount = $rows->sum(function ($row) {
            return $row->payable_amount - $row->paid_amount;
        });

        $lastSettlement = DoctorSettlement::where('doctor_id', $doctorId)
            ->latest('settlement_date')
            ->first();

        $monthAmount = DoctorSettlement::where('doctor_id', $doctorId)
            ->whereMonth('settlement_date', now()->month)
            ->whereYear('settlement_date', now()->year)
            ->sum('net_amount');

        $yearAmount = DoctorSettlement::where('doctor_id', $doctorId)
            ->whereYear('settlement_date', now()->year)
            ->sum('net_amount');

        return [
            'consultation_fee' => $doctor->consultation_fee_doctor ?? 0,
            'invoice_count' => $rows->count(),
            'outstanding_amount' => round($outstandingAmount, 2),
            'last_settlement' => $lastSettlement
                ? $lastSettlement->settlement_date->format('d-m-Y')
                : '--',
            'month_amount' => round($monthAmount, 2),
            'year_amount' => round($yearAmount, 2),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | User-wise Settlement Entry Screen
    |--------------------------------------------------------------------------
    */

    public function indexByUser()
    {
        $users = \App\Models\User::whereIn('role', ['Admin', 'Supervisor', 'Employee'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $paymentModes = [

            'CASH'   => 'Cash',
            'BANK'   => 'Bank Transfer',
            'CHEQUE' => 'Cheque',
            'UPI'    => 'UPI'

        ];

        return view(
            'apps-doctor-settlement-by-user',
            compact(
                'users',
                'paymentModes'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Outstanding Payables (by user + invoice date, grouped by doctor)
    |--------------------------------------------------------------------------
    */

    public function getOutstandingPayablesByUserDate(Request $request)
    {
        try {

            $request->validate([

                'user_id' => 'required|exists:users,id',
                'invoice_date' => 'required|date',

            ]);

            $rows = DoctorPayable::query()
                    ->join('invoices', 'doctor_payables.invoice_id', '=', 'invoices.id')
                    ->joinSub(
                        $this->finalCollectorSubquery(),
                        'fc',
                        'fc.invoice_reference',
                        '=',
                        'invoices.invoice_no'
                    )
                    ->select(
                        'doctor_payables.id as payable_id',
                        'doctor_payables.invoice_id',
                        'doctor_payables.invoice_no',
                        'invoices.invoice_date',
                        'doctor_payables.invoice_type',
                        'doctor_payables.transaction_type',
                        'doctor_payables.patient_id',
                        'doctor_payables.patient_name',
                        'doctor_payables.item_code',
                        'doctor_payables.item_description',
                        'doctor_payables.payable_amount',
                        'doctor_payables.paid_amount',
                        'doctor_payables.doctor_id',
                        'doctor_payables.doctor_name',
                        DB::raw('(doctor_payables.payable_amount - IFNULL(doctor_payables.paid_amount,0)) as balance_amount')
                    )

                // The doctor's share isn't actually in anyone's hand until the
                // invoice itself is fully collected (due_amount = 0) -- and it
                // belongs to whoever collected it, not whoever created the
                // invoice (those are frequently different people once a due
                // payment is collected later by someone else).
                ->where('invoices.due_amount', 0)

                ->whereRaw(
                    'COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) = ?',
                    [$request->user_id]
                )

                ->whereDate('invoices.invoice_date', $request->invoice_date)

                ->whereRaw(
                    '(doctor_payables.payable_amount - IFNULL(doctor_payables.paid_amount,0)) > 0'
                )

                ->whereIn(
                    'doctor_payables.payment_status',
                    ['PENDING', 'APPROVED']
                )

                ->orderBy('doctor_payables.doctor_name')

                ->orderByDesc('doctor_payables.invoice_id')

                ->get();

            $rows->transform(function ($row) {

                $row->invoice_date = $row->invoice_date
                    ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                    : null;

                return $row;
            });

            $groups = $rows->groupBy('doctor_id')->map(function ($group) {

                return [
                    'doctor_id' => $group->first()->doctor_id,
                    'doctor_name' => $group->first()->doctor_name,
                    'items' => $group->values(),
                    'total_outstanding' => round($group->sum('balance_amount'), 2),
                ];
            })->values();

            return response()->json([

                'status' => true,

                'groups' => $groups,

                'summary' => [
                    'total_doctors' => $groups->count(),
                    'total_invoices' => $rows->count(),
                    'total_outstanding' => round($rows->sum('balance_amount'), 2),
                ]

            ]);

        } catch (\Exception $e) {

            Log::error(
                'Doctor Settlement Outstanding By User',
                [

                    'user_id' => $request->user_id,

                    'error' => $e->getMessage()

                ]
            );

            return response()->json([

                'status' => false,

                'message' => $e->getMessage()

            ], 500);

        }
    }

    }
