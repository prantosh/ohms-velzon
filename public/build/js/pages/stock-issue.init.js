let currentPage = 1;
let lastPage = 1;
let perPage = 10;
let searchTerm = '';
let rowCounter = 0;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ==========================================================
   LIST
========================================================== */

async function loadIssues(page = 1) {

    currentPage = page;

    const response = await fetch(
        `/stock-issue/list?page=${page}&per_page=${perPage}&search=${encodeURIComponent(searchTerm)}`
    );

    const result = await response.json();

    let tbody = document.querySelector('#issueTableBody');

    tbody.innerHTML = '';

    lastPage = result.pagination.last_page;

    document.querySelector('#pageNumber').innerText = `Page ${result.pagination.current_page}`;
    document.querySelector('#pagination-info').innerText = `Total Records : ${result.pagination.total}`;

    let startSl = (result.pagination.current_page - 1) * perPage;

    if (!result.data.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No stock issues found.</td></tr>';
        return;
    }

    result.data.forEach((raw, index) => {

        tbody.innerHTML += `
        <tr>
            <td>${startSl + index + 1}</td>
            <td>${raw.issue_no}</td>
            <td>${raw.issue_date_fmt ?? ''}</td>
            <td>${escapeHtml(raw.issued_to_name ?? '')}</td>
            <td>
                <button class="btn btn-sm btn-soft-info view-issue-btn" data-id="${raw.id}" title="View">
                    <i class="ri-eye-fill"></i>
                </button>
            </td>
        </tr>
        `;
    });
}

/* ==========================================================
   ITEM ROW MANAGEMENT
========================================================== */

function addIssueItemRow() {

    rowCounter++;

    let tr = document.createElement('tr');
    tr.id = `issue-row-${rowCounter}`;

    tr.innerHTML = `
        <td class="item-search-wrap">
            <input type="text" class="form-control item-search-input" placeholder="Search item...">
            <input type="hidden" class="item-id-field">
            <div class="item-suggestions"></div>
        </td>
        <td><input type="text" class="form-control uom-field" readonly></td>
        <td><input type="text" class="form-control stock-field" readonly></td>
        <td><input type="number" step="0.01" min="0.01" class="form-control qty-field" value="1"></td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-soft-danger remove-row-btn"><i class="ri-delete-bin-5-line"></i></button>
        </td>
    `;

    document.querySelector('#issueItemsBody').appendChild(tr);
}

document.getElementById('btnAddIssueRow').addEventListener('click', addIssueItemRow);

async function searchItemsForRow(row, term) {

    let suggestionsBox = row.querySelector('.item-suggestions');

    if (!term) {
        suggestionsBox.style.display = 'none';
        suggestionsBox.innerHTML = '';
        return;
    }

    const response = await fetch(`/inventory-item/search-by-name?term=${encodeURIComponent(term)}`);
    const result = await response.json();

    if (!result.status || !result.items.length) {
        suggestionsBox.innerHTML = '<div class="suggestion-item text-muted">No items found</div>';
        suggestionsBox.style.display = 'block';
        return;
    }

    suggestionsBox.innerHTML = result.items.map(item => `
        <div class="suggestion-item"
             data-id="${item.id}"
             data-name="${escapeHtml(item.item_name)}"
             data-uom="${escapeHtml(item.uom)}"
             data-stock="${item.current_stock}">
            <strong>${escapeHtml(item.item_name)}</strong> (${escapeHtml(item.item_code)}) - ${escapeHtml(item.uom)}, Stock: ${item.current_stock}
        </div>
    `).join('');

    suggestionsBox.style.display = 'block';
}

document.getElementById('issueItemsBody').addEventListener('input', function (e) {

    let row = e.target.closest('tr');
    if (!row) return;

    if (e.target.classList.contains('item-search-input')) {
        searchItemsForRow(row, e.target.value.trim());
    }
});

