<?php

namespace App\Http\Controllers;

use App\Models\CashAdjustment;
use App\Services\AuditService;
use App\Services\CashInHandService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Admin-only screen that clears a staff user's cash deficit.
 *
 * A deficit arises when cash paid out (doctor settlements, refunds) in a day
 * exceeds the cash that user collected. It is deliberately NOT netted against
 * the user's next-day collections -- nobody has verified the payout, so doing
 * that would let them keep later cash. Instead it stays pending (see
 * CashInHandService) until an Admin records WHY it is legitimate here:
 * cash handed back by accounts, a bank withdrawal, or reimbursement of money
 * the user paid out of pocket, ideally referencing the earlier deposit it is
 * adjusted against. The entry is dated to the deficit day, shows on that
 * day's cash submission slip, and is never revenue. If the payout never
 * really happened, cancel the doctor settlement instead.
 *
 * Deliberately NOT in config/access_pages, so it cannot be granted to
 * other roles.
 */
class CashAdjustmentController extends Controller
{
    private const MODULE_CODE = 'CASH_ADJUSTMENT';

    private const SOURCES = [
        'ACCOUNTS_CASH' => 'Cash handed by Accounts',
        'BANK_WITHDRAWAL' => 'Bank withdrawal',
        'OWN_POCKET_REIMBURSEMENT' => 'Reimbursement of cash paid by user',
        'OTHER' => 'Other',
    ];

    private function ensureAdmin(): void
    {
        abort_unless(
            optional(Auth::user())->role === 'Admin',
            403,
            'Only an Admin can access Cash Adjustments.'
        );
    }

    public function index()
    {
        $this->ensureAdmin();

        return view('apps-cash-adjustment', ['sources' => self::SOURCES]);
    }

    /*
    |--------------------------------------------------------------------------
    | Pending deficits + recent adjustments
    |--------------------------------------------------------------------------
    */

    public function data()
    {
        $this->ensureAdmin();

        $service = app(CashInHandService::class);
        $today = now()->toDateString();

        // Candidate (user, date) pairs: every recorded closing that ended
        // negative, plus any user whose live balance today is negative.
        $pairs = DB::table('cash_day_closings')
            ->where('carried_forward', '<', 0)
            ->get(['user_id', 'closing_date'])
            ->map(fn($r) => [(int) $r->user_id, substr($r->closing_date, 0, 10)]);

        $live = DB::table('users')
            ->where('cash_in_hand', '<', 0)
            ->get(['id', 'cash_in_hand_date'])
            ->map(fn($u) => [(int) $u->id, $u->cash_in_hand_date ? substr($u->cash_in_hand_date, 0, 10) : $today]);

        $pairs = $pairs->merge($live)->unique(fn($p) => $p[0] . '|' . $p[1])->values();

        $names = DB::table('users')->whereIn('id', $pairs->pluck(0)->unique())->pluck('name', 'id');

        $deficits = $pairs->map(function ($p) use ($service, $names) {

            $deficit = $service->deficitFor($p[0], $p[1]);

            return $deficit > 0 ? [
                'user_id' => $p[0],
                'user_name' => $names[$p[0]] ?? ('User #' . $p[0]),
                'date' => $p[1],
                'date_fmt' => Carbon::parse($p[1])->format('d-m-Y'),
                'deficit' => $deficit,
            ] : null;
        })->filter()->sortBy('date')->values();

        $recent = DB::table('cash_adjustments as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->orderByDesc('a.id')
            ->limit(50)
            ->get([
                'a.id', 'a.adjustment_no', 'a.adjustment_date', 'a.amount', 'a.source', 'a.reference_date',
                'a.reference_note', 'a.remarks', 'a.status', 'a.created_by_name', 'a.cancel_reason', 'u.name as user_name',
            ])
            ->map(function ($a) {
                $a->adjustment_date_fmt = Carbon::parse($a->adjustment_date)->format('d-m-Y');
                $a->reference_date_fmt = $a->reference_date ? Carbon::parse($a->reference_date)->format('d-m-Y') : '';
                $a->source_label = self::SOURCES[$a->source] ?? $a->source;
                return $a;
            });

        return response()->json(['status' => true, 'deficits' => $deficits, 'recent' => $recent]);
    }

    /*
    |--------------------------------------------------------------------------
    | Post an adjustment
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'adjustment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'source' => 'required|in:' . implode(',', array_keys(self::SOURCES)),
            'reference_date' => 'nullable|date|before_or_equal:today',
            'reference_note' => 'nullable|string|max:255',
            'remarks' => 'required|string|min:5|max:500',
        ]);

        $userId = (int) $request->user_id;
        $date = Carbon::parse($request->adjustment_date)->toDateString();
        $amount = round((float) $request->amount, 2);

        DB::beginTransaction();

        try {

            // Serialise concurrent postings for the same user.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();

            $service = app(CashInHandService::class);

            $deficit = $service->deficitFor($userId, $date);

            if ($deficit <= 0) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'This user has no pending deficit on ' . Carbon::parse($date)->format('d-m-Y') . '.'], 422);
            }

            if ($amount - $deficit > 0.004) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Adjustment (₹' . number_format($amount, 2) . ') is more than the pending deficit (₹' . number_format($deficit, 2) . ').'], 422);
            }

            $adjustment = CashAdjustment::create([
                'user_id' => $userId,
                'adjustment_date' => $date,
                'amount' => $amount,
                'source' => $request->source,
                'reference_date' => $request->reference_date ?: null,
                'reference_note' => $request->reference_note ? trim($request->reference_note) : null,
                'remarks' => trim($request->remarks),
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'created_by_name' => Auth::user()->name,
            ]);

            $adjustment->adjustment_no = 'ADJ/' . now()->format('my') . '/' . str_pad((string) $adjustment->id, 6, '0', STR_PAD_LEFT);
            $adjustment->save();

            $service->applyAdjustment($userId, $date, $amount, 1);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $adjustment,
                $adjustment->only($adjustment->getFillable()),
                'Cash adjustment posted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Adjustment ' . $adjustment->adjustment_no . ' posted.',
            ]);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Cash Adjustment Failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel an adjustment (restores the deficit)
    |--------------------------------------------------------------------------
    */

    public function cancel(Request $request, $id, AuditService $auditService)
    {
        $this->ensureAdmin();

        $request->validate(['reason' => 'required|string|min:5|max:255']);

        DB::beginTransaction();

        try {

            $adjustment = CashAdjustment::where('id', $id)->lockForUpdate()->first();

            if (!$adjustment || $adjustment->status !== 'ACTIVE') {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Adjustment not found or already cancelled.'], 422);
            }

            $old = $adjustment->only($adjustment->getFillable());

            app(CashInHandService::class)->applyAdjustment(
                (int) $adjustment->user_id,
                $adjustment->adjustment_date->toDateString(),
                (float) $adjustment->amount,
                -1
            );

            $adjustment->status = 'CANCELLED';
            $adjustment->cancelled_by = Auth::id();
            $adjustment->cancelled_at = now();
            $adjustment->cancel_reason = trim($request->reason);
            $adjustment->save();

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $adjustment,
                $old,
                $adjustment->only($adjustment->getFillable()),
                'Cash adjustment cancelled'
            );

            return response()->json(['status' => true, 'message' => 'Adjustment cancelled; the deficit is pending again.']);

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('Cash Adjustment Cancel Failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
