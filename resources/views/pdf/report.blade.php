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
            line-height: 1.4;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            /* border-bottom: 2px solid #000; */
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .title {
            font-size: 16px;
            font-weight: bold;
            margin: 10px 0;
        }

        .ref-info {
            text-align: right;
            font-size: 11px;
            margin-bottom: 15px;
        }

        .section {
            margin: 15px 0;
        }

        .section-title {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 8px;
            padding: 5px;
            background-color: #e8e8e8;
            border-left: 4px solid #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }

        .field-label {
            font-weight: bold;
            font-size: 10px;
            color: #333;
        }

        .data-value {
            font-weight: normal;
            color: #000;
            font-size: 11px;
            margin-top: 2px;
        }

        .large-field {
            min-height: 80px;
        }

        .signature-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 10px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">Medical Referral Form</div>
    </div>

    <div class="ref-info">
        <strong>Referral ID:</strong> {{ $referralId }}<br>
        <strong>Date:</strong> {{ $dateCreated }}
    </div>

    <!-- Referring From Section -->
    <div class="section">
        <div class="section-title">REFERRING FROM</div>
        <table>
            <tr>
                <td class="field-label" style="width: 50%;">
                    Referring Doctor:<br>
                    <span class="data-value">{{ $referrerName }}</span>
                </td>
                <td class="field-label" style="width: 50%;">
                    Designation:<br>
                    <span class="data-value">{{ $referrerDesignation }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 50%;">
                    Department/Business Unit:<br>
                    <span class="data-value">{{ $referrerBusinessUnit }}</span>
                </td>
                <td class="field-label" style="width: 50%;">
                    Phone:<br>
                    <span class="data-value">{{ $referrerPhone }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" colspan="2">
                    Email:<br>
                    <span class="data-value">{{ $referrerEmail }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Referring To Section -->
    <div class="section">
        <div class="section-title">REFERRING TO</div>
        <table>
            <tr>
                <td class="field-label" style="width: 50%;">
                    Recipient Name:<br>
                    <span class="data-value">{{ $recipientName }}</span>
                </td>
                <td class="field-label" style="width: 50%;">
                    Position:<br>
                    <span class="data-value">{{ $recipientPosition }}</span>
                </td>
            </tr>
            @if(!empty($recipientSpecialty))
            <tr>
                <td class="field-label" colspan="2">
                    Specialty:<br>
                    <span class="data-value">{{ $recipientSpecialty }}</span>
                </td>
            </tr>
            @endif
            <tr>
                <td class="field-label" style="width: 50%;">
                    Organization/Clinic Name:<br>
                    <span class="data-value">{{ $organizationName }}</span>
                </td>
                <td class="field-label" style="width: 50%;">
                    Phone:<br>
                    <span class="data-value">{{ $recipientPhone }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" colspan="2">
                    Address:<br>
                    <span class="data-value">{{ $organizationAddress }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Patient Information Section -->
    <div class="section">
        <div class="section-title">PATIENT INFORMATION</div>
        <table>
            <tr>
                <td class="field-label" style="width: 50%;">
                    Patient Name:<br>
                    <span class="data-value">{{ $patientName }}</span>
                </td>
                <td class="field-label" style="width: 50%;">
                    IC/Passport No:<br>
                    <span class="data-value">{{ $patientIcNo }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label" style="width: 50%;">
                    Phone Number:<br>
                    <span class="data-value">{{ $patientPhone }}</span>
                </td>
                @if(!empty($patientEmail))
                <td class="field-label" style="width: 50%;">
                    Email:<br>
                    <span class="data-value">{{ $patientEmail }}</span>
                </td>
                @else
                <td class="field-label" style="width: 50%;">
                    Email:<br>
                    <span class="data-value">N/A</span>
                </td>
                @endif
            </tr>
            <tr>
                <td class="field-label" colspan="2">
                    Address:<br>
                    <span class="data-value">{{ $patientAddress }}</span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Clinical Information Section -->
    <div class="section">
        <div class="section-title">CLINICAL INFORMATION</div>
        <table>
            <tr>
                <td class="field-label large-field" colspan="2">
                    Reason for Referral:<br>
                    <span class="data-value">{{ $referralReason }}</span>
                </td>
            </tr>
            <tr>
                <td class="field-label large-field" colspan="2">
                    Condition:<br>
                    <span class="data-value">{{ $referralCondition }}</span>
                </td>
            </tr>
            @if(!empty($medicalHistory))
            <tr>
                <td class="field-label large-field" colspan="2">
                    Medical History:<br>
                    <span class="data-value">{{ $medicalHistory }}</span>
                </td>
            </tr>
            @endif
            @if(!empty($additionalRemarks))
            <tr>
                <td class="field-label large-field" colspan="2">
                    Additional Remarks:<br>
                    <span class="data-value">{{ $additionalRemarks }}</span>
                </td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Referral Details Section -->
    @if(!empty($referralDetails) && is_array($referralDetails) && count($referralDetails) > 0)
    <div class="section">
        <div class="section-title">ADDITIONAL REFERRAL DETAILS</div>
        <table>
            @foreach($referralDetails as $detail)
            <tr>
                <td class="field-label" style="width: 40%;">
                    {{ $detail['form_name'] }}:
                </td>
                <td class="data-value" style="width: 60%;">
                    {{ $detail['form_value'] }}
                </td>
            </tr>
            @endforeach
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This is a confidential medical document. Please handle with care.</p>
        <p style="font-size: 9px; color: #666;">Generated on {{ $dateCreated }}</p>
    </div>
</body>
</html>
