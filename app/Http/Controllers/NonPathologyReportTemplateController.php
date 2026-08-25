<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemDetail;
use App\Models\InvoiceItemMaster;
use App\Models\NonPathologyReportTemplate;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Admin CRUD for Non-Pathology report templates -- mirrors
 * UsgReportTemplateController exactly (single-item scoped, three narrative
 * fields), for every Non-Pathology category with no parameter grid (X-Ray,
 * Endoscopy, EMG-NCV, Dental, EYE, Miscellaneous, etc; see
 * NonPathologyReportController for the same qualifying-item-code
 * definition this mirrors).
 */
class NonPathologyReportTemplateController extends Controller
{
    private const MODULE_CODE = 'NON_PATHOLOGY_REPORT_TEMPLATE';

    private function qualifyingItemCodes(): array
    {
        return InvoiceItemMaster::where('test_parameter_required', '!=', 'YES')
            ->whereNotIn('item_code', ['USG001', 'DOC001'])
            ->pluck('item_code')
            ->toArray();
    }

    public function index()
    {
        $items = InvoiceItemDetail::whereIn('item_code', $this->qualifyingItemCodes())
            ->whereNotIn('item_code_sub', \App\Support\CardiologyReportFields::ITEM_CODE_SUBS)
            ->where('status', 'Y')
            ->orderBy('item_description_sub')
            ->get(['item_code_sub', 'item_description_sub']);

        return view('apps-non-pathology-report-template', compact('items'));
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = NonPathologyReportTemplate::with(['creator', 'updater']);

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('item_code_sub', 'like', "%{$search}%");
            });
        }

        $templates = $query
            ->orderBy('item_code_sub')
            ->orderBy('title')
            ->paginate($perPage);

        $itemCodeSubs = collect($templates->items())->pluck('item_code_sub')->unique();

        $itemNames = InvoiceItemDetail::whereIn('item_code_sub', $itemCodeSubs)
            ->pluck('item_description_sub', 'item_code_sub');

        $templates->getCollection()->transform(function ($row) use ($itemNames) {

            $row->item_name = $itemNames->get($row->item_code_sub);

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

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'title' => 'required|max:150',
            'item_code_sub' => 'required|exists:invoice_item_details,item_code_sub',
            'clinical_history' => 'nullable|string',
            'findings' => 'nullable|string',
            'impression' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        DB::beginTransaction();

        try {

            $template = NonPathologyReportTemplate::create([

                'title' => trim($request->title),
                'item_code_sub' => $request->item_code_sub,

                'clinical_history' => HtmlSanitizerService::sanitizeClinicalText($request->clinical_history),
                'findings' => HtmlSanitizerService::sanitizeClinicalText($request->findings),
                'impression' => HtmlSanitizerService::sanitizeClinicalText($request->impression),

                'status' => $request->status,

                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $template,
                $template->only($template->getFillable()),
                'Non-Pathology Report Template created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Non-Pathology Report Template created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Non-Pathology Report Template.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $template = NonPathologyReportTemplate::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $template
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'title' => 'required|max:150',
            'item_code_sub' => 'required|exists:invoice_item_details,item_code_sub',
            'clinical_history' => 'nullable|string',
            'findings' => 'nullable|string',
            'impression' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        DB::beginTransaction();

        try {

            $template = NonPathologyReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->update([

                'title' => trim($request->title),
                'item_code_sub' => $request->item_code_sub,

                'clinical_history' => HtmlSanitizerService::sanitizeClinicalText($request->clinical_history),
                'findings' => HtmlSanitizerService::sanitizeClinicalText($request->findings),
                'impression' => HtmlSanitizerService::sanitizeClinicalText($request->impression),

                'status' => $request->status,

                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $template,
                $oldData,
                $template->only($template->getFillable()),
                'Non-Pathology Report Template updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Non-Pathology Report Template updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Non-Pathology Report Template.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $template = NonPathologyReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $template,
                $oldData,
                'Non-Pathology Report Template deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Non-Pathology Report Template deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Non-Pathology Report Template.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FOR TEST -- lightweight lookup used by the Non-Pathology report card's
    | template picker.
    |--------------------------------------------------------------------------
    */

    public function forTest($itemCodeSub)
    {
        $templates = NonPathologyReportTemplate::where('item_code_sub', $itemCodeSub)
            ->where('status', 'ACTIVE')
            ->orderBy('title')
            ->get(['id', 'title', 'clinical_history', 'findings', 'impression']);

        return response()->json([
            'status' => true,
            'data' => $templates
        ]);
    }
}
