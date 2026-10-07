<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Inventory;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Inventory $inventory,
        public readonly string $productName,
        public readonly string $variantName,
        public readonly int $availableQuantity,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return Setting::boolean('emailLowStockAlerts', true)
            ? ['database', 'mail']
            : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject((string) $data['title'])
            ->greeting('Hello '.$notifiable->name.',')
            ->line((string) $data['message'])
            ->action('Review inventory', rtrim((string) config('app.frontend_url'), '/').'/admin/inventory');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $item = $this->productName.($this->variantName !== '' && $this->variantName !== 'Default' ? ' ('.$this->variantName.')' : '');

        return [
            'title' => 'Low stock alert',
            'message' => '"'.$item.'" is running low on stock: only '.$this->availableQuantity.' left.',
            'product_variant_id' => $this->inventory->product_variant_id,
            'available_quantity' => $this->availableQuantity,
            'url' => '/admin/products',
        ];
    }
}
