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

        if ($request->filled('item_code')) {
            $query->where('invoice_item_details.item_code', $request->item_code);
        }

        if ($request->filled('is_package')) {
            $query->where('invoice_item_details.is_package', $request->boolean('is_package'));
        }

        if ($request->filled('is_outsourced')) {
            $query->where('invoice_item_details.is_outsourced', $request->boolean('is_outsourced'));
        }

        if ($request->filled('is_report_not_required')) {
            $query->where('invoice_item_details.is_report_not_required', $request->boolean('is_report_not_required'));
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

            ->addColumn('patient_friendly_description_display', function ($row) {

                // Blank when not set -- the fallback to item_description_sub
                // (see InvoiceItemDetail::effectivePatientFriendlyDescription())
                // only matters wherever this is actually shown to a patient;
                // this column reflects what was literally typed in, nothing
                // more.
                return e((string) $row->patient_friendly_description);

            })

            ->addColumn('discount_summary', function ($row) {

                return '<button type="button" class="btn btn-sm btn-outline-primary view-discount"'
                    . ' data-desc="' . e($row->item_description_sub) . '"'
                    . ' data-standard="' . number_format((float) $row->discount_percent, 2) . '"'
                    . ' data-member="' . number_format((float) $row->member_discount, 2) . '"'
                    . ' data-other-lab="' . number_format((float) $row->discount_other_lab, 2) . '"'
                    . ' data-member-other-lab="' . number_format((float) $row->discount_member_other_lab, 2) . '"'
                    . ' data-senior="' . number_format((float) $row->discount_senior_citizen, 2) . '">'
                    . '<i class="ri-percent-line"></i> View</button>';

            })

            ->addColumn('range_summary', function ($row) {

                if (
                    trim((string) $row->range_male) === ''
                    && trim((string) $row->range_female) === ''
                    && trim((string) $row->range_common) === ''
                ) {
                    return '<button type="button" class="btn btn-sm btn-outline-secondary edit-item" data-id="' . $row->id . '">'
                        . '<i class="ri-add-line"></i> Add Range</button>';
                }

                return '<button type="button" class="btn btn-sm btn-outline-primary view-range"'
                    . ' data-desc="' . e($row->item_description_sub) . '"'
                    . ' data-male="' . e($row->range_male ?? '-') . '"'
                    . ' data-female="' . e($row->range_female ?? '-') . '"'
                    . ' data-common="' . e($row->range_common ?? '-') . '">'
                    . '<i class="ri-ruler-line"></i> View</button>';

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

            ->addColumn('is_report_not_required_label', function ($row) {

                return $row->is_report_not_required
                    ? '<span class="badge bg-secondary">Report Not Required</span>'
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
                'patient_friendly_description_display',
                'discount_summary',
                'range_summary',
                'is_package_label',
                'is_outsourced_label',
                'is_report_not_required_label',
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

            'patient_friendly_description' => 'nullable|string',

            'rate' => 'nullable|numeric',

            'standard_discount' => 'nullable|numeric',

            'member_discount' => 'nullable|numeric',

            'other_lab_discount' => 'nullable|numeric',

            'member_other_lab_discount' => 'nullable|numeric',

            'senior_citizen_discount' => 'nullable|numeric|min:0|max:100',

            'report_days' => 'nullable|integer',

            'test_group_code' => 'nullable|exists:test_group_masters,id',

            'is_package' => 'nullable|boolean',

            'is_outsourced' => 'nullable|boolean',

            'is_report_not_required' => 'nullable|boolean',

            'components' => 'nullable|array',

            'components.*' => 'exists:invoice_item_details,id',

        ]);

        $validator->after(fn ($v) => $this->validateUomRangePair($v, $request));

        if ($validator->fails()) {

            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);

        }

        // Creating a NEW package is Admin-only (editing an existing one is
        // unchanged) -- a package bills its components instead of itself,
        // so getting this wrong has real billing consequences.
        if ($request->boolean('is_package') && ($denied = $this->denyUnlessAdmin('Only an Admin can create a package.'))) {
            return $denied;
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

                // Optional -- left null when blank rather than copying
                // item_description_sub in at save time, so a later bulk
                // re-seed (or a change to item_description_sub itself)
                // never has to guess which rows were "really" filled in
                // vs defaulted. Wherever this needs to be shown, fall back
                // to item_description_sub there (see
                // InvoiceItemDetail::effectivePatientFriendlyDescription()).
                'patient_friendly_description' => $request->filled('patient_friendly_description')
                    ? $request->patient_friendly_description
                    : null,

                'is_package' => $isPackage,

                'is_outsourced' => $request->boolean('is_outsourced'),

                'is_report_not_required' => $request->boolean('is_report_not_required'),

                'rate' => $request->rate,

                'discount_percent' => $request->standard_discount,

                'member_discount' => $request->member_discount,

                'discount_other_lab' => $request->other_lab_discount,

                'discount_member_other_lab' => $request->member_other_lab_discount,

                'discount_senior_citizen' => $request->senior_citizen_discount ?: 0,

                'test_group_code' => $request->test_group_code,

                'uom' => $request->uom,

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

        $this->syncKeyParameter($item, $request);

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

        // Key Parameter tracking config (KeyParameterService) for this item,
        // if any -- lets the edit form show/prefill it instead of needing a
        // separate screen. Keyed by item_code_sub, same identity the
        // key_parameters table uses.
        // An item can be tracked under several groups (Key Parameter
        // Master); this form edits only its original (oldest) row.
        $item->key_parameter = DB::table('key_parameters')
            ->where('item_code_sub', $item->item_code_sub)
            ->orderBy('id')
            ->first();

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

                'patient_friendly_description' => 'nullable|string',

                'test_group_code' => 'nullable|exists:test_group_masters,id',

                'is_package' => 'nullable|boolean',

                'is_outsourced' => 'nullable|boolean',

                'is_report_not_required' => 'nullable|boolean',

                'components' => 'nullable|array',

                'components.*' => 'exists:invoice_item_details,id',

                'kp_enabled' => 'nullable|boolean',

                'kp_parameter_name' => 'required_if:kp_enabled,1|nullable|max:150',

                'kp_group_label' => 'nullable|max:100',

                'kp_uom' => 'nullable|max:50',

                'kp_sort_order' => 'nullable|integer',

                'kp_trigger_item_code_subs' => 'nullable|string|max:255',

            ]
        );

        $validator->after(fn ($v) => $this->validateUomRangePair($v, $request));

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

            // Optional -- left null when blank, same reasoning as store().
            'patient_friendly_description' => $request->filled('patient_friendly_description')
                ? $request->patient_friendly_description
                : null,

            'is_package' => $isPackage,

            'is_outsourced' => $request->boolean('is_outsourced'),

            'is_report_not_required' => $request->boolean('is_report_not_required'),

            'rate' => $request->rate,

            'discount_percent' => $request->standard_discount,

            'member_discount' => $request->member_discount,

            'discount_other_lab' => $request->other_lab_discount,

            'discount_member_other_lab' => $request->member_other_lab_discount,

            'discount_senior_citizen' => $request->senior_citizen_discount ?: 0,

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

        $this->syncKeyParameter($item, $request);

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
     * UOM and Range are meant to travel together -- a UOM with no range to
     * interpret it against is meaningless, and a range with no UOM is
     * ambiguous. Skipped entirely for a package: those fields don't apply to
     * a package line at all (it bills its components, not itself -- see
     * #testDetailsWrap in the view, hidden whenever is_package is checked).
     */
    private function validateUomRangePair($validator, Request $request): void
    {
        if ($request->boolean('is_package')) {
            return;
        }

        $hasUom = trim((string) $request->uom) !== '';

        $hasRange = trim((string) $request->male_range) !== ''
            || trim((string) $request->female_range) !== ''
            || trim((string) $request->common_range) !== '';

        if ($hasUom && !$hasRange) {
            $validator->errors()->add(
                'uom',
                'Enter at least one Range (Male, Female, or Common) when a UOM is entered.'
            );
        }

        if ($hasRange && !$hasUom) {
            $validator->errors()->add(
                'uom',
                'Enter a UOM when a Range value is entered.'
            );
        }
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
     * Enable/update or disable this item's Key Parameter tracking
     * (KeyParameterService) from the item edit form, instead of needing the
     * separate pathology template editor or a direct DB row. Keyed by this
     * item's own item_code_sub -- disabling never deletes the row (a
     * template may already have an "Insert Result Field" marker anchored to
     * it), it just flips is_active off the same way the admin-curated list
     * is meant to be deactivated elsewhere.
     *
     * Policy: any item with a UOM (a numeric result worth tracking) is a Key
     * Parameter BY DEFAULT -- an admin who never opens the Key Parameter
     * section still gets tracking for free the first time this item is
     * saved with a UOM. The explicit kp_enabled checkbox only matters (a)
     * the first time, for an item with NO uom that the admin wants tracked
     * anyway, or (b) to opt back OUT of the default once a row already
     * exists. A row that already exists is never silently re-activated or
     * re-defaulted here -- once an admin has made an explicit choice for an
     * item (on a prior save), that choice stands until they change it again.
     */
    private function syncKeyParameter(InvoiceItemDetail $item, Request $request): void
    {
        // Oldest row only -- the same one show() loads into the form; rows
        // added under other groups in Key Parameter Master are left alone.
        $existing = DB::table('key_parameters')->where('item_code_sub', $item->item_code_sub)->orderBy('id')->first();

        $wantsTracking = $request->boolean('kp_enabled')
            || (!$existing && trim((string) $item->uom) !== '');

        if (!$wantsTracking) {

            if ($existing) {
                DB::table('key_parameters')->where('id', $existing->id)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $triggers = array_values(array_filter(array_map(
            'trim',
            explode(',', $request->kp_trigger_item_code_subs ?: $item->item_code_sub)
        )));

        if (empty($triggers)) {
            $triggers = [$item->item_code_sub];
        }

        $values = [
            'parameter_name' => $request->kp_parameter_name ?: $item->item_description_sub,
            'group_label' => $request->kp_group_label ?: null,
            'uom' => $request->kp_uom ?: $item->uom,
            'trigger_item_code_subs' => json_encode($triggers),
            'sort_order' => $request->kp_sort_order ?? 0,
            'is_active' => true,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('key_parameters')->where('id', $existing->id)->update($values);
        } else {
            DB::table('key_parameters')->insert(array_merge(
                ['item_code_sub' => $item->item_code_sub, 'created_at' => now()],
                $values
            ));
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
                'invoice_item_details.discount_senior_citizen',
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
                'Only Senior Citizen',
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
                    $row->discount_senior_citizen,
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
