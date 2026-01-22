<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserVerificationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;
    protected bool $approved;
    protected ?string $reason;
    public function __construct(bool $approved, ?string $reason = null)
    {
        $this->approved = $approved;
        $this->reason = $reason;
    }
    public function via($notifiable): array
    {
        return ['mail'];
    }
    public function toMail($notifiable): MailMessage
    {
        if ($this->approved) {
            return (new MailMessage)
                ->subject('Your Account Has Been Approved!')
                ->line('Congratulations! Your account has been approved.')
                ->action('Access Dashboard', url('/'))
                ->line('Thank you for being part of our community!');
        }
        return (new MailMessage)
            ->subject('Account Verification Update')
            ->line("Unfortunately, your verification request was denied.")
            ->line("Reason: {$this->reason}")
            ->line("Please contact support if you have any questions.");
    }
}
