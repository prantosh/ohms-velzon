<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemDetail;
use App\Models\UsgReportTemplate;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class UsgReportTemplateController extends Controller
{
    private const MODULE_CODE = 'USG_REPORT_TEMPLATE';

    public function index()
    {
        $studies = InvoiceItemDetail::where('item_code', 'USG001')
            ->where('status', 'Y')
            ->orderBy('item_description_sub')
            ->get(['item_code_sub', 'item_description_sub']);

        return view('apps-usg-report-template', compact('studies'));
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = UsgReportTemplate::with(['creator', 'updater']);

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

        $studyNames = InvoiceItemDetail::where('item_code', 'USG001')
            ->whereIn('item_code_sub', $itemCodeSubs)
            ->pluck('item_description_sub', 'item_code_sub');

        $templates->getCollection()->transform(function ($row) use ($studyNames) {

            $row->study_name = $studyNames->get($row->item_code_sub);

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

            $template = UsgReportTemplate::create([

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
                'USG Report Template created'
            );

            return response()->json([
                'status' => true,
                'message' => 'USG Report Template created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save USG Report Template.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $template = UsgReportTemplate::findOrFail($id);

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

            $template = UsgReportTemplate::findOrFail($id);

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
                'USG Report Template updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'USG Report Template updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update USG Report Template.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $template = UsgReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $template,
                $oldData,
                'USG Report Template deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'USG Report Template deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete USG Report Template.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FOR STUDY -- lightweight lookup used by the USG report entry screen's
    | template picker (separate from the paginated admin list() above).
    |--------------------------------------------------------------------------
    */

    public function forStudy($itemCodeSub)
    {
        $templates = UsgReportTemplate::where('item_code_sub', $itemCodeSub)
            ->where('status', 'ACTIVE')
            ->orderBy('title')
            ->get(['id', 'title', 'clinical_history', 'findings', 'impression']);

        return response()->json([
            'status' => true,
            'data' => $templates
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ALL -- lightweight, unpaginated listing of every template (any status,
    | any study) used to populate the "Copy from existing template" picker
    | in the Add Template modal. Separate from the paginated admin list()
    | above for the same reason forStudy() is.
    |--------------------------------------------------------------------------
    */

    public function all()
    {
        $templates = UsgReportTemplate::orderBy('item_code_sub')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code_sub', 'status']);

        $studyNames = InvoiceItemDetail::where('item_code', 'USG001')
            ->whereIn('item_code_sub', $templates->pluck('item_code_sub')->unique())
            ->pluck('item_description_sub', 'item_code_sub');

        $data = $templates->map(function ($template) use ($studyNames) {

            return [
                'id' => $template->id,
                'title' => $template->title,
                'study_name' => $studyNames->get($template->item_code_sub, $template->item_code_sub),
                'status' => $template->status,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }
}
