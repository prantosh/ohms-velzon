<?php

namespace App\Http\Controllers;

use App\Models\ExpenditureCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Expenditure report, category-wise, for any period: a summary (entries and amount per
 * category) and the entry-by-entry detail of one category or of all of them.
 */
class ExpenditureReportController extends Controller
{
    public function index()
    {
        $categories = ExpenditureCategory::orderBy('description')->get(['id', 'description']);

        return view('apps-expenditure-report', compact('categories'));
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY -- one row per category with entries in the period
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        [$from, $to] = $this->period($request);

        return response()->json(array_merge(['status' => true], $this->summaryData($from, $to, $request->query('category_id'))));
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL -- the entries of one category (or, without category_id, of every category)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        [$from, $to] = $this->period($request);

        return response()->json(array_merge(['status' => true], $this->detailData($from, $to, $request->query('category_id'))));
    }

    /*
    |--------------------------------------------------------------------------
    | PRINT (PDF) -- type=summary or type=detail (optionally for one category)
    |--------------------------------------------------------------------------
    */

    public function print(Request $request)
    {
        [$from, $to] = $this->period($request);

        $categoryId = $request->query('category_id');

        $periodText = Carbon::parse($from)->format('d-m-Y') . ' to ' . Carbon::parse($to)->format('d-m-Y');

        if ($request->query('type') === 'detail') {

            $pdf = Pdf::loadView('apps-expenditure-report-detail-pdf', [
                'period' => $periodText,
                'data' => $this->detailData($from, $to, $categoryId),
                'printedBy' => optional(auth()->user())->name,
            ]);

            $pdf->setPaper('A4', 'landscape');

            return $pdf->stream('Expenditure-Detail-' . $from . '-to-' . $to . '.pdf');
        }

        $pdf = Pdf::loadView('apps-expenditure-report-summary-pdf', [
            'period' => $periodText,
            'data' => $this->summaryData($from, $to, $categoryId),
            'printedBy' => optional(auth()->user())->name,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('Expenditure-Summary-' . $from . '-to-' . $to . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | DATA BUILDERS (shared by the screen and the PDFs)
    |--------------------------------------------------------------------------
    */

    /** @return array{0:string,1:string} [from, to] as Y-m-d */
    private function period(Request $request): array
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'category_id' => 'nullable|integer',
        ]);

        return [Carbon::parse($request->from_date)->toDateString(), Carbon::parse($request->to_date)->toDateString()];
    }

    private function baseQuery(string $from, string $to, $categoryId = null)
    {
        $query = DB::table('expenditure_transactions as t')
            ->leftJoin('expenditure_categories as c', 'c.id', '=', 't.expenditure_category_id')
            ->whereBetween('t.transaction_date', [$from, $to]);

        if ($categoryId !== null && $categoryId !== '' && $categoryId !== 'ALL') {
            $query->where('t.expenditure_category_id', (int) $categoryId);
        }

        return $query;
    }

    private function summaryData(string $from, string $to, $categoryId = null): array
    {
        $rows = $this->baseQuery($from, $to, $categoryId)
            ->groupBy('t.expenditure_category_id', 'c.description')
            ->orderBy('c.description')
            ->selectRaw('t.expenditure_category_id as category_id, c.description as category, COUNT(*) as entries, SUM(t.amount) as amount')
            ->get();

        $grand = (float) $rows->sum('amount');

        $rows = $rows->map(fn ($r) => [
            'category_id' => $r->category_id,
            'category' => $r->category ?? '(no category)',
            'entries' => (int) $r->entries,
            'amount' => round((float) $r->amount, 2),
            'share' => $grand > 0 ? round(((float) $r->amount / $grand) * 100, 1) : 0,
        ])->values();

        return [
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'total_entries' => (int) $rows->sum('entries'),
            'total_amount' => round($grand, 2),
        ];
    }

    private function detailData(string $from, string $to, $categoryId = null): array
    {
        $entries = $this->baseQuery($from, $to, $categoryId)
            ->leftJoin('expenditure_agencies as a', 'a.id', '=', 't.expenditure_agency_id')
            ->leftJoin('users as u', 'u.id', '=', 't.expenditure_by')
            ->orderBy('c.description')
            ->orderBy('t.transaction_date')
            ->orderBy('t.id')
            ->get([
                't.id', 't.expenditure_category_id', 'c.description as category', 't.transaction_date', 't.voucher_number',
                't.bill_number', 'a.description as agency', 't.payment_mode', 't.cheque_number', 't.amount',
                'u.name as spent_by', 't.remarks',
            ]);

        $groups = $entries->groupBy(fn ($e) => $e->category ?? '(no category)')->map(function ($items, $category) {

            return [
                'category' => $category,
                'category_id' => $items->first()->expenditure_category_id,
                'entries' => $items->count(),
                'amount' => round((float) $items->sum('amount'), 2),
                'rows' => $items->map(fn ($e) => [
                    'date_fmt' => $e->transaction_date ? Carbon::parse($e->transaction_date)->format('d-m-Y') : '',
                    'voucher_number' => $e->voucher_number,
                    'bill_number' => $e->bill_number,
                    'paid_to' => $e->agency,
                    'payment_mode' => $e->payment_mode,
                    'cheque_number' => $e->cheque_number,
                    'amount' => round((float) $e->amount, 2),
                    'spent_by' => $e->spent_by,
                    'remarks' => $e->remarks,
                ])->values(),
            ];
        })->values();

        return [
            'from' => $from,
            'to' => $to,
            'groups' => $groups,
            'total_entries' => (int) $groups->sum('entries'),
            'total_amount' => round((float) $groups->sum('amount'), 2),
        ];
    }
}
