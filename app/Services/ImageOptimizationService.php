<?php

namespace App\Services;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizationService
{
    protected const MAX_UPLOAD_BYTES = 5242880; // 5 MB

    protected const MAX_DIMENSION = 1600;

    protected const TARGET_MAX_BYTES = 512000; // 500 KB ceiling

    protected const ALLOWED_MIMES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/webp',
    ];

    /**
     * Process, resize, and compress an uploaded image into an optimized WebP file in private storage.
     *
     * @return array{path: string, size: int, mime: string}
     *
     * @throws Exception
     */
    public function optimizeAndStore(UploadedFile $file): array
    {
        // 1. Strict File Security Validation
        if (! $file->isValid()) {
            throw new Exception('Invalid uploaded file or upload error.');
        }

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new Exception('File exceeds maximum allowed size of 5 MB.');
        }

        $realMime = $file->getMimeType();
        if (! in_array(strtolower($realMime), self::ALLOWED_MIMES)) {
            throw new Exception('Invalid image type. Only JPEG, PNG, and WebP images are permitted.');
        }

        $tempPath = $file->getRealPath();
        if (! $tempPath || ! file_exists($tempPath)) {
            throw new Exception('Temporary upload path is inaccessible.');
        }

        // 2. Load into GD resource
        $sourceImage = null;
        switch (strtolower($realMime)) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $sourceImage = @imagecreatefromjpeg($tempPath);
                // Correct EXIF orientation if available
                if (function_exists('exif_read_data')) {
                    $exif = @exif_read_data($tempPath);
                    if (! empty($exif['Orientation'])) {
                        switch ($exif['Orientation']) {
                            case 3:
                                $sourceImage = imagerotate($sourceImage, 180, 0);
                                break;
                            case 6:
                                $sourceImage = imagerotate($sourceImage, -90, 0);
                                break;
                            case 8:
                                $sourceImage = imagerotate($sourceImage, 90, 0);
                                break;
                        }
                    }
                }
                break;
            case 'image/png':
            case 'image/x-png':
                $sourceImage = @imagecreatefrompng($tempPath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $sourceImage = @imagecreatefromwebp($tempPath);
                }
                break;
        }

        if (! $sourceImage) {
            throw new Exception('Unable to process or decode image contents.');
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);

        // 3. Compute Aspect-Ratio Constrained Dimensions (max 1600x1600)
        $targetWidth = $origWidth;
        $targetHeight = $origHeight;

        if ($origWidth > self::MAX_DIMENSION || $origHeight > self::MAX_DIMENSION) {
            $ratio = min(self::MAX_DIMENSION / $origWidth, self::MAX_DIMENSION / $origHeight);
            $targetWidth = max(1, (int) round($origWidth * $ratio));
            $targetHeight = max(1, (int) round($origHeight * $ratio));
        }

        // 4. Create TrueColor Target Image
        $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency if applicable
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefilledrectangle($resizedImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagealphablending($resizedImage, true);

        imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $origWidth, $origHeight
        );

        imagedestroy($sourceImage);

        // 5. Generate Private Storage Target Path (YYYY/MM/<random_uuid>.webp)
        $yearMonth = date('Y/m');
        $randomName = Str::uuid()->toString().'.webp';
        $relativePath = "private/transaction-proofs/{$yearMonth}/{$randomName}";

        // Ensure destination directory exists on local disk
        $fullPath = Storage::disk('local')->path($relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 6. Progressive WebP Compression (Target 100-350 KB, ceiling < 500 KB)
        $quality = 75;
        imagewebp($resizedImage, $fullPath, $quality);

        // If file is still larger than ceiling, progressively compress down to quality 55
        while (filesize($fullPath) > self::TARGET_MAX_BYTES && $quality > 55) {
            $quality -= 5;
            imagewebp($resizedImage, $fullPath, $quality);
        }

        imagedestroy($resizedImage);

        $finalSize = filesize($fullPath);

        return [
            'path' => $relativePath,
            'size' => $finalSize,
            'mime' => 'image/webp',
        ];
    }

    /**
     * Safely delete a transaction proof image from storage.
     */
    public function deleteImage(?string $relativePath): bool
    {
        if (empty($relativePath)) {
            return false;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->delete($relativePath);
        }

        return false;
    }
}
