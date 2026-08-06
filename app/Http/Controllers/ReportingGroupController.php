<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemMaster;
use App\Models\ReportingGroup;
use App\Models\ReportingGroupItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ReportingGroupController extends Controller
{
    private const MODULE_CODE = 'REPORTING_GROUP';

    /*
    |--------------------------------------------------------------------------
    | VIEW
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $itemMasters = InvoiceItemMaster::where('status', 1)
            ->orderBy('item_name')
            ->get(['item_code', 'item_name', 'item_type']);

        return view('apps-reporting-group', compact('itemMasters'));
    }

    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = ReportingGroup::with('items');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('group_name', 'like', "%{$search}%");
        }

        $groups = $query->orderBy('group_name')->paginate($perPage);

        $itemNameMap = InvoiceItemMaster::pluck('item_name', 'item_code');

        $groups->getCollection()->transform(function ($group) use ($itemNameMap) {

            $group->item_codes = $group->items->pluck('item_code')->values();

            $group->item_labels = $group->items
                ->map(fn($item) => $itemNameMap[$item->item_code] ?? $item->item_code)
                ->values();

            return $group;
        });

        return response()->json([
            'status' => true,
            'data' => $groups->items(),
            'pagination' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'total' => $groups->total()
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'group_name' => 'required|max:150|unique:reporting_groups,group_name',
            'remarks' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'item_codes' => 'nullable|array',
            'item_codes.*' => 'exists:invoice_item_masters,item_code',
        ]);

        $conflict = $this->findItemCodeConflict($request->item_codes ?? [], null);

        if ($conflict) {

            return response()->json([
                'status' => false,
                'message' => "Item code {$conflict['item_code']} is already assigned to reporting group \"{$conflict['group_name']}\"."
            ], 422);
        }

        DB::beginTransaction();

        try {

            $group = ReportingGroup::create([
                'group_name' => $request->group_name,
                'remarks' => $request->remarks,
                'status' => $request->status,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($group, $request->item_codes ?? []);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $group,
                $group->only($group->getFillable()),
                'Reporting group created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Reporting group created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save reporting group.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT / UPDATE
    |--------------------------------------------------------------------------
    */

    public function edit($id)
    {
        $group = ReportingGroup::with('items')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id' => $group->id,
                'group_name' => $group->group_name,
                'remarks' => $group->remarks,
                'status' => $group->status,
                'item_codes' => $group->items->pluck('item_code')->values(),
            ]
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'group_name' => 'required|max:150|unique:reporting_groups,group_name,' . $id,
            'remarks' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'item_codes' => 'nullable|array',
            'item_codes.*' => 'exists:invoice_item_masters,item_code',
        ]);

        $group = ReportingGroup::findOrFail($id);

        $conflict = $this->findItemCodeConflict($request->item_codes ?? [], $group->id);

        if ($conflict) {

            return response()->json([
                'status' => false,
                'message' => "Item code {$conflict['item_code']} is already assigned to reporting group \"{$conflict['group_name']}\"."
            ], 422);
        }

        $oldData = $group->only($group->getFillable());

        DB::beginTransaction();

        try {

            $group->update([
                'group_name' => $request->group_name,
                'remarks' => $request->remarks,
                'status' => $request->status,
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($group, $request->item_codes ?? []);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $group,
                $oldData,
                $group->only($group->getFillable()),
                'Reporting group updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Reporting group updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update reporting group.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        try {

            $group = ReportingGroup::findOrFail($id);

            $oldData = $group->only($group->getFillable());

            $group->items()->delete();

            $group->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $group,
                $oldData,
                'Reporting group deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Reporting group deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete reporting group.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function findItemCodeConflict(array $itemCodes, ?int $excludeGroupId): ?array
    {
        if (empty($itemCodes)) {
            return null;
        }

        $existing = ReportingGroupItem::with('group')
            ->whereIn('item_code', $itemCodes)
            ->when($excludeGroupId, fn($q) => $q->where('reporting_group_id', '!=', $excludeGroupId))
            ->first();

        if (!$existing) {
            return null;
        }

        return [
            'item_code' => $existing->item_code,
            'group_name' => optional($existing->group)->group_name,
        ];
    }

    private function syncItems(ReportingGroup $group, array $itemCodes): void
    {
        ReportingGroupItem::where('reporting_group_id', $group->id)->delete();

        foreach (array_unique($itemCodes) as $itemCode) {

            ReportingGroupItem::create([
                'reporting_group_id' => $group->id,
                'item_code' => $itemCode,
                'created_by' => Auth::id(),
            ]);
        }
    }
}
