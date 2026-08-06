"use strict";

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

function escapeHtml(value) {

    return (value ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function renderGroups(invoiceNo, confirmed, groups) {

    let tbody = document.querySelector('#groupsTableBody');

    tbody.innerHTML = '';

    groups.forEach(group => {

        let printUrl = '/diagnostic-test-report/print?' + new URLSearchParams({
            invoice_no: invoiceNo,
            test_group_code: group.test_group_code ?? ''
        }).toString();

        tbody.innerHTML += `
        <tr>

            <td class="text-center">${escapeHtml(group.test_group_name)}</td>

            <td class="text-center">${group.test_count}</td>

            <td class="text-center">${group.result_count} / ${group.test_count}</td>

            <td class="text-center">
                <a href="${printUrl}"
                   target="_blank"
                   class="btn btn-sm btn-info ${confirmed ? '' : 'disabled'}"
                   ${confirmed ? '' : 'tabindex="-1" aria-disabled="true"'}
                   title="${confirmed ? 'Print Report' : 'Confirm the test report in Test Result Entry before printing'}">
                    <i class="ri-printer-line"></i>
                    Print
                </a>
            </td>

        </tr>
        `;
    });
}

async function searchInvoice() {

    let invoiceNo = document.querySelector('#invoiceNoInput').value.trim();

    document.querySelector('#invoiceInfoWrap').style.display = 'none';
    document.querySelector('#invoiceNotFoundMsg').style.display = 'none';
    document.querySelector('#notConfirmedMsg').style.display = 'none';
    document.querySelector('#groupsTableWrap').style.display = 'none';

    if (!invoiceNo) {
        return;
    }

    const response = await fetch('/diagnostic-test-report/search', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        },
        body: JSON.stringify({ invoice_no: invoiceNo })
    });

    const result = await response.json();

    if (!result.status) {

        document.querySelector('#invoiceNotFoundMsg').style.display = 'block';
        return;
    }

    document.querySelector('#info-invoice_no').innerText = result.invoice.invoice_no ?? '';
    document.querySelector('#info-invoice_date').innerText = result.invoice.invoice_date ?? '';
    document.querySelector('#info-patient_name').innerText = result.invoice.patient_name ?? '';
    document.querySelector('#info-confirmed_status').innerHTML = result.confirmed
        ? '<span class="badge bg-success">Confirmed</span>'
        : '<span class="badge bg-warning">Not Confirmed</span>';

    document.querySelector('#invoiceInfoWrap').style.display = 'block';

    if (!result.confirmed) {
        document.querySelector('#notConfirmedMsg').style.display = 'block';
    }

    renderGroups(result.invoice.invoice_no, result.confirmed, result.groups);

    document.querySelector('#groupsTableWrap').style.display = 'block';
}

document.getElementById('btnSearchInvoice').addEventListener('click', searchInvoice);

document.getElementById('invoiceNoInput').addEventListener('keypress', function (e) {

    if (e.which === 13) {

        e.preventDefault();
        searchInvoice();
    }
});
