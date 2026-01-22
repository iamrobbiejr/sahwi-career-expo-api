<?php

namespace App\Notifications;

use App\Models\EmailBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BroadcastCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public EmailBroadcast $broadcast
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
        $successRate = $this->broadcast->getSuccessRate();

        return (new MailMessage)
            ->subject('Email Broadcast Completed Successfully')
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('Your email broadcast has been completed successfully.')
            ->line('**Broadcast Details:**')
            ->line('Subject: ' . $this->broadcast->subject)
            ->line('Total Recipients: ' . number_format($this->broadcast->total_recipients))
            ->line('Successfully Sent: ' . number_format($this->broadcast->sent_count))
            ->line('Failed: ' . number_format($this->broadcast->failed_count))
            ->line('Success Rate: ' . $successRate . '%')
            ->action('View Broadcast Details', url('/admin/broadcasts/' . $this->broadcast->id))
            ->line('Thank you for using our broadcast service!');
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
            'total_recipients' => $this->broadcast->total_recipients,
            'sent_count' => $this->broadcast->sent_count,
            'failed_count' => $this->broadcast->failed_count,
            'success_rate' => $this->broadcast->getSuccessRate(),
            'message' => 'Your email broadcast has been completed successfully.',
        ];
    }
}
