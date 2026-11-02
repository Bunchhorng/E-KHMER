<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Inventory;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly ?int $shopId;

    public readonly ?int $productId;

    public readonly int $variantId;

    public readonly ?string $sku;

    public readonly int $threshold;

    public readonly ?string $shopName;

    public function __construct(
        Inventory $inventory,
        public readonly string $productName,
        public readonly string $variantName,
        public readonly int $availableQuantity,
    ) {
        $this->afterCommit();
        $variant = $inventory->variant;
        $this->shopId = $variant?->product?->shop_id;
        $this->shopName = $variant?->product?->shop?->name;
        $this->productId = $variant?->product_id;
        $this->variantId = (int) $inventory->product_variant_id;
        $this->sku = $variant?->sku;
        $this->threshold = (int) $inventory->low_stock_threshold;
    }

    /** Recheck membership if access was revoked while a delivery was queued. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel === 'mail' && ! Setting::boolean('emailLowStockAlerts', true)) {
            return false;
        }

        return $notifiable->isSuperAdmin()
            || ($this->shopId !== null && $notifiable->managedShops()->whereKey($this->shopId)->exists());
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
            ->action('Review inventory', rtrim((string) config('app.frontend_url'), '/').$data['url']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $item = $this->productName.($this->variantName !== '' && $this->variantName !== 'Default' ? ' ('.$this->variantName.')' : '');

        return [
            'title' => 'Low stock alert',
            'message' => '"'.$item.'"'.($this->shopName !== null ? ' at '.$this->shopName : '').' is running low on stock: only '.$this->availableQuantity.' left. SKU: '.($this->sku ?? 'N/A').'. Threshold: '.$this->threshold.'.',
            'shop_id' => $this->shopId,
            'shop_name' => $this->shopName,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'product_variant_id' => $this->variantId,
            'variant_name' => $this->variantName,
            'sku' => $this->sku,
            'available_quantity' => $this->availableQuantity,
            'threshold' => $this->threshold,
            'url' => $notifiable->isSuperAdmin() ? '/admin/inventory' : '/seller/shops/'.$this->shopId.'/inventory',
        ];
    }
}
