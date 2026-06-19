-- JeMa Jobs - DB-basierte UI-Uebersetzungen.
-- Apply after 13_online_application_fields.sql.
--
-- Diese Migration legt nur die Sprachstruktur an.
-- UI-Texte selbst sind produktive Daten und gehoeren nicht mehr in PHP-,
-- Markdown- oder SQL-Ressourcenkataloge.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ui_text_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text_key VARCHAR(190) NOT NULL,
    namespace VARCHAR(80) NOT NULL DEFAULT 'app',
    description VARCHAR(255) NULL,
    default_locale CHAR(5) NOT NULL DEFAULT 'de-CH',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ui_text_keys_key (text_key),
    KEY idx_ui_text_keys_namespace (namespace, is_active),
    CONSTRAINT fk_ui_text_keys_default_locale FOREIGN KEY (default_locale) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ui_text_translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    text_key_id BIGINT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    text_value LONGTEXT NOT NULL,
    status ENUM('draft','review','approved','archived') NOT NULL DEFAULT 'approved',
    updated_by BIGINT UNSIGNED NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ui_text_translation (text_key_id, locale),
    KEY idx_ui_text_translations_locale (locale, status),
    FULLTEXT KEY ft_ui_text_translations_value (text_value),
    CONSTRAINT fk_ui_text_translations_key FOREIGN KEY (text_key_id) REFERENCES ui_text_keys(id) ON DELETE CASCADE,
    CONSTRAINT fk_ui_text_translations_locale FOREIGN KEY (locale) REFERENCES languages(code),
    CONSTRAINT fk_ui_text_translations_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_ui_text_translations_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ui_text_cache_versions (
    locale CHAR(5) PRIMARY KEY,
    version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ui_text_cache_versions_locale FOREIGN KEY (locale) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ui_text_cache_versions (locale, version)
SELECT code, 1 FROM languages WHERE is_active = 1;
