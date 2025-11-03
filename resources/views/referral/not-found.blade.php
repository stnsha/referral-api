<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Not Found</title>
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

        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 16px;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 12px;
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

        .info-box ul {
            color: #666;
            font-size: 14px;
            line-height: 1.8;
            padding-left: 20px;
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
        <div class="icon">🔍</div>
        <h1>Referral Not Found</h1>
        <p>We couldn't find the referral you're looking for.</p>

        <div class="info-box">
            <h3>Possible reasons:</h3>
            <ul>
                <li>The referral link is invalid or has been removed</li>
                <li>The referral ID in the URL is incorrect</li>
                <li>The referral may have been cancelled</li>
            </ul>
        </div>

        <p style="margin-top: 30px; color: #888; font-size: 14px;">
            Please check the link or contact the clinic for assistance.
        </p>
    </div>
</body>
</html>
