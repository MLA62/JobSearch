-- Read-oriented CRM/reporting views. Application queries must still filter by
-- owner_user_id/user_id to enforce tenant isolation.

CREATE OR REPLACE VIEW v_job_pipeline AS
SELECT
    j.owner_user_id,
    j.id AS job_id,
    j.title AS job_title,
    j.status AS job_status,
    j.match_score,
    j.location_text,
    j.published_at,
    j.expires_at,
    c.id AS company_id,
    c.name AS company_name,
    a.id AS application_id,
    a.status AS application_status,
    a.applied_at,
    a.next_action,
    a.next_action_at
FROM jobs j
JOIN companies c ON c.id = j.company_id
LEFT JOIN applications a ON a.job_id = j.id AND a.deleted_at IS NULL
WHERE j.deleted_at IS NULL AND c.deleted_at IS NULL;

CREATE OR REPLACE VIEW v_application_overview AS
SELECT
    a.user_id,
    a.id AS application_id,
    a.status,
    a.applied_at,
    a.channel,
    a.reference_number,
    a.next_action,
    a.next_action_at,
    j.id AS job_id,
    j.title AS job_title,
    j.location_text,
    c.id AS company_id,
    c.name AS company_name,
    CONCAT_WS(' ', ct.first_name, ct.last_name) AS contact_name,
    ct.email AS contact_email
FROM applications a
JOIN jobs j ON j.id = a.job_id
JOIN companies c ON c.id = j.company_id
LEFT JOIN contacts ct ON ct.id = a.primary_contact_id
WHERE a.deleted_at IS NULL AND j.deleted_at IS NULL AND c.deleted_at IS NULL;

CREATE OR REPLACE VIEW v_contact_activity AS
SELECT
    l.owner_user_id,
    l.id AS log_id,
    l.occurred_at,
    l.follow_up_at,
    l.channel,
    l.direction,
    l.subject,
    l.outcome,
    CONCAT_WS(' ', ct.first_name, ct.last_name) AS contact_name,
    c.name AS company_name,
    j.title AS job_title
FROM contact_logs l
JOIN contacts ct ON ct.id = l.contact_id
JOIN companies c ON c.id = l.company_id
LEFT JOIN jobs j ON j.id = l.job_id;

CREATE OR REPLACE VIEW v_calendar_items AS
SELECT
    ce.owner_user_id,
    ce.id,
    ce.title,
    ce.event_type,
    ce.starts_at,
    ce.ends_at,
    ce.all_day,
    ce.status,
    ce.location,
    ce.application_id,
    ce.contact_id
FROM calendar_events ce
UNION ALL
SELECT
    a.user_id,
    -a.id,
    CONCAT('Next action: ', COALESCE(a.next_action, j.title)),
    'follow_up',
    a.next_action_at,
    NULL,
    0,
    'planned',
    j.location_text,
    a.id,
    a.primary_contact_id
FROM applications a
JOIN jobs j ON j.id = a.job_id
WHERE a.next_action_at IS NOT NULL AND a.deleted_at IS NULL;

