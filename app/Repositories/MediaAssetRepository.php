<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\MediaAsset;
use App\Services\PublicIdGenerator;
use PDO;
use RuntimeException;

/**
 * Repository for media asset metadata.
 */
final class MediaAssetRepository extends BaseRepository
{
    private const MAX_PUBLIC_ID_ATTEMPTS = 5;

    public function __construct(
        private readonly PublicIdGenerator $publicIdGenerator = new PublicIdGenerator()
    ) {
        parent::__construct(MediaAsset::class);
    }

    /**
     * Find a non-deleted media asset by primary key.
     */
    public function findById(int|string $id): MediaAsset
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM media_assets WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Media asset not found.');
        }

        return new MediaAsset($row);
    }

    /**
     * Find a non-deleted media asset by opaque public id.
     */
    public function findByPublicId(string $publicId): ?MediaAsset
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM media_assets
             WHERE public_id = :public_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['public_id' => trim($publicId)]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : new MediaAsset($row);
    }

    /**
     * Create one media asset metadata record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): MediaAsset
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO media_assets (
                public_id,
                organization_id,
                media_type_key,
                mime_type,
                original_filename,
                storage_disk_key,
                storage_key,
                checksum_sha256,
                file_size_bytes,
                width,
                height,
                uploaded_by_user_id,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :public_id,
                :organization_id,
                :media_type_key,
                :mime_type,
                :original_filename,
                :storage_disk_key,
                :storage_key,
                :checksum_sha256,
                :file_size_bytes,
                :width,
                :height,
                :uploaded_by_user_id,
                :is_active,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'public_id' => $data['public_id'] ?? $this->generateUniquePublicId(),
            'organization_id' => $this->requiredInt($data['organization_id'] ?? null, 'organization_id'),
            'media_type_key' => $this->normalizeKey((string) ($data['media_type_key'] ?? 'image')),
            'mime_type' => trim((string) ($data['mime_type'] ?? '')),
            'original_filename' => $this->nullableString($data['original_filename'] ?? null, 255),
            'storage_disk_key' => $this->normalizeKey((string) ($data['storage_disk_key'] ?? 'local')),
            'storage_key' => trim((string) ($data['storage_key'] ?? '')),
            'checksum_sha256' => strtolower(trim((string) ($data['checksum_sha256'] ?? ''))),
            'file_size_bytes' => $this->requiredInt($data['file_size_bytes'] ?? null, 'file_size_bytes'),
            'width' => $this->nullableInt($data['width'] ?? null),
            'height' => $this->nullableInt($data['height'] ?? null),
            'uploaded_by_user_id' => $this->nullableInt($data['uploaded_by_user_id'] ?? null),
            'is_active' => $this->boolInt($data['is_active'] ?? true),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Logically archive an asset without deleting files.
     */
    public function archive(int|string $id, int $organizationId): bool
    {
        $statement = Database::pdo()->prepare(
            'UPDATE media_assets
             SET is_active = 0,
                archived_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => $id,
            'organization_id' => $organizationId,
        ]);

        return $statement->rowCount() > 0;
    }

    private function generateUniquePublicId(): string
    {
        for ($attempt = 0; $attempt < self::MAX_PUBLIC_ID_ATTEMPTS; $attempt++) {
            $publicId = $this->publicIdGenerator->generate('med');

            if ($this->findByPublicId($publicId) === null) {
                return $publicId;
            }
        }

        throw new RuntimeException('Could not generate unique media public id.');
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }

    private function boolInt(mixed $value): int
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : substr($text, 0, $maxLength);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }
}
