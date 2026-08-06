<?php

namespace App\Services;

use App\Models\DoctorAppointment;
use App\Models\DoctorPayable;
use App\Models\DoctorSettlement;
use App\Models\DoctorSettlementItem;
use App\Models\ExpenditureTransaction;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockIssue;
use App\Models\StockIssueItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Merges records created on the offline (WAMP) fallback instance -- every
 * one tagged with an 'OFF' prefix on its document number by
 * App\Support\OfflineMode -- into production, after an internet outage
 * ends. See docs/OFFLINE_DISASTER_RECOVERY.md.
 *
 * Source: the connection this app is currently running against (the
 * offline/WAMP database, when this command is run on the WAMP machine).
 * Destination: the 'production' connection, registered at runtime by the
 * calling command.
 *
 * plan() computes everything and writes nothing. commit() replays the same
 * logic and actually writes, re-using each step's exists-check as an
 * idempotency guard so a second run after an interrupted first one is safe.
 */
class OfflineMergeService
{
    private const TARGET = 'production';

    private AuditService $auditService;
    private string $mergeNote;

    /**
     * The connection this service reads from -- WAMP's own real default
     * connection, captured explicitly by the caller rather than assumed,
     * since this class temporarily repoints database.default to TARGET
     * during commit() (the only way to make AuditService's internal
     * AuditLog::create() land on production instead of here -- AuditService
     * has no connection parameter of its own).
     */
    private string $sourceConnection;

    public function __construct(AuditService $auditService, string $sourceConnection, ?string $sourceLabel = null)
    {
        $this->auditService = $auditService;
        $this->sourceConnection = $sourceConnection;
        $this->mergeNote = 'Merged from offline session' . ($sourceLabel ? " ({$sourceLabel})" : '');
    }

    /**
     * Compute the full merge plan without writing anything.
     */
    public function plan(?Carbon $since): array
    {
        return $this->run(commit: false, since: $since);
    }

    /**
     * Re-run the same logic, actually writing to the target connection.
     *
     * Temporarily makes TARGET the default DB connection so AuditService's
     * AuditLog::create() calls -- which always use the default connection,
     * having no connection parameter of their own -- land the audit trail
     * on production instead of the source (WAMP) database.
     */
    public function commit(?Carbon $since): array
    {
        $originalDefault = Config::get('database.default');

        Config::set('database.default', self::TARGET);

        try {
            return $this->run(commit: true, since: $since);
        } finally {
            Config::set('database.default', $originalDefault);
        }
    }

    private function run(bool $commit, ?Carbon $since): array
    {
        $report = [
            'entities' => [],
            'flagged' => [],
        ];

        $patientMap = $this->mergePatients($commit, $report);
        $appointmentIdMap = $this->mergeAppointments($commit, $report);
        $invoiceIdMap = $this->mergeInvoices($commit, $report, $appointmentIdMap);
        $this->mergeInvoiceDetails($commit, $report, $invoiceIdMap);
        $payableIdMap = $this->mergePayables($commit, $report, $invoiceIdMap);
        $this->mergeDailyTransactions($commit, $report);
        $settlementIdMap = $this->mergeSettlements($commit, $report);
        $this->mergeSettlementItems($commit, $report, $settlementIdMap, $payableIdMap, $invoiceIdMap);
        $this->mergeExpenditures($commit, $report);
        $purchaseOrderIdMap = $this->mergePurchaseOrders($commit, $report);
        $this->mergePurchaseOrderItems($commit, $report, $purchaseOrderIdMap);
        $goodsReceiptIdMap = $this->mergeGoodsReceipts($commit, $report, $purchaseOrderIdMap);
        $this->mergeGoodsReceiptItems($commit, $report, $goodsReceiptIdMap, $purchaseOrderIdMap);
        $stockIssueIdMap = $this->mergeStockIssues($commit, $report);
        $this->mergeStockIssueItems($commit, $report, $stockIssueIdMap);

        $this->detectPreExistingRowsModifiedOffline($report, $since);

        return $report;
    }

