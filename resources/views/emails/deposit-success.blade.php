<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Deposit Successful</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            background: #10b981;
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
            <h1>Wallet Funded!</h1>
        </div>
        <div class="content">
            <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
            <p>Your wallet has been successfully funded. The balance is now available for your use on the platform.</p>
            
            <div class="details-box">
                <div class="detail-row">
                    <span class="label">Amount</span>
                    <span class="value">{{ $currency }} {{ number_format($amount, 2) }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Reference</span>
                    <span class="value">{{ $reference }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Date</span>
                    <span class="value">{{ now()->format('M d, Y H:i') }}</span>
                </div>
            </div>

            <p>Thank you for choosing Fajiri.</p>
            
            <a href="{{ config('app.frontend_url', 'https://app.fajiri.org') }}/wallet" class="btn">View Wallet Balance</a>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Fajiri. All rights reserved.
        </div>
    </div>
</body>
</html>
