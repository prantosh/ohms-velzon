<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorPayable;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin-only screen to add (or correct) the performing doctor on a saved
 * diagnostic invoice line, after the invoice has been created.
 *
 * Covers what the invoice screens cannot:
 * - a line saved with no doctor because its test had no rule in
 *   doctor_test_payment_masters at the time (rule added later), and
 * - a wrong doctor on an invoice that is already fully paid (the
 *   doctor_replacements block in DiagnosticInvoiceController only runs
 *   while a due amount is being collected).
 *
 * The doctor's payable is the selected doctor's active rule for the exact
 * test -- never typed in -- except for manual categories (Dental Manual,
 * Miscellaneous Manual), where the Admin types it, same as the invoice
 * screen (see doctorOptions()). The patient's line amount and the
 * invoice total/paid/due never change; only how the line amount splits
 * between the clinic and the doctor.
 *
 * Settlement: the payable stays with whoever collected the invoice's final
 * payment (invoices.doctor_amount_collected_by, else the last RECEIVED
 * daily transaction) -- same attribution every settlement screen uses.
 * Saving touches invoices.updated_at, so a fully-paid invoice's new payable
 * shows up in that user's Doctor Settlement on today's date, paid out of
 * the cash they hold now. Cash already counted in an earlier daily cash
 * settlement is not moved.
 *
 * Deliberately NOT in config/access_pages, so it cannot be granted to
 * other roles.
 */
class InvoiceDoctorCorrectionController extends Controller
{
    private const MODULE_CODE = 'INVOICE_DOCTOR_CORRECTION';

