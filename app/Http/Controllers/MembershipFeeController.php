<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\MembershipFeePayment;
use App\Models\MembershipFeeRate;
use App\Models\User;
use App\Models\WhatsappAutoSendSetting;
use App\Services\AuditService;
use App\Services\WatiService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MembershipFeeController extends Controller
{
    private const MODULE_CODE = 'MEMBERSHIP_FEE';

    private const ITEM_CODE = 'MEM001';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-membership-fee');
    }

    /*
    |--------------------------------------------------------------------------
    | SEARCH MEMBER BY MOBILE
    |--------------------------------------------------------------------------
    */

    public function searchMember(Request $request)
    {
        $request->validate([
            'mobile_no' => 'required'
        ]);

        $member = User::where('mobile_no', trim($request->mobile_no))
            ->whereIn('role', ['Member', 'Supervisor'])
            ->first();

        if (!$member) {

            return response()->json([
                'status' => false,
                'message' => 'No member found with this mobile number.'
            ]);
        }

        $lastPaidMonth = MembershipFeePayment::where('user_id', $member->id)
            ->max('payment_month');

        $nextDueMonth = $lastPaidMonth
            ? Carbon::parse($lastPaidMonth)->addMonthNoOverflow()->startOfMonth()
            : $this->safeJoiningMonth($member);

        return response()->json([
            'status' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'mobile_no' => $member->mobile_no,
                'role' => $member->role,
                'status' => $member->status,
            ],
            'last_paid_month' => $lastPaidMonth
                ? Carbon::parse($lastPaidMonth)->format('Y-m')
                : null,
            'next_due_month' => $nextDueMonth->format('Y-m'),
            'current_month' => now()->format('Y-m'),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW MONTHS (RATE + AMOUNT, EXCLUDING ALREADY RECORDED MONTHS)
    |--------------------------------------------------------------------------
    */

    public function previewMonths(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_month' => 'required',
            'to_month' => 'required',
        ]);

        $months = $this->monthsBetween($request->from_month, $request->to_month);

        if (empty($months)) {

            return response()->json([
                'status' => false,
                'message' => 'The "To Month" must not be before the "From Month".'
            ]);
        }

        $alreadyRecorded = MembershipFeePayment::where('user_id', $request->user_id)
            ->whereIn('payment_month', $months)
            ->pluck('payment_month')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $rows = [];

        $total = 0;

        $skipped = 0;

        foreach ($months as $month) {

            if (in_array($month, $alreadyRecorded)) {

                $skipped++;

                continue;
            }

            $rate = MembershipFeeRate::rateForMonth(substr($month, 0, 7));

            $rows[] = [
                'month' => substr($month, 0, 7),
                'label' => Carbon::parse($month)->format('F Y'),
                'rate' => $rate,
            ];

            $total += $rate;
        }

        return response()->json([
            'status' => true,
            'months' => $rows,
            'total_amount' => round($total, 2),
            'skipped_already_paid' => $skipped,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE PAYMENT (ONE INVOICE, MULTIPLE MONTHS)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'from_month' => 'required',
            'to_month' => 'required',
            'payment_mode' => 'required',
            'remarks' => 'nullable|string',
        ]);

        $member = User::where('id', $request->user_id)
            ->whereIn('role', ['Member', 'Supervisor'])
            ->first();

        if (!$member) {

            return response()->json([
                'status' => false,
                'message' => 'Member not found.'
            ], 422);
        }

        $months = $this->monthsBetween($request->from_month, $request->to_month);

        if (empty($months)) {

            return response()->json([
                'status' => false,
                'message' => 'The "To Month" must not be before the "From Month".'
            ], 422);
        }

        $alreadyRecorded = MembershipFeePayment::where('user_id', $member->id)
            ->whereIn('payment_month', $months)
            ->pluck('payment_month')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $payableMonths = array_values(array_diff($months, $alreadyRecorded));

        if (empty($payableMonths)) {

            return response()->json([
                'status' => false,
                'message' => 'All selected months have already been paid or adjusted.'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $lineItems = [];

            $total = 0;

            foreach ($payableMonths as $month) {

                $rate = MembershipFeeRate::rateForMonth(substr($month, 0, 7));

                $lineItems[] = [
                    'month' => $month,
                    'label' => Carbon::parse($month)->format('F Y'),
                    'rate' => $rate,
                ];

                $total += $rate;
            }

            $total = round($total, 2);

            $invoiceNo = $this->generateInvoiceNo();

            $invoice = Invoice::create([

                'invoice_no' => $invoiceNo,
                'invoice_type' => 'MEMBERSHIP_FEE',
                'invoice_category' => 'NON_PATHOLOGY',

                'patient_id' => $member->id,
                'patient_name' => $member->name,
                'patient_mobile_no' => $member->mobile_no,

                'invoice_date' => now()->toDateString(),

                'total_amount' => $total,
                'discount' => 0,
                'paid_amount' => $total,
                'due_amount' => 0,

                'payment_mode' => $request->payment_mode,
                'received_by' => Auth::id(),
                'remarks' => $request->remarks,

                'status' => 'Active',

                'created_by' => Auth::id()
            ]);

            foreach ($lineItems as $line) {

                DB::table('invoice_details')->insert([

                    'invoice_no' => $invoiceNo,
                    'item_code' => self::ITEM_CODE,
                    'item_description' => 'Membership Fee - ' . $line['label'],

                    'quantity' => 1,
                    'rate' => $line['rate'],
                    'amount' => $line['rate'],

                    'remarks' => $request->remarks,

                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                MembershipFeePayment::create([

                    'user_id' => $member->id,
                    'payment_month' => $line['month'],
                    'rate_applied' => $line['rate'],
                    'amount' => $line['rate'],
                    'payment_type' => 'PAID',
                    'invoice_no' => $invoiceNo,
                    'remarks' => $request->remarks,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            $this->recordLedgerEntry(
                'RECEIVED',
                $total,
                $invoiceNo,
                (string) $member->id,
                $member->name,
                $request->payment_mode,
                'Membership Fee (' . count($payableMonths) . ' month(s))'
            );

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $invoice,
                array_merge(
                    $invoice->only($invoice->getFillable()),
                    ['months_paid' => array_column($lineItems, 'label')]
                ),
                'Membership fee collected'
            );

            if (WhatsappAutoSendSetting::isEnabled('INVOICE')) {
                $whatsappSent = $this->generateAndSendInvoice($invoice->id);
            } else {
                WhatsappAutoSendSetting::logSkipped('INVOICE', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
                $whatsappSent = false;
            }

            return response()->json([
                'status' => true,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoiceNo,
                'months_paid' => count($payableMonths),
                'total_amount' => $total,
                'whatsapp_sent' => $whatsappSent,
                'message' => count($payableMonths) . ' month(s) paid successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to record membership fee payment.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN: ADJUST LAST PAID MONTH (NO FINANCIAL TRANSACTION)
    |--------------------------------------------------------------------------
    */

    public function adjust(Request $request, AuditService $auditService)
    {
        if (optional(Auth::user())->role !== 'Admin') {

            return response()->json([
                'status' => false,
                'message' => 'Only an Admin can adjust the last paid month.'
            ], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'target_month' => 'required',
            'remarks' => 'nullable|string',
        ]);

        $member = User::where('id', $request->user_id)
            ->whereIn('role', ['Member', 'Supervisor'])
            ->first();

        if (!$member) {

            return response()->json([
                'status' => false,
                'message' => 'Member not found.'
            ], 422);
        }

        $lastPaidMonth = MembershipFeePayment::where('user_id', $member->id)
            ->max('payment_month');

        $targetMonth = Carbon::parse($request->target_month . '-01')->startOfMonth();

        if ($lastPaidMonth && $targetMonth->lte(Carbon::parse($lastPaidMonth))) {

            return response()->json([
                'status' => false,
                'message' => 'Target month must be after the current last paid month ('
                    . Carbon::parse($lastPaidMonth)->format('Y-m') . ').'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $rate = MembershipFeeRate::rateForMonth($targetMonth->format('Y-m'));

            MembershipFeePayment::create([

                'user_id' => $member->id,
                'payment_month' => $targetMonth->toDateString(),
                'rate_applied' => $rate,
                'amount' => $rate,
                'payment_type' => 'ADJUSTED',
                'invoice_no' => null,
                'remarks' => $request->remarks ?? 'Manually adjusted by admin',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            $auditService->logAction(
                self::MODULE_CODE,
                $member,
                'CUSTOM',
                "Last paid month manually set to {$request->target_month} (no financial transaction)"
            );

            return response()->json([
                'status' => true,
                'message' => "Last paid month updated to {$request->target_month}. No invoice or financial transaction was created."
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to adjust last paid month.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PAYMENT HISTORY
    |--------------------------------------------------------------------------
    */

    public function history($userId)
    {
        $rows = MembershipFeePayment::where('user_id', $userId)
            ->orderByDesc('payment_month')
            ->get();

        $rows->transform(function ($row) {

            $row->month_label = Carbon::parse($row->payment_month)->format('F Y');

            $row->created_dt = optional($row->created_at)->format('d-m-Y H:i');

            $row->invoice_id = $row->invoice_no
                ? Invoice::where('invoice_no', $row->invoice_no)->value('id')
                : null;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $rows
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT
    |--------------------------------------------------------------------------
    */

    public function printInvoice($id)
    {
        $invoice = Invoice::where('invoice_type', 'MEMBERSHIP_FEE')
            ->findOrFail($id);

        $months = MembershipFeePayment::where('invoice_no', $invoice->invoice_no)
            ->orderBy('payment_month')
            ->get();

        $receivedByName = $invoice->received_by
            ? DB::table('users')->where('id', $invoice->received_by)->value('name')
            : null;

        $pdf = Pdf::loadView(
            'apps-membership-fee-pdf',
            compact('invoice', 'months', 'receivedByName')
        );

        $safeFileName = str_replace(['/', '\\'], '-', $invoice->invoice_no) . '.pdf';

        return $pdf->stream($safeFileName);
    }

    /*
    |--------------------------------------------------------------------------
    | RESEND WHATSAPP
    |--------------------------------------------------------------------------
    */

    public function sendWhatsapp($id)
    {
        $sent = $this->generateAndSendInvoice($id);

        return response()->json([
            'status' => $sent,
            'message' => $sent
                ? 'Invoice sent via WhatsApp.'
                : 'Unable to send invoice via WhatsApp.'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function generateAndSendInvoice($invoiceId): bool
    {
        try {

            $invoice = Invoice::findOrFail($invoiceId);

            $months = MembershipFeePayment::where('invoice_no', $invoice->invoice_no)
                ->orderBy('payment_month')
                ->get();

            $receivedByName = $invoice->received_by
                ? DB::table('users')->where('id', $invoice->received_by)->value('name')
                : null;

            $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';

            $pdf = Pdf::loadView(
                'apps-membership-fee-pdf',
                compact('invoice', 'months', 'receivedByName')
            );

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            $sent = false;

            $responseBody = null;

            try {

                $response = WatiService::sendInvoice(
                    $invoice->patient_mobile_no,
                    $invoice->patient_name,
                    $invoice->invoice_no,
                    $pdfUrl,
                    now()->format('d-m-Y H:i'),
                    'membership_fees'
                );

                $sent = $response->successful();

                $responseBody = $response->body();

            } catch (\Throwable $e) {

                Log::error('Membership Fee WhatsApp Send Failed: ' . $e->getMessage());

                $responseBody = $e->getMessage();
            }

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'patient_name' => $invoice->patient_name,
                'message_type' => 'INVOICE',
                'status' => $sent ? 'SENT' : 'FAILED',
                'response' => $responseBody,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('invoices')->where('id', $invoice->id)->update([

                'whatsapp_sent_at' => now(),
                'whatsapp_status' => $sent ? 'SENT' : 'FAILED',
                'whatsapp_send_count' => DB::raw('whatsapp_send_count + 1')
            ]);

            return $sent;

        } catch (Exception $e) {

            Log::error('Membership Fee WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    private function safeJoiningMonth(User $member): Carbon
    {
        if (!$member->date_of_joining) {
            return now()->startOfMonth();
        }

        try {

            $date = Carbon::parse($member->date_of_joining);

            if ($date->year < 1990) {
                return now()->startOfMonth();
            }

            return $date->startOfMonth();

        } catch (Exception $e) {

            return now()->startOfMonth();
        }
    }

    private function monthsBetween(string $fromMonth, string $toMonth): array
    {
        try {

            $from = Carbon::parse($fromMonth . '-01')->startOfMonth();
            $to = Carbon::parse($toMonth . '-01')->startOfMonth();

        } catch (Exception $e) {

            return [];
        }

        if ($to->lt($from)) {
            return [];
        }

        $months = [];

        $cursor = $from->copy();

        while ($cursor->lte($to)) {

            $months[] = $cursor->toDateString();

            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    private function recordLedgerEntry(
        string $type,
        float $amount,
        string $invoiceNo,
        ?string $patientId,
        ?string $patientName,
        string $paymentMode,
        string $remarks
    ): void {

        $transactionNo = \App\Support\OfflineMode::prefix('TRN/') .
            now()->format('ymd') .
            '/' .
            str_pad(DB::table('daily_transactions')->count() + 1, 6, '0', STR_PAD_LEFT);

        DB::table('daily_transactions')->insert([

            'transaction_date' => now()->toDateString(),
            'transaction_no' => $transactionNo,
            'transaction_type' => $type,

            'received_amount' => $type === 'RECEIVED' ? $amount : 0,
            'refund_amount' => $type === 'REFUND' ? $amount : 0,

            'invoice_reference' => $invoiceNo,

            'patient_id' => $patientId,
            'patient_name' => $patientName,

            'operator_id' => Auth::id(),
            'operator_name' => Auth::user()->name,

            'payment_mode' => $paymentMode,

            'remarks' => $remarks,
            'status' => 'ACTIVE',

            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function generateInvoiceNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('MEM/') .
            now()->format('my') .
            '/' .
            str_pad(Auth::id(), 3, '0', STR_PAD_LEFT);

        $lastInvoice = Invoice::where('invoice_no', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $serial = 1;

        if ($lastInvoice) {

            $explode = explode('/', $lastInvoice->invoice_no);

            $serial = intval(end($explode)) + 1;
        }

        return $prefix . '/' . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
