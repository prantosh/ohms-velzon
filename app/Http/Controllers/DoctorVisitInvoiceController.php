<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\DoctorAppointment;
use App\Models\WhatsappAutoSendSetting;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use App\Services\WatiService;
use App\Services\AuditService;
use App\Models\DoctorPayable;
use App\Models\Doctor;
use App\Models\Patient;
class DoctorVisitInvoiceController extends Controller
{
    private const PATIENT_MODULE_CODE = 'PATIENT';

    private const MODULE_CODE = 'DOCTOR_VISIT';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-doctor-visit-invoice');
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD INVOICES
    |--------------------------------------------------------------------------
    */

    public function getInvoices()
    {
        try {

            $appointments =
                            DoctorAppointment::leftJoin(
                                'invoices',
                                'doctor_appointments.id',
                                '=',
                                'invoices.appointment_id'
                            )

                            ->leftJoin(
                                'invoice_item_masters',
                                'invoices.item_code',
                                '=',
                                'invoice_item_masters.item_code'
                            )

                            ->leftJoin(
                                'invoice_item_details',
                                'invoices.item_code_sub',
                                '=',
                                'invoice_item_details.item_code_sub'
                            )

                            ->leftJoin(
                                'doctors',
                                'doctor_appointments.doctor_id',
                                '=',
                                'doctors.id'
                            )

                    ->whereDate(
                        'doctor_appointments.appointment_date',
                        now()->toDateString()
                    )

                    ->select(

                        'doctor_appointments.*',

                        'doctors.doctor_name',

                        'doctors.consultation_fee_total',

                        'invoices.id as invoice_id',

                        'invoices.invoice_no',

                        'invoices.invoice_date',

                        'invoices.consultation_fee',

                        'invoices.discount',

                        'doctor_appointments.id',
                        'doctor_appointments.appointment_no',
                        'doctor_appointments.patient_id',
                        'doctor_appointments.patient_name',
                        'doctors.doctor_name',
                        'doctor_appointments.appointment_time as visit_time',
                        'invoices.paid_amount',


                        'invoices.payment_mode',

                        'doctor_appointments.patient_age as patient_age',
                        'doctor_appointments.patient_gender',
                        'invoices.card_number',
                        'invoices.is_card_holder',
                        'invoices.item_code',
                        'invoices.item_code_sub',

                        'invoice_item_masters.item_name',

                        'invoice_item_details.item_description_sub',
                        'invoices.cancelled',
                        'invoices.cancelled_at',
                        'invoices.refund_amount',
                        'invoices.refund_date',
                        'invoices.remarks'
                    )
                    ->orderBy(
                        'doctor_appointments.created_at',
                        'desc'
                    )

                    ->get();

            return response()->json([

                'status' => true,

                'data' => $appointments
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
    | LOAD APPOINTMENTS
    |--------------------------------------------------------------------------
    */

    public function getAppointments()
    {
        $appointments =
            DoctorAppointment::orderBy('id', 'desc')
                ->get();

        return response()->json($appointments);
    }
    /*
|--------------------------------------------------------------------------
| VALIDATE CARD
|--------------------------------------------------------------------------
*/

    public function validateCard(Request $request)
    {
        $card =
            DB::table('patient_cards')

                ->where('card_no', $request->card_number)

                ->where(function ($query) use ($request) {

                    $query->where(
                        'patient_name1',
                        $request->patient_name
                    )

                        ->orWhere(
                            'patient_name2',
                            $request->patient_name
                        )

                        ->orWhere(
                            'patient_name3',
                            $request->patient_name
                        )

                        ->orWhere(
                            'patient_name4',
                            $request->patient_name
                        )

                        ->orWhere(
                            'patient_name5',
                            $request->patient_name
                        );
                })

                ->where('active', 1)

                ->first();

        if ($card) {

            $doctor =
                DB::table('doctors')

                    ->where(
                        'id',
                        $request->doctor_id
                    )

                    ->first();

            return response()->json([

                'status' => true,

                'discount' =>
                    $doctor->discount ?? 0,

                'message' =>
                    'Card validated successfully'
            ]);
        }

        return response()->json([

            'status' => false,

            'message' =>
                'Invalid card number or patient name'
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'patient_age' => 'required',
            'patient_gender' => 'required',
        ]);

        $appointment =
            DoctorAppointment::findOrFail(
                $request->appointment_id
            );

        $appointment->update([

            'patient_name' =>
                $request->patient_name
        ]);

        // Propagate any name/age/gender correction made on this form back
        // to the shared patients record -- previously only update() (i.e.
        // editing an already-created invoice) did this, and even there
        // gender was never included since it didn't exist as a field yet.
        // Goes through the Patient model (not a raw DB::table update) so
        // the correction can be recorded in the audit log like every other
        // write in this app.
        if ($appointment->patient_id) {

            $patient = Patient::where('patient_id', $appointment->patient_id)->first();

            if ($patient) {

                $oldPatientData = $patient->only(['patient_name', 'age', 'gender']);

                $patient->update([
                    'patient_name' => $request->patient_name,
                    'age' => $request->patient_age,
                    'gender' => $request->patient_gender,
                ]);

                $newPatientData = $patient->only(['patient_name', 'age', 'gender']);

                if ($oldPatientData !== $newPatientData) {

                    $auditService->logUpdate(
                        self::PATIENT_MODULE_CODE,
                        $patient,
                        $oldPatientData,
                        $newPatientData,
                        'Patient details corrected during doctor visit invoice creation'
                    );
                }
            }
        }



        /*
        |--------------------------------------------------------------------------
        | INVOICE NUMBER
        |--------------------------------------------------------------------------
        */

        $prefix =
            \App\Support\OfflineMode::prefix('DOC/') .
            now()->format('my') .
            '/' .
            str_pad(
                Auth::id(),
                3,
                '0',
                STR_PAD_LEFT
            );
        

        $itemCode = 'DOC001';

        $itemCodeSub =
            DB::table('invoice_item_details')
                ->where('item_code', $itemCode)
                ->value('item_code_sub');

        if (!$itemCodeSub) {

            $itemCodeSub = 'DOC001';
        }
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
                intval(end($explode)) + 1;
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

 
        /*
        |--------------------------------------------------------------------------
        | CREATE INVOICE
        |--------------------------------------------------------------------------
        */
         $paidAmount =
                    max(
                        0,
                        ($request->consultation_fee ?? 0)
                        - ($request->discount ?? 0)
                    );
        $invoice =
            Invoice::create([

                'invoice_no' =>
                    $invoiceNo,

                'appointment_id' =>
                    $appointment->id,

                'appointment_no' =>
                    $appointment->appointment_no,

                'doctor_id' =>
                    $appointment->doctor_id,

                'patient_id' =>
                    $appointment->patient_id,

                'patient_name' =>
                    $request->patient_name,

                'patient_mobile_no' =>
                    $appointment->patient_mobile_no,

                'patient_age' =>
                    $request->patient_age,

                'patient_gender' =>
                    $request->patient_gender,

                'invoice_date' =>
                    $request->invoice_date,

                'consultation_fee' =>
                    $request->consultation_fee,

                'total_amount' =>
                    $request->consultation_fee,

                'discount' =>
                    $request->discount ?? 0,

               
                        'paid_amount' => $paidAmount,

                'due_amount' => 0,

                'status' => 'Paid',
                

                'payment_mode' =>
                    $request->payment_mode,

                'remarks' =>
                    $request->remarks,

                
                'is_card_holder' =>
                    $request->is_card_holder ?? 0,

                'card_number' =>
                    $request->card_number,
                'item_code'     => $itemCode,

                'item_code_sub' => $itemCodeSub,
               
                'created_by' =>
                    Auth::id()
            ]);

        $auditService->logCreate(
            self::MODULE_CODE,
            $invoice,
            $invoice->only($invoice->getFillable()),
            'Doctor visit invoice created'
        );

        $doctor = DB::table('doctors')
            ->where('id', $appointment->doctor_id)
            ->first();

        $hasCardNo = (float) ($request->card_number ?? 0) !== 0.0;

        $paymentRate =
            $hasCardNo
            ? ($doctor->consultation_fee_doctor_discounted_patient ?? 0)
            : ($doctor->consultation_fee_doctor ?? 0);

        DB::table('doctor_payables')->insert([

            'payable_no' => $this->generatePayableNo(),

            'invoice_no' => $invoice->invoice_no,
            'invoice_id' => $invoice->id,
            'patient_id' => $appointment->patient_id,

            // Explicit rather than relying on 'DOCTOR_VISIT' happening to be
            // the first value in the invoice_type enum (MySQL's implicit
            // default for an omitted NOT NULL enum column) -- doctors who
            // also do diagnostic-test work need every payable to say
            // plainly which activity earned it, not depend on enum
            // ordering by coincidence.
            'invoice_type' => 'DOCTOR_VISIT',

            'doctor_code' => $doctor->doctor_code ?? null,
            'doctor_id' => $appointment->doctor_id,

            'doctor_name' => $doctor->doctor_name ?? null,

            'item_code' => $itemCode,
            'item_code_sub' => $invoice->item_code_sub,

            'item_description' => 'Doctor Consultation',

            'gross_amount' => $invoice->consultation_fee,

            'patient_name' => $invoice->patient_name,
            'transaction_type' => 'RECEIVED',

            'payment_rate' => $paymentRate,

            'payable_amount' => $paymentRate,

            // Documents which of the doctor's two consultation-fee tiers
            // was used -- mirrors 'payment_rule' on the diagnostic side
            // (which records the rate came from doctor_test_payment_masters).
            'payment_rule' => $hasCardNo
                ? 'CARD_HOLDER_DISCOUNTED_RATE'
                : 'STANDARD_DOCTOR_RATE',

            'payment_status' => 'PENDING',

            'remarks' => 'Doctor Visit Invoice',

            'created_by' => Auth::id(),

            'updated_by' => Auth::id(),

            'created_at' => now(),

            'updated_at' => now()
        ]);
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

            'transaction_date' => $request->invoice_date
                ?: now()->toDateString(),

            'transaction_no' => $transactionNo,

            'transaction_type' => 'RECEIVED',

            'received_amount' => $request->paid_amount,

            'refund_amount' => 0,

            'doctor_payment_amount' => $paymentRate,

            'invoice_reference' => $invoice->invoice_no,

            'patient_id' => $request->patient_id,

            'patient_name' => $request->patient_name,

            'operator_id' => Auth::id(),

            'operator_name' => Auth::user()->name,

            'payment_mode' => $request->payment_mode,

            'remarks' => 'Diagnostic Invoice Collection',

            'status' => 'ACTIVE',

            'created_by' => Auth::id(),

            'created_at' => now(),

            'updated_at' => now(),

        ]);

        $this->autoSendInvoiceWhatsapp($invoice);

        return response()->json([

            'status' => true,

            'invoice_id' => $invoice->id,

            'message' =>
                'Invoice created successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id, AuditService $auditService)
    {
        $invoice =
            Invoice::findOrFail($id);
        if ($invoice->cancelled == 'Y') {

            return response()->json([
                'status' => false,
                'message' => 'Cancelled invoice cannot be edited'
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | NO FINANCIAL RE-PROCESSING ON EDIT
        |--------------------------------------------------------------------------
        | Editing an existing invoice must never re-collect payment or create
        | another ledger entry. Only patient name / age / gender are
        | updatable here, and only where they actually changed.
        */

        $nameChanged =
            $request->filled('patient_name') &&
            $request->patient_name !== $invoice->patient_name;

        $ageChanged =
            $request->filled('patient_age') &&
            (string) $request->patient_age !== (string) $invoice->patient_age;

        $genderChanged =
            $request->filled('patient_gender') &&
            $request->patient_gender !== $invoice->patient_gender;

        if (!$nameChanged && !$ageChanged && !$genderChanged) {

            return response()->json([

                'status' => true,

                'message' => 'No changes to patient name, age, or gender.'
            ]);
        }

        $appointment = DoctorAppointment::find($invoice->appointment_id);

        if ($appointment && $nameChanged) {

            $appointment->update([
                'patient_name' => $request->patient_name
            ]);
        }

        if ($invoice->patient_id && ($nameChanged || $ageChanged || $genderChanged)) {

            $patient = Patient::where('patient_id', $invoice->patient_id)->first();

            if ($patient) {

                $oldPatientData = $patient->only(['patient_name', 'age', 'gender']);

                $patient->update(array_filter([
                    'patient_name' => $nameChanged ? $request->patient_name : null,
                    'age' => $ageChanged ? $request->patient_age : null,
                    'gender' => $genderChanged ? $request->patient_gender : null,
                ], fn($value) => $value !== null));

                $newPatientData = $patient->only(['patient_name', 'age', 'gender']);

                if ($oldPatientData !== $newPatientData) {

                    $auditService->logUpdate(
                        self::PATIENT_MODULE_CODE,
                        $patient,
                        $oldPatientData,
                        $newPatientData,
                        'Patient details corrected while editing doctor visit invoice'
                    );
                }
            }
        }

        $oldInvoiceData = $invoice->only($invoice->getFillable());

        $invoice->update(array_filter([

            'patient_name' => $nameChanged ? $request->patient_name : null,

            'patient_age' => $ageChanged ? $request->patient_age : null,

            'patient_gender' => $genderChanged ? $request->patient_gender : null,

            'updated_by' => Auth::id(),

        ], fn($value) => $value !== null));

        $auditService->logUpdate(
            self::MODULE_CODE,
            $invoice,
            $oldInvoiceData,
            $invoice->only($invoice->getFillable()),
            'Doctor visit invoice patient details updated'
        );

        return response()->json([

            'status' => true,

            'message' =>
                'Patient details updated successfully'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        DB::beginTransaction();

        try {

            $invoice = Invoice::findOrFail($id);

            if ($invoice->cancelled == 'Y') {

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

            $refundAmount =
                ($invoice->paid_amount ?? 0)
                + ($invoice->paid_amount_1 ?? 0)
                + ($invoice->paid_amount_2 ?? 0);

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

                'transaction_date' => now()->format('Y-m-d'),

                'transaction_no' => $transactionNo,

                'transaction_type' => 'REFUND',

                'received_amount' => 0,

                'refund_amount' => $refundAmount,

                'invoice_reference' => $invoice->invoice_no,

                'item_reference' => $invoice->item_code_sub,

                'appointment_reference' => $invoice->appointment_no,

                'patient_id' => $invoice->patient_id,

                'patient_name' => $invoice->patient_name,

                'operator_id' => Auth::id(),

                'operator_name' => Auth::user()->name ?? null,

                'payment_mode' => $invoice->payment_mode,

                'remarks' => 'Invoice Cancelled Refund',

                'status' => 'ACTIVE',

                'created_by' => Auth::id(),

                'created_at' => now(),

                'updated_at' => now()
            ]);

            $invoice->update([

                'status' => 'C',

                'cancelled' => 'Y',

                'cancelled_by' => Auth::id(),

                'cancelled_at' => now(),

                'cancellation_approved_by' => $approverId,

                'refund_amount' => $refundAmount,

                'refund_date' => now()->format('Y-m-d'),

                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $invoice,
                $oldInvoiceData,
                $invoice->only($invoice->getFillable()),
                'Doctor visit invoice cancelled'
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
    | SEND WHATSAPP
    |--------------------------------------------------------------------------
    */




    public function sendWhatsapp($id)
    {
        try {

            $invoice = Invoice::findOrFail($id);

            if ($invoice->cancelled == 'Y') {

                return response()->json([
                    'status' => false,
                    'message' => 'Cancelled invoice cannot be sent'
                ]);
            }

            $result = $this->sendInvoiceWhatsapp($invoice);

            if (!$result['sent']) {

                return response()->json([
                    'status' => false,
                    'message' => json_encode($result['response'])
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'WhatsApp sent successfully'
            ]);

        } catch (\Exception $e) {

            \Log::error('Doctor Visit Invoice WhatsApp Error', [
                'message' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Shared by the manual "Send WhatsApp" button (always runs, ungated)
     * and the automatic send fired from store() (gated by
     * WhatsappAutoSendSetting -- checked by the caller, not in here, so
     * this method itself always actually attempts the send).
     */
    private function sendInvoiceWhatsapp(Invoice $invoice): array
    {
        $appointment = DoctorAppointment::leftJoin(
                'doctors',
                'doctor_appointments.doctor_id',
                '=',
                'doctors.id'
            )
            ->select('doctor_appointments.*', 'doctors.doctor_name')
            ->where('doctor_appointments.id', $invoice->appointment_id)
            ->first();

        $folderPath = public_path('invoices');

        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0777, true, true);
        }

        $safeInvoiceNo = str_replace('/', '_', $invoice->invoice_no);
        $fileName = $safeInvoiceNo . '.pdf';
        $filePath = $folderPath . '/' . $fileName;

        $pdf = Pdf::loadView('apps-doctor-visit-invoice-pdf', compact('invoice', 'appointment'));
        $pdf->setPaper('A4', 'portrait');
        $pdf->save($filePath);

        $pdfUrl = asset('invoices/' . $fileName);

        $parameters = [
            ["name" => "1", "value" => $pdfUrl],
            ["name" => "2", "value" => $invoice->patient_name],
            ["name" => "3", "value" => preg_replace('/^dr\.?\s+/i', '', $appointment->doctor_name ?? '')],
            ["name" => "4", "value" => now()->format('d-m-Y H:i')],
            ["name" => "5", "value" => $invoice->invoice_no],
        ];

        $response = (new WatiService())->sendTemplateMessage(
            '91' . preg_replace('/\D/', '', $invoice->patient_mobile_no),
            'invoice_doctor',
            'invoice_doctor_send',
            $parameters
        );

        $sent = isset($response['result']) && $response['result'] == true;
        $status = $sent ? 'SENT' : 'FAILED';

        DB::table('whatsapp_message_logs')->insert([
            'invoice_no' => $invoice->invoice_no,
            'mobile_no' => $invoice->patient_mobile_no,
            'patient_name' => $invoice->patient_name,
            'message_type' => 'INVOICE',
            'status' => $status,
            'response' => json_encode($response),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoice->update([
            'whatsapp_sent_at' => $sent ? now() : null,
            'whatsapp_status' => $status,
            'whatsapp_send_count' => $sent
                ? DB::raw('whatsapp_send_count + 1')
                : DB::raw('whatsapp_send_count'),
        ]);

        return ['sent' => $sent, 'response' => $response];
    }

    /**
     * Gated entry point for the automatic trigger fired from store().
     * The manual sendWhatsapp() route above calls sendInvoiceWhatsapp()
     * directly, ungated, so staff always retain a working resend button.
     * Swallows its own exceptions -- a WhatsApp/PDF failure here must never
     * fail invoice creation itself.
     */
    private function autoSendInvoiceWhatsapp(Invoice $invoice): void
    {
        if (!WhatsappAutoSendSetting::isEnabled('INVOICE')) {
            WhatsappAutoSendSetting::logSkipped('INVOICE', $invoice->invoice_no, $invoice->patient_mobile_no, $invoice->patient_name);
            return;
        }

        try {
            $this->sendInvoiceWhatsapp($invoice);
        } catch (\Exception $e) {
            \Log::error('Doctor Visit Invoice Auto WhatsApp Error', ['message' => $e->getMessage()]);
        }
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

    /*
|--------------------------------------------------------------------------
| PRINT PDF
|--------------------------------------------------------------------------
*/

    /*
|--------------------------------------------------------------------------
| GENERATE PDF
|--------------------------------------------------------------------------
*/

    public function printInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->cancelled == 'Y') {
            abort(404, 'Cancelled invoice cannot be printed.');
        }

        $appointment = DoctorAppointment::leftJoin(
            'doctors',
            'doctor_appointments.doctor_id',
            '=',
            'doctors.id'
        )
            ->select(
                'doctor_appointments.*',
                'doctors.doctor_name'
            )
            ->where(
                'doctor_appointments.id',
                $invoice->appointment_id
            )
            ->first();

        $pdf = Pdf::loadView(
            'apps-doctor-visit-invoice-pdf',
            compact('invoice', 'appointment')
        );

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream(str_replace('/', '_', $invoice->invoice_no) . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT PRESCRIPTION
    |--------------------------------------------------------------------------
    | Blank prescription pad, printable only after the visit invoice has
    | been created -- header/patient/doctor info pre-filled, with blank
    | space for BP/Weight and the prescription itself to be filled in by
    | hand during consultation.
    */

    public function printPrescription($id)
    {
        $invoice = Invoice::findOrFail($id);

        if ($invoice->cancelled == 'Y') {
            abort(404, 'Cancelled invoice has no prescription to print.');
        }

        $doctor = Doctor::find($invoice->doctor_id);

        $pdf = Pdf::loadView(
            'apps-doctor-visit-prescription-pdf',
            compact('invoice', 'doctor')
        );

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream(
            'Prescription-' . str_replace('/', '_', $invoice->invoice_no) . '.pdf'
        );
    }
}
