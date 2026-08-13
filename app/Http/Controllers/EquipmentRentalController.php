<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\EquipmentCategory;
use App\Models\Ctrl;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\WatiService;
use App\Mail\DiscountApprovedMail;
use Illuminate\Support\Facades\Mail;
use App\Services\AuditService;
use Carbon\Carbon;
use Exception;

class EquipmentRentalController extends Controller
{
    private const RENTAL_TYPES = ['OXYGEN_RENT', 'CONCENTRATOR_RENT'];

    private const ITEM_CODES = [
        'OXYGEN_RENT' => 'OXY001',
        'CONCENTRATOR_RENT' => 'CON001'
    ];

    private const MODULE_CODE = 'EQUIPMENT_RENTAL';

    private const PATIENT_MODULE_CODE = 'PATIENT';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-equipment-rentals');
    }

    /*
    |--------------------------------------------------------------------------
    | SETTINGS (RATES + AVAILABLE CATEGORIES)
    |--------------------------------------------------------------------------
    */

    public function settings()
    {
        return response()->json([
            'status' => true,
            'rates' => Ctrl::current(),
            'categories' => EquipmentCategory::where('status', 'ACTIVE')
                ->orderBy('category_name')
                ->get(['id', 'category_name', 'available_quantity']),
            'approvers' => DB::table('users')
                ->where('authorised', 'YES')
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

        $query = Invoice::whereIn('invoices.invoice_type', self::RENTAL_TYPES)
            ->whereNull('invoices.reference_no')
            ->leftJoin('invoice_details', 'invoice_details.invoice_no', '=', 'invoices.invoice_no')
            ->leftJoin('equipment_categories', 'equipment_categories.id', '=', 'invoice_details.equipment_category_id')
            ->leftJoin('invoices as final_invoices', 'final_invoices.reference_no', '=', 'invoices.invoice_no')
            ->leftJoin('invoice_details as final_invoice_details', 'final_invoice_details.invoice_no', '=', 'final_invoices.invoice_no')
            ->select(
                'invoices.*',
                'equipment_categories.category_name',
                'invoice_details.quantity',
                'final_invoice_details.rental_return_date',
                'final_invoice_details.rental_days',
                'final_invoices.id as final_invoice_id',
                'final_invoices.invoice_no as final_invoice_no',
                'final_invoices.total_amount as final_invoice_amount',
                'final_invoices.whatsapp_status as final_whatsapp_status'
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

            $row->rental_return_date = $row->rental_return_date
                ? \Carbon\Carbon::parse($row->rental_return_date)->format('d-m-Y')
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
    | ISSUE
    |--------------------------------------------------------------------------
    */

    public function issue(Request $request, AuditService $auditService)
    {
        $request->validate([
            'rental_type' => 'required|in:' . implode(',', self::RENTAL_TYPES),
            'category_id' => 'required|exists:equipment_categories,id',
            'quantity' => 'required|integer|min:1',
            'patient_id' => 'required',
            'patient_name' => 'required',
            'advance_amount' => 'required|numeric|min:0',
            'payment_mode' => 'required',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_approved_by' => 'nullable|exists:users,id,authorised,YES',
            'discount_remarks' => 'nullable|string',
            'low_advance_reason' => 'nullable|string'
        ]);

        $discountAmount = (float) ($request->discount_amount ?? 0);

        if ($discountAmount > 0 && !$request->discount_approved_by) {

            return response()->json([
                'status' => false,
                'message' => 'Select the approving authority before applying a discount.'
            ], 422);
        }

        if ($discountAmount > $request->advance_amount) {

            return response()->json([
                'status' => false,
                'message' => 'Discount cannot exceed the advance amount.'
            ], 422);
        }

        // Minimum advance is a soft default, not a hard floor -- staff can
        // accept less, but must record why.
        $ctrl = Ctrl::current();

        $minimumAdvance = ($request->rental_type === 'OXYGEN_RENT'
            ? $ctrl->oxygen_min_advance
            : $ctrl->concentrator_min_advance) * $request->quantity;

        if ($request->advance_amount < $minimumAdvance && !$request->filled('low_advance_reason')) {

            return response()->json([
                'status' => false,
                'message' => 'Advance is below the usual minimum of ' .
                    number_format($minimumAdvance, 2) . ' -- please enter a reason.'
            ], 422);
        }

        DB::beginTransaction();

        try {

            $category = EquipmentCategory::findOrFail($request->category_id);

            if ($category->available_quantity < $request->quantity) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => "Only {$category->available_quantity} unit(s) of {$category->category_name} available."
                ], 422);
            }

            $patient = Patient::where('patient_id', $request->patient_id)->first();

            if (!$patient) {

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

            } else {

                // Age/gender are editable on the Issue Equipment form even
                // for an existing patient (only these two -- name stays
                // locked) -- propagate any correction back to the shared
                // patients record, same pattern as
                // DoctorVisitInvoiceController.
                $oldPatientData = $patient->only(['age', 'gender']);

                $patient->update([
                    'age' => $request->patient_age,
                    'gender' => $request->patient_gender,
                ]);

                $newPatientData = $patient->only(['age', 'gender']);

                if ($oldPatientData !== $newPatientData) {

                    $auditService->logUpdate(
                        self::PATIENT_MODULE_CODE,
                        $patient,
                        $oldPatientData,
                        $newPatientData,
                        'Patient age/gender corrected during equipment rental issue'
                    );
                }
            }

            $invoiceNo = $this->generateInvoiceNo($request->rental_type);

            $netAdvance = round($request->advance_amount - $discountAmount, 2);

            $invoice = Invoice::create([

                'invoice_no' => $invoiceNo,
                'invoice_type' => $request->rental_type,
                'invoice_category' => 'NON_PATHOLOGY',

                'patient_id' => $request->patient_id,
                'patient_name' => $request->patient_name,
                'patient_mobile_no' => $request->patient_mobile_no,
                'patient_age' => $request->patient_age,
                'patient_gender' => $request->patient_gender,

                'invoice_date' => now()->toDateString(),

                'total_amount' => $request->advance_amount,
                'discount' => $discountAmount,
                'paid_amount' => $netAdvance,
                'due_amount' => 0,

                'payment_mode' => $request->payment_mode,
                'remarks' => $request->remarks,
                'low_advance_reason' => $request->low_advance_reason,

                'status' => 'Issued',

                'created_by' => Auth::id()
            ]);

            DB::table('invoice_details')->insert([

                'invoice_no' => $invoiceNo,
                'item_code' => self::ITEM_CODES[$request->rental_type],
                'item_description' => $category->category_name,
                'equipment_category_id' => $category->id,

                'quantity' => $request->quantity,
                'rate' => $request->advance_amount / $request->quantity,
                'additional_discount_amount' => $discountAmount,
                'discount_approved_by' => $request->discount_approved_by,
                'discount_approval_remarks' => $request->discount_remarks,
                'amount' => $netAdvance,

                'remarks' => $request->remarks,

                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $category->decrement('available_quantity', $request->quantity);

            $this->recordLedgerEntry(
                'RECEIVED',
                $netAdvance,
                $invoiceNo,
                $request->patient_id,
                $request->patient_name,
                $request->payment_mode,
                ucwords(strtolower(str_replace('_', ' ', $request->rental_type))) . ' - Advance'
            );

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $invoice,
                $invoice->only($invoice->getFillable()),
                'Equipment issued'
            );

            $this->generateAndSendInvoice($invoice->id, false);

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
                'message' => 'Equipment issued successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to issue equipment.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN / SETTLE
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | SETTLEMENT PREVIEW (READ-ONLY -- SAME RATE LOGIC AS RETURNEQUIPMENT)
    |--------------------------------------------------------------------------
    */

    public function previewSettlement(Request $request, $id)
    {
        $request->validate([
            'return_date' => 'required|date',
            'units' => 'nullable|integer|min:0',
        ]);

        $invoice = Invoice::whereIn('invoice_type', self::RENTAL_TYPES)
            ->findOrFail($id);

        if ($invoice->status !== 'Issued') {

            return response()->json([
                'status' => false,
                'message' => 'This rental has already been settled or cancelled.'
            ], 422);
        }

        if ($invoice->invoice_type === 'OXYGEN_RENT' && !$request->filled('units')) {

            return response()->json([
                'status' => false,
                'message' => 'Enter the oxygen units (kg) consumed.'
            ], 422);
        }

        $detail = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->first();

        [$days, , $finalAmount] = $this->calculateRentalCharge(
            $invoice,
            $detail,
            $request->return_date,
            $request->units
        );

        $advanceAmount = (float) $invoice->paid_amount;

        return response()->json([
            'status' => true,
            'days_used' => $days,
            'advance_amount' => $advanceAmount,
            'rent_amount' => $finalAmount,
            'settlement_amount' => round($finalAmount - $advanceAmount, 2)
        ]);
    }

    // Oxygen is billed additively: (oxygen_rate_per_day x days) +
    // (oxygen_rate_per_unit x units of oxygen (kg) actually consumed) --
    // two separate rate components, not one rate multiplied by both.
    // "units" is unrelated to invoice_details.quantity, which only tracks
    // how many cylinders were issued for inventory purposes.
    // Concentrator is billed flat on days alone, regardless of how many
    // concentrators were issued.
    private function calculateRentalCharge(Invoice $invoice, $detail, string $returnDate, $units = null): array
    {
        $days = max(
            1,
            Carbon::parse($invoice->invoice_date)->diffInDays(Carbon::parse($returnDate))
        );

        $ctrl = Ctrl::current();

        if ($invoice->invoice_type === 'OXYGEN_RENT') {

            $dayRate = $ctrl->oxygen_rate_per_day;

            $finalAmount = round(
                ($dayRate * $days) + ($ctrl->oxygen_rate_per_unit * (float) $units),
                2
            );

        } else {

            $dayRate = $ctrl->concentrator_rate_per_day;

            $finalAmount = round($dayRate * $days, 2);
        }

        return [$days, $dayRate, $finalAmount];
    }

    public function returnEquipment(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'return_date' => 'required|date',
            'payment_mode' => 'required',
            'units' => 'nullable|integer|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_approved_by' => 'nullable|exists:users,id,authorised,YES',
            'discount_remarks' => 'nullable|string'
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

            $invoice = Invoice::whereIn('invoice_type', self::RENTAL_TYPES)
                ->findOrFail($id);

            if ($invoice->status !== 'Issued') {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'This rental has already been settled or cancelled.'
                ], 422);
            }

            if ($invoice->invoice_type === 'OXYGEN_RENT' && !$request->filled('units')) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Enter the oxygen units (kg) consumed.'
                ], 422);
            }

            $detail = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->first();

            [$days, $perUnit, $finalAmount] = $this->calculateRentalCharge($invoice, $detail, $request->return_date, $request->units);

            if ($discountAmount > $finalAmount) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Discount cannot exceed the final rental charge.'
                ], 422);
            }

            $netFinalAmount = round($finalAmount - $discountAmount, 2);

            $settlement = round($netFinalAmount - $invoice->paid_amount, 2);

            $oldInvoiceData = $invoice->only($invoice->getFillable());

            $invoice->update([
                'status' => 'Returned',
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Equipment rental marked as returned'
            );

            $finalInvoiceNo = $this->generateInvoiceNo($invoice->invoice_type);

            $finalInvoice = Invoice::create([

                'invoice_no' => $finalInvoiceNo,
                'invoice_type' => $invoice->invoice_type,
                'invoice_category' => 'NON_PATHOLOGY',
                'reference_no' => $invoice->invoice_no,

                'patient_id' => $invoice->patient_id,
                'patient_name' => $invoice->patient_name,
                'patient_mobile_no' => $invoice->patient_mobile_no,
                'patient_age' => $invoice->patient_age,
                'patient_gender' => $invoice->patient_gender,

                'invoice_date' => $request->return_date,

                'total_amount' => $finalAmount,
                'discount' => $discountAmount,
                'paid_amount' => $netFinalAmount,
                'due_amount' => 0,

                'payment_mode' => $request->payment_mode,
                'remarks' => 'Final settlement for rental ' . $invoice->invoice_no,

                'status' => 'Settled',

                'created_by' => Auth::id()
            ]);

            DB::table('invoice_details')->insert([

                'invoice_no' => $finalInvoiceNo,
                'item_code' => $detail->item_code,
                'item_description' => $detail->item_description,
                'equipment_category_id' => $detail->equipment_category_id,

                'quantity' => $detail->quantity,
                'rate' => $perUnit,
                'additional_discount_amount' => $discountAmount,
                'discount_approved_by' => $request->discount_approved_by,
                'discount_approval_remarks' => $request->discount_remarks,
                'amount' => $netFinalAmount,

                'rental_return_date' => $request->return_date,
                'rental_days' => $days,
                'oxygen_units_consumed' => $invoice->invoice_type === 'OXYGEN_RENT'
                    ? $request->units
                    : null,

                'remarks' => $request->remarks,

                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            if ($detail->equipment_category_id) {

                EquipmentCategory::where('id', $detail->equipment_category_id)
                    ->increment('available_quantity', $detail->quantity);
            }

            if ($settlement > 0) {

                $this->recordLedgerEntry(
                    'RECEIVED',
                    $settlement,
                    $finalInvoiceNo,
                    $invoice->patient_id,
                    $invoice->patient_name,
                    $request->payment_mode,
                    'Rental Settlement - Additional Due Collected'
                );

            } elseif ($settlement < 0) {

                $this->recordLedgerEntry(
                    'REFUND',
                    abs($settlement),
                    $finalInvoiceNo,
                    $invoice->patient_id,
                    $invoice->patient_name,
                    $request->payment_mode,
                    'Rental Settlement - Deposit Refund'
                );
            }

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $finalInvoice,
                $finalInvoice->only($finalInvoice->getFillable()),
                'Equipment rental final settlement invoice created'
            );

            $this->generateAndSendInvoice($finalInvoice->id, true, $invoice->paid_amount, $settlement);

            if ($discountAmount > 0) {

                $this->sendDiscountApprovalEmail(
                    $request->discount_approved_by,
                    $finalInvoiceNo,
                    $invoice->patient_name,
                    $discountAmount,
                    $request->discount_remarks
                );
            }

            return response()->json([
                'status' => true,
                'final_invoice_id' => $finalInvoice->id,
                'final_invoice_no' => $finalInvoiceNo,
                'days_used' => $days,
                'final_amount' => $netFinalAmount,
                'settlement_amount' => $settlement,
                'message' => 'Equipment returned and settled successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to settle equipment return.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL (ONLY WHILE STILL ISSUED)
    |--------------------------------------------------------------------------
    */

    public function cancel($id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $invoice = Invoice::whereIn('invoice_type', self::RENTAL_TYPES)
                ->findOrFail($id);

            if ($invoice->status !== 'Issued') {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Only an unreturned rental can be cancelled.'
                ], 422);
            }

            if (!\App\Support\InvoiceCancellationApproval::isToday($invoice)) {

                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'Only a rental issued today can be cancelled.'
                ], 422);
            }

            $detail = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->first();

            $oldInvoiceData = $invoice->only($invoice->getFillable());

            $invoice->update([
                'status' => 'Cancelled',
                'cancelled' => 'Y',
                'cancelled_by' => Auth::id(),
                'cancelled_at' => now()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Equipment rental cancelled'
            );

            if ($detail && $detail->equipment_category_id) {

                EquipmentCategory::where('id', $detail->equipment_category_id)
                    ->increment('available_quantity', $detail->quantity);
            }

            if ($invoice->paid_amount > 0) {

                $this->recordLedgerEntry(
                    'REFUND',
                    $invoice->paid_amount,
                    $invoice->invoice_no,
                    $invoice->patient_id,
                    $invoice->patient_name,
                    $invoice->payment_mode,
                    'Rental Cancelled - Advance Refunded'
                );
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Rental cancelled successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to cancel rental.'
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
        $invoice = Invoice::whereIn('invoice_type', self::RENTAL_TYPES)
            ->findOrFail($id);

        $detail = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->first();

        $isFinal = !empty($invoice->reference_no);

        $advanceAmount = 0;
        $settlement = 0;

        if ($isFinal) {

            $advanceInvoice = Invoice::where('invoice_no', $invoice->reference_no)->first();

            $advanceAmount = $advanceInvoice->paid_amount ?? 0;
            $settlement = round($invoice->paid_amount - $advanceAmount, 2);
        }

        $approverName = $detail && $detail->discount_approved_by
            ? DB::table('users')->where('id', $detail->discount_approved_by)->value('name')
            : null;

        $pdf = Pdf::loadView(
            'apps-equipment-rental-pdf',
            compact('invoice', 'detail', 'isFinal', 'advanceAmount', 'settlement', 'approverName')
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
        $invoice = Invoice::whereIn('invoice_type', self::RENTAL_TYPES)
            ->findOrFail($id);

        $isFinal = !empty($invoice->reference_no);

        $advanceAmount = 0;
        $settlement = 0;

        if ($isFinal) {

            $advanceInvoice = Invoice::where('invoice_no', $invoice->reference_no)->first();

            $advanceAmount = $advanceInvoice->paid_amount ?? 0;
            $settlement = round($invoice->paid_amount - $advanceAmount, 2);
        }

        $sent = $this->generateAndSendInvoice($id, $isFinal, $advanceAmount, $settlement);

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

    private function generateAndSendInvoice(
        $invoiceId,
        bool $isFinal,
        float $advanceAmount = 0,
        float $settlement = 0
    ): bool {

        try {

            $invoice = Invoice::findOrFail($invoiceId);

            $detail = DB::table('invoice_details')
                ->where('invoice_no', $invoice->invoice_no)
                ->first();

            $approverName = $detail && $detail->discount_approved_by
                ? DB::table('users')->where('id', $detail->discount_approved_by)->value('name')
                : null;

            $fileName = str_replace('/', '_', $invoice->invoice_no) . '.pdf';

            $pdf = Pdf::loadView(
                'apps-equipment-rental-pdf',
                compact('invoice', 'detail', 'isFinal', 'advanceAmount', 'settlement', 'approverName')
            );

            $pdfPath = public_path('invoices/' . $fileName);

            $pdf->save($pdfPath);

            $pdfUrl = asset('invoices/' . $fileName);

            $sent = false;

            $responseBody = null;

            try {

                $isOxygen = $invoice->invoice_type === 'OXYGEN_RENT';

                $templateName = $isOxygen
                    ? config('services.wati.oxygen_template_name')
                    : config('services.wati.concentrator_template_name');

                $broadcastName = $isOxygen
                    ? config('services.wati.oxygen_broadcast_name')
                    : config('services.wati.concentrator_broadcast_name');

                $response = WatiService::sendInvoice(
                    $invoice->patient_mobile_no,
                    $invoice->patient_name,
                    $invoice->invoice_no,
                    $pdfUrl,
                    now()->format('d-m-Y H:i'),
                    $templateName,
                    $broadcastName
                );

                $sent = $response->successful();

                $responseBody = $response->body();

            } catch (\Throwable $e) {

                Log::error('Equipment Rental WhatsApp Send Failed: ' . $e->getMessage());

                $responseBody = $e->getMessage();
            }

            DB::table('whatsapp_message_logs')->insert([

                'invoice_no' => $invoice->invoice_no,
                'mobile_no' => $invoice->patient_mobile_no,
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

            Log::error('Equipment Rental WhatsApp Send Failed: ' . $e->getMessage());

            return false;
        }
    }

    private function generateInvoiceNo(string $rentalType): string
    {
        $prefix = \App\Support\OfflineMode::prefix($rentalType === 'OXYGEN_RENT' ? 'OXY/' : 'CON/') .
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
