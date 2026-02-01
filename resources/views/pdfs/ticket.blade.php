<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Ticket - {{ $ticket->ticket_number }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .page {
            width: 100%;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            box-sizing: border-box;
        }
        .ticket {
            width: 340px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .ticket-image {
            width: 100%;
            height: 160px;
            object-fit: cover;
            display: block;
        }

        .ticket-image-placeholder {
            width: 100%;
            height: 160px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #a0aec0;
            font-size: 13px;
        }

        .ticket-body {
            padding: 24px 28px 20px;
        }
        .event-name {
            font-size: 18px;
            font-weight: bold;
            color: #1a202c;
            margin: 0 0 4px 0;
            line-height: 1.3;
        }

        .ticket-type-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #fff;
            background: #2b6cb0;
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 18px;
        }

        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 16px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .detail-label {
            font-size: 11px;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: bold;
        }
        .detail-value {
            font-size: 11.5px;
            color: #2d3748;
            text-align: right;
            max-width: 200px;
            word-wrap: break-word;
        }
        .barcode-section {
            text-align: center;
            margin-top: 18px;
            padding: 16px 0 10px;
            border-top: 2px dashed #e2e8f0;
        }
        .barcode-image {
            margin: 0 auto;
        }
        .ticket-number {
            font-size: 13px;
            font-weight: bold;
            color: #2b6cb0;
            letter-spacing: 2.5px;
            margin-top: 6px;
        }
        .footer {
            text-align: center;
            padding: 12px 28px 18px;
            border-top: 1px solid #f0f0f0;
        }

        .footer p {
            margin: 0;
            font-size: 9.5px;
            color: #a0aec0;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<div class="page">
    <div class="ticket">

        @if($event->img)
            <img src="{{ $event->img }}" alt="{{ $event->name }}" class="ticket-image">
        @else
            <div class="ticket-image-placeholder">No Event Image</div>
        @endif

        <div class="ticket-body">
            <div class="event-name">{{ $event->name }}</div>
            <div class="ticket-type-badge">{{ ucfirst($registration->attendee_type) }} Ticket</div>

            <hr class="divider">

            <div class="detail-row">
                <span class="detail-label">Attendee</span>
                <span class="detail-value">{{ $registration->attendee_name }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value">{{ $registration->attendee_email }}</span>
            </div>
            @if($registration->attendee_phone)
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">{{ $registration->attendee_phone }}</span>
                </div>
            @endif

            @if($registration->attendee_title)
                <div class="detail-row">
                    <span class="detail-label">Title</span>
                    <span class="detail-value">{{ $registration->attendee_title }}</span>
                </div>
            @endif
            @if($registration->organization)
                <div class="detail-row">
                    <span class="detail-label">Organization</span>
                    <span class="detail-value">{{ $registration->organization->name }}</span>
                </div>
            @endif

            <hr class="divider">

            <div class="detail-row">
                <span class="detail-label">Date</span>
                <span class="detail-value">{{ $event->start_date->format('M j, Y g:i A') }}</span>
            </div>
            @if($event->venue)
                <div class="detail-row">
                    <span class="detail-label">Venue</span>
                    <span class="detail-value">{{ $event->venue }}</span>
                </div>
            @endif
            @if($event->location)
                <div class="detail-row">
                    <span class="detail-label">Location</span>
                    <span class="detail-value">{{ $event->location }}</span>
                </div>
            @endif

            <div class="barcode-section">
                <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode" class="barcode-image"
                     style="height: 70px;">
                <div class="ticket-number">{{ $ticket->ticket_number }}</div>
            </div>
        </div>

        <div class="footer">
            <p>This ticket is non-transferable. Present with valid photo ID at entry.</p>
            <p>Issued {{ $ticket->created_at->format('M j, Y') }} &middot; {{ config('mail.from.address') }}</p>
        </div>

    </div>
</div>
</body>
</html>
