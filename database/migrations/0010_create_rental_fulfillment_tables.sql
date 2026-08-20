CREATE TABLE IF NOT EXISTS rental_fulfillments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NOT NULL,
    planned_start_date DATE NOT NULL,
    planned_end_date DATE NOT NULL,
    actual_handover_at DATETIME NULL,
    actual_return_at DATETIME NULL,
    handed_over_by_user_id BIGINT UNSIGNED NULL,
    returned_to_user_id BIGINT UNSIGNED NULL,
    received_by_name VARCHAR(255) NULL,
    handover_note TEXT NULL,
    return_note TEXT NULL,
    terms_version_key VARCHAR(100) NULL,
    deposit_required_amount DECIMAL(12,2) NULL,
    deposit_received_amount DECIMAL(12,2) NULL,
    deposit_returned_amount DECIMAL(12,2) NULL,
    deposit_retained_amount DECIMAL(12,2) NULL,
    deposit_status_key VARCHAR(50) NOT NULL DEFAULT 'not_required',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_rental_fulfillments_public_id (public_id),
    UNIQUE KEY uniq_rental_fulfillments_booking_id (booking_id),
    KEY idx_rental_fulfillments_organization_id (organization_id),
    KEY idx_rental_fulfillments_planned_dates (organization_id, planned_start_date, planned_end_date),
    KEY idx_rental_fulfillments_actual_handover_at (actual_handover_at),
    KEY idx_rental_fulfillments_actual_return_at (actual_return_at),
    KEY idx_rental_fulfillments_handed_over_by_user_id (handed_over_by_user_id),
    KEY idx_rental_fulfillments_returned_to_user_id (returned_to_user_id),
    KEY idx_rental_fulfillments_deposit_status_key (deposit_status_key),
    KEY idx_rental_fulfillments_deleted_at (deleted_at),
    CONSTRAINT fk_rental_fulfillments_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_rental_fulfillments_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_rental_fulfillments_handover_users
        FOREIGN KEY (handed_over_by_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_rental_fulfillments_return_users
        FOREIGN KEY (returned_to_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rental_fulfillment_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rental_fulfillment_id BIGINT UNSIGNED NOT NULL,
    booking_item_id BIGINT UNSIGNED NOT NULL,
    rental_item_id BIGINT UNSIGNED NOT NULL,
    item_public_id_snapshot VARCHAR(40) NOT NULL,
    item_name_snapshot VARCHAR(255) NOT NULL,
    handover_condition_key VARCHAR(50) NOT NULL,
    handover_condition_note TEXT NULL,
    return_condition_key VARCHAR(50) NULL,
    return_condition_note TEXT NULL,
    has_return_deviation TINYINT(1) NOT NULL DEFAULT 0,
    damage_note TEXT NULL,
    meter_value_handover DECIMAL(12,2) NULL,
    meter_value_return DECIMAL(12,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uniq_rental_fulfillment_items_booking_item (rental_fulfillment_id, booking_item_id),
    KEY idx_rental_fulfillment_items_fulfillment_id (rental_fulfillment_id),
    KEY idx_rental_fulfillment_items_booking_item_id (booking_item_id),
    KEY idx_rental_fulfillment_items_rental_item_id (rental_item_id),
    KEY idx_rental_fulfillment_items_handover_condition (handover_condition_key),
    KEY idx_rental_fulfillment_items_return_condition (return_condition_key),
    KEY idx_rental_fulfillment_items_return_deviation (has_return_deviation),
    KEY idx_rental_fulfillment_items_deleted_at (deleted_at),
    CONSTRAINT fk_rental_fulfillment_items_fulfillments
        FOREIGN KEY (rental_fulfillment_id) REFERENCES rental_fulfillments (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_rental_fulfillment_items_booking_items
        FOREIGN KEY (booking_item_id) REFERENCES booking_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_rental_fulfillment_items_rental_items
        FOREIGN KEY (rental_item_id) REFERENCES rental_items (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
