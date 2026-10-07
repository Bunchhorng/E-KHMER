<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Order $order)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return Setting::boolean('emailOrderNotifications', true)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Order confirmed: #'.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your order #'.$this->order->order_number.' has been confirmed.')
            ->line('Order total: $'.number_format((float) $this->order->total, 2))
            ->action('View order', rtrim((string) config('app.frontend_url'), '/').'/account/orders/'.$this->order->order_number);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Order confirmed',
            'message' => 'Your order #'.$this->order->order_number.' has been confirmed and is now being processed.',
            'order_number' => $this->order->order_number,
            'total' => round((float) $this->order->total, 2),
            'status' => $this->order->status,
            'url' => '/account/orders/'.$this->order->order_number,
        ];
    }
}
