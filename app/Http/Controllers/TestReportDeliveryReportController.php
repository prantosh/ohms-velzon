<?php

namespace App\Http\Controllers;

use App\Models\TestReportDelivery;
use App\Models\User;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Report over test_report_deliveries -- the append-only log of every
 * "mark as delivered" action recorded by TestReportDashboardController.
 * One invoice can appear more than once here if it was delivered,
 * unmarked, then re-delivered.
 */
class TestReportDeliveryReportController extends Controller
{
    private const STAFF_ROLES = ['Admin', 'Supervisor', 'Employee'];

    public function index()
    {
        $users = User::whereIn('role', self::STAFF_ROLES)->orderBy('name')->get(['id', 'name', 'role']);

        return view('apps-test-report-delivery-report', compact('users'));
    }

    public function list(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);

        $rows = $this->filteredQuery($request)
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows->getCollection()->transform(function ($row) {
            return $this->toRow($row);
        });

        return response()->json([
            'status' => true,
            'data' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function print(Request $request)
    {
        $rows = $this->filteredQuery($request)
            ->orderByDesc('delivered_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                return $this->toRow($row);
            });

        $isAllUsers = !$request->filled('user_id') || $request->user_id === 'ALL';

        $userLabel = $isAllUsers
            ? 'All Users'
            : optional(User::find($request->user_id))->name;

        $pdf = Pdf::loadView('apps-test-report-delivery-report-pdf', [
            'rows' => $rows,
            'userLabel' => $userLabel,
            'search' => $request->search,
            'fromDateFmt' => $request->from_date ? Carbon::parse($request->from_date)->format('d-m-Y') : null,
            'toDateFmt' => $request->to_date ? Carbon::parse($request->to_date)->format('d-m-Y') : null,
            'totalCount' => $rows->count(),
            'printedBy' => optional(auth()->user())->name,
        ]);

        return $pdf->stream('Test-Report-Delivery-Log-' . now()->format('d-m-Y') . '.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = TestReportDelivery::query()->with('deliveredByUser');

        if ($request->filled('user_id') && $request->user_id !== 'ALL') {
            $query->where('delivered_by', $request->user_id);
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('delivered_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('delivered_at', '<=', $request->to_date);
        }

        return $query;
    }

    private function toRow(TestReportDelivery $row): array
    {
        $paymentStatus = $row->due_amount <= 0
            ? 'Paid'
            : ($row->paid_amount <= 0 ? 'Due' : 'Partial');

        return [
            'id' => $row->id,
            'invoice_no' => $row->invoice_no,
            'patient_name' => $row->patient_name,
            'patient_mobile_no' => $row->patient_mobile_no,
            'total_amount' => (float) $row->total_amount,
            'paid_amount' => (float) $row->paid_amount,
            'due_amount' => (float) $row->due_amount,
            'payment_status' => $paymentStatus,
            'delivered_by_name' => optional($row->deliveredByUser)->name ?? '-',
            'delivered_at_fmt' => $row->delivered_at ? $row->delivered_at->format('d-m-Y h:i A') : '-',
        ];
    }
}
