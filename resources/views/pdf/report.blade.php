<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Alpro Group Patient Referral</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            margin: 20px;
            line-height: 1.4;
        }

        .header .logo {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .main {
            width: 100%;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            border: 1px solid #000;
        }

        table tr td {
            border: 1px solid #000;
            padding: 3px 8px;
            vertical-align: top;
        }

        .section-header {
            background-color: #d3d3d3;
            font-weight: bold;
            padding: 4px 8px;
        }

        .no-border-bottom {
            border-bottom: none !important;
        }

        .no-border-top {
            border-top: none !important;
        }

    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Alpro Group Patient Referral</div>
    </div>
    <div class="main">
        <table>
            <tr>
                <td class="section-header" style="width: 50%;">Referred From</td>
                <td class="section-header" style="width: 50%;">Refer To</td>
            </tr>

            @if($is_external)
                {{-- External Referral: Show staff details in Referred From --}}
                <tr>
                    <td class="no-border-bottom">
                        @if($assigneeName)
                            <strong>{{ $assigneeName }}</strong>@if($assigneeDesignation) ({{ $assigneeDesignation }})@endif
                        @endif
                    </td>
                    <td class="no-border-bottom">
                        @if($refereeName)
                            <strong>{{ $refereeName }}</strong>@if($refereePosition) ({{ $refereePosition }})@endif
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="no-border-bottom no-border-top">{{ $assigneeOutletInfo }}</td>
                    <td class="no-border-bottom no-border-top">{{ $organizationName }}</td>
                </tr>
                <tr>
                    <td class="no-border-bottom no-border-top">{{ $assigneeOutletAddr }}</td>
                    <td class="no-border-bottom no-border-top">{{ $organizationAddr }}</td>
                </tr>
                <tr>
                    <td class="no-border-bottom no-border-top">{{ $assigneePhone }}</td>
                    <td class="no-border-bottom no-border-top">{{ $refereePhone }}</td>
                </tr>
                <tr>
                    <td class="no-border-top">{{ $assigneeEmail }}</td>
                    <td class="no-border-top">{{ $refereeEmail }}</td>
                </tr>
            @else
                {{-- Internal Referral: Show staff + outlet in Referred From, outlet in Refer To --}}
                <tr>
                    <td class="no-border-bottom">
                        @if($assigneeName)
                            <strong>{{ $assigneeName }}</strong>@if($assigneeDesignation) ({{ $assigneeDesignation }})@endif
                        @endif
                    </td>
                    <td class="no-border-bottom">{{ $recipientOutletInfo }}</td>
                </tr>
                <tr>
                    <td class="no-border-bottom no-border-top">{{ $assigneeOutletInfo }}</td>
                    <td class="no-border-bottom no-border-top">{{ $recipientOutletAddr }}</td>
                </tr>
                <tr>
                    <td class="no-border-bottom no-border-top">{{ $assigneeOutletAddr }}</td>
                    <td class="no-border-bottom no-border-top">{{ $recipientOutletPhone }}</td>
                </tr>
                <tr>
                    <td class="no-border-bottom no-border-top">
                        @if($assigneePhone || $assigneeEmail)
                            {{ $assigneePhone }}@if($assigneePhone && $assigneeEmail) / @endif{{ $assigneeEmail }}
                        @endif
                    </td>
                    <td class="no-border-top">{{ $recipientOutletEmail }}</td>
                </tr>
            @endif

            <tr>
                <td colspan="2" class="section-header">Patient Information</td>
            </tr>
            <tr>
                <td colspan="2" class="no-border-bottom">
                    @if($patientName){{ $patientName }}@endif
                    @if($patientIcNo) (NRIC: {{ $patientIcNo }})@endif
                </td>
            </tr>
            @if($patientAddress)
            <tr>
                <td colspan="2" class="no-border-bottom no-border-top">{{ $patientAddress }}</td>
            </tr>
            @endif
            @if($patientPhone)
            <tr>
                <td colspan="2" class="@if(!$patientAddress)no-border-bottom @endif no-border-top">{{ $patientPhone }}</td>
            </tr>
            @endif
            @if(!$patientAddress && !$patientPhone)
            <tr>
                <td colspan="2" class="no-border-top"></td>
            </tr>
            @endif
            @if($referralReason)
            <tr>
                <td colspan="2" class="section-header">Referral Reason</td>
            </tr>
            <tr>
                <td colspan="2">{{ $referralReason }}</td>
            </tr>
            @endif
            @if($referralCondition)
            <tr>
                <td colspan="2" class="section-header">Referral Condition</td>
            </tr>
            <tr>
                <td colspan="2">{{ $referralCondition }}</td>
            </tr>
            @endif
            @if($medicalHistory)
            <tr>
                <td colspan="2" class="section-header">Medical History</td>
            </tr>
            <tr>
                <td colspan="2">{{ $medicalHistory }}</td>
            </tr>
            @endif
            @if($additionalRemarks)
            <tr>
                <td colspan="2" class="section-header">Additional Remarks</td>
            </tr>
            <tr>
                <td colspan="2">{{ $additionalRemarks }}</td>
            </tr>
            @endif
        </table>
    </div>
    <div class="footer" style="text-align: center; margin-top: 30px;">
        <div style="margin-bottom: 10px; font-size: 12px; font-weight: bold;">Scan QR Code to View Referral</div>
        @if($qrCodeBase64)
        <div>
            <img src="{{ $qrCodeBase64 }}" alt="QR Code" width="150" height="150">
        </div>
        @endif
    </div>
</body>
</html>
