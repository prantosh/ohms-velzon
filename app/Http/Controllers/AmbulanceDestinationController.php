<?php

namespace App\Http\Controllers;

use App\Models\AmbulanceDestinationMaster;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AmbulanceDestinationController extends Controller
{
    private const MODULE_CODE = 'AMBULANCE_DESTINATION_MASTER';

    public function index()
    {
        return view('apps-ambulance-destination');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = AmbulanceDestinationMaster::with('creator');

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('destination_code', 'like', "%{$search}%")
                    ->orWhere('destination_name', 'like', "%{$search}%");

            });
        }

        $destinations = $query
            ->orderBy('destination_name')
            ->paginate($perPage);

        $destinations->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)
                ->format('d-m-Y H:i');

            $row->created_by_name = optional($row->creator)->name;

            return $row;

        });

        return response()->json([
            'status' => true,
            'data' => $destinations->items(),
            'pagination' => [
                'current_page' => $destinations->currentPage(),
                'last_page' => $destinations->lastPage(),
                'total' => $destinations->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'destination_name' => 'required|max:150|unique:ambulance_destination_masters,destination_name',
            'fare_ac' => 'required|numeric|min:0',
            'fare_nonac' => 'required|numeric|min:0',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $destination = AmbulanceDestinationMaster::create([

                'destination_code' => $this->generateDestinationCode(),

                'destination_name' => strtoupper(trim($request->destination_name)),

                'fare_ac' => $request->fare_ac,

                'fare_nonac' => $request->fare_nonac,

                'remarks' => $request->remarks,

                'status' => $request->status,

                'created_by' => Auth::id(),

                'updated_by' => Auth::id()

            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $destination,
                $destination->only($destination->getFillable()),
                'Ambulance destination created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Ambulance Destination created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save Ambulance Destination.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $destination = AmbulanceDestinationMaster::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $destination
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'destination_name' => 'required|max:150|unique:ambulance_destination_masters,destination_name,' . $id,
            'fare_ac' => 'required|numeric|min:0',
            'fare_nonac' => 'required|numeric|min:0',
            'status' => 'required'
        ]);

        DB::beginTransaction();

        try {

            $destination = AmbulanceDestinationMaster::findOrFail($id);

            $oldData = $destination->only($destination->getFillable());

            $destination->update([

                'destination_name' => strtoupper(trim($request->destination_name)),

                'fare_ac' => $request->fare_ac,

                'fare_nonac' => $request->fare_nonac,

                'remarks' => $request->remarks,

                'status' => $request->status,

                'updated_by' => Auth::id()

            ]);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $destination,
                $oldData,
                $destination->only($destination->getFillable()),
                'Ambulance destination updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Ambulance Destination updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update Ambulance Destination.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $destination = AmbulanceDestinationMaster::findOrFail($id);

            $inUse = DB::table('invoice_details')
                ->where('ambulance_destination_id', $id)
                ->exists();

            if ($inUse) {

                return response()->json([
                    'status' => false,
                    'message' => 'This destination cannot be deleted. It is referenced by existing ambulance rental invoices.'
                ]);
            }

            $oldData = $destination->only($destination->getFillable());

            $destination->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $destination,
                $oldData,
                'Ambulance destination deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Ambulance Destination deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete Ambulance Destination.'
            ], 500);
        }
    }

    private function generateDestinationCode(): string
    {
        $last = AmbulanceDestinationMaster::latest('id')->first();

        if (!$last) {
            return 'AD000001';
        }

        $number = intval(substr($last->destination_code, 2)) + 1;

        return 'AD' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}
