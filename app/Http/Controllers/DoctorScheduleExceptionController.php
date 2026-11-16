<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use App\Models\DoctorScheduleException;
use App\Services\AppointmentReassignmentService;
use App\Services\AuditService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Lets staff mark specific CALENDAR DATES as unavailable for a doctor, on
 * top of their normal recurring weekly schedule (some doctors have a
 * weekly recurring slot but only actually sit once or twice a month).
 * Booking on an excepted date is blocked in DoctorScheduleQueryService
 * and AppointmentBookingService, not here -- this controller only
 * manages the exception rows themselves.
 */
class DoctorScheduleExceptionController extends Controller
{
    private const MODULE_CODE = 'DOCTOR_SCHEDULE_EXCEPTION';

    /*
    |--------------------------------------------------------------------------
    | REPORT PAGE (Appointment > Doctor Unavailable Dates)
    |--------------------------------------------------------------------------
    | Read-only listing of every disabled date across all doctors, with who
    | disabled it and when. Defaults to upcoming dates only; the filters
    | let staff narrow by doctor / date range or include past dates.
    */

    public function report(Request $request)
    {
        $request->validate([
            'doctor_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'scope' => 'nullable|in:upcoming,all',
        ]);

        $scope = $request->input('scope', 'upcoming');

        $query = DoctorScheduleException::with([
            'doctor:id,doctor_name',
            'creator:id,name',
        ]);

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('exception_date', '>=', $request->from_date);
        } elseif ($scope === 'upcoming') {
            $query->whereDate('exception_date', '>=', Carbon::today()->toDateString());
        }

        if ($request->filled('to_date')) {
            $query->whereDate('exception_date', '<=', $request->to_date);
        }

        $exceptions = $query
            ->orderBy('exception_date', $scope === 'upcoming' ? 'asc' : 'desc')
            ->orderBy('doctor_id')
            ->get();

        // Booked-appointment counts for every (doctor, date) on the page in
        // one grouped query, instead of one count query per row.
        $bookedCounts = collect();

        if ($exceptions->isNotEmpty()) {

            $bookedCounts = DoctorAppointment::query()
                ->selectRaw('doctor_id, DATE(appointment_date) as appt_date, COUNT(*) as total')
                ->whereIn('doctor_id', $exceptions->pluck('doctor_id')->unique())
                ->whereIn('appointment_date', $exceptions->map(fn ($e) => $e->exception_date->toDateString())->unique())
                ->where('appointment_status', 'Booked')
                ->groupBy('doctor_id', 'appt_date')
                ->get()
                ->keyBy(fn ($row) => $row->doctor_id . '|' . $row->appt_date);
        }

        $exceptions->each(function ($exception) use ($bookedCounts) {
            $key = $exception->doctor_id . '|' . $exception->exception_date->toDateString();
            $exception->booked_count = (int) ($bookedCounts[$key]->total ?? 0);
        });

        $doctors = Doctor::whereIn('id', DoctorScheduleException::distinct()->pluck('doctor_id'))
            ->orderBy('doctor_name')
            ->get(['id', 'doctor_name']);

        return view('apps-doctor-unavailable-dates', compact('exceptions', 'doctors', 'scope'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST (upcoming exceptions for one doctor)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
        ]);

        $exceptions = DoctorScheduleException::where('doctor_id', $request->doctor_id)
            ->whereDate('exception_date', '>=', Carbon::today()->toDateString())
            ->orderBy('exception_date')
            ->get();

        $exceptions->each(function ($exception) {

            $exception->booked_count = DoctorAppointment::where('doctor_id', $exception->doctor_id)
                ->where('appointment_date', $exception->exception_date)
                ->where('appointment_status', 'Booked')
                ->count();
        });

        return response()->json([
            'status' => true,
            'data' => $exceptions,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ONE OR MORE BLACKOUT DATES
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService, AppointmentReassignmentService $reassignmentService)
    {
        $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'dates' => 'required|array|min:1',
            'dates.*' => 'date|after_or_equal:today',
            'reason' => 'nullable|string|max:255',
        ]);

        try {

            $doctor = Doctor::findOrFail($request->doctor_id);

            $created = [];
            $skipped = [];
            $warnings = [];

            foreach (array_unique($request->dates) as $date) {

                $exists = DoctorScheduleException::where('doctor_id', $doctor->id)
                    ->whereDate('exception_date', $date)
                    ->exists();

                if ($exists) {
                    $skipped[] = $date;
                    continue;
                }

                $exception = DoctorScheduleException::create([
                    'doctor_id' => $doctor->id,
                    'exception_date' => $date,
                    'reason' => $request->reason,
                    'created_by' => Auth::id(),
                ]);

                $created[] = $date;

                $bookedCount = DoctorAppointment::where('doctor_id', $doctor->id)
                    ->where('appointment_date', $date)
                    ->where('appointment_status', 'Booked')
                    ->count();

                if ($bookedCount > 0) {
                    $warnings[] = "{$date} already has {$bookedCount} booked appointment(s) -- reassign or cancel them separately.";
                }

                $auditService->logCreate(
                    self::MODULE_CODE,
                    $exception,
                    $exception->only($exception->getFillable()),
                    'Doctor schedule exception (blackout date) added'
                );
            }

            $message = count($created) > 0
                ? count($created) . ' date(s) marked unavailable.'
                : 'No new dates added -- all selected dates were already marked unavailable.';

            if (!empty($skipped)) {
                $message .= ' Already unavailable: ' . implode(', ', $skipped) . '.';
            }

            if (!empty($warnings)) {
                $message .= ' ' . implode(' ', $warnings);
            }

            // Read-only: builds suggested reassignments for whatever's already
            // booked on the newly-blacked-out dates, doesn't touch those rows.
            $affectedAppointments = [];

            if (!empty($created)) {

                $affected = DoctorAppointment::where('doctor_id', $doctor->id)
                    ->whereIn('appointment_date', $created)
                    ->where('appointment_status', 'Booked')
                    ->orderBy('appointment_date')
                    ->orderBy('token_no')
                    ->get();

                if ($affected->isNotEmpty()) {
                    $affectedAppointments = $reassignmentService->suggestReassignments($doctor->id, $affected);
                }
            }

            return response()->json([
                'status' => true,
                'message' => $message,
                'reassignment_needed' => !empty($affectedAppointments),
                'affected_appointments' => $affectedAppointments,
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save unavailable date(s).',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RE-ENABLE A DATE
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        try {

            $exception = DoctorScheduleException::findOrFail($id);

            $oldData = $exception->only($exception->getFillable());

            $exception->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $exception,
                $oldData,
                'Doctor schedule exception (blackout date) removed'
            );

            return response()->json([
                'status' => true,
                'message' => 'Date re-enabled successfully.',
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to re-enable this date.',
            ], 500);
        }
    }
}
