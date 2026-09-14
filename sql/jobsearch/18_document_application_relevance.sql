ALTER TABLE user_documents
    ADD COLUMN is_application_relevant TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current;
