<?php

namespace Tests\Feature\Api;

use App\Services\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    public function test_large_photos_are_resized_and_reencoded_without_embedded_metadata(): void
    {
        Storage::fake('public');
        $original = UploadedFile::fake()->image('camera.jpg', 2400, 1200);
        $bytes = file_get_contents($original->getRealPath());
        $comment = 'GPS_COMMENT_TEST';
        $bytes = substr($bytes, 0, 2)."\xFF\xFE".pack('n', strlen($comment) + 2).$comment.substr($bytes, 2);
        $file = UploadedFile::fake()->createWithContent('camera.jpg', $bytes);
        $url = app(MediaUploadService::class)->storeImage($file);
        $stored = Storage::disk('public')->path('images/products/'.basename($url));
        $info = getimagesize($stored);
        $this->assertSame(1920, $info[0]);
        $this->assertSame(960, $info[1]);
        $this->assertStringNotContainsString($comment, file_get_contents($stored));
    }

    public function test_camera_orientation_is_applied_before_metadata_is_removed(): void
    {
        Storage::fake('public');
        $original = UploadedFile::fake()->image('camera.jpg', 60, 30);
        $bytes = file_get_contents($original->getRealPath());
        $exif = "Exif\0\0II".pack('v', 42).pack('V', 8).pack('v', 1).pack('v', 0x0112).pack('v', 3).pack('V', 1).pack('v', 6)."\0\0".pack('V', 0);
        $file = UploadedFile::fake()->createWithContent('camera.jpg', substr($bytes, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($bytes, 2));
        $url = app(MediaUploadService::class)->storeImage($file);
        $info = getimagesize(Storage::disk('public')->path('images/products/'.basename($url)));
        $this->assertSame(30, $info[0]);
        $this->assertSame(60, $info[1]);
    }

    public function test_excessive_dimensions_are_rejected_before_decoding(): void
    {
        $this->expectException(ValidationException::class);
        app(MediaUploadService::class)->storeImage(UploadedFile::fake()->image('wide.png', 10001, 1));
    }
}
