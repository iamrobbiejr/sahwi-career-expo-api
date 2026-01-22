<?php

namespace App\Notifications;

use App\Models\ForumPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForumPostMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $post;

    /**
     * Create a new notification instance.
     */
    public function __construct(ForumPost $post)
    {
        $this->post = $post;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned in a forum post')
            ->line($this->post->author->name . ' mentioned you in a post')
            ->line('Post: ' . $this->post->title)
            ->action('View Post', url('/forums/' . $this->post->forum_id . '/posts/' . $this->post->id))
            ->line('Thank you for participating in our forums!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'post_id' => $this->post->id,
            'forum_id' => $this->post->forum_id,
            'author_id' => $this->post->author_id,
            'author_name' => $this->post->author->name,
            'title' => $this->post->title,
        ];
    }
}
