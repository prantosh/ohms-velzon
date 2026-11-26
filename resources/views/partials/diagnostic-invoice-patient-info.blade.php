{{-- Patient / invoice details block of the diagnostic invoice PDF.
     Shared so the STANDARD layout can repeat it in the page header of every
     page, while SHORT prints it once in the normal flow. Expects $invoice and
     (optionally) $testDateText; fmtDate() is declared by the parent view.
     Three rows of three label/value pairs. --}}
@php
    $genderAge = trim(implode(' / ', array_filter([
        trim((string) ($invoice->patient_gender ?? '')),
        trim((string) ($invoice->patient_age ?? '')),
    ], fn ($v) => $v !== '')));
@endphp
<table style="font-size: {{ ($paperSize ?? "STANDARD") === "SHORT" ? "10px" : "11px" }};">

<tr>
    <td width="14%" class="label-blue" style="white-space:nowrap;">Patient Name</td>
    <td width="28%"><strong>{{ $invoice->patient_name }}</strong></td>

    <td width="11%" class="label-blue" style="white-space:nowrap;">Invoice No</td>
    <td width="18%">{{ $invoice->invoice_no }}</td>

    <td width="13%" class="label-blue" style="white-space:nowrap;">Invoice Date</td>
    <td width="16%">{{ fmtDate($invoice->invoice_date) }}</td>
</tr>

<tr>
    <td class="label-blue" style="white-space:nowrap;">Gender / Age</td>
    <td>{{ $genderAge }}</td>

    <td class="label-blue" style="white-space:nowrap;">Patient ID</td>
    <td>{{ $invoice->patient_id }}</td>

    <td class="label-blue" style="white-space:nowrap;">Mobile No</td>
    <td>{{ $invoice->patient_mobile_no }}</td>
</tr>

<tr>
    <td class="label-blue" style="white-space:nowrap;">Referred Doctor</td>
    <td>{{ $invoice->referred_doctor }}</td>

    <td class="label-blue" style="white-space:nowrap;">Test Date</td>
    <td>{{ !empty($testDateText) ? $testDateText : fmtDate($invoice->test_date) }}</td>

    <td class="label-blue" style="white-space:nowrap;">Payment Mode</td>
    <td>{{ $invoice->payment_mode }}</td>
</tr>
</table>
