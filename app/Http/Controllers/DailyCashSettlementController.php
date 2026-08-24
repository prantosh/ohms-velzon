<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DailyCashSettlementController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private const INVOICE_TYPE_LABELS = ['DOCTOR_VISIT' => 'Doctor Consultation', 'DIAGNOSTIC' => 'Diagnostic'];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)->orderBy('name')->get(['id', 'name', 'role']);

        return view('apps-daily-cash-settlement', compact('users'));
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
            'date' => 'required|date',
        ]);

        if ($request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        $result = $this->buildReport($request->user_id, $request->date);

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
            'date' => 'required|date',
        ]);

        if ($request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        $isAllUsers = $request->user_id === 'ALL';

        $userLabel = $isAllUsers
            ? 'All Users'
            : optional(User::find($request->user_id))->name;

        $result = $this->buildReport($request->user_id, $request->date);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView('apps-daily-cash-settlement-pdf', [
            'userLabel' => $userLabel,
            'isAllUsers' => $isAllUsers,
            'dateFmt' => $date->format('d-m-Y'),
            'groups' => $result['groups'],
            'summary' => $result['summary'],
            'printedBy' => optional(auth()->user())->name,
        ]);

        $fileName = 'Daily-Cash-Settlement-' . str_replace(' ', '-', $userLabel) . '-' . $date->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Doctor-wise breakdown of everything settled (paid to a doctor) on one
     * given day, scoped to one user (or ALL) via the same collector
     * attribution used across every doctor-payable/settlement screen --
     * whoever collected the invoice's final payment installment (or the
     * explicit invoices.doctor_amount_collected_by override) is credited
     * with having settled it, since that's who physically had the doctor's
     * cash in hand. "For a day" here means last_settlement_date -- the day
     * the doctor was actually paid -- not the invoice date, since this
     * report is specifically about the day's cash settlement activity.
     */
    private function buildReport($userId, $date): array
    {
        $isAllUsers = $userId === 'ALL';

        $userMap = User::whereIn('role', self::STAFF_ROLES)->pluck('name', 'id');

        $query = DB::table('doctor_payables')
            ->join('invoices', 'doctor_payables.invoice_id', '=', 'invoices.id')
            ->joinSub(
                $this->finalCollectorSubquery(),
                'fc',
                'fc.invoice_reference',
                '=',
                'invoices.invoice_no'
            )
            ->where('invoices.due_amount', 0)
            ->where('doctor_payables.payment_status', 'PAID')
            ->whereDate('doctor_payables.last_settlement_date', $date);

        if (!$isAllUsers) {
            $query->whereRaw(
                'COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) = ?',
                [$userId]
            );
        }

        $rows = $query
            ->orderBy('doctor_payables.doctor_name')
            ->orderBy('doctor_payables.invoice_no')
            ->get([
                'doctor_payables.id', 'doctor_payables.payable_no', 'doctor_payables.invoice_no',
                'doctor_payables.invoice_type', 'doctor_payables.doctor_id', 'doctor_payables.doctor_name',
                'doctor_payables.patient_name', 'doctor_payables.item_description', 'doctor_payables.paid_amount',
                'doctor_payables.last_settlement_no',
                DB::raw('COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) as collected_by'),
            ])
            ->map(function ($row) use ($isAllUsers, $userMap) {
                $row->category = self::INVOICE_TYPE_LABELS[$row->invoice_type] ?? $row->invoice_type;
                $row->user_name = $isAllUsers ? ($userMap[$row->collected_by] ?? '-') : null;
                return $row;
            });

        $groups = $rows->groupBy('doctor_id')
            ->map(function ($group) {
                return [
                    'doctor_id' => $group->first()->doctor_id,
                    'doctor_name' => $group->first()->doctor_name,
                    'items' => $group->values(),
                    'total_amount' => round($group->sum('paid_amount'), 2),
                    'invoice_count' => $group->count(),
                ];
            })
            ->sortBy('doctor_name')
            ->values();

        return [
            'is_all_users' => $isAllUsers,
            'groups' => $groups,
            'summary' => [
                'total_doctors' => $groups->count(),
                'total_invoices' => $rows->count(),
                'total_amount' => round($rows->sum('paid_amount'), 2),
            ],
        ];
    }

    /**
     * For a given invoice, multiple RECEIVED transactions may exist (phased
     * payments, possibly collected by different users). The doctor payment
     * for that invoice is the responsibility of whoever collected the LATEST
     * (final) installment -- earlier partial-payment collectors are excluded.
     * Same subquery as DoctorPayableByUserController/DoctorSettlementController.
     */
    private function finalCollectorSubquery()
    {
        $ranked = DB::table('daily_transactions')
            ->select('invoice_reference', 'operator_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY invoice_reference ORDER BY created_at DESC) as rn')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', '!=', 'CANCELLED')
            ->whereNotNull('invoice_reference');

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('rn', 1)
            ->select('invoice_reference', 'operator_id as final_collector_id');
    }
}
