-- Extends contacts and contact activities into CRM-style relations.
-- Run once after 03_company_relationships_job_terms.sql.

ALTER TABLE contacts
  ADD COLUMN application_id BIGINT UNSIGNED NULL AFTER company_id,
  ADD KEY idx_contacts_application (application_id);

UPDATE contacts c
JOIN applications a
  ON a.user_id = c.owner_user_id
 AND a.job_id = c.job_id
 AND a.deleted_at IS NULL
SET c.application_id = a.id
WHERE c.application_id IS NULL;

ALTER TABLE contact_logs
  ADD COLUMN application_id BIGINT UNSIGNED NULL AFTER company_id,
  ADD COLUMN status ENUM('planned','open','done','cancelled') NOT NULL DEFAULT 'done' AFTER direction,
  ADD KEY idx_contact_logs_application_status (application_id, status);

UPDATE contact_logs l
JOIN applications a
  ON a.user_id = l.owner_user_id
 AND a.job_id = l.job_id
 AND a.deleted_at IS NULL
SET l.application_id = a.id
WHERE l.application_id IS NULL;

