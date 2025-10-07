<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Medical Referral Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            line-height: 1.2;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .logo {
            font-size: 36px;
            font-weight: bold;
            font-style: italic;
            margin-bottom: 10px;
        }

        .title {
            font-size: 14px;
            font-weight: bold;
            line-height: 1.3;
        }

        .print-button {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            border: 1px solid #000;
            background: #f0f0f0;
            font-size: 10px;
        }

        .notice {
            font-size: 11px;
            margin: 15px 0;
            line-height: 1.4;
        }

        .section {
            margin: 20px 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
            height: 25px;
        }

        .field-label {
            font-weight: normal;
            background: #fff;
            font-size: 11px;
        }

        .large-field {
            height: 100px;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 11px;
            line-height: 1.4;
        }

        .footer-info {
            margin: 5px 0;
        }

        .data-value {
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Alpro Pharmacy</div>
        {{-- <div class="title">
            Minnesota Health Care Programs<br>
            Minnesota Restricted Recipient Program (MRRP)<br>
            Medical Referral for UCare Restricted Recipient Enrollee
        </div> --}}
    </div>

    @php
        $firstHistory = isset($referralDetails[0]) ? $referralDetails[0] : null;
        $lastHistory = isset($referralDetails) ? end($referralDetails) : null;
        $externalReferee = null;
        $externalOrganization = null;

        // Find external referral data from referral histories
        if (isset($referralDetails)) {
            foreach ($referralDetails as $detail) {
                // Check if this is an Eloquent model or array
                if (is_object($detail)) {
                    if (!empty($detail->external_referee_id) && $detail->external_referee) {
                        $externalReferee = $detail->external_referee;
                        $externalOrganization = $externalReferee->organization ?? null;
                        break;
                    }
                } else {
                    // Array format (from show/download methods)
                    if (isset($detail['external_referral']) && !empty($detail['external_referral'])) {
                        $externalReferee = (object) $detail['external_referral'][0];
                        $externalOrganization = $externalReferee;
                        break;
                    }
                }
            }
        }
    @endphp

    <div class="section">
        <div class="section-title">Referral Information</div>
        <table>
            <tr>
                <td class="field-label" style="width: 50%" colspan="2">
                    Referring to (First & Last Name):<br>
                    <span class="data-value">{{ $externalReferee->name ?? 'External Provider' }}</span>
                </td>
                <td class="field-label" style="width: 50%" colspan="2">
                    Specialty:<br>
                    <span class="data-value">{{ $externalReferee->specialty ?? 'General Practice' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 50%" colspan="2">
                    Clinic Name:<br>
                    <span class="data-value">{{ $externalOrganization->name ?? 'External Clinic' }}</span>
                </td>
                <td class="field-label" style="width: 50%" colspan="2">
                    Phone Number:<br>
                    <span class="data-value">{{ $externalReferee->phone ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" colspan="4">
                    Street Address:<br>
                    <span class="data-value">{{ $externalOrganization->address ?? 'Provider Address' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 33%">
                    City:<br>
                    <span class="data-value">{{ $externalOrganization->state ?? 'Kuala Lumpur' }}</span>
                </td>
                <td class="field-label" style="width: 33%">
                    State:<br>
                    <span class="data-value">{{ $externalOrganization->state ?? 'Malaysia' }}</span>
                </td>
                <td class="field-label" style="width: 34%" colspan="2">
                    Zip Code:<br>
                    <span class="data-value">{{ $externalOrganization->postcode ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label large-field" colspan="4">
                    Reason for Referral:<br><br>
                    <span class="data-value">
                        @if($firstHistory)
                            @php
                                $reason = is_object($firstHistory) ? ($firstHistory->referral_reason ?? 'N/A') : ($firstHistory['referral_reason'] ?? 'N/A');
                                $condition = is_object($firstHistory) ? ($firstHistory->referral_condition ?? 'N/A') : ($firstHistory['referral_condition'] ?? 'N/A');
                                $history = is_object($firstHistory) ? ($firstHistory->medical_history ?? 'N/A') : ($firstHistory['medical_history'] ?? 'N/A');
                                $remarks = is_object($firstHistory) ? ($firstHistory->additional_remarks ?? 'N/A') : ($firstHistory['additional_remarks'] ?? 'N/A');
                            @endphp
                            <strong>Reason:</strong> {{ $reason }}<br><br>
                            <strong>Condition:</strong> {{ $condition }}<br><br>
                            <strong>Medical History:</strong> {{ $history }}<br><br>
                            <strong>Additional Remarks:</strong> {{ $remarks }}
                        @endif
                    </span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 50%" colspan="2">
                    Start Date:<br>
                    @php
                        $createdAt = is_object($firstHistory) ? ($firstHistory->created_at ?? date('m/d/Y')) : ($firstHistory['created_at'] ?? date('m/d/Y'));
                    @endphp
                    <span class="data-value">{{ $createdAt }}</span>
                </td>
                <td class="field-label" style="width: 50%" colspan="2">
                    End Date:<br>
                    <span class="data-value">{{ 'Open-ended' }}</span>
                </td>
            </tr>
            {{-- <tr>
                <td class="field-label" style="width: 50%">
                    Signature:<br>
                    <span class="data-value">{{ 'Digital Signature' }}</span>
                </td>
                <td class="field-label" style="width: 50%">
                    Date:<br>
                    <span class="data-value">{{ date('m/d/Y') }}</span>
                </td>
            </tr> --}}
        </table>
    </div>

    <div class="footer">
        {{-- <div class="footer-info">
            <strong>UCare Clinical Services</strong><br>
            Restricted Recipient Program<br>
            P.O. Box 52<br>
            Minneapolis, MN 55440
        </div>

        <div class="footer-info">
            <strong>Fax Number: (612) 884-2316</strong>
        </div>

        <div class="footer-info">
            If you have any questions, call (612) 676-3397 or (877) 447-4384
        </div> --}}

        <div style="text-align: right; margin-top: 20px; font-size: 10px;">
            {{ date('n/j/Y') }}
        </div>
    </div>
</body>
</html>
