<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\MediaVariant;
use PDO;

/**
 * Repository for generated media variants.
 */
final class MediaVariantRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(MediaVariant::class);
    }

    /**
     * Find a non-deleted media variant by primary key.
     */
    public function findById(int|string $id): MediaVariant
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM media_variants WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Media variant not found.');
        }

        return new MediaVariant($row);
    }

    /**
     * Create one generated media variant record.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): MediaVariant
    {
        $statement = Database::pdo()->prepare(
            'INSERT INTO media_variants (
                media_asset_id,
                variant_key,
                mime_type,
                storage_disk_key,
                storage_key,
                file_size_bytes,
                width,
                height,
                created_at,
                updated_at
            ) VALUES (
                :media_asset_id,
                :variant_key,
                :mime_type,
                :storage_disk_key,
                :storage_key,
                :file_size_bytes,
                :width,
                :height,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'media_asset_id' => $this->requiredInt($data['media_asset_id'] ?? null, 'media_asset_id'),
            'variant_key' => $this->normalizeKey((string) ($data['variant_key'] ?? '')),
            'mime_type' => trim((string) ($data['mime_type'] ?? '')),
            'storage_disk_key' => $this->normalizeKey((string) ($data['storage_disk_key'] ?? 'local')),
            'storage_key' => trim((string) ($data['storage_key'] ?? '')),
            'file_size_bytes' => $this->requiredInt($data['file_size_bytes'] ?? null, 'file_size_bytes'),
            'width' => $this->requiredInt($data['width'] ?? null, 'width'),
            'height' => $this->requiredInt($data['height'] ?? null, 'height'),
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Find one active variant for authenticated admin delivery.
     *
     * @return array<string, mixed>|null
     */
    public function findAdminDeliverable(string $mediaPublicId, string $variantKey): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT media_variants.*,
                media_assets.organization_id,
                media_assets.public_id AS media_public_id
             FROM media_variants
             INNER JOIN media_assets
                ON media_assets.id = media_variants.media_asset_id
                AND media_assets.is_active = 1
                AND media_assets.archived_at IS NULL
                AND media_assets.deleted_at IS NULL
             WHERE media_assets.public_id = :public_id
                AND media_variants.variant_key = :variant_key
                AND media_variants.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($mediaPublicId),
            'variant_key' => $this->normalizeKey($variantKey),
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Find one active variant that belongs to at least one public rental item.
     *
     * @return array<string, mixed>|null
     */
    public function findPublicDeliverable(string $mediaPublicId, string $variantKey): ?array
    {
        $statement = Database::pdo()->prepare(
            'SELECT media_variants.*,
                media_assets.public_id AS media_public_id
             FROM media_variants
             INNER JOIN media_assets
                ON media_assets.id = media_variants.media_asset_id
                AND media_assets.is_active = 1
                AND media_assets.archived_at IS NULL
                AND media_assets.deleted_at IS NULL
             INNER JOIN item_media
                ON item_media.media_asset_id = media_assets.id
                AND item_media.is_active = 1
                AND item_media.deleted_at IS NULL
             INNER JOIN rental_items
                ON rental_items.id = item_media.rental_item_id
                AND rental_items.organization_id = item_media.organization_id
                AND rental_items.publication_status_key = :publication_status
                AND rental_items.is_active = 1
                AND rental_items.is_rentable = 1
                AND rental_items.deleted_at IS NULL
             INNER JOIN organizations
                ON organizations.id = rental_items.organization_id
                AND organizations.status_key = :organization_status
                AND organizations.deleted_at IS NULL
             INNER JOIN item_categories
                ON item_categories.id = rental_items.primary_category_id
                AND item_categories.is_active = 1
                AND item_categories.deleted_at IS NULL
                AND (
                    item_categories.organization_id IS NULL
                    OR item_categories.organization_id = rental_items.organization_id
                )
             INNER JOIN item_rates AS daily_rates
                ON daily_rates.rental_item_id = rental_items.id
                AND daily_rates.rate_type = :daily_rate_type
                AND daily_rates.is_active = 1
                AND daily_rates.deleted_at IS NULL
             WHERE media_assets.public_id = :public_id
                AND media_variants.variant_key = :variant_key
                AND media_variants.deleted_at IS NULL
             ORDER BY media_variants.id ASC
             LIMIT 1'
        );
        $statement->execute([
            'public_id' => trim($mediaPublicId),
            'variant_key' => $this->normalizeKey($variantKey),
            'publication_status' => 'published',
            'organization_status' => 'active',
            'daily_rate_type' => 'daily',
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }

    private function requiredInt(mixed $value, string $field): int
    {
        if ($value === null || $value === '') {
            throw new ModelException($field . ' is required.');
        }

        return (int) $value;
    }
}
