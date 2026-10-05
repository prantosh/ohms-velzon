<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Day-wise summary of every cancelled invoice (any invoice type) within a
 * date range, with a single-date drill-down for the full list. Grouped by
 * cancelled_at (the day the cancellation actually happened), not
 * invoice_date -- a due/old invoice is frequently cancelled days after it
 * was raised, so organizing by invoice_date would scatter a single day's
 * worth of cancellation activity across whatever dates the underlying
 * invoices happened to carry.
 */
class InvoiceCancellationHistoryController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Visit',
        'DIAGNOSTIC' => 'Diagnostic',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

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

        return view('apps-invoice-cancellation-history', compact('users'));
    }

    /*
    |--------------------------------------------------------------------------
    | DAY-WISE SUMMARY -- ONE ROW PER DATE WITHIN THE RANGE
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'user_id' => 'nullable|string',
        ]);

        if ($request->filled('user_id') && $request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        $rows = DB::table('invoices')
            ->where('cancelled', 'Y')
            ->whereDate('cancelled_at', '>=', $request->from_date)
            ->whereDate('cancelled_at', '<=', $request->to_date)
            ->when(
                $request->filled('user_id') && $request->user_id !== 'ALL',
                fn ($q) => $q->where('cancelled_by', $request->user_id)
            )
            ->selectRaw('DATE(cancelled_at) as date')
            ->selectRaw('COUNT(*) as cancelled_count')
            ->selectRaw('SUM(total_amount) as total_amount')
            ->selectRaw('SUM(paid_amount) as paid_amount')
            ->groupBy(DB::raw('DATE(cancelled_at)'))
            ->orderByDesc('date')
            ->get()
            ->map(function ($row) {

                $row->date_fmt = Carbon::parse($row->date)->format('d-m-Y');
                $row->total_amount = round((float) $row->total_amount, 2);
                $row->paid_amount = round((float) $row->paid_amount, 2);

                return $row;
            });

        return response()->json([
            'status' => true,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'data' => $rows,
            'summary' => [
                'cancelled_count' => $rows->sum('cancelled_count'),
                'total_amount' => round($rows->sum('total_amount'), 2),
                'paid_amount' => round($rows->sum('paid_amount'), 2),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL -- EVERY CANCELLED INVOICE ON ONE SINGLE DATE
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'user_id' => 'nullable|string',
        ]);

        if ($request->filled('user_id') && $request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        $userNames = User::pluck('name', 'id');

        $rows = DB::table('invoices')
            ->where('cancelled', 'Y')
            ->whereDate('cancelled_at', $request->date)
            ->when(
                $request->filled('user_id') && $request->user_id !== 'ALL',
                fn ($q) => $q->where('cancelled_by', $request->user_id)
            )
            ->orderBy('cancelled_at')
            ->get([
                'id', 'invoice_no', 'invoice_type', 'patient_name', 'patient_mobile_no',
                'total_amount', 'paid_amount', 'due_amount',
                'invoice_date', 'cancelled_by', 'cancelled_at',
                'cancellation_approved_by', 'cancellation_remarks',
            ])
            ->map(function ($row) use ($userNames) {

                $row->invoice_type_label = self::INVOICE_TYPE_LABELS[$row->invoice_type] ?? $row->invoice_type;
                $row->invoice_date_fmt = $row->invoice_date
                    ? Carbon::parse($row->invoice_date)->format('d-m-Y')
                    : null;
                $row->cancelled_at_fmt = $row->cancelled_at
                    ? Carbon::parse($row->cancelled_at)->format('d-m-Y h:i A')
                    : null;
                $row->cancelled_by_name = $userNames->get($row->cancelled_by) ?? '-';
                $row->approved_by_name = $row->cancellation_approved_by
                    ? ($userNames->get($row->cancellation_approved_by) ?? '-')
                    : null;

                return $row;
            });

        return response()->json([
            'status' => true,
            'date' => $request->date,
            'date_fmt' => Carbon::parse($request->date)->format('d-m-Y'),
            'data' => $rows,
            'summary' => [
                'cancelled_count' => $rows->count(),
                'total_amount' => round($rows->sum('total_amount'), 2),
                'paid_amount' => round($rows->sum('paid_amount'), 2),
            ],
        ]);
    }
}
