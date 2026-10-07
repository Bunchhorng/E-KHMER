<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;

class ReceiptBrandingService
{
    /**
     * Resolve the visual identity for a receipt.
     *
     * A receipt containing one shop's lines carries that shop's branding. A
     * multi-shop parent order intentionally falls back to marketplace branding:
     * choosing one seller's logo for several sellers would be misleading.
     *
     * @return array{name: string, tagline: string, logo: ?string, mark: string}
     */
    public function forOrder(Order $order): array
    {
        $order->items->each(function ($item): void {
            $item->setAttribute('receipt_image', $this->localImageDataUri($item->image_path));
        });

        $shops = $order->items
            ->map(fn ($item) => $item->shop)
            ->filter()
            ->unique('id')
            ->values();

        /** @var Shop|null $shop */
        $shop = $shops->count() === 1 ? $shops->first() : null;
        $name = $shop?->name ?? config('app.name', 'E-KHMER');

        return [
            'name' => $name,
            'tagline' => $shop?->description ?: 'E-Commerce Store',
            'logo' => $this->localImageDataUri($shop?->logo),
            'mark' => mb_strtoupper(mb_substr($name, 0, 1)),
        ];
    }

    /**
     * DomPDF renders locally embedded images consistently in downloads, even
     * when the application's public storage URL is not reachable from PHP.
     */
    public function localImageDataUri(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $path = ltrim((string) (parse_url($url, PHP_URL_PATH) ?? $url), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (! str_starts_with($path, 'images/') || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
