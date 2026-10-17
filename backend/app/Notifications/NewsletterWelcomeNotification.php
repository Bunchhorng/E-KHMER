<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterWelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $token)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('E-KHMER newsletter subscription')
            ->line('Your email has been subscribed to E-KHMER updates.')
            ->line('If you did not request this subscription, use the link below.')
            ->action('Unsubscribe', rtrim(config('app.url'), '/').'/api/newsletter/unsubscribe/'.$this->token);
    }
}
