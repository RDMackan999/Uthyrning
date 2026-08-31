CREATE TABLE IF NOT EXISTS media_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    media_type_key VARCHAR(50) NOT NULL DEFAULT 'image',
    mime_type VARCHAR(100) NOT NULL,
    original_filename VARCHAR(255) NULL,
    storage_disk_key VARCHAR(50) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    archived_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_media_assets_public_id (public_id),
    UNIQUE KEY uniq_media_assets_storage (storage_disk_key, storage_key),
    KEY idx_media_assets_organization_type_active (organization_id, media_type_key, is_active),
    KEY idx_media_assets_uploaded_by_user_id (uploaded_by_user_id),
    KEY idx_media_assets_checksum_sha256 (checksum_sha256),
    KEY idx_media_assets_archived_at (archived_at),
    KEY idx_media_assets_deleted_at (deleted_at),
    CONSTRAINT fk_media_assets_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_media_assets_users
        FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_asset_id BIGINT UNSIGNED NOT NULL,
    variant_key VARCHAR(50) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    storage_disk_key VARCHAR(50) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    file_size_bytes BIGINT UNSIGNED NOT NULL,
    width INT UNSIGNED NOT NULL,
    height INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_media_variants_asset_variant (media_asset_id, variant_key),
    UNIQUE KEY uniq_media_variants_storage (storage_disk_key, storage_key),
    KEY idx_media_variants_media_asset_id (media_asset_id),
    KEY idx_media_variants_variant_key (variant_key),
    KEY idx_media_variants_deleted_at (deleted_at),
    CONSTRAINT fk_media_variants_media_assets
        FOREIGN KEY (media_asset_id) REFERENCES media_assets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    media_asset_id BIGINT UNSIGNED NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_item_media_item_asset (rental_item_id, media_asset_id),
    KEY idx_item_media_organization_item (organization_id, rental_item_id),
    KEY idx_item_media_media_asset_id (media_asset_id),
    KEY idx_item_media_item_active_sort (rental_item_id, is_active, sort_order),
    KEY idx_item_media_item_primary (rental_item_id, is_primary, is_active, deleted_at),
    KEY idx_item_media_deleted_at (deleted_at),
    CONSTRAINT fk_item_media_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_item_media_rental_items
        FOREIGN KEY (rental_item_id) REFERENCES rental_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_item_media_media_assets
        FOREIGN KEY (media_asset_id) REFERENCES media_assets (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