    private function ensureAdmin(): void
    {
        abort_unless(
            optional(Auth::user())->role === 'Admin',
            403,
            'Only an Admin can access Invoice Doctor Correction.'
        );
    }

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-invoice-doctor-correction');
    }

    /*
    |--------------------------------------------------------------------------
    | Search -- by invoice no (exact) or patient mobile no (recent invoices)
    |--------------------------------------------------------------------------
    */

    public function search(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['q' => 'required|string|max:50']);

        $q = trim($request->q);

        $invoice = Invoice::where('invoice_no', $q)->first();

        if ($invoice) {
            return response()->json(['status' => true, 'mode' => 'invoice', 'invoice' => $this->invoicePayload($invoice)]);
        }

        $digits = preg_replace('/\D/', '', $q);

        if (strlen($digits) >= 10) {

            $invoices = Invoice::where('invoice_type', 'DIAGNOSTIC')
                ->where('patient_mobile_no', 'like', '%' . substr($digits, -10))
                ->orderByDesc('id')
                ->limit(30)
                ->get(['id', 'invoice_no', 'invoice_date', 'patient_name', 'total_amount', 'due_amount', 'cancelled']);

            if ($invoices->isNotEmpty()) {
                return response()->json([
                    'status' => true,
                    'mode' => 'list',
                    'invoices' => $invoices->map(fn ($i) => [
                        'invoice_no' => $i->invoice_no,
                        'invoice_date' => $i->invoice_date ? \Carbon\Carbon::parse($i->invoice_date)->format('d-m-Y') : '',
                        'patient_name' => $i->patient_name,
                        'total_amount' => (float) $i->total_amount,
                        'due_amount' => (float) $i->due_amount,
                        'cancelled' => $i->cancelled === 'Y',
                    ]),
                ]);
            }
        }

        return response()->json(['status' => false, 'message' => 'No invoice found for this Invoice No / Mobile No.'], 404);
    }

    /*
    |--------------------------------------------------------------------------
    | Assign / change the doctor on one invoice line
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate([
            'invoice_detail_id' => 'required|integer',
            'doctor_id' => 'required|integer',
            // Only read for manual-category lines; ignored otherwise.
            'payment_value' => 'nullable|numeric|min:0',
            'reason' => 'required|string|min:5|max:500',
        ]);

        $doctorId = (int) $request->doctor_id;
        $reason = trim($request->reason);

        DB::beginTransaction();

        try {

            $line = DB::table('invoice_details')
                ->where('id', $request->invoice_detail_id)
                ->lockForUpdate()
                ->first();

            $invoice = $line
                ? Invoice::where('invoice_no', $line->invoice_no)->lockForUpdate()->first()
                : null;

            if (!$line || !$invoice) {
                DB::rollBack();
                return $this->fail('Invoice line not found.', 404);
            }

            $blocked = $this->lineBlockReason($invoice, $line);

            if ($blocked) {
                DB::rollBack();
                return $this->fail($blocked);
            }

            $doctor = Doctor::find($doctorId);

            if (!$doctor) {
                DB::rollBack();
                return $this->fail('Selected doctor not found.');
            }

            $isManual = $this->isManualItem($line->item_code);

            $option = $this->doctorOptions($line)->firstWhere('doctor_id', $doctorId);

            if (!$option) {
                DB::rollBack();
                return $this->fail($isManual
                    ? $doctor->doctor_name . ' cannot be selected for "' . $line->item_description . '".'
                    : $doctor->doctor_name . ' has no active payment rule for "' . $line->item_description . '" in Doctor Test Payment Master.');
            }

            if ($isManual) {

                // Manual category: the doctor's amount is typed by the
                // Admin (a rule's rate, if any, is only the suggestion) --
                // same as the invoice screen.
                if (!$request->filled('payment_value')) {
                    DB::rollBack();
                    return $this->fail('Enter the doctor amount for "' . $line->item_description . '".');
                }

                $newRate = round((float) $request->payment_value, 2);

            } else {

                // Rule-based test: always the selected doctor's rule.
                $newRate = round((float) $option['payment_value'], 2);
            }

            if ($newRate > (float) $line->amount) {
                DB::rollBack();
                return $this->fail('The doctor amount (' . number_format($newRate, 2) . ') is more than the billed amount (' . number_format((float) $line->amount, 2) . ') for "' . $line->item_description . '".');
            }

            $oldDoctorId = (int) ($line->doctor_id ?? 0);
            $oldPaymentValue = round((float) ($line->payment_value ?? 0), 2);

            if ($oldDoctorId === $doctorId && $oldPaymentValue === $newRate) {
                DB::rollBack();
                return $this->fail('This doctor and amount are already on the line -- nothing to change.');
            }

            $payables = DoctorPayable::where('invoice_id', $invoice->id)
                ->where('item_code_sub', $line->item_code_sub)
                ->lockForUpdate()
                ->get();

            if ($payables->count() > 1) {
                DB::rollBack();
                return $this->fail('This invoice has more than one doctor payable for "' . $line->item_description . '", so it cannot be corrected here safely.');
            }

            $payable = $payables->first();

            if ($payable && ($why = $this->payableBlockReason($payable))) {
                DB::rollBack();
                return $this->fail($why);
            }

            $oldDoctorName = $oldDoctorId ? (Doctor::whereKey($oldDoctorId)->value('doctor_name') ?? '-') : 'None';

            $note = 'Doctor ' . ($oldDoctorId ? 'changed' : 'added') . ' by Admin after invoice creation: ' . $reason;

            // ---- invoice line -----------------------------------------------
            DB::table('invoice_details')
                ->where('id', $line->id)
                ->update([
                    'doctor_id' => $doctorId,
                    'payment_value' => $newRate,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            // ---- invoice aggregate (net shift, same as the due-payment
            // doctor replacement). save() also touches updated_at, which is
            // what puts the payable on today's Doctor Settlement screen.
            $oldInvoiceData = $invoice->only(['doctor_payment_amount', 'doctor_amount_collected_by']);

            $invoice->doctor_payment_amount = max(
                0,
                round((float) $invoice->doctor_payment_amount - $oldPaymentValue + $newRate, 2)
            );
            $invoice->updated_by = Auth::id();
            $invoice->save();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData + [
                    'line_item' => $line->item_description,
                    'line_doctor_id' => $oldDoctorId ?: null,
                    'line_doctor_name' => $oldDoctorName,
                    'line_payment_value' => $oldPaymentValue,
                ],
                $invoice->only(['doctor_payment_amount', 'doctor_amount_collected_by']) + [
                    'line_item' => $line->item_description,
                    'line_doctor_id' => $doctorId,
                    'line_doctor_name' => $doctor->doctor_name,
                    'line_payment_value' => $newRate,
                ],
                $note
            );

            // ---- doctor payable ---------------------------------------------
            if ($payable) {

                $oldPayableData = $payable->only($payable->getFillable());

                $payable->doctor_id = $doctorId;
                $payable->doctor_code = $doctor->doctor_code;
                $payable->doctor_name = $doctor->doctor_name;
                $payable->payment_rule = 'TEST_PAYMENT_MASTER';
                $payable->payment_rate = $newRate;
                $payable->payable_amount = $newRate;
                $payable->remarks = trim($note . ' ' . ($payable->remarks ?? ''));
                $payable->updated_by = Auth::id();
                $payable->save();

                $auditService->logUpdate(
                    self::MODULE_CODE,
                    $payable,
                    $oldPayableData,
                    $payable->only($payable->getFillable()),
                    $note
                );

            } else {

                // A handful of older invoices have no payable row for a line.
                $payable = DoctorPayable::create([
                    'payable_no' => $this->generatePayableNo(),
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'invoice_type' => 'DIAGNOSTIC',
                    'transaction_type' => 'RECEIVED',
                    'doctor_id' => $doctorId,
                    'doctor_code' => $doctor->doctor_code,
                    'doctor_name' => $doctor->doctor_name,
                    'patient_id' => $invoice->patient_id,
                    'patient_name' => $invoice->patient_name,
                    'item_code' => $line->item_code,
                    'item_code_sub' => $line->item_code_sub,
                    'item_description' => $line->item_description,
                    'gross_amount' => (float) $line->amount,
                    'payment_rule' => 'TEST_PAYMENT_MASTER',
                    'payment_rate' => $newRate,
                    'payable_amount' => $newRate,
                    'payment_status' => DoctorPayable::STATUS_PENDING,
                    'remarks' => $note,
                    'created_by' => Auth::id(),
                ]);

                $auditService->logCreate(
                    self::MODULE_CODE,
                    $payable,
                    $payable->only($payable->getFillable()),
                    $note
                );
            }

            DB::commit();

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Invoice Doctor Correction failed', [
                'invoice_detail_id' => $request->invoice_detail_id,
                'doctor_id' => $doctorId,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('Could not save: ' . $e->getMessage(), 500);
        }

        $invoice->refresh();

        return response()->json([
            'status' => true,
            'message' => $doctor->doctor_name . ' set on "' . $line->item_description . '" with a payable of ' . number_format($newRate, 2) . '.',
            'invoice' => $this->invoicePayload($invoice),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function invoicePayload(Invoice $invoice): array
    {
        $lines = DB::table('invoice_details')
            ->where('invoice_no', $invoice->invoice_no)
            ->orderBy('id')
            ->get();

        $payables = DoctorPayable::where('invoice_id', $invoice->id)->get()->groupBy('item_code_sub');

        $doctorNames = Doctor::whereIn('id', $lines->pluck('doctor_id')->filter()->unique())->pluck('doctor_name', 'id');

        $rows = $lines->map(function ($line) use ($invoice, $payables, $doctorNames) {

            $linePayables = $payables->get($line->item_code_sub, collect());
            $payable = $linePayables->first();
            $isManual = $this->isManualItem($line->item_code);

            $blocked = $this->lineBlockReason($invoice, $line);
            $options = $blocked ? collect() : $this->doctorOptions($line);

            if (!$blocked && $linePayables->count() > 1) {
                $blocked = 'More than one doctor payable exists for this test.';
            }

            if (!$blocked && $payable) {
                $blocked = $this->payableBlockReason($payable);
            }

            if (!$blocked && $options->isEmpty()) {
                $blocked = $isManual
                    ? 'No doctor can be set for this manual item (no rule in Doctor Test Payment Master).'
                    : 'No active rule in Doctor Test Payment Master for this test.';
            }

            return [
                'id' => $line->id,
                'item_code_sub' => $line->item_code_sub,
                'item_description' => $line->item_description,
                'amount' => (float) $line->amount,
                'doctor_id' => $line->doctor_id ? (int) $line->doctor_id : null,
                'doctor_name' => $line->doctor_id ? ($doctorNames[$line->doctor_id] ?? ('#' . $line->doctor_id)) : null,
                'payment_value' => (float) ($line->payment_value ?? 0),
                'waived' => (bool) $line->doctor_payment_waived,
                'payable_status' => $payable?->payment_status,
                'paid_amount' => (float) ($payable?->paid_amount ?? 0),
                'is_package_component' => !empty($line->package_parent_item_code_sub),
                'is_manual' => $isManual,
                'editable' => $blocked === null,
                'blocked_reason' => $blocked,
                'options' => $options->values(),
            ];
        })->values();

        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'invoice_type' => $invoice->invoice_type,
            'invoice_date' => $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') : '',
            'patient_name' => $invoice->patient_name,
            'patient_mobile_no' => $invoice->patient_mobile_no,
            'total_amount' => (float) $invoice->total_amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'due_amount' => (float) $invoice->due_amount,
            'doctor_payment_amount' => (float) $invoice->doctor_payment_amount,
            'cancelled' => $invoice->cancelled === 'Y',
            'settled_by' => $this->settlementUserName($invoice),
            'lines' => $rows,
        ];
    }

    /** Why this line can't be given a doctor here, or null if it can. */
    private function lineBlockReason(Invoice $invoice, $line): ?string
    {
        if ($invoice->invoice_type !== 'DIAGNOSTIC') {
            return 'Only diagnostic invoices can be corrected here.';
        }

        if ($invoice->cancelled === 'Y') {
            return 'Invoice is cancelled.';
        }

        if (!empty($line->package_parent_item_code_sub)) {
            return 'Package component -- the doctor goes on the package line.';
        }

        if ($line->doctor_payment_waived) {
            return 'Doctor payment was waived on this line.';
        }

        return null;
    }

    /**
     * Doctors that may be set on this line, each as
     * {doctor_id, doctor_name, payment_value|null}.
     *
     * - Rule-based test: the active doctor_test_payment_masters rows for the
     *   exact test; payment_value is the fixed rate.
     * - Manual category: the same rule rows if any (rate is only a
     *   suggestion, the Admin types the amount); a Dental Manual item with
     *   no rule offers every DENTAL-specialisation doctor instead (no
     *   suggestion) -- mirroring loadDoctorDropdownForItem() /
     *   loadDentalDoctorsForRow() on the invoice screen. The line's current
     *   doctor is always kept in the list so its amount can be corrected.
     */
    private function doctorOptions($line)
    {
        $options = DB::table('doctor_test_payment_masters as p')
            ->join('doctors as d', 'd.id', '=', 'p.doctor_id')
            ->where('p.item_code_sub', $line->item_code_sub)
            ->where('p.status', 'A')
            ->where('p.doctor_id', '!=', 999)
            ->orderBy('d.doctor_name')
            ->get(['p.doctor_id', 'p.payment_value', 'd.doctor_name'])
            ->map(fn ($o) => [
                'doctor_id' => (int) $o->doctor_id,
                'doctor_name' => $o->doctor_name,
                'payment_value' => (float) $o->payment_value,
            ]);

        if (!$this->isManualItem($line->item_code)) {
            return $options->values();
        }

        if ($options->isEmpty() && $this->isDentalManualItem($line->item_code)) {
            $options = Doctor::where('status', 1)
                ->where(function ($q) {
                    $q->where('specialisation', 'DENTAL')
                        ->orWhereHas('specialisations', fn ($s) => $s->where('category', 'DENTAL'));
                })
                ->orderBy('doctor_name')
                ->get(['id', 'doctor_name'])
                ->map(fn ($d) => ['doctor_id' => (int) $d->id, 'doctor_name' => $d->doctor_name, 'payment_value' => null]);
        }

        $currentId = (int) ($line->doctor_id ?? 0);

        if ($currentId && $currentId !== 999 && !$options->contains('doctor_id', $currentId)) {
            $current = Doctor::find($currentId);

            if ($current) {
                $options->prepend(['doctor_id' => $currentId, 'doctor_name' => $current->doctor_name, 'payment_value' => null]);
            }
        }

        return $options->values();
    }

    private function isDentalManualItem(?string $itemCode): bool
    {
        return $itemCode && DB::table('invoice_item_masters')
            ->where('item_code', $itemCode)
            ->where('item_name', 'like', '%manual%')
            ->where('item_name', 'like', '%dental%')
            ->exists();
    }

    private function payableBlockReason(DoctorPayable $payable): ?string
    {
        if ($payable->payment_status === DoctorPayable::STATUS_CANCELLED) {
            return 'Doctor payable is cancelled.';
        }

        if ($payable->payment_status === DoctorPayable::STATUS_PAID
            || (float) $payable->paid_amount > 0
            || (int) ($payable->settlement_count ?? 0) > 0) {
            return 'Doctor payment already settled (fully or partly).';
        }

        return null;
    }

    private function isManualItem(?string $itemCode): bool
    {
        static $cache = [];

        if (!$itemCode) {
            return false;
        }

        return $cache[$itemCode] ??= (bool) DB::table('invoice_item_masters')
            ->where('item_code', $itemCode)
            ->where('item_name', 'like', '%manual%')
            ->exists();
    }

    /** Same attribution as DoctorSettlementController: explicit collector, else the last RECEIVED transaction's operator. */
    private function settlementUserName(Invoice $invoice): ?string
    {
        $userId = $invoice->doctor_amount_collected_by ?: DB::table('daily_transactions')
            ->where('invoice_reference', $invoice->invoice_no)
            ->where('transaction_type', 'RECEIVED')
            ->where('status', '!=', 'CANCELLED')
            ->orderByDesc('created_at')
            ->value('operator_id');

        return $userId ? User::whereKey($userId)->value('name') : null;
    }

    /** Same format as DiagnosticInvoiceController::generatePayableNo(). */
    private function generatePayableNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('PAY/') . now()->format('my') . '/';

        $last = DoctorPayable::where('payable_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('payable_no');

        $serial = $last ? (int) substr($last, strrpos($last, '/') + 1) + 1 : 1;

        return $prefix . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }

    private function fail(string $message, int $code = 422)
    {
        return response()->json(['status' => false, 'message' => $message], $code);
    }
}
