<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AllInvoicesReportController extends Controller
{
    private const INVOICE_TYPE_LABELS = [
        'DOCTOR_VISIT' => 'Doctor Visit',
        'DIAGNOSTIC' => 'Diagnostic',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

    private const PRINT_URL_PREFIXES = [
        'DOCTOR_VISIT' => '/doctor-visit-invoice/print/',
        'DIAGNOSTIC' => '/diagnostic-invoice/print/',
        'OXYGEN_RENT' => '/equipment-rental/print/',
        'CONCENTRATOR_RENT' => '/equipment-rental/print/',
        'AMBULANCE_RENT' => '/ambulance-rental/print/',
        'MEMBERSHIP_FEE' => '/membership-fee/print/',
        'OTHER_INCOME' => '/income/print/',
    ];

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-all-invoices-report');
    }

    /*
    |--------------------------------------------------------------------------
    | LIST (shared by all 3 tabs via ?tab=all|pending|cancelled)
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'tab' => 'required|in:all,pending,cancelled',
            'search' => 'nullable|string',
        ]);

        $perPage = $request->get('per_page', 10);

        $invoices = $this->tabQuery($request)
            ->orderByDesc('id')
            ->paginate($perPage);

        $userNames = DB::table('users')
            ->whereIn('id', $invoices->getCollection()->pluck('created_by')->filter()->unique())
            ->pluck('name', 'id');

        $invoices->getCollection()->transform(function ($row) use ($userNames) {

            $row->invoice_date_fmt = $row->invoice_date
                ? Carbon::parse($row->invoice_date)->format('d-m-Y')
                : null;

            $row->invoice_type_label = self::INVOICE_TYPE_LABELS[$row->invoice_type] ?? $row->invoice_type;

            $row->doctor_display = $row->doctor_name ?: ($row->referred_doctor ?: '-');

            $row->is_cancelled = $row->cancelled === 'Y';

            $row->is_pending = !$row->is_cancelled && (float) $row->due_amount > 0;

            $row->created_by_name = $userNames->get($row->created_by) ?? '-';

            $prefix = self::PRINT_URL_PREFIXES[$row->invoice_type] ?? null;

            $row->print_url = $prefix ? $prefix . $row->id : null;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $invoices->items(),
            'pagination' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | COUNTS (for the 3 tab badges, one date at a time)
    |--------------------------------------------------------------------------
    */

    public function counts(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'search' => 'nullable|string',
        ]);

        return response()->json([
            'status' => true,
            'counts' => [
                'all' => (clone $this->tabQuery($request, 'all'))->count(),
                'pending' => (clone $this->tabQuery($request, 'pending'))->count(),
                'cancelled' => (clone $this->tabQuery($request, 'cancelled'))->count(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY HELPER
    |--------------------------------------------------------------------------
    */

    private function tabQuery(Request $request, ?string $tabOverride = null)
    {
        $tab = $tabOverride ?? $request->tab;

        $query = DB::table('invoices')->whereDate('invoice_date', $request->date);

        if ($tab === 'pending') {

            $query->where('due_amount', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y');
                });

        } elseif ($tab === 'cancelled') {

            $query->where('cancelled', 'Y');
        }
        // 'all' tab: every invoice for the day, cancelled or not, pending or not.

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('patient_mobile_no', 'like', "%{$search}%");
            });
        }

        return $query;
    }
}
