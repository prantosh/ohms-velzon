@extends('layouts.master')
@section('title')
    @lang('translation.settings')
@endsection
@section('css')
    <link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}" rel="stylesheet" type="text/css" />
@endsection
@section('content')

    <div class="row">
        <div class="col-xxl-3">
            <div class="card card-bg-fill">
                <div class="card-body p-4">
                    <div class="text-center">
                        <div class="profile-user position-relative d-inline-block mx-auto  mb-4">
                            <img src="@if (Auth::user()->avatar != '') {{ URL::asset('images/' . Auth::user()->avatar) }}@else{{ URL::asset('build/images/users/avatar-1.jpg') }} @endif"
                                class="  rounded-circle avatar-xl img-thumbnail user-profile-image"
                                alt="user-profile-image">
                            <div class="avatar-xs p-0 rounded-circle profile-photo-edit">
                                <input id="profile-img-file-input" type="file" class="profile-img-file-input">
                                <label for="profile-img-file-input" class="profile-photo-edit avatar-xs">
                                    <span class="avatar-title rounded-circle bg-light text-body">
                                        <i class="ri-camera-fill"></i>
                                    </span>
                                </label>
                            </div>
                        </div>
                        <h5 class="fs-16 mb-1">{{ $profileUser->name }}</h5>
                        <p class="text-muted mb-0">{{ $profileUser->role }}</p>
                    </div>
                </div>
            </div>
            <!--end card-->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-5">
                        <div class="flex-grow-1">
                            <h5 class="card-title mb-0">Complete Your Profile</h5>
                        </div>
                        <div class="flex-shrink-0">
                            <a href="javascript:void(0);" class="badge bg-light text-primary fs-12"><i
                                    class="ri-edit-box-line align-bottom me-1"></i> Edit</a>
                        </div>
                    </div>
                    <div class="progress animated-progress custom-progress progress-label">
                        <div class="progress-bar bg-danger" role="progressbar" style="width: 30%" aria-valuenow="30"
                            aria-valuemin="0" aria-valuemax="100">
                            <div class="label">30%</div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end card-->
        </div>
        <!--end col-->
        <div class="col-xxl-9">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs-custom rounded card-header-tabs border-bottom-0" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#personalDetails" role="tab">
                                <i class="fas fa-home"></i>
                                Personal Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#changePassword" role="tab">
                                <i class="far fa-user"></i>
                                Change Password
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-4">
                    <div class="tab-content">
                        <div class="tab-pane active" id="personalDetails" role="tabpanel">
                            <form action="javascript:void(0);" id="personalDetailsForm"
                                data-update-url="{{ route('updateProfile', $profileUser->id) }}">
                                @csrf
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="nameInput" class="form-label">Name</label>
                                            <input type="text" class="form-control" id="nameInput" name="name"
                                                placeholder="Enter your name" value="{{ $profileUser->name }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="phonenumberInput" class="form-label">Phone
                                                Number</label>
                                            <input type="text" class="form-control" id="phonenumberInput" name="mobile_no"
                                                placeholder="Enter your phone number" value="{{ $profileUser->mobile_no }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="emailInput" class="form-label">Email
                                                Address</label>
                                            <input type="email" class="form-control" id="emailInput" name="email"
                                                placeholder="Enter your email" value="{{ $profileUser->email }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="designationInput" class="form-label">Designation</label>
                                            <input type="text" class="form-control" id="designationInput"
                                                placeholder="Designation" value="{{ $profileUser->role }}" readonly>
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="JoiningdatInput" class="form-label">Joining
                                                Date</label>
                                            <input type="text" class="form-control" data-provider="flatpickr"
                                                id="JoiningdatInput" name="date_of_joining" data-date-format="d M, Y"
                                                placeholder="Select date"
                                                value="{{ $profileUser->date_of_joining ? \Carbon\Carbon::parse($profileUser->date_of_joining)->format('d M, Y') : '' }}" />
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="dobInput" class="form-label">Date of Birth</label>
                                            <input type="text" class="form-control" data-provider="flatpickr"
                                                id="dobInput" name="date_of_birth" data-date-format="d M, Y"
                                                placeholder="Select date"
                                                value="{{ $profileUser->date_of_birth ? \Carbon\Carbon::parse($profileUser->date_of_birth)->format('d M, Y') : '' }}" />
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label for="genderInput" class="form-label">Gender</label>
                                            <select class="form-control" id="genderInput" name="gender">
                                                <option value="">Select gender</option>
                                                <option value="Male" @selected($profileUser->gender == 'Male')>Male</option>
                                                <option value="Female" @selected($profileUser->gender == 'Female')>Female</option>
                                                <option value="Other" @selected($profileUser->gender == 'Other')>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label for="bloodGroupInput" class="form-label">Blood Group</label>
                                            <select class="form-control" id="bloodGroupInput" name="blood_group">
                                                <option value="">Select blood group</option>
                                                @foreach(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bg)
                                                <option value="{{ $bg }}" @selected($profileUser->blood_group == $bg)>{{ $bg }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label for="qualificationInput" class="form-label">Qualification</label>
                                            <input type="text" class="form-control" id="qualificationInput" name="qualification"
                                                placeholder="Enter qualification" value="{{ $profileUser->qualification }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="aadharInput" class="form-label">Aadhar No</label>
                                            <input type="text" class="form-control" id="aadharInput" name="aadhar_no"
                                                placeholder="Enter Aadhar number" value="{{ $profileUser->aadhar_no }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="panInput" class="form-label">PAN No</label>
                                            <input type="text" class="form-control" id="panInput" name="pan_no"
                                                placeholder="Enter PAN number" value="{{ $profileUser->pan_no }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="fatherNameInput" class="form-label">Father's Name</label>
                                            <input type="text" class="form-control" id="fatherNameInput" name="father_name"
                                                placeholder="Enter father's name" value="{{ $profileUser->father_name }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="guardianNameInput" class="form-label">Guardian's Name</label>
                                            <input type="text" class="form-control" id="guardianNameInput" name="guardian_name"
                                                placeholder="Enter guardian's name" value="{{ $profileUser->guardian_name }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="emergencyMobileInput" class="form-label">Emergency Mobile No</label>
                                            <input type="text" class="form-control" id="emergencyMobileInput" name="emergency_mobile_no"
                                                placeholder="Enter emergency contact number" value="{{ $profileUser->emergency_mobile_no }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="zipcodeInput" class="form-label">PIN</label>
                                            <input type="text" class="form-control" minlength="5" maxlength="6"
                                                id="zipcodeInput" placeholder="Enter PIN" value="">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="presentAddressInput" class="form-label">Present Address</label>
                                            <textarea class="form-control" id="presentAddressInput" name="present_address" rows="2"
                                                placeholder="Enter present address">{{ $profileUser->present_address }}</textarea>
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label for="permanentAddressInput" class="form-label">Permanent Address</label>
                                            <textarea class="form-control" id="permanentAddressInput" name="permanent_address" rows="2"
                                                placeholder="Enter permanent address">{{ $profileUser->permanent_address }}</textarea>
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label for="bankNameInput" class="form-label">Bank Name</label>
                                            <input type="text" class="form-control" id="bankNameInput" name="bank_name"
                                                placeholder="Enter bank name" value="{{ $profileUser->bank_name }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label for="bankBranchInput" class="form-label">Bank Branch</label>
                                            <input type="text" class="form-control" id="bankBranchInput" name="bank_branch"
                                                placeholder="Enter bank branch" value="{{ $profileUser->bank_branch }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label for="bankAcInput" class="form-label">Bank A/C No</label>
                                            <input type="text" class="form-control" id="bankAcInput" name="bank_ac_no"
                                                placeholder="Enter bank account number" value="{{ $profileUser->bank_ac_no }}">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-12">
                                        <div class="hstack gap-2 justify-content-end">
                                            <button type="submit" class="btn btn-primary">Updates</button>
                                            <button type="button" class="btn btn-soft-danger" id="personalDetailsCancelBtn">Cancel</button>
                                        </div>
                                    </div>
                                    <!--end col-->
                                </div>
                                <!--end row-->
                            </form>

                            <div class="mt-4 pt-2 border-top">
                                <h5 class="card-title mb-3">Account Information</h5>
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label class="form-label">Authorised</label>
                                            <input type="text" class="form-control" value="{{ $profileUser->authorised }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label class="form-label">Status</label>
                                            <input type="text" class="form-control" value="{{ $profileUser->status }}" readonly>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mb-3">
                                            <label class="form-label">Email Verified At</label>
                                            <input type="text" class="form-control"
                                                value="{{ $profileUser->email_verified_at ? \Carbon\Carbon::parse($profileUser->email_verified_at)->format('d M, Y h:i A') : 'Not verified' }}"
                                                readonly>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label">Created</label>
                                            <input type="text" class="form-control"
                                                value="{{ $profileUser->created_at ? \Carbon\Carbon::parse($profileUser->created_at)->format('d M, Y h:i A') : '' }}{{ $createdByName ? ' by ' . $createdByName : '' }}"
                                                readonly>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-3">
                                            <label class="form-label">Last Updated</label>
                                            <input type="text" class="form-control"
                                                value="{{ $profileUser->updated_at ? \Carbon\Carbon::parse($profileUser->updated_at)->format('d M, Y h:i A') : '' }}{{ $updatedByName ? ' by ' . $updatedByName : '' }}"
                                                readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end tab-pane-->
                        <div class="tab-pane" id="changePassword" role="tabpanel">
                            <form action="javascript:void(0);" id="changePasswordForm"
                                data-update-url="{{ route('updatePassword', $profileUser->id) }}">
                                @csrf
                                <div class="row g-2">
                                    <div class="col-lg-4">
                                        <div>
                                            <label for="oldpasswordInput" class="form-label">Old
                                                Password*</label>
                                            <input type="password" class="form-control" id="oldpasswordInput" name="current_password"
                                                placeholder="Enter current password">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div>
                                            <label for="newpasswordInput" class="form-label">New
                                                Password*</label>
                                            <input type="password" class="form-control" id="newpasswordInput" name="password"
                                                placeholder="Enter new password">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-4">
                                        <div>
                                            <label for="confirmpasswordInput" class="form-label">Confirm
                                                Password*</label>
                                            <input type="password" class="form-control" id="confirmpasswordInput" name="password_confirmation"
                                                placeholder="Confirm password">
                                        </div>
                                    </div>
                                    <!--end col-->
                                    <div class="col-lg-12">
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">Change
                                                Password</button>
                                        </div>
                                    </div>
                                    <!--end col-->
                                </div>
                                <!--end row-->
                            </form>
                            <div class="mt-4 mb-3 border-bottom pb-2">
                                <h5 class="card-title">Login History</h5>
                            </div>
                            @forelse($loginHistory as $entry)
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-shrink-0 avatar-sm">
                                    <div class="avatar-title bg-light text-primary rounded-3 fs-18">
                                        <i class="{{ $entry['icon'] }}"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6>{{ $entry['label'] }}</h6>
                                    <p class="text-muted mb-0">
                                        {{ $entry['ip_address'] ?? 'Unknown IP' }} - {{ $entry['login_time_fmt'] ?? '-' }}
                                    </p>
                                </div>
                                <div>
                                    @if($entry['still_active'])
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    @else
                                        <span class="text-muted small">Logged out {{ $entry['logout_time_fmt'] }}</span>
                                    @endif
                                </div>
                            </div>
                            @empty
                            <p class="text-muted mb-0">No login history recorded yet.</p>
                            @endforelse
                        </div>
                        <!--end tab-pane-->
                    </div>
                </div>
            </div>
        </div>
        <!--end col-->
    </div>
    <!--end row-->
@endsection
@section('script')
    <script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>
    <script src="{{ URL::asset('build/js/pages/profile-setting.init.js') }}"></script>
    <script src="{{ URL::asset('build/js/app.js') }}"></script>
@endsection
