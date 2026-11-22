<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Every additional (manual, approver-authorised) discount given on any
 * invoice. Source is invoice_details: additional_discount_percent /
 * additional_discount_amount + discount_approved_by, joined to invoices by
 * invoice_no. Diagnostic lines can carry both a percent and a flat amount
 * (percent is rounded to the nearest 10, same as the invoice maths);
 * rental lines only ever use the flat amount.
 */
class AdditionalDiscountReportController extends Controller
{
    private const TYPE_OPTIONS = [
        'DOCTOR_VISIT' => 'Doctor Visit',
        'DIAGNOSTIC_PATHOLOGY' => 'Pathology',
        'DIAGNOSTIC_NON_PATHOLOGY' => 'Non-Pathology',
        'OXYGEN_RENT' => 'Oxygen Concentrator/Cylinder Rental',
        'CONCENTRATOR_RENT' => 'Concentrator Rental',
        'AMBULANCE_RENT' => 'Ambulance Rental',
        'MEMBERSHIP_FEE' => 'Membership Fee',
        'OTHER_INCOME' => 'Income from Other Source',
    ];

    public function index()
    {
        $approvers = User::where('authorised', 'YES')->orderBy('name')->get(['id', 'name']);
        $operators = User::orderBy('name')->get(['id', 'name']);
        $invoiceTypes = self::TYPE_OPTIONS;

        return view('apps-additional-discount-report', compact('approvers', 'operators', 'invoiceTypes'));
    }

    public function data(Request $request)
    {
        $rows = $this->fetchRows($request);

        return response()->json([
            'status' => true,
            'rows' => $rows,
            'summary' => $this->summarise($rows),
        ]);
    }

    public function print(Request $request)
    {
        ini_set('memory_limit', '512M');

        $rows = $this->fetchRows($request);

        $pdf = Pdf::loadView('apps-additional-discount-report-pdf', [
            'rows' => $rows,
            'summary' => $this->summarise($rows),
            'fromDate' => Carbon::parse($request->from_date),
            'toDate' => Carbon::parse($request->to_date),
            'filters' => $this->describeFilters($request),
            'printedBy' => optional(auth()->user())->name,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream(
            'Additional-Discount-Report-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    private function rules(): array
    {
        return [
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'approver_id' => 'nullable|exists:users,id',
            'operator_id' => 'nullable|exists:users,id',
            'invoice_type' => 'nullable|in:ALL,' . implode(',', array_keys(self::TYPE_OPTIONS)),
            'include_cancelled' => 'nullable|in:0,1',
        ];
    }

    private function fetchRows(Request $request)
    {
        $request->validate($this->rules());

        $query = DB::table('invoice_details as d')
            ->join('invoices as i', 'i.invoice_no', '=', 'd.invoice_no')
            ->leftJoin('users as ap', 'ap.id', '=', 'd.discount_approved_by')
            ->leftJoin('users as op', 'op.id', '=', 'i.created_by')
            ->whereBetween('i.invoice_date', [$request->from_date, $request->to_date])
            ->where(function ($q) {
                $q->where('d.additional_discount_amount', '>', 0)
                  ->orWhere('d.additional_discount_percent', '>', 0);
            });

        if (!$request->boolean('include_cancelled')) {
            $query->where('i.cancelled', '!=', 'Y');
        }

        if ($request->filled('approver_id')) {
            $query->where('d.discount_approved_by', $request->approver_id);
        }

        if ($request->filled('operator_id')) {
            $query->where('i.created_by', $request->operator_id);
        }

        $type = $request->invoice_type;
        if ($type && $type !== 'ALL') {
            if ($type === 'DIAGNOSTIC_PATHOLOGY') {
                $query->where('i.invoice_type', 'DIAGNOSTIC')->where('i.invoice_category', 'PATHOLOGY');
            } elseif ($type === 'DIAGNOSTIC_NON_PATHOLOGY') {
                $query->where('i.invoice_type', 'DIAGNOSTIC')->where('i.invoice_category', 'NON_PATHOLOGY');
            } else {
                $query->where('i.invoice_type', $type);
            }
        }

        return $query
            ->orderBy('i.invoice_date')
            ->orderBy('i.invoice_no')
            ->orderBy('d.line_no')
            ->get([
                'i.invoice_no', 'i.invoice_date', 'i.invoice_type', 'i.invoice_category',
                'i.patient_name', 'i.cancelled',
                'd.item_description', 'd.rate',
                'd.additional_discount_percent', 'd.additional_discount_amount',
                'd.discount_approval_remarks',
                'ap.name as approver_name', 'op.name as operator_name',
            ])
            ->map(function ($r) {
                $percent = (float) $r->additional_discount_percent;
                $percentAmt = round(((float) $r->rate * $percent / 100) / 10) * 10;
                $r->discount_total = round($percentAmt + (float) $r->additional_discount_amount, 2);
                $r->type_label = $this->typeLabel($r);
                $r->date_fmt = Carbon::parse($r->invoice_date)->format('d-m-Y');
                $r->approver_name = $r->approver_name ?: '-';
                $r->operator_name = $r->operator_name ?: '-';
                $r->is_cancelled = $r->cancelled === 'Y';
                return $r;
            })
            ->filter(fn($r) => $r->discount_total > 0)
            ->values();
    }

    private function typeLabel($r): string
    {
        if ($r->invoice_type === 'DIAGNOSTIC') {
            return $r->invoice_category === 'PATHOLOGY' ? 'Pathology' : 'Non-Pathology';
        }

        return self::TYPE_OPTIONS[$r->invoice_type] ?? $r->invoice_type;
    }

    private function summarise($rows): array
    {
        $active = $rows->where('is_cancelled', false);

        $group = fn($key) => $active->groupBy($key)->map(fn($g, $k) => [
            'name' => $k,
            'count' => $g->count(),
            'total' => round($g->sum('discount_total'), 2),
        ])->sortByDesc('total')->values();

        return [
            'lines' => $rows->count(),
            'invoices' => $rows->pluck('invoice_no')->unique()->count(),
            'total_discount' => round($active->sum('discount_total'), 2),
            'by_approver' => $group('approver_name'),
            'by_operator' => $group('operator_name'),
            'by_type' => $group('type_label'),
        ];
    }

    private function describeFilters(Request $request): array
    {
        $name = fn($id) => $id ? DB::table('users')->where('id', $id)->value('name') : 'All';
        $type = $request->invoice_type;

        return [
            'Approver' => $name($request->approver_id),
            'Operator' => $name($request->operator_id),
            'Invoice Type' => ($type && $type !== 'ALL') ? (self::TYPE_OPTIONS[$type] ?? $type) : 'All',
            'Cancelled Invoices' => $request->boolean('include_cancelled') ? 'Included (not in totals)' : 'Excluded',
        ];
    }
}
