<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>REFERRAL LETTER</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 21px;
            letter-spacing: 0.5px;
            text-align: justify;
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
    <header>
        <img style="width:100%;" src="{{ public_path('logo/letterhead.png') }}" />
        <p style="text-align:right;margin-right:60px;">Date: {{ $dateCreated }}</span>
        <h3 style="text-align: center;">REFERRAL LETTER</h3>
    </header>
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
    </div>
    <div style="margin: 15px 0px;">
        <span style="font-weight: bold;">Purpose of Referral</span>
        <span>{{ $referralReason }}</span>
        @if ($referralCondition)
            <span style="font-weight: bold;">Details of Patient's Condition</span>
            <span>{{ $referralCondition }}</span>
        @endif

        @if ($medicalHistory)
            <span style="font-weight: bold;">Relevant Medical History</span>
            <span>{{ $medicalHistory }}</span>
        @endif

        @if ($additionalRemarks)
            <span style="font-weight: bold;">Additional Remarks</span>
            <span>{{ $additionalRemarks }}</span>
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
</body>

</html>
