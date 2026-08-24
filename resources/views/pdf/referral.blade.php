<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>REFERRAL LETTER</title>
    @php
        $hasStationery = !empty($letterheadPath) && file_exists(public_path($letterheadPath));
    @endphp
    <style>
        @if ($hasStationery)
        @page {
            /*
             * Per-page margins that respect the stationery safe zones on every page:
             * - Top: logo occupies approx top 45mm; padded to ~55mm
             * - Bottom: footer starts approx 30mm from bottom; padded to ~35mm
             * - Left/Right: standard 20mm page margins
             */
            margin: 40mm 20mm 35mm 20mm;
        }

        @endif

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 21px;
            letter-spacing: 0.5px;
            text-align: justify;
            margin: 0;
            padding: 0;
        }

        .content-wrapper {
            padding: 0;
        }

        span {
            display: block;
        }

        .patient {
            display: inline;
            font-weight: normal
        }

        .fixed {
            width: 300px;
            display: inline-block;
            white-space: normal;
        }
    </style>
</head>

<body>
    <div class="content-wrapper">
        @if (!$hasStationery)
            <header>
                <img style="width:100%;" src="{{ public_path('logo/letterhead.png') }}" />
            </header>
        @endif

        <span style="text-align:right;margin-right:0px;">MyReferral ID: {{ $referralId }}</span>
        <span style="text-align:right;margin-right:0px;">Date: {{ $dateCreated }}</span>
        <h3 style="text-align: center;">REFERRAL LETTER</h3>

        <div style="border-bottom: 1px solid black;margin-bottom:10px;position:relative;min-height:130px;">
            <div style="width:65%;display:inline-block;vertical-align:top;">
                @if (!$is_external)
                    <span style="font-weight: bold;">{{ $recipientOutletInfo }}</span>
                    <span class="fixed">{{ $recipientOutletAddr }}</span>
                    <span>Tel: {{ $recipientOutletPhone }}</span>
                    <span style="padding-bottom: 10px;">Email: {{ $recipientOutletEmail }}</span>
                @else
                    @if ($refereeName)
                        <span style="font-weight: bold;">{{ $refereeName }}</span>
                    @endif
                    <span style="font-weight: bold;">{{ $organizationName }}</span>
                    <span class="fixed">{{ $organizationAddr }}</span>
                    @if ($refereePhone)
                        <span>Tel: {{ $refereePhone }}</span>
                    @endif
                    @if ($refereeEmail)
                        <span>Email: {{ $refereeEmail }}</span>
                    @endif
                    <span style="padding-bottom: 10px;"></span>
                @endif
            </div>
            @if (isset($qrCodeBase64) && !empty($qrCodeBase64) && !$is_external)
                <div
                    style="width:30%;display:inline-block;vertical-align:top;text-align:right;position:absolute;right:0;top:0;">
                    <img src="{{ $qrCodeBase64 }}" style="width: 120px; height: 120px;">
                </div>
            @endif
        </div>
        <div>
            <span>Dear Sir/Madam, </span>
            <span>Thank you for seeing this patient. I would like to refer this patient to
                {{ $is_external ? $organizationName : $recipientBusinessUnit }} for
                further assistance with his/her health condition. The following is a summary of the patient's
                information.</span>
        </div>
        <div style="margin: 15px 0px;">
            <span style="font-weight: bold;">Patient's Name: <span class="patient"
                    style="text-transform: uppercase;">{{ $patientName }}</span></span>
            <span style="font-weight: bold;">Patient's Identification Number: <span
                    class="patient">{{ $patientIcNo }}</span></span>
            <span style="font-weight: bold;">Patient's Contact No: <span
                    class="patient">{{ $patientPhone }}</span></span>
        </div>
        <div style="margin: 15px 0px;">
            <div><span style="font-weight: bold;">Purpose of Referral</span></div>
            <div>{!! nl2br(e($referralReason)) !!}</div>
            @if ($referralCondition)
                <div><span style="font-weight: bold;">Details of Patient's Condition</span></div>
                <div>{!! nl2br(e($referralCondition)) !!}</div>
            @endif

            @if ($medicalHistory)
                <div><span style="font-weight: bold;">Relevant Medical History</span></div>
                <div>{!! nl2br(e($medicalHistory)) !!}</div>
            @endif

            @if ($additionalRemarks)
                <div><span style="font-weight: bold;">Additional Remarks</span></div>
                <div>{!! nl2br(e($additionalRemarks)) !!}</div>
            @endif

            @if ($priority)
                <div><span style="font-weight: bold;">Priority</span></div>
                <div>{{ $priority }}</div>
            @endif
        </div>
        <div style="margin: 10px 0px;">
            <span>
                Should you require further information, do not hesitate to contact us at
                {{ $assigneeOutletPhone }}@if ($assigneeOutletPhone && $assigneeOutletEmail)
                    or
                @endif{{ $assigneeOutletEmail }}.
            </span>
            <span style="margin-top: 10px;">Thank you for your attention to this matter. </span>
        </div>
        <div>
            <span>Best Regards,</span>
            <span style="font-weight: bold;">{{ $assigneeName }} ({{ $assigneeDesignation }})</span>
            <span style="font-weight: bold;">{{ $assigneeOutletInfo }}</span>
            <span class="fixed">{{ $assigneeOutletAddr }}</span>
            {{-- <span>Tel: {{$assigneeOutletPhone}}</span>
            <span>Email: {{$assigneeOutletEmail}}</span> --}}
        </div>

        @if (!$hasStationery && !empty($footerPath) && file_exists(public_path($footerPath)))
            <footer style="margin-top:20px;">
                <img style="width:100%;" src="{{ public_path($footerPath) }}" />
            </footer>
        @endif
    </div>
</body>

</html>
