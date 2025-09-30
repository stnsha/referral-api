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
    <div class="print-button">Print Form</div>

    <div class="header">
        <div class="logo">UCare</div>
        <div class="title">
            Minnesota Health Care Programs<br>
            Minnesota Restricted Recipient Program (MRRP)<br>
            Medical Referral for UCare Restricted Recipient Enrollee
        </div>
    </div>

    <div class="notice">
        To ensure proper payment to the referral provider, the primary care physician must mail or fax this medical referral form immediately to the Clinical Services Restricted Recipient Program.
    </div>

    @php
        $firstHistory = isset($referralDetails[0]) ? $referralDetails[0] : null;
        $lastHistory = isset($referralDetails) ? end($referralDetails) : null;
        $externalReferral = null;

        // Find external referral data
        if (isset($referralDetails)) {
            foreach ($referralDetails as $detail) {
                if (isset($detail['external_referral']) && !empty($detail['external_referral'])) {
                    $externalReferral = $detail['external_referral'][0];
                    break;
                }
            }
        }
    @endphp

    <div class="section">
        <div class="section-title">Section I: Primary Physician</div>
        <table>
            <tr>
                <td class="field-label" style="width: 33%">
                    Date:<br>
                    <span class="data-value">{{ date('m/d/Y') }}</span>
                </td>
                <td class="field-label" style="width: 33%">
                    Recipient Name:<br>
                    <span class="data-value">{{ $customer_id ?? 'Patient ID: ' . ($customer_id ?? 'N/A') }}</span>
                </td>
                <td class="field-label" style="width: 34%">
                    PMI Number:<br>
                    <span class="data-value">{{ $referral_id ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 60%">
                    Primary Physician:<br>
                    <span class="data-value">{{ $firstHistory['business_unit_name'] ?? 'Primary Care Provider' }}</span>
                </td>
                <td class="field-label" style="width: 40%">
                    Provider I.D. Number:<br>
                    <span class="data-value">{{ $firstHistory['staff_id'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 60%">
                    Street Address:<br>
                    <span class="data-value">{{ $firstHistory['location'] ?? 'Clinic Address' }}</span>
                </td>
                <td class="field-label" style="width: 40%">
                    Phone Number:<br>
                    <span class="data-value">{{ $externalReferral['phone'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 33%">
                    City:<br>
                    <span class="data-value">{{ 'Kuala Lumpur' }}</span>
                </td>
                <td class="field-label" style="width: 33%">
                    State:<br>
                    <span class="data-value">{{ $externalReferral['state'] ?? 'Malaysia' }}</span>
                </td>
                <td class="field-label" style="width: 34%">
                    Zip Code:<br>
                    <span class="data-value">{{ $externalReferral['postcode'] ?? 'N/A' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Section II: Referral Information</div>
        <table>
            <tr>
                <td class="field-label" style="width: 40%">
                    Referring to (First & Last Name):<br>
                    <span class="data-value">{{ $externalReferral['name'] ?? 'External Provider' }}</span>
                </td>
                <td class="field-label" style="width: 30%">
                    Specialty:<br>
                    <span class="data-value">{{ $externalReferral['specialty'] ?? 'General Practice' }}</span>
                </td>
                <td class="field-label" style="width: 30%">
                    I.D. #<br>
                    <span class="data-value">{{ $externalReferral['external_referee_id'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 40%">
                    Street Address:<br>
                    <span class="data-value">{{ $externalReferral['address'] ?? 'Provider Address' }}</span>
                </td>
                <td class="field-label" style="width: 30%">
                    Clinic Name:<br>
                    <span class="data-value">{{ $externalReferral['organization'] ?? 'External Clinic' }}</span>
                </td>
                <td class="field-label" style="width: 30%">
                    I.D. #<br>
                    <span class="data-value">{{ $externalReferral['external_organization_id'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 25%">
                    City:<br>
                    <span class="data-value">{{ 'Kuala Lumpur' }}</span>
                </td>
                <td class="field-label" style="width: 25%">
                    State:<br>
                    <span class="data-value">{{ $externalReferral['state'] ?? 'Malaysia' }}</span>
                </td>
                <td class="field-label" style="width: 25%">
                    Zip Code:<br>
                    <span class="data-value">{{ $externalReferral['postcode'] ?? 'N/A' }}</span>
                </td>
                <td class="field-label" style="width: 25%">
                    Phone Number:<br>
                    <span class="data-value">{{ $externalReferral['phone'] ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label large-field" colspan="4">
                    Reason for Referral:<br><br>
                    <span class="data-value">
                        @if($firstHistory)
                            <strong>Reason:</strong> {{ $firstHistory['referral_reason'] ?? 'N/A' }}<br><br>
                            <strong>Condition:</strong> {{ $firstHistory['referral_condition'] ?? 'N/A' }}<br><br>
                            <strong>Medical History:</strong> {{ $firstHistory['medical_history'] ?? 'N/A' }}<br><br>
                            <strong>Additional Remarks:</strong> {{ $firstHistory['additional_remarks'] ?? 'N/A' }}
                        @endif
                    </span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 50%">
                    Start Date:<br>
                    <span class="data-value">{{ $firstHistory['created_at'] ?? date('m/d/Y') }}</span>
                </td>
                <td class="field-label" style="width: 50%">
                    End Date:<br>
                    <span class="data-value">{{ 'Open-ended' }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 50%">
                    Signature:<br>
                    <span class="data-value">{{ 'Digital Signature' }}</span>
                </td>
                <td class="field-label" style="width: 50%">
                    Date:<br>
                    <span class="data-value">{{ date('m/d/Y') }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <div class="footer-info">
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
        </div>

        <div style="text-align: right; margin-top: 20px; font-size: 10px;">
            {{ date('n/j/Y') }}
        </div>
    </div>
</body>
</html>
