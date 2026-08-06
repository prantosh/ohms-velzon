<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doctor;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\DoctorExport;
use App\Models\Specialisation;
use App\Services\AuditService;
use Illuminate\Support\Facades\Auth;
class DoctorController extends Controller
{
    private const MODULE_CODE = 'DOCTOR';

    /*
    |--------------------------------------------------------------------------
    | DOCTOR LIST
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $specialisations =
            Specialisation::orderBy('category')->get();
        
        return view(
            'apps-ecommerce-doctors',
            compact('specialisations')
        );
    }


       /*
    |--------------------------------------------------------------------------
    | AJAX DOCTOR LIST
    |--------------------------------------------------------------------------
    */

    public function getDoctors(Request $request)
    {
        try {

            $perPage =
                $request->get('per_page', 10);

            $doctors =
                Doctor::orderBy('doctor_name', 'asc')
                    ->paginate($perPage);

            $specialisationIdsByDoctor = \Illuminate\Support\Facades\DB::table('doctor_specialisations')
                ->whereIn('doctor_id', $doctors->getCollection()->pluck('id'))
                ->get(['doctor_id', 'specialisation_id'])
                ->groupBy('doctor_id')
                ->map(fn ($rows) => $rows->pluck('specialisation_id')->values());

            $doctors->getCollection()->transform(function ($row) use ($specialisationIdsByDoctor) {

                $row->joining_date =
                    $row->joining_date
                    ? date(
                        'Y-m-d',
                        strtotime($row->joining_date)
                    )
                    : '';

                $row->image_url =
                    $row->doctor_image
                    ? asset(
                        'uploads/doctors/' .
                        $row->doctor_image
                    )
                    : asset(
                        'build/images/users/avatar-1.jpg'
                    );
                $row->specialisation_code = $row->specialisation_code;

                $row->specialisation_codes =
                    $specialisationIdsByDoctor->get($row->id, collect())->values();

                return $row;
            });

            return response()->json([

                'status' => true,

                'data' => $doctors->items(),

                'pagination' => [

                    'current_page' =>
                        $doctors->currentPage(),

                    'last_page' =>
                        $doctors->lastPage(),

                    'per_page' =>
                        $doctors->perPage(),

                    'total' =>
                        $doctors->total(),
                ]
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,
                'message' => $e->getMessage()

            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([

            'doctor_code' => 'required|string|unique:doctors,doctor_code',
            'doctor_name' => 'required|string',
            'gender' => 'required|in:M,F,O',
            'specialisation_codes' => 'required|array|min:1',
            'specialisation_codes.*' => 'exists:specialisations,id',
            'joining_date' => 'required|date|before_or_equal:today',
        ]);

        $specialisationCodes = $request->specialisation_codes;

        $specialisation =
            Specialisation::find(
                $specialisationCodes[0]
            );
        $imageName = null;

        if ($request->hasFile('doctor_image')) {

            $image = $request->file('doctor_image');

            $imageName =
                time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/doctors'),
                $imageName
            );
        }
        $doctor = Doctor::create([

            'doctor_code' => $request->doctor_code,

            'doctor_name' => 'Dr. ' . preg_replace('/^dr\.?\s*/i', '', trim($request->doctor_name)),

            'gender' => $request->gender,

            'date_of_birth' => $request->date_of_birth,

            'mobile_no' => $request->mobile_no,

            'alternate_mobile_no' => $request->alternate_mobile_no,

            'email' => $request->email,

            'specialisation' => $specialisation->category,

            'specialisation_code' => $specialisationCodes[0],

            'qualification' => $request->qualification,

            'experience_years' => $request->experience_years,

            'registration_no' => $request->registration_no,

            'joining_date' => $request->joining_date,

            'consultation_fee_total' => $request->consultation_fee_total,

            'consultation_fee_doctor' => $request->consultation_fee_doctor,

            'consultation_fee_doctor_discounted_patient'
            => $request->consultation_fee_doctor_discounted_patient,

            'discount' => $request->discount,

            'status' => $request->status,

            'remarks' => $request->remarks,
            'doctor_image' => $imageName,
            'created_by' => Auth::id(),
        ]);

        $doctor->specialisations()->sync($specialisationCodes);

        $auditService->logCreate(
            self::MODULE_CODE,
            $doctor,
            $doctor->only($doctor->getFillable()),
            'Doctor created'
        );

        return response()->json([

            'status' => true,

            'message' =>
                'Doctor added successfully'
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE DOCTOR
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id, AuditService $auditService)
    {
        $doctor = Doctor::findOrFail($id);

        $request->validate([

            'doctor_code' => 'required|string|unique:doctors,doctor_code,' . $id,
            'doctor_name' => 'required|string',
            'gender' => 'required|in:M,F,O',
            'specialisation_codes' => 'required|array|min:1',
            'specialisation_codes.*' => 'exists:specialisations,id',
            'joining_date' => 'required|date|before_or_equal:today',
        ]);

        $specialisationCodes = $request->specialisation_codes;

        $oldData = $doctor->only($doctor->getFillable());

        $specialisation = Specialisation::find($specialisationCodes[0]);

        $imageName = $doctor->doctor_image;

        if ($request->hasFile('doctor_image')) {

            if (
                $doctor->doctor_image &&
                file_exists(
                    public_path(
                        'uploads/doctors/' .
                        $doctor->doctor_image
                    )
                )
            ) {

                unlink(
                    public_path(
                        'uploads/doctors/' .
                        $doctor->doctor_image
                    )
                );
            }

            $image = $request->file('doctor_image');

            $imageName =
                time() . '.' .
                $image->getClientOriginalExtension();

            $image->move(
                public_path('uploads/doctors'),
                $imageName
            );
        }


        $doctor->update([

            'doctor_code' => $request->doctor_code,

            'doctor_name' => $request->doctor_name,

            'gender' => $request->gender,

            'date_of_birth' => $request->date_of_birth,

            'mobile_no' => $request->mobile_no,

            'alternate_mobile_no' => $request->alternate_mobile_no,

            'email' => $request->email,

            'specialisation' => $specialisation->category,

            'specialisation_code' => $specialisationCodes[0],

            'qualification' => $request->qualification,

            'experience_years' => $request->experience_years,

            'registration_no' => $request->registration_no,

            'joining_date' => $request->joining_date,

            'consultation_fee_total' => $request->consultation_fee_total,

            'consultation_fee_doctor' => $request->consultation_fee_doctor,

            'consultation_fee_doctor_discounted_patient'
            => $request->consultation_fee_doctor_discounted_patient,

            'discount' => $request->discount,

            'status' => $request->status,

            'remarks' => $request->remarks,
            'doctor_image' => $imageName,
            'updated_by' => Auth::id(),
        ]);

        $doctor->specialisations()->sync($specialisationCodes);

        $auditService->logUpdate(
            self::MODULE_CODE,
            $doctor,
            $oldData,
            $doctor->only($doctor->getFillable()),
            'Doctor updated'
        );

        return response()->json([

            'status' => true,
            'message' => 'Doctor updated successfully'

        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | DELETE DOCTOR
    |--------------------------------------------------------------------------
    */

    public function destroy($id, AuditService $auditService)
    {
        try {

            $doctor = Doctor::findOrFail($id);

            $oldData = $doctor->only($doctor->getFillable());

            if (
                $doctor->doctor_image &&
                file_exists(public_path('uploads/doctors/' . $doctor->doctor_image))
            ) {
                unlink(public_path('uploads/doctors/' . $doctor->doctor_image));
            }

            $doctor->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $doctor,
                $oldData,
                'Doctor deleted'
            );

            return response()->json([

                'status' => true,
                'message' => 'Doctor deleted successfully'
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'status' => false,
                'message' => $e->getMessage()

            ], 500);
        }
    }
    public function exportExcel()
    {
        $doctors =
            Doctor::orderBy('id', 'desc')
                ->get();

        $filename = 'doctors.csv';

        $headers = [

            "Content-type" => "text/csv",

            "Content-Disposition" =>
                "attachment; filename=$filename",

            "Pragma" => "no-cache",

            "Cache-Control" =>
                "must-revalidate, post-check=0, pre-check=0",

            "Expires" => "0"
        ];

        $columns = [

            'ID',
            'Doctor Code',
            'Doctor Name',
            'Mobile No',
            'Email',
            'specialisation',
            'Joining Date',
            'Status'
        ];

        $callback =
            function () use ($doctors, $columns) {

                $file =
                    fopen('php://output', 'w');

                fputcsv($file, $columns);

                foreach ($doctors as $row) {

                    fputcsv($file, [

                        $row->id,
                        $row->doctor_code,
                        $row->doctor_name,
                        $row->mobile_no,
                        $row->email,
                        $row->specialisation,
                        $row->joining_date,
                        $row->status
                    ]);
                }

                fclose($file);
            };

        return response()
            ->stream($callback, 200, $headers);
    }

    public function printDoctors()
    {
        $doctors =
            Doctor::orderBy('id', 'desc')
                ->get();

        return view(
            'doctors-print',
            compact('doctors')
        );
    }
}
