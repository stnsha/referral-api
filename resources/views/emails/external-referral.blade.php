<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Referral from {{ $referrerBusinessUnit }}</title>
    <style>
        /* Reset styles */
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            background-color: #f5f5f5;
            padding: 20px 0;
        }

        /* Email container */
        .email-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Header with logo */
        .email-header {
            background-color: #ffffff;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 3px solid #0066cc;
        }

        .email-logo {
            max-width: 200px;
            width: 100%;
            height: auto;
            display: inline-block;
        }

        /* Email body */
        .email-body {
            padding: 40px 30px;
            color: #333333;
            line-height: 1.8;
            font-size: 15px;
        }

        .email-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0 0 25px 0;
            padding-bottom: 15px;
            border-bottom: 2px solid #e8e8e8;
        }

        .greeting {
            margin-bottom: 20px;
            font-size: 15px;
        }

        .intro-text {
            margin-bottom: 25px;
            line-height: 1.8;
        }

        /* Section styling */
        .section {
            margin: 25px 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #0066cc;
            margin: 0 0 12px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-content {
            margin: 0 0 15px 0;
            color: #4a4a4a;
            line-height: 1.8;
            white-space: pre-line;
        }

        /* Referral details table */
        .details-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }

        .details-table tr {
            border-bottom: 1px solid #e8e8e8;
        }

        .details-table tr:last-child {
            border-bottom: none;
        }

        .details-table td {
            padding: 12px 0;
            vertical-align: top;
        }

        .details-label {
            font-weight: 600;
            color: #1a1a1a;
            width: 40%;
            padding-right: 15px;
        }

        .details-value {
            color: #4a4a4a;
            width: 60%;
        }

        /* Attachment notice */
        .attachment-notice {
            background-color: #f0f7ff;
            border-left: 4px solid #0066cc;
            padding: 15px 20px;
            margin: 25px 0;
            font-size: 14px;
            color: #0066cc;
        }

        /* Closing */
        .closing {
            margin-top: 30px;
            line-height: 1.8;
        }

        /* Signature */
        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e8e8e8;
        }

        .signature-line {
            margin: 5px 0;
            color: #4a4a4a;
        }

        .signature-name {
            font-weight: 700;
            color: #1a1a1a;
            font-size: 16px;
        }

        /* Footer */
        .email-footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #888888;
            border-top: 1px solid #e8e8e8;
        }

        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            body {
                padding: 0;
            }

            .email-container {
                width: 100% !important;
                box-shadow: none;
            }

            .email-header {
                padding: 25px 15px;
            }

            .email-logo {
                max-width: 150px;
            }

            .email-body {
                padding: 30px 20px !important;
            }

            .email-title {
                font-size: 18px;
            }

            .section-title {
                font-size: 15px;
            }

            .details-table td {
                display: block;
                width: 100% !important;
                padding: 8px 0;
            }

            .details-label {
                font-weight: 700;
                margin-bottom: 5px;
            }

            .details-value {
                padding-left: 0;
            }

            .attachment-notice {
                padding: 12px 15px;
                font-size: 13px;
            }

            .email-footer {
                padding: 15px 20px;
            }
        }

        @media only screen and (max-width: 480px) {
            .email-body {
                font-size: 14px;
            }

            .email-title {
                font-size: 17px;
            }

            .section-title {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header with Logo -->
        <div class="email-header">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="email-logo">
        </div>

        <!-- Email Body -->
        <div class="email-body">
            <h1 class="email-title">Referral from {{ $referrerBusinessUnit }}</h1>

            <div style="text-align: right; color: #888; font-size: 14px; margin-bottom: 20px;">
                <strong>Referral ID:</strong> {{ $referralId }}<br>
                <strong>Date:</strong> {{ $dateCreated }}
            </div>

            <div class="greeting">
                Dear {{ $recipientName }},
            </div>

            <div class="intro-text">
                I am <strong>{{ $referrerName }}</strong> from <strong>{{ $referrerBusinessUnit }}</strong>. I am referring <strong>{{ $patientName }}</strong> to your clinic/hospital for further evaluation and management.
            </div>

            <!-- Patient Information -->
            <div class="section">
                <div class="section-title">Patient Information:</div>
                <table class="details-table">
                    <tr>
                        <td class="details-label">Name:</td>
                        <td class="details-value">{{ $patientName }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">IC No:</td>
                        <td class="details-value">{{ $patientIcNo }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">Phone No:</td>
                        <td class="details-value">{{ $patientPhone }}</td>
                    </tr>
                    <tr>
                        <td class="details-label">Address:</td>
                        <td class="details-value">{{ $patientAddress }}</td>
                    </tr>
                    @if(!empty($patientEmail))
                    <tr>
                        <td class="details-label">Email:</td>
                        <td class="details-value">{{ $patientEmail }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <!-- Reason for Referral -->
            <div class="section">
                <div class="section-title">Reason for Referral:</div>
                <div class="section-content">{{ $referralReason }}</div>
            </div>

            <!-- Clinical Details -->
            <div class="section">
                <div class="section-title">Clinical Details:</div>
                <div class="section-content">
                    <strong>Condition:</strong><br>
                    {{ $referralCondition }}
                </div>

                @if(!empty($medicalHistory))
                <div class="section-content">
                    <strong>Medical History:</strong><br>
                    {{ $medicalHistory }}
                </div>
                @endif

                @if(!empty($additionalRemarks))
                <div class="section-content">
                    <strong>Additional Remarks:</strong><br>
                    {{ $additionalRemarks }}
                </div>
                @endif

                @if(!empty($referralDetails) && is_array($referralDetails) && count($referralDetails) > 0)
                <div class="section-content">
                    <strong>Referral Information:</strong>
                    <table class="details-table">
                        @foreach($referralDetails as $detail)
                        <tr>
                            <td class="details-label">{{ $detail['form_name'] }}:</td>
                            <td class="details-value">{{ $detail['form_value'] }}</td>
                        </tr>
                        @endforeach
                    </table>
                </div>
                @endif
            </div>

            <!-- Attachment Notice -->
            @if(!empty($referralAttachments) && count($referralAttachments) > 0)
            <div class="attachment-notice">
                📎 Attached are the relevant medical documents for your reference.
            </div>
            @endif

            <!-- Closing -->
            <div class="closing">
                Thank you for your attention to this referral. Please do not hesitate to contact me if you require any further information or clarification regarding this case.
            </div>

            <!-- Signature -->
            <div class="signature">
                <div class="signature-line">Best regards,</div>
                <div class="signature-line signature-name">{{ $referrerName }}</div>
                <div class="signature-line">{{ $referrerDesignation }}</div>
                <div class="signature-line">{{ $referrerBusinessUnit }}</div>
                @if(!empty($referrerPhone))
                <div class="signature-line">Phone: {{ $referrerPhone }}</div>
                @endif
                @if(!empty($referrerEmail))
                <div class="signature-line">Email: {{ $referrerEmail }}</div>
                @endif
            </div>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            This is a confidential medical communication. If you have received this email in error, please notify the sender immediately.
        </div>
    </div>
</body>
</html>
