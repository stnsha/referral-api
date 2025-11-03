<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify NRIC - Referral {{ $refId }}</title>
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
            max-width: 450px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .header .ref-id {
            color: #667eea;
            font-size: 18px;
            font-weight: 600;
        }

        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 4px;
        }

        .info-box p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
            letter-spacing: 2px;
            text-align: center;
            font-weight: 600;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .error {
            color: #dc3545;
            font-size: 13px;
            margin-top: 6px;
            display: block;
        }

        .form-group.has-error input {
            border-color: #dc3545;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .security-note {
            text-align: center;
            color: #888;
            font-size: 12px;
            margin-top: 20px;
            line-height: 1.5;
        }

        .security-note svg {
            width: 14px;
            height: 14px;
            vertical-align: middle;
            margin-right: 4px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Your Identity</h1>
            <div class="ref-id">{{ $refId }}</div>
        </div>

        <div class="info-box">
            <p>To view your referral details, please enter the <strong>last 4 digits</strong> of your NRIC number.</p>
        </div>

        <form method="POST" action="{{ route('referral.verify.nric', ['id' => $referralId]) }}">
            @csrf
            <div class="form-group {{ $errors->has('nric_last4') || $errors->has('nric') ? 'has-error' : '' }}">
                <label for="nric_last4">Last 4 Digits of NRIC</label>
                <input
                    type="text"
                    id="nric_last4"
                    name="nric_last4"
                    maxlength="4"
                    pattern="[0-9A-Za-z]{4}"
                    placeholder="****"
                    value="{{ old('nric_last4') }}"
                    required
                    autofocus
                >
                @if($errors->has('nric_last4'))
                    <span class="error">{{ $errors->first('nric_last4') }}</span>
                @endif
                @if($errors->has('nric'))
                    <span class="error">{{ $errors->first('nric') }}</span>
                @endif
            </div>

            <button type="submit" class="btn">Verify & Continue</button>
        </form>

        <div class="security-note">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Your information is secure. Access expires in 5 minutes after verification.
        </div>
    </div>

    <script>
        // Auto-uppercase input
        document.getElementById('nric_last4').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>
