<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RolePageAccess;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;

class UserController extends Controller
{
    private const MODULE_CODE = 'USER';

    /**
     * A Supervisor may only create/assign these roles. Employee/Member
     * cannot manage users at all -- see ensureCanManageUsers(). Enforced
     * server-side regardless of what the role dropdown shows or whether the
     * request reaches this controller directly, since the 'users' page_access
     * check (config/page_route_map.php + CheckPageAccess middleware) only
     * covers the named 'users.index' route -- store/update/destroy/list/edit
     * below are unnamed and would otherwise fall through that check entirely.
     */
    private const SUPERVISOR_ASSIGNABLE_ROLES = ['Employee', 'Member'];

    private const USER_MANAGEMENT_ROLES = ['Admin', 'Supervisor'];

    /**
     * Only these two roles are eligible for the diagnostic-test family
     * discount (see MembershipFeeController::searchMember(), which already
     * treats Member and Supervisor as the same tier) -- family_member_1/2/3
     * are only ever persisted for users in one of these roles.
     */
    private const MEMBER_TIER_ROLES = ['Member', 'Supervisor'];

    /**
     * Blocks any role outside USER_MANAGEMENT_ROLES from reaching the
     * list/view/delete endpoints at all.
     */
    private function ensureCanManageUsers(): void
    {
        if (!in_array(optional(Auth::user())->role, self::USER_MANAGEMENT_ROLES, true)) {
            abort(403, 'You do not have access to manage users.');
        }
    }

    /**
     * Returns an error message if the current user isn't allowed to
     * create/assign the given role, or null if it's allowed.
     */
    private function roleAssignmentError(string $role): ?string
    {
        $currentRole = optional(Auth::user())->role;

        if ($currentRole === 'Admin') {
            return null;
        }

        if ($currentRole === 'Supervisor') {
            return in_array($role, self::SUPERVISOR_ASSIGNABLE_ROLES, true)
                ? null
                : 'A Supervisor can only assign the Employee or Member role.';
        }

        return 'You do not have permission to manage users.';
    }

    public function index()
    {
        $this->ensureCanManageUsers();

        return view('apps-users', [
            'pages' => config('access_pages'),
            'roles' => RolePageAccess::orderBy('role')->pluck('role'),
            'currentUserRole' => optional(Auth::user())->role,
        ]);
    }

