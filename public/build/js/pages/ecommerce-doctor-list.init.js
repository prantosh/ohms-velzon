/*
|--------------------------------------------------------------------------
| LOAD DOCTORS
|--------------------------------------------------------------------------
*/
let currentPage = 1;
let lastPage = 1;
let perPage = 10;

let doctorDataTable = null;

/*
|--------------------------------------------------------------------------
| DATE FORMAT FUNCTIONS
|--------------------------------------------------------------------------
*/

function formatDate(dateString) {

    if (!dateString) return '';

    let date = new Date(dateString);

    let day =
        ('0' + date.getDate()).slice(-2);

    let month =
        ('0' + (date.getMonth() + 1)).slice(-2);

    let year =
        date.getFullYear();

    return day + '-' + month + '-' + year;
}
function formatDateForMysql(dateString) {

    if (!dateString) return '';

    let parts = dateString.split('-');

    return parts[2] + '-' + parts[1] + '-' + parts[0];
}

/*
|--------------------------------------------------------------------------
| INIT DATATABLE
|--------------------------------------------------------------------------
*/

function initDataTable() {

    /*
    |--------------------------------------------------------------------------
    | DESTROY OLD TABLE
    |--------------------------------------------------------------------------
    */

    if ($.fn.DataTable.isDataTable('#doctorTable')) {

        $('#doctorTable')
            .DataTable()
            .destroy();
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE OLD BUTTONS
    |--------------------------------------------------------------------------
    */

    $('#exportButtons').html('');

    /*
    |--------------------------------------------------------------------------
    | INIT DATATABLE
    |--------------------------------------------------------------------------
    */

    doctorDataTable =
        $('#doctorTable').DataTable({

           

            paging: false,

            searching: true,

            info: false,

            ordering: false,

            lengthChange: false,

            responsive: true,

            autoWidth: false,

            dom:
                
                "frt",

            buttons: [

                {
                    text: 'Excel',
                    className:
                        'btn btn-success btn-sm',

                    action: function () {

                        window.open(
                            '/doctor/export/excel',
                            '_blank'
                        );
                    }
                },

                {
                    text: 'Print',
                    className:
                        'btn btn-primary btn-sm',

                    action: function () {

                        window.open(
                            '/doctor/export/print',
                            '_blank'
                        );
                    }
                }
            ]
        });
    /*
    |--------------------------------------------------------------------------
    | MOVE BUTTONS
    |--------------------------------------------------------------------------
    */

    doctorDataTable
        .buttons()
        .container()
        .appendTo('#exportButtons');
    /*
|--------------------------------------------------------------------------
| SINGLE SEARCH BOX
|--------------------------------------------------------------------------
*/

    $('#customSearch').html('');

    $('#doctorTable_filter')
        .detach()
        .appendTo('#customSearch');

    $('#doctorTable_filter label')
        .addClass('mb-0 d-flex align-items-center gap-2');

    $('#doctorTable_filter input')
        .addClass('form-control form-control-sm')
        .attr('placeholder', 'Search doctors...')
        .css({
            width: '220px'
        });
}









async function loadDoctors(page = 1) {

    try {

        currentPage = page;

        const response =
            await fetch(
                `/doctor/list?page=${page}&per_page=${perPage}`,
                {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

        const result =
            await response.json();

        let tbody =
            document.querySelector(
                "#doctorTable tbody"
            );

        if ($.fn.DataTable.isDataTable('#doctorTable')) {

            $('#doctorTable')
                .DataTable()
                .clear()
                .destroy();
        }


        tbody.innerHTML = "";

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

        lastPage =
            result.pagination.last_page;

        document.querySelector(
            "#pageNumber"
        ).innerText =
            `Page ${result.pagination.current_page}`;

        document.querySelector(
            "#pagination-info"
        ).innerText =
            `Total Doctors: ${result.pagination.total}`;

        /*
        |--------------------------------------------------------------------------
        | BUTTON STATE
        |--------------------------------------------------------------------------
        */

        document.querySelector(
            "#prevPage"
        ).disabled =
            currentPage <= 1;

        document.querySelector(
            "#nextPage"
        ).disabled =
            currentPage >= lastPage;

        /*
        |--------------------------------------------------------------------------
        | NO DATA
        |--------------------------------------------------------------------------
        */

        if (
            !result.status ||
            !result.data.length
        ) {

            tbody.innerHTML = `

<tr>

    <td colspan="13"
        class="text-center">

        No doctor data found

    </td>

</tr>
`;

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | LOOP
        |--------------------------------------------------------------------------
        */

        result.data.forEach(raw => {

            let badge = '';

            if (raw.status === 'Active') {

                badge = `

<span class="badge bg-success-subtle text-success">

    Active

</span>
`;

            } else {

                badge = `

<span class="badge bg-danger-subtle text-danger">

    Block

</span>
`;
            }

            tbody.innerHTML += `

<tr>

    <td>

        <div class="form-check">

            <input class="form-check-input"
                   type="checkbox">

        </div>

    </td>

    <td>

        <a href="javascript:void(0)"
           class="fw-medium link-primary">

           #DOC${raw.id}

        </a>

    </td>

    <td>

        <img src="${raw.image_url}"
     onerror="this.src='/build/images/users/avatar-1.jpg'"
             width="40"
             height="40"
             class="rounded-circle object-fit-cover">

    </td>

    <td>${raw.doctor_code}</td>
    <td>${raw.doctor_name}</td>
    <td>${raw.registration_no}</td>
    <td>${raw.gender ?? ''}</td>
    <td>${raw.mobile_no ?? ''}</td>
    <td>${raw.specialisation ?? ''}</td>
    <td>${raw.qualification ?? ''}</td>
    <td>${formatDate(raw.joining_date)}</td>
    <td>${raw.consultation_fee_total ?? ''}</td>
    <td>${badge}</td>

    <td>

        <ul class="list-inline hstack gap-2 mb-0">

            <li class="list-inline-item">

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="text-primary edit-item-btn"
                   data-doctor_code="${raw.doctor_code}"
                   data-id="${raw.id}"
                   data-doctor_name="${raw.doctor_name}"
                   data-email="${raw.email}"
                   data-mobile_no="${raw.mobile_no}"
                   data-joining_date="${formatDate(raw.joining_date)}"
                   data-status="${raw.status}"
                   data-gender="${raw.gender}"
                   data-specialisation_code="${raw.specialisation_code}"
                   data-specialisation_codes='${JSON.stringify(raw.specialisation_codes ?? [])}'
                   data-qualification="${raw.qualification}"
                   data-consultation_fee_total="${raw.consultation_fee_total}"
                   data-date_of_birth="${formatDate(raw.date_of_birth)}"
                   data-alternate_mobile_no="${raw.alternate_mobile_no ?? ''}"
                   data-experience_years="${raw.experience_years ?? ''}"
                   data-registration_no="${raw.registration_no ?? ''}"
                   data-consultation_fee_doctor="${raw.consultation_fee_doctor ?? ''}"
                   data-consultation_fee_doctor_discounted_patient="${raw.consultation_fee_doctor_discounted_patient ?? ''}"
                   data-discount="${raw.discount ?? ''}"
                   data-remarks="${raw.remarks ?? ''}"
                   data-image="${raw.image_url ?? ''}"
                 >
                   
                                        <i class="ri-pencil-fill fs-16"></i>

                </a>

            </li>

            <li class="list-inline-item">

                <a href="javascript:void(0)"
                   class="text-danger delete-item-btn"
                   data-id="${raw.id}">

                    <i class="ri-delete-bin-5-fill fs-16"></i>

                </a>

            </li>

        </ul>

    </td>

</tr>
`;

        });

        /*
        |--------------------------------------------------------------------------
        | INIT EXPORT BUTTONS
        |--------------------------------------------------------------------------
        */

        requestAnimationFrame(() => {

            initDataTable();

        });

    }

    catch (error) {

        console.error(error);
    }
}

/*
|--------------------------------------------------------------------------
| PAGINATION BUTTONS
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "click",
    function (e) {

        if (e.target.id === "prevPage") {

            if (currentPage > 1) {

                loadDoctors(currentPage - 1);
            }
        }

        if (e.target.id === "nextPage") {

            if (currentPage < lastPage) {

                loadDoctors(currentPage + 1);
            }
        }
    }
);


document.addEventListener(
    "change",
    function (e) {

        if (e.target.id === "perPage") {

            perPage =
                parseInt(e.target.value);

            currentPage = 1;

            loadDoctors(1);
        }
    }
);

/*
|--------------------------------------------------------------------------
| SINGLE FORM SUBMIT
|--------------------------------------------------------------------------
*/

const doctorForm =
    document.querySelector(".tablelist-form");

if (doctorForm) {

    doctorForm.onsubmit =
        async function (e) {

            e.preventDefault();

            const submitButton =
                document.querySelector("#add-btn");

            /*
            |--------------------------------------------------------------------------
            | PREVENT DOUBLE CLICK
            |--------------------------------------------------------------------------
            */

            if (submitButton.disabled) {

                return;
            }

            submitButton.disabled = true;

            Swal.fire({
                title: 'Saving....',
                text: 'Please wait',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function () {
                    Swal.showLoading();
                }
            });

            try {

                /*
                |--------------------------------------------------------------------------
                | EDIT ID
                |--------------------------------------------------------------------------
                */

                const editId =
                    document.querySelector("#edit-id").value;

                /*
                |--------------------------------------------------------------------------
                | FORM DATA
                |--------------------------------------------------------------------------
                */

                const formData =
                    new FormData();
                formData.append(
                    "doctor_code",
                    document.querySelector("#doctor_code-field").value
                );
                formData.append(
                    "doctor_name",
                    document.querySelector("#doctor_name-field").value
                );

                formData.append(
                    "email",
                    document.querySelector("#email-field").value
                );

                formData.append(
                    "mobile_no",
                    document.querySelector("#mobile_no-field").value
                );

                formData.append(
                    "joining_date",
                    formatDateForMysql(
                        document.querySelector("#joining_date-field").value
                    )
                );

                formData.append(
                    "status",
                    document.querySelector("#status-field").value
                );
                formData.append(
                    "gender",
                    document.querySelector("#gender-field").value
                );

                const selectedSpecialisationCodes = Array.from(
                    document.querySelector("#specialisation_codes-field")
                        .selectedOptions
                ).map((option) => option.value);

                selectedSpecialisationCodes.forEach((code) => {
                    formData.append("specialisation_codes[]", code);
                });

                formData.append(
                    "qualification",
                    document.querySelector("#qualification-field").value
                );

                formData.append(
                    "consultation_fee_total",
                    document.querySelector("#consultation_fee_total-field").value
                );
                formData.append(
                    "date_of_birth",
                    formatDateForMysql(
                        document.querySelector("#date_of_birth-field").value
                    )
                );

                formData.append(
                    "alternate_mobile_no",
                    document.querySelector("#alternate_mobile_no-field").value
                );

                formData.append(
                    "experience_years",
                    document.querySelector("#experience_years-field").value
                );

                formData.append(
                    "registration_no",
                    document.querySelector("#registration_no-field").value
                );

                formData.append(
                    "consultation_fee_doctor",
                    document.querySelector("#consultation_fee_doctor-field").value
                );

                formData.append(
                    "consultation_fee_doctor_discounted_patient",
                    document.querySelector("#consultation_fee_doctor_discounted_patient-field").value
                );

                formData.append(
                    "discount",
                    document.querySelector("#discount-field").value
                );

                formData.append(
                    "remarks",
                    document.querySelector("#remarks").value
                );
                /*
                |--------------------------------------------------------------------------
                | IMAGE
                |--------------------------------------------------------------------------
                */

                const imageInput =
                    document.querySelector("#image-field");

                if (
                    imageInput &&
                    imageInput.files.length > 0
                ) {

                    formData.append(
                        "doctor_image",
                        imageInput.files[0]
                    );
                }










                /*
                |--------------------------------------------------------------------------
                | URL
                |--------------------------------------------------------------------------
                */

                let url =
                    '/doctor/add';

                if (editId !== '') {

                    url =
                        `/doctor/update/${editId}`;
                }

                /*
                |--------------------------------------------------------------------------
                | AJAX
                |--------------------------------------------------------------------------
                */

                const response =
                    await fetch(url, {

                        method: 'POST',

                        headers: {

                            'X-CSRF-TOKEN':
                                document
                                    .querySelector('meta[name="csrf-token"]')
                                    .getAttribute('content'),

                            'Accept':
                                'application/json'
                        },

                        body: formData
                    });

                /*
                |--------------------------------------------------------------------------
                | VALIDATION ERROR
                |--------------------------------------------------------------------------
                */

                if (!response.ok) {

                    const errorData =
                        await response.json();

                    Swal.fire({

                        icon: 'error',

                        title: 'Validation Error',

                        text:
                            errorData.message ||
                            JSON.stringify(errorData.errors) ||
                            'Something went wrong'
                    });

                    

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                const result =
                    await response.json();

                Swal.fire({

                    icon: 'success',

                    title: 'Success',

                    text: result.message,

                    timer: 2000,

                    showConfirmButton: false
                });

                doctorForm.reset();

                $("#specialisation_codes-field").val(null).trigger("change");

                document.querySelector("#edit-id").value = '';

                submitButton.innerText =
                    "Save Doctor";

                /*
                |--------------------------------------------------------------------------
                | CLOSE MODAL
                |--------------------------------------------------------------------------
                */

                /*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

                const modalElement =
                    document.getElementById('showModal');

                const modal =
                    bootstrap.Modal.getInstance(modalElement);

                if (modal) {

                    modal.hide();
                }

                /*
                |--------------------------------------------------------------------------
                | RELOAD TABLE AFTER MODAL CLOSE
                |--------------------------------------------------------------------------
                */

                setTimeout(() => {

                    loadDoctors(currentPage);

                }, 300);

            } catch (error) {

                console.error(error);

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: error.toString()
                });

            } finally {

                /*
                |--------------------------------------------------------------------------
                | ENABLE BUTTON
                |--------------------------------------------------------------------------
                */

                submitButton.disabled = false;
            }
        };
}

/*
|--------------------------------------------------------------------------
| EDIT DOCTORS
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "click",
    function (e) {

        const button =
            e.target.closest(".edit-item-btn");

        if (!button) return;

        /*
        |--------------------------------------------------------------------------
        | GET VALUES
        |--------------------------------------------------------------------------
        */

        const id =
            button.getAttribute("data-id");
        const doctor_code =
            button.getAttribute(
                "data-doctor_code"
            );

        const doctor_name =
            button.getAttribute(
                "data-doctor_name"
            );

        const email =
            button.getAttribute(
                "data-email"
            );

        const mobile_no =
            button.getAttribute(
                "data-mobile_no"
            );

        const joining_date =
            button.getAttribute(
                "data-joining_date"
            );

        const status =
            button.getAttribute(
                "data-status"
            );
        const gender =
            button.getAttribute(
                "data-gender"
            );

        const specialisation_code =
            button.getAttribute(
                "data-specialisation_code"
            );

        let specialisation_codes = [];
        try {
            specialisation_codes = JSON.parse(
                button.getAttribute("data-specialisation_codes") || "[]"
            );
        } catch (e) {
            specialisation_codes = [];
        }
        if (!specialisation_codes.length && specialisation_code) {
            specialisation_codes = [specialisation_code];
        }

        const qualification =
            button.getAttribute(
                "data-qualification"
            );

        const consultation_fee_total =
            button.getAttribute(
                "data-consultation_fee_total"
            );
        const date_of_birth =
            button.getAttribute(
                "data-date_of_birth"
            );

        const alternate_mobile_no =
            button.getAttribute(
                "data-alternate_mobile_no"
            );

        const experience_years =
            button.getAttribute(
                "data-experience_years"
            );

        const registration_no =
            button.getAttribute(
                "data-registration_no"
            );

        const consultation_fee_doctor =
            button.getAttribute(
                "data-consultation_fee_doctor"
            );

        const consultation_fee_doctor_discounted_patient =
            button.getAttribute(
                "data-consultation_fee_doctor_discounted_patient"
            );

        const discount =
            button.getAttribute(
                "data-discount"
            );

        const remarks =
            button.getAttribute(
                "data-remarks"
            );

        const image =
            button.getAttribute(
                "data-image"
            );
        /*
        |--------------------------------------------------------------------------
        | SET VALUES
        |--------------------------------------------------------------------------
        */

        document.querySelector("#edit-id").value =
            id;
        document.querySelector("#doctor_code-field").value =
            doctor_code;
        document.querySelector("#doctor_name-field").value =
            doctor_name;

        document.querySelector("#email-field").value =
            email;

        document.querySelector("#mobile_no-field").value =
            mobile_no;

        document.querySelector("#joining_date-field").value =
            joining_date;

        document.querySelector("#status-field").value =
            status;
        document.querySelector("#gender-field").value =
            gender;

        setTimeout(() => {

            $("#specialisation_codes-field")
                .val(
                    specialisation_codes.map((code) =>
                        String(code).trim()
                    )
                )
                .trigger("change");

        }, 300);

        document.querySelector("#qualification-field").value =
            qualification;

        document.querySelector("#consultation_fee_total-field").value =
            consultation_fee_total;
        document.querySelector("#date_of_birth-field").value =
            date_of_birth;

        document.querySelector("#alternate_mobile_no-field").value =
            alternate_mobile_no;

        document.querySelector("#experience_years-field").value =
            experience_years;

        document.querySelector("#registration_no-field").value =
            registration_no;

        document.querySelector("#consultation_fee_doctor-field").value =
            consultation_fee_doctor;

        document.querySelector("#consultation_fee_doctor_discounted_patient-field").value =
            consultation_fee_doctor_discounted_patient;

        document.querySelector("#discount-field").value =
            discount;

        document.querySelector("#remarks").value =
            remarks;
        if (image) {

            document.querySelector("#imagePreview").src =
                image;

            document.querySelector("#imagePreview").style.display =
                'block';
        }
        /*
        |--------------------------------------------------------------------------
        | BUTTON TEXT
        |--------------------------------------------------------------------------
        */

        document.querySelector("#add-btn").innerText =
            "Update Doctor";
        document.querySelector("#modal-title").innerText =
            "Update Doctor";
    });

/*
|--------------------------------------------------------------------------
| DELETE DOCTOR
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "click",
    async function (e) {

        const button =
            e.target.closest(".delete-item-btn");

        if (!button) return;

        /*
        |--------------------------------------------------------------------------
        | CONFIRM DELETE
        |--------------------------------------------------------------------------
        */

        const confirmDelete =
            await Swal.fire({

                title: 'Are you sure?',

                text: "Doctor will be deleted",

                icon: 'warning',

                showCancelButton: true,

                confirmButtonColor: '#d33',

                cancelButtonColor: '#3085d6',

                confirmButtonText: 'Yes, delete it!'
            });

        if (!confirmDelete.isConfirmed) {

            return;
        }

        const id =
            button.dataset.id;

        try {

            const response =
                await fetch(
                    `/doctor/delete/${id}`,
                    {

                        method: 'DELETE',

                        headers: {

                            'X-CSRF-TOKEN':
                                document
                                    .querySelector(
                                        'meta[name="csrf-token"]'
                                    )
                                    .getAttribute('content')
                        }
                    });

            const result =
                await response.json();

            Swal.fire({

                icon: 'success',

                title: 'Success',

                text: result.message,

                timer: 2000,

                showConfirmButton: false
            });

            /*
            |--------------------------------------------------------------------------
            | RELOAD TABLE
            |--------------------------------------------------------------------------
            */

            loadDoctors();

        } catch (error) {

            console.error(error);
        }
    });

