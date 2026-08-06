<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItemMaster;
use App\Services\AuditService;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\Validator;

use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;
class InvoiceItemMasterController extends Controller
{
    private const MODULE_CODE = 'INVOICE_ITEM_MASTER';

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view(
            'apps-ecommerce-invoice-item-masters'
        );
    }

    public function list(Request $request)
    {
        $query = InvoiceItemMaster::select([
            'id',
            'item_code',
            'item_name',
            'item_type',
            'description',
            'status',
            'test_parameter_required',
            'created_at',
            'updated_at'
        ]);

        return DataTables::of($query)

    ->addIndexColumn()

            ->editColumn('created_at', function ($row) {

                return optional($row->created_at)
                    ? $row->created_at->format('d-m-Y H:i')
                    : '';

            })

            ->editColumn('updated_at', function ($row) {

                return optional($row->updated_at)
                    ? $row->updated_at->format('d-m-Y H:i')
                    : '';

            })

    ->editColumn('status', function ($row) {

        return $row->status
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-danger">Inactive</span>';

    })

    ->editColumn('test_parameter_required', function ($row) {

        return $row->test_parameter_required === 'YES'
            ? '<span class="badge bg-success">YES</span>'
            : '<span class="badge bg-secondary">NO</span>';

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
        'test_parameter_required',
        'action'
    ])

    ->make(true);
    }
    public function store(Request $request, AuditService $auditService)
    {
        $validator = Validator::make(

            $request->all(),

            [

                'item_code' =>

                    'required|max:6|unique:invoice_item_masters,item_code',

                'item_name' =>

                    'required|max:191',

                'item_type' =>

                    'required|max:50',

                'test_parameter_required' =>

                    'nullable|in:YES,NO'

            ]

        );

        if ($validator->fails()) {

            return response()->json([

                'status' => false,

                'errors' => $validator->errors()

            ]);

        }

        $item = InvoiceItemMaster::create([

            'item_code' => strtoupper($request->item_code),

            'item_name' => $request->item_name,

            'item_type' => $request->item_type,

            'description' => $request->description,

            'status' => $request->status,

            'test_parameter_required' => $request->test_parameter_required ?? 'NO',

            'created_by' => Auth::id(),

            'updated_by' => Auth::id()

        ]);

        $auditService->logCreate(
            self::MODULE_CODE,
            $item,
            $item->only($item->getFillable()),
            'Invoice item master created'
        );

        return response()->json([

            'status' => true,

            'message' => 'Invoice Item created successfully.'

        ]);
    }
    public function edit($id)
    {
        $item = InvoiceItemMaster::findOrFail($id);

        $item->item_type = strtoupper(trim($item->item_type));

        return response()->json($item);
    }
    public function update(
        Request $request,
        $id,
        AuditService $auditService
    ) {
        $validator = Validator::make(

            $request->all(),

            [

                'item_code' =>

                    'required|max:6|unique:invoice_item_masters,item_code,' . $id,

                'item_name' =>

                    'required|max:191',

                'item_type' =>

                    'required|max:50',

                'test_parameter_required' =>

                    'nullable|in:YES,NO'

            ]

        );

        if ($validator->fails()) {

            return response()->json([

                'status' => false,

                'errors' => $validator->errors()

            ]);

        }

        $item = InvoiceItemMaster::findOrFail($id);

        $oldData = $item->only($item->getFillable());

        $item->update([

            'item_code' => strtoupper($request->item_code),

            'item_name' => $request->item_name,

            'item_type' => $request->item_type,

            'description' => $request->description,

            'status' => $request->status,

            'test_parameter_required' => $request->test_parameter_required ?? 'NO',

            'updated_by' => Auth::id()

        ]);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $item,
            $oldData,
            $item->only($item->getFillable()),
            'Invoice item master updated'
        );

        return response()->json([

            'status' => true,

            'message' => 'Updated successfully.'

        ]);
    }
    public function destroy($id, AuditService $auditService)
    {
        $item = InvoiceItemMaster::findOrFail($id);

        $oldData = $item->only($item->getFillable());

        $item->delete();

        $auditService->logDelete(
            self::MODULE_CODE,
            $item,
            $oldData,
            'Invoice item master deleted'
        );

        return response()->json([

            'status' => true,

            'message' => 'Deleted successfully.'

        ]);
    }
    public function exportExcel()
    {
        return Excel::download(
            new InvoiceItemMasterExport,
            'invoice_item_masters.xlsx'
        );
    }
}
