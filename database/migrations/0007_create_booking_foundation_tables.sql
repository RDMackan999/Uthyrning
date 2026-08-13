CREATE TABLE IF NOT EXISTS booking_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    status_key VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_blocking TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_booking_statuses_status_key (status_key),
    KEY idx_booking_statuses_is_active (is_active),
    KEY idx_booking_statuses_is_blocking (is_blocking),
    KEY idx_booking_statuses_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NULL,
    status_key VARCHAR(50) NOT NULL DEFAULT 'request',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    customer_comment TEXT NULL,
    internal_note TEXT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SEK',
    total_units INT UNSIGNED NOT NULL DEFAULT 0,
    subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    deposit_amount DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_bookings_public_id (public_id),
    KEY idx_bookings_organization_id (organization_id),
    KEY idx_bookings_customer_id (customer_id),
    KEY idx_bookings_company_id (company_id),
    KEY idx_bookings_status_key (status_key),
    KEY idx_bookings_organization_status_dates (organization_id, status_key, start_date, end_date),
    KEY idx_bookings_deleted_at (deleted_at),
    CONSTRAINT fk_bookings_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_customers
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_companies
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_bookings_booking_statuses
        FOREIGN KEY (status_key) REFERENCES booking_statuses (status_key)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    rate_type VARCHAR(50) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SEK',
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    number_of_units INT UNSIGNED NOT NULL,
    subtotal_amount DECIMAL(12,2) NOT NULL,
    deposit_amount DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_booking_items_booking_id (booking_id),
    KEY idx_booking_items_rental_item_id (rental_item_id),
    KEY idx_booking_items_item_dates (rental_item_id, start_date, end_date),
    CONSTRAINT fk_booking_items_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_items_rental_items
        FOREIGN KEY (rental_item_id) REFERENCES rental_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_customer_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    company_id BIGINT UNSIGNED NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_email_normalized VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    company_name VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_customer_snapshots_booking_id (booking_id),
    KEY idx_booking_customer_snapshots_customer_id (customer_id),
    KEY idx_booking_customer_snapshots_company_id (company_id),
    KEY idx_booking_customer_snapshots_email_normalized (customer_email_normalized),
    CONSTRAINT fk_booking_customer_snapshots_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_customer_snapshots_customers
        FOREIGN KEY (customer_id) REFERENCES customers (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_customer_snapshots_companies
        FOREIGN KEY (company_id) REFERENCES companies (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_price_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    booking_item_id BIGINT UNSIGNED NULL,
    rate_type VARCHAR(50) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'SEK',
    number_of_units INT UNSIGNED NOT NULL,
    subtotal_amount DECIMAL(12,2) NOT NULL,
    deposit_amount DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_booking_price_snapshots_booking_id (booking_id),
    KEY idx_booking_price_snapshots_booking_item_id (booking_item_id),
    CONSTRAINT fk_booking_price_snapshots_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_price_snapshots_booking_items
        FOREIGN KEY (booking_item_id) REFERENCES booking_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    from_status_key VARCHAR(50) NULL,
    to_status_key VARCHAR(50) NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    comment TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_booking_status_history_booking_id (booking_id),
    KEY idx_booking_status_history_to_status_key (to_status_key),
    KEY idx_booking_status_history_changed_by_user_id (changed_by_user_id),
    CONSTRAINT fk_booking_status_history_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_status_history_from_statuses
        FOREIGN KEY (from_status_key) REFERENCES booking_statuses (status_key)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_status_history_to_statuses
        FOREIGN KEY (to_status_key) REFERENCES booking_statuses (status_key)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_status_history_users
        FOREIGN KEY (changed_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_notes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    note_type VARCHAR(50) NOT NULL DEFAULT 'internal',
    body TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 1,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_booking_notes_booking_id (booking_id),
    KEY idx_booking_notes_created_by_user_id (created_by_user_id),
    KEY idx_booking_notes_deleted_at (deleted_at),
    CONSTRAINT fk_booking_notes_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_booking_notes_users
        FOREIGN KEY (created_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
