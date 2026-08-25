<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\PathologyReportTemplate;
use App\Models\PathologyReportTemplateItem;
use App\Models\TestGroupMaster;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * Admin CRUD for the narrative Pathology report templates that replace the
 * old structured analyte-grid entry (see TestResultEntryController /
 * TestReportRowBuilder, both untouched -- historical confirmed invoices
 * keep working through them). A template can cover one Pathology sub-item
 * or several bundled together (a panel) -- see PathologyReportController,
 * which uses pathology_report_template_items to decide which templates are
 * "available" for a given invoice.
 *
 * Organized by test_group_masters (Hematology/Biochemistry/etc) via a
 * dynamic tab strip on the frontend, plus a null "Ungrouped/General"
 * bucket -- test_group_code is sparsely populated today (see
 * invoice_item_details.test_group_code), so most items fall in that bucket
 * until an admin assigns them a group via the existing Test Parameter
 * screen.
 */
class PathologyReportTemplateController extends Controller
{
    private const MODULE_CODE = 'PATHOLOGY_REPORT_TEMPLATE';
    private const ITEM_CODE = 'PAT001';

    public function index()
    {
        $testGroups = TestGroupMaster::where('status', 'ACTIVE')
            ->orderBy('test_group_name')
            ->get(['id', 'test_group_name']);

        return view('apps-pathology-report-template', compact('testGroups'));
    }

    /*
    |--------------------------------------------------------------------------
    | ITEMS FOR GROUP -- populates the item multi-select when the admin
    | picks a test-group tab / dropdown on the Add Template form. Pass
    | test_group_code=0 (or omit it) for the "Ungrouped/General" bucket.
    |--------------------------------------------------------------------------
    */

    public function itemsForGroup(Request $request)
    {
        $testGroupCode = $request->get('test_group_code');

        $query = InvoiceItemDetail::where('item_code', self::ITEM_CODE)
            ->where('status', 'Y');

        if ($testGroupCode) {
            $query->where('test_group_code', $testGroupCode);
        } else {
            $query->whereNull('test_group_code');
        }

        $items = $query->orderBy('item_description_sub')
            ->get(['item_code_sub', 'item_description_sub']);

        return response()->json([
            'status' => true,
            'data' => $items,
        ]);
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = PathologyReportTemplate::with(['items', 'creator', 'updater']);

        $testGroupCode = $request->get('test_group_code');

        if ($request->filled('test_group_code')) {
            $query->where('test_group_code', $testGroupCode);
        } elseif ($request->get('ungrouped') === '1') {
            $query->whereNull('test_group_code');
        }

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where('title', 'like', "%{$search}%");
        }

        $templates = $query
            ->orderBy('title')
            ->paginate($perPage);

        $itemCodeSubs = $templates->getCollection()
            ->flatMap(fn ($t) => $t->items->pluck('item_code_sub'))
            ->unique();

        $itemNames = InvoiceItemDetail::whereIn('item_code_sub', $itemCodeSubs)
            ->pluck('item_description_sub', 'item_code_sub');

        $templates->getCollection()->transform(function ($row) use ($itemNames) {

            $row->item_code_subs = $row->items->pluck('item_code_sub')->values();

            $row->item_names = $row->items
                ->map(fn ($i) => $itemNames->get($i->item_code_sub, $i->item_code_sub))
                ->implode(', ');

            $row->created_dt = optional($row->created_at)->format('d-m-Y H:i');
            $row->updated_dt = optional($row->updated_at)->format('d-m-Y H:i');

            $row->created_by_name = optional($row->creator)->name;
            $row->updated_by_name = optional($row->updater)->name;

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $templates->items(),
            'pagination' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'total' => $templates->total()
            ]
        ]);
    }

    private function validateTemplate(Request $request): array
    {
        $validator = Validator::make(
            $request->all(),
            [
                'title' => 'required|max:150',
                'test_group_code' => 'nullable|exists:test_group_masters,id',
                'item_code_subs' => 'required|array|min:1',
                'item_code_subs.*' => 'required|distinct|exists:invoice_item_details,item_code_sub',
                'content' => 'nullable|string',
                'status' => 'required|in:ACTIVE,INACTIVE',
            ],
            [],
            []
        );

        $validator->after(function ($validator) use ($request) {

            $itemCodeSubs = $request->item_code_subs ?? [];

            if (empty($itemCodeSubs)) {
                return;
            }

            // Keeps the per-group page organization coherent -- every item
            // a template bundles must actually belong to the same group
            // (or all be ungrouped) that the template itself is filed
            // under. The Add Template form already scopes its item picker
            // to one group at a time, so this only fires on tampering.
            $mismatched = InvoiceItemDetail::where('item_code', self::ITEM_CODE)
                ->whereIn('item_code_sub', $itemCodeSubs)
                ->where(function ($q) use ($request) {
                    if ($request->test_group_code) {
                        $q->whereNull('test_group_code')
                            ->orWhere('test_group_code', '!=', $request->test_group_code);
                    } else {
                        $q->whereNotNull('test_group_code');
                    }
                })
                ->exists();

            if ($mismatched) {
                $validator->errors()->add('item_code_subs', 'All selected items must belong to the chosen test group.');
            }
        });

        return $validator->validate();
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateTemplate($request);

        DB::beginTransaction();

        try {

            $template = PathologyReportTemplate::create([

                'title' => trim($data['title']),
                'test_group_code' => $data['test_group_code'] ?? null,
                'content' => HtmlSanitizerService::sanitizeClinicalText($data['content'] ?? null),
                'status' => $data['status'],

                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($template, $data['item_code_subs']);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $template,
                array_merge($template->only($template->getFillable()), ['item_code_subs' => $data['item_code_subs']]),
                'Pathology Report Template created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Pathology Report Template created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Pathology Report Template.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $template = PathologyReportTemplate::with('items')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => array_merge(
                $template->toArray(),
                ['item_code_subs' => $template->items->pluck('item_code_sub')->values()]
            ),
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $data = $this->validateTemplate($request);

        DB::beginTransaction();

        try {

            $template = PathologyReportTemplate::findOrFail($id);

            $oldData = array_merge(
                $template->only($template->getFillable()),
                ['item_code_subs' => $template->items()->pluck('item_code_sub')]
            );

            $template->update([

                'title' => trim($data['title']),
                'test_group_code' => $data['test_group_code'] ?? null,
                'content' => HtmlSanitizerService::sanitizeClinicalText($data['content'] ?? null),
                'status' => $data['status'],

                'updated_by' => Auth::id(),
            ]);

            $this->syncItems($template, $data['item_code_subs']);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $template,
                $oldData,
                array_merge($template->only($template->getFillable()), ['item_code_subs' => $data['item_code_subs']]),
                'Pathology Report Template updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Pathology Report Template updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Pathology Report Template.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $template = PathologyReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            PathologyReportTemplateItem::where('pathology_report_template_id', $template->id)->delete();

            $template->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $template,
                $oldData,
                'Pathology Report Template deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Pathology Report Template deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Pathology Report Template.'
            ], 500);
        }
    }

    private function syncItems(PathologyReportTemplate $template, array $itemCodeSubs): void
    {
        PathologyReportTemplateItem::where('pathology_report_template_id', $template->id)->delete();

        foreach (array_unique($itemCodeSubs) as $itemCodeSub) {

            PathologyReportTemplateItem::create([
                'pathology_report_template_id' => $template->id,
                'item_code_sub' => $itemCodeSub,
            ]);
        }
    }
}
