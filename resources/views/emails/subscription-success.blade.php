<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Subscription Successful</title>
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
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .header {
            background: linear-gradient(135deg, #0052cc 0%, #003d99 100%);
            padding: 40px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 40px;
        }
        .details-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 24px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #edf2f7;
        }
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .label {
            color: #64748b;
            font-size: 14px;
        }
        .value {
            font-weight: 600;
            color: #1e293b;
        }
        .footer {
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            background: #fcfdfe;
        }
        .btn {
            display: inline-block;
            background: #0052cc;
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{ $plan->name }}</h1>
        </div>
        <div class="content">
            <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
            <p>Congratulations! You've successfully subscribed to the <strong>{{ $plan->name }}</strong> plan. Your account has been upgraded, and you now have access to premium features.</p>
            
            <div class="details-box">
                <div class="detail-row">
                    <span class="label">Plan Name</span>
                    <span class="value">{{ $plan->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Amount Paid</span>
                    <span class="value">{{ $currency }} {{ number_format($amount, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Status</span>
                    <span class="value" style="color: #10b981;">Active</span>
                </div>
            </div>

            <p>If you have any questions, feel free to reach out to our support team.</p>
            
            <a href="{{ config('app.frontend_url', 'https://app.fajiri.org') }}/dashboard" class="btn">Go to Dashboard</a>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Fajiri. All rights reserved.
        </div>
    </div>
</body>
</html>
