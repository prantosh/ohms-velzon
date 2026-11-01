function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').content;
}

let currentPatient = null;

async function searchPatient() {

    let patientId = document.getElementById('patientIdInput').value.trim();

    if (!patientId) {
        Swal.fire({ icon: 'warning', title: 'Patient ID required', text: 'Enter a Patient ID to search.' });
        return;
    }

    document.getElementById('notFoundWrap').style.display = 'none';
    document.getElementById('editCard').style.display = 'none';

    const params = new URLSearchParams({ patient_id: patientId });

    const response = await fetch(`/patient-detail-correction/lookup?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });

    const result = await response.json();

    if (!result.status) {
        document.getElementById('notFoundWrap').style.display = 'block';
        currentPatient = null;
        return;
    }

    currentPatient = result.patient;

    document.getElementById('edit-patient_id').value = currentPatient.patient_id;
    document.getElementById('edit-mobile_no').value = currentPatient.mobile_no ?? '';
    document.getElementById('edit-patient_name').value = currentPatient.patient_name ?? '';
    document.getElementById('edit-age').value = currentPatient.age ?? '';
    document.getElementById('edit-gender').value = currentPatient.gender ?? '';

    document.getElementById('edit-patient_name').readOnly = !!result.name_protected;
    document.getElementById('protectedNameWarning').style.display = result.name_protected ? 'block' : 'none';

    document.getElementById('editCard').style.display = 'block';
}

async function saveCorrection() {

    if (!currentPatient) return;

    let name = document.getElementById('edit-patient_name').value.trim();

    if (!name) {
        Swal.fire({ icon: 'warning', title: 'Name required', text: 'Patient name cannot be empty.' });
        return;
    }

    const confirmResult = await Swal.fire({
        icon: 'question',
        title: 'Save correction?',
        html: 'This will update the Name/Age/Gender on the patient\'s master record and on every linked invoice, appointment, doctor payable, settlement item and daily transaction for this Patient ID.',
        showCancelButton: true,
        confirmButtonText: 'Yes, Save',
    });

    if (!confirmResult.isConfirmed) return;

    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: function () {
            Swal.showLoading();
        }
    });

    const response = await fetch('/patient-detail-correction/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            patient_id: currentPatient.patient_id,
            patient_name: name,
            age: document.getElementById('edit-age').value || null,
            gender: document.getElementById('edit-gender').value || null,
        }),
    });

    const result = await response.json();

    if (!result.status) {
        Swal.fire({ icon: 'error', title: 'Update Failed', text: result.message ?? 'Unable to update patient details.' });
        return;
    }

    currentPatient.patient_name = result.patient.patient_name;
    currentPatient.age = result.patient.age;
    currentPatient.gender = result.patient.gender;

    Swal.fire({ icon: 'success', title: 'Updated', text: result.message, timer: 2000, showConfirmButton: false });
}

document.getElementById('searchPatientBtn').addEventListener('click', searchPatient);

document.getElementById('patientIdInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchPatient();
    }
});

document.getElementById('saveCorrectionBtn').addEventListener('click', saveCorrection);

/* ---------- Referred doctor (per invoice) ---------- */

let currentInvoiceNo = null;

async function searchInvoice() {

    let invoiceNo = document.getElementById('invoiceNoInput').value.trim();

    if (!invoiceNo) {
        Swal.fire({ icon: 'warning', title: 'Invoice No required', text: 'Enter an Invoice No to search.' });
        return;
    }

    document.getElementById('invoiceNotFoundWrap').style.display = 'none';
    document.getElementById('invoiceEditWrap').style.display = 'none';

    const params = new URLSearchParams({ invoice_no: invoiceNo });

    const response = await fetch(`/patient-detail-correction/invoice-lookup?${params.toString()}`, {
        headers: { 'Accept': 'application/json' }
    });

    const result = await response.json();

    if (!result.status) {
        document.getElementById('invoiceNotFoundWrap').style.display = 'block';
        currentInvoiceNo = null;
        return;
    }

    currentInvoiceNo = result.invoice.invoice_no;

    document.getElementById('inv-invoice_type').value = result.invoice.invoice_type ?? '';
    document.getElementById('inv-patient_name').value = result.invoice.patient_name ?? '';
    document.getElementById('inv-referred_doctor').value = result.invoice.referred_doctor ?? '';

    document.getElementById('invoiceEditWrap').style.display = 'block';
}

async function saveReferredDoctor() {

    if (!currentInvoiceNo) return;

    const response = await fetch('/patient-detail-correction/referred-doctor', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            invoice_no: currentInvoiceNo,
            referred_doctor: document.getElementById('inv-referred_doctor').value.trim() || null,
        }),
    });

    const result = await response.json();

    if (!result.status) {
        Swal.fire({ icon: 'error', title: 'Update Failed', text: result.message ?? 'Unable to update referred doctor.' });
        return;
    }

    document.getElementById('inv-referred_doctor').value = result.referred_doctor ?? '';

    Swal.fire({ icon: 'success', title: 'Updated', text: result.message, timer: 2000, showConfirmButton: false });
}

document.getElementById('searchInvoiceBtn').addEventListener('click', searchInvoice);

document.getElementById('invoiceNoInput').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchInvoice();
    }
});

document.getElementById('saveReferredDoctorBtn').addEventListener('click', saveReferredDoctor);
