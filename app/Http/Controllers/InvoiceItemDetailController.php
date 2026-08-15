<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\TestGroupMaster;
use App\Models\TestPackageComponent;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;
use App\Exports\InvoiceItemDetailExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceItemDetailController extends Controller
{
    private const MODULE_CODE = 'INVOICE_ITEM_DETAIL';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $itemMasters = InvoiceItemMaster::where('status', 1)
            ->orderBy('item_code')
            ->get();

        $testGroups = TestGroupMaster::where('status', 'ACTIVE')
            ->orderBy('test_group_name')
            ->get();

        return view(
            'apps-ecommerce-invoice-item-details',
            compact('itemMasters', 'testGroups')
        );
    }

    public function list(Request $request)
    {
        $query = InvoiceItemDetail::leftJoin(
            'invoice_item_masters',
            'invoice_item_details.item_code',
            '=',
            'invoice_item_masters.item_code'
        )
            ->leftJoin(
                'test_group_masters',
                'invoice_item_details.test_group_code',
                '=',
                'test_group_masters.id'
            )
            ->select(

                'invoice_item_details.*',

                'invoice_item_masters.item_name',

                'invoice_item_masters.item_type',

                'test_group_masters.test_group_name'

            );

        if ($request->boolean('packages_only')) {
            $query->where('invoice_item_details.is_package', 1);
        }

        return DataTables::of($query)

            ->addIndexColumn()

            ->editColumn('rate', function ($row) {

                return number_format($row->rate, 2);

            })

            ->editColumn('status', function ($row) {

                return $row->status === 'Y'
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>';

            })

            ->addColumn('is_package_label', function ($row) {

                return $row->is_package
                    ? '<button type="button" class="btn btn-sm btn-info edit-item" data-id="' . $row->id . '">Package</button>'
                    : '';

            })

            ->addColumn('is_outsourced_label', function ($row) {

                return $row->is_outsourced
                    ? '<span class="badge bg-warning text-dark">Outsourced</span>'
                    : '';

            })

            ->addColumn('action', function ($row) {

                return '

                        <button class="btn btn-sm btn-primary edit-item" data-id="' . $row->id . '">

                        <i class="ri-pencil-line"></i>

                        </button>

                        <button class="btn btn-sm btn-danger delete-item" data-id="' . $row->id . '">

                        <i class="ri-delete-bin-line"></i>

                        </button>

                        ';

            })

            ->rawColumns([
                'status',
                'is_package_label',
                'is_outsourced_label',
                'action'
            ])

            ->make(true);
    }
    /**
     * Candidate component tests for a package: other, non-package tests
     * under the same item_code (category), excluding the item itself.
     */
    public function componentCandidates($item_code, $excludeId = null)
    {
        $query = InvoiceItemDetail::where('item_code', $item_code)
            ->where('is_package', 0);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $items = $query->orderBy('item_description_sub')
            ->get(['id', 'item_code_sub', 'item_description_sub']);

        return response()->json([
            'status' => true,
            'items' => $items,
        ]);
    }

    public function getItemMaster($item_code)
    {
        $item = InvoiceItemMaster::where('item_code', $item_code)->first();

        if (!$item) {

            return response()->json([
                'status' => false
            ]);

        }

        return response()->json([

            'status' => true,

            'item_name' => $item->item_name,

            'item_type' => $item->item_type,

            'item_code_sub' => $this->generateSubItemCode($item_code)

        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    private function generateSubItemCode(string $itemCode): string
    {
        $last = InvoiceItemDetail::where('item_code', $itemCode)
            ->orderByDesc('item_code_sub')
            ->first();

        if (!$last) {
            // First detail: DOC001 -> DOC002
            $number = (int) substr($itemCode, -3) + 1;
        } else {
            // Next detail: DOC002 -> DOC003
            $number = (int) substr($last->item_code_sub, -3) + 1;
        }

        $prefix = substr($itemCode, 0, -3);

        return $prefix . str_pad($number, 3, '0', STR_PAD_LEFT);
    }


    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make($request->all(), [

            'item_code' => 'required|exists:invoice_item_masters,item_code',

            'item_description_sub' => 'required|max:255',

            'rate' => 'nullable|numeric',

            'standard_discount' => 'nullable|numeric',

            'member_discount' => 'nullable|numeric',

            'other_lab_discount' => 'nullable|numeric',

            'member_other_lab_discount' => 'nullable|numeric',

            'report_days' => 'nullable|integer',

            'test_group_code' => 'nullable|exists:test_group_masters,id',

            'is_package' => 'nullable|boolean',

            'is_outsourced' => 'nullable|boolean',

            'components' => 'nullable|array',

            'components.*' => 'exists:invoice_item_details,id',

        ]);

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);

        }
        if (
            InvoiceItemDetail::where('item_code', $request->item_code)
                ->where('item_description_sub', $request->item_description_sub)
                ->exists()
        ) {

            return response()->json([

                'status' => false,

                'errors' => [

                    'item_description_sub' => [

                        'This description already exists for the selected Item Code.'

                    ]

                ]

            ]);

        }

        $isPackage = $request->boolean('is_package');

        $item = null;

        DB::transaction(function () use ($request, $isPackage, &$item) {

            $item = InvoiceItemDetail::create([

                'item_code' => $request->item_code,

                'item_code_sub' => $this->generateSubItemCode($request->item_code),

                'item_description_sub' => $request->item_description_sub,

                'is_package' => $isPackage,

                'is_outsourced' => $request->boolean('is_outsourced'),

                'rate' => $request->rate,

                'discount_percent' => $request->standard_discount,

                'member_discount' => $request->member_discount,

                'discount_other_lab' => $request->other_lab_discount,

                'discount_member_other_lab' => $request->member_other_lab_discount,

                'test_group_code' => $request->test_group_code,

                'range_male' => $request->male_range,

                'range_female' => $request->female_range,

                'range_common' => $request->common_range,



                'method' => $request->method,

                'report_days' => $request->report_days ?? 0,

                'status' => $request->status,
                'created_by' => auth()->id(),
                'created_dt' => now(),

            ]);

            $this->syncPackageComponents($item, $isPackage, $request->components ?? []);

        });

        $auditService->logCreate(
            self::MODULE_CODE,
            $item,
            $item->only($item->getFillable()),
            'Invoice item detail created'
        );

        return response()->json([

            'status' => true,

            'message' => 'Invoice Item Detail created successfully.'

        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $item = InvoiceItemDetail::leftJoin(
            'invoice_item_masters',
            'invoice_item_details.item_code',
            '=',
            'invoice_item_masters.item_code'
        )
            ->select(
                'invoice_item_details.*',
                'invoice_item_masters.item_name',
                'invoice_item_masters.item_type'
            )
            ->where('invoice_item_details.id', $id)
            ->firstOrFail();

        $item->component_ids = TestPackageComponent::where('package_invoice_item_detail_id', $id)
            ->where('status', 'ACTIVE')
            ->orderBy('sort_order')
            ->pluck('component_invoice_item_detail_id');

        return response()->json($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id, AuditService $auditService)
    {
        $validator = Validator::make(
            $request->all(),
            [

                'item_code' => 'required',

                'item_code_sub' => 'required|max:10|unique:invoice_item_details,item_code_sub,' . $id,

                'item_description_sub' => 'required|max:255',

                'test_group_code' => 'nullable|exists:test_group_masters,id',

                'is_package' => 'nullable|boolean',

                'is_outsourced' => 'nullable|boolean',

                'components' => 'nullable|array',

                'components.*' => 'exists:invoice_item_details,id',

            ]
        );

        if ($validator->fails()) {

            return response()->json([

                'status' => false,

                'errors' => $validator->errors()

            ]);

        }

        $item = InvoiceItemDetail::findOrFail($id);

        $oldData = $item->only($item->getFillable());

        $isPackage = $request->boolean('is_package');

        $item->update([

            'item_description_sub' => $request->item_description_sub,

            'is_package' => $isPackage,

            'is_outsourced' => $request->boolean('is_outsourced'),

            'rate' => $request->rate,

            'discount_percent' => $request->standard_discount,

            'member_discount' => $request->member_discount,

            'discount_other_lab' => $request->other_lab_discount,

            'discount_member_other_lab' => $request->member_other_lab_discount,

            'test_group_code' => $request->test_group_code,

            'uom' => $request->uom,

            'range_male' => $request->male_range,

            'range_female' => $request->female_range,

            'range_common' => $request->common_range,

            'method' => $request->method,

            'report_days' => $request->report_days ?? 0,

            'status' => $request->status,

            'update_by' => auth()->id(),
            'update_dt' => now(),

        ]);

        $this->syncPackageComponents($item, $isPackage, $request->components ?? []);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $item,
            $oldData,
            $item->only($item->getFillable()),
            'Invoice item detail updated'
        );

        return response()->json([

            'status' => true,

            'message' => 'Updated successfully.'

        ]);
    }

    /**
     * Replace a package's component links to match the submitted list.
     */
    private function syncPackageComponents(InvoiceItemDetail $item, bool $isPackage, array $componentIds): void
    {
        TestPackageComponent::where('package_invoice_item_detail_id', $item->id)->delete();

        if (!$isPackage) {
            return;
        }

        foreach (array_values($componentIds) as $index => $componentId) {

            if ((int) $componentId === (int) $item->id) {
                continue;
            }

            TestPackageComponent::create([
                'package_invoice_item_detail_id' => $item->id,
                'component_invoice_item_detail_id' => $componentId,
                'sort_order' => $index,
                'status' => 'ACTIVE',
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id, AuditService $auditService)
    {
        $item = InvoiceItemDetail::findOrFail($id);

        $oldData = $item->only($item->getFillable());

        TestPackageComponent::where('package_invoice_item_detail_id', $id)
            ->orWhere('component_invoice_item_detail_id', $id)
            ->delete();

        $item->delete();

        $auditService->logDelete(
            self::MODULE_CODE,
            $item,
            $oldData,
            'Invoice item detail deleted'
        );

        return response()->json([

            'status' => true,

            'message' => 'Deleted successfully.'

        ]);
    }

    public function excel(Request $request)
    {
        $rows = InvoiceItemDetail::leftJoin(
            'invoice_item_masters',
            'invoice_item_details.item_code',
            '=',
            'invoice_item_masters.item_code'
        )
            ->select(
                'invoice_item_details.item_code',
                'invoice_item_masters.item_name',
                'invoice_item_masters.item_type',
                'invoice_item_details.item_code_sub',
                'invoice_item_details.item_description_sub',
                'invoice_item_details.rate',
                'invoice_item_details.discount_percent',
                'invoice_item_details.member_discount',
                'invoice_item_details.discount_other_lab',
                'invoice_item_details.discount_member_other_lab',
                'invoice_item_details.status',
                'invoice_item_details.created_by',
                'invoice_item_details.created_dt',
                'invoice_item_details.update_by',
                'invoice_item_details.update_dt'
            )
            ->orderBy('invoice_item_details.item_code')
            ->orderBy('invoice_item_details.item_code_sub')
            ->get();

        $filename = 'InvoiceItemDetails_' . now()->format('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {

            $file = fopen('php://output', 'w');

            fputcsv($file, [

                'Item Code',
                'Item Name',
                'Item Type',
                'Sub Code',
                'Item Description',
                'Rate',
                'Discount %',
                'Member Discount',
                'Other Lab Discount',
                'Member + Other Lab',
                'Status',
                'Created By',
                'Created Date',
                'Updated By',
                'Updated Date'

            ]);

            foreach ($rows as $row) {

                fputcsv($file, [

                    $row->item_code,
                    $row->item_name,
                    $row->item_type,
                    $row->item_code_sub,
                    $row->item_description_sub,
                    $row->rate,
                    $row->discount_percent,
                    $row->member_discount,
                    $row->discount_other_lab,
                    $row->discount_member_other_lab,
                    $row->status === 'Y' ? 'Active' : 'Inactive',
                    $row->created_by,
                    $row->created_dt
                    ? \Carbon\Carbon::parse($row->created_dt)->format('d-m-Y H:i')
                    : '',
                    $row->update_by,
                    $row->update_dt
                    ? \Carbon\Carbon::parse($row->update_dt)->format('d-m-Y H:i')
                    : ''

                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
