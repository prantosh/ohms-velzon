<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsappMessageReportController extends Controller
{
    private const MESSAGE_TYPES = ['INVOICE', 'TEST_REPORT', 'APPOINTMENT', 'OTP', 'DOCTOR_APPOINTMENT_REASSIGNED'];

    private const TYPE_LABELS = [
        'INVOICE' => 'Invoice',
        'TEST_REPORT' => 'Test Report',
        'APPOINTMENT' => 'Appointment',
        'OTP' => 'OTP',
        'DOCTOR_APPOINTMENT_REASSIGNED' => 'Appointment Reassigned (Blackout)',
    ];

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

        $typeSums = collect(self::MESSAGE_TYPES)->map(function ($type) {
            $col = strtolower($type);
            return "SUM(CASE WHEN message_type = '{$type}' THEN 1 ELSE 0 END) as type_{$col}";
        })->implode(', ');

        $rows = DB::table('whatsapp_message_logs')
            ->whereBetween(DB::raw('DATE(created_at)'), [$request->from_date, $request->to_date])
            ->selectRaw("
                DATE(created_at) as log_date,
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'SENT' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_count,
                {$typeSums}
            ")
            ->groupBy('log_date')
            ->orderBy('log_date')
            ->get()
            ->map(function ($row) {
                return $this->decorateSummaryRow($row);
            });

        return response()->json([
            'status' => true,
            'rows' => $rows->values(),
            'grand_total' => $this->sumSummaryRows($rows),
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
            ->get(['id', 'invoice_no', 'mobile_no', 'message_type', 'message_id', 'status', 'response', 'created_at'])
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

    private function decorateSummaryRow($row)
    {
        $row->date_fmt = Carbon::parse($row->log_date)->format('d-m-Y (D)');
        $row->total_count = (int) $row->total_count;
        $row->sent_count = (int) $row->sent_count;
        $row->failed_count = (int) $row->failed_count;

        foreach (self::MESSAGE_TYPES as $type) {
            $col = 'type_' . strtolower($type);
            $row->$col = (int) ($row->$col ?? 0);
        }

        return $row;
    }

    private function sumSummaryRows($rows): array
    {
        $totals = [
            'total_count' => (int) $rows->sum('total_count'),
            'sent_count' => (int) $rows->sum('sent_count'),
            'failed_count' => (int) $rows->sum('failed_count'),
        ];

        foreach (self::MESSAGE_TYPES as $type) {
            $col = 'type_' . strtolower($type);
            $totals[$col] = (int) $rows->sum($col);
        }

        return $totals;
    }

    private function decorateDetailRow($row)
    {
        $row->type_label = self::TYPE_LABELS[$row->message_type] ?? $row->message_type;
        $row->time_fmt = Carbon::parse($row->created_at)->format('h:i A');

        // Successful sends carry a large, mostly-irrelevant WATI contact
        // payload -- not useful in a report. Failed sends carry the actual
        // error, which is the whole point of showing this column.
        $row->response_preview = $row->status === 'FAILED'
            ? \Illuminate\Support\Str::limit((string) $row->response, 200)
            : null;

        unset($row->response);

        return $row;
    }

    private function sumDetailRows($rows): array
    {
        $summary = [
            'total_count' => $rows->count(),
            'sent_count' => $rows->where('status', 'SENT')->count(),
            'failed_count' => $rows->where('status', 'FAILED')->count(),
        ];

        foreach (self::MESSAGE_TYPES as $type) {
            $col = 'type_' . strtolower($type);
            $summary[$col] = $rows->where('message_type', $type)->count();
        }

        return $summary;
    }
}
