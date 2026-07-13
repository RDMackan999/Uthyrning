CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_name VARCHAR(150) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    subject_type VARCHAR(100) NULL,
    subject_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    context_json JSON NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_logs_event_name (event_name),
    KEY idx_audit_logs_actor_user_id (actor_user_id),
    KEY idx_audit_logs_subject_type_subject_id (subject_type, subject_id),
    KEY idx_audit_logs_occurred_at (occurred_at),
    CONSTRAINT fk_audit_logs_users
        FOREIGN KEY (actor_user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
