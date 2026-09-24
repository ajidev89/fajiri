<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $announcement->title }}</title>
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
        .announcement-image {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 24px;
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
            <img src="{{ asset('logo.png') }}" alt="Fajiri Logo" style="max-height: 50px; margin-bottom: 10px;">
        </div>
        <div class="content">
            <p>Hi {{ $user->profile->first_name ?? 'there' }},</p>
            <h1 style="font-size: 22px; margin: 0 0 16px;">{{ $announcement->title }}</h1>
            @if ($announcement->image_url)
                <img src="{{ $announcement->image_url }}" alt="" class="announcement-image">
            @endif
            <p>{!! nl2br(e($announcement->content)) !!}</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Fajiri. All rights reserved.
        </div>
    </div>
</body>
</html>
