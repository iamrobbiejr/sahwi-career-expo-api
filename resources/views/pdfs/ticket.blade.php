<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Ticket - {{ $ticket->ticket_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 2cm;
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #F5F4EF; /* --color-bg */
            color: #0D1B5E; /* --color-text */
            line-height: 1.4;
        }

        .page-wrapper {
            width: 100%;
            display: block;
            text-align: center;
        }

        .ticket {
            display: inline-block;
            width: 380px;
            background: #FFFFFF;
            border-radius: 16px;
            overflow: hidden;
            text-align: left;
            margin-top: 50px;
            box-shadow: 0 15px 35px rgba(13, 27, 94, 0.15);
            border: 1px solid #DCC8A0; /* --color-gold-pale */
        }

        .ticket-header {
            background-color: #0D1B5E; /* --color-navy-deep */
            padding: 24px 20px;
            text-align: center;
            border-bottom: 4px solid #C8A064; /* --color-gold */
        }

        .brand-name {
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 3px;
            color: #FFFFFF;
            margin: 0;
            text-transform: uppercase;
        }

        .brand-subtitle {
            font-size: 10px;
            color: #DCC8A0; /* --color-gold-pale */
            letter-spacing: 2px;
            margin-top: 4px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .ticket-image-container {
            width: 100%;
            height: 150px;
            background-color: #1A2B7A; /* --color-navy-mid */
            overflow: hidden;
            display: block;
        }

        .ticket-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ticket-body {
            padding: 25px;
            position: relative;
        }

        .event-title {
            font-size: 22px;
            font-weight: bold;
            color: #0D1B5E;
            margin: 0 0 10px 0;
            line-height: 1.2;
        }

        .badge {
            display: inline-block;
            background-color: #C8A064; /* --color-gold */
            color: #FFFFFF;
            font-size: 10px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-label {
            font-size: 9px;
            color: #5A6480; /* --color-text-muted */
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: bold;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #0D1B5E;
            margin-bottom: 12px;
        }

        .divider {
            height: 1px;
            border-top: 1px dashed #DCC8A0;
            margin: 20px 0;
            position: relative;
        }

        /* Punch holes for aesthetic */
        .divider:before, .divider:after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: #F5F4EF;
            border-radius: 50%;
            top: -10px;
        }

        .divider:before {
            left: -36px;
        }

        .divider:after {
            right: -36px;
        }

        .barcode-section {
            text-align: center;
            padding: 10px 0;
        }

        .barcode-container {
            margin-bottom: 10px;
        }

        .ticket-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 4px;
            color: #1A2B7A;
        }

        .footer {
            background-color: #0D1B5E;
            padding: 15px;
            text-align: center;
            color: #DCC8A0;
            font-size: 9px;
        }

        .footer p {
            margin: 0;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="ticket">
        <div class="ticket-header">
            <h1 class="brand-name">EDUGATE</h1>
            <div class="brand-subtitle">Official Event Pass</div>
        </div>

        @if($event->img)
            <div class="ticket-image-container">
                <img src="{{ $event->img }}" alt="{{ $event->name }}" class="ticket-image">
            </div>
        @endif

        <div class="ticket-body">
            <div class="badge">{{ ucfirst($registration->attendee_type) }} Ticket</div>
            <h2 class="event-title">{{ $event->name }}</h2>

            <table class="info-table">
                <tr>
                    <td colspan="2">
                        <div class="info-label">Guest Name</div>
                        <div class="info-value">{{ $registration->attendee_name }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="width: 55%;">
                        <div class="info-label">Date & Time</div>
                        <div class="info-value">
                            {{ $event->start_date->format('M j, Y') }}<br>
                            {{ $event->start_date->format('g:i A') }}
                        </div>
                    </td>
                    <td style="width: 45%;">
                        <div class="info-label">Venue</div>
                        <div class="info-value">
                            {{ $event->venue }}<br>
                            {{ $event->location }}
                        </div>
                    </td>
                </tr>
                @if($registration->organization)
                    <tr>
                        <td colspan="2">
                            <div class="info-label">Organization</div>
                            <div class="info-value">{{ $registration->organization->name }}</div>
                        </td>
                    </tr>
                @endif
            </table>

            <div class="divider"></div>

            <div class="barcode-section">
                <div class="barcode-container">
                    <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode"
                         style="height: 60px; max-width: 100%;">
                </div>
                <div class="ticket-number">{{ $ticket->ticket_number }}</div>
            </div>
        </div>

        <div class="footer">
            <p>Please present this digital or printed ticket at the venue.</p>
            <p>Powered by EduGate & Sahwira Events &copy; {{ date('Y') }}</p>
        </div>
    </div>
</div>
</body>
</html>
