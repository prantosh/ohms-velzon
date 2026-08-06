<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\SampleMaster;
use App\Models\TestAnalyte;
use App\Models\TestGroupMaster;
use App\Models\TestSubGroup;
use App\Models\TestSubGroupMember;
use App\Models\UomMaster;
use App\Models\TestResultEntry;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class TestParameterController extends Controller
{
    private const MODULE_CODE = 'INVOICE_ITEM_DETAIL';

    private const ANALYTE_MODULE_CODE = 'TEST_ANALYTE_MASTER';

    private const SUB_GROUP_MODULE_CODE = 'TEST_SUB_GROUP';

    private const EDITABLE_FIELDS = [
        'test_group_code',
        'sample_master_id',
        'uom',
        'range_male',
        'range_female',
        'range_common',
        'method',
        'report_days',
    ];

    public function index()
    {
        $itemMasters = InvoiceItemMaster::where('status', 1)
            ->where('test_parameter_required', 'YES')
            ->orderBy('item_name')
            ->get(['item_code', 'item_name']);

        $testGroups = TestGroupMaster::where('status', 'ACTIVE')
            ->orderBy('test_group_name')
            ->get();

        $sampleMasters = SampleMaster::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        $uomMasters = UomMaster::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();

        return view(
            'apps-test-parameter',
            compact('itemMasters', 'testGroups', 'sampleMasters', 'uomMasters')
        );
    }

    public function list(Request $request, $itemCode)
    {
        $perPage = $request->get('per_page', 25);

        $query = InvoiceItemDetail::leftJoin(
            'test_group_masters',
            'invoice_item_details.test_group_code',
            '=',
            'test_group_masters.id'
        )
            ->leftJoin(
                'sample_masters',
                'invoice_item_details.sample_master_id',
                '=',
                'sample_masters.id'
            )
            ->where('invoice_item_details.item_code', $itemCode)
            ->select(
                'invoice_item_details.*',
                'test_group_masters.test_group_name',
                'sample_masters.name as sample_name'
            );

        if ($request->filled('search')) {

            $query->where(
                'invoice_item_details.item_description_sub',
                'like',
                '%' . trim($request->search) . '%'
            );
        }

        $rows = $query
            ->orderBy('invoice_item_details.item_code_sub')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $rows->items(),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'total' => $rows->total(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem()
            ]
        ]);
    }

    public function edit($id)
    {
        $item = InvoiceItemDetail::findOrFail($id);

        $item->analyte_count = $item->analytes()->count();

        return response()->json([
            'status' => true,
            'data' => $item
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'test_group_code' => 'nullable|exists:test_group_masters,id',
            'sample_master_id' => 'nullable|exists:sample_masters,id',
            'uom' => 'nullable|max:30',
            'range_male' => 'nullable|max:1000',
            'range_female' => 'nullable|max:1000',
            'range_common' => 'nullable|max:1000',
            'method' => 'nullable|max:100',
            'report_days' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();

        try {

            $item = InvoiceItemDetail::findOrFail($id);

            $oldData = $item->only(self::EDITABLE_FIELDS);

            $newData = [
                'test_group_code' => $request->test_group_code,
                'sample_master_id' => $request->sample_master_id,
                'uom' => $request->uom,
                'range_male' => $request->range_male,
                'range_female' => $request->range_female,
                'range_common' => $request->range_common,
                'method' => $request->method,
                'report_days' => $request->report_days ?? 0,
            ];

            $item->update(array_merge($newData, [
                'update_by' => Auth::id(),
                'update_dt' => now(),
            ]));

            $auditService->logUpdate(
                self::MODULE_CODE,
                $item,
                $oldData,
                $newData,
                'Test parameter entry updated'
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Test parameter updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update test parameter.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGE ANALYTES (components of a panel test, e.g. CBC -> Hb, TC, DC...)
    |--------------------------------------------------------------------------
    */

    public function analyteList($invoiceItemDetailId)
    {
        $analytes = TestAnalyte::where('invoice_item_detail_id', $invoiceItemDetailId)
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $analytes
        ]);
    }

    public function analyteStore(Request $request, $invoiceItemDetailId, AuditService $auditService)
    {
        InvoiceItemDetail::findOrFail($invoiceItemDetailId);

        $request->validate([
            'analyte_name' => 'required|max:150',
            'group_name' => 'nullable|max:100',
            'uom' => 'nullable|max:30',
            'range_male' => 'nullable|max:1000',
            'range_female' => 'nullable|max:1000',
            'range_common' => 'nullable|max:1000',
            'method' => 'nullable|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $duplicate = TestAnalyte::where('invoice_item_detail_id', $invoiceItemDetailId)
            ->whereRaw('UPPER(analyte_name) = ?', [strtoupper(trim($request->analyte_name))])
            ->exists();

        if ($duplicate) {

            return response()->json([
                'status' => false,
                'message' => 'An analyte with this name already exists for this test.'
            ]);
        }

        try {

            $analyte = TestAnalyte::create([

                'invoice_item_detail_id' => $invoiceItemDetailId,
                'analyte_name' => trim($request->analyte_name),
                'group_name' => $request->group_name ? trim($request->group_name) : null,
                'uom' => $request->uom,
                'range_male' => $request->range_male,
                'range_female' => $request->range_female,
                'range_common' => $request->range_common,
                'method' => $request->method,
                'sort_order' => $request->sort_order ?? 0,
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $auditService->logCreate(
                self::ANALYTE_MODULE_CODE,
                $analyte,
                $analyte->only($analyte->getFillable()),
                'Analyte added'
            );

            return response()->json([
                'status' => true,
                'message' => 'Analyte added successfully.',
                'data' => $analyte
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add analyte.'
            ], 500);
        }
    }

    public function analyteUpdate(Request $request, $id, AuditService $auditService)
    {
        $analyte = TestAnalyte::findOrFail($id);

        $request->validate([
            'analyte_name' => 'required|max:150',
            'group_name' => 'nullable|max:100',
            'uom' => 'nullable|max:30',
            'range_male' => 'nullable|max:1000',
            'range_female' => 'nullable|max:1000',
            'range_common' => 'nullable|max:1000',
            'method' => 'nullable|max:100',
            'sort_order' => 'nullable|integer',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        $duplicate = TestAnalyte::where('invoice_item_detail_id', $analyte->invoice_item_detail_id)
            ->whereRaw('UPPER(analyte_name) = ?', [strtoupper(trim($request->analyte_name))])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {

            return response()->json([
                'status' => false,
                'message' => 'An analyte with this name already exists for this test.'
            ]);
        }

        try {

            $oldData = $analyte->only($analyte->getFillable());

            $analyte->update([

                'analyte_name' => trim($request->analyte_name),
                'group_name' => $request->group_name ? trim($request->group_name) : null,
                'uom' => $request->uom,
                'range_male' => $request->range_male,
                'range_female' => $request->range_female,
                'range_common' => $request->range_common,
                'method' => $request->method,
                'sort_order' => $request->sort_order ?? 0,
                'status' => $request->status,
                'updated_by' => Auth::id(),
            ]);

            $auditService->logUpdate(
                self::ANALYTE_MODULE_CODE,
                $analyte,
                $oldData,
                $analyte->only($analyte->getFillable()),
                'Analyte updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Analyte updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update analyte.'
            ], 500);
        }
    }

    public function analyteDestroy($id, AuditService $auditService)
    {
        $analyte = TestAnalyte::findOrFail($id);

        if (TestResultEntry::where('analyte_id', $id)->exists()) {

            return response()->json([
                'status' => false,
                'message' => 'This analyte cannot be deleted. Results have already been recorded against it.'
            ]);
        }

        try {

            $oldData = $analyte->only($analyte->getFillable());

            $analyte->delete();

            $auditService->logDelete(
                self::ANALYTE_MODULE_CODE,
                $analyte,
                $oldData,
                'Analyte deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Analyte deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete analyte.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MANAGE SUB GROUPS (clubs several existing analyte groups together,
    | e.g. Sub Group "Liver Panel" = analyte groups A + B). One test can
    | define several sub-groups.
    |--------------------------------------------------------------------------
    */

    public function subGroupList($invoiceItemDetailId)
    {
        $subGroups = TestSubGroup::where('invoice_item_detail_id', $invoiceItemDetailId)
            ->with('members')
            ->orderBy('sort_order')
            ->get();

        $availableGroups = TestAnalyte::where('invoice_item_detail_id', $invoiceItemDetailId)
            ->whereNotNull('group_name')
            ->where('group_name', '!=', '')
            ->distinct()
            ->orderBy('group_name')
            ->pluck('group_name');

        return response()->json([
            'status' => true,
            'data' => $subGroups,
            'available_groups' => $availableGroups,
        ]);
    }

    public function subGroupStore(Request $request, $invoiceItemDetailId, AuditService $auditService)
    {
        InvoiceItemDetail::findOrFail($invoiceItemDetailId);

        $request->validate([
            'name' => 'required|max:150',
            'group_names' => 'required|array|min:1',
            'group_names.*' => 'required|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $duplicate = TestSubGroup::where('invoice_item_detail_id', $invoiceItemDetailId)
            ->whereRaw('UPPER(name) = ?', [strtoupper(trim($request->name))])
            ->exists();

        if ($duplicate) {

            return response()->json([
                'status' => false,
                'message' => 'A sub group with this name already exists for this test.'
            ]);
        }

        DB::beginTransaction();

        try {

            $subGroup = TestSubGroup::create([

                'invoice_item_detail_id' => $invoiceItemDetailId,
                'name' => trim($request->name),
                'sort_order' => $request->sort_order ?? 0,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            foreach (array_unique($request->group_names) as $groupName) {

                TestSubGroupMember::create([
                    'sub_group_id' => $subGroup->id,
                    'group_name' => $groupName,
                ]);
            }

            DB::commit();

            $auditService->logCreate(
                self::SUB_GROUP_MODULE_CODE,
                $subGroup,
                $subGroup->only($subGroup->getFillable()),
                'Test sub group created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Sub group added successfully.',
                'data' => $subGroup->load('members'),
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to add sub group.'
            ], 500);
        }
    }

    public function subGroupUpdate(Request $request, $id, AuditService $auditService)
    {
        $subGroup = TestSubGroup::findOrFail($id);

        $request->validate([
            'name' => 'required|max:150',
            'group_names' => 'required|array|min:1',
            'group_names.*' => 'required|max:100',
            'sort_order' => 'nullable|integer',
        ]);

        $duplicate = TestSubGroup::where('invoice_item_detail_id', $subGroup->invoice_item_detail_id)
            ->whereRaw('UPPER(name) = ?', [strtoupper(trim($request->name))])
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {

            return response()->json([
                'status' => false,
                'message' => 'A sub group with this name already exists for this test.'
            ]);
        }

        $oldData = $subGroup->only($subGroup->getFillable());

        DB::beginTransaction();

        try {

            $subGroup->update([

                'name' => trim($request->name),
                'sort_order' => $request->sort_order ?? 0,
                'updated_by' => Auth::id(),
            ]);

            TestSubGroupMember::where('sub_group_id', $subGroup->id)->delete();

            foreach (array_unique($request->group_names) as $groupName) {

                TestSubGroupMember::create([
                    'sub_group_id' => $subGroup->id,
                    'group_name' => $groupName,
                ]);
            }

            DB::commit();

            $auditService->logUpdate(
                self::SUB_GROUP_MODULE_CODE,
                $subGroup,
                $oldData,
                $subGroup->only($subGroup->getFillable()),
                'Test sub group updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Sub group updated successfully.',
                'data' => $subGroup->load('members'),
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update sub group.'
            ], 500);
        }
    }

    public function subGroupDestroy($id, AuditService $auditService)
    {
        try {

            $subGroup = TestSubGroup::findOrFail($id);

            $oldData = $subGroup->only($subGroup->getFillable());

            TestSubGroupMember::where('sub_group_id', $id)->delete();

            $subGroup->delete();

            $auditService->logDelete(
                self::SUB_GROUP_MODULE_CODE,
                $subGroup,
                $oldData,
                'Test sub group deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Sub group deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete sub group.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | COPY ANALYTES & SUB GROUPS FROM ANOTHER TEST (avoids re-entering the
    | same panel structure by hand for a similar/duplicate test)
    |--------------------------------------------------------------------------
    */

    public function copyAnalytes(Request $request, $targetInvoiceItemDetailId)
    {
        $request->validate([
            'source_invoice_item_detail_id' => 'required|integer|exists:invoice_item_details,id',
        ]);

        $sourceId = (int) $request->source_invoice_item_detail_id;
        $targetId = (int) $targetInvoiceItemDetailId;

        if ($sourceId === $targetId) {

            return response()->json([
                'status' => false,
                'message' => 'Source and target must be different tests.'
            ]);
        }

        InvoiceItemDetail::findOrFail($targetId);

        DB::beginTransaction();

        try {

            $sourceAnalytes = TestAnalyte::where('invoice_item_detail_id', $sourceId)
                ->where('status', 'ACTIVE')
                ->orderBy('sort_order')
                ->get();

            $existingNames = TestAnalyte::where('invoice_item_detail_id', $targetId)
                ->get()
                ->map(fn ($a) => strtoupper(trim($a->analyte_name)))
                ->all();

            $analytesCopied = 0;
            $analytesSkipped = 0;

            foreach ($sourceAnalytes as $sourceAnalyte) {

                if (in_array(strtoupper(trim($sourceAnalyte->analyte_name)), $existingNames, true)) {
                    $analytesSkipped++;
                    continue;
                }

                TestAnalyte::create([

                    'invoice_item_detail_id' => $targetId,
                    'analyte_name' => $sourceAnalyte->analyte_name,
                    'group_name' => $sourceAnalyte->group_name,
                    'uom' => $sourceAnalyte->uom,
                    'range_male' => $sourceAnalyte->range_male,
                    'range_female' => $sourceAnalyte->range_female,
                    'range_common' => $sourceAnalyte->range_common,
                    'method' => $sourceAnalyte->method,
                    'sort_order' => $sourceAnalyte->sort_order,
                    'status' => 'ACTIVE',
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                $existingNames[] = strtoupper(trim($sourceAnalyte->analyte_name));
                $analytesCopied++;
            }

            $sourceSubGroups = TestSubGroup::where('invoice_item_detail_id', $sourceId)
                ->with('members')
                ->orderBy('sort_order')
                ->get();

            $existingSubGroupNames = TestSubGroup::where('invoice_item_detail_id', $targetId)
                ->get()
                ->map(fn ($sg) => strtoupper(trim($sg->name)))
                ->all();

            $subGroupsCopied = 0;
            $subGroupsSkipped = 0;

            foreach ($sourceSubGroups as $sourceSubGroup) {

                if (in_array(strtoupper(trim($sourceSubGroup->name)), $existingSubGroupNames, true)) {
                    $subGroupsSkipped++;
                    continue;
                }

                $newSubGroup = TestSubGroup::create([

                    'invoice_item_detail_id' => $targetId,
                    'name' => $sourceSubGroup->name,
                    'sort_order' => $sourceSubGroup->sort_order,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                foreach ($sourceSubGroup->members as $member) {

                    TestSubGroupMember::create([
                        'sub_group_id' => $newSubGroup->id,
                        'group_name' => $member->group_name,
                    ]);
                }

                $subGroupsCopied++;
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => "Copied {$analytesCopied} analyte(s)" .
                    ($analytesSkipped ? " ({$analytesSkipped} skipped as duplicate)" : '') .
                    " and {$subGroupsCopied} sub group(s)" .
                    ($subGroupsSkipped ? " ({$subGroupsSkipped} skipped as duplicate)" : '') . '.',
                'analytes_copied' => $analytesCopied,
                'analytes_skipped' => $analytesSkipped,
                'sub_groups_copied' => $subGroupsCopied,
                'sub_groups_skipped' => $subGroupsSkipped,
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to copy analytes.'
            ], 500);
        }
    }
}
