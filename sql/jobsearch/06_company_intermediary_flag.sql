ALTER TABLE companies
  ADD COLUMN is_intermediary TINYINT(1) NOT NULL DEFAULT 0 AFTER employee_count;

UPDATE companies c
JOIN (
  SELECT DISTINCT owner_user_id, intermediary_company_id
  FROM company_relationships
  WHERE deleted_at IS NULL
) cr ON cr.owner_user_id = c.owner_user_id AND cr.intermediary_company_id = c.id
SET c.is_intermediary = 1
WHERE c.deleted_at IS NULL;
