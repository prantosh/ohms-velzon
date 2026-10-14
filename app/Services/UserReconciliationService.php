<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-user reconciliation figures, month by month and cumulative since the
 * system began.
 *
 * Attribution (same as the Reporting Group Summary / Daily Cash Submission):
 *   - collection / refund ..... the user who recorded the transaction
 *                               (daily_transactions.created_by)
 *   - doctor paid ............. the user who processed the settlement
 *                               (doctor_settlements.created_by)
 *   - doctor payable .......... the user whose invoice created the payable
 *                               (doctor_payables.created_by); payables of cancelled
 *                               invoices that were never paid out are left out
 *   - doctor pending (at T) ... payables created up to T on the user's invoices,
 *                               less everything settled against them up to T
 *
 * Completed months are saved once (user_monthly_reconciliations); the current
 * month is always worked out live as month-to-date.
 */
class UserReconciliationService
{
    private const FAR_PAST = '1900-01-01';

    /*
    |--------------------------------------------------------------------------
    | INCEPTION
    |--------------------------------------------------------------------------
    */

    public function inceptionDate(): ?string
    {
        $dates = array_filter([
            DB::table('daily_transactions')->min('transaction_date'),
            DB::table('doctor_payables')->selectRaw('MIN(DATE(created_at)) as d')->value('d'),
            DB::table('doctor_settlements')->selectRaw('MIN(DATE(settlement_date)) as d')->value('d'),
        ]);

        return empty($dates) ? null : Carbon::parse(min($dates))->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | SNAPSHOTS
    |--------------------------------------------------------------------------
    */

    /** Save every completed month that has no saved rows yet (also what the 1st-of-month job calls). */
    public function ensureSnapshots(): int
    {
        $inception = $this->inceptionDate();

        if (!$inception) {
            return 0;
        }

        $month = Carbon::parse($inception)->startOfMonth();
        $lastCompleted = now()->startOfMonth()->subMonth();
        $generated = 0;

        while ($month->lte($lastCompleted)) {

            $exists = DB::table('user_monthly_reconciliations')
                ->where('month_start', $month->toDateString())
                ->exists();

            if (!$exists) {
                $this->generateMonth($month->copy());
                $generated++;
            }

            $month->addMonth();
        }

        return $generated;
    }

    /** (Re)build and save one completed month for every user with any activity up to its end. */
    public function generateMonth(Carbon $monthStart): int
    {
        $from = $monthStart->copy()->startOfMonth();
        $to = $monthStart->copy()->endOfMonth();

        $rows = $this->summarise($from->toDateString(), $to->toDateString());

        foreach ($rows as $userId => $row) {

            DB::table('user_monthly_reconciliations')->updateOrInsert(
                ['user_id' => $userId, 'month_start' => $from->toDateString()],
                array_merge($row['columns'], [
                    'month_end' => $to->toDateString(),
                    'details' => json_encode($row['details']),
                    'generated_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }

        return count($rows);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY (any period)
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<int,array{columns:array,details:array}> keyed by user id
     */
    public function summarise(string $from, string $to): array
    {
        $period = $this->figures($from, $to);
        $cum = $this->figures(self::FAR_PAST, $to);

        $periodDoctors = $this->doctorMovements($from, $to);
        $pending = $this->pendingAsOf($to);
        $flows = $this->settlementFlowsAsOf($to);

        $userIds = array_unique(array_merge(array_keys($cum), array_keys($pending)));

        $rows = [];

        foreach ($userIds as $userId) {

            $p = $period[$userId] ?? $this->emptyFigures();
            $c = $cum[$userId] ?? $this->emptyFigures();

            $pCollCash = $p['collection']['CASH'] ?? 0.0;
            $pCollTotal = array_sum($p['collection']);
            $cCollCash = $c['collection']['CASH'] ?? 0.0;
            $cCollTotal = array_sum($c['collection']);

            $pRefundTotal = array_sum($p['refund']);
            $cRefundTotal = array_sum($c['refund']);

            $pPaidTotal = array_sum($p['doctor_paid']);
            $cPaidTotal = array_sum($c['doctor_paid']);

            $pNet = $pCollCash - ($p['refund']['CASH'] ?? 0.0) - ($p['doctor_paid']['CASH'] ?? 0.0);
            $cNet = $cCollCash - ($c['refund']['CASH'] ?? 0.0) - ($c['doctor_paid']['CASH'] ?? 0.0);

            $pendingTotal = $pending[$userId]['total'] ?? 0.0;

            // doctor-wise: this period's payable / paid, and what is still pending at the end
            $doctors = [];

            foreach (($periodDoctors[$userId] ?? []) as $doctorId => $d) {
                $doctors[$doctorId] = [
                    'doctor_id' => $doctorId,
                    'doctor_name' => $d['name'],
                    'payable' => round($d['payable'], 2),
                    'paid' => round($d['paid'], 2),
                    'pending' => 0.0,
                    'unpaid_count' => 0,
                ];
            }

            foreach (($pending[$userId]['doctors'] ?? []) as $doctorId => $d) {
                $doctors[$doctorId] ??= [
                    'doctor_id' => $doctorId,
                    'doctor_name' => $d['name'],
                    'payable' => 0.0,
                    'paid' => 0.0,
                    'pending' => 0.0,
                    'unpaid_count' => 0,
                ];
                $doctors[$doctorId]['pending'] = round($d['pending'], 2);
                $doctors[$doctorId]['unpaid_count'] = $d['unpaid_count'];
            }

            $doctors = array_values(array_filter(
                $doctors,
                fn ($d) => abs($d['payable']) > 0.004 || abs($d['paid']) > 0.004 || abs($d['pending']) > 0.004
            ));
            usort($doctors, fn ($a, $b) => strcmp((string) $a['doctor_name'], (string) $b['doctor_name']));

            $rows[$userId] = [
                'columns' => [
                    'user_id' => $userId,
                    'month_start' => $from,
                    'period_collection_cash' => round($pCollCash, 2),
                    'period_collection_non_cash' => round($pCollTotal - $pCollCash, 2),
                    'period_refund' => round($pRefundTotal, 2),
                    'period_doctor_payable' => round($p['doctor_payable'], 2),
                    'period_doctor_paid' => round($pPaidTotal, 2),
                    'period_net_cash' => round($pNet, 2),
                    'cum_collection_cash' => round($cCollCash, 2),
                    'cum_collection_non_cash' => round($cCollTotal - $cCollCash, 2),
                    'cum_refund' => round($cRefundTotal, 2),
                    'cum_doctor_payable' => round($c['doctor_payable'], 2),
                    'cum_doctor_paid' => round($cPaidTotal, 2),
                    'cum_net_cash' => round($cNet, 2),
                    'doctor_pending' => round($pendingTotal, 2),
                    'doctor_pending_waiting' => round($pending[$userId]['waiting'] ?? 0, 2),
                ],
                'details' => [
                    'modes' => [
                        'collection' => ['period' => $this->roundAll($p['collection']), 'cum' => $this->roundAll($c['collection'])],
                        'refund' => ['period' => $this->roundAll($p['refund']), 'cum' => $this->roundAll($c['refund'])],
                        'doctor_paid' => ['period' => $this->roundAll($p['doctor_paid']), 'cum' => $this->roundAll($c['doctor_paid'])],
                    ],
                    'doctors' => $doctors,
                    // cumulative: why payable and paid differ (pending is only part of the story)
                    'reconciliation' => $this->reconciliation($c['doctor_payable'], $cPaidTotal, $pendingTotal, $flows[$userId] ?? []),
                ],
            ];
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | FIGURES
    |--------------------------------------------------------------------------
    */

    private function emptyFigures(): array
    {
        return ['collection' => [], 'refund' => [], 'doctor_paid' => [], 'doctor_payable' => 0.0];
    }

    private function roundAll(array $byMode): array
    {
        return array_map(fn ($v) => round($v, 2), $byMode);
    }

    /**
     * Collection / refund / doctor paid by mode, and doctor payable, per user, for from..to.
     *
     * @return array<int,array{collection:array,refund:array,doctor_paid:array,doctor_payable:float}>
     */
    private function figures(string $from, string $to): array
    {
        $out = [];

        $ensure = function ($userId) use (&$out) {
            $out[$userId] ??= $this->emptyFigures();
        };

        $mode = fn ($m) => strtoupper(trim((string) ($m ?: 'UNKNOWN')));

        foreach ($this->transactionTotals('RECEIVED', 'received_amount', $from, $to) as $r) {
            $ensure($r->created_by);
            $k = $mode($r->payment_mode);
            $out[$r->created_by]['collection'][$k] = ($out[$r->created_by]['collection'][$k] ?? 0) + (float) $r->total;
        }

        foreach ($this->transactionTotals('REFUND', 'refund_amount', $from, $to) as $r) {
            $ensure($r->created_by);
            $k = $mode($r->payment_mode);
            $out[$r->created_by]['refund'][$k] = ($out[$r->created_by]['refund'][$k] ?? 0) + (float) $r->total;
        }

        $paid = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->where('ds.status', '!=', 'CANCELLED')
            ->whereBetween(DB::raw('DATE(ds.settlement_date)'), [$from, $to])
            ->groupBy('ds.created_by', 'ds.payment_mode')
            ->selectRaw('ds.created_by, ds.payment_mode, SUM(dsi.settlement_amount) as total')
            ->get();

        foreach ($paid as $r) {
            $ensure($r->created_by);
            $k = $mode($r->payment_mode);
            $out[$r->created_by]['doctor_paid'][$k] = ($out[$r->created_by]['doctor_paid'][$k] ?? 0) + (float) $r->total;
        }

        $payable = $this->validPayables()
            ->whereBetween(DB::raw('DATE(dp.created_at)'), [$from, $to])
            ->groupBy('dp.created_by')
            ->selectRaw('dp.created_by, SUM(dp.payable_amount) as total')
            ->get();

        foreach ($payable as $r) {
            $ensure($r->created_by);
            $out[$r->created_by]['doctor_payable'] += (float) $r->total;
        }

        unset($out[null], $out['']);

        return $out;
    }

    private function transactionTotals(string $type, string $amountColumn, string $from, string $to)
    {
        return DB::table('daily_transactions')
            ->where('transaction_type', $type)
            ->where('status', 'ACTIVE')
            ->whereBetween('transaction_date', [$from, $to])
            ->groupBy('created_by', 'payment_mode')
            ->selectRaw("created_by, payment_mode, SUM({$amountColumn}) as total")
            ->get();
    }

    /** Doctor payables that count (not cancelled; of a cancelled invoice only if already paid out). */
    private function validPayables()
    {
        return DB::table('doctor_payables as dp')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'dp.invoice_id')
            ->where('dp.payment_status', '!=', 'CANCELLED')
            ->where(function ($q) {
                $q->whereNull('inv.cancelled')
                    ->orWhere('inv.cancelled', '!=', 'Y')
                    ->orWhere('dp.payment_status', 'PAID');
            });
    }

    /**
     * Doctor-wise payable created and paid out within from..to, per user.
     *
     * @return array<int,array<int,array{name:?string,payable:float,paid:float}>> user => doctor => ...
     */
    private function doctorMovements(string $from, string $to): array
    {
        $out = [];

        $payable = $this->validPayables()
            ->whereBetween(DB::raw('DATE(dp.created_at)'), [$from, $to])
            ->groupBy('dp.created_by', 'dp.doctor_id', 'dp.doctor_name')
            ->selectRaw('dp.created_by, dp.doctor_id, dp.doctor_name, SUM(dp.payable_amount) as total')
            ->get();

        foreach ($payable as $r) {
            $out[$r->created_by][$r->doctor_id ?? 0] ??= ['name' => $r->doctor_name, 'payable' => 0.0, 'paid' => 0.0];
            $out[$r->created_by][$r->doctor_id ?? 0]['payable'] += (float) $r->total;
        }

        $paid = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->where('ds.status', '!=', 'CANCELLED')
            ->whereBetween(DB::raw('DATE(ds.settlement_date)'), [$from, $to])
            ->groupBy('ds.created_by', 'ds.doctor_id', 'ds.doctor_name')
            ->selectRaw('ds.created_by, ds.doctor_id, ds.doctor_name, SUM(dsi.settlement_amount) as total')
            ->get();

        foreach ($paid as $r) {
            $out[$r->created_by][$r->doctor_id ?? 0] ??= ['name' => $r->doctor_name, 'payable' => 0.0, 'paid' => 0.0];
            $out[$r->created_by][$r->doctor_id ?? 0]['paid'] += (float) $r->total;
        }

        return $out;
    }

    /**
     * The invoice-wise lines behind the cumulative reconciliation of ONE user, up to $asOf:
     *
     *   paid_for_others ..... payables of OTHER users' invoices that this user paid out
     *   own_paid_by_others .. payables of this user's invoices that OTHER users paid out
     *   pending ............. payables of this user's invoices still (partly) unpaid
     *
     * @return array{paid_for_others:array,own_paid_by_others:array,pending:array}
     */
    public function crossUserItems(int $userId, string $asOf): array
    {
        $users = DB::table('users')->pluck('name', 'id');

        $settled = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->join('doctor_payables as dp', 'dp.id', '=', 'dsi.payable_id')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'dp.invoice_id')
            ->where('ds.status', '!=', 'CANCELLED')
            ->where('dp.payment_status', '!=', 'CANCELLED')
            ->where(function ($q) {
                $q->whereNull('inv.cancelled')
                    ->orWhere('inv.cancelled', '!=', 'Y')
                    ->orWhere('dp.payment_status', 'PAID');
            })
            ->whereDate('ds.settlement_date', '<=', $asOf)
            ->where(function ($q) use ($userId) {
                $q->where(function ($w) use ($userId) {
                    $w->where('ds.created_by', $userId)->where('dp.created_by', '!=', $userId);
                })->orWhere(function ($w) use ($userId) {
                    $w->where('dp.created_by', $userId)->where('ds.created_by', '!=', $userId);
                });
            })
            ->orderBy('ds.settlement_date')
            ->orderBy('dsi.invoice_no')
            ->get([
                'dsi.invoice_no', 'dsi.item_description', 'dsi.settlement_amount',
                'ds.settlement_no', 'ds.settlement_date', 'ds.doctor_name', 'ds.created_by as payer_id',
                'dp.created_by as creator_id', 'dp.created_at as payable_created', 'dp.payable_amount',
            ]);

        $line = fn ($r) => [
            'invoice_no' => $r->invoice_no,
            'item' => $r->item_description,
            'doctor' => $r->doctor_name,
            'billed_by' => $users[$r->creator_id] ?? '-',
            'payable_date' => $r->payable_created ? Carbon::parse($r->payable_created)->format('d-m-Y') : '-',
            'payable_amount' => round((float) $r->payable_amount, 2),
            'paid_by' => $users[$r->payer_id] ?? '-',
            'settled_on' => $r->settlement_date ? Carbon::parse($r->settlement_date)->format('d-m-Y') : '-',
            'settlement_no' => $r->settlement_no,
            'amount' => round((float) $r->settlement_amount, 2),
        ];

        $forOthers = [];
        $ownByOthers = [];

        foreach ($settled as $r) {
            if ((int) $r->payer_id === $userId) {
                $forOthers[] = $line($r);
            } else {
                $ownByOthers[] = $line($r);
            }
        }

        // still unpaid on this user's own invoices
        $payables = $this->validPayables()
            ->where('dp.created_by', $userId)
            ->whereDate('dp.created_at', '<=', $asOf)
            ->get(['dp.id', 'dp.invoice_id', 'dp.invoice_no', 'dp.item_description', 'dp.doctor_name', 'dp.payable_amount', 'dp.created_at']);

        // Doctor Settlement (By User) only offers a payable once the PATIENT has paid the
        // invoice in full (nothing due), under the user who collected it last, on the date the
        // invoice was last updated -- so show, per line, whether it can be settled yet and where.
        $invoices = DB::table('invoices')
            ->whereIn('id', $payables->pluck('invoice_id')->unique())
            ->get(['id', 'invoice_no', 'due_amount', 'updated_at', 'doctor_amount_collected_by'])
            ->keyBy('id');

        $lastCollector = DB::table('daily_transactions')
            ->whereIn('invoice_reference', $invoices->pluck('invoice_no'))
            ->where('transaction_type', 'RECEIVED')
            ->where('status', '!=', 'CANCELLED')
            ->orderBy('created_at')
            ->get(['invoice_reference', 'operator_id'])
            ->groupBy('invoice_reference')
            ->map(fn ($g) => $g->last()->operator_id);

        $paidSoFar = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->where('ds.status', '!=', 'CANCELLED')
            ->whereDate('ds.settlement_date', '<=', $asOf)
            ->whereIn('dsi.payable_id', $payables->pluck('id'))
            ->groupBy('dsi.payable_id')
            ->selectRaw('dsi.payable_id, SUM(dsi.settlement_amount) as total')
            ->pluck('total', 'payable_id');

        $pending = [];

        foreach ($payables as $p) {

            $left = (float) $p->payable_amount - (float) ($paidSoFar[$p->id] ?? 0);

            if (abs($left) > 0.004) {

                $inv = $invoices[$p->invoice_id] ?? null;
                $due = $inv ? (float) $inv->due_amount : 0.0;
                $collectorId = $inv ? ($inv->doctor_amount_collected_by ?: ($lastCollector[$inv->invoice_no] ?? null)) : null;

                $pending[] = [
                    'invoice_no' => $p->invoice_no,
                    'item' => $p->item_description,
                    'doctor' => $p->doctor_name,
                    'payable_date' => Carbon::parse($p->created_at)->format('d-m-Y'),
                    'payable_amount' => round((float) $p->payable_amount, 2),
                    'settled' => round((float) ($paidSoFar[$p->id] ?? 0), 2),
                    'amount' => round($left, 2),
                    // can it be settled yet?
                    'patient_due' => round($due, 2),
                    'ready' => $due <= 0.004,
                    'settle_under' => $collectorId ? ($users[$collectorId] ?? '-') : '-',
                    'settle_on' => $inv && $inv->updated_at ? Carbon::parse($inv->updated_at)->format('d-m-Y') : '-',
                ];
            }
        }

        return ['paid_for_others' => $forOthers, 'own_paid_by_others' => $ownByOthers, 'pending' => $pending];
    }

    /**
     * Cumulative payable - paid for one user, split into its parts:
     *
     *   payable - paid  =  pending
     *                      + payables of this user's invoices that OTHER users paid out
     *                      - payables of OTHER users' invoices that THIS user paid out
     *
     * (a user's pending only concerns their own invoices, while "paid" is whatever they
     * personally paid out, whoever's invoice it belonged to)
     */
    private function reconciliation(float $payable, float $paid, float $pending, array $flow): array
    {
        $own = (float) ($flow['own_paid_by_self'] ?? 0);
        $paidForOthers = $paid - $own;
        $ownPaidByOthers = (float) ($flow['settled_against_own'] ?? 0) - $own;

        return [
            'payable' => round($payable, 2),
            'paid' => round($paid, 2),
            'difference' => round($payable - $paid, 2),
            'pending' => round($pending, 2),
            'own_paid_by_others' => round($ownPaidByOthers, 2),
            'paid_for_others' => round($paidForOthers, 2),
        ];
    }

    /**
     * Per user, up to $asOf: how much was settled against the payables of the user's own
     * invoices (by anyone), and how much of that the user paid out personally.
     *
     * @return array<int,array{settled_against_own:float,own_paid_by_self:float}>
     */
    private function settlementFlowsAsOf(string $asOf): array
    {
        $rows = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->join('doctor_payables as dp', 'dp.id', '=', 'dsi.payable_id')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'dp.invoice_id')
            ->where('ds.status', '!=', 'CANCELLED')
            ->where('dp.payment_status', '!=', 'CANCELLED')
            ->where(function ($q) {
                $q->whereNull('inv.cancelled')
                    ->orWhere('inv.cancelled', '!=', 'Y')
                    ->orWhere('dp.payment_status', 'PAID');
            })
            ->whereDate('ds.settlement_date', '<=', $asOf)
            ->groupBy('dp.created_by', 'ds.created_by')
            ->selectRaw('dp.created_by as owner, ds.created_by as payer, SUM(dsi.settlement_amount) as total')
            ->get();

        $out = [];

        foreach ($rows as $r) {

            if ($r->owner === null) {
                continue;
            }

            $out[$r->owner]['settled_against_own'] = ($out[$r->owner]['settled_against_own'] ?? 0) + (float) $r->total;

            if ((int) $r->owner === (int) $r->payer) {
                $out[$r->owner]['own_paid_by_self'] = ($out[$r->owner]['own_paid_by_self'] ?? 0) + (float) $r->total;
            }
        }

        return $out;
    }

    /**
     * Doctor payment still pending at the end of $asOf, on the invoices each user billed:
     * every payable created up to then, less what was settled against it up to then.
     *
     * @return array<int,array{total:float,doctors:array}> keyed by user id
     */
    private function pendingAsOf(string $asOf): array
    {
        $payables = $this->validPayables()
            ->whereDate('dp.created_at', '<=', $asOf)
            ->get(['dp.id', 'dp.created_by', 'dp.doctor_id', 'dp.doctor_name', 'dp.payable_amount', 'inv.due_amount as invoice_due']);

        $settled = DB::table('doctor_settlement_items as dsi')
            ->join('doctor_settlements as ds', 'ds.id', '=', 'dsi.settlement_id')
            ->where('ds.status', '!=', 'CANCELLED')
            ->whereDate('ds.settlement_date', '<=', $asOf)
            ->whereNotNull('dsi.payable_id')
            ->groupBy('dsi.payable_id')
            ->selectRaw('dsi.payable_id, SUM(dsi.settlement_amount) as total')
            ->pluck('total', 'payable_id');

        $out = [];

        foreach ($payables as $p) {

            if ($p->created_by === null) {
                continue;
            }

            $pending = (float) $p->payable_amount - (float) ($settled[$p->id] ?? 0);

            $out[$p->created_by] ??= ['total' => 0.0, 'waiting' => 0.0, 'doctors' => []];
            $out[$p->created_by]['total'] += $pending;

            // still unpaid AND the patient has not paid the invoice in full: cannot be settled yet
            if ($pending > 0.004 && (float) ($p->invoice_due ?? 0) > 0.004) {
                $out[$p->created_by]['waiting'] += $pending;
            }

            $d = $p->doctor_id ?? 0;
            $out[$p->created_by]['doctors'][$d] ??= ['name' => $p->doctor_name, 'pending' => 0.0, 'unpaid_count' => 0];
            $out[$p->created_by]['doctors'][$d]['pending'] += $pending;

            if ($pending > 0.004) {
                $out[$p->created_by]['doctors'][$d]['unpaid_count']++;
            }
        }

        return $out;
    }
}
