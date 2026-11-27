<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates deterministic, sharp-cornered local placeholder images on the
 * `public` disk so seeded products, shops, brands, and receipts render cleanly
 * offline without relying on external URLs.
 */
class DemoMediaGenerator
{
    /** @var array<string, array{bg: array{int, int, int}, accent: array{int, int, int}, label: string}> */
    private const PALETTES = [
        'khmer-food-snacks' => ['bg' => [246, 238, 224], 'accent' => [180, 83, 9], 'label' => 'KHMER PANTRY'],
        'rice-agricultural-products' => ['bg' => [236, 243, 232], 'accent' => [63, 98, 18], 'label' => 'HARVEST'],
        'kampot-pepper-spices' => ['bg' => [244, 232, 226], 'accent' => [127, 29, 29], 'label' => 'KAMPOT SPICE'],
        'cambodian-coffee-beverages' => ['bg' => [239, 231, 224], 'accent' => [92, 51, 23], 'label' => 'ROASTERY'],
        'traditional-khmer-clothing' => ['bg' => [245, 234, 214], 'accent' => [146, 64, 14], 'label' => 'HERITAGE'],
        'krama-cambodian-silk' => ['bg' => [242, 231, 236], 'accent' => [157, 23, 77], 'label' => 'KHMER SILK'],
        'handicrafts-souvenirs' => ['bg' => [238, 233, 223], 'accent' => [113, 63, 18], 'label' => 'ARTISAN'],
        'bamboo-rattan-products' => ['bg' => [241, 238, 222], 'accent' => [101, 109, 44], 'label' => 'BAMBOO'],
        'fashion-shoes' => ['bg' => [231, 236, 245], 'accent' => [30, 58, 138], 'label' => 'APPAREL'],
        'electronics-accessories' => ['bg' => [226, 232, 240], 'accent' => [15, 23, 42], 'label' => 'TECH STORE'],
        'beauty-personal-care' => ['bg' => [248, 234, 240], 'accent' => [131, 24, 67], 'label' => 'BOTANICAL'],
        'home-kitchen' => ['bg' => [232, 241, 242], 'accent' => [17, 94, 89], 'label' => 'HOME LIVING'],
        'books-stationery' => ['bg' => [237, 236, 247], 'accent' => [67, 56, 202], 'label' => 'BOOKS'],
        'sports-outdoor' => ['bg' => [229, 243, 236], 'accent' => [21, 128, 61], 'label' => 'OUTDOOR'],
        'baby-family-products' => ['bg' => [232, 242, 250], 'accent' => [3, 105, 161], 'label' => 'FAMILY'],
    ];

    public static function productImageUrl(string $categorySlug, int $variantIndex, string $title): string
    {
        $slot = ($variantIndex % 3) + 1;
        $relativePath = "images/products/demo-{$categorySlug}-{$slot}.png";
        $palette = self::PALETTES[$categorySlug] ?? ['bg' => [236, 240, 245], 'accent' => [29, 78, 216], 'label' => 'E-KHMER'];

        self::ensureSquareImage($relativePath, 400, 400, $palette['bg'], $palette['accent'], $palette['label'], strtoupper($categorySlug), $slot);

        return Storage::disk('public')->url($relativePath);
    }

    public static function shopLogoUrl(string $code, string $name): string
    {
        $slug = Str::slug($code);
        $relativePath = "images/shops/demo-logo-{$slug}.png";
        $palette = self::shopPalette($code);

        self::ensureSquareImage($relativePath, 240, 240, $palette['accent'], [255, 255, 255], strtoupper(Str::substr($code, 0, 8)), strtoupper(Str::substr($name, 0, 18)), 1);

        return Storage::disk('public')->url($relativePath);
    }

    public static function shopBannerUrl(string $code, string $name): string
    {
        $slug = Str::slug($code);
        $relativePath = "images/shops/demo-banner-{$slug}.png";
        $palette = self::shopPalette($code);

        self::ensureBannerImage($relativePath, 960, 320, $palette['bg'], $palette['accent'], strtoupper(Str::substr($name, 0, 28)), 'E-KHMER CAMBODIA MARKETPLACE');

        return Storage::disk('public')->url($relativePath);
    }

    public static function brandLogoUrl(string $slug, string $name): string
    {
        $relativePath = "images/brands/demo-brand-{$slug}.png";
        $palette = self::shopPalette($slug);

        self::ensureSquareImage($relativePath, 200, 200, [248, 250, 252], $palette['accent'], strtoupper(Str::substr($name, 0, 14)), 'VERIFIED BRAND', 2);

        return Storage::disk('public')->url($relativePath);
    }

