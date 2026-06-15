ALTER TABLE user_documents
    ADD COLUMN scope ENUM('profile','application') NOT NULL DEFAULT 'profile' AFTER language_code,
    ADD COLUMN application_id BIGINT UNSIGNED NULL AFTER scope,
    ADD COLUMN job_id BIGINT UNSIGNED NULL AFTER application_id,
    ADD KEY idx_user_documents_scope (user_id, scope, is_current),
    ADD KEY idx_user_documents_application (application_id, job_id);

CREATE TABLE user_language_skills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    language_code CHAR(5) NOT NULL,
    language_name VARCHAR(80) NOT NULL,
    cefr_level ENUM('A1','A2','B1','B2','C1','C2') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_language_skill (user_id, language_code),
    CONSTRAINT fk_user_language_skills_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
