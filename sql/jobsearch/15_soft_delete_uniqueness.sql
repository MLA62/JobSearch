-- JeMa Jobs 2.1.7: Gelöschte Datensätze blockieren keine Neuanlage.
-- Pro fachlichem Schlüssel bleibt höchstens ein aktiver Datensatz erlaubt.

ALTER TABLE users
    ADD COLUMN active_unique TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED AFTER deleted_at,
    DROP INDEX uq_users_email,
    ADD UNIQUE KEY uq_users_email (email, active_unique);

ALTER TABLE company_relationships
    ADD COLUMN active_unique TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED AFTER deleted_at,
    DROP INDEX uq_company_relationship,
    ADD UNIQUE KEY uq_company_relationship (owner_user_id, intermediary_company_id, client_company_id, relationship_type, active_unique);

ALTER TABLE job_platforms
    ADD COLUMN active_unique TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED AFTER deleted_at,
    DROP INDEX uq_job_platform_name,
    ADD UNIQUE KEY uq_job_platform_name (name, active_unique);

ALTER TABLE jobs
    ADD COLUMN active_unique TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED AFTER deleted_at,
    DROP INDEX uq_job_source_external,
    ADD UNIQUE KEY uq_job_source_external (source_id, external_id, active_unique);

ALTER TABLE applications
    ADD COLUMN active_unique TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED AFTER deleted_at,
    DROP INDEX uq_application_user_job,
    ADD UNIQUE KEY uq_application_user_job (user_id, job_id, active_unique);
