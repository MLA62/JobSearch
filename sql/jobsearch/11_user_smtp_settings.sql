CREATE TABLE IF NOT EXISTS user_smtp_settings (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    smtp_host VARCHAR(255) NOT NULL,
    smtp_port SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    smtp_encryption ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    smtp_username VARCHAR(255) NULL,
    smtp_password_encrypted LONGTEXT NULL,
    from_email VARCHAR(254) NOT NULL,
    from_name VARCHAR(190) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_smtp_active (is_active),
    CONSTRAINT fk_user_smtp_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
