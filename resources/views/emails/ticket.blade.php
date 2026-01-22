<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #667eea;">Your Event Ticket</h2>

    <p>Dear {{ $registration->attendee_name }},</p>

    <p>Thank you for registering for <strong>{{ $event->name }}</strong>!</p>

    <div style="background: #f7fafc; padding: 20px; border-radius: 10px; margin: 20px 0;">
        <h3 style="margin-top: 0;">Event Details</h3>
        <p><strong>Event:</strong> {{ $event->name }}</p>
        <p><strong>Date:</strong> {{ $event->start_date->format('F j, Y g:i A') }}</p>
        @if($event->venue)
            <p><strong>Venue:</strong> {{ $event->venue }}</p>
        @endif
        <p><strong>Ticket Number:</strong> {{ $ticket->ticket_number }}</p>
    </div>

    <p>Your ticket is attached to this email as a PDF. Please:</p>
    <ul>
        <li>Download and save your ticket</li>
        <li>Print your ticket or have it ready on your mobile device</li>
        <li>Bring a valid photo ID to the event</li>
    </ul>

    <p style="background: #fff5f5; padding: 15px; border-left: 4px solid #f56565; border-radius: 5px;">
        <strong>Important:</strong> Screenshots are not accepted. Please present the original PDF or printed ticket.
    </p>

    <p>If you have any questions, please don't hesitate to contact us.</p>

    <p>See you at the event!</p>

    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">

    <p style="font-size: 12px; color: #718096;">
        This is an automated email. Please do not reply to this message.
    </p>
</div>
</body>
</html>
