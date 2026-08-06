<?php

namespace App\Http\Controllers;

use App\Models\PatientCard;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class PatientCardController extends Controller
{
    private const MODULE_CODE = 'PATIENT_CARD';

    public function index()
    {
        return view('apps-patient-card');
    }

    public function list(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $query = PatientCard::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('card_no', 'like', "%{$search}%")
                    ->orWhere('mobile_no', 'like', "%{$search}%")
                    ->orWhere('patient_name1', 'like', "%{$search}%")
                    ->orWhere('patient_name2', 'like', "%{$search}%")
                    ->orWhere('patient_name3', 'like', "%{$search}%")
                    ->orWhere('patient_name4', 'like', "%{$search}%")
                    ->orWhere('patient_name5', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', $request->active);
        }

        $cards = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        $cards->getCollection()->transform(function ($row) {

            $row->created_dt = optional($row->created_at)->format('d-m-Y H:i');

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $cards->items(),
            'pagination' => [
                'current_page' => $cards->currentPage(),
                'last_page' => $cards->lastPage(),
                'total' => $cards->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'card_no' => 'required|max:50|unique:patient_cards,card_no',
            'mobile_no' => 'nullable|digits:10',
            'patient_name1' => 'required|max:150',
            'patient_name2' => 'nullable|max:150',
            'patient_name3' => 'nullable|max:150',
            'patient_name4' => 'nullable|max:150',
            'patient_name5' => 'nullable|max:150',
            'active' => 'required|in:Yes,No',
            'remarks' => 'nullable|string',
        ]);

        try {

            $card = PatientCard::create([

                'card_no' => trim($request->card_no),
                'mobile_no' => $request->mobile_no,
                'patient_name1' => strtoupper(trim($request->patient_name1)),
                'patient_name2' => $request->patient_name2 ? strtoupper(trim($request->patient_name2)) : null,
                'patient_name3' => $request->patient_name3 ? strtoupper(trim($request->patient_name3)) : null,
                'patient_name4' => $request->patient_name4 ? strtoupper(trim($request->patient_name4)) : null,
                'patient_name5' => $request->patient_name5 ? strtoupper(trim($request->patient_name5)) : null,
                'active' => $request->active,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            $auditService->logCreate(
                self::MODULE_CODE,
                $card,
                $card->only($card->getFillable()),
                'Patient card created'
            );

            return response()->json([
                'status' => true,
                'message' => 'Patient card created successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save patient card.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $card = PatientCard::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $card
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'card_no' => 'required|max:50|unique:patient_cards,card_no,' . $id,
            'mobile_no' => 'nullable|digits:10',
            'patient_name1' => 'required|max:150',
            'patient_name2' => 'nullable|max:150',
            'patient_name3' => 'nullable|max:150',
            'patient_name4' => 'nullable|max:150',
            'patient_name5' => 'nullable|max:150',
            'active' => 'required|in:Yes,No',
            'remarks' => 'nullable|string',
        ]);

        try {

            $card = PatientCard::findOrFail($id);

            $oldData = $card->only($card->getFillable());

            $card->update([

                'card_no' => trim($request->card_no),
                'mobile_no' => $request->mobile_no,
                'patient_name1' => strtoupper(trim($request->patient_name1)),
                'patient_name2' => $request->patient_name2 ? strtoupper(trim($request->patient_name2)) : null,
                'patient_name3' => $request->patient_name3 ? strtoupper(trim($request->patient_name3)) : null,
                'patient_name4' => $request->patient_name4 ? strtoupper(trim($request->patient_name4)) : null,
                'patient_name5' => $request->patient_name5 ? strtoupper(trim($request->patient_name5)) : null,
                'active' => $request->active,
                'remarks' => $request->remarks,
                'updated_by' => Auth::id()
            ]);

            $auditService->logUpdate(
                self::MODULE_CODE,
                $card,
                $oldData,
                $card->only($card->getFillable()),
                'Patient card updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'Patient card updated successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update patient card.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        try {

            $card = PatientCard::findOrFail($id);

            $oldData = $card->only($card->getFillable());

            $card->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $card,
                $oldData,
                'Patient card deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'Patient card deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete patient card.'
            ], 500);
        }
    }
}