/*
|--------------------------------------------------------------------------
| RESET MODAL
|--------------------------------------------------------------------------
*/

const doctorModal =
    document.getElementById(
        'showModal'
    );

if (doctorModal) {

    doctorModal.addEventListener(
        'hidden.bs.modal',

        function () {

            doctorForm.reset();

            $("#specialisation_codes-field").val(null).trigger("change");

            document.querySelector("#edit-id").value = '';

            document.querySelector("#add-btn").innerText =
                "Save Doctor";
            document.querySelector("#modal-title").innerText =
                "Add Doctor";
            document.querySelector("#imagePreview").src = '';

            document.querySelector("#imagePreview").style.display = 'none';

            loadDoctors(currentPage);
        }
    );
}



/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/
document.addEventListener(
    "click",
    function (e) {

        if (e.target.closest("#addDoctorBtn")) {

            document.querySelector("#modal-title").innerText =
                "Add Doctor";

            document.querySelector("#add-btn").innerText =
                "Save Doctor";
        }
    }
);
document.addEventListener(
    "DOMContentLoaded",

    function () {

        loadDoctors();

        $('#specialisation_codes-field').select2({

            dropdownParent: $('#showModal'),

            width: '100%',

            placeholder: 'Select Specialisation(s)'
        });
    }
);


    document
    .getElementById('image-field')
    .addEventListener('change', function (e) {

        const file = e.target.files[0];

    const preview =
    document.getElementById('imagePreview');

    if (file) {

        preview.src =
        URL.createObjectURL(file);

    preview.style.display = 'block';

        } else {

        preview.src = '';

    preview.style.display = 'none';
        }
});

