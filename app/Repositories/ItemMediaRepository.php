<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\BaseRepository;
use App\Core\Collection;
use App\Core\Database;
use App\Core\ModelException;
use App\Models\ItemMedia;
use PDO;

/**
 * Repository for rental item media relations.
 */
final class ItemMediaRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(ItemMedia::class);
    }

    /**
     * Find a non-deleted item media relation by primary key.
     */
    public function findById(int|string $id): ItemMedia
    {
        $statement = Database::pdo()->prepare(
            'SELECT * FROM item_media WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Item media relation not found.');
        }

        return new ItemMedia($row);
    }

    /**
     * Find active media relations for one rental item.
     *
     * @return Collection<ItemMedia>
     */
    public function findForItem(int $organizationId, int $rentalItemId): Collection
    {
        $statement = Database::pdo()->prepare(
            'SELECT item_media.*,
                media_assets.public_id AS media_public_id,
                media_assets.original_filename,
                media_assets.mime_type,
                media_assets.file_size_bytes,
                media_assets.width,
                media_assets.height
             FROM item_media
             INNER JOIN media_assets
                ON media_assets.id = item_media.media_asset_id
                AND media_assets.organization_id = item_media.organization_id
                AND media_assets.is_active = 1
                AND media_assets.archived_at IS NULL
                AND media_assets.deleted_at IS NULL
             WHERE item_media.organization_id = :organization_id
                AND item_media.rental_item_id = :rental_item_id
                AND item_media.is_active = 1
                AND item_media.deleted_at IS NULL
             ORDER BY item_media.is_primary DESC, item_media.sort_order ASC, item_media.id ASC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return $this->relationsFromRows($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Return public image references for one public rental item URL.
     *
     * @return list<array{public_id: string, variant: string, url: string, is_primary: bool}>
     */
    public function findPublicImagesForItemRoute(string $itemPublicId, string $slug, string $variantKey = 'detail'): array
    {
        $statement = Database::pdo()->prepare(
            'SELECT media_assets.public_id,
                item_media.is_primary
             FROM item_media
             INNER JOIN media_assets
                ON media_assets.id = item_media.media_asset_id
                AND media_assets.organization_id = item_media.organization_id
                AND media_assets.is_active = 1
                AND media_assets.archived_at IS NULL
                AND media_assets.deleted_at IS NULL
             INNER JOIN media_variants
                ON media_variants.media_asset_id = media_assets.id
                AND media_variants.variant_key = :variant_key
                AND media_variants.deleted_at IS NULL
             INNER JOIN rental_items
                ON rental_items.id = item_media.rental_item_id
                AND rental_items.organization_id = item_media.organization_id
                AND rental_items.public_id = :item_public_id
                AND rental_items.slug = :slug
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
             WHERE item_media.is_active = 1
                AND item_media.deleted_at IS NULL
             ORDER BY item_media.is_primary DESC, item_media.sort_order ASC, item_media.id ASC'
        );
        $statement->execute([
            'variant_key' => $this->normalizeKey($variantKey),
            'item_public_id' => trim($itemPublicId),
            'slug' => strtolower(trim($slug)),
            'publication_status' => 'published',
            'organization_status' => 'active',
            'daily_rate_type' => 'daily',
        ]);

        $images = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mediaPublicId = (string) ($row['public_id'] ?? '');

            if ($mediaPublicId === '') {
                continue;
            }

            $images[] = [
                'public_id' => $mediaPublicId,
                'variant' => $this->normalizeKey($variantKey),
                'url' => '/media/' . rawurlencode($mediaPublicId) . '/' . rawurlencode($this->normalizeKey($variantKey)),
                'is_primary' => (bool) ((int) ($row['is_primary'] ?? 0)),
            ];
        }

        return $images;
    }

    /**
     * Link a media asset to one rental item.
     */
    public function link(int $organizationId, int $rentalItemId, int $mediaAssetId, bool $isPrimary = false): ItemMedia
    {
        $this->ensureRentalItemBelongsToOrganization($rentalItemId, $organizationId);
        $this->ensureMediaAssetBelongsToOrganization($mediaAssetId, $organizationId);

        if ($isPrimary) {
            $this->clearPrimary($organizationId, $rentalItemId);
        }

        $statement = Database::pdo()->prepare(
            'INSERT INTO item_media (
                organization_id,
                rental_item_id,
                media_asset_id,
                sort_order,
                is_primary,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :organization_id,
                :rental_item_id,
                :media_asset_id,
                :sort_order,
                :is_primary,
                1,
                UTC_TIMESTAMP(),
                UTC_TIMESTAMP()
            )'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
            'media_asset_id' => $mediaAssetId,
            'sort_order' => $this->nextSortOrder($organizationId, $rentalItemId),
            'is_primary' => $isPrimary ? 1 : 0,
        ]);

        return $this->findById((int) Database::pdo()->lastInsertId());
    }

    /**
     * Set one active relation as primary and clear previous primary for the same item.
     */
    public function setPrimaryByMediaPublicId(int $organizationId, int $rentalItemId, string $mediaPublicId): ItemMedia
    {
        $relation = $this->findActiveRelationByMediaPublicId($organizationId, $rentalItemId, $mediaPublicId);
        $this->clearPrimary($organizationId, $rentalItemId);

        $statement = Database::pdo()->prepare(
            'UPDATE item_media
             SET is_primary = 1,
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => (int) ($relation->toArray()['id'] ?? 0),
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return $this->findById((int) ($relation->toArray()['id'] ?? 0));
    }

    /**
     * Update safe sort order values for existing active item media relations.
     *
     * @param array<string, mixed> $sortOrdersByPublicId
     */
    public function updateSortOrder(int $organizationId, int $rentalItemId, array $sortOrdersByPublicId): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE item_media
             INNER JOIN media_assets
                ON media_assets.id = item_media.media_asset_id
                AND media_assets.organization_id = item_media.organization_id
             SET item_media.sort_order = :sort_order,
                item_media.updated_at = UTC_TIMESTAMP()
             WHERE item_media.organization_id = :organization_id
                AND item_media.rental_item_id = :rental_item_id
                AND media_assets.public_id = :media_public_id
                AND item_media.is_active = 1
                AND item_media.deleted_at IS NULL
                AND media_assets.deleted_at IS NULL'
        );

        foreach ($sortOrdersByPublicId as $mediaPublicId => $sortOrder) {
            if (!is_string($mediaPublicId) || trim($mediaPublicId) === '') {
                continue;
            }

            $statement->execute([
                'sort_order' => max(0, min(10000, (int) $sortOrder)),
                'organization_id' => $organizationId,
                'rental_item_id' => $rentalItemId,
                'media_public_id' => trim($mediaPublicId),
            ]);
        }
    }

    /**
     * Logically archive an item-media relation without deleting the physical file.
     */
    public function archiveByMediaPublicId(int $organizationId, int $rentalItemId, string $mediaPublicId): bool
    {
        $relation = $this->findActiveRelationByMediaPublicId($organizationId, $rentalItemId, $mediaPublicId);

        $statement = Database::pdo()->prepare(
            'UPDATE item_media
             SET is_active = 0,
                is_primary = 0,
                deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
             WHERE id = :id
                AND organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'id' => (int) ($relation->toArray()['id'] ?? 0),
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return $statement->rowCount() > 0;
    }

    /**
     * Find an active relation by media public id within one item scope.
     */
    public function findActiveRelationByMediaPublicId(
        int $organizationId,
        int $rentalItemId,
        string $mediaPublicId
    ): ItemMedia {
        $statement = Database::pdo()->prepare(
            'SELECT item_media.*,
                media_assets.public_id AS media_public_id
             FROM item_media
             INNER JOIN media_assets
                ON media_assets.id = item_media.media_asset_id
                AND media_assets.organization_id = item_media.organization_id
                AND media_assets.public_id = :media_public_id
                AND media_assets.is_active = 1
                AND media_assets.archived_at IS NULL
                AND media_assets.deleted_at IS NULL
             WHERE item_media.organization_id = :organization_id
                AND item_media.rental_item_id = :rental_item_id
                AND item_media.is_active = 1
                AND item_media.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'media_public_id' => trim($mediaPublicId),
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ModelException('Item media relation not found.');
        }

        return new ItemMedia($row);
    }

    /**
     * Check whether one item currently has active media.
     */
    public function hasActiveMedia(int $organizationId, int $rentalItemId): bool
    {
        $statement = Database::pdo()->prepare(
            'SELECT COUNT(*)
             FROM item_media
             WHERE organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND is_active = 1
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function clearPrimary(int $organizationId, int $rentalItemId): void
    {
        $statement = Database::pdo()->prepare(
            'UPDATE item_media
             SET is_primary = 0,
                updated_at = UTC_TIMESTAMP()
             WHERE organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND is_primary = 1
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);
    }

    private function nextSortOrder(int $organizationId, int $rentalItemId): int
    {
        $statement = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(sort_order), -1) + 1
             FROM item_media
             WHERE organization_id = :organization_id
                AND rental_item_id = :rental_item_id
                AND deleted_at IS NULL'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'rental_item_id' => $rentalItemId,
        ]);

        return (int) $statement->fetchColumn();
    }

    private function ensureRentalItemBelongsToOrganization(int $rentalItemId, int $organizationId): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT id
             FROM rental_items
             WHERE id = :rental_item_id
                AND organization_id = :organization_id
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'rental_item_id' => $rentalItemId,
            'organization_id' => $organizationId,
        ]);

        if ($statement->fetchColumn() === false) {
            throw new ModelException('Rental item is not available for this organization.');
        }
    }

    private function ensureMediaAssetBelongsToOrganization(int $mediaAssetId, int $organizationId): void
    {
        $statement = Database::pdo()->prepare(
            'SELECT id
             FROM media_assets
             WHERE id = :media_asset_id
                AND organization_id = :organization_id
                AND is_active = 1
                AND archived_at IS NULL
                AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'media_asset_id' => $mediaAssetId,
            'organization_id' => $organizationId,
        ]);

        if ($statement->fetchColumn() === false) {
            throw new ModelException('Media asset is not available for this organization.');
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return Collection<ItemMedia>
     */
    private function relationsFromRows(array $rows): Collection
    {
        return new Collection(array_map(
            static fn (array $row): ItemMedia => new ItemMedia($row),
            $rows
        ));
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(trim($key));
    }
}
