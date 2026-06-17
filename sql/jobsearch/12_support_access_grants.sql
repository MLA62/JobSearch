CREATE TABLE IF NOT EXISTS support_access_grants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    granted_by_user_id BIGINT UNSIGNED NOT NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by_user_id BIGINT UNSIGNED NULL,
    revoked_at DATETIME NULL,
    KEY idx_support_active (user_id, revoked_at, granted_at),
    CONSTRAINT fk_support_grants_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_grants_granted_by FOREIGN KEY (granted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_grants_revoked_by FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
