<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DoctorPaymentReportController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $doctors = Doctor::orderBy('doctor_name')
            ->get(['id', 'doctor_name']);

        return view('apps-doctor-payment-report', compact('users', 'doctors'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'doctor_id' => 'required',
            'date' => 'required|date',
        ]);

        if ($request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        if ($request->doctor_id !== 'ALL') {
            $request->validate(['doctor_id' => 'exists:doctors,id']);
        }

        $result = $this->buildReport($request->user_id, $request->doctor_id, $request->date);

        return response()->json(array_merge(['status' => true], $result));
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT (PDF)
    |--------------------------------------------------------------------------
    */

    public function print(Request $request)
    {
        $request->validate([
            'user_id' => 'required',
            'doctor_id' => 'required',
            'date' => 'required|date',
        ]);

        if ($request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        if ($request->doctor_id !== 'ALL') {
            $request->validate(['doctor_id' => 'exists:doctors,id']);
        }

        $isAllUsers = $request->user_id === 'ALL';
        $isAllDoctors = $request->doctor_id === 'ALL';

        $userLabel = $isAllUsers ? 'All Users' : optional(User::find($request->user_id))->name;
        $doctorLabel = $isAllDoctors ? 'All Doctors' : optional(Doctor::find($request->doctor_id))->doctor_name;

        $result = $this->buildReport($request->user_id, $request->doctor_id, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-doctor-payment-report-pdf',
            [
                'userLabel' => $userLabel,
                'doctorLabel' => $doctorLabel,
                'isAllUsers' => $isAllUsers,
                'isAllDoctors' => $isAllDoctors,
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'date' => $date,
            ]
        );

        $fileName = 'Doctor-Payment-Report-' .
            str_replace(' ', '-', $doctorLabel ?? 'Doctor') .
            '-' . str_replace(' ', '-', $userLabel ?? 'User') .
            '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildReport($userId, $doctorId, $date): array
    {
        $isAllUsers = $userId === 'ALL';
        $isAllDoctors = $doctorId === 'ALL';

        $userMap = User::whereIn('role', self::STAFF_ROLES)->pluck('name', 'id');

        $query = DB::table('doctor_payables')
            ->join('invoices', 'invoices.id', '=', 'doctor_payables.invoice_id')
            ->whereDate('invoices.invoice_date', $date)
            ->where('doctor_payables.payment_status', '!=', 'CANCELLED');

        if (!$isAllUsers) {
            $query->where('doctor_payables.created_by', $userId);
        }

        if (!$isAllDoctors) {
            $query->where('doctor_payables.doctor_id', $doctorId);
        }

        $rows = $query
            ->orderBy('doctor_payables.created_at')
            ->get([
                'doctor_payables.invoice_no',
                'doctor_payables.doctor_name',
                'doctor_payables.created_by',
                'invoices.patient_name',
                'invoices.card_number',
                'doctor_payables.item_description',
                'doctor_payables.gross_amount',
                'doctor_payables.payable_amount',
                'doctor_payables.last_settlement_no',
                'doctor_payables.created_at',
            ])
            ->map(function ($row) use ($userMap) {
                $row->doctor_fees = round((float) $row->payable_amount, 2);
                $row->clinic_charge = round((float) $row->gross_amount - (float) $row->payable_amount, 2);
                $row->time_fmt = Carbon::parse($row->created_at)->format('h:i A');
                $row->user_name = $userMap[$row->created_by] ?? '-';
                $row->settlement_display = $row->last_settlement_no ?: 'Not Settled';
                return $row;
            });

        return [
            'is_all_users' => $isAllUsers,
            'is_all_doctors' => $isAllDoctors,
            'rows' => $rows->values(),
            'summary' => [
                'total_items' => $rows->count(),
                'total_gross' => round($rows->sum('gross_amount'), 2),
                'total_doctor_fees' => round($rows->sum('doctor_fees'), 2),
                'total_clinic_charge' => round($rows->sum('clinic_charge'), 2),
            ],
        ];
    }
}
