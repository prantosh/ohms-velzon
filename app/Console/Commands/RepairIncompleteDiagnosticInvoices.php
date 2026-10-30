<?php

namespace App\Console\Commands;

use App\Models\DoctorPayable;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairIncompleteDiagnosticInvoices extends Command
{
    protected $signature = 'invoices:repair-incomplete
                            {--from= : Only invoices dated on/after this date (default: 30 days ago)}
                            {--commit : Apply the repairs (default is a dry run)}';

    protected $description = 'Add missing package component lines and doctor payables to diagnostic invoices left half-saved, and list invoices whose lines do not add up to the total';

    public function handle(): int
    {
        $from = $this->option('from') ?: now()->subDays(30)->toDateString();
        $commit = (bool) $this->option('commit');

        $this->line($commit ? 'COMMIT mode' : 'DRY RUN (pass --commit to apply)');

        $invoices = Invoice::where('invoice_type', 'DIAGNOSTIC')
            ->whereDate('invoice_date', '>=', $from)
            ->where(fn ($q) => $q->whereNull('cancelled')->orWhere('cancelled', '!=', 'Y'))
            ->orderBy('id')
            ->get();

        $addedLines = 0;
        $addedPayables = 0;
        $manual = [];

        foreach ($invoices as $invoice) {

            DB::beginTransaction();

            try {

                $addedLines += $this->repairComponents($invoice, $commit);
                $addedPayables += $this->repairPayables($invoice, $commit);

                $commit ? DB::commit() : DB::rollBack();

            } catch (\Throwable $e) {

                DB::rollBack();
                $this->error("{$invoice->invoice_no}: {$e->getMessage()}");
                continue;
            }

            $sum = (float) DB::table('invoice_details')->where('invoice_no', $invoice->invoice_no)->sum('amount');

            if (abs($sum - (float) $invoice->total_amount) > 0.5) {
                $manual[] = [$invoice->invoice_no, $invoice->patient_name, $invoice->total_amount, $sum];
            }
        }

        $this->info("Component lines " . ($commit ? 'added' : 'to add') . ": {$addedLines}; payables " . ($commit ? 'added' : 'to add') . ": {$addedPayables}");

        if ($manual) {
            $this->warn('Lines do not add up to the invoice total -- tests are missing and cannot be rebuilt automatically (cancel/re-bill):');
            $this->table(['Invoice', 'Patient', 'Total', 'Lines sum'], $manual);
        }

        return self::SUCCESS;
    }

    private function repairComponents(Invoice $invoice, bool $commit): int
    {
        $added = 0;

        $packageLines = DB::table('invoice_details as d')
            ->join('invoice_item_details as p', function ($join) {
                $join->on('p.item_code', '=', 'd.item_code')->on('p.item_code_sub', '=', 'd.item_code_sub');
            })
            ->where('d.invoice_no', $invoice->invoice_no)
            ->whereNull('d.package_parent_item_code_sub')
            ->where('p.is_package', 1)
            ->get(['d.*', 'p.id as package_detail_id', 'p.created_dt as package_created']);

        foreach ($packageLines as $line) {

            // A package flagged after this invoice was billed was billed as a plain test.
            $updated = DB::table('invoice_item_details')->where('id', $line->package_detail_id)->value('update_dt');
            if ($updated && $line->created_at < $updated) {
                continue;
            }

            $components = DB::table('test_package_components as c')
                ->join('invoice_item_details as i', 'i.id', '=', 'c.component_invoice_item_detail_id')
                ->where('c.package_invoice_item_detail_id', $line->package_detail_id)
                ->where('c.status', 'ACTIVE')
                ->get(['i.item_code', 'i.item_code_sub', 'i.item_description_sub']);

            foreach ($components as $component) {

                $exists = DB::table('invoice_details')
                    ->where('invoice_no', $invoice->invoice_no)
                    ->where('item_code_sub', $component->item_code_sub)
                    ->where('package_parent_item_code_sub', $line->item_code_sub)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $this->line("{$invoice->invoice_no}: add component {$component->item_code_sub} under {$line->item_code_sub}");

                DB::table('invoice_details')->insert([
                    'invoice_no' => $invoice->invoice_no,
                    'line_no' => $line->line_no,
                    'item_code' => $component->item_code,
                    'item_code_sub' => $component->item_code_sub,
                    'item_description' => $component->item_description_sub,
                    'quantity' => 1,
                    'rate' => 0,
                    'standard_discount' => 0,
                    'additional_discount_percent' => 0,
                    'additional_discount_amount' => 0,
                    'amount' => 0,
                    'doctor_id' => null,
                    'payment_value' => 0,
                    'doctor_payment_waived' => 0,
                    'package_parent_item_code_sub' => $line->item_code_sub,
                    'test_date' => $line->test_date,
                    'created_by' => $line->created_by,
                    'created_at' => $line->created_at,
                    'updated_at' => now(),
                ]);

                $added++;
            }
        }

        return $added;
    }

    private function repairPayables(Invoice $invoice, bool $commit): int
    {
        $added = 0;

        $lines = DB::table('invoice_details')->where('invoice_no', $invoice->invoice_no)->get();

        foreach ($lines as $line) {

            $has = DoctorPayable::where('invoice_no', $invoice->invoice_no)
                ->where('item_code_sub', $line->item_code_sub)
                ->exists();

            if ($has) {
                continue;
            }

            $doctor = $line->doctor_id ? DB::table('doctors')->where('id', $line->doctor_id)->first() : null;
            $waived = (bool) $line->doctor_payment_waived;
            $payable = $waived ? 0 : (float) $line->payment_value;

            $this->line("{$invoice->invoice_no}: add payable for {$line->item_code_sub}");

            DoctorPayable::create([
                'payable_no' => $this->nextPayableNo(),
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'invoice_type' => 'DIAGNOSTIC',
                'transaction_type' => 'RECEIVED',
                'doctor_id' => $line->doctor_id,
                'doctor_code' => $doctor->doctor_code ?? null,
                'doctor_name' => $doctor->doctor_name ?? 'ALL DOCTORS',
                'patient_id' => $invoice->patient_id,
                'patient_name' => $invoice->patient_name,
                'item_code' => $line->item_code,
                'item_code_sub' => $line->item_code_sub,
                'item_description' => $line->item_description,
                'gross_amount' => $line->amount,
                'payment_rule' => 'TEST_PAYMENT_MASTER',
                'payment_rate' => $payable,
                'payable_amount' => $payable,
                'payment_status' => DoctorPayable::STATUS_PENDING,
                'remarks' => $waived ? trim('Doctor payment waived. ' . ($line->remarks ?? '')) : $line->remarks,
                'created_by' => $line->created_by,
            ]);

            $added++;
        }

        return $added;
    }

    private function nextPayableNo(): string
    {
        $prefix = \App\Support\OfflineMode::prefix('PAY/') . now()->format('my') . '/';

        $last = DoctorPayable::where('payable_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('payable_no');

        $serial = $last ? (int) substr($last, strrpos($last, '/') + 1) + 1 : 1;

        return $prefix . str_pad($serial, 4, '0', STR_PAD_LEFT);
    }
}
