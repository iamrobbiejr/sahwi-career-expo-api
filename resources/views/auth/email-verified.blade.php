<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verified</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="3;url={{ config('app.frontend_url') }}">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .container {
            background: white;
            padding: 3rem 2rem;
            border-radius: 16px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-container {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: {{ $alreadyVerified ? '#f3f4f6' : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }};
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.6s ease-out 0.2s both;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .icon {
            width: 40px;
            height: 40px;
            stroke: {{ $alreadyVerified ? '#6b7280' : 'white' }};
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        @keyframes checkmark {
            0% {
                stroke-dashoffset: 100;
            }
            100% {
                stroke-dashoffset: 0;
            }
        }

        .checkmark {
            stroke-dasharray: 100;
            animation: checkmark 0.8s ease-out 0.4s both;
        }

        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.75rem;
        }

        p {
            font-size: 1rem;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        .btn:active {
            transform: translateY(0);
        }

        .redirect-info {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            font-size: 0.875rem;
            color: #9ca3af;
        }

        .countdown {
            font-weight: 600;
            color: #667eea;
        }

        @media (max-width: 480px) {
            .container {
                padding: 2rem 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .icon-container {
                width: 64px;
                height: 64px;
            }

            .icon {
                width: 32px;
                height: 32px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="icon-container">
        @if($alreadyVerified)
            <svg class="icon" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 16v-4M12 8h.01"/>
            </svg>
        @else
            <svg class="icon" viewBox="0 0 24 24">
                <path class="checkmark" d="M20 6L9 17l-5-5"/>
            </svg>
        @endif
    </div>

    <h1>
        {{ $alreadyVerified ? 'Already Verified' : 'Email Verified!' }}
    </h1>

    <p>
        {{ $alreadyVerified
            ? 'Your email address has already been verified. You can proceed to login and start using your account.'
            : 'Thank you for verifying your email address. Your account is now fully activated and ready to use!' }}
    </p>

    <a href="{{ config('app.frontend_url') }}" class="btn">
        {{ $alreadyVerified ? 'Go to Login' : 'Get Started' }}
    </a>

    <div class="redirect-info">
        Redirecting automatically in <span class="countdown" id="countdown">3</span> seconds...
    </div>
</div>

<script>
    let seconds = 3;
    const countdownEl = document.getElementById('countdown');
    const frontendUrl = "{{ config('app.frontend_url') }}";

    const interval = setInterval(() => {
        seconds--;
        countdownEl.textContent = seconds;

        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = frontendUrl;
        }
    }, 1000);
</script>
</body>
</html>
