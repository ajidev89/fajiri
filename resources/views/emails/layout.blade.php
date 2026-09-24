<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
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
            background: #ffffff;
            padding: 40px 20px 20px;
            text-align: center;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }
        .content {
            padding: 40px;
        }
        .content h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 16px;
        }
        .content p {
            margin: 0 0 16px;
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
        .btn {
            display: inline-block;
            background: #10b981;
            color: #ffffff !important;
            padding: 12px 28px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 8px;
        }
        .footer {
            padding: 30px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            background: #fcfdfe;
        }
        @yield('styles')
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('logo.png') }}" alt="Fajiri Logo" style="max-height: 50px; margin-bottom: 10px;">
        </div>
        <div class="content">
            @yield('content')
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Fajiri. All rights reserved.
        </div>
    </div>
</body>
</html>
