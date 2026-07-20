CREATE TABLE IF NOT EXISTS rental_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    owning_company_id BIGINT UNSIGNED NULL,
    primary_category_id BIGINT UNSIGNED NOT NULL,
    slug VARCHAR(160) NOT NULL,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(120) NULL,
    description TEXT NULL,
    internal_note TEXT NULL,
    manufacturer VARCHAR(150) NULL,
    model VARCHAR(150) NULL,
    serial_number VARCHAR(150) NULL,
    inventory_number VARCHAR(150) NULL,
    status_key VARCHAR(50) NOT NULL DEFAULT 'draft',
    publication_status_key VARCHAR(50) NOT NULL DEFAULT 'draft',
    condition_grade_id BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_rentable TINYINT(1) NOT NULL DEFAULT 0,
    vat_rate DECIMAL(5,2) NULL,
    deposit_amount DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_rental_items_public_id (public_id),
    UNIQUE KEY uniq_rental_items_organization_slug (organization_id, slug),
    KEY idx_rental_items_organization_id (organization_id),
    KEY idx_rental_items_owning_company_id (owning_company_id),
    KEY idx_rental_items_primary_category_id (primary_category_id),
    KEY idx_rental_items_status_key (status_key),
    KEY idx_rental_items_publication_status_key (publication_status_key),
    KEY idx_rental_items_active_rentable (organization_id, is_active, is_rentable),
    KEY idx_rental_items_deleted_at (deleted_at),
    KEY idx_rental_items_inventory_number (inventory_number),
    CONSTRAINT fk_rental_items_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_rental_items_companies
        FOREIGN KEY (owning_company_id) REFERENCES companies (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_items_primary_categories
        FOREIGN KEY (primary_category_id) REFERENCES item_categories (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_rates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    rate_type VARCHAR(50) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SEK',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_item_rates_rental_item_id (rental_item_id),
    KEY idx_item_rates_type (rate_type),
    KEY idx_item_rates_active (rental_item_id, is_active),
    KEY idx_item_rates_deleted_at (deleted_at),
    CONSTRAINT fk_item_rates_rental_items
        FOREIGN KEY (rental_item_id) REFERENCES rental_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE item_category_relations
    ADD CONSTRAINT fk_item_category_relations_rental_items
        FOREIGN KEY (rental_item_id) REFERENCES rental_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;
