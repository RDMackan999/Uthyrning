<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Core\Config;
use App\Core\MediaException;
use GdImage;
use Throwable;

/**
 * Creates safe web image variants from an already validated source image.
 */
final class ImageProcessingService
{
    /**
     * Create configured image variants without upscaling the source image.
     *
     * @return array<string, array{path: string, mime_type: string, file_size_bytes: int, width: int, height: int}>
     */
    public function createVariants(string $sourcePath, string $mimeType): array
    {
        if (!extension_loaded('gd')) {
            throw new MediaException('Bildhantering saknar stöd i PHP-miljön.');
        }

        $imageInfo = @getimagesize($sourcePath);

        if ($imageInfo === false) {
            throw new MediaException('Bilden kunde inte läsas.');
        }

        $sourceWidth = (int) ($imageInfo[0] ?? 0);
        $sourceHeight = (int) ($imageInfo[1] ?? 0);
        $source = $this->loadImage($sourcePath, $mimeType);
        $variants = [];
        $temporaryPaths = [];

        try {
            foreach ($this->configuredVariants() as $variantKey => $size) {
                [$width, $height] = $this->fitDimensions(
                    $sourceWidth,
                    $sourceHeight,
                    (int) ($size['width'] ?? 0),
                    (int) ($size['height'] ?? 0)
                );
                $target = imagecreatetruecolor($width, $height);

                if (!$target instanceof GdImage) {
                    throw new MediaException('Bildvariant kunde inte skapas.');
                }

                try {
                    $this->prepareCanvas($target, $mimeType);

                    imagecopyresampled(
                        $target,
                        $source,
                        0,
                        0,
                        0,
                        0,
                        $width,
                        $height,
                        $sourceWidth,
                        $sourceHeight
                    );

                    $targetPath = $this->temporaryVariantPath($variantKey);
                    $temporaryPaths[] = $targetPath;
                    $this->saveImage($target, $targetPath, $mimeType);
                } finally {
                    imagedestroy($target);
                }

                $variants[$variantKey] = [
                    'path' => $targetPath,
                    'mime_type' => $mimeType,
                    'file_size_bytes' => filesize($targetPath) ?: 0,
                    'width' => $width,
                    'height' => $height,
                ];
            }
        } catch (Throwable $exception) {
            foreach ($temporaryPaths as $temporaryPath) {
                if (is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }

            throw $exception;
        } finally {
            imagedestroy($source);
        }

        return $variants;
    }

    /**
     * @return array<string, array{width: int, height: int}>
     */
    private function configuredVariants(): array
    {
        $variants = Config::get('media.variants', []);

        if (!is_array($variants) || $variants === []) {
            return [
                'thumbnail' => ['width' => 320, 'height' => 240],
                'card' => ['width' => 800, 'height' => 600],
                'detail' => ['width' => 1600, 'height' => 1200],
            ];
        }

        $safeVariants = [];

        foreach ($variants as $key => $size) {
            if (!is_string($key) || !is_array($size)) {
                continue;
            }

            $variantKey = strtolower(trim($key));
            $width = (int) ($size['width'] ?? 0);
            $height = (int) ($size['height'] ?? 0);

            if (!preg_match('/^[a-z0-9_-]{1,50}$/', $variantKey) || $width <= 0 || $height <= 0) {
                continue;
            }

            $safeVariants[$variantKey] = ['width' => $width, 'height' => $height];
        }

        return $safeVariants;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fitDimensions(int $sourceWidth, int $sourceHeight, int $maxWidth, int $maxHeight): array
    {
        if ($sourceWidth <= 0 || $sourceHeight <= 0 || $maxWidth <= 0 || $maxHeight <= 0) {
            throw new MediaException('Bilddimensioner är ogiltiga.');
        }

        $ratio = min(1.0, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);

        return [
            max(1, (int) round($sourceWidth * $ratio)),
            max(1, (int) round($sourceHeight * $ratio)),
        ];
    }

    private function loadImage(string $sourcePath, string $mimeType): GdImage
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$image instanceof GdImage) {
            throw new MediaException('Bildformatet kunde inte bearbetas.');
        }

        return $image;
    }

    private function prepareCanvas(GdImage $image, string $mimeType): void
    {
        if (in_array($mimeType, ['image/png', 'image/webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
            imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $transparent);
        }
    }

    private function saveImage(GdImage $image, string $targetPath, string $mimeType): void
    {
        $saved = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, $targetPath, 85),
            'image/png' => imagepng($image, $targetPath, 6),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, $targetPath, 85) : false,
            default => false,
        };

        if (!$saved || !is_file($targetPath)) {
            throw new MediaException('Bildvariant kunde inte sparas.');
        }
    }

    private function temporaryVariantPath(string $variantKey): string
    {
        $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'temp';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new MediaException('Temporär bildkatalog kunde inte skapas.');
        }

        return $directory . DIRECTORY_SEPARATOR . 'media-' . $variantKey . '-' . bin2hex(random_bytes(16));
    }
}
