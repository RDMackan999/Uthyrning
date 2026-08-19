CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    public_id VARCHAR(40) NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    booking_id BIGINT UNSIGNED NULL,
    event_key VARCHAR(100) NOT NULL,
    channel_key VARCHAR(50) NOT NULL DEFAULT 'email',
    recipient_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_email_normalized VARCHAR(255) NOT NULL,
    template_key VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status_key VARCHAR(50) NOT NULL DEFAULT 'pending',
    idempotency_key VARCHAR(255) NOT NULL,
    attempts_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    last_error_code VARCHAR(100) NULL,
    last_error_summary VARCHAR(500) NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    failed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notifications_public_id (public_id),
    UNIQUE KEY uniq_notifications_idempotency_key (idempotency_key),
    KEY idx_notifications_organization_id (organization_id),
    KEY idx_notifications_booking_event (booking_id, event_key),
    KEY idx_notifications_status_scheduled (organization_id, status_key, scheduled_at),
    KEY idx_notifications_recipient_email_normalized (recipient_email_normalized),
    CONSTRAINT fk_notifications_organizations
        FOREIGN KEY (organization_id) REFERENCES organizations (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_notifications_bookings
        FOREIGN KEY (booking_id) REFERENCES bookings (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    notification_id BIGINT UNSIGNED NOT NULL,
    attempt_number INT UNSIGNED NOT NULL,
    transport_key VARCHAR(50) NOT NULL,
    status_key VARCHAR(50) NOT NULL,
    error_code VARCHAR(100) NULL,
    error_summary VARCHAR(500) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_notification_attempts_notification_attempt (notification_id, attempt_number),
    KEY idx_notification_attempts_notification_id (notification_id),
    KEY idx_notification_attempts_status_key (status_key),
    CONSTRAINT fk_notification_attempts_notifications
        FOREIGN KEY (notification_id) REFERENCES notifications (id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
