<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body style="font-family: Arial, sans-serif; font-size: 14px; color: #212529;">

    <p>Dear {{ $data['employee'] }},</p>

    <p>
        This is a system-generated record of the report entry you
        <strong>{{ $data['action'] === 'CONFIRM' ? 'confirmed (final, locked)' : 'saved (draft, may be changed further)' }}</strong>.
        Please keep it for your records.
    </p>

    <table style="border-collapse: collapse; margin: 15px 0;">
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Report Type</td>
            <td style="padding: 4px 0;">{{ $data['kind_label'] }} (report #{{ $data['report_id'] }})</td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Invoice No</td>
            <td style="padding: 4px 0;">{{ $data['invoice_no'] }}</td>
        </tr>
        @if($data['patient'])
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Patient</td>
            <td style="padding: 4px 0;">{{ $data['patient'] }}</td>
        </tr>
        @endif
        @if($data['items'])
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Test / Item</td>
            <td style="padding: 4px 0;">{{ $data['items'] }}</td>
        </tr>
        @endif
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Action</td>
            <td style="padding: 4px 0;">{{ $data['action'] === 'CONFIRM' ? 'Confirmed' : 'Saved' }} by {{ $data['employee'] }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 10px 4px 0; font-weight: bold;">Date &amp; Time</td>
            <td style="padding: 4px 0;">{{ $data['at'] }}</td>
        </tr>
    </table>

    <h4 style="margin-bottom: 6px;">Report content as entered</h4>

    @forelse($data['fields'] as $field => $value)
        <div style="margin-bottom: 10px;">
            <div style="font-weight: bold;">{{ ucfirst(str_replace('_', ' ', $field)) }}</div>
            <div style="border: 1px solid #dee2e6; padding: 6px 8px;">
                @if(str_contains($value, '<'))
                    {!! \App\Services\HtmlSanitizerService::sanitizeClinicalText($value) !!}
                @else
                    {!! nl2br(e($value)) !!}
                @endif
            </div>
        </div>
    @empty
        <p><em>No content entered.</em></p>
    @endforelse

    <p style="font-size: 12px; color: #6c757d;">
        Entry fingerprint (SHA-256): {{ $data['hash'] }}
    </p>

    <p>Regards,<br>ABSSRK IT Team</p>

</body>

</html>
