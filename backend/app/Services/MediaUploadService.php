<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MediaUploadService
{
    private const CONTEXTS = ['products', 'brands', 'categories', 'shops', 'avatars'];

    /**
     * Store an uploaded image on the public disk and return its public URL.
     *
     * A failed write must be loud. Storage::put() swallows the error and returns
     * false (for example when the target directory is not writable by the FPM
     * user), and url(false) then degrades to the bare disk URL. That silently
     * persisted a path with no filename, so the record looked like it had an
     * image while every request for it 404s.
     *
     * @throws RuntimeException
     */
    public function storeImage(UploadedFile $file, string $context = 'products'): string
    {
        abort_unless(in_array($context, self::CONTEXTS, true), 422, 'Invalid upload context.');

        $path = $this->storeOptimizedImage($file, $context);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("Failed to store the uploaded image in images/{$context}.");
        }

        return Storage::disk('public')->url($path);
    }

    private function storeOptimizedImage(UploadedFile $file, string $context): string|false
    {
        $info = @getimagesize($file->getRealPath());
        if (! $info || $info[0] * $info[1] > 12500000 || max($info[0], $info[1]) > 10000) {
            throw ValidationException::withMessages(['image' => 'Choose an image up to 12.5 megapixels and 10,000 pixels per side.']);
        }
        $type = $info[2];
        if (! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || ($type === IMAGETYPE_WEBP && ! function_exists('imagewebp'))) {
            return $file->store("images/{$context}", 'public');
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if ($image === false) {
            throw ValidationException::withMessages(['image' => 'This image could not be decoded.']);
        }
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
            if (in_array($orientation, [2, 4, 5, 7], true)) {
                imageflip($image, in_array($orientation, [4, 5], true) ? IMG_FLIP_VERTICAL : IMG_FLIP_HORIZONTAL);
            }
            $angle = match ($orientation) {
                3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
            };
            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0);
            }
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1920 / max($width, $height));
        if ($scale < 1) {
            $resized = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
            $image = $resized;
        }
        ob_start();
        try {
            $written = match ($type) {
                IMAGETYPE_JPEG => imagejpeg($image, null, 85), IMAGETYPE_PNG => imagepng($image, null, 6), default => imagewebp($image, null, 85)
            };
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (! $written || ! is_string($contents)) {
            throw new RuntimeException('Image optimization failed.');
        }
        $extension = match ($type) {
            IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', default => 'webp'
        };
        $path = "images/{$context}/".Str::random(40).'.'.$extension;

        return Storage::disk('public')->put($path, $contents) ? $path : false;
    }

    /**
     * Safely delete an image referenced by a public URL created with storeImage().
     */
    public function deleteImage(?string $url): void
    {
        if ($url === null || $url === '') {
            return;
        }

        $storageUrl = rtrim((string) config('filesystems.disks.public.url'), '/');

        $relative = null;

        if (str_starts_with($url, $storageUrl)) {
            $relative = ltrim(substr($url, strlen($storageUrl)), '/');
        } elseif (str_starts_with($url, '/storage/')) {
            $relative = ltrim(substr($url, strlen('/storage/')), '/');
        }

        if ($relative === null || ! str_starts_with($relative, 'images/')) {
            return;
        }

        Storage::disk('public')->delete($relative);
    }
}
