<?php

namespace App\Http\Controllers;

use App\Models\MembershipFeeRate;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MembershipFeeRateController extends Controller
{
    private const MODULE_CODE = 'MEMBERSHIP_FEE_RATE_MASTER';

    public function index()
    {
        return view('apps-membership-fee-rate');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $rates = MembershipFeeRate::with('creator')
            ->orderByDesc('effective_from')
            ->paginate($perPage);

        $rates->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)
                ->format('d-m-Y H:i');

            $row->created_by_name = optional($row->creator)->name;

            return $row;

        });

        return response()->json([
            'status' => true,
            'data' => $rates->items(),
            'pagination' => [
                'current_page' => $rates->currentPage(),
                'last_page' => $rates->lastPage(),
                'total' => $rates->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'effective_from' => 'required|date|unique:membership_fee_rates,effective_from',
            'monthly_rate' => 'required|numeric|min:0',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $rate = MembershipFeeRate::create([

                'effective_from' => \Carbon\Carbon::parse($request->effective_from)->startOfMonth()->toDateString(),

                'monthly_rate' => $request->monthly_rate,

                'remarks' => $request->remarks,

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()

            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $rate,
                $rate->only($rate->getFillable()),
                'Membership fee rate created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Membership fee rate created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save membership fee rate.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $rate = MembershipFeeRate::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $rate
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'effective_from' => 'required|date|unique:membership_fee_rates,effective_from,' . $id,
            'monthly_rate' => 'required|numeric|min:0',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $rate = MembershipFeeRate::findOrFail($id);

            $oldData = $rate->only($rate->getFillable());

            $rate->update([

                'effective_from' => \Carbon\Carbon::parse($request->effective_from)->startOfMonth()->toDateString(),

                'monthly_rate' => $request->monthly_rate,

                'remarks' => $request->remarks,

                'status' => $request->status,

                'updated_by' => Auth::id()

            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $rate,
                $oldData,
                $rate->only($rate->getFillable()),
                'Membership fee rate updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Membership fee rate updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update membership fee rate.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $rate = MembershipFeeRate::findOrFail($id);

            $inUse = DB::table('membership_fee_payments')
                ->where('rate_applied', $rate->monthly_rate)
                ->exists();

            if ($inUse) {

                return response()->json([
                    'status' => false,
                    'message' => 'This rate cannot be deleted. It has already been used in recorded payments.'
                ]);
            }

            $oldData = $rate->only($rate->getFillable());

            $rate->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $rate,
                $oldData,
                'Membership fee rate deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Membership fee rate deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete membership fee rate.'
            ], 500);
        }
    }
}
