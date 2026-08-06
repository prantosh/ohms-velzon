<?php
namespace App\Http\Controllers;


use App\Models\Doctor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\DoctorPayable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DoctorPayableController extends Controller
{
    /**
     * ----------------------------------------------------------
     * Doctor Payment History Report
     * ----------------------------------------------------------
     */
    public function index()
    {
        $doctors = Doctor::where('status', 1)
            ->orderBy('doctor_name')
            ->get();

        $users = User::orderBy('name')->get();

        return view(
            'apps-doctor-payable',
            compact(
                'doctors',
                'users'
            )
        );
    }

    /**
     * ----------------------------------------------------------
     * AJAX SEARCH
     * ----------------------------------------------------------
     */
    public function search(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|string',
           
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        try {

            $data = $this->getReportData($request);

            return response()->json([

                'success' => true,

                'groups' => $data['groups'],

                'summary' => $data['summary']

            ]);

        } catch (\Exception $e) {

            Log::error('Doctor Payment History Search', [

                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'user' => Auth::id()

            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    private function buildGroups($rows): array
    {
        return $rows
            ->groupBy('doctor_id')
            ->map(function ($items) {

                return [

                    'doctor_name' => $items->first()->doctor_name,

                    'doctor_code' => $items->first()->doctor_code,

                    'transaction_count' => $items->count(),

                    'gross_amount' => $items->sum('gross_amount'),

                    'payable_amount' => $items->sum('payable_amount'),

                    'paid_amount' => $items->sum('paid_amount'),

                    'balance_amount' =>
                        $items->sum('payable_amount')
                        - $items->sum('paid_amount'),

                    'rows' => $items->values(),

                ];

            })
            ->values()
            ->toArray();
    }
    /**
     * ----------------------------------------------------------
     * PDF
     * ----------------------------------------------------------
     */
    public function printPdf(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required',
            
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        try {

            $data = $this->getReportData($request);

            $doctor = 'All Doctors';

            if ($request->doctor_id != 'ALL') {

                $doctor = Doctor::find(
                    $request->doctor_id
                )?->doctor_name;
            }

            

            $pdf = Pdf::loadView(
                'apps-doctor-payable-pdf',
                [
                    'groups' => $data['groups'],

                    'summary' => $data['summary'],
                    

                    'doctor' => $doctor,
                    
                    'fromDate' => Carbon::parse(
                        $request->from_date
                    )->format('d-m-Y'),

                    'toDate' => Carbon::parse(
                        $request->to_date
                    )->format('d-m-Y'),
                    'invoiceType' => $request->invoice_type ?? 'ALL',
                    'transactionType' => $request->transaction_type ?? 'ALL',
                    'paymentStatus' => $request->payment_status ?? 'ALL',

                    'printedBy' => Auth::user()->name,

                    'printedOn' => now()
                        ->format('d-m-Y h:i A')

                ]
            );

            $pdf->setPaper('A4', 'landscape');

            return $pdf->stream(
                'Doctor_Payables_Register_' .
                now()->format('YmdHis') .
                '.pdf'
            );

        } catch (\Exception $e) {

            Log::error('Doctor Payment PDF', [

                'message' => $e->getMessage(),
                'line' => $e->getLine()

            ]);

            return back()->with(
                'error',
                $e->getMessage()
            );
        }
    }

    /**
     * ----------------------------------------------------------
     * EXCEL EXPORT
     * ----------------------------------------------------------
     */
    public function exportExcel(Request $request)
    {
        $data = $this->getReportData($request);

        $filename =
            'Doctor_Payables_Register_' .
            now()->format('YmdHis') .
            '.csv';

        $headers = [

            'Content-Type' =>
                'text/csv',

            'Content-Disposition' =>
                'attachment; filename="' .
                $filename .
                '"',

        ];

        $callback = function () use ($data) {

            $file = fopen(
                'php://output',
                'w'
            );

            fputcsv($file, [

                'Payable No',
                'Payable Date',
                'Invoice No',
                'Invoice Type',
                'Transaction Type',
                'Doctor',
                'Doctor Code',
                'Patient',
                'Item Description',
                'Gross Amount',
                'Payment Rule',
                'Payment Rate',
                'Payable Amount',
                'Paid Amount',
                'Balance Amount',
                'Payment Status'

            ]);

            foreach ($data['rows'] as $row) {

                fputcsv($file, [

                    $row->payable_no,

                    $row->payable_date,

                    $row->invoice_no,

                    $row->invoice_type,

                    $row->transaction_type,

                    $row->doctor_name,

                    $row->doctor_code,

                    $row->patient_name,

                    $row->item_description,

                    number_format($row->gross_amount, 2, '.', ''),

                    $row->payment_rule,

                    number_format($row->payment_rate, 2, '.', ''),

                    number_format($row->payable_amount, 2, '.', ''),

                    number_format($row->paid_amount, 2, '.', ''),

                    number_format($row->balance_amount, 2, '.', ''),

                    $row->payment_status

                ]);
            }
            fputcsv($file, []);

            fputcsv($file, [

                '',
                '',
                '',
                '',
                '',
                '',
                '',
                'GRAND TOTAL',

                '',

                number_format(
                    $data['summary']['gross_amount'],
                    2,
                    '.',
                    ''
                ),

                '',

                '',

                number_format(
                    $data['summary']['grand_payable'],
                    2,
                    '.',
                    ''
                ),

                number_format(
                    $data['summary']['paid_amount'],
                    2,
                    '.',
                    ''
                ),

                number_format(
                    $data['summary']['balance_amount'],
                    2,
                    '.',
                    ''
                ),

                ''

            ]);
            fclose($file);
        };

        return response()->stream(
            $callback,
            200,
            $headers
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Remaining methods in Part 2
    |--------------------------------------------------------------------------
    |
    | getReportData()
    | buildSummary()
    | buildDoctorSummary()
    | buildActionButtons()
    |
    */
    /**
     * ------------------------------------------------------------------
     * COMMON DATA LOADER
     * ------------------------------------------------------------------
     */
    private function getReportData(Request $request): array
    {
        $query = DoctorPayable::query();

        /*
        |--------------------------------------------------------------------------
        | Doctor
        |--------------------------------------------------------------------------
        */

        if ($request->doctor_id != 'ALL') {
            $query->where('doctor_id', $request->doctor_id);
        }

        /*
        |--------------------------------------------------------------------------
        | Invoice Type
        |--------------------------------------------------------------------------
        */

        if (
            !empty($request->invoice_type) &&
            $request->invoice_type != 'ALL'
        ) {

            $query->where(
                'invoice_type',
                $request->invoice_type
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Transaction Type
        |--------------------------------------------------------------------------
        */

        if (
            !empty($request->transaction_type) &&
            $request->transaction_type != 'ALL'
        ) {

            $query->where(
                'transaction_type',
                $request->transaction_type
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Status
        |--------------------------------------------------------------------------
        */

        if (
            !empty($request->payment_status) &&
            $request->payment_status != 'ALL'
        ) {

            $query->where(
                'payment_status',
                $request->payment_status
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Date
        |--------------------------------------------------------------------------
        */

        $query->whereBetween(
            'created_at',
            [
                $request->from_date . ' 00:00:00',
                $request->to_date . ' 23:59:59'
            ]
        );
        $query->where('payable_amount', '>', 0);
        $rows = $query
            ->orderBy('doctor_name')
            ->orderBy('created_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Collector Resolution
        |--------------------------------------------------------------------------
        | The "User" column is who actually collected the doctor-inclusive
        | amount from the patient -- not who created the invoice, since a
        | due payment collected later is frequently a different person
        | (see DoctorSettlementController for the same rule). Not shown at
        | all until the invoice is fully collected (due_amount = 0).
        */

        $invoiceIds = $rows->pluck('invoice_id')->filter()->unique()->values();

        $invoicesById = DB::table('invoices')
            ->whereIn('id', $invoiceIds)
            ->get(['id', 'invoice_no', 'due_amount', 'doctor_amount_collected_by'])
            ->keyBy('id');

        $finalCollectorByInvoiceNo = DB::table('daily_transactions')
            ->select('invoice_reference', 'operator_id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY invoice_reference ORDER BY created_at DESC) as rn')
            ->where('transaction_type', 'RECEIVED')
            ->where('status', '!=', 'CANCELLED')
            ->whereIn('invoice_reference', $invoicesById->pluck('invoice_no'))
            ->get()
            ->where('rn', 1)
            ->pluck('operator_id', 'invoice_reference');

        $userNames = User::pluck('name', 'id');

        /*
        |--------------------------------------------------------------------------
        | Formatting
        |--------------------------------------------------------------------------
        */

        foreach ($rows as $row) {

            $row->payable_date =
                optional($row->created_at)->format('d-m-Y');

            $row->balance_amount =
                $row->payable_amount - $row->paid_amount;

            $invoice = $invoicesById->get($row->invoice_id);

            if ($invoice && (float) $invoice->due_amount == 0.0) {

                $collectorId = $invoice->doctor_amount_collected_by
                    ?? $finalCollectorByInvoiceNo->get($invoice->invoice_no);

                $row->collection_due = false;
                $row->user_name = $userNames->get($collectorId) ?? '-';

            } else {

                $row->collection_due = true;
                $row->user_name = 'COLLECTION DUE';
            }

            $row->actions =
                $this->buildActionButtons(
                    $row->invoice_id,
                    $row->invoice_type
                );
        }

        return [

            'rows' => $rows,

            'groups' => $this->buildGroups($rows),

            'summary' => $this->buildSummary($rows)

        ];
    }
    /**
     * ------------------------------------------------------------------
     * SUMMARY CARDS
     * ------------------------------------------------------------------
     */
    private function buildSummary($rows): array
    {
        return [

            'total_doctors' =>

                $rows->pluck('doctor_id')
                    ->unique()
                    ->count(),

            'pending_amount' =>

                $rows
                    ->where('payment_status', 'PENDING')
                    ->sum('payable_amount'),

            'approved_amount' =>

                $rows
                    ->where('payment_status', 'APPROVED')
                    ->sum('payable_amount'),

            'paid_amount' =>

                $rows->sum('paid_amount'),
            'gross_amount' => $rows->sum('gross_amount'),

            'balance_amount' =>
                $rows->sum('payable_amount')
                - $rows->sum('paid_amount'),

            'grand_payable' =>

                $rows->sum('payable_amount')

        ];
    }

    


   

   
    /**
     * ------------------------------------------------------------------
     * ACTION BUTTONS
     * ------------------------------------------------------------------
     */
    private function buildActionButtons(
        $invoiceId,
        $invoiceType,
        $cancelled = 'N'
    ) {
        if (empty($invoiceId)) {
            return '';
        }

        if ($invoiceType === 'DIAGNOSTIC') {

            $printRoute = route(
                'diagnostic-invoice.print',
                $invoiceId
            );

            $whatsappRoute = route(
                'diagnostic-invoice.send-whatsapp',
                $invoiceId
            );

            // Diagnostic invoice print streams the PDF directly -- a plain link works.
            $printButton = '
            <a href="' . $printRoute . '"
               target="_blank"
               class="btn btn-sm btn-primary me-1"
               title="Print Invoice">
                <i class="ri-printer-line"></i>
            </a>';

        } else {

            $printRoute = route(
                'doctor-visit-invoice.print',
                $invoiceId
            );

            $whatsappRoute = route(
                'doctor-visit-invoice.send-whatsapp',
                $invoiceId
            );

            // Doctor visit invoice print saves the PDF server-side and returns
            // JSON {status, pdf_url} -- it must be called via AJAX, not a plain link,
            // otherwise the browser tab just shows the raw JSON.
            $printButton = '
            <button type="button"
               class="btn btn-sm btn-primary me-1 printDoctorVisitBtn"
               data-id="' . $invoiceId . '"
               title="Print Invoice">
                <i class="ri-printer-line"></i>
            </button>';
        }

        return $printButton . '

        <a href="' . $whatsappRoute . '"
           class="btn btn-sm btn-success sendWhatsapp"
           data-id="' . $invoiceId . '"
           title="Send WhatsApp">
            <i class="ri-whatsapp-line"></i>
        </a>';
    }
    /**
     * ------------------------------------------------------------------
     * PRINT VIEW
     * ------------------------------------------------------------------
     */
    public function printReport(Request $request)
    {
        $data = $this->getReportData($request);

        $doctor = 'All Doctors';

        if ($request->doctor_id != 'ALL') {

            $doctor = Doctor::find(
                $request->doctor_id
            )?->doctor_name;
        }

        

        return view(
            'apps-doctor-payable-print',
            [

                'groups' => $data['groups'],

                'summary' => $data['summary'],


                'doctor' => $doctor,

               

                'fromDate' => Carbon::parse(
                    $request->from_date
                )->format('d-m-Y'),

                'toDate' => Carbon::parse(
                    $request->to_date
                )->format('d-m-Y'),

                'printedBy' => Auth::user()->name,
                'invoiceType' => $request->invoice_type ?? 'ALL',

                'transactionType' => $request->transaction_type ?? 'ALL',

                'paymentStatus' => $request->payment_status ?? 'ALL',

                'printedOn' => now()->format('d-m-Y h:i A')

            ]
        );
    }
}
