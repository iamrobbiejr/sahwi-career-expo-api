<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Event Ticket - {{ $ticket->ticket_number }}</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 0;
        }
        .ticket-container {
            width: 100%;
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .ticket {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .event-name {
            font-size: 32px;
            font-weight: bold;
            color: #2d3748;
            margin: 0 0 10px 0;
        }
        .ticket-type {
            font-size: 16px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .details-grid {
            display: table;
            width: 100%;
            margin: 30px 0;
        }
        .detail-row {
            display: table-row;
        }
        .detail-label {
            display: table-cell;
            padding: 12px 0;
            font-weight: bold;
            color: #4a5568;
            width: 40%;
        }
        .detail-value {
            display: table-cell;
            padding: 12px 0;
            color: #2d3748;
        }
        .barcode-section {
            text-align: center;
            margin: 40px 0;
            padding: 30px;
            background: #f7fafc;
            border-radius: 10px;
        }
        .barcode-image {
            margin: 20px 0;
        }
        .ticket-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
            margin-top: 10px;
            letter-spacing: 3px;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #e2e8f0;
            color: #718096;
            font-size: 12px;
        }
        .important-notice {
            background: #fff5f5;
            border-left: 4px solid #f56565;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .venue-highlight {
            background: #ebf8ff;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="ticket-container">
    <div class="ticket">
        <!-- Header -->
        <div class="header">
            <div class="event-name">{{ $event->name }}</div>
            <div class="ticket-type">{{ ucfirst($registration->attendee_type) }} Ticket</div>
        </div>

        <!-- Attendee Details -->
        <div class="details-grid">
            <div class="detail-row">
                <div class="detail-label">Attendee Name:</div>
                <div class="detail-value">{{ $registration->attendee_name }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email:</div>
                <div class="detail-value">{{ $registration->attendee_email }}</div>
            </div>
            @if($registration->attendee_phone)
                <div class="detail-row">
                    <div class="detail-label">Phone:</div>
                    <div class="detail-value">{{ $registration->attendee_phone }}</div>
                </div>
            @endif
            @if($registration->attendee_title)
                <div class="detail-row">
                    <div class="detail-label">Title:</div>
                    <div class="detail-value">{{ $registration->attendee_title }}</div>
                </div>
            @endif
            @if($registration->organization)
                <div class="detail-row">
                    <div class="detail-label">Organization:</div>
                    <div class="detail-value">{{ $registration->organization->name }}</div>
                </div>
            @endif
        </div>

        <!-- Event Details -->
        <div class="venue-highlight">
            <div class="details-grid">
                <div class="detail-row">
                    <div class="detail-label">Date & Time:</div>
                    <div class="detail-value">{{ $event->start_date->format('F j, Y g:i A') }}</div>
                </div>
                @if($event->venue)
                    <div class="detail-row">
                        <div class="detail-label">Venue:</div>
                        <div class="detail-value">{{ $event->venue }}</div>
                    </div>
                @endif
                @if($event->location)
                    <div class="detail-row">
                        <div class="detail-label">Location:</div>
                        <div class="detail-value">{{ $event->location }}</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Barcode -->
        <div class="barcode-section">
            <div style="font-size: 14px; color: #4a5568; margin-bottom: 10px;">
                Please present this code at the entrance
            </div>
            <div class="barcode-image">
                <img src="data:image/png;base64,{{ $barcode }}" alt="Barcode" style="height: 100px;">
            </div>
            <div class="ticket-number">{{ $ticket->ticket_number }}</div>
        </div>

        <!-- Important Notice -->
        <div class="important-notice">
            <strong>Important:</strong> This ticket is non-transferable and must be presented along with a valid photo ID at the event entrance. Screenshots are not accepted.
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Issued on {{ $ticket->created_at->format('F j, Y g:i A') }}</p>
            <p>For support, please contact {{ config('mail.from.address') }}</p>
        </div>
    </div>
</div>
</body>
</html>
