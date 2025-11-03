<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Completed - {{ $referralId }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
            padding: 50px 40px;
            text-align: center;
        }

        .icon-container {
            margin-bottom: 30px;
        }

        .checkmark-circle {
            width: 80px;
            height: 80px;
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }

        .checkmark-circle svg {
            width: 80px;
            height: 80px;
        }

        .checkmark-circle circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 3;
            stroke: #4CAF50;
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark-circle path {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            stroke: #4CAF50;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.6s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 16px;
        }

        .ref-id {
            color: #667eea;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .message {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .status-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .info-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }

        .info-box h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .info-box p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .info-box p:last-child {
            margin-bottom: 0;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            color: #888;
            font-size: 13px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon-container">
            <div class="checkmark-circle">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                    <circle cx="26" cy="26" r="25" fill="none"/>
                    <path fill="none" stroke-width="3" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                </svg>
            </div>
        </div>

        <h1>Referral Completed</h1>
        <div class="ref-id">{{ $referralId }}</div>

        <div class="status-badge">
            ✓ Treatment Completed
        </div>

        <div class="message">
            <p><strong>Thank you for your trust!</strong></p>
            <p>Your referral has been successfully completed. We hope your treatment went well and you're feeling better.</p>
        </div>

        <div class="info-box">
            <h3>What's next?</h3>
            <p>• Your referral is now closed and archived</p>
            <p>• For any questions about your treatment, please contact the clinic directly</p>
            <p>• If you need further medical assistance, please schedule a new appointment</p>
        </div>

        <div class="footer">
            If you have any concerns or questions, please don't hesitate to contact us.
        </div>
    </div>

    <script>
        // Add a subtle fade-in animation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.container').style.opacity = '0';
            document.querySelector('.container').style.transform = 'translateY(20px)';
            document.querySelector('.container').style.transition = 'opacity 0.5s, transform 0.5s';

            setTimeout(function() {
                document.querySelector('.container').style.opacity = '1';
                document.querySelector('.container').style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>
