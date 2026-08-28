<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Core\Config;
use App\Core\MediaException;
use finfo;

/**
 * Validates uploaded rental item images before storage.
 */
final class ImageValidationService
{
    /**
     * @var array<string, string>
     */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Normalize PHP's single or multi-file upload shape.
     *
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function normalizeUploadedFiles(mixed $files): array
    {
        if (!is_array($files) || $files === []) {
            return [];
        }

        if (is_array($files['name'] ?? null)) {
            $normalized = [];
            $names = $files['name'];

            foreach (array_keys($names) as $index) {
                $normalized[] = [
                    'name' => (string) ($files['name'][$index] ?? ''),
                    'type' => (string) ($files['type'][$index] ?? ''),
                    'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
                    'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int) ($files['size'][$index] ?? 0),
                ];
            }

            return $normalized;
        }

        return [[
            'name' => (string) ($files['name'] ?? ''),
            'type' => (string) ($files['type'] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error' => (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'] ?? 0),
        ]];
    }

    /**
     * Validate one uploaded image and return safe metadata.
     *
     * @param array<string, mixed> $file
     * @return array{
     *     tmp_name: string,
     *     original_filename: string|null,
     *     mime_type: string,
     *     extension: string,
     *     file_size_bytes: int,
     *     width: int,
     *     height: int,
     *     checksum_sha256: string
     * }
     */
    public function validate(array $file): array
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new MediaException('Bilden kunde inte laddas upp.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_file($tmpName)) {
            throw new MediaException('Bilden kunde inte kontrolleras.');
        }

        $reportedSize = $file['size'] ?? null;
        $actualSize = filesize($tmpName);
        $fileSize = is_numeric($reportedSize) ? (int) $reportedSize : (int) ($actualSize ?: 0);
        $maxSize = (int) Config::get('media.max_file_size_bytes', 8388608);

        if ($fileSize <= 0 || $fileSize > $maxSize) {
            throw new MediaException('Bilden är för stor eller tom.');
        }

        $imageInfo = @getimagesize($tmpName);

        if ($imageInfo === false) {
            throw new MediaException('Filen är inte en giltig bild.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $maxWidth = (int) Config::get('media.max_width', 6000);
        $maxHeight = (int) Config::get('media.max_height', 6000);

        if ($width <= 0 || $height <= 0 || $width > $maxWidth || $height > $maxHeight) {
            throw new MediaException('Bildens dimensioner är inte tillåtna.');
        }

        $mimeType = $this->detectedMimeType($tmpName);
        $imageMimeType = (string) ($imageInfo['mime'] ?? '');

        if (!isset(self::EXTENSIONS_BY_MIME[$mimeType]) || $mimeType !== $imageMimeType) {
            throw new MediaException('Bildformatet stöds inte.');
        }

        $checksum = hash_file('sha256', $tmpName);

        if (!is_string($checksum) || $checksum === '') {
            throw new MediaException('Bilden kunde inte kontrolleras.');
        }

        return [
            'tmp_name' => $tmpName,
            'original_filename' => $this->safeOriginalFilename((string) ($file['name'] ?? '')),
            'mime_type' => $mimeType,
            'extension' => self::EXTENSIONS_BY_MIME[$mimeType],
            'file_size_bytes' => $fileSize,
            'width' => $width,
            'height' => $height,
            'checksum_sha256' => $checksum,
        ];
    }

    private function detectedMimeType(string $path): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return is_string($mimeType) ? $mimeType : '';
    }

    private function safeOriginalFilename(string $filename): ?string
    {
        $basename = basename(str_replace('\\', '/', $filename));
        $basename = preg_replace('/[\x00-\x1F\x7F]+/', '', $basename) ?? '';
        $basename = trim($basename);

        if ($basename === '') {
            return null;
        }

        return substr($basename, 0, 255);
    }
}
