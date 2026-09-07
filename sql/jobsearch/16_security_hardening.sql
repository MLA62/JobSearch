ALTER TABLE users
    ADD COLUMN IF NOT EXISTS session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER locked_until;

CREATE TABLE IF NOT EXISTS auth_rate_limits (
    bucket_key CHAR(64) PRIMARY KEY,
    scope VARCHAR(32) NOT NULL,
    failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    locked_until DATETIME NULL,
    last_attempt_at DATETIME NOT NULL,
    KEY idx_auth_rate_limits_cleanup (last_attempt_at),
    KEY idx_auth_rate_limits_locked (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE auth_tokens
   SET consumed_at=NOW()
 WHERE token_type='password_reset'
   AND consumed_at IS NULL;

DELETE FROM auth_rate_limits WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
