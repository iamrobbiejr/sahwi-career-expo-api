<?php

namespace App\Notifications;

use App\Models\EmailBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public EmailBroadcast $broadcast,
        public string $errorMessage
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email Broadcast Failed')
            ->error()
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Unfortunately, your email broadcast has failed to process.')
            ->line('**Broadcast Details:**')
            ->line('Subject: ' . $this->broadcast->subject)
            ->line('Error: ' . $this->errorMessage)
            ->line('Please review the broadcast settings and try again.')
            ->action('View Broadcast Details', url('/admin/broadcasts/' . $this->broadcast->id))
            ->line('If the problem persists, please contact support.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'broadcast_id' => $this->broadcast->id,
            'subject' => $this->broadcast->subject,
            'error_message' => $this->errorMessage,
            'message' => 'Your email broadcast has failed to process.',
        ];
    }
}
