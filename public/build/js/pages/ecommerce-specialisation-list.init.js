let currentPage = 1;
let lastPage = 1;
let perPage = 10;

async function loadSpecialisations(page = 1) {
    currentPage = page;

    const response =
        await fetch(
            `/specialisation/list?page=${page}&per_page=${perPage}`
        );

    const result = await response.json();

    let tbody =
        document.querySelector(
            "#specialisationTable tbody"
        );

    tbody.innerHTML = "";

    lastPage =
        result.pagination.last_page;

    document.querySelector(
        "#pageNumber"
    ).innerText =
        `Page ${result.pagination.current_page}`;

    document.querySelector(
        "#pagination-info"
    ).innerText =
        `Total Records : ${result.pagination.total}`;

    result.data.forEach(raw => {

        tbody.innerHTML += `
        <tr>

            <td>
                <input type="checkbox">
            </td>

            <td>#SP${raw.id}</td>

            <td>${raw.category}</td>

            <td>${raw.created_dt ?? ''}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   data-category="${raw.category}"
                   title="Edit">

                    <i class="ri-pencil-fill"></i>
                </a>

                <a href="javascript:void(0)"
                   class="btn btn-sm btn-soft-danger delete-item-btn"
                   data-id="${raw.id}"
                   title="Delete">

                    <i class="ri-delete-bin-5-fill"></i>
                </a>

            </td>

        </tr>
        `;
    });
}

document.querySelector(".tablelist-form")
    .addEventListener("submit", async function (e) {

        e.preventDefault();

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

        let editId =
            document.querySelector("#edit-id").value;

        let formData =
            new FormData();

        formData.append(
            'category',
            document.querySelector(
                "#category-field"
            ).value
        );

        let url =
            '/specialisation/add';

        if (editId) {
            url =
                `/specialisation/update/${editId}`;
        }

        const response =
            await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN':
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                },
                body: formData
            });

        const result =
            await response.json();

        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: result.message
        });

        bootstrap.Modal
            .getInstance(
                document.getElementById(
                    'showModal'
                )
            )
            .hide();

        this.reset();

        loadSpecialisations(currentPage);
    });
document.getElementById('showModal')
    .addEventListener(
        'hidden.bs.modal',
        function () {

            document.querySelector(
                ".tablelist-form"
            ).reset();

            document.querySelector(
                "#edit-id"
            ).value = '';

            document.querySelector(
                "#modal-title"
            ).innerText =
                "Add New Specialisation";

            document.querySelector(
                "#add-btn"
            ).innerText =
                "Save Specialisation";
        }
    );
document.addEventListener("click", async function (e) {

    // Previous Page
    if (e.target.closest("#prevPage")) {
        if (currentPage > 1) {
            loadSpecialisations(currentPage - 1);
        }
        return;
    }

    // Next Page
    if (e.target.closest("#nextPage")) {
        if (currentPage < lastPage) {
            loadSpecialisations(currentPage + 1);
        }
        return;
    }

    // EDIT
    let editBtn = e.target.closest(".edit-item-btn");
    if (editBtn) {

        document.querySelector("#modal-title").innerText =
            "Update Specialisation";

        document.querySelector("#add-btn").innerText =
            "Update Specialisation";

        document.querySelector("#edit-id").value =
            editBtn.dataset.id;

        document.querySelector("#category-field").value =
            editBtn.dataset.category;

        return;
    }

    // DELETE
    let deleteBtn = e.target.closest(".delete-item-btn");

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: "Delete Specialisation?",
            text: "This record will be permanently deleted.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#0ab39c",
            cancelButtonColor: "#f06548",
            confirmButtonText: "Yes, Delete"
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/specialisation/delete/${id}`, {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN":
                    document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content
            }
        });

        let result = await response.json();

        Swal.fire({
            icon: result.status ? "success" : "error",
            title: result.status ? "Deleted" : "Error",
            text: result.message
        });

        if (result.status) {
            loadSpecialisations(currentPage);
        }
    }
});
document.addEventListener(
    "change",
    function (e) {

        if (e.target.id === "perPage") {

            perPage =
                parseInt(e.target.value);

            loadSpecialisations(1);
        }
    }
);

loadSpecialisations();
