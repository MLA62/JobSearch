-- JeMa Jobs CRM - MariaDB 10.6 schema
-- Import after selecting database kerubina_JeMaJobs in phpMyAdmin.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE languages (
    code CHAR(5) PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    native_name VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (code, name, native_name, sort_order) VALUES
('de', 'German', 'Deutsch', 10),
('en', 'English', 'English', 20),
('es', 'Spanish', 'Español', 30),
('pt', 'Portuguese', 'Português', 40);

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('invited','active','locked','disabled') NOT NULL DEFAULT 'invited',
    preferred_language CHAR(5) NOT NULL DEFAULT 'de',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Zurich',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NULL,
    mobile VARCHAR(50) NULL,
    date_of_birth DATE NULL,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    postal_code VARCHAR(30) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    country_code CHAR(2) NULL,
    profile_photo_path VARCHAR(500) NULL,
    last_login_at DATETIME NULL,
    email_verified_at DATETIME NULL,
    failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_name (last_name, first_name),
    CONSTRAINT fk_users_language FOREIGN KEY (preferred_language) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE roles (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (code, name) VALUES
('admin', 'Administrator'), ('manager', 'Manager'), ('user', 'User'), ('viewer', 'Viewer');

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE two_factor_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    method ENUM('email','totp','webauthn') NOT NULL,
    label VARCHAR(120) NULL,
    secret_encrypted TEXT NULL,
    credential_id VARBINARY(255) NULL,
    public_key TEXT NULL,
    sign_count BIGINT UNSIGNED NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_2fa_credential (credential_id),
    KEY idx_2fa_user (user_id, method),
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_type ENUM('email_verify','password_reset','two_factor','remember_me') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_token_hash (token_hash),
    KEY idx_auth_tokens_user_type (user_id, token_type, expires_at),
    CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_preferences (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL DEFAULT 'Default',
    desired_roles TEXT NULL,
    desired_locations TEXT NULL,
    remote_preference ENUM('onsite','hybrid','remote','any') NOT NULL DEFAULT 'any',
    employment_types SET('full_time','part_time','temporary','contract','internship','freelance') NULL,
    workload_min TINYINT UNSIGNED NULL,
    workload_max TINYINT UNSIGNED NULL,
    salary_min DECIMAL(12,2) NULL,
    salary_max DECIMAL(12,2) NULL,
    salary_currency CHAR(3) NOT NULL DEFAULT 'CHF',
    salary_period ENUM('hour','month','year') NOT NULL DEFAULT 'year',
    desired_level VARCHAR(100) NULL,
    desired_benefits TEXT NULL,
    excluded_industries TEXT NULL,
    willing_to_relocate TINYINT(1) NOT NULL DEFAULT 0,
    travel_percentage TINYINT UNSIGNED NULL,
    available_from DATE NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_preference_workload CHECK (workload_min IS NULL OR workload_min <= 100),
    CONSTRAINT chk_preference_workload_max CHECK (workload_max IS NULL OR workload_max <= 100),
    CONSTRAINT fk_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_types (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name_key VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_document_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_types (code, name_key) VALUES
('cv','document.cv'), ('certificate','document.certificate'),
('reference_letter','document.reference_letter'), ('diploma','document.diploma'),
('cover_letter','document.cover_letter'), ('portfolio','document.portfolio'), ('other','document.other');

CREATE TABLE user_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    document_type_id SMALLINT UNSIGNED NOT NULL,
    language_code CHAR(5) NULL,
    scope ENUM('profile','application') NOT NULL DEFAULT 'profile',
    application_id BIGINT UNSIGNED NULL,
    job_id BIGINT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    valid_from DATE NULL,
    valid_until DATE NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_user_documents_user_type (user_id, document_type_id, is_current),
    KEY idx_user_documents_scope (user_id, scope, is_current),
    KEY idx_user_documents_application (application_id, job_id),
    CONSTRAINT fk_user_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_documents_type FOREIGN KEY (document_type_id) REFERENCES document_types(id),
    CONSTRAINT fk_user_documents_language FOREIGN KEY (language_code) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    legal_name VARCHAR(255) NULL,
    website VARCHAR(500) NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(50) NULL,
    industry VARCHAR(150) NULL,
    employee_count VARCHAR(50) NULL,
    is_intermediary TINYINT(1) NOT NULL DEFAULT 0,
    address_line1 VARCHAR(190) NULL,
    address_line2 VARCHAR(190) NULL,
    postal_code VARCHAR(30) NULL,
    city VARCHAR(120) NULL,
    region VARCHAR(120) NULL,
    country_code CHAR(2) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    rating TINYINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_companies_owner_name (owner_user_id, name),
    CONSTRAINT chk_company_rating CHECK (rating IS NULL OR rating BETWEEN 1 AND 5),
    CONSTRAINT fk_companies_owner FOREIGN KEY (owner_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_members (
    company_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    access_level ENUM('owner','edit','view') NOT NULL DEFAULT 'view',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (company_id, user_id),
    CONSTRAINT fk_company_members_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_relationships (
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

CREATE TABLE job_sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    source_type ENUM('manual','rss','api','web_import','email') NOT NULL DEFAULT 'manual',
    base_url VARCHAR(500) NULL,
    configuration_encrypted LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_run_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_job_sources_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    source_id BIGINT UNSIGNED NULL,
    external_id VARCHAR(190) NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NULL,
    requirements LONGTEXT NULL,
    benefits LONGTEXT NULL,
    employment_type ENUM('full_time','part_time','temporary','contract','internship','freelance','other') NULL,
    engagement_type ENUM('permanent','temporary') NOT NULL DEFAULT 'permanent',
    contract_term ENUM('open_ended','fixed_term','unknown') NOT NULL DEFAULT 'unknown',
    fixed_term_start DATE NULL,
    fixed_term_end DATE NULL,
    workplace_type ENUM('onsite','hybrid','remote','unknown') NOT NULL DEFAULT 'unknown',
    workload_min TINYINT UNSIGNED NULL,
    workload_max TINYINT UNSIGNED NULL,
    salary_min DECIMAL(12,2) NULL,
    salary_max DECIMAL(12,2) NULL,
    salary_currency CHAR(3) NULL,
    salary_period ENUM('hour','month','year') NULL,
    location_text VARCHAR(255) NULL,
    country_code CHAR(2) NULL,
    source_url VARCHAR(1000) NULL,
    original_pdf_status ENUM('none','pending','rendered','failed') NOT NULL DEFAULT 'none',
    original_pdf_requested_at DATETIME NULL,
    original_pdf_rendered_at DATETIME NULL,
    original_pdf_error TEXT NULL,
    published_at DATETIME NULL,
    expires_at DATETIME NULL,
    status ENUM('draft','open','interesting','applied','interview','offer','rejected','closed','archived') NOT NULL DEFAULT 'open',
    match_score DECIMAL(5,2) NULL,
    raw_import_data LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_job_source_external (source_id, external_id),
    KEY idx_jobs_owner_status (owner_user_id, status),
    KEY idx_jobs_original_pdf_status (owner_user_id, original_pdf_status),
    KEY idx_jobs_company (company_id),
    KEY idx_jobs_dates (published_at, expires_at),
    FULLTEXT KEY ftx_jobs_search (title, description, requirements),
    CONSTRAINT chk_job_score CHECK (match_score IS NULL OR match_score BETWEEN 0 AND 100),
    CONSTRAINT fk_jobs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_jobs_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_jobs_source FOREIGN KEY (source_id) REFERENCES job_sources(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    job_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    position VARCHAR(150) NULL,
    department VARCHAR(150) NULL,
    email VARCHAR(254) NULL,
    phone VARCHAR(50) NULL,
    mobile VARCHAR(50) NULL,
    linkedin_url VARCHAR(500) NULL,
    preferred_language CHAR(5) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_contacts_company_name (company_id, last_name, first_name),
    KEY idx_contacts_application (application_id),
    KEY idx_contacts_job (job_id),
    CONSTRAINT fk_contacts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_contacts_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_contacts_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    CONSTRAINT fk_contacts_language FOREIGN KEY (preferred_language) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    contact_id BIGINT UNSIGNED NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    job_id BIGINT UNSIGNED NULL,
    channel ENUM('email','external_email','onsite','phone','meeting','video','whatsapp','sms','message','letter','note','other') NOT NULL,
    direction ENUM('incoming','outgoing','internal') NOT NULL DEFAULT 'internal',
    status ENUM('planned','open','done','cancelled') NOT NULL DEFAULT 'done',
    subject VARCHAR(255) NULL,
    body LONGTEXT NULL,
    occurred_at DATETIME NOT NULL,
    follow_up_at DATETIME NULL,
    outcome VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_contact_logs_contact_date (contact_id, occurred_at),
    KEY idx_contact_logs_application_status (application_id, status),
    KEY idx_contact_logs_followup (owner_user_id, follow_up_at),
    CONSTRAINT fk_contact_logs_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_contact_logs_contact FOREIGN KEY (contact_id) REFERENCES contacts(id),
    CONSTRAINT fk_contact_logs_company FOREIGN KEY (company_id) REFERENCES companies(id),
    CONSTRAINT fk_contact_logs_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_log_documents (
    contact_log_id BIGINT UNSIGNED NOT NULL,
    user_document_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (contact_log_id, user_document_id),
    KEY idx_contact_log_documents_document (user_document_id),
    CONSTRAINT fk_contact_log_documents_log FOREIGN KEY (contact_log_id) REFERENCES contact_logs(id) ON DELETE CASCADE,
    CONSTRAINT fk_contact_log_documents_document FOREIGN KEY (user_document_id) REFERENCES user_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    intermediary_company_id BIGINT UNSIGNED NULL,
    primary_contact_id BIGINT UNSIGNED NULL,
    status ENUM('draft','ready','sent','confirmed','interview','assessment','offer','accepted','rejected','withdrawn','closed') NOT NULL DEFAULT 'draft',
    applied_at DATETIME NULL,
    channel ENUM('email','portal','website','mail','referral','other') NULL,
    reference_number VARCHAR(120) NULL,
    cover_letter_text LONGTEXT NULL,
    email_subject VARCHAR(255) NULL,
    email_body LONGTEXT NULL,
    salary_expectation DECIMAL(12,2) NULL,
    salary_currency CHAR(3) NULL,
    next_action VARCHAR(255) NULL,
    next_action_at DATETIME NULL,
    notes LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_application_user_job (user_id, job_id),
    KEY idx_applications_status_date (user_id, status, applied_at),
    KEY idx_applications_next_action (user_id, next_action_at),
    KEY idx_applications_intermediary (intermediary_company_id),
    CONSTRAINT fk_applications_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_applications_job FOREIGN KEY (job_id) REFERENCES jobs(id),
    CONSTRAINT fk_applications_intermediary FOREIGN KEY (intermediary_company_id) REFERENCES companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_applications_contact FOREIGN KEY (primary_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE application_documents (
    application_id BIGINT UNSIGNED NOT NULL,
    user_document_id BIGINT UNSIGNED NOT NULL,
    purpose ENUM('cv','cover_letter','certificate','reference','portfolio','other') NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (application_id, user_document_id),
    CONSTRAINT fk_application_documents_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_documents_document FOREIGN KEY (user_document_id) REFERENCES user_documents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE application_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    old_status VARCHAR(40) NULL,
    new_status VARCHAR(40) NOT NULL,
    comment TEXT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_application_history (application_id, changed_at),
    CONSTRAINT fk_application_history_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_application_history_user FOREIGN KEY (changed_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    color CHAR(7) NULL,
    UNIQUE KEY uq_tags_owner_name (owner_user_id, name),
    CONSTRAINT fk_tags_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entity_tags (
    tag_id BIGINT UNSIGNED NOT NULL,
    entity_type ENUM('company','job','contact','application','document') NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (tag_id, entity_type, entity_id),
    KEY idx_entity_tags_entity (entity_type, entity_id),
    CONSTRAINT fk_entity_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    base_entity ENUM('companies','jobs','contacts','contact_logs','applications','documents','calendar') NOT NULL,
    display_type ENUM('table','list','cards','preview','calendar_day','calendar_week','calendar_month') NOT NULL DEFAULT 'table',
    is_shared TINYINT(1) NOT NULL DEFAULT 0,
    page_size SMALLINT UNSIGNED NOT NULL DEFAULT 25,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_reports_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_report_columns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    label VARCHAR(150) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    width VARCHAR(20) NULL,
    format VARCHAR(50) NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_report_columns_report FOREIGN KEY (report_id) REFERENCES saved_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_report_filters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    operator ENUM('eq','neq','contains','starts_with','gt','gte','lt','lte','between','in','is_null','not_null') NOT NULL,
    value_json LONGTEXT NULL,
    boolean_join ENUM('and','or') NOT NULL DEFAULT 'and',
    group_no SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT chk_report_filter_json CHECK (value_json IS NULL OR JSON_VALID(value_json)),
    CONSTRAINT fk_report_filters_report FOREIGN KEY (report_id) REFERENCES saved_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_report_sorts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    direction ENUM('asc','desc') NOT NULL DEFAULT 'asc',
    priority SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_report_sorts_report FOREIGN KEY (report_id) REFERENCES saved_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE calendar_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    event_type ENUM('task','follow_up','interview','deadline','meeting','reminder','other') NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    all_day TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('planned','completed','cancelled') NOT NULL DEFAULT 'planned',
    location VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_calendar_owner_start (owner_user_id, starts_at),
    CONSTRAINT fk_calendar_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_calendar_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    language_code CHAR(5) NOT NULL,
    template_type ENUM('cover_letter','email','pdf','report') NOT NULL,
    name VARCHAR(190) NOT NULL,
    subject_template TEXT NULL,
    body_template LONGTEXT NOT NULL,
    css LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_templates_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_templates_language FOREIGN KEY (language_code) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE generated_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    report_id BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    file_type ENUM('pdf','csv','xlsx','docx','zip') NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_generated_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_generated_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    CONSTRAINT fk_generated_report FOREIGN KEY (report_id) REFERENCES saved_reports(id) ON DELETE SET NULL,
    CONSTRAINT fk_generated_template FOREIGN KEY (template_id) REFERENCES document_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbound_emails (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    contact_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(254) NOT NULL,
    cc_json LONGTEXT NULL,
    bcc_json LONGTEXT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html LONGTEXT NULL,
    body_text LONGTEXT NULL,
    status ENUM('draft','queued','sending','sent','failed','cancelled') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    provider_message_id VARCHAR(255) NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_email_cc_json CHECK (cc_json IS NULL OR JSON_VALID(cc_json)),
    CONSTRAINT chk_email_bcc_json CHECK (bcc_json IS NULL OR JSON_VALID(bcc_json)),
    CONSTRAINT fk_emails_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
    CONSTRAINT fk_emails_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE SET NULL,
    CONSTRAINT fk_emails_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE outbound_email_attachments (
    email_id BIGINT UNSIGNED NOT NULL,
    generated_file_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (email_id, generated_file_id),
    CONSTRAINT fk_email_attachments_email FOREIGN KEY (email_id) REFERENCES outbound_emails(id) ON DELETE CASCADE,
    CONSTRAINT fk_email_attachments_file FOREIGN KEY (generated_file_id) REFERENCES generated_files(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    status ENUM('running','success','partial','failed') NOT NULL DEFAULT 'running',
    records_seen INT UNSIGNED NOT NULL DEFAULT 0,
    records_created INT UNSIGNED NOT NULL DEFAULT 0,
    records_updated INT UNSIGNED NOT NULL DEFAULT 0,
    records_failed INT UNSIGNED NOT NULL DEFAULT 0,
    log_text LONGTEXT NULL,
    CONSTRAINT fk_import_runs_source FOREIGN KEY (source_id) REFERENCES job_sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action ENUM('create','read','update','delete','login','logout','export','send','import','other') NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_values LONGTEXT NULL,
    new_values LONGTEXT NULL,
    ip_address VARBINARY(16) NULL,
    user_agent VARCHAR(500) NULL,
    request_id CHAR(36) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id, created_at),
    KEY idx_audit_user_date (user_id, created_at),
    CONSTRAINT chk_audit_old_json CHECK (old_values IS NULL OR JSON_VALID(old_values)),
    CONSTRAINT chk_audit_new_json CHECK (new_values IS NULL OR JSON_VALID(new_values)),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
