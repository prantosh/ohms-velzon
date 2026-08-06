<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DoctorPayableByUserController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Consultation',
        'DIAGNOSTIC' => 'Diagnostic',
    ];

    private const RANGE_LABELS = [
        '3' => 'Last 3 Days',
        '7' => 'Last 7 Days',
        '30' => 'Last 30 Days',
        '6m' => 'Last 6 Months',
        'all' => 'All Time',
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

        return view('apps-doctor-payable-by-user', compact('users'));
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
            'range' => 'nullable|in:3,7,30,6m,all',
        ]);

        if ($request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        $result = $this->buildDashboard($request->user_id, $request->range ?: '30');

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
            'range' => 'nullable|in:3,7,30,6m,all',
        ]);

        if ($request->user_id !== 'ALL') {
            $request->validate(['user_id' => 'exists:users,id']);
        }

        $result = $this->buildDashboard($request->user_id, $request->range ?: '30');

        $isAllUsers = $request->user_id === 'ALL';

        $userLabel = $isAllUsers
            ? 'All Users'
            : optional(User::find($request->user_id))->name;

        // The printed slip is a pending payout worksheet only -- settled
        // payables are already paid and don't need to be printed.
        $pdf = Pdf::loadView(
            'apps-doctor-payable-by-user-pdf',
            [
                'userLabel' => $userLabel,
                'isAllUsers' => $isAllUsers,
                'rangeLabel' => $result['range_label'],
                'pending' => $result['pending'],
                'summary' => $result['summary'],
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        $fileName = 'Doctor-Payable-By-User-' .
            str_replace(' ', '-', $userLabel ?? 'User') .
            '-' . now()->format('d-m-Y') . '.pdf';

        return $pdf->stream($fileName);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function buildDashboard($userId, $range): array
    {
        $isAllUsers = $userId === 'ALL';

        $userMap = User::whereIn('role', self::STAFF_ROLES)->pluck('name', 'id');

        /*
        |----------------------------------------------------------------
        | PENDING (no date restriction). Split in two:
        |   - collected: doctor-inclusive amount already in a specific
        |     user's hand (due_amount = 0) -- attributed to that collector.
        |   - not yet collected (due_amount > 0): nobody's responsibility
        |     yet, so only surfaced in the "ALL Users" view with a
        |     "COLLECTION DUE" marker instead of a name -- never attributed
        |     to a specific user's filtered view (see DoctorSettlementController
        |     for the same collected-vs-created distinction).
        |----------------------------------------------------------------
        */

        $pendingQuery = DB::table('doctor_payables')
            ->join('invoices', 'doctor_payables.invoice_id', '=', 'invoices.id')
            ->joinSub(
                $this->finalCollectorSubquery(),
                'fc',
                'fc.invoice_reference',
                '=',
                'invoices.invoice_no'
            )
            ->whereIn('doctor_payables.payment_status', ['PENDING', 'APPROVED']);

        if (!$isAllUsers) {
            $pendingQuery->where('invoices.due_amount', 0)
                ->whereRaw(
                    'COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) = ?',
                    [$userId]
                );
        }

        $pending = $pendingQuery
            ->where('doctor_payables.payable_amount', '>', 0)
            ->orderByDesc('doctor_payables.created_at')
            ->get([
                'doctor_payables.id', 'doctor_payables.payable_no', 'doctor_payables.invoice_id',
                'doctor_payables.invoice_no', 'doctor_payables.invoice_type', 'doctor_payables.doctor_name',
                'doctor_payables.patient_name', 'doctor_payables.item_description', 'doctor_payables.gross_amount',
                'doctor_payables.payable_amount', 'doctor_payables.paid_amount', 'doctor_payables.payment_status',
                'doctor_payables.created_at',
                'invoices.due_amount',
                DB::raw('COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) as created_by'),
            ])
            ->map(function ($row) use ($userMap) {
                $row->category = self::INVOICE_TYPE_LABELS[$row->invoice_type] ?? $row->invoice_type;
                $row->balance_amount = round((float) $row->payable_amount - (float) $row->paid_amount, 2);
                $row->created_at_fmt = Carbon::parse($row->created_at)->format('d-m-Y h:i A');

                if ((float) $row->due_amount > 0) {
                    $row->collection_due = true;
                    $row->user_name = 'COLLECTION DUE';
                } else {
                    $row->collection_due = false;
                    $row->user_name = $userMap[$row->created_by] ?? '-';
                }

                $this->attachPrintInfo($row);
                return $row;
            });

        /*
        |----------------------------------------------------------------
        | SETTLED (payment_status PAID, filtered by the last settlement's
        | own date -- not the invoice date -- within the selected window)
        |----------------------------------------------------------------
        */

        $settledQuery = DB::table('doctor_payables')
            ->join('invoices', 'doctor_payables.invoice_id', '=', 'invoices.id')
            ->joinSub(
                $this->finalCollectorSubquery(),
                'fc',
                'fc.invoice_reference',
                '=',
                'invoices.invoice_no'
            )
            ->where('invoices.due_amount', 0)
            ->where('doctor_payables.payment_status', 'PAID');

        if (!$isAllUsers) {
            $settledQuery->whereRaw(
                'COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) = ?',
                [$userId]
            );
        }

        $cutoff = match ($range) {
            '3' => Carbon::now()->subDays(3)->startOfDay(),
            '7' => Carbon::now()->subDays(7)->startOfDay(),
            '30' => Carbon::now()->subDays(30)->startOfDay(),
            '6m' => Carbon::now()->subMonths(6)->startOfDay(),
            default => null,
        };

        if ($cutoff) {
            $settledQuery->where('doctor_payables.last_settlement_date', '>=', $cutoff);
        }

        $settled = $settledQuery
            ->orderByDesc('doctor_payables.last_settlement_date')
            ->get([
                'doctor_payables.id', 'doctor_payables.payable_no', 'doctor_payables.invoice_id',
                'doctor_payables.invoice_no', 'doctor_payables.invoice_type', 'doctor_payables.doctor_name',
                'doctor_payables.patient_name', 'doctor_payables.item_description', 'doctor_payables.gross_amount',
                'doctor_payables.payable_amount', 'doctor_payables.paid_amount', 'doctor_payables.last_settlement_id',
                'doctor_payables.last_settlement_no', 'doctor_payables.last_settlement_date',
                'doctor_payables.settlement_count',
                DB::raw('COALESCE(invoices.doctor_amount_collected_by, fc.final_collector_id) as created_by'),
            ])
            ->map(function ($row) use ($userMap) {
                $row->category = self::INVOICE_TYPE_LABELS[$row->invoice_type] ?? $row->invoice_type;
                $row->last_settlement_date_fmt = $row->last_settlement_date
                    ? Carbon::parse($row->last_settlement_date)->format('d-m-Y')
                    : '-';
                $row->user_name = $userMap[$row->created_by] ?? '-';
                $row->voucher_url = $row->last_settlement_id
                    ? route('doctor-settlement.pdf', $row->last_settlement_id)
                    : null;
                $this->attachPrintInfo($row);
                return $row;
            });

        return [
            'is_all_users' => $isAllUsers,
            'range' => $range,
            'range_label' => self::RANGE_LABELS[$range] ?? self::RANGE_LABELS['30'],
            'pending' => $pending->values(),
            'settled' => $settled->values(),
            'summary' => [
                'pending_count' => $pending->count(),
                'pending_payable_amount' => round($pending->sum('payable_amount'), 2),
                'pending_balance_amount' => round($pending->sum('balance_amount'), 2),
                'settled_count' => $settled->count(),
                'settled_amount' => round($settled->sum('paid_amount'), 2),
            ],
        ];
    }

    /**
     * Mirrors DoctorSettlementController::finalCollectorSubquery() -- for a
     * given invoice, the doctor-inclusive amount belongs to whoever posted
     * the LATEST (final) RECEIVED transaction, used as a fallback for
     * invoices predating the invoices.doctor_amount_collected_by column.
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

    /**
     * Diagnostic invoice PDFs stream directly from a GET request, so a plain
     * URL is enough. Doctor visit invoice PDFs are generated on demand and
     * the endpoint returns JSON {status, pdf_url} -- the frontend must call
     * it via AJAX and open the returned pdf_url, not link to it directly.
     */
    private function attachPrintInfo($row): void
    {
        if (empty($row->invoice_id)) {
            $row->print_url = null;
            $row->print_ajax_url = null;
            return;
        }

        if ($row->invoice_type === 'DIAGNOSTIC') {
            $row->print_url = route('diagnostic-invoice.print', $row->invoice_id);
            $row->print_ajax_url = null;
        } else {
            $row->print_url = null;
            $row->print_ajax_url = route('doctor-visit-invoice.print', $row->invoice_id);
        }
    }
}