    public function list(Request $request)
    {
        $this->ensureCanManageUsers();

        $perPage = $request->get('per_page', 10);

        $query = User::query();

        if ($request->filled('search')) {

            $search = trim($request->search);

            $query->where(function ($q) use ($search) {

                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile_no', 'like', "%{$search}%");
            });
        }

        $users = $query
            ->orderBy('name')
            ->paginate($perPage);

        $users->getCollection()->transform(function ($row) {

            $row->date_of_birth_display = $row->date_of_birth
                ? \Carbon\Carbon::parse($row->date_of_birth)->format('d-m-Y')
                : null;

            $row->date_of_joining_display = $row->date_of_joining
                ? \Carbon\Carbon::parse($row->date_of_joining)->format('d-m-Y')
                : null;

            $row->image_url = $row->user_image
                ? asset('uploads/users/' . $row->user_image)
                : asset('build/images/users/avatar-1.jpg');

            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total()
            ]
        ]);
    }

    public function store(Request $request, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:191',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|min:6',
            'mobile_no' => 'nullable|digits:10|unique:users,mobile_no',
            'role' => 'required|exists:role_page_access,role',
            'authorised' => 'required|in:YES,NO',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'gender' => 'nullable|in:Male,Female,Other',
            'family_member_1' => 'nullable|max:191',
            'family_member_2' => 'nullable|max:191',
            'family_member_3' => 'nullable|max:191',
        ]);

        if ($error = $this->roleAssignmentError($request->role)) {

            return response()->json([
                'status' => false,
                'message' => $error
            ]);
        }

        $isMemberTier = in_array($request->role, self::MEMBER_TIER_ROLES, true);

        DB::beginTransaction();

        try {

            $imageName = null;

            if ($request->hasFile('user_image')) {

                $image = $request->file('user_image');

                $imageName = time() . '.' . $image->getClientOriginalExtension();

                $image->move(public_path('uploads/users'), $imageName);
            }

            $user = User::create([

                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'avatar' => '',
                'user_image' => $imageName,
                'mobile_no' => $request->mobile_no,

                'role' => $request->role,
                'authorised' => $request->authorised,
                'status' => $request->status,

                'date_of_birth' => $request->date_of_birth,
                'date_of_joining' => $request->date_of_joining,
                'gender' => $request->gender,

                'aadhar_no' => $request->aadhar_no,
                'pan_no' => $request->pan_no,
                'qualification' => $request->qualification,
                'blood_group' => $request->blood_group,

                'father_name' => $request->father_name,
                'guardian_name' => $request->guardian_name,

                'present_address' => $request->present_address,
                'permanent_address' => $request->permanent_address,
                'emergency_mobile_no' => $request->emergency_mobile_no,

                'bank_ac_no' => $request->bank_ac_no,
                'bank_name' => $request->bank_name,
                'bank_branch' => $request->bank_branch,

                'family_member_1' => $isMemberTier ? $request->family_member_1 : null,
                'family_member_2' => $isMemberTier ? $request->family_member_2 : null,
                'family_member_3' => $isMemberTier ? $request->family_member_3 : null,

                'created_by' => Auth::id(),
                'updated_by' => Auth::id()
            ]);

            DB::commit();

            $auditService->logCreate(
                self::MODULE_CODE,
                $user,
                collect($user->only($user->getFillable()))->except('password')->toArray(),
                'User created'
            );

            return response()->json([
                'status' => true,
                'message' => 'User created successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to save user.'
            ], 500);
        }
    }

    public function edit($id)
    {
        $this->ensureCanManageUsers();

        $user = User::findOrFail($id);

        $user->image_url = $user->user_image
            ? asset('uploads/users/' . $user->user_image)
            : asset('build/images/users/avatar-1.jpg');

        return response()->json([
            'status' => true,
            'data' => $user
        ]);
    }

    public function update(Request $request, $id, AuditService $auditService)
    {
        $request->validate([
            'name' => 'required|max:191',
            'email' => 'required|email|max:191|unique:users,email,' . $id,
            'password' => 'nullable|min:6',
            'mobile_no' => 'nullable|digits:10|unique:users,mobile_no,' . $id,
            'role' => 'required|exists:role_page_access,role',
            'authorised' => 'required|in:YES,NO',
            'status' => 'required|in:ACTIVE,INACTIVE',
            'gender' => 'nullable|in:Male,Female,Other',
            'family_member_1' => 'nullable|max:191',
            'family_member_2' => 'nullable|max:191',
            'family_member_3' => 'nullable|max:191',
        ]);

        if ($error = $this->roleAssignmentError($request->role)) {

            return response()->json([
                'status' => false,
                'message' => $error
            ]);
        }

        $isMemberTier = in_array($request->role, self::MEMBER_TIER_ROLES, true);

        DB::beginTransaction();

        try {

            $user = User::findOrFail($id);

            $oldData = collect($user->only($user->getFillable()))->except('password')->toArray();

            if ($request->hasFile('user_image')) {

                if (
                    $user->user_image &&
                    file_exists(public_path('uploads/users/' . $user->user_image))
                ) {
                    unlink(public_path('uploads/users/' . $user->user_image));
                }

                $image = $request->file('user_image');

                $imageName = time() . '.' . $image->getClientOriginalExtension();

                $image->move(public_path('uploads/users'), $imageName);

                $user->user_image = $imageName;
            }

            $data = [

                'name' => $request->name,
                'email' => $request->email,
                'mobile_no' => $request->mobile_no,

                'role' => $request->role,
                'authorised' => $request->authorised,
                'status' => $request->status,

                'date_of_birth' => $request->date_of_birth,
                'date_of_joining' => $request->date_of_joining,
                'gender' => $request->gender,

                'aadhar_no' => $request->aadhar_no,
                'pan_no' => $request->pan_no,
                'qualification' => $request->qualification,
                'blood_group' => $request->blood_group,

                'father_name' => $request->father_name,
                'guardian_name' => $request->guardian_name,

                'present_address' => $request->present_address,
                'permanent_address' => $request->permanent_address,
                'emergency_mobile_no' => $request->emergency_mobile_no,

                'bank_ac_no' => $request->bank_ac_no,
                'bank_name' => $request->bank_name,
                'bank_branch' => $request->bank_branch,

                'family_member_1' => $isMemberTier ? $request->family_member_1 : null,
                'family_member_2' => $isMemberTier ? $request->family_member_2 : null,
                'family_member_3' => $isMemberTier ? $request->family_member_3 : null,

                'updated_by' => Auth::id()
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            DB::commit();

            $auditService->logUpdate(
                self::MODULE_CODE,
                $user,
                $oldData,
                collect($user->only($user->getFillable()))->except('password')->toArray(),
                'User updated'
            );

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully.'
            ]);

        } catch (Exception $e) {

            DB::rollBack();

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to update user.'
            ], 500);
        }
    }

    public function destroy($id, AuditService $auditService)
    {
        $this->ensureCanManageUsers();

        try {

            if ((int) $id === (int) Auth::id()) {

                return response()->json([
                    'status' => false,
                    'message' => 'You cannot delete your own account.'
                ]);
            }

            $user = User::findOrFail($id);

            if ($user->role === 'Admin' && User::where('role', 'Admin')->count() <= 1) {

                return response()->json([
                    'status' => false,
                    'message' => 'Cannot delete the last remaining Admin user.'
                ]);
            }

            if (
                $user->user_image &&
                file_exists(public_path('uploads/users/' . $user->user_image))
            ) {
                unlink(public_path('uploads/users/' . $user->user_image));
            }

            $oldData = collect($user->only($user->getFillable()))->except('password')->toArray();

            $user->delete();

            $auditService->logDelete(
                self::MODULE_CODE,
                $user,
                $oldData,
                'User deleted'
            );

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully.'
            ]);

        } catch (Exception $e) {

            Log::error($e);

            return response()->json([
                'status' => false,
                'message' => 'Unable to delete user.'
            ], 500);
        }
    }
}
