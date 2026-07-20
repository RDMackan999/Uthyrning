CREATE TABLE IF NOT EXISTS item_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NULL,
    organization_scope_key VARCHAR(64) NOT NULL DEFAULT 'global',
    slug VARCHAR(120) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_item_categories_scope_slug (organization_scope_key, slug),
    KEY idx_item_categories_organization_id (organization_id),
    KEY idx_item_categories_slug (slug),
    KEY idx_item_categories_is_active (is_active),
    KEY idx_item_categories_sort_order (sort_order),
    KEY idx_item_categories_deleted_at (deleted_at),
    KEY idx_item_categories_scope_active_sort (organization_scope_key, is_active, sort_order),
    CONSTRAINT fk_item_categories_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_category_relations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    item_category_id BIGINT UNSIGNED NOT NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_item_category_relations_item_category (rental_item_id, item_category_id),
    KEY idx_item_category_relations_rental_item_id (rental_item_id),
    KEY idx_item_category_relations_item_category_id (item_category_id),
    KEY idx_item_category_relations_is_primary (is_primary),
    CONSTRAINT fk_item_category_relations_item_categories
        FOREIGN KEY (item_category_id) REFERENCES item_categories (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
