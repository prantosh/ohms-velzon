let currentPage = 1;
let lastPage = 1;
let perPage = 10;

async function loadEquipmentCategories(page = 1) {
    currentPage = page;

    const response =
        await fetch(
            `/equipment-category/list?page=${page}&per_page=${perPage}`
        );

    const result = await response.json();

    let tbody =
        document.querySelector(
            "#equipmentCategoryTable tbody"
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

    let startSl =
        (result.pagination.current_page - 1) * perPage;

    result.data.forEach((raw, index) => {

        let statusBadge =
            raw.status === 'ACTIVE'
                ? '<span class="badge bg-success">ACTIVE</span>'
                : '<span class="badge bg-danger">INACTIVE</span>';

        tbody.innerHTML += `
        <tr>

            <td>
                <input type="checkbox">
            </td>

            <td>${startSl + index + 1}</td>

            <td>${raw.category_code}</td>

            <td>${raw.category_name}</td>

            <td>${raw.total_quantity}</td>

            <td>${raw.available_quantity}</td>

            <td>${statusBadge}</td>

            <td>${raw.created_by_name ?? ''}</td>

            <td>${raw.created_dt ?? ''}</td>

            <td>

                <a href="#showModal"
                   data-bs-toggle="modal"
                   class="btn btn-sm btn-soft-success edit-item-btn me-1"
                   data-id="${raw.id}"
                   data-code="${raw.category_code}"
                   data-name="${raw.category_name}"
                   data-quantity="${raw.total_quantity}"
                   data-remarks="${raw.remarks ?? ''}"
                   data-status="${raw.status}"
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
            'category_name',
            document.querySelector(
                "#category_name-field"
            ).value
        );

        formData.append(
            'total_quantity',
            document.querySelector(
                "#total_quantity-field"
            ).value
        );

        formData.append(
            'remarks',
            document.querySelector(
                "#remarks-field"
            ).value
        );

        formData.append(
            'status',
            document.querySelector(
                "#status-field"
            ).value
        );

        let url =
            '/equipment-category/store';

        if (editId) {
            url =
                `/equipment-category/update/${editId}`;
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

        if (!response.ok) {

            let errorText =
                result.errors
                    ? Object.values(result.errors).flat().join(', ')
                    : (result.message ?? 'Unable to save Equipment Category.');

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorText
            });

            return;
        }

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

        loadEquipmentCategories(currentPage);
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
                "Add Equipment Category";

            document.querySelector(
                "#add-btn"
            ).innerText =
                "Save Category";
        }
    );
document.addEventListener("click", async function (e) {

    // Previous Page
    if (e.target.closest("#prevPage")) {
        if (currentPage > 1) {
            loadEquipmentCategories(currentPage - 1);
        }
        return;
    }

    // Next Page
    if (e.target.closest("#nextPage")) {
        if (currentPage < lastPage) {
            loadEquipmentCategories(currentPage + 1);
        }
        return;
    }

    // EDIT
    let editBtn = e.target.closest(".edit-item-btn");
    if (editBtn) {

        document.querySelector("#modal-title").innerText =
            "Update Equipment Category";

        document.querySelector("#add-btn").innerText =
            "Update Category";

        document.querySelector("#edit-id").value =
            editBtn.dataset.id;

        document.querySelector("#category_code-field").value =
            editBtn.dataset.code;

        document.querySelector("#category_name-field").value =
            editBtn.dataset.name;

        document.querySelector("#total_quantity-field").value =
            editBtn.dataset.quantity;

        document.querySelector("#remarks-field").value =
            editBtn.dataset.remarks;

        document.querySelector("#status-field").value =
            editBtn.dataset.status;

        return;
    }

    // DELETE
    let deleteBtn = e.target.closest(".delete-item-btn");

    if (deleteBtn) {

        let id = deleteBtn.dataset.id;

        let confirm = await Swal.fire({
            title: "Delete Equipment Category?",
            text: "This record will be permanently deleted.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#0ab39c",
            cancelButtonColor: "#f06548",
            confirmButtonText: "Yes, Delete"
        });

        if (!confirm.isConfirmed) return;

        let response = await fetch(`/equipment-category/delete/${id}`, {
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
            loadEquipmentCategories(currentPage);
        }
    }
});
document.addEventListener(
    "change",
    function (e) {

        if (e.target.id === "perPage") {

            perPage =
                parseInt(e.target.value);

            loadEquipmentCategories(1);
        }
    }
);

loadEquipmentCategories();
