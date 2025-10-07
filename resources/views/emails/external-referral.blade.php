<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Referral Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4a5568;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f7fafc;
            padding: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 0 0 5px 5px;
        }
        .info-row {
            margin: 15px 0;
        }
        .label {
            font-weight: bold;
            color: #2d3748;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #718096;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>New Referral Notification</h1>
    </div>
    <div class="content">
        <p>Dear {{ $refereeName }},</p>

        <p>You have received a new external referral. Please find the details below:</p>

        <div class="info-row">
            <span class="label">Referral ID:</span> {{ $referralId }}
        </div>

        <div class="info-row">
            <span class="label">Referral Reason:</span> {{ $referralReason }}
        </div>

        <p>The complete referral details are attached to this email as a PDF document.</p>

        <p>Please review the referral at your earliest convenience.</p>

        <p>Best regards,<br>
        Referral Management System</p>
    </div>
    <div class="footer">
        <p>This is an automated notification. Please do not reply to this email.</p>
    </div>
</body>
</html>
