<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\ReportPrintDueLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * View of reports printed while the invoice still had a payment due (see
 * ReportPrintDueLogger). Admin always; other roles via Role Page Access
 * ('report-print-due-log', enforced by CheckPageAccess).
 */
class ReportPrintDueLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->filteredQuery($request)->paginate(25)->withQueryString();
        $this->attachFinalPayment($logs);

        return view('apps-report-print-due-log', compact('logs'));
    }

    public function pdf(Request $request)
    {
        ini_set('memory_limit', '512M');

        $logs = $this->filteredQuery($request)->get();
        $this->attachFinalPayment($logs);

        $pdf = Pdf::loadView('apps-report-print-due-log-pdf', [
            'logs' => $logs,
            'filters' => [
                'From' => $request->filled('from') ? $request->from : 'All',
                'To' => $request->filled('to') ? $request->to : 'All',
                'Search' => $request->filled('search') ? trim($request->search) : '-',
                'Final Payment' => ['paid' => 'Fully Paid', 'due' => 'Due'][$request->input('payment_status')] ?? 'All',
            ],
            'printedBy' => optional(Auth::user())->name,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->stream('Report-Print-With-Due-Log-' . now()->format('d-m-Y') . '.pdf');
    }

    private function filteredQuery(Request $request)
    {
        $query = ReportPrintDueLog::query();

        if ($request->filled('from')) {
            $query->whereDate('printed_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('printed_at', '<=', $request->to);
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('printed_by_name', 'like', "%{$search}%");
            });
        }

        // Filter on the invoice's PRESENT payment state (same source as the
        // Final Payment Status column). Cancelled invoices show under All only.
        $paymentStatus = $request->input('payment_status');

        if (in_array($paymentStatus, ['paid', 'due'], true)) {
            $query->whereExists(function ($q) use ($paymentStatus) {
                $q->selectRaw('1')
                    ->from('invoices')
                    ->whereColumn('invoices.id', 'report_print_due_logs.invoice_id')
                    ->where(fn ($c) => $c->whereNull('invoices.cancelled')->orWhere('invoices.cancelled', '!=', 'Y'))
                    ->where('invoices.due_amount', $paymentStatus === 'paid' ? '<=' : '>', 0);
            });
        }

        return $query->orderByDesc('printed_at')->orderByDesc('id');
    }

    private function attachFinalPayment($logs): void
    {
        // The log row is a snapshot taken at print time; the invoice's
        // present payment state is read live so Admin can see whether the
        // due was cleared afterwards, and when.
        $invoices = Invoice::whereIn('id', $logs->pluck('invoice_id')->filter()->unique())
            ->get([
                'id', 'status', 'cancelled', 'paid_amount', 'due_amount', 'payment_mode',
                'paid_amount_1', 'paid_date_1', 'paid_amount_2', 'paid_date_2',
            ])
            ->keyBy('id');

        foreach ($logs as $log) {
            $log->final_payment = $this->finalPaymentStatus($invoices->get($log->invoice_id));
        }
    }

    /**
     * @return array{label: string, badge: string, lines: string[]}
     */
    private function finalPaymentStatus(?Invoice $invoice): array
    {
        if (!$invoice) {
            return ['label' => 'Invoice not found', 'badge' => 'bg-secondary', 'lines' => []];
        }

        if ($invoice->cancelled === 'Y') {
            return ['label' => 'Invoice Cancelled', 'badge' => 'bg-dark', 'lines' => []];
        }

        // Latest due-collection payment (phase 2 is the final installment).
        $lastPayment = null;
        foreach ([2, 1] as $phase) {
            $date = $invoice->{"paid_date_{$phase}"};
            if ($date && (float) $invoice->{"paid_amount_{$phase}"} > 0) {
                $lastPayment = [
                    'amount' => (float) $invoice->{"paid_amount_{$phase}"},
                    'at' => Carbon::parse($date),
                ];
                break;
            }
        }

        $paymentLine = $lastPayment
            ? '₹' . number_format($lastPayment['amount'], 2) . ' on ' . $lastPayment['at']->format('d-m-Y h:i A')
            : null;

        if ((float) $invoice->due_amount <= 0) {
            return [
                'label' => 'Fully Paid',
                'badge' => 'bg-success',
                'lines' => array_values(array_filter([
                    $paymentLine ? 'Final payment ' . $paymentLine : null,
                    $invoice->payment_mode ? 'Mode: ' . $invoice->payment_mode : null,
                ])),
            ];
        }

        return [
            'label' => 'Due ₹' . number_format((float) $invoice->due_amount, 2),
            'badge' => $lastPayment ? 'bg-warning text-dark' : 'bg-danger',
            'lines' => array_values(array_filter([
                $paymentLine ? 'Last part payment ' . $paymentLine : 'No payment collected yet',
            ])),
        ];
    }
}
