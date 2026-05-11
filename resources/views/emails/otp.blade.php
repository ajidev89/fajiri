<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Verification Code</title>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            color: #1a1a1a;
            margin: 0;
            padding: 0;
            background-color: #f4f7f9;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        .header {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            padding: 40px 20px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        .content {
            padding: 40px;
            text-align: center;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 16px;
        }
        .message {
            color: #64748b;
            margin-bottom: 32px;
            font-size: 16px;
        }
        .otp-container {
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 32px;
            margin: 32px 0;
            display: inline-block;
            min-width: 240px;
        }
        .otp-code {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 42px;
            font-weight: 800;
            color: #4f46e5;
            letter-spacing: 8px;
            margin: 0;
        }
        .expiry {
            margin-top: 24px;
            font-size: 14px;
            color: #94a3b8;
        }
        .security-notice {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px;
            color: #94a3b8;
        }
        .footer {
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            background: #fcfdfe;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Fajiri</h1>
        </div>
        <div class="content">
            <p class="greeting">Verify Your Identity</p>
            <p class="message">Please use the verification code below to complete your action. This code is valid for 5 minutes.</p>
            
            <div class="otp-container">
                <p class="otp-code">{{ $code }}</p>
            </div>

            <p class="expiry">This code will expire at {{ now()->addMinutes(5)->format('H:i') }} UTC.</p>

            <div class="security-notice">
                If you did not request this code, please ignore this email or contact support if you have concerns.
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Fajiri. All rights reserved.
        </div>
    </div>
</body>
</html>
