ALTER TABLE jobs
    ADD COLUMN original_pdf_status ENUM('none','pending','rendered','failed') NOT NULL DEFAULT 'none' AFTER source_url,
    ADD COLUMN original_pdf_requested_at DATETIME NULL AFTER original_pdf_status,
    ADD COLUMN original_pdf_rendered_at DATETIME NULL AFTER original_pdf_requested_at,
    ADD COLUMN original_pdf_error TEXT NULL AFTER original_pdf_rendered_at,
    ADD KEY idx_jobs_original_pdf_status (owner_user_id, original_pdf_status);

UPDATE jobs j
SET
    original_pdf_status = CASE
        WHEN EXISTS (
            SELECT 1
            FROM user_documents d
            WHERE d.user_id = j.owner_user_id
              AND d.job_id = j.id
              AND d.title = 'Originale Stellenausschreibung'
              AND d.deleted_at IS NULL
        ) THEN 'rendered'
        WHEN j.source_url IS NOT NULL AND j.source_url <> '' THEN 'pending'
        ELSE 'none'
    END,
    original_pdf_requested_at = CASE
        WHEN j.source_url IS NOT NULL AND j.source_url <> '' THEN COALESCE(j.original_pdf_requested_at, j.created_at)
        ELSE NULL
    END,
    original_pdf_rendered_at = CASE
        WHEN EXISTS (
            SELECT 1
            FROM user_documents d
            WHERE d.user_id = j.owner_user_id
              AND d.job_id = j.id
              AND d.title = 'Originale Stellenausschreibung'
              AND d.deleted_at IS NULL
        ) THEN NOW()
        ELSE NULL
    END
WHERE j.deleted_at IS NULL;
