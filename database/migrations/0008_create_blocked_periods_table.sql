CREATE TABLE IF NOT EXISTS blocked_periods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason_code VARCHAR(50) NOT NULL DEFAULT 'manual',
    internal_note TEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_blocked_periods_organization_id (organization_id),
    KEY idx_blocked_periods_rental_item_id (rental_item_id),
    KEY idx_blocked_periods_created_by_user_id (created_by_user_id),
    KEY idx_blocked_periods_reason_code (reason_code),
    KEY idx_blocked_periods_item_dates (rental_item_id, start_date, end_date),
    KEY idx_blocked_periods_deleted_at (deleted_at),
    CONSTRAINT fk_blocked_periods_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_blocked_periods_rental_items
        FOREIGN KEY (rental_item_id) REFERENCES rental_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_blocked_periods_users
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
