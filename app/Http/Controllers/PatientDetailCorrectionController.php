<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\AuditService;
use App\Services\PatientIdentityGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only typo-correction tool for a patient's Name/Age/Gender.
 *
 * Employees frequently mistype these three fields during appointment
 * booking / invoice creation, and they then get baked as a denormalized
 * snapshot into several tables (not just the patients master row) --
 * invoices, doctor_appointments, doctor_payables, doctor_settlement_items
 * and daily_transactions all carry their own copy, keyed by patient_id.
 * This screen looks a patient up by patient_id and, on save, corrects
 * every one of those snapshots in one transaction so the fix is visible
 * everywhere, not just on the master record.
 *
 * Deliberately NOT propagated (see class docblock on each case):
 * - test_report_deliveries / whatsapp_message_logs: append-only
 *   historical logs of what was actually delivered/sent at the time --
 *   rewriting them would falsify history. New entries will already pick
 *   up the corrected name since they read from invoices at send time.
 * - patient_cards: keyed only by mobile number (multiple family-member
 *   name slots, no patient_id column at all) -- no reliable link to
 *   correct automatically.
 */
class PatientDetailCorrectionController extends Controller
{
    private const MODULE_CODE = 'PATIENT_DETAIL_CORRECTION';

    private PatientIdentityGuard $identityGuard;

    public function __construct(PatientIdentityGuard $identityGuard)
    {
        $this->identityGuard = $identityGuard;
    }

    /**
     * This screen writes identity data across six tables at once, so it
     * is restricted to Admin regardless of role_page_access configuration
     * -- same defense-in-depth as CloudBackupController.
     */
    private function ensureAdmin(): void
    {
        if (optional(Auth::user())->role !== 'Admin') {
            abort(403, 'Only an Admin can access Patient Detail Correction.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-patient-detail-correction');
    }

    /*
    |--------------------------------------------------------------------------
    | LOOKUP -- CURRENT DETAILS FOR ONE PATIENT ID
    |--------------------------------------------------------------------------
    */

    public function lookup(Request $request)
    {
        $this->ensureAdmin();

        $request->validate(['patient_id' => 'required|string']);

        $patient = Patient::where('patient_id', trim($request->patient_id))->first();

        if (!$patient) {
            return response()->json(['status' => false, 'message' => 'No patient found with this Patient ID.'], 404);
        }

        $protectedUsers = $this->identityGuard->protectedUsersForMobiles([$patient->mobile_no]);
        $nameProtected = $this->identityGuard->isProtected($patient->mobile_no, $patient->patient_name, $protectedUsers);

        return response()->json([
            'status' => true,
            'patient' => [
                'patient_id' => $patient->patient_id,
                'patient_name' => $patient->patient_name,
                'age' => $patient->age,
                'gender' => $patient->gender,
                'mobile_no' => $patient->mobile_no,
            ],
            'name_protected' => $nameProtected,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE -- CORRECT + PROPAGATE EVERYWHERE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate([
            'patient_id' => 'required|string|exists:patients,patient_id',
            'patient_name' => 'required|string|max:150',
            'age' => 'nullable|integer|min:0|max:150',
            'gender' => 'nullable|in:Male,Female,Other',
        ]);

        $patient = Patient::where('patient_id', $request->patient_id)->first();

        $newName = trim($request->patient_name);

        if ($newName === '') {
            return response()->json(['status' => false, 'message' => 'Patient name cannot be empty.'], 422);
        }

        $nameChanged = strcasecmp(trim($patient->patient_name), $newName) !== 0;

        if ($nameChanged) {

            $protectedUsers = $this->identityGuard->protectedUsersForMobiles([$patient->mobile_no]);

            if ($this->identityGuard->isProtected($patient->mobile_no, $patient->patient_name, $protectedUsers)) {

                return response()->json([
                    'status' => false,
                    'message' => 'This patient\'s current name matches a Member/Admin/Supervisor account (or one of their registered family members), so it cannot be renamed here. Age/Gender can still be corrected by resubmitting with the name unchanged.',
                ], 422);
            }
        }

        $oldData = $patient->only(['patient_name', 'age', 'gender']);

        $newAge = $request->filled('age') ? (int) $request->age : null;
        $newGender = $request->gender ?: null;

        $patientId = $patient->patient_id;

        $propagated = [];

        DB::beginTransaction();

        try {

            $patient->update([
                'patient_name' => $newName,
                'age' => $newAge,
                'gender' => $newGender,
            ]);

            $propagated['patients'] = 1;

            $propagated['invoices'] = DB::table('invoices')
                ->where('patient_id', $patientId)
                ->update([
                    'patient_name' => $newName,
                    'patient_age' => $newAge,
                    'patient_gender' => $newGender,
                ]);

            $propagated['doctor_appointments'] = DB::table('doctor_appointments')
                ->where('patient_id', $patientId)
                ->update([
                    'patient_name' => $newName,
                    'patient_age' => $newAge,
                    'patient_gender' => $newGender,
                ]);

            $propagated['doctor_payables'] = DB::table('doctor_payables')
                ->where('patient_id', $patientId)
                ->update(['patient_name' => $newName]);

            $propagated['doctor_settlement_items'] = DB::table('doctor_settlement_items')
                ->where('patient_id', $patientId)
                ->update(['patient_name' => $newName]);

            $propagated['daily_transactions'] = DB::table('daily_transactions')
                ->where('patient_id', $patientId)
                ->update(['patient_name' => $newName]);

            DB::commit();

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }

        $newData = $patient->only(['patient_name', 'age', 'gender']);

        $remarks = "Patient details corrected and propagated -- "
            . collect($propagated)->map(fn ($count, $table) => "{$table}: {$count}")->implode(', ');

        $auditService->logUpdate(self::MODULE_CODE, $patient, $oldData, $newData, $remarks);

        return response()->json([
            'status' => true,
            'message' => 'Patient details updated and propagated across all linked records.',
            'patient' => $newData,
            'propagated' => $propagated,
        ]);
    }
}
