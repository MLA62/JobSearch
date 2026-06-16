-- JeMa Jobs CRM - sharing, document text, translations, exports and privacy.
-- Apply after 07_job_original_pdf_status.sql.

CREATE TABLE IF NOT EXISTS guest_shares (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    title VARCHAR(190) NOT NULL,
    target_type ENUM('area','company','job','application','contact','document','report') NOT NULL,
    target_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(254) NOT NULL,
    permission ENUM('view','comment','edit') NOT NULL DEFAULT 'view',
    download_policy ENUM('none','original','pdf','both') NOT NULL DEFAULT 'none',
    watermark_enabled TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    last_accessed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guest_shares_token (token_hash),
    KEY idx_guest_shares_owner (owner_user_id, revoked_at, expires_at),
    CONSTRAINT fk_guest_shares_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_id BIGINT UNSIGNED NOT NULL,
    recipient_email VARCHAR(254) NOT NULL,
    device_hash CHAR(64) NOT NULL,
    verified_at DATETIME NULL,
    revoked_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guest_session_device (share_id, device_hash),
    KEY idx_guest_sessions_share (share_id, revoked_at),
    CONSTRAINT fk_guest_sessions_share FOREIGN KEY (share_id) REFERENCES guest_shares(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guest_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_id BIGINT UNSIGNED NOT NULL,
    guest_session_id BIGINT UNSIGNED NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_guest_comments_entity (entity_type, entity_id, created_at),
    CONSTRAINT fk_guest_comments_share FOREIGN KEY (share_id) REFERENCES guest_shares(id) ON DELETE CASCADE,
    CONSTRAINT fk_guest_comments_session FOREIGN KEY (guest_session_id) REFERENCES guest_sessions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_texts (
    user_document_id BIGINT UNSIGNED PRIMARY KEY,
    extraction_status ENUM('pending','ready','failed','skipped') NOT NULL DEFAULT 'pending',
    language_code CHAR(5) NULL,
    extracted_text LONGTEXT NULL,
    ocr_text LONGTEXT NULL,
    corrected_text LONGTEXT NULL,
    extracted_at DATETIME NULL,
    corrected_at DATETIME NULL,
    error_message TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FULLTEXT KEY ft_document_texts (extracted_text, ocr_text, corrected_text),
    CONSTRAINT fk_document_texts_document FOREIGN KEY (user_document_id) REFERENCES user_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS record_translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('company','job','contact','application','document') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    source_language CHAR(5) NULL,
    target_language CHAR(5) NOT NULL,
    title VARCHAR(255) NULL,
    body LONGTEXT NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_record_translations_entity (owner_user_id, entity_type, entity_id, target_language, is_current),
    CONSTRAINT fk_record_translations_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS storage_quotas (
    user_id BIGINT UNSIGNED PRIMARY KEY,
    quota_bytes BIGINT UNSIGNED NOT NULL DEFAULT 5368709120,
    warning_80_sent_at DATETIME NULL,
    warning_95_sent_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_storage_quotas_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cleanup_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    cutoff_date DATE NOT NULL,
    status ENUM('draft','requested','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'draft',
    preview_json LONGTEXT NULL,
    requested_at DATETIME NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_cleanup_preview_json CHECK (preview_json IS NULL OR JSON_VALID(preview_json)),
    CONSTRAINT fk_cleanup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cleanup_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS export_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    export_type ENUM('account_zip','audit_csv','audit_pdf','general_csv','general_xlsx','general_pdf') NOT NULL,
    status ENUM('queued','running','ready','failed','expired') NOT NULL DEFAULT 'queued',
    filter_json LONGTEXT NULL,
    generated_file_id BIGINT UNSIGNED NULL,
    expires_at DATETIME NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_export_filter_json CHECK (filter_json IS NULL OR JSON_VALID(filter_json)),
    CONSTRAINT fk_export_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_export_file FOREIGN KEY (generated_file_id) REFERENCES generated_files(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
