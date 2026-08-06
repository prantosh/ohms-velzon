async function loadCustomers() {

    try {

        const response = await fetch('/customer_api.php');

        const result = await response.json();

        console.log(result);

        let tbody = document.querySelector("#customerTable tbody");

        tbody.innerHTML = "";

        if (!result.status || !result.data.length) {

            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center">
                        No customer data found
                    </td>
                </tr>
            `;

            return;
        }

        result.data.forEach(raw => {

            let badge = '';

            if (raw.status === 'Active') {

                badge = `
                    <span class="badge bg-success-subtle text-success text-uppercase">
                        Active
                    </span>
                `;

            } else {

                badge = `
                    <span class="badge bg-danger-subtle text-danger text-uppercase">
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

                           #CUS${raw.id}

                        </a>
                    </td>

                    <td>${raw.customer_name}</td>

                    <td>${raw.email}</td>

                    <td>${raw.phone}</td>

                    <td>${raw.date}</td>

                    <td>${badge}</td>

                    <td>

                        <ul class="list-inline hstack gap-2 mb-0">

                            <li class="list-inline-item">

                                <a href="#showModal"
                                   data-bs-toggle="modal"
                                   class="text-primary">

                                   <i class="ri-pencil-fill fs-16"></i>

                                </a>

                            </li>

                            <li class="list-inline-item">

                                <a class="text-danger"
                                   href="#deleteRecordModal"
                                   data-bs-toggle="modal">

                                   <i class="ri-delete-bin-5-fill fs-16"></i>

                                </a>

                            </li>

                        </ul>

                    </td>

                </tr>
            `;
        });

    } catch (error) {

        console.error("FETCH ERROR:", error);
    }
}
document.addEventListener("DOMContentLoaded", function () { loadCustomers(); });
