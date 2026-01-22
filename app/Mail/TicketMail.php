<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket
    ) {}

    public function build(): TicketMail
    {
        $registration = $this->ticket->registration;
        $event = $registration->event;

        return $this->subject("Your Ticket for {$event->name}")
            ->view('emails.ticket')
            ->with([
                'ticket' => $this->ticket,
                'registration' => $registration,
                'event' => $event,
            ])
            ->attachData(
                Storage::get($this->ticket->pdf_path),
                "ticket-{$this->ticket->ticket_number}.pdf",
                ['mime' => 'application/pdf']
            );
    }
}
