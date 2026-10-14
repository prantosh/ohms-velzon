<?php

namespace App\Http\Controllers;

use App\Models\CardiologyReportTemplate;
use App\Models\InvoiceItemDetail;
use App\Services\AuditService;
use App\Services\HtmlSanitizerService;
use App\Support\CardiologyReportFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Mirrors UsgReportTemplateController exactly, generalized from 3
 * hand-listed fields to the 16 in CardiologyReportFields::FIELDS, and
 * scoped to only the 6 real Echo sub-items of CRD001 (not the whole
 * Cardiology item_code -- ECG/Holter/ABPM/Sleep Study don't fit this
 * structured layout and stay on the generic Non-Pathology system).
 */
class CardiologyReportTemplateController extends Controller
{
    private const MODULE_CODE = 'CARDIOLOGY_REPORT_TEMPLATE';

    public function index()
    {
        $studies = InvoiceItemDetail::whereIn('item_code_sub', CardiologyReportFields::ITEM_CODE_SUBS)
            ->where('status', 'Y')
            ->orderBy('item_description_sub')
            ->get(['item_code_sub', 'item_description_sub']);

        return view('apps-cardiology-report-template', [
            'studies' => $studies,
            'fields' => CardiologyReportFields::FIELDS,
        ]);
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = CardiologyReportTemplate::with(['creator', 'updater']);

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

        $studyNames = InvoiceItemDetail::whereIn('item_code_sub', $itemCodeSubs)
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

    private function validationRules(): array
    {
        return array_merge([
            'title' => 'required|max:150',
            'item_code_sub' => 'required|in:' . implode(',', CardiologyReportFields::ITEM_CODE_SUBS),
            'status' => 'required|in:ACTIVE,INACTIVE',
        ], CardiologyReportFields::validationRules());
    }

    private function sanitizedFields(Request $request): array
    {
        $data = [];
        foreach (CardiologyReportFields::FIELDS as $key => $label) {
            $data[$key] = HtmlSanitizerService::sanitizeClinicalText($request->input($key));
        }
        return $data;
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate($this->validationRules());

        DB::beginTransaction();

        try {

            $template = CardiologyReportTemplate::create(array_merge([

                'title' => trim($request->title),
                'item_code_sub' => $request->item_code_sub,
                'status' => $request->status,

                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),

            ], $this->sanitizedFields($request)));

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $template,
                $template->only($template->getFillable()),
                'Cardiology Report Template created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Cardiology Report Template created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Cardiology Report Template.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $template = CardiologyReportTemplate::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $template
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate($this->validationRules());

        DB::beginTransaction();

        try {

            $template = CardiologyReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->update(array_merge([

                'title' => trim($request->title),
                'item_code_sub' => $request->item_code_sub,
                'status' => $request->status,

                'updated_by' => Auth::id(),

            ], $this->sanitizedFields($request)));

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $template,
                $oldData,
                $template->only($template->getFillable()),
                'Cardiology Report Template updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Cardiology Report Template updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Cardiology Report Template.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $template = CardiologyReportTemplate::findOrFail($id);

            $oldData = $template->only($template->getFillable());

            $template->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $template,
                $oldData,
                'Cardiology Report Template deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Cardiology Report Template deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Cardiology Report Template.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FOR STUDY -- lightweight lookup used by the live-entry template picker.
    |--------------------------------------------------------------------------
    */

    public function forStudy($itemCodeSub)
    {
        $templates = CardiologyReportTemplate::where('item_code_sub', $itemCodeSub)
            ->where('status', 'ACTIVE')
            ->orderBy('title')
            ->get(array_merge(['id', 'title'], array_keys(CardiologyReportFields::FIELDS)));

        return response()->json([
            'status' => true,
            'data' => $templates
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ALL -- lightweight, unpaginated listing for "Copy from existing
    | template" in the Add Template modal.
    |--------------------------------------------------------------------------
    */

    public function all()
    {
        $templates = CardiologyReportTemplate::orderBy('item_code_sub')
            ->orderBy('title')
            ->get(['id', 'title', 'item_code_sub', 'status']);

        $studyNames = InvoiceItemDetail::whereIn('item_code_sub', $templates->pluck('item_code_sub')->unique())
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
