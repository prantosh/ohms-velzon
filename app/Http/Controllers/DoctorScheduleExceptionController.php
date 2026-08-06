<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\DoctorAppointment;
use App\Models\DoctorScheduleException;
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

    public function store(Request $request, AuditService $auditService)
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

            return response()->json([
                'status' => true,
                'message' => $message,
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
