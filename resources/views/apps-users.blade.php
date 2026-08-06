@extends('layouts.master')

@section('title')
    User Management
@endsection

@section('css')

<link href="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.css') }}"
      rel="stylesheet"
      type="text/css" />

<link href="{{ URL::asset('build/libs/flatpickr/flatpickr.min.css') }}" rel="stylesheet" type="text/css" />

@endsection

@section('content')

@component('components.breadcrumb')

    @slot('li_1')
        Administration
    @endslot

    @slot('title')
        User Management
    @endslot

@endcomponent

<div class="row">

    <div class="col-lg-12">

        <div class="card">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">

    <div class="d-flex align-items-center gap-3 flex-wrap">

        <div class="d-flex align-items-center">

            <label class="me-2 mb-0 fw-semibold">
                Show
            </label>

            <select id="perPage"
                    class="form-select form-select-sm w-auto">

                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>

            </select>

            <span class="ms-2">
                records
            </span>

        </div>

        <div>
            <input type="text"
                   id="searchInput"
                   class="form-control form-control-sm"
                   placeholder="Search name / email / mobile">
        </div>

    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">

        <button type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#showModal"
                id="addUserBtn">

            <i class="ri-add-line align-bottom me-1"></i>
            Add User

        </button>

    </div>

</div>

            <div class="card-body">

                <div class="table-responsive">

                    <table class="table table-bordered align-middle"
                           id="userTable">

                        <thead class="table-light">

                            <tr>

                            <th width="50">
                                <input type="checkbox">
                            </th>

                            <th width="70">Photo</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th width="110">Mobile</th>
                            <th width="100">Role</th>
                            <th width="100">Authorised</th>
                            <th width="100">Status</th>
                            <th width="120">Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        </tbody>

                    </table>

                    <div class="d-flex justify-content-between align-items-center mt-3">

    <div id="pagination-info"></div>

    <div class="d-flex align-items-center gap-2">

        <button class="btn btn-sm btn-primary" id="prevPage">Previous</button>

        <span id="pageNumber">Page 1</span>

        <button class="btn btn-sm btn-primary" id="nextPage">Next</button>

    </div>

</div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- MODAL -->

<div class="modal fade"
     id="showModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-light p-3">

                <h5 class="modal-title" id="modal-title">
                    Add User
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                </button>

            </div>

            <form class="tablelist-form" enctype="multipart/form-data">

                @csrf

                <input type="hidden" id="edit-id">

                <div class="modal-body">

                    <h6 class="fw-semibold text-primary">Login Details</h6>

                    <div class="row">

                        <div class="col-md-2 mb-3 text-center">
                            <label class="form-label d-block">Photo</label>
                            <img id="user_image-preview"
                                 src="{{ URL::asset('build/images/users/avatar-1.jpg') }}"
                                 class="rounded-circle avatar-md mb-2"
                                 style="width:80px;height:80px;object-fit:cover;">
                            <input type="file" id="user_image-field" class="form-control form-control-sm" accept="image/*">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" id="name-field" class="form-control" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" id="email-field" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Mobile No</label>
                            <input type="text" id="mobile_no-field" class="form-control" maxlength="10">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">
                                Password
                                <span class="text-danger" id="password-required">*</span>
                                <small class="text-muted" id="password-hint" style="display:none;">(leave blank to keep unchanged)</small>
                            </label>
                            <input type="password" id="password-field" class="form-control">
                        </div>

                    </div>

                    <hr>

                    <h6 class="fw-semibold text-primary">Role &amp; Access</h6>

                    <div class="row">

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Role <span class="text-danger">*</span></label>
                            <select id="role-field" class="form-select" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select id="status-field" class="form-select" required>
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">
                                Authorised
                                <i class="ri-information-line" title="Allowed to authorise discounts (e.g. Equipment Rental)"></i>
                            </label>
                            <select id="authorised-field" class="form-select">
                                <option value="NO">NO</option>
                                <option value="YES">YES</option>
                            </select>
                        </div>

                        <div class="col-md-12 mb-3">

                            <label class="form-label">
                                Pages Accessible for Selected Role
                                <a href="{{ route('role-page-access.index') }}"
                                   target="_blank"
                                   class="ms-1">
                                    (manage role access)
                                </a>
                            </label>

                            <div id="role-page-access-preview"
                                 class="border rounded p-2 bg-light-subtle">

                                <span class="text-muted">Select a role to see its allowed pages.</span>

                            </div>

                        </div>

                    </div>

                    <hr>

                    <h6 class="fw-semibold text-primary">Personal Details</h6>

                    <div class="row">

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="text" id="date_of_birth-field" class="form-control flatpickr">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Date of Joining</label>
                            <input type="text" id="date_of_joining-field" class="form-control flatpickr">
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Gender</label>
                            <select id="gender-field" class="form-select">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Blood Group</label>
                            <input type="text" id="blood_group-field" class="form-control" maxlength="5">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Qualification</label>
                            <input type="text" id="qualification-field" class="form-control">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Aadhar No</label>
                            <input type="text" id="aadhar_no-field" class="form-control" maxlength="20">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">PAN No</label>
                            <input type="text" id="pan_no-field" class="form-control" maxlength="10">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Father Name</label>
                            <input type="text" id="father_name-field" class="form-control">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Guardian Name</label>
                            <input type="text" id="guardian_name-field" class="form-control">
                        </div>

                    </div>

                    <hr>

                    <h6 class="fw-semibold text-primary">Address &amp; Contact</h6>

                    <div class="row">

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Present Address</label>
                            <textarea id="present_address-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Permanent Address</label>
                            <textarea id="permanent_address-field" class="form-control" rows="2"></textarea>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Emergency Mobile No</label>
                            <input type="text" id="emergency_mobile_no-field" class="form-control" maxlength="10">
                        </div>

                    </div>

                    <hr>

                    <h6 class="fw-semibold text-primary">Bank Details</h6>

                    <div class="row">

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bank A/C No</label>
                            <input type="text" id="bank_ac_no-field" class="form-control">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" id="bank_name-field" class="form-control">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label class="form-label">Branch</label>
                            <input type="text" id="bank_branch-field" class="form-control">
                        </div>

                    </div>

                    <div id="family-members-section" style="display: none;">

                        <hr>

                        <h6 class="fw-semibold text-primary">Family Members (For Discount Eligibility)</h6>

                        <div class="row">

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Family Member 1</label>
                                <input type="text" id="family_member_1-field" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Family Member 2</label>
                                <input type="text" id="family_member_2-field" class="form-control">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Family Member 3</label>
                                <input type="text" id="family_member_3-field" class="form-control">
                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>

                    <button type="submit" class="btn btn-success" id="add-btn">Save User</button>

                </div>

            </form>

        </div>

    </div>

</div>

@endsection

@section('script')

<script src="{{ URL::asset('build/libs/sweetalert2/sweetalert2.min.js') }}"></script>

<script>
    window.pageLabels = @json($pages);
    window.currentUserRole = @json($currentUserRole);
</script>

<script src="{{ URL::asset('build/libs/flatpickr/flatpickr.min.js') }}"></script>
<script src="{{ URL::asset('build/js/pages/flatpickr-dmy.init.js') }}"></script>

<script src="{{ URL::asset('build/js/pages/users.init.js') }}"></script>

@endsection
