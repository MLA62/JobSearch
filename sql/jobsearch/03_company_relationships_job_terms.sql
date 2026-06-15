CREATE TABLE IF NOT EXISTS company_relationships (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    intermediary_company_id BIGINT UNSIGNED NOT NULL,
    client_company_id BIGINT UNSIGNED NOT NULL,
    relationship_type ENUM('recruitment_agency','staffing_agency','payroll_provider','other') NOT NULL DEFAULT 'recruitment_agency',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_company_relationship (owner_user_id, intermediary_company_id, client_company_id, relationship_type),
    KEY idx_company_relationship_client (client_company_id),
    CONSTRAINT fk_company_relationship_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_relationship_intermediary FOREIGN KEY (intermediary_company_id) REFERENCES companies(id),
    CONSTRAINT fk_company_relationship_client FOREIGN KEY (client_company_id) REFERENCES companies(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE applications
    ADD COLUMN intermediary_company_id BIGINT UNSIGNED NULL AFTER job_id,
    ADD KEY idx_applications_intermediary (intermediary_company_id),
    ADD CONSTRAINT fk_applications_intermediary FOREIGN KEY (intermediary_company_id) REFERENCES companies(id) ON DELETE SET NULL;

ALTER TABLE jobs
    ADD COLUMN engagement_type ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent' AFTER employment_type,
    ADD COLUMN contract_term ENUM('open_ended','fixed_term','unknown') NOT NULL DEFAULT 'unknown' AFTER engagement_type,
    ADD COLUMN fixed_term_start DATE NULL AFTER contract_term,
    ADD COLUMN fixed_term_end DATE NULL AFTER fixed_term_start;
