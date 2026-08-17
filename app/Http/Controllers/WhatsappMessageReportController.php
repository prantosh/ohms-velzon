<?php

namespace App\Http\Controllers;

use App\Support\WhatsappMessageTypeRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappMessageReportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        return view('apps-whatsapp-message-report');
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (date range, grouped by day)
    |--------------------------------------------------------------------------
    */

    public function summary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $typeColumns = WhatsappMessageTypeRegistry::resolveTypeColumns();

        $raw = DB::table('whatsapp_message_logs')
            ->whereBetween(DB::raw('DATE(created_at)'), [$request->from_date, $request->to_date])
            ->selectRaw("
                DATE(created_at) as log_date,
                message_type,
                COUNT(*) as cnt,
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'SKIPPED' THEN 1 ELSE 0 END) as skipped
            ")
            ->groupBy('log_date', 'message_type')
            ->orderBy('log_date')
            ->get();

        $rows = $raw->groupBy('log_date')
            ->map(fn($dayRows, $logDate) => $this->buildSummaryRow($logDate, $dayRows, $typeColumns))
            ->values();

        return response()->json([
            'status' => true,
            'type_columns' => collect($typeColumns)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'rows' => $rows,
            'grand_total' => $this->sumSummaryRows($rows, $typeColumns),
        ]);
    }

    public function printSummary(Request $request)
    {
        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $result = json_decode($this->summary($request)->getContent(), true);

        $pdf = Pdf::loadView(
            'apps-whatsapp-message-summary-pdf',
            [
                'rows' => $result['rows'],
                'grandTotal' => $result['grand_total'],
                'typeColumns' => $result['type_columns'],
                'fromDate' => Carbon::parse($request->from_date),
                'toDate' => Carbon::parse($request->to_date),
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        return $pdf->stream(
            'WhatsApp-Message-Summary-' .
            Carbon::parse($request->from_date)->format('d-m-Y') . '-to-' .
            Carbon::parse($request->to_date)->format('d-m-Y') . '.pdf'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETAIL (single day, every message)
    |--------------------------------------------------------------------------
    */

    public function detail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $rows = DB::table('whatsapp_message_logs')
            ->whereDate('created_at', $request->date)
            ->orderBy('created_at')
            ->get(['id', 'invoice_no', 'patient_name', 'mobile_no', 'message_type', 'message_id', 'status', 'response', 'created_at'])
            ->map(function ($row) {
                return $this->decorateDetailRow($row);
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'summary' => $this->sumDetailRows($rows),
        ]);
    }

    public function printDetail(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $result = json_decode($this->detail($request)->getContent(), true);

        $date = Carbon::parse($request->date);

        $pdf = Pdf::loadView(
            'apps-whatsapp-message-detail-pdf',
            [
                'rows' => $result['rows'],
                'summary' => $result['summary'],
                'date' => $date,
                'printedBy' => optional(auth()->user())->name,
            ]
        );

        return $pdf->stream('WhatsApp-Message-Detail-' . $date->format('d-m-Y') . '.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * @param string $logDate
     * @param \Illuminate\Support\Collection $dayRows one row per message_type present on this date
     * @param array<string,string> $typeColumns full set of type columns to fill (0 where absent that day)
     */
    private function buildSummaryRow(string $logDate, $dayRows, array $typeColumns): array
    {
        $types = [];
        foreach ($typeColumns as $key => $label) {
            $match = $dayRows->firstWhere('message_type', $key);
            $types[$key] = $match ? (int) $match->cnt : 0;
        }

        return [
            'log_date' => $logDate,
            'date_fmt' => Carbon::parse($logDate)->format('d-m-Y (D)'),
            'total_count' => (int) $dayRows->sum('cnt'),
            'sent_count' => (int) $dayRows->sum('sent'),
            'failed_count' => (int) $dayRows->sum('failed'),
            'skipped_count' => (int) $dayRows->sum('skipped'),
            'types' => $types,
        ];
    }

    private function sumSummaryRows($rows, array $typeColumns): array
    {
        $totals = [
            'total_count' => (int) $rows->sum('total_count'),
            'sent_count' => (int) $rows->sum('sent_count'),
            'failed_count' => (int) $rows->sum('failed_count'),
            'skipped_count' => (int) $rows->sum('skipped_count'),
        ];

        $types = [];
        foreach ($typeColumns as $key => $label) {
            $types[$key] = (int) $rows->sum(fn($row) => $row['types'][$key] ?? 0);
        }
        $totals['types'] = $types;

        return $totals;
    }

    private function decorateDetailRow($row)
    {
        $row->type_label = WhatsappMessageTypeRegistry::label($row->message_type);
        $row->time_fmt = Carbon::parse($row->created_at)->format('h:i A');

        // Successful sends carry a large, mostly-irrelevant WATI contact
        // payload -- not useful in a report. Failed and skipped sends carry
        // the actual error/reason, which is the whole point of this column.
        $row->response_preview = in_array($row->status, ['FAILED', 'SKIPPED'])
            ? \Illuminate\Support\Str::limit((string) $row->response, 200)
            : null;

        unset($row->response);

        return $row;
    }

    private function sumDetailRows($rows): array
    {
        return [
            'total_count' => $rows->count(),
            'sent_count' => $rows->where('status', 'SENT')->count(),
            'failed_count' => $rows->where('status', 'FAILED')->count(),
            'skipped_count' => $rows->where('status', 'SKIPPED')->count(),
        ];
    }
}
