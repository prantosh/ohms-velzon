{{--
    CORRECTION FOOTNOTE -- printed on a report that replaces one an Admin
    deleted after it had already been sent to the patient via WhatsApp.
    Usage: @include('partials.report-correction-footnote', ['correctedFromSentAt' => $finding->corrected_from_sent_at ?? null])
--}}
@if(!empty($correctedFromSentAt))
<div style="margin-top: 14px; padding: 8px 10px; border: 1px solid #c00000; color: #c00000; font-size: 0.85em;">
    Please ignore the previous report sent at {{ \Carbon\Carbon::parse($correctedFromSentAt)->format('d-m-Y h:i A') }} and accept this as corrected. We are extremely sorry for this inadvertent mistake.
</div>
@endif
