<?php
namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Specialisation;
use App\Models\Patient;
use App\Services\AppointmentBookingService;
use App\Services\AppointmentReassignmentService;
use App\Services\DoctorScheduleQueryService;
use App\Services\AuditService;

class DoctorAppointmentController extends Controller
{
    private const MODULE_CODE = 'DOCTOR_APPOINTMENT';

    protected $scheduleQuery;
    protected $bookingService;
    protected $reassignmentService;

    public function __construct(
        DoctorScheduleQueryService $scheduleQuery,
        AppointmentBookingService $bookingService,
        AppointmentReassignmentService $reassignmentService
    ) {
        $this->scheduleQuery = $scheduleQuery;
        $this->bookingService = $bookingService;
        $this->reassignmentService = $reassignmentService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $appointments = DoctorAppointment::with('doctor')
            ->latest()
            ->get();

        $doctors = Doctor::where('status', 1)
            ->where('doctor_name', 'like', 'Dr.%')
            ->get();

        $specialisations = Specialisation::orderBy('category')
            ->get();

        return view('apps-ecommerce-doctor-appointments', compact(
            'appointments',
            'doctors',
            'specialisations'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required',
            'doctor_schedule_session_id' => 'required|exists:doctor_schedule_sessions,id',
            'patient_id' => 'nullable|integer|exists:patients,id',
            'patient_name' => 'required',
            'patient_mobile_no' => 'required',
            'patient_age' => 'nullable',
            'patient_gender' => 'nullable',
            'appointment_date' => 'required|date',
            'appointment_time' => 'required',
            'remarks' => 'nullable|string',
        ]);

        $result = $this->bookingService->book([
            'doctor_id' => $request->doctor_id,
            'doctor_schedule_session_id' => $request->doctor_schedule_session_id,
            // Real numeric patients.id of an existing patient the operator
            // matched via the mobile-number search, if any -- lets book()
            // correct that same patient's name/age/gender instead of
            // matching by name text (which breaks the moment the name is
            // corrected) or creating a duplicate.
            'patient_id' => $request->patient_id,
            'patient_name' => $request->patient_name,
            'patient_mobile_no' => $request->patient_mobile_no,
            'patient_age' => $request->patient_age,
            'patient_gender' => $request->patient_gender,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'remarks' => $request->remarks,
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => $result['status'],
            'message' => $result['message'],
            'token_no' => optional($result['appointment'])->token_no,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id, AuditService $auditService)
    {
        $appointment = DoctorAppointment::findOrFail($id);

        $oldData = $appointment->only($appointment->getFillable());

        $appointment->update([
            'appointment_status' => $request->appointment_status,
            'remarks' => $request->remarks,
            'updated_by' => Auth::id()
        ]);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $appointment,
            $oldData,
            $appointment->only($appointment->getFillable()),
            'Appointment updated'
        );

        return response()->json([
            'status' => true,
            'message' => 'Appointment updated successfully.'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, AuditService $auditService)
    {
        $appointment = DoctorAppointment::findOrFail($id);

        $oldData = $appointment->only($appointment->getFillable());

        $appointment->delete();

        $auditService->logDelete(
            self::MODULE_CODE,
            $appointment,
            $oldData,
            'Appointment deleted'
        );

        return response()->json([
            'status' => true,
            'message' => 'Appointment deleted successfully.'
        ]);
    }

    public function getAvailableSlots(Request $request)
    {
        return response()->json(
            $this->scheduleQuery->getAvailableSlots($request->doctor_id, $request->appointment_date)
        );
    }

    /**
     * Commits a reviewed batch of appointment reassignments -- see
     * DoctorScheduleExceptionController::store(), which is where the
     * suggestions this batch is built from originally come from (a doctor
     * blackout date that already had Booked appointments on it).
     */
    public function bulkReassign(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'assignments' => 'required|array|min:1',
            'assignments.*.appointment_id' => 'required|integer|exists:doctor_appointments,id',
            'assignments.*.new_date' => 'required|date',
            'assignments.*.new_time' => 'required',
            'assignments.*.new_session_id' => 'required|integer|exists:doctor_schedule_sessions,id',
        ]);

        $result = $this->reassignmentService->commitReassignments($request->assignments, Auth::id());
        $notifiedCount = $this->reassignmentService->notifyPatients($result['committed']);

        $message = count($result['committed']) . ' appointment(s) reassigned, ' . $notifiedCount . ' patient(s) notified via WhatsApp.';

        if (!empty($result['failed'])) {
            $message .= ' ' . count($result['failed']) . ' could not be reassigned.';
        }

        return response()->json([
            'status' => true,
            'message' => $message,
            'committed' => $result['committed'],
            'failed' => $result['failed'],
        ]);
    }

    public function getTotalPatients(Request $request)
    {
        $count = DoctorAppointment::where(
            'doctor_id',
            $request->doctor_id
        )
            ->where(
                'appointment_date',
                $request->appointment_date
            )
            ->count();

        // Token numbering for this doctor/date starts at 3, not 1 (see
        // AppointmentBookingService::FIRST_TOKEN_NO), so the displayed
        // "Total Patients Booked" figure is offset by the same +2 to stay
        // consistent with the actual token that will be issued next.
        return response()->json([
            'total_patients' => $count + 2
        ]);
    }

    public function getDoctorsBySpecialisation(Request $request)
    {
        return response()->json(
            $this->scheduleQuery->getDoctorsBySpecialisation($request->specialisation_id)
        );
    }

    public function getAvailableDays(Request $request)
    {
        return response()->json(
            $this->scheduleQuery->getAvailableDays($request->doctor_id)
        );
    }

    public function getPatientsByMobile(Request $request)
    {
        $patients = Patient::where(
            'mobile_no',
            $request->mobile_no
        )
            ->orderBy('patient_name')
            ->get([
                'id',
                'patient_id',
                'patient_name',
                'age',
                'gender'
            ]);

        return response()->json($patients);
    }
    public function getPatientAppointments(Request $request)
    {
        $appointments = DoctorAppointment::leftJoin(
            'doctors',
            'doctor_appointments.doctor_id',
            '=',
            'doctors.id'
        )

            ->where(
                'doctor_appointments.patient_mobile_no',
                $request->mobile_no
            )

            ->where(
                'doctor_appointments.patient_name',
                $request->patient_name
            )

            ->select(
                'doctor_appointments.*',
                'doctors.doctor_name'
            )

            ->orderBy(
                'doctor_appointments.appointment_date',
                'desc'
            )

            ->get();

        return response()->json($appointments);
    }
    public function getPatientDetails(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer',
            'mobile_no' => 'required',
        ]);

        $patient = Patient::where('id', $request->patient_id)
            ->where('mobile_no', $request->mobile_no)
            ->first();

        return response()->json($patient);
    }
}
