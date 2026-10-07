<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\Setting;
use App\Models\ShopOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Order $order,
        public readonly string $status,
        public readonly ?ShopOrder $shopOrder = null,
    ) {}

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
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject((string) $data['title'].': #'.$this->order->order_number)
            ->greeting('Hello '.$notifiable->name.',')
            ->line((string) $data['message'])
            ->action('View order', rtrim((string) config('app.frontend_url'), '/').'/account/orders/'.$this->order->order_number);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $titles = [
            Order::STATUS_CONFIRMED => 'Order confirmed',
            Order::STATUS_PROCESSING => 'Order processing',
            Order::STATUS_SHIPPED => 'Order shipped',
            Order::STATUS_DELIVERED => 'Order delivered',
            Order::STATUS_CANCELLED => 'Order cancelled',
            Order::STATUS_REFUNDED => 'Order refunded',
        ];

        $messages = [
            Order::STATUS_CONFIRMED => 'Your order #'.$this->order->order_number.' has been confirmed.',
            Order::STATUS_PROCESSING => 'Your order #'.$this->order->order_number.' is now being processed.',
            Order::STATUS_SHIPPED => 'Your order #'.$this->order->order_number.' has been shipped and is on its way!',
            Order::STATUS_DELIVERED => 'Your order #'.$this->order->order_number.' has been delivered. We hope you love it!',
            Order::STATUS_CANCELLED => 'Your order #'.$this->order->order_number.' has been cancelled.',
            Order::STATUS_REFUNDED => 'A refund has been issued for order #'.$this->order->order_number.'.',
        ];

        if ($this->shopOrder !== null) {
            return [
                'title' => ($this->shopOrder->shop?->name ?? 'Shop').' order '.$this->status,
                'message' => 'Delivery '.$this->shopOrder->shop_order_number.' from '.($this->shopOrder->shop?->name ?? 'your shop').' is now '.$this->status.'.',
                'order_number' => $this->order->order_number,
                'shop_order_number' => $this->shopOrder->shop_order_number,
                'shop_id' => $this->shopOrder->shop_id,
                'total' => round((float) $this->shopOrder->total, 2),
                'status' => $this->status,
                'url' => '/account/orders/'.$this->order->order_number,
            ];
        }

        return [
            'title' => $titles[$this->status] ?? 'Order updated',
            'message' => $messages[$this->status] ?? 'Your order #'.$this->order->order_number.' status is now "'.$this->status.'".',
            'order_number' => $this->order->order_number,
            'total' => round((float) $this->order->total, 2),
            'status' => $this->status,
            'url' => '/account/orders/'.$this->order->order_number,
        ];
    }
}