document.getElementById('issueItemsBody').addEventListener('click', function (e) {

    let suggestion = e.target.closest('.suggestion-item');

    if (suggestion && suggestion.dataset.id) {

        let row = suggestion.closest('tr');

        row.querySelector('.item-search-input').value = suggestion.dataset.name;
        row.querySelector('.item-id-field').value = suggestion.dataset.id;
        row.querySelector('.uom-field').value = suggestion.dataset.uom;
        row.querySelector('.stock-field').value = `${suggestion.dataset.stock} ${suggestion.dataset.uom}`;

        row.querySelector('.item-suggestions').style.display = 'none';
        return;
    }

    let removeBtn = e.target.closest('.remove-row-btn');

    if (removeBtn) {
        removeBtn.closest('tr').remove();
    }
});

document.addEventListener('click', function (e) {

    if (!e.target.closest('.item-search-wrap')) {
        document.querySelectorAll('.item-suggestions').forEach(box => box.style.display = 'none');
    }
});

/* ==========================================================
   CREATE ISSUE
========================================================== */

document.getElementById('btnCreateIssue').addEventListener('click', function () {

    document.getElementById('issueForm').reset();
    document.getElementById('issueItemsBody').innerHTML = '';
    setFlatpickrValue('issue_date-field', new Date().toISOString().substring(0, 10));

    addIssueItemRow();

    new bootstrap.Offcanvas(document.getElementById('issueOffcanvas')).show();
});

document.getElementById('issueForm').addEventListener('submit', async function (e) {

    e.preventDefault();

    let items = [];

    document.querySelectorAll('#issueItemsBody tr').forEach(row => {

        let itemId = row.querySelector('.item-id-field').value;

        if (!itemId) return;

        items.push({
            inventory_item_id: itemId,
            uom: row.querySelector('.uom-field').value,
            issue_qty: row.querySelector('.qty-field').value
        });
    });

    if (!items.length) {
        Swal.fire({ icon: 'warning', title: 'Add at least one item to issue.' });
        return;
    }

    let payload = {
        issue_date: document.getElementById('issue_date-field').value,
        issued_to_name: document.getElementById('issued_to_name-field').value,
        remarks: document.getElementById('remarks-field').value,
        items: items
    };

    const response = await fetch('/stock-issue/store', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok || !result.status) {

        Swal.fire({ icon: 'error', title: 'Error', text: result.message ?? 'Unable to record stock issue.' });
        return;
    }

    Swal.fire({ icon: 'success', title: 'Success', text: result.message });

    bootstrap.Offcanvas.getInstance(document.getElementById('issueOffcanvas')).hide();

    loadIssues(1);
});

/* ==========================================================
   VIEW / PAGINATION
========================================================== */

document.addEventListener('click', async function (e) {

    if (e.target.closest('#prevPage')) {
        if (currentPage > 1) loadIssues(currentPage - 1);
        return;
    }

    if (e.target.closest('#nextPage')) {
        if (currentPage < lastPage) loadIssues(currentPage + 1);
        return;
    }

    let viewBtn = e.target.closest('.view-issue-btn');

    if (viewBtn) {

        const response = await fetch(`/stock-issue/show/${viewBtn.dataset.id}`);
        const result = await response.json();

        if (!result.status) return;

        let issue = result.data;

        let rows = issue.items.map(item => `
            <tr>
                <td>${escapeHtml(item.item.item_name)}</td>
                <td>${item.uom}</td>
                <td class="text-end">${Number(item.issue_qty).toFixed(2)}</td>
                <td class="text-end">${Number(item.unit_rate).toFixed(2)}</td>
                <td class="text-end">${Number(item.amount).toFixed(2)}</td>
            </tr>
        `).join('');

        Swal.fire({
            title: issue.issue_no,
            html: `
                <div class="text-start">
                    <p><strong>Issued To:</strong> ${escapeHtml(issue.issued_to_name ?? '-')}</p>
                    <table class="table table-bordered table-sm">
                        <thead><tr><th>Item</th><th>UOM</th><th>Qty</th><th>Rate</th><th>Amount</th></tr></thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `,
            width: 650
        });
    }
});

document.addEventListener('change', function (e) {

    if (e.target.id === 'perPage') {
        perPage = parseInt(e.target.value);
        loadIssues(1);
    }
});

let searchDebounce;

document.getElementById('searchInput').addEventListener('input', function (e) {

    clearTimeout(searchDebounce);

    searchDebounce = setTimeout(() => {
        searchTerm = e.target.value;
        loadIssues(1);
    }, 350);
});

loadIssues();