    public static function categoryImageUrl(string $slug): string
    {
        $relativePath = "images/categories/demo-cat-{$slug}.png";
        $palette = self::PALETTES[$slug] ?? ['bg' => [236, 240, 245], 'accent' => [29, 78, 216], 'label' => 'CATEGORY'];

        self::ensureSquareImage($relativePath, 320, 320, $palette['bg'], $palette['accent'], $palette['label'], strtoupper($slug), 1);

        return Storage::disk('public')->url($relativePath);
    }

    /** @return array{bg: array{int, int, int}, accent: array{int, int, int}} */
    private static function shopPalette(string $seed): array
    {
        $palettes = array_values(self::PALETTES);
        $chosen = $palettes[abs(crc32($seed)) % count($palettes)];

        return ['bg' => $chosen['bg'], 'accent' => $chosen['accent']];
    }

    /**
     * @param  array{int, int, int}  $bgRgb
     * @param  array{int, int, int}  $accentRgb
     */
    private static function ensureSquareImage(
        string $relativePath,
        int $width,
        int $height,
        array $bgRgb,
        array $accentRgb,
        string $headline,
        string $subline,
        int $patternVariant,
    ): void {
        $disk = Storage::disk('public');
        if ($disk->exists($relativePath)) {
            return;
        }

        $bytes = self::renderCardPng($width, $height, $bgRgb, $accentRgb, $headline, $subline, $patternVariant);
        $disk->put($relativePath, $bytes);
    }

    /**
     * @param  array{int, int, int}  $bgRgb
     * @param  array{int, int, int}  $accentRgb
     */
    private static function ensureBannerImage(
        string $relativePath,
        int $width,
        int $height,
        array $bgRgb,
        array $accentRgb,
        string $headline,
        string $subline,
    ): void {
        $disk = Storage::disk('public');
        if ($disk->exists($relativePath)) {
            return;
        }

        $bytes = self::renderCardPng($width, $height, $bgRgb, $accentRgb, $headline, $subline, 3);
        $disk->put($relativePath, $bytes);
    }

    /**
     * @param  array{int, int, int}  $bgRgb
     * @param  array{int, int, int}  $accentRgb
     */
    private static function renderCardPng(
        int $width,
        int $height,
        array $bgRgb,
        array $accentRgb,
        string $headline,
        string $subline,
        int $patternVariant,
    ): string {
        if (! function_exists('imagecreatetruecolor')) {
            // Minimal valid 1x1 PNG fallback when GD is unavailable.
            return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2Z0AAAAASUVORK5CYII=');
        }

        $image = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($image, $bgRgb[0], $bgRgb[1], $bgRgb[2]);
        $accent = imagecolorallocate($image, $accentRgb[0], $accentRgb[1], $accentRgb[2]);
        $border = imagecolorallocate($image, max(0, $accentRgb[0] - 25), max(0, $accentRgb[1] - 25), max(0, $accentRgb[2] - 25));
        $surface = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 71, 85, 105);

        imagefill($image, 0, 0, $bg);

        // Sharp-cornered geometric frame matching E-KHMER design system.
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $border);
        imagefilledrectangle($image, 0, 0, $width - 1, max(10, (int) ($height * 0.08)), $accent);

        $pad = (int) round(min($width, $height) * 0.14);
        $cardTop = max(24, (int) round($height * 0.22));
        $cardBottom = $height - max(24, (int) round($height * 0.18));
        imagefilledrectangle($image, $pad, $cardTop, $width - $pad, $cardBottom, $surface);
        imagerectangle($image, $pad, $cardTop, $width - $pad, $cardBottom, $accent);

        if ($patternVariant === 1) {
            imagefilledrectangle($image, $pad + 18, $cardTop + 18, $pad + 54, $cardTop + 54, $accent);
        } elseif ($patternVariant === 2) {
            imagefilledrectangle($image, $width - $pad - 54, $cardTop + 18, $width - $pad - 18, $cardTop + 54, $accent);
        } else {
            imagefilledrectangle($image, $pad, $cardTop, $pad + 14, $cardBottom, $accent);
        }

        $titleText = Str::limit($headline, 24, '');
        $subText = Str::limit($subline, 28, '');
        $centerY = (int) round(($cardTop + $cardBottom) / 2);
        imagestring($image, 5, $pad + 24, $centerY - 12, $titleText, $accent);
        imagestring($image, 3, $pad + 24, $centerY + 12, $subText, $muted);

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
