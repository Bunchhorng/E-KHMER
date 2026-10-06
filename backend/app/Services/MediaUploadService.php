<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $path = $file->store("images/{$context}", 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException("Failed to store the uploaded image in images/{$context}.");
        }

        return Storage::disk('public')->url($path);
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

        if ($relative === null || !str_starts_with($relative, 'images/')) {
            return;
        }

        Storage::disk('public')->delete($relative);
    }
}
