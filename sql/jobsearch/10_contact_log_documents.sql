CREATE TABLE IF NOT EXISTS contact_log_documents (
    contact_log_id BIGINT UNSIGNED NOT NULL,
    user_document_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (contact_log_id, user_document_id),
    KEY idx_contact_log_documents_document (user_document_id),
    CONSTRAINT fk_contact_log_documents_log FOREIGN KEY (contact_log_id) REFERENCES contact_logs(id) ON DELETE CASCADE,
    CONSTRAINT fk_contact_log_documents_document FOREIGN KEY (user_document_id) REFERENCES user_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
