<?php

namespace App\Mail;

use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BroadcastEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public EmailBroadcast $broadcast,
        public EmailBroadcastRecipient $recipient
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $from = $this->broadcast->from_email
            ? new Address($this->broadcast->from_email, $this->broadcast->from_name ?? config('mail.from.name'))
            : new Address(config('mail.from.address'), config('mail.from.name'));

        $envelope = Envelope::make()
            ->from($from)
            ->subject($this->broadcast->subject);

        if ($this->broadcast->reply_to_email) {
            $envelope->replyTo($this->broadcast->reply_to_email);
        }

        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = $this->broadcast->template
            ? "emails.broadcasts.{$this->broadcast->template}"
            : 'emails.broadcasts.default';

        return new Content(
            view: $view,
            with: [
                'broadcast' => $this->broadcast,
                'recipient' => $this->recipient,
                'user' => $this->recipient->user,
                'trackingPixelUrl' => $this->getTrackingPixelUrl(),
                'unsubscribeUrl' => $this->getUnsubscribeUrl(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (empty($this->broadcast->attachments)) {
            return [];
        }

        return collect($this->broadcast->attachments)->map(function ($filePath) {
            return Attachment::fromPath(storage_path('app/' . $filePath));
        })->toArray();
    }

    /**
     * Get tracking pixel URL.
     */
    protected function getTrackingPixelUrl(): ?string
    {
        if (!$this->broadcast->track_opens) {
            return null;
        }

        return route('broadcast.track.open', [
            'broadcast' => $this->broadcast->tracking_id,
            'recipient' => $this->recipient->id,
        ]);
    }

    /**
     * Get unsubscribe URL.
     */
    protected function getUnsubscribeUrl(): string
    {
        return route('broadcast.unsubscribe', [
            'recipient' => $this->recipient->id,
        ]);
    }
}
