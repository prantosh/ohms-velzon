<?php

namespace App\Http\Controllers;

use App\Models\AmbulanceDestinationMaster;
use App\Models\Invoice;
use App\Services\AuditService;
use App\Services\WatiService;
use App\Mail\DiscountApprovedMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class AmbulanceRentalController extends Controller
{
    private const MODULE_CODE = 'AMBULANCE_RENTAL';

    private const ITEM_CODE = 'AMB001';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-ambulance-rental');
    }

    /*
    |--------------------------------------------------------------------------
    | SETTINGS (DESTINATIONS + APPROVERS + STAFF)
    |--------------------------------------------------------------------------
    */

    public function settings()
    {
        return response()->json([
            'status' => true,
            'destinations' => AmbulanceDestinationMaster::where('status', 'ACTIVE')
                ->orderBy('destination_name')
                ->get(['id', 'destination_name', 'fare_ac', 'fare_nonac']),
            'approvers' => DB::table('users')
                ->where('authorised', 'YES')
                ->orderBy('name')
                ->get(['id', 'name']),
            'staff' => DB::table('users')
                ->orderBy('name')
                ->get(['id', 'name'])
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = Invoice::where('invoices.invoice_type', 'AMBULANCE_RENT')
            ->leftJoin('invoice_details', 'invoice_details.invoice_no', '=', 'invoices.invoice_no')
            ->leftJoin(
                'ambulance_destination_masters',
                'ambulance_destination_masters.id',
                '=',
                'invoice_details.ambulance_destination_id'
            )
            ->select(
                'invoices.*',
                'ambulance_destination_masters.destination_name',
                'invoice_details.booking_type',
                'invoice_details.from_destination',
                'invoice_details.pickup_time',
                'invoice_details.release_time',
                'invoice_details.waiting_charge'
            );

        if ($request->filled('status')) {
            $query->where('invoices.status', $request->status);
        }

        $rentals = $query
            ->orderByDesc('invoices.id')
            ->paginate($perPage);

        $rentals->getCollection()->transform(function ($row) {

            $row->invoice_date = $row->invoice_date
                ? \Carbon\Carbon::parse($row->invoice_date)->format('d-m-Y')
                : null;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $rentals->items(),
            'pagination' => [
                'current_page' => $rentals->currentPage(),
                'last_page' => $rentals->lastPage(),
                'total' => $rentals->total()
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE (ONE-TIME ENTRY BILLING)
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([

            'destination_id' => 'required|exists:ambulance_destination_masters,id',
            'booking_type' => 'required|in:AC,NONAC',

            'patient_id' => 'required',
            'patient_name' => 'required',

            'from_destination' => 'nullable|max:255',
            'address' => 'nullable',

            'booking_date' => 'nullable|date',
            'pickup_time' => 'required|date',
            'release_time' => 'nullable|date|after_or_equal:pickup_time',

            'odometer_pickup_km' => 'nullable|numeric|min:0',
            'odometer_release_km' => 'nullable|numeric|min:0|gte:odometer_pickup_km',

            'waiting_charge' => 'nullable|numeric|min:0',

            'discount_amount' => 'nullable|numeric|min:0',
            'discount_approved_by' => 'nullable|exists:users,id,authorised,YES',
            'discount_remarks' => 'nullable|string',

            'received_amount' => 'required|numeric|min:0',
            'payment_mode' => 'required',
            'received_by' => 'nullable|exists:users,id',

            'remarks' => 'nullable|string',
        ]);

        $discountAmount = (float) ($request->discount_amount ?? 0);

        if ($discountAmount > 0 && !$request->discount_approved_by) {

            return response()->json([
                'status' => false,
                'message' => 'Select the approving authority before applying a discount.'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $destination = AmbulanceDestinationMaster::findOrFail($request->destination_id);

            $fare = $request->booking_type === 'AC'
                ? $destination->fare_ac
                : $destination->fare_nonac;

            $waitingCharge = (float) ($request->waiting_charge ?? 0);

            $actualAmount = round($fare + $waitingCharge, 2);

            if ($discountAmount > $actualAmount) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Discount cannot exceed the actual amount.'
                ], 422);
            }

            $netPayable = round($actualAmount - $discountAmount, 2);

            $receivedAmount = (float) $request->received_amount;

            $dueAmount = max(0, round($netPayable - $receivedAmount, 2));

            if (!DB::table('patients')->where('patient_id', $request->patient_id)->exists()) {

                DB::table('patients')->insert([
                    'patient_id' => $request->patient_id,
                    'patient_name' => $request->patient_name,
                    'mobile_no' => $request->patient_mobile_no,
                    'age' => $request->patient_age,
                    'gender' => $request->patient_gender,
                    'status' => 'A',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            $invoiceNo = $this->generateInvoiceNo();

            $invoice = Invoice::create([

                'invoice_no' => $invoiceNo,
                'invoice_type' => 'AMBULANCE_RENT',
                'invoice_category' => 'NON_PATHOLOGY',

                'patient_id' => $request->patient_id,
                'patient_name' => $request->patient_name,
                'patient_mobile_no' => $request->patient_mobile_no,
                'patient_age' => $request->patient_age,
                'patient_gender' => $request->patient_gender,

                'invoice_date' => $request->booking_date ?? now()->toDateString(),

                'total_amount' => $actualAmount,
                'discount' => $discountAmount,
                'paid_amount' => $receivedAmount,
                'due_amount' => $dueAmount,

                'payment_mode' => $request->payment_mode,
                'received_by' => $request->received_by ?? Auth::id(),
                'remarks' => $request->remarks,

                'status' => 'Active',

                'created_by' => Auth::id()
            ]);

            DB::table('invoice_details')->insert([

                'invoice_no' => $invoiceNo,
                'item_code' => self::ITEM_CODE,
                'item_description' => $destination->destination_name,
                'ambulance_destination_id' => $destination->id,

                'booking_type' => $request->booking_type,
                'from_destination' => $request->from_destination,
                'address' => $request->address,

                'pickup_time' => $request->pickup_time,
                'release_time' => $request->release_time,

                'odometer_pickup_km' => $request->odometer_pickup_km,
                'odometer_release_km' => $request->odometer_release_km,

                'waiting_charge' => $waitingCharge,

                'quantity' => 1,
                'rate' => $fare,
                'additional_discount_amount' => $discountAmount,
                'discount_approved_by' => $request->discount_approved_by,
                'discount_approval_remarks' => $request->discount_remarks,
                'amount' => $netPayable,

                'remarks' => $request->remarks,

                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            if ($receivedAmount > 0) {

                $this->recordLedgerEntry(
                    'RECEIVED',
                    $receivedAmount,
                    $invoiceNo,
                    $request->patient_id,
                    $request->patient_name,
                    $request->payment_mode,
                    'Ambulance Rental'
                );
            }

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $invoice,
                $invoice->only($invoice->getFillable()),
                'Ambulance rental invoice created'
            );

            $this->generateAndSendInvoice($invoice->id);

            if ($discountAmount > 0) {

                $this->sendDiscountApprovalEmail(
                    $request->discount_approved_by,
                    $invoiceNo,
                    $request->patient_name,
                    $discountAmount,
                    $request->discount_remarks
                );
            }

            return response()->json([
                'status' => true,
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoiceNo,
                'message' => 'Ambulance rental invoice created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to create ambulance rental invoice.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */

    public function cancel($id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $invoice = Invoice::where('invoice_type', 'AMBULANCE_RENT')
                ->findOrFail($id);

            if ($invoice->cancelled === 'Y') {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'This invoice has already been cancelled.'
                ], 422);
            }

            $approverId = \App\Support\InvoiceCancellationApproval::resolveApprover($invoice);

            if ($approverId === false) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => \App\Support\InvoiceCancellationApproval::NOT_TODAY_MESSAGE
                ], 422);
            }

            $oldData = $invoice->only($invoice->getFillable());

            $invoice->update([
                'status' => 'Cancelled',
                'cancelled' => 'Y',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now(),
                'cancellation_approved_by' => $approverId
            ]);

            if ($invoice->paid_amount > 0) {

                $this->recordLedgerEntry(
                    'REFUND',
                    $invoice->paid_amount,
                    $invoice->invoice_no,
                    $invoice->patient_id,
                    $invoice->patient_name,
                    $invoice->payment_mode,
                    'Ambulance Rental Cancelled'
                );
            }

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldData,
                $invoice->only($invoice->getFillable()),
                'Ambulance rental invoice cancelled'
            );

            return response()->json([
                'status' => true,
                'message' => 'Ambulance rental invoice cancelled successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to cancel ambulance rental invoice.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT
    |--------------------------------------------------------------------------
    */

    public function printInvoice($id)
    {
        $invoice = Invoice::where('invoice_type', 'AMBULANCE_RENT')
            ->findOrFail($id);

        $detail = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->first();

        $approverName = $detail && $detail->discount_approved_by
            ? DB::table('users')->where('id', $detail->discount_approved_by)->value('name')
            : null;

        $receivedByName = $invoice->received_by
            ? DB::table('users')->where('id', $invoice->received_by)->value('name')
            : null;

        $pdf = Pdf::loadView(
            'apps-ambulance-rental-pdf',
            compact('invoice', 'detail', 'approverName', 'receivedByName')
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

    private function sendDiscountApprovalEmail(
        $approverId,
        string $invoiceNo,
        string $patientName,
        float $discountAmount,
        ?string $remarks
    ): void {

        try {

            $approver = DB::table('users')->where('id', $approverId)->first();

            if (!$approver || !$approver->email) {
                return;
            }

            Mail::to($approver->email)->send(new DiscountApprovedMail(
                $approver->name,
                $invoiceNo,
                $patientName,
                $discountAmount,
                $remarks,
                optional(Auth::user())->name
            ));

        } catch (Exception $e) {

            Log::error('Discount approval email failed: ' . $e->getMessage());
        }
    }

    private function generateAndSendInvoice($invoiceId): bool
    {
        try {

            $invoice = Invoice::findOrFail($invoiceId);

            $detail = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->first();

            $approverName = $detail && $detail->discount_approved_by
                ? DB::table('users')->where('id', $detail->discount_approved_by)->value('name')
                : null;

            $receivedByName = $invoice->received_by
                ? DB::table('users')->where('id', $invoice->received_by)->value('name')
                : null;

            $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';

            $pdf = Pdf::loadView(
                'apps-ambulance-rental-pdf',
                compact('invoice', 'detail', 'approverName', 'receivedByName')
            );

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            $response = WatiService::sendInvoice(
                $invoice->patient_mobile_no,
                $invoice->patient_name,
                $invoice->invoice_no,
                $pdfUrl,
                now()->format('d-m-Y H:i'),
                'ambulance_invoice'
            );

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
                'message_type' => 'INVOICE',
                'status' => $response->successful() ? 'SENT' : 'FAILED',
                'response' => $response->body(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('invoices')->where('id', $invoice->id)->update([

                'whatsapp_sent_at' => now(),
                'whatsapp_status' => $response->successful() ? 'SENT' : 'FAILED',
                'whatsapp_send_count' => DB::raw('whatsapp_send_count + 1')
            ]);

            return $response->successful();

        } catch (Exception $e) {

            Log::error('Ambulance Rental WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    private function generateInvoiceNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('AMB/') .
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
}
