<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $broadcast->subject }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4A90E2;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border: 1px solid #dddddd;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
            border-radius: 0 0 5px 5px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4A90E2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .unsubscribe {
            margin-top: 20px;
            font-size: 11px;
            color: #999999;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>{{ config('app.name') }}</h1>
</div>

<div class="content">
    @if($user)
        <p>Hello {{ $user->name }},</p>
    @else
        <p>Hello,</p>
    @endif

    {!! nl2br(e($broadcast->message)) !!}
</div>

<div class="footer">
    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>

    <div class="unsubscribe">
        <p>
            If you no longer wish to receive these emails,
            <a href="{{ $unsubscribeUrl }}">click here to unsubscribe</a>.
        </p>
    </div>
</div>

@if($trackingPixelUrl)
    <img src="{{ $trackingPixelUrl }}" width="1" height="1" alt="" style="display:block" />
@endif
</body>
</html>