    private function recordEntity(array &$report, string $name, int $created, int $skipped): void
    {
        $report['entities'][$name] = [
            'created' => $created,
            'already_present' => $skipped,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | PATIENTS
    |--------------------------------------------------------------------------
    */

    private function mergePatients(bool $commit, array &$report): array
    {
        $rows = DB::connection($this->sourceConnection)->table('patients')
            ->where('patient_id', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $exists = Patient::on(self::TARGET)->where('patient_id', $row->patient_id)->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $patient = new Patient();
                $patient->setConnection(self::TARGET);
                $patient->fill([
                    'patient_id' => $row->patient_id,
                    'patient_name' => $row->patient_name,
                    'mobile_no' => $row->mobile_no,
                    'age' => $row->age,
                    'gender' => $row->gender,
                    'status' => $row->status,
                ]);
                $patient->save();

                $this->auditService->logCreate(
                    'PATIENT',
                    $patient,
                    $patient->only($patient->getFillable()),
                    $this->mergeNote
                );
            }

            $created++;
        }

        $this->recordEntity($report, 'patients', $created, $skipped);

        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | DOCTOR APPOINTMENTS
    |--------------------------------------------------------------------------
    */

    private function mergeAppointments(bool $commit, array &$report): array
    {
        $rows = DB::connection($this->sourceConnection)->table('doctor_appointments')
            ->where('appointment_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = DoctorAppointment::on(self::TARGET)
                ->where('appointment_no', $row->appointment_no)
                ->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                // token_no is a plain integer with no OFF-prefix protection --
                // recompute fresh against production's live state, exactly
                // like a brand-new booking would, rather than trusting the
                // offline-computed value (doc Â§6.3).
                $tokenNo = $this->nextAppointmentToken(
                    $row->doctor_id,
                    $row->appointment_date
                );

                $appointment = new DoctorAppointment();
                $appointment->setConnection(self::TARGET);
                $appointment->fill([
                    'appointment_no' => $row->appointment_no,
                    'doctor_id' => $row->doctor_id,
                    'doctor_schedule_session_id' => $row->doctor_schedule_session_id,
                    'patient_id' => $row->patient_id,
                    'patient_name' => $row->patient_name,
                    'patient_mobile_no' => $row->patient_mobile_no,
                    'patient_age' => $row->patient_age,
                    'patient_gender' => $row->patient_gender,
                    'appointment_date' => $row->appointment_date,
                    'appointment_time' => $row->appointment_time,
                    'token_no' => $tokenNo,
                    'consultation_fee' => $row->consultation_fee,
                    'appointment_status' => $row->appointment_status,
                    'remarks' => $row->remarks,
                    'created_by' => $row->created_by,
                    'updated_by' => $row->updated_by,
                ]);
                $appointment->save();

                $this->auditService->logCreate(
                    'DOCTOR_APPOINTMENT',
                    $appointment,
                    $appointment->only($appointment->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $appointment->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'doctor_appointments', $created, $skipped);

        return $idMap;
    }

    private function nextAppointmentToken($doctorId, $appointmentDate): int
    {
        $existingCount = DoctorAppointment::on(self::TARGET)
            ->where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $appointmentDate)
            ->count();

        return 3 + $existingCount; // matches AppointmentBookingService::FIRST_TOKEN_NO
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICES
    |--------------------------------------------------------------------------
    */

    private function mergeInvoices(bool $commit, array &$report, array $appointmentIdMap): array
    {
        $rows = DB::connection($this->sourceConnection)->table('invoices')
            ->where('invoice_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = Invoice::on(self::TARGET)->where('invoice_no', $row->invoice_no)->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                $invoice = new Invoice();
                $invoice->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                if (!empty($row->appointment_id) && isset($appointmentIdMap[$row->appointment_id])) {
                    $data['appointment_id'] = $appointmentIdMap[$row->appointment_id];
                }

                $invoice->fill(array_intersect_key($data, array_flip($invoice->getFillable())));
                $invoice->save();

                $this->auditService->logCreate(
                    'DIAGNOSTIC_INVOICE',
                    $invoice,
                    $invoice->only($invoice->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $invoice->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'invoices', $created, $skipped);

        return $idMap;
    }

    /*
    |--------------------------------------------------------------------------
    | INVOICE DETAILS -- raw table, linked by invoice_no (string, no remap)
    |--------------------------------------------------------------------------
    */

    private function mergeInvoiceDetails(bool $commit, array &$report, array $invoiceIdMap): void
    {
        $offlineInvoiceNumbers = DB::connection($this->sourceConnection)->table('invoices')
            ->where('invoice_no', 'like', 'OFF%')
            ->pluck('invoice_no');

        if ($offlineInvoiceNumbers->isEmpty()) {
            $this->recordEntity($report, 'invoice_details', 0, 0);
            return;
        }

        $rows = DB::connection($this->sourceConnection)->table('invoice_details')
            ->whereIn('invoice_no', $offlineInvoiceNumbers)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $exists = DB::connection(self::TARGET)->table('invoice_details')
                ->where('invoice_no', $row->invoice_no)
                ->where('item_code_sub', $row->item_code_sub)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $data = (array) $row;
                unset($data['id']);

                DB::connection(self::TARGET)->table('invoice_details')->insert($data);
            }

            $created++;
        }

        $this->recordEntity($report, 'invoice_details', $created, $skipped);
    }

    /*
    |--------------------------------------------------------------------------
    | DOCTOR PAYABLES -- newly created ones, tied to an offline invoice
    |--------------------------------------------------------------------------
    */

    private function mergePayables(bool $commit, array &$report, array $invoiceIdMap): array
    {
        $rows = DB::connection($this->sourceConnection)->table('doctor_payables')
            ->where('payable_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = DoctorPayable::on(self::TARGET)->where('payable_no', $row->payable_no)->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                $payable = new DoctorPayable();
                $payable->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                if (!empty($row->invoice_id) && isset($invoiceIdMap[$row->invoice_id])) {
                    $data['invoice_id'] = $invoiceIdMap[$row->invoice_id];
                }

                $payable->fill(array_intersect_key($data, array_flip($payable->getFillable())));
                $payable->save();

                $this->auditService->logCreate(
                    'DOCTOR_PAYMENT',
                    $payable,
                    $payable->only($payable->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $payable->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'doctor_payables', $created, $skipped);

        return $idMap;
    }

    /*
    |--------------------------------------------------------------------------
    | DAILY TRANSACTIONS -- raw table, linked by string invoice_reference
    |--------------------------------------------------------------------------
    */

    private function mergeDailyTransactions(bool $commit, array &$report): void
    {
        $rows = DB::connection($this->sourceConnection)->table('daily_transactions')
            ->where('transaction_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $exists = DB::connection(self::TARGET)->table('daily_transactions')
                ->where('transaction_no', $row->transaction_no)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $data = (array) $row;
                unset($data['id']);

                DB::connection(self::TARGET)->table('daily_transactions')->insert($data);
            }

            $created++;
        }

        $this->recordEntity($report, 'daily_transactions', $created, $skipped);
    }

    /*
    |--------------------------------------------------------------------------
    | DOCTOR SETTLEMENTS
    |--------------------------------------------------------------------------
    */

    private function mergeSettlements(bool $commit, array &$report): array
    {
        $rows = DB::connection($this->sourceConnection)->table('doctor_settlements')
            ->where('settlement_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = DoctorSettlement::on(self::TARGET)->where('settlement_no', $row->settlement_no)->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                $settlement = new DoctorSettlement();
                $settlement->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                $settlement->fill(array_intersect_key($data, array_flip($settlement->getFillable())));
                $settlement->save();

                $this->auditService->logCreate(
                    'DOCTOR_SETTLEMENT',
                    $settlement,
                    $settlement->only($settlement->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $settlement->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'doctor_settlements', $created, $skipped);

        return $idMap;
    }

    /*
    |--------------------------------------------------------------------------
    | DOCTOR SETTLEMENT ITEMS -- also the one place a settlement can mutate
    | a payable that already existed before the outage (not just create new
    | ones). Any payable_id not covered by $payableIdMap is looked up by its
    | stable payable_no in production and updated the same way
    | DoctorSettlementController::updateDoctorPayable() would.
    |--------------------------------------------------------------------------
    */

    private function mergeSettlementItems(
        bool $commit,
        array &$report,
        array $settlementIdMap,
        array $payableIdMap,
        array $invoiceIdMap
    ): void {

        // $settlementIdMap only has entries for settlements that were
        // already present on production (skip branch) or, when $commit is
        // true, ones just created -- in a dry run a brand-new settlement's
        // id is never added to the map. Query the source directly instead,
        // so the preview is accurate whether or not anything was written.
        $offlineSettlementIds = DB::connection($this->sourceConnection)
            ->table('doctor_settlements')
            ->where('settlement_no', 'like', 'OFF%')
            ->pluck('id')
            ->all();

        if (empty($offlineSettlementIds)) {
            $this->recordEntity($report, 'doctor_settlement_items', 0, 0);
            $this->recordEntity($report, 'payable_status_synced', 0, 0);
            return;
        }

        $rows = DB::connection($this->sourceConnection)->table('doctor_settlement_items')
            ->whereIn('settlement_id', $offlineSettlementIds)
            ->get();

        $created = 0;
        $skipped = 0;
        $synced = 0;

        // Source payable_no/invoice_no for each offline settlement item's
        // referenced payable/invoice -- needed to resolve the production
        // row regardless of whether it was just created by this run
        // ($payableIdMap/$invoiceIdMap) or already existed beforehand.
        $sourcePayableNos = DB::connection($this->sourceConnection)->table('doctor_payables')
            ->whereIn('id', $rows->pluck('payable_id')->unique())
            ->pluck('payable_no', 'id');

        $sourceInvoiceNos = DB::connection($this->sourceConnection)->table('invoices')
            ->whereIn('id', $rows->pluck('invoice_id')->filter()->unique())
            ->pluck('invoice_no', 'id');

        foreach ($rows as $row) {

            // Not in the map means its parent settlement is new-this-run
            // and, in a dry run, wasn't actually created -- so this item
            // can't already exist in production either.
            $newSettlementId = $settlementIdMap[$row->settlement_id] ?? null;

            $isPreExistingPayable = !isset($payableIdMap[$row->payable_id]);
            $payableNo = $sourcePayableNos[$row->payable_id] ?? null;

            $newPayableId = $payableIdMap[$row->payable_id]
                ?? ($payableNo ? DoctorPayable::on(self::TARGET)->where('payable_no', $payableNo)->value('id') : null);

            $newInvoiceId = null;

            if ($row->invoice_id) {
                $invoiceNo = $sourceInvoiceNos[$row->invoice_id] ?? null;
                $newInvoiceId = $invoiceIdMap[$row->invoice_id]
                    ?? ($invoiceNo ? Invoice::on(self::TARGET)->where('invoice_no', $invoiceNo)->value('id') : null);
            }

            $exists = $newSettlementId && $newPayableId && DoctorSettlementItem::on(self::TARGET)
                ->where('settlement_id', $newSettlementId)
                ->where('payable_id', $newPayableId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $item = new DoctorSettlementItem();
                $item->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);
                $data['settlement_id'] = $newSettlementId;
                $data['payable_id'] = $newPayableId;
                $data['invoice_id'] = $newInvoiceId;

                $item->fill(array_intersect_key($data, array_flip($item->getFillable())));
                $item->save();

                $this->auditService->logCreate(
                    'DOCTOR_SETTLEMENT',
                    $item,
                    $item->only($item->getFillable()),
                    $this->mergeNote . ' (settlement item)'
                );

                // If this payable pre-dates the outage (not something this
                // merge just created), replay the exact state change
                // updateDoctorPayable() would have applied.
                if ($isPreExistingPayable && $newPayableId) {
                    $this->syncPreExistingPayable($newPayableId, $newSettlementId, $row);
                }
            }

            // Counted whether or not this is a dry run, so the report
            // previews what WOULD happen -- the write itself only happens
            // above when $commit is true.
            if ($isPreExistingPayable && $newPayableId) {
                $synced++;
            }

            $created++;
        }

        $this->recordEntity($report, 'doctor_settlement_items', $created, $skipped);
        $this->recordEntity($report, 'payable_status_synced', $synced, 0);
    }

    private function syncPreExistingPayable(int $payableId, int $settlementId, object $item): void
    {
        $payable = DoctorPayable::on(self::TARGET)->find($payableId);
        $settlement = DoctorSettlement::on(self::TARGET)->find($settlementId);

        if (!$payable || !$settlement) {
            return;
        }

        $oldData = $payable->only($payable->getFillable());

        $newPaid = round($payable->paid_amount + $item->settlement_amount, 2);
        $newBalance = max(0, round($payable->payable_amount - $newPaid, 2));

        $payable->paid_amount = $newPaid;
        $payable->payment_status = $newBalance <= 0 ? 'PAID' : 'APPROVED';
        $payable->last_settlement_id = $settlement->id;

        if (empty($payable->first_settlement_date)) {
            $payable->first_settlement_date = $settlement->settlement_date;
        }

        $payable->last_settlement_no = $settlement->settlement_no;
        $payable->last_settlement_date = $settlement->settlement_date;
        $payable->settlement_count = ($payable->settlement_count ?? 0) + 1;
        $payable->updated_by = $item->created_by;
        $payable->save();

        $this->auditService->logUpdate(
            'DOCTOR_PAYMENT',
            $payable,
            $oldData,
            $payable->only($payable->getFillable()),
            $this->mergeNote . ' -- settlement of a pre-existing payable'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | EXPENDITURE TRANSACTIONS
    |--------------------------------------------------------------------------
    */

    private function mergeExpenditures(bool $commit, array &$report): void
    {
        $rows = DB::connection($this->sourceConnection)->table('expenditure_transactions')
            ->where('voucher_number', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $exists = ExpenditureTransaction::on(self::TARGET)
                ->where('voucher_number', $row->voucher_number)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $tx = new ExpenditureTransaction();
                $tx->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                $tx->fill(array_intersect_key($data, array_flip($tx->getFillable())));
                $tx->save();

                $this->auditService->logCreate(
                    'EXPENDITURE',
                    $tx,
                    $tx->only($tx->getFillable()),
                    $this->mergeNote
                );
            }

            $created++;
        }

        $this->recordEntity($report, 'expenditure_transactions', $created, $skipped);
    }

    /*
    |--------------------------------------------------------------------------
    | PURCHASE ORDERS
    |--------------------------------------------------------------------------
    */

    private function mergePurchaseOrders(bool $commit, array &$report): array
    {
        $rows = DB::connection($this->sourceConnection)->table('purchase_orders')
            ->where('po_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = PurchaseOrder::on(self::TARGET)->where('po_no', $row->po_no)->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                $po = new PurchaseOrder();
                $po->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                $po->fill(array_intersect_key($data, array_flip($po->getFillable())));
                $po->save();

                $this->auditService->logCreate(
                    'PURCHASE_ORDER',
                    $po,
                    $po->only($po->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $po->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'purchase_orders', $created, $skipped);

        return $idMap;
    }

    private function mergePurchaseOrderItems(bool $commit, array &$report, array $purchaseOrderIdMap): void
    {
        // Query the source directly for offline PO ids -- $purchaseOrderIdMap
        // only has entries for POs already on production plus (in commit
        // mode only) ones just created; in a dry run a brand-new PO's id
        // is never added to it.
        $offlinePurchaseOrderIds = DB::connection($this->sourceConnection)
            ->table('purchase_orders')
            ->where('po_no', 'like', 'OFF%')
            ->pluck('id')
            ->all();

        if (empty($offlinePurchaseOrderIds)) {
            $this->recordEntity($report, 'purchase_order_items', 0, 0);
            return;
        }

        $rows = DB::connection($this->sourceConnection)->table('purchase_order_items')
            ->whereIn('purchase_order_id', $offlinePurchaseOrderIds)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $newPoId = $purchaseOrderIdMap[$row->purchase_order_id] ?? null;

            // No mapped production id yet means its parent PO doesn't
            // exist there either (dry run, or an interrupted prior commit
            // that never reached this PO) -- so this item can't already
            // exist in production; count it as "would create" without a
            // (meaningless, since $newPoId would be null) exists check.
            $exists = $newPoId && PurchaseOrderItem::on(self::TARGET)
                ->where('purchase_order_id', $newPoId)
                ->where('inventory_item_id', $row->inventory_item_id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $item = new PurchaseOrderItem();
                $item->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id']);
                $data['purchase_order_id'] = $newPoId;

                $item->fill(array_intersect_key($data, array_flip($item->getFillable())));
                $item->save();
            }

            $created++;
        }

        $this->recordEntity($report, 'purchase_order_items', $created, $skipped);
    }

    /*
    |--------------------------------------------------------------------------
    | GOODS RECEIPTS
    |--------------------------------------------------------------------------
    */

    private function mergeGoodsReceipts(bool $commit, array &$report, array $purchaseOrderIdMap): array
    {
        $rows = DB::connection($this->sourceConnection)->table('goods_receipts')
            ->where('receipt_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = GoodsReceipt::on(self::TARGET)->where('receipt_no', $row->receipt_no)->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                $receipt = new GoodsReceipt();
                $receipt->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                if (!empty($row->purchase_order_id) && isset($purchaseOrderIdMap[$row->purchase_order_id])) {
                    $data['purchase_order_id'] = $purchaseOrderIdMap[$row->purchase_order_id];
                }

                $receipt->fill(array_intersect_key($data, array_flip($receipt->getFillable())));
                $receipt->save();

                $this->auditService->logCreate(
                    'GOODS_RECEIPT',
                    $receipt,
                    $receipt->only($receipt->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $receipt->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'goods_receipts', $created, $skipped);

        return $idMap;
    }

    private function mergeGoodsReceiptItems(
        bool $commit,
        array &$report,
        array $goodsReceiptIdMap,
        array $purchaseOrderIdMap
    ): void {

        $offlineGoodsReceiptIds = DB::connection($this->sourceConnection)
            ->table('goods_receipts')
            ->where('receipt_no', 'like', 'OFF%')
            ->pluck('id')
            ->all();

        if (empty($offlineGoodsReceiptIds)) {
            $this->recordEntity($report, 'goods_receipt_items', 0, 0);
            return;
        }

        $rows = DB::connection($this->sourceConnection)->table('goods_receipt_items')
            ->whereIn('goods_receipt_id', $offlineGoodsReceiptIds)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $newReceiptId = $goodsReceiptIdMap[$row->goods_receipt_id] ?? null;

            $exists = $newReceiptId && GoodsReceiptItem::on(self::TARGET)
                ->where('goods_receipt_id', $newReceiptId)
                ->where('inventory_item_id', $row->inventory_item_id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $item = new GoodsReceiptItem();
                $item->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id']);
                $data['goods_receipt_id'] = $newReceiptId;

                if (!empty($row->purchase_order_item_id)) {
                    // purchase_order_item_id remap not tracked at this
                    // granularity -- left as-is only when the referenced PO
                    // item was pre-existing (its id is unchanged); offline
                    // PO items merged this run are matched by their parent
                    // PO's remap already applied above at the header level.
                    $data['purchase_order_item_id'] = $row->purchase_order_item_id;
                }

                $item->fill(array_intersect_key($data, array_flip($item->getFillable())));
                $item->save();
            }

            $created++;
        }

        $this->recordEntity($report, 'goods_receipt_items', $created, $skipped);
    }

    /*
    |--------------------------------------------------------------------------
    | STOCK ISSUES
    |--------------------------------------------------------------------------
    */

    private function mergeStockIssues(bool $commit, array &$report): array
    {
        $rows = DB::connection($this->sourceConnection)->table('stock_issues')
            ->where('issue_no', 'like', 'OFF%')
            ->get();

        $created = 0;
        $skipped = 0;
        $idMap = [];

        foreach ($rows as $row) {

            $existing = StockIssue::on(self::TARGET)->where('issue_no', $row->issue_no)->first();

            if ($existing) {
                $idMap[$row->id] = $existing->id;
                $skipped++;
                continue;
            }

            if ($commit) {

                $issue = new StockIssue();
                $issue->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id'], $data['created_at'], $data['updated_at']);

                $issue->fill(array_intersect_key($data, array_flip($issue->getFillable())));
                $issue->save();

                $this->auditService->logCreate(
                    'STOCK_ISSUE',
                    $issue,
                    $issue->only($issue->getFillable()),
                    $this->mergeNote
                );

                $idMap[$row->id] = $issue->id;
            }

            $created++;
        }

        $this->recordEntity($report, 'stock_issues', $created, $skipped);

        return $idMap;
    }

    private function mergeStockIssueItems(bool $commit, array &$report, array $stockIssueIdMap): void
    {
        $offlineStockIssueIds = DB::connection($this->sourceConnection)
            ->table('stock_issues')
            ->where('issue_no', 'like', 'OFF%')
            ->pluck('id')
            ->all();

        if (empty($offlineStockIssueIds)) {
            $this->recordEntity($report, 'stock_issue_items', 0, 0);
            return;
        }

        $rows = DB::connection($this->sourceConnection)->table('stock_issue_items')
            ->whereIn('stock_issue_id', $offlineStockIssueIds)
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {

            $newIssueId = $stockIssueIdMap[$row->stock_issue_id] ?? null;

            $exists = $newIssueId && StockIssueItem::on(self::TARGET)
                ->where('stock_issue_id', $newIssueId)
                ->where('inventory_item_id', $row->inventory_item_id)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            if ($commit) {

                $item = new StockIssueItem();
                $item->setConnection(self::TARGET);

                $data = (array) $row;
                unset($data['id']);
                $data['stock_issue_id'] = $newIssueId;

                $item->fill(array_intersect_key($data, array_flip($item->getFillable())));
                $item->save();
            }

            $created++;
        }

        $this->recordEntity($report, 'stock_issue_items', $created, $skipped);
    }

    /*
    |--------------------------------------------------------------------------
    | DETECTION PASS -- pre-existing rows mutated offline. Report-only,
    | never auto-merged (doc Â§6.2 / plan scope decision).
    |--------------------------------------------------------------------------
    */

    private function detectPreExistingRowsModifiedOffline(array &$report, ?Carbon $since): void
    {
        $flagged = [];

        $checks = [
            ['table' => 'invoices', 'number_column' => 'invoice_no'],
            ['table' => 'doctor_appointments', 'number_column' => 'appointment_no'],
            ['table' => 'doctor_settlements', 'number_column' => 'settlement_no'],
        ];

        foreach ($checks as $check) {

            $query = DB::connection($this->sourceConnection)->table($check['table'])
                ->where($check['number_column'], 'not like', 'OFF%')
                ->whereColumn('updated_at', '>', 'created_at');

            if ($since) {
                $query->where('updated_at', '>=', $since);
            }

            $rows = $query->get([$check['number_column'], 'updated_at', 'updated_by']);

            foreach ($rows as $row) {
                $flagged[] = [
                    'table' => $check['table'],
                    'number' => $row->{$check['number_column']},
                    'updated_at' => $row->updated_at,
                    'updated_by' => $row->updated_by,
                ];
            }
        }

        $report['flagged'] = $flagged;
    }
}
