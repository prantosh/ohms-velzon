{{--
    HEADER (shared across all PDF reports/invoices)
    Usage: @include('partials.pdf-header', ['reportTitle' => 'TEST REPORT'])
    Optional 'headerColor' (default '#000') -- pass '#003399' for the
    patient-facing colour invoices.
    Optional 'compact' (default false) -- shrinks the logo/badge images and
    drops the web/phone line, for PDFs (e.g. the prescription pad) that
    need to save vertical space for content below the header.
--}}
@php
    $isCompact = $compact ?? false;
    $logoHeight = $isCompact ? 70 : 125;
    $badgeHeight = $isCompact ? 35 : 55;
@endphp

{{-- WATERMARK -- position:fixed repeats this on every page. Placed before
     the header table so it paints behind everything that follows. --}}
<div style="
    position: fixed;
    top: 280px;
    left: 0;
    right: 0;
    text-align: center;
    opacity: 0.08;
">
    <img
        src="{{ public_path('images/abssrk_logo.png') }}"
        style="width: 380px;">
</div>

<table style="border:none; margin:0;">

    <tr>

        <td colspan="3"
    style="
        border:none;
        text-align:center;
        padding:0;
        line-height:0;
    ">

            <img
                src="{{ public_path('images/abssrk_logo.png') }}"
                style="height:{{ $logoHeight }}px;">

        </td>

    </tr>

    <tr>

        <td
            style="
                width:15%;
                border:none;
                text-align:left;
                vertical-align:middle;
            ">

            <img
                src="{{ public_path('images/iso.jpg') }}"
                style="height:{{ $badgeHeight }}px;">

        </td>

        <td
            style="
                width:70%;
                border:none;
                text-align:center;
                vertical-align:middle;
            ">

            <h3
                style="
                    margin:0;
                    color:{{ $headerColor ?? '#000' }};
                ">

                Dr. Amitava Basu Smriti Swastha Raksha Kendra

            </h3>

            <h4
                style="
                    margin:2px 0;
                    color:{{ $headerColor ?? '#000' }};
                ">

                Srayan Apartment, 19, M B Road,
                Kolkata - 700049

            </h4>

            @unless($isCompact)
            <h6 style="margin:2px 0;">

                Web: www.abssrk.online; Phone: 2513-7070 / 7439, 2539-2009 ; Mobile: 8685882287, 9051132429

            </h6>
            @endunless

        </td>

        <td
            style="
                width:15%;
                border:none;
                text-align:right;
                vertical-align:middle;
            ">

            <img
                src="{{ public_path('images/nabl.jpg') }}"
                style="height:{{ $badgeHeight }}px;">

        </td>

    </tr>

    <tr>

        <td colspan="3"
            style="
                border:none;
                text-align:center;
                padding-top:2px;
                padding-bottom:0;
            ">

            <h3
                style="
                    margin:0;
                    color:{{ $headerColor ?? '#000' }};
                ">

                {{ $reportTitle }}

            </h3>

        </td>

    </tr>

    <tr>

        <td colspan="3"
            style="
                border:none;
                text-align:right;
                padding-top:2px;
                padding-bottom:0;
                font-size:9px;
                color:#555;
            ">

            Printed On: {{ now()->format('d-m-Y h:i A') }}

        </td>

    </tr>

</table>
