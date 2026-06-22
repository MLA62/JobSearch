<?php

declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']),
    'samesite' => 'Lax',
]);
session_start();

$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Application configuration is missing.');
}
$config = require $configPath;

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name'],
        (int) $config['db_port']
    );
    $db->set_charset('utf8mb4');
} catch (Throwable $exception) {
    http_response_code(503);
    exit('Database connection failed.');
}

try {
    $db->query("INSERT INTO languages (code, name, native_name, is_active, sort_order) VALUES
        ('de-CH', 'German (Switzerland)', 'Deutsch (Schweiz)', 1, 10),
        ('fr-CH', 'French (Switzerland)', 'Français (Suisse)', 1, 20),
        ('en-GB', 'English (United Kingdom)', 'English (UK)', 1, 30),
        ('pt-BR', 'Portuguese (Brazil)', 'Português (Brasil)', 1, 40),
        ('es-MX', 'Spanish (Mexico)', 'Español (México)', 1, 50)
        ON DUPLICATE KEY UPDATE name=VALUES(name), native_name=VALUES(native_name), is_active=VALUES(is_active), sort_order=VALUES(sort_order)");
    $localeMappings = ['de' => 'de-CH', 'fr' => 'fr-CH', 'en' => 'en-GB', 'es' => 'es-MX', 'pt' => 'pt-BR'];
    try {
        $db->query('ALTER TABLE record_translations MODIFY target_language CHAR(5) NOT NULL');
    } catch (Throwable $ignored) {
        // Die Tabelle kann auf aelteren oder frisch importierten Installationen noch fehlen.
    }
    foreach ([
        'users' => 'preferred_language',
        'contacts' => 'preferred_language',
        'user_documents' => 'language_code',
        'document_templates' => 'language_code',
        'record_translations' => 'target_language',
    ] as $table => $column) {
        try {
            foreach ($localeMappings as $oldLocale => $newLocale) {
                $stmt = $db->prepare("UPDATE `{$table}` SET `{$column}`=? WHERE `{$column}`=?");
                $stmt->bind_param('ss', $newLocale, $oldLocale);
                $stmt->execute();
            }
        } catch (Throwable $ignored) {
            // Aeltere Installationen haben noch nicht zwingend jede sprachbezogene Tabelle.
        }
    }
    try {
        $db->query("ALTER TABLE users ALTER COLUMN preferred_language SET DEFAULT 'de-CH'");
    } catch (Throwable $ignored) {
        // Die Vorgabe hilft, ist fuer den laufenden Betrieb aber nicht zwingend.
    }
    $db->query("CREATE TABLE IF NOT EXISTS ui_text_keys (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        text_key VARCHAR(190) NOT NULL,
        namespace VARCHAR(80) NOT NULL DEFAULT 'app',
        description VARCHAR(255) NULL,
        default_locale CHAR(5) NOT NULL DEFAULT 'de-CH',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ui_text_keys_key (text_key),
        KEY idx_ui_text_keys_namespace (namespace, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ui_text_translations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        text_key_id BIGINT UNSIGNED NOT NULL,
        locale CHAR(5) NOT NULL,
        text_value LONGTEXT NOT NULL,
        status ENUM('draft','review','approved','archived') NOT NULL DEFAULT 'approved',
        updated_by BIGINT UNSIGNED NULL,
        approved_by BIGINT UNSIGNED NULL,
        approved_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ui_text_translation (text_key_id, locale),
        KEY idx_ui_text_translations_locale (locale, status),
        KEY idx_ui_text_translations_key (text_key_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ui_text_cache_versions (
        locale CHAR(5) PRIMARY KEY,
        version BIGINT UNSIGNED NOT NULL DEFAULT 1,
        changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("INSERT IGNORE INTO ui_text_cache_versions (locale, version) SELECT code, 1 FROM languages WHERE is_active = 1");
} catch (Throwable $exception) {
    error_log('Locale migration failed: ' . $exception->getMessage());
}

try {
    $column = $db->query("SHOW COLUMNS FROM users LIKE 'last_seen_at'")->fetch_assoc();
    if (!$column) {
        $db->query('ALTER TABLE users ADD COLUMN last_seen_at DATETIME NULL AFTER last_login_at');
    }
    $db->query("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_hash CHAR(64) NOT NULL,
        ip_hash CHAR(64) NULL,
        user_agent_hash CHAR(64) NULL,
        first_seen_at DATETIME NOT NULL,
        last_seen_at DATETIME NOT NULL,
        logged_out_at DATETIME NULL,
        UNIQUE KEY uniq_user_session_hash (session_hash),
        KEY idx_user_sessions_user_active (user_id, logged_out_at, last_seen_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $exception) {
    // Optionale Laufzeit-Telemetrie darf die App nicht blockieren.
}

function ensureColumn(mysqli $db, string $table, string $column, string $definition, ?string $after = null): void
{
    $escapedTable = str_replace('`', '``', $table);
    $escapedColumn = $db->real_escape_string($column);
    $exists = $db->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'")->fetch_assoc();
    if ($exists) {
        return;
    }
    $afterSql = '';
    if ($after !== null) {
        $escapedAfter = $db->real_escape_string($after);
        $afterExists = $db->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedAfter}'")->fetch_assoc();
        if ($afterExists) {
            $afterSql = ' AFTER `' . str_replace('`', '``', $after) . '`';
        }
    }
    $db->query("ALTER TABLE `{$escapedTable}` ADD COLUMN {$definition}{$afterSql}");
}

function modifyColumnWhenMissingValue(mysqli $db, string $table, string $column, string $value, string $definition): void
{
    $escapedTable = str_replace('`', '``', $table);
    $escapedColumn = $db->real_escape_string($column);
    $columnInfo = $db->query("SHOW COLUMNS FROM `{$escapedTable}` LIKE '{$escapedColumn}'")->fetch_assoc();
    if ($columnInfo && strpos((string) $columnInfo['Type'], "'" . $value . "'") === false) {
        $db->query("ALTER TABLE `{$escapedTable}` MODIFY {$definition}");
    }
}

try {
    ensureColumn($db, 'applications', 'intermediary_company_id', '`intermediary_company_id` BIGINT UNSIGNED NULL', 'job_id');
    ensureColumn($db, 'applications', 'primary_contact_id', '`primary_contact_id` BIGINT UNSIGNED NULL', 'intermediary_company_id');
    ensureColumn($db, 'applications', 'application_url', '`application_url` VARCHAR(1000) NULL', 'channel');
    ensureColumn($db, 'applications', 'portal_account', '`portal_account` VARCHAR(254) NULL', 'application_url');
    ensureColumn($db, 'applications', 'reference_number', '`reference_number` VARCHAR(120) NULL', 'portal_account');
    ensureColumn($db, 'applications', 'online_notes', '`online_notes` TEXT NULL', 'reference_number');
    ensureColumn($db, 'applications', 'cover_letter_text', '`cover_letter_text` LONGTEXT NULL', 'online_notes');
    ensureColumn($db, 'applications', 'email_subject', '`email_subject` VARCHAR(255) NULL', 'cover_letter_text');
    ensureColumn($db, 'applications', 'email_body', '`email_body` LONGTEXT NULL', 'email_subject');
    ensureColumn($db, 'applications', 'next_action', '`next_action` VARCHAR(255) NULL', 'salary_currency');
    ensureColumn($db, 'applications', 'next_action_at', '`next_action_at` DATETIME NULL', 'next_action');
    ensureColumn($db, 'applications', 'notes', '`notes` LONGTEXT NULL', 'next_action_at');
    ensureColumn($db, 'companies', 'notes', '`notes` TEXT NULL', 'rating');
    ensureColumn($db, 'jobs', 'notes', '`notes` LONGTEXT NULL', 'description');
    $db->query("CREATE TABLE IF NOT EXISTS job_questions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        owner_user_id BIGINT UNSIGNED NOT NULL,
        job_id BIGINT UNSIGNED NOT NULL,
        question_text TEXT NOT NULL,
        answer_text LONGTEXT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        KEY idx_job_questions_job (job_id, sort_order),
        CONSTRAINT fk_job_questions_owner FOREIGN KEY (owner_user_id) REFERENCES users(id),
        CONSTRAINT fk_job_questions_job FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS job_platforms (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        base_url VARCHAR(500) NULL,
        search_url_template VARCHAR(1000) NOT NULL,
        notes TEXT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at DATETIME NULL,
        UNIQUE KEY uq_job_platform_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    seedJobPlatforms($db);
    ensureColumn($db, 'users', 'linkedin_url', '`linkedin_url` VARCHAR(500) NULL', 'mobile');
    ensureColumn($db, 'users', 'facebook_url', '`facebook_url` VARCHAR(500) NULL', 'linkedin_url');
    ensureColumn($db, 'users', 'x_url', '`x_url` VARCHAR(500) NULL', 'facebook_url');
    ensureColumn($db, 'users', 'other_profile_url', '`other_profile_url` VARCHAR(500) NULL', 'x_url');
    ensureColumn($db, 'document_types', 'sort_order', '`sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'name_key');
    ensureColumn($db, 'contacts', 'application_id', '`application_id` BIGINT UNSIGNED NULL', 'company_id');
    ensureColumn($db, 'contact_logs', 'application_id', '`application_id` BIGINT UNSIGNED NULL', 'company_id');
    ensureColumn($db, 'contact_logs', 'status', "`status` ENUM('planned','open','done','cancelled') NOT NULL DEFAULT 'done'", 'direction');
    ensureColumn($db, 'contact_logs', 'outcome', '`outcome` VARCHAR(500) NULL', 'follow_up_at');
    ensureColumn($db, 'user_preferences', 'salary_period', "`salary_period` ENUM('hour','month','year') NOT NULL DEFAULT 'year'", 'salary_currency');
    ensureColumn($db, 'user_preferences', 'desired_benefits', '`desired_benefits` TEXT NULL', 'desired_level');
    ensureColumn($db, 'user_preferences', 'excluded_industries', '`excluded_industries` TEXT NULL', 'desired_benefits');
    ensureColumn($db, 'user_preferences', 'willing_to_relocate', '`willing_to_relocate` TINYINT(1) NOT NULL DEFAULT 0', 'excluded_industries');
    ensureColumn($db, 'user_preferences', 'travel_percentage', '`travel_percentage` TINYINT UNSIGNED NULL', 'willing_to_relocate');
    ensureColumn($db, 'user_preferences', 'available_from', '`available_from` DATE NULL', 'travel_percentage');
    $db->query("CREATE TABLE IF NOT EXISTS application_documents (
        application_id BIGINT UNSIGNED NOT NULL,
        user_document_id BIGINT UNSIGNED NOT NULL,
        purpose ENUM('cv','cover_letter','certificate','reference','portfolio','other') NOT NULL DEFAULT 'other',
        sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (application_id, user_document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    ensureColumn($db, 'application_documents', 'purpose', "`purpose` ENUM('cv','cover_letter','certificate','reference','portfolio','other') NOT NULL DEFAULT 'other'", 'user_document_id');
    ensureColumn($db, 'application_documents', 'sort_order', '`sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0', 'purpose');
    modifyColumnWhenMissingValue($db, 'applications', 'status', 'ready', "`status` ENUM('draft','ready','sent','confirmed','interview','assessment','offer','accepted','rejected','withdrawn','closed') NOT NULL DEFAULT 'draft'");
    modifyColumnWhenMissingValue($db, 'applications', 'channel', 'website', "`channel` ENUM('email','portal','website','mail','referral','other') NULL");
    $db->query("UPDATE applications a SET a.next_action=NULL, a.next_action_at=NULL WHERE a.status IN ('rejected','withdrawn','closed') AND a.deleted_at IS NULL AND (a.next_action IS NOT NULL OR a.next_action_at IS NOT NULL)");
    $db->query("UPDATE applications a JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL SET a.next_action='Antwort auf Bewerbung pendent', a.next_action_at=COALESCE(a.applied_at, a.next_action_at, NOW()) WHERE a.status='sent' AND a.deleted_at IS NULL AND (a.next_action IS NULL OR a.next_action='' OR a.next_action='Eingang bestätigen lassen' OR a.next_action<>'Antwort auf Bewerbung pendent' OR (a.applied_at IS NOT NULL AND (a.next_action_at IS NULL OR a.next_action_at<>a.applied_at)) OR (a.applied_at IS NULL AND a.next_action_at IS NULL))");
    foreach (dbAll($db, "SELECT a.id, a.user_id FROM applications a JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE a.status='sent' AND a.deleted_at IS NULL AND a.primary_contact_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM contact_logs l WHERE l.owner_user_id=a.user_id AND l.application_id=a.id AND l.contact_id=a.primary_contact_id AND l.subject='Bewerbung eingereicht') LIMIT 200") as $submittedApplication) {
        ensureSubmittedApplicationContactLog($db, (int) $submittedApplication['user_id'], (int) $submittedApplication['id']);
    }
} catch (Throwable $exception) {
    error_log('Online application schema check failed: ' . $exception->getMessage());
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function filePickerHtml(string $name, bool $required = true): string
{
    $id = 'file_' . bin2hex(random_bytes(4));
    return '<label class="file-picker">' . e(tr('documents.file'))
        . '<input id="' . e($id) . '" type="file" name="' . e($name) . '"' . ($required ? ' required' : '') . ' data-file-picker>'
        . '<span class="file-picker-button">' . e(tr('documents.choose_file')) . '</span>'
        . '<span class="file-picker-name" data-file-picker-name data-empty="' . e(tr('documents.no_file_selected')) . '">' . e(tr('documents.no_file_selected')) . '</span>'
        . '</label>';
}

function cascadeExec(mysqli $db, string $sql, string $types = '', array $values = []): void
{
    try {
        if ($types === '') {
            $db->query($sql);
            return;
        }
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    } catch (Throwable $exception) {
        error_log('Cascade cleanup skipped: ' . $exception->getMessage() . ' SQL=' . $sql);
    }
}

function cleanupReportCascade(mysqli $db, int $ownerUserId, int $reportId): void
{
    cascadeExec($db, 'DELETE FROM saved_report_columns WHERE report_id=?', 'i', [$reportId]);
    cascadeExec($db, 'DELETE FROM saved_report_filters WHERE report_id=?', 'i', [$reportId]);
    cascadeExec($db, 'DELETE FROM saved_report_sorts WHERE report_id=?', 'i', [$reportId]);
    cascadeExec($db, "UPDATE guest_shares SET revoked_at=COALESCE(revoked_at, NOW()) WHERE owner_user_id=? AND target_type='report' AND target_id=?", 'ii', [$ownerUserId, $reportId]);
}

function cleanupTranslationCascade(mysqli $db, int $ownerUserId, string $entityType, int $entityId): void
{
    cascadeExec($db, 'DELETE FROM record_translations WHERE owner_user_id=? AND entity_type=? AND entity_id=?', 'isi', [$ownerUserId, $entityType, $entityId]);
}

function cleanupShareCascade(mysqli $db, int $ownerUserId, string $targetType, int $targetId): void
{
    cascadeExec($db, 'UPDATE guest_shares SET revoked_at=COALESCE(revoked_at, NOW()) WHERE owner_user_id=? AND target_type=? AND target_id=?', 'isi', [$ownerUserId, $targetType, $targetId]);
}

function cleanupDocumentCascade(mysqli $db, int $ownerUserId, int $documentId): void
{
    cascadeExec($db, 'DELETE FROM application_documents WHERE user_document_id=?', 'i', [$documentId]);
    cascadeExec($db, 'DELETE FROM contact_log_documents WHERE user_document_id=?', 'i', [$documentId]);
    cascadeExec($db, 'DELETE FROM document_texts WHERE user_document_id=?', 'i', [$documentId]);
    cleanupTranslationCascade($db, $ownerUserId, 'document', $documentId);
    cleanupShareCascade($db, $ownerUserId, 'document', $documentId);
}

function cleanupContactLogCascade(mysqli $db, int $ownerUserId, int $logId): void
{
    cascadeExec($db, 'UPDATE user_documents d JOIN contact_log_documents cld ON cld.user_document_id=d.id SET d.deleted_at=COALESCE(d.deleted_at, NOW()), d.is_current=0 WHERE cld.contact_log_id=? AND d.user_id=?', 'ii', [$logId, $ownerUserId]);
    cascadeExec($db, 'DELETE FROM contact_log_documents WHERE contact_log_id=?', 'i', [$logId]);
}

function cleanupContactCascade(mysqli $db, int $ownerUserId, int $contactId): void
{
    foreach (dbAll($db, 'SELECT id FROM contact_logs WHERE owner_user_id=? AND contact_id=?', 'ii', [$ownerUserId, $contactId]) as $log) {
        cleanupContactLogCascade($db, $ownerUserId, (int) $log['id']);
    }
    cascadeExec($db, 'DELETE FROM contact_logs WHERE owner_user_id=? AND contact_id=?', 'ii', [$ownerUserId, $contactId]);
    cascadeExec($db, 'UPDATE applications SET primary_contact_id=NULL WHERE user_id=? AND primary_contact_id=?', 'ii', [$ownerUserId, $contactId]);
    cleanupTranslationCascade($db, $ownerUserId, 'contact', $contactId);
    cleanupShareCascade($db, $ownerUserId, 'contact', $contactId);
}

function cleanupApplicationCascade(mysqli $db, int $ownerUserId, int $applicationId): void
{
    foreach (dbAll($db, 'SELECT id FROM user_documents WHERE user_id=? AND scope="application" AND application_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $applicationId]) as $document) {
        cleanupDocumentCascade($db, $ownerUserId, (int) $document['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM contact_logs WHERE owner_user_id=? AND application_id=?', 'ii', [$ownerUserId, $applicationId]) as $log) {
        cleanupContactLogCascade($db, $ownerUserId, (int) $log['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM contacts WHERE owner_user_id=? AND application_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $applicationId]) as $contact) {
        cleanupContactCascade($db, $ownerUserId, (int) $contact['id']);
    }
    cascadeExec($db, 'DELETE FROM application_documents WHERE application_id=?', 'i', [$applicationId]);
    cascadeExec($db, 'UPDATE user_documents SET deleted_at=COALESCE(deleted_at, NOW()), is_current=0 WHERE user_id=? AND scope="application" AND application_id=?', 'ii', [$ownerUserId, $applicationId]);
    cascadeExec($db, 'DELETE FROM contact_logs WHERE owner_user_id=? AND application_id=?', 'ii', [$ownerUserId, $applicationId]);
    cascadeExec($db, 'DELETE FROM calendar_events WHERE owner_user_id=? AND application_id=?', 'ii', [$ownerUserId, $applicationId]);
    cascadeExec($db, 'DELETE FROM application_status_history WHERE application_id=?', 'i', [$applicationId]);
    cleanupTranslationCascade($db, $ownerUserId, 'application', $applicationId);
    cleanupShareCascade($db, $ownerUserId, 'application', $applicationId);
}

function cleanupJobCascade(mysqli $db, int $ownerUserId, int $jobId): void
{
    foreach (dbAll($db, 'SELECT id FROM applications WHERE user_id=? AND job_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $jobId]) as $application) {
        cleanupApplicationCascade($db, $ownerUserId, (int) $application['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM contacts WHERE owner_user_id=? AND job_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $jobId]) as $contact) {
        cleanupContactCascade($db, $ownerUserId, (int) $contact['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM user_documents WHERE user_id=? AND job_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $jobId]) as $document) {
        cleanupDocumentCascade($db, $ownerUserId, (int) $document['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM contact_logs WHERE owner_user_id=? AND job_id=?', 'ii', [$ownerUserId, $jobId]) as $log) {
        cleanupContactLogCascade($db, $ownerUserId, (int) $log['id']);
    }
    cascadeExec($db, 'UPDATE applications SET deleted_at=COALESCE(deleted_at, NOW()) WHERE user_id=? AND job_id=?', 'ii', [$ownerUserId, $jobId]);
    cascadeExec($db, 'UPDATE contacts SET deleted_at=COALESCE(deleted_at, NOW()) WHERE owner_user_id=? AND job_id=?', 'ii', [$ownerUserId, $jobId]);
    cascadeExec($db, 'UPDATE job_questions SET deleted_at=COALESCE(deleted_at, NOW()) WHERE owner_user_id=? AND job_id=?', 'ii', [$ownerUserId, $jobId]);
    cascadeExec($db, 'UPDATE user_documents SET deleted_at=COALESCE(deleted_at, NOW()), is_current=0 WHERE user_id=? AND job_id=?', 'ii', [$ownerUserId, $jobId]);
    cascadeExec($db, 'DELETE FROM contact_logs WHERE owner_user_id=? AND job_id=?', 'ii', [$ownerUserId, $jobId]);
    cleanupTranslationCascade($db, $ownerUserId, 'job', $jobId);
    cleanupShareCascade($db, $ownerUserId, 'job', $jobId);
}

function cleanupCompanyCascade(mysqli $db, int $ownerUserId, int $companyId): void
{
    foreach (dbAll($db, 'SELECT id FROM jobs WHERE owner_user_id=? AND company_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $companyId]) as $job) {
        cleanupJobCascade($db, $ownerUserId, (int) $job['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM contacts WHERE owner_user_id=? AND company_id=? AND deleted_at IS NULL', 'ii', [$ownerUserId, $companyId]) as $contact) {
        cleanupContactCascade($db, $ownerUserId, (int) $contact['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM contact_logs WHERE owner_user_id=? AND company_id=?', 'ii', [$ownerUserId, $companyId]) as $log) {
        cleanupContactLogCascade($db, $ownerUserId, (int) $log['id']);
    }
    cascadeExec($db, 'UPDATE jobs SET deleted_at=COALESCE(deleted_at, NOW()) WHERE owner_user_id=? AND company_id=?', 'ii', [$ownerUserId, $companyId]);
    cascadeExec($db, 'UPDATE contacts SET deleted_at=COALESCE(deleted_at, NOW()) WHERE owner_user_id=? AND company_id=?', 'ii', [$ownerUserId, $companyId]);
    cascadeExec($db, 'UPDATE applications SET intermediary_company_id=NULL WHERE user_id=? AND intermediary_company_id=?', 'ii', [$ownerUserId, $companyId]);
    cascadeExec($db, 'UPDATE company_relationships SET deleted_at=COALESCE(deleted_at, NOW()) WHERE owner_user_id=? AND (intermediary_company_id=? OR client_company_id=?)', 'iii', [$ownerUserId, $companyId, $companyId]);
    cascadeExec($db, 'DELETE FROM contact_logs WHERE owner_user_id=? AND company_id=?', 'ii', [$ownerUserId, $companyId]);
    cleanupTranslationCascade($db, $ownerUserId, 'company', $companyId);
    cleanupShareCascade($db, $ownerUserId, 'company', $companyId);
}

function cleanupUserCascade(mysqli $db, int $targetUserId): void
{
    cascadeExec($db, 'UPDATE user_sessions SET logged_out_at=COALESCE(logged_out_at, NOW()) WHERE user_id=?', 'i', [$targetUserId]);
    cascadeExec($db, 'DELETE FROM auth_tokens WHERE user_id=?', 'i', [$targetUserId]);
    cascadeExec($db, 'DELETE FROM two_factor_methods WHERE user_id=?', 'i', [$targetUserId]);
    cascadeExec($db, 'DELETE FROM user_roles WHERE user_id=?', 'i', [$targetUserId]);
    cascadeExec($db, 'UPDATE support_grants SET revoked_at=COALESCE(revoked_at, NOW()) WHERE user_id=? OR admin_user_id=?', 'ii', [$targetUserId, $targetUserId]);
    cascadeExec($db, 'UPDATE guest_shares SET revoked_at=COALESCE(revoked_at, NOW()) WHERE owner_user_id=?', 'i', [$targetUserId]);
    foreach (dbAll($db, 'SELECT id FROM companies WHERE owner_user_id=? AND deleted_at IS NULL', 'i', [$targetUserId]) as $company) {
        cleanupCompanyCascade($db, $targetUserId, (int) $company['id']);
    }
    foreach (dbAll($db, 'SELECT id FROM user_documents WHERE user_id=? AND deleted_at IS NULL', 'i', [$targetUserId]) as $document) {
        cleanupDocumentCascade($db, $targetUserId, (int) $document['id']);
    }
    cascadeExec($db, 'UPDATE user_documents SET deleted_at=COALESCE(deleted_at, NOW()), is_current=0 WHERE user_id=?', 'i', [$targetUserId]);
    cascadeExec($db, 'UPDATE user_preferences SET is_active=0 WHERE user_id=?', 'i', [$targetUserId]);
    cascadeExec($db, 'DELETE FROM record_translations WHERE owner_user_id=?', 'i', [$targetUserId]);
}

function multilingualUiEnabled(): bool
{
    return true;
}

function legacyHtmlTranslationEnabled(): bool
{
    return false;
}

function pageSupportsMultilingualUi(string $page): bool
{
    return true;
}

function supportedLocales(): array
{
    if (!multilingualUiEnabled()) {
        return [
            'de-CH' => ['name' => 'Deutsch (Schweiz)', 'native' => 'Deutsch', 'code' => 'DE'],
        ];
    }
    return [
        'de-CH' => ['name' => 'Deutsch (Schweiz)', 'native' => 'Deutsch', 'code' => 'DE'],
        'fr-CH' => ['name' => 'Français (Suisse)', 'native' => 'Français', 'code' => 'FR'],
        'en-GB' => ['name' => 'English (UK)', 'native' => 'English', 'code' => 'EN'],
        'pt-BR' => ['name' => 'Português (Brasil)', 'native' => 'Português', 'code' => 'PT'],
        'es-MX' => ['name' => 'Español (México)', 'native' => 'Español', 'code' => 'ES'],
    ];
}

function normalizeLocale(?string $locale): string
{
    if (!multilingualUiEnabled()) {
        return 'de-CH';
    }
    $locale = strtolower(str_replace('_', '-', trim((string) $locale)));
    if ($locale === '') {
        return 'de-CH';
    }
    $aliases = [
        'de' => 'de-CH',
        'de-ch' => 'de-CH',
        'de-de' => 'de-CH',
        'de-at' => 'de-CH',
        'fr' => 'fr-CH',
        'fr-ch' => 'fr-CH',
        'fr-fr' => 'fr-CH',
        'en' => 'en-GB',
        'en-gb' => 'en-GB',
        'en-us' => 'en-GB',
        'pt' => 'pt-BR',
        'pt-br' => 'pt-BR',
        'pt-pt' => 'pt-BR',
        'es' => 'es-MX',
        'es-mx' => 'es-MX',
        'es-es' => 'es-MX',
    ];
    if (isset($aliases[$locale])) {
        return $aliases[$locale];
    }
    $language = explode('-', $locale, 2)[0];
    return $aliases[$language] ?? 'de-CH';
}

function browserLocale(): string
{
    $header = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    foreach (explode(',', $header) as $entry) {
        $locale = trim(explode(';', $entry, 2)[0]);
        if ($locale !== '') {
            return normalizeLocale($locale);
        }
    }
    return 'de-CH';
}

function currentLocale(?array $currentUser = null): string
{
    if (!empty($_SESSION['locale'])) {
        return normalizeLocale((string) $_SESSION['locale']);
    }
    if ($currentUser && !empty($currentUser['preferred_language'])) {
        return normalizeLocale((string) $currentUser['preferred_language']);
    }
    return browserLocale();
}

function localeName(string $locale): string
{
    $locale = normalizeLocale($locale);
    return supportedLocales()[$locale]['name'] ?? supportedLocales()['de-CH']['name'];
}

function localeNativeName(string $locale): string
{
    $locale = normalizeLocale($locale);
    return supportedLocales()[$locale]['native'] ?? supportedLocales()['de-CH']['native'];
}

function localeCode(string $locale): string
{
    $locale = normalizeLocale($locale);
    return supportedLocales()[$locale]['code'] ?? supportedLocales()['de-CH']['code'];
}

function localeFlagIconHtml(string $locale): string
{
    $country = match (normalizeLocale($locale)) {
        'de-CH', 'fr-CH' => 'ch',
        'en-GB' => 'gb',
        'pt-BR' => 'br',
        'es-MX' => 'mx',
        default => 'ch',
    };
    $icons = [
        'ch' => '<svg class="locale-flag locale-flag-ch" viewBox="0 0 32 32" role="img" focusable="false"><rect width="32" height="32" rx="2" fill="#d52b1e"/><path fill="#fff" d="M13 6h6v7h7v6h-7v7h-6v-7H6v-6h7z"/></svg>',
        'gb' => '<svg class="locale-flag" viewBox="0 0 60 36" role="img" focusable="false"><rect width="60" height="36" fill="#012169"/><path stroke="#fff" stroke-width="7" d="M0 0l60 36M60 0L0 36"/><path stroke="#c8102e" stroke-width="4" d="M0 0l60 36M60 0L0 36"/><path stroke="#fff" stroke-width="12" d="M30 0v36M0 18h60"/><path stroke="#c8102e" stroke-width="7" d="M30 0v36M0 18h60"/></svg>',
        'br' => '<svg class="locale-flag" viewBox="0 0 60 36" role="img" focusable="false"><rect width="60" height="36" fill="#009b3a"/><path fill="#ffdf00" d="M30 4l25 14-25 14L5 18z"/><circle cx="30" cy="18" r="8.5" fill="#002776"/><path stroke="#fff" stroke-width="2" d="M20 17c7-3 14-3 20 1"/></svg>',
        'mx' => '<svg class="locale-flag" viewBox="0 0 60 36" role="img" focusable="false"><rect width="20" height="36" fill="#006847"/><rect x="20" width="20" height="36" fill="#fff"/><rect x="40" width="20" height="36" fill="#ce1126"/><circle cx="30" cy="18" r="4.2" fill="#b38e5d"/><path stroke="#006847" stroke-width="1.2" d="M27 20c2 2 5 2 7 0"/></svg>',
    ];
    return $icons[$country];
}

function localeHtmlLang(string $locale): string
{
    return normalizeLocale($locale);
}

function languageUrl(string $locale): string
{
    $params = $_GET;
    $params['lang'] = normalizeLocale($locale);
    return '/?' . http_build_query($params);
}

function languagePickerHtml(string $activeLocale, string $modifier = ''): string
{
    if (!multilingualUiEnabled()) {
        return '';
    }
    $activeLocale = normalizeLocale($activeLocale);
    ob_start();
    ?>
    <nav class="locale-picker <?= e($modifier) ?>" aria-label="<?= e(tr('language.choose', $activeLocale)) ?>">
        <?php foreach (supportedLocales() as $locale => $meta): ?>
            <a class="locale-option <?= $activeLocale === $locale ? 'is-active' : '' ?>" href="<?= e(languageUrl($locale)) ?>" lang="<?= e(localeHtmlLang($locale)) ?>" title="<?= e($meta['name']) ?>" aria-label="<?= e($meta['name']) ?>">
                <?= localeFlagIconHtml($locale) ?>
                <span class="locale-code" aria-hidden="true"><?= e((string)($meta['code'] ?? localeCode($locale))) ?></span>
                <span class="locale-label"><?= e($meta['native']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
    return trim((string) ob_get_clean());
}

function translationCatalog(): array
{
    return [];
}

function tr(string $key, ?string $locale = null, array $replace = []): string
{
    global $appLocale, $currentUser;
    $locale = normalizeLocale($locale ?? ((string) ($appLocale ?? '') !== '' ? (string) $appLocale : currentLocale(is_array($currentUser ?? null) ? $currentUser : null)));
    $text = dbUiText($key, $locale);
    if ($text === null) {
        $text = $key;
    }
    foreach ($replace as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function dbUiText(string $key, string $locale): ?string
{
    global $db;
    static $cache = [];
    static $available = null;
    $locale = normalizeLocale($locale);
    if ($key === '') {
        return null;
    }
    if ($available === false) {
        return null;
    }
    if (!isset($cache[$locale])) {
        $cache[$locale] = [];
        try {
            $rows = dbAll(
                $db,
                "SELECT k.text_key, t.text_value
                   FROM ui_text_keys k
                   JOIN ui_text_translations t ON t.text_key_id = k.id
                  WHERE k.is_active = 1
                    AND t.status = 'approved'
                    AND t.locale IN (?, 'de-CH')
                  ORDER BY CASE WHEN t.locale = ? THEN 0 ELSE 1 END",
                'ss',
                [$locale, $locale]
            );
            foreach ($rows as $row) {
                $textKey = (string) $row['text_key'];
                if (!array_key_exists($textKey, $cache[$locale])) {
                    $cache[$locale][$textKey] = (string) $row['text_value'];
                }
            }
            $available = true;
        } catch (Throwable $exception) {
            $available = false;
            error_log('DB UI text lookup failed: ' . $exception->getMessage());
            return null;
        }
    }
    return $cache[$locale][$key] ?? null;
}

function rememberDbUiTextFallback(string $key, string $locale, string $text): void
{
    global $db;
    static $remembered = [];
    $locale = normalizeLocale($locale);
    $cacheKey = $locale . "\0" . $key;
    if (isset($remembered[$cacheKey]) || $key === '' || $text === '') {
        return;
    }
    $remembered[$cacheKey] = true;
    try {
        $namespace = substr((string) (str_contains($key, '.') ? strtok($key, '.') : 'app'), 0, 80);
        $defaultLocale = 'de-CH';
        $stmt = $db->prepare('INSERT INTO ui_text_keys (text_key, namespace, default_locale) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE namespace=VALUES(namespace), is_active=1');
        $stmt->bind_param('sss', $key, $namespace, $defaultLocale);
        $stmt->execute();

        $row = dbOne($db, 'SELECT id FROM ui_text_keys WHERE text_key=?', 's', [$key]);
        if (!$row) {
            return;
        }
        $keyId = (int) $row['id'];
        $status = 'approved';
        $stmt = $db->prepare('INSERT INTO ui_text_translations (text_key_id, locale, text_value, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE text_value=IF(text_value="", VALUES(text_value), text_value), status=IF(status="archived", VALUES(status), status)');
        $stmt->bind_param('isss', $keyId, $locale, $text, $status);
        $stmt->execute();
    } catch (Throwable $exception) {
        error_log('DB UI text remember failed: ' . $exception->getMessage());
    }
}

function legacyLiteralTextKey(string $text): string
{
    return 'legacy.literal.' . sha1($text);
}

function seedDbUiTextCatalog(): void
{
    return;
}

function legacyUiSupplementalTranslationCatalog(): array
{
    return [];
}

function legacyUiQualityPatchTranslationCatalog(): array
{
    return [];
}

function legacyUiSeedCatalog(): array
{
    return [];
}

function dbLegacyUiTranslationMap(string $locale): array
{
    global $db;
    static $cache = [];
    $locale = normalizeLocale($locale);
    if ($locale === 'de-CH') {
        return [];
    }
    if (array_key_exists($locale, $cache)) {
        return $cache[$locale];
    }
    $cache[$locale] = [];
    try {
        $rows = dbAll(
            $db,
            "SELECT de.text_value source_text, COALESCE(target.text_value, de.text_value) target_text
               FROM ui_text_keys k
               JOIN ui_text_translations de
                 ON de.text_key_id = k.id
                AND de.locale = 'de-CH'
                AND de.status = 'approved'
          LEFT JOIN ui_text_translations target
                 ON target.text_key_id = k.id
                AND target.locale = ?
                AND target.status = 'approved'
              WHERE k.is_active = 1
                AND k.text_key LIKE 'legacy.literal.%'",
            's',
            [$locale]
        );
        foreach ($rows as $row) {
            $source = (string) ($row['source_text'] ?? '');
            $target = (string) ($row['target_text'] ?? '');
            if ($source !== '' && $target !== '' && $source !== $target) {
                $cache[$locale][$source] = $target;
            }
        }
    } catch (Throwable $exception) {
        error_log('DB legacy UI text lookup failed: ' . $exception->getMessage());
    }
    return $cache[$locale];
}

function translateUiSegment(string $text, string $locale): string
{
    $locale = normalizeLocale($locale);
    if ($locale === 'de-CH') {
        return $text;
    }
    $map = dbLegacyUiTranslationMap($locale);
    if (!$map) {
        return $text;
    }
    $leading = '';
    $trailing = '';
    if (preg_match('/^\s+/u', $text, $match)) {
        $leading = $match[0];
    }
    if (preg_match('/\s+$/u', $text, $match)) {
        $trailing = $match[0];
    }
    $core = trim($text);
    if ($core === '') {
        return $text;
    }
    if (isset($map[$core])) {
        return $leading . $map[$core] . $trailing;
    }

    $phrases = $map;
    if ($phrases) {
        uksort($phrases, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($phrases as $source => $target) {
            $source = (string) $source;
            if (strlen($source) < 4 || $source === (string) $target) {
                continue;
            }
            $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($source, '/') . '(?![\p{L}\p{N}_])/u';
            $core = preg_replace($pattern, (string) $target, $core) ?? $core;
        }
    }
    return $leading . $core . $trailing;
}

function translateUiHtml(string $html, string $locale): string
{
    $locale = normalizeLocale($locale);
    if ($locale === 'de-CH' || $html === '') {
        return $html;
    }
    $protected = [];
    $protect = static function (string $pattern) use (&$html, &$protected): void {
        $html = preg_replace_callback($pattern, static function (array $match) use (&$protected): string {
            $key = '%%JEMA_I18N_SKIP_' . count($protected) . '%%';
            $protected[$key] = $match[0];
            return $key;
        }, $html) ?? $html;
    };

    // Nicht sichtbare Technik, Benutzereingaben und explizit markierte Marken/Daten nie automatisch uebersetzen.
    $protect('/<(script|style|pre|code)\b[^>]*>.*?<\/\1>/is');
    $html = preg_replace_callback('/(<textarea\b[^>]*>)(.*?)(<\/textarea>)/is', static function (array $match) use (&$protected): string {
        $key = '%%JEMA_I18N_SKIP_' . count($protected) . '%%';
        $protected[$key] = $match[2];
        return $match[1] . $key . $match[3];
    }, $html) ?? $html;
    $protect('/<([a-z][a-z0-9:-]*)\b(?=[^>]*(?:data-i18n-skip|translate=(["\'])no\2))[^>]*>.*?<\/\1>/is');

    $html = preg_replace_callback('/>([^<>]+)</u', static function (array $match) use ($locale): string {
        return '>' . translateUiSegment($match[1], $locale) . '<';
    }, $html) ?? $html;
    $html = preg_replace_callback('/\b(placeholder|title|aria-label|data-progress-button-text|data-progress-steps)=("|\')([^"\']*)(\2)/u', static function (array $match) use ($locale): string {
        return $match[1] . '=' . $match[2] . translateUiSegment($match[3], $locale) . $match[4];
    }, $html) ?? $html;
    $html = preg_replace_callback('/confirm\((["\'])(.*?)(\1)\)/u', static function (array $match) use ($locale): string {
        return 'confirm(' . $match[1] . translateUiSegment($match[2], $locale) . $match[3] . ')';
    }, $html) ?? $html;
    return $protected ? strtr($html, $protected) : $html;
}

function startUiTranslationBuffer(string $locale): void
{
    $locale = normalizeLocale($locale);
    if ($locale === 'de-CH' || !legacyHtmlTranslationEnabled()) {
        return;
    }
    ob_start(static fn(string $buffer): string => translateUiHtml($buffer, $locale));
}

function localizedHelpTopics(string $locale): array
{
    $locale = normalizeLocale($locale);
    return [
        [
            'category' => tr('help.flow.profile.title', $locale),
            'audience' => tr('nav.account', $locale),
            'title' => tr('help.flow.profile.title', $locale),
            'summary' => tr('help.flow.profile.text', $locale),
            'steps' => [tr('profile.app_language_hint', $locale), tr('help.quick.search.body', $locale), tr('help.flow.profile.text', $locale)],
            'tips' => [tr('help.hero_intro', $locale)],
            'links' => [[tr('nav.profile', $locale), '/?page=profile'], [tr('nav.documents', $locale), '/?page=documents']],
            'keywords' => 'profile documents preferences security',
        ],
        [
            'category' => tr('help.flow.search.title', $locale),
            'audience' => tr('nav.application', $locale),
            'title' => tr('help.quick.search.title', $locale),
            'summary' => tr('help.flow.search.text', $locale),
            'steps' => [tr('help.quick.search.body', $locale), tr('help.flow.search.text', $locale), tr('help.flow.import.text', $locale)],
            'tips' => [tr('help.search_status_initial', $locale)],
            'links' => [[tr('help.quick.search.link', $locale), '/?page=job_platform_search'], [tr('dashboard.create_job', $locale), '/?page=jobs#quick-import']],
            'keywords' => 'search portals prompt direct links import',
        ],
        [
            'category' => tr('help.flow.import.title', $locale),
            'audience' => tr('nav.jobs', $locale),
            'title' => tr('help.flow.import.title', $locale),
            'summary' => tr('help.flow.import.text', $locale),
            'steps' => [tr('help.quick.search.body', $locale), tr('help.flow.import.text', $locale), tr('help.flow.apply.text', $locale)],
            'tips' => [tr('help.search_status_initial', $locale)],
            'links' => [[tr('nav.jobs', $locale), '/?page=jobs'], [tr('dashboard.create_job', $locale), '/?page=jobs#quick-import']],
            'keywords' => 'jobs import quick import duplicate company proposal',
        ],
        [
            'category' => tr('nav.crm', $locale),
            'audience' => tr('nav.companies', $locale),
            'title' => tr('nav.companies', $locale),
            'summary' => tr('help.flow.import.text', $locale),
            'steps' => [tr('help.flow.import.text', $locale), tr('help.flow.follow.text', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('help.quick.track.body', $locale)],
            'links' => [[tr('nav.companies', $locale), '/?page=companies']],
            'keywords' => 'company companies employer crm contacts reports',
        ],
        [
            'category' => tr('help.flow.apply.title', $locale),
            'audience' => tr('nav.applications', $locale),
            'title' => tr('help.quick.apply.title', $locale),
            'summary' => tr('help.flow.apply.text', $locale),
            'steps' => [tr('help.quick.apply.body', $locale), tr('help.flow.apply.text', $locale), tr('help.flow.follow.text', $locale)],
            'tips' => [tr('dashboard.next_body', $locale)],
            'links' => [[tr('help.quick.apply.link', $locale), '/?page=applications']],
            'keywords' => 'application online documents submission',
        ],
        [
            'category' => tr('help.flow.apply.title', $locale),
            'audience' => tr('nav.applications', $locale),
            'title' => tr('help.flow.apply.text', $locale),
            'summary' => tr('help.quick.apply.body', $locale),
            'steps' => [tr('help.flow.apply.text', $locale), tr('help.flow.follow.text', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('help.quick.track.body', $locale)],
            'links' => [[tr('nav.applications', $locale), '/?page=applications']],
            'keywords' => 'online application web form submit documents package',
        ],
        [
            'category' => tr('nav.documents', $locale),
            'audience' => tr('nav.documents', $locale),
            'title' => tr('nav.documents', $locale),
            'summary' => tr('help.flow.profile.text', $locale),
            'steps' => [tr('help.flow.profile.text', $locale), tr('help.quick.apply.body', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('profile.app_language_hint', $locale)],
            'links' => [[tr('nav.documents', $locale), '/?page=documents'], [tr('nav.profile', $locale), '/?page=profile#documents']],
            'keywords' => 'documents files attachments versions profile application',
        ],
        [
            'category' => tr('help.flow.follow.title', $locale),
            'audience' => tr('nav.planning', $locale),
            'title' => tr('help.quick.track.title', $locale),
            'summary' => tr('help.flow.follow.text', $locale),
            'steps' => [tr('help.quick.track.body', $locale), tr('help.flow.follow.text', $locale)],
            'tips' => [tr('help.status.jump', $locale) . ': ' . tr('help.flow.follow.title', $locale)],
            'links' => [[tr('help.quick.track.link', $locale), '/?page=pendents'], [tr('nav.calendar', $locale), '/?page=calendar']],
            'keywords' => 'follow up pending calendar contact log',
        ],
        [
            'category' => tr('help.flow.dossier.title', $locale),
            'audience' => tr('nav.reporting', $locale),
            'title' => tr('help.flow.dossier.title', $locale),
            'summary' => tr('help.flow.dossier.text', $locale),
            'steps' => [tr('help.flow.dossier.text', $locale), tr('help.license_body2', $locale)],
            'tips' => [tr('help.quick.track.body', $locale)],
            'links' => [[tr('nav.reports', $locale), '/?page=reports'], [tr('nav.applications', $locale), '/?page=applications']],
            'keywords' => 'dossier reports pdf documentation',
        ],
        [
            'category' => tr('nav.crm', $locale),
            'audience' => tr('nav.contacts', $locale),
            'title' => tr('nav.contacts', $locale),
            'summary' => tr('help.flow.follow.text', $locale),
            'steps' => [tr('help.quick.track.body', $locale), tr('help.flow.follow.text', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('dashboard.next_body', $locale)],
            'links' => [[tr('nav.contacts', $locale), '/?page=contacts'], [tr('nav.pendents', $locale), '/?page=pendents']],
            'keywords' => 'contacts contact log crm follow up attachment',
        ],
        [
            'category' => tr('nav.planning', $locale),
            'audience' => tr('nav.planning', $locale),
            'title' => tr('nav.pendents', $locale) . ' / ' . tr('nav.calendar', $locale),
            'summary' => tr('help.flow.follow.text', $locale),
            'steps' => [tr('help.quick.track.body', $locale), tr('help.flow.follow.text', $locale), tr('help.status.jump', $locale) . ': ' . tr('nav.calendar', $locale)],
            'tips' => [tr('dashboard.next_body', $locale)],
            'links' => [[tr('nav.pendents', $locale), '/?page=pendents'], [tr('nav.calendar', $locale), '/?page=calendar']],
            'keywords' => 'pending pendents calendar agenda reminder ics',
        ],
        [
            'category' => tr('nav.reporting', $locale),
            'audience' => tr('nav.reporting', $locale),
            'title' => tr('nav.reports', $locale),
            'summary' => tr('help.flow.dossier.text', $locale),
            'steps' => [tr('help.flow.dossier.text', $locale), tr('help.quick.track.body', $locale), tr('help.license_body2', $locale)],
            'tips' => [tr('help.search_status_initial', $locale)],
            'links' => [[tr('nav.reports', $locale), '/?page=reports']],
            'keywords' => 'reports reporting pdf export tables filters',
        ],
        [
            'category' => tr('nav.account', $locale),
            'audience' => tr('support.admin', $locale),
            'title' => tr('nav.admin_users', $locale),
            'summary' => tr('support.granted_hint', $locale),
            'steps' => [tr('support.granted', $locale), tr('support.admin', $locale), tr('support.stop', $locale)],
            'tips' => [tr('help.license_body2', $locale)],
            'links' => [[tr('nav.admin_users', $locale), '/?page=admin_users'], [tr('nav.admin_job_platforms', $locale), '/?page=admin_job_platforms']],
            'keywords' => 'admin users support security two factor password',
        ],
        [
            'category' => tr('help.license_eyebrow', $locale),
            'audience' => tr('nav.help', $locale),
            'title' => tr('help.license_title', $locale),
            'summary' => tr('help.license_body1', $locale),
            'steps' => [tr('help.license_body1', $locale), tr('help.license_body2', $locale)],
            'tips' => [tr('help.license_badge', $locale)],
            'links' => [[tr('nav.about', $locale), '/?page=about']],
            'keywords' => 'license privacy support',
        ],
    ];
}

function localizedContextHelpTopics(string $locale): array
{
    $locale = normalizeLocale($locale);
    $topics = localizedHelpTopics($locale);
    $findTopic = static function (string $keyword) use ($topics): array {
        foreach ($topics as $topic) {
            if (str_contains((string) ($topic['keywords'] ?? ''), $keyword)) {
                return $topic;
            }
        }
        return $topics[0] ?? ['title' => '', 'summary' => '', 'steps' => [], 'tips' => []];
    };
    $fromTopic = static function (array $topic, string $linkLabel, string $href): array {
        return [
            'title' => (string) ($topic['title'] ?? ''),
            'intro' => (string) ($topic['summary'] ?? ''),
            'steps' => array_values(array_slice((array) ($topic['steps'] ?? []), 0, 4)),
            'tips' => array_values(array_slice((array) ($topic['tips'] ?? []), 0, 2)),
            'link' => [$linkLabel, $href],
        ];
    };
    $profile = $findTopic('profile');
    $search = $findTopic('search');
    $apply = $findTopic('application');
    $follow = $findTopic('follow');
    $dossier = $findTopic('dossier');
    $license = $findTopic('license');

    return [
        'dashboard' => [
            'title' => tr('nav.dashboard', $locale),
            'intro' => tr('dashboard.next_body', $locale),
            'steps' => [tr('help.quick.track.body', $locale), tr('help.quick.search.body', $locale), tr('help.quick.apply.body', $locale)],
            'tips' => [tr('help.search_status_initial', $locale)],
            'link' => [tr('context.all_topics', $locale), '/?page=help'],
        ],
        'profile' => $fromTopic($profile, tr('nav.profile', $locale), '/?page=profile'),
        'documents' => [
            'title' => tr('nav.documents', $locale),
            'intro' => tr('help.flow.profile.text', $locale),
            'steps' => [tr('help.flow.profile.text', $locale), tr('help.quick.apply.body', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('profile.app_language_hint', $locale)],
            'link' => [tr('nav.documents', $locale), '/?page=documents'],
        ],
        'job_platform_search' => $fromTopic($search, tr('help.quick.search.link', $locale), '/?page=job_platform_search'),
        'jobs' => [
            'title' => tr('nav.jobs', $locale),
            'intro' => tr('help.flow.import.text', $locale),
            'steps' => [tr('help.quick.search.body', $locale), tr('help.flow.import.text', $locale), tr('help.flow.apply.text', $locale)],
            'tips' => [tr('help.search_status_initial', $locale)],
            'link' => [tr('nav.jobs', $locale), '/?page=jobs'],
        ],
        'applications' => $fromTopic($apply, tr('help.quick.apply.link', $locale), '/?page=applications'),
        'companies' => [
            'title' => tr('nav.companies', $locale),
            'intro' => tr('help.flow.import.text', $locale),
            'steps' => [tr('help.flow.import.text', $locale), tr('help.flow.follow.text', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('help.quick.track.body', $locale)],
            'link' => [tr('nav.companies', $locale), '/?page=companies'],
        ],
        'contacts' => [
            'title' => tr('nav.contacts', $locale),
            'intro' => tr('help.flow.follow.text', $locale),
            'steps' => [tr('help.quick.track.body', $locale), tr('help.flow.follow.text', $locale), tr('help.flow.dossier.text', $locale)],
            'tips' => [tr('dashboard.next_body', $locale)],
            'link' => [tr('nav.contacts', $locale), '/?page=contacts'],
        ],
        'pendents' => $fromTopic($follow, tr('nav.pendents', $locale), '/?page=pendents'),
        'calendar' => $fromTopic($follow, tr('nav.calendar', $locale), '/?page=calendar&view=agenda'),
        'reports' => $fromTopic($dossier, tr('nav.reports', $locale), '/?page=reports'),
        'admin_users' => [
            'title' => tr('nav.admin_users', $locale),
            'intro' => tr('support.granted_hint', $locale),
            'steps' => [tr('support.granted', $locale), tr('support.admin', $locale), tr('support.stop', $locale)],
            'tips' => [tr('help.license_body2', $locale)],
            'link' => [tr('nav.admin_users', $locale), '/?page=admin_users'],
        ],
        'admin_job_platforms' => [
            'title' => tr('nav.admin_job_platforms', $locale),
            'intro' => tr('help.flow.search.text', $locale),
            'steps' => [tr('help.flow.search.text', $locale), tr('help.quick.search.body', $locale), tr('help.flow.import.text', $locale)],
            'tips' => [tr('help.search_status_initial', $locale)],
            'link' => [tr('nav.admin_job_platforms', $locale), '/?page=admin_job_platforms'],
        ],
        'privacy' => $fromTopic($license, tr('nav.privacy', $locale), '/?page=privacy'),
        'sharing' => $fromTopic($license, tr('nav.sharing', $locale), '/?page=sharing'),
    ];
}

function redirect(string $path = '/'): never
{
    header('Location: ' . $path);
    exit;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(): void
{
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function userId(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function realUserId(): int
{
    return (int) ($_SESSION['support_admin_user_id'] ?? $_SESSION['user_id'] ?? 0);
}

function sessionPresenceHash(): string
{
    return hash('sha256', session_id());
}

function requestHash(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : hash('sha256', $value);
}

function touchUserPresence(mysqli $db, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $sessionHash = sessionPresenceHash();
        $ipHash = requestHash((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $userAgentHash = requestHash((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_hash, ip_hash, user_agent_hash, first_seen_at, last_seen_at, logged_out_at)
            VALUES (?, ?, ?, ?, NOW(), NOW(), NULL)
            ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), ip_hash=VALUES(ip_hash), user_agent_hash=VALUES(user_agent_hash), last_seen_at=NOW(), logged_out_at=NULL");
        $stmt->bind_param('isss', $userId, $sessionHash, $ipHash, $userAgentHash);
        $stmt->execute();
        $db->query('UPDATE users SET last_seen_at = NOW() WHERE id = ' . $userId);
    } catch (Throwable $exception) {
        // Praesenzinformationen sind nur informativ und duerfen produktive Arbeit nie blockieren.
    }
}

function endUserPresenceSession(mysqli $db, int $userId): void
{
    if ($userId <= 0) {
        return;
    }
    try {
        $sessionHash = sessionPresenceHash();
        $stmt = $db->prepare('UPDATE user_sessions SET logged_out_at=NOW(), last_seen_at=NOW() WHERE user_id=? AND session_hash=?');
        $stmt->bind_param('is', $userId, $sessionHash);
        $stmt->execute();
        $db->query('UPDATE users SET last_seen_at = NOW() WHERE id = ' . $userId);
    } catch (Throwable $exception) {
        // Abmelden muss weiterlaufen, auch wenn die Praesenzbereinigung nicht verfuegbar ist.
    }
}

function isSupportImpersonation(): bool
{
    return !empty($_SESSION['support_admin_user_id']) && !empty($_SESSION['support_target_user_id']);
}

function endSupportImpersonationSession(): void
{
    if (!empty($_SESSION['support_admin_user_id'])) {
        $_SESSION['user_id'] = (int) $_SESSION['support_admin_user_id'];
    }
    unset($_SESSION['support_admin_user_id'], $_SESSION['support_admin_name'], $_SESSION['support_target_user_id'], $_SESSION['support_target_name']);
}

function requireLogin(): void
{
    if (userId() < 1) {
        redirect('/?page=login');
    }
}

function clearAuthenticatedSession(): void
{
    unset(
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $_SESSION['support_admin_user_id'],
        $_SESSION['support_admin_name'],
        $_SESSION['support_target_user_id'],
        $_SESSION['support_target_name']
    );
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function outboundEmailEnabled(array $config): bool
{
    return !empty($config['smtp_enabled'])
        && trim((string) ($config['smtp_host'] ?? '')) !== ''
        && trim((string) ($config['mail_from'] ?? '')) !== '';
}

function secretKey(array $config): string
{
    $seed = (string) ($config['app_key'] ?? $config['app_secret'] ?? $config['db_password'] ?? '');
    if ($seed === '') {
        $seed = 'jema-jobs-local-prototype';
    }
    return hash('sha256', $seed, true);
}

function encryptSecret(array $config, string $plain): ?string
{
    if ($plain === '') {
        return null;
    }
    if (!function_exists('openssl_encrypt')) {
        throw new RuntimeException('OpenSSL ist für SMTP-Passwörter nicht verfügbar.');
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', secretKey($config), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('SMTP-Passwort konnte nicht verschlüsselt werden.');
    }
    return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
}

function decryptSecret(array $config, ?string $stored): string
{
    $stored = (string) $stored;
    if ($stored === '') {
        return '';
    }
    $parts = explode(':', $stored, 4);
    if (count($parts) !== 4 || $parts[0] !== 'v1' || !function_exists('openssl_decrypt')) {
        return '';
    }
    $plain = openssl_decrypt(
        base64_decode($parts[3], true) ?: '',
        'aes-256-gcm',
        secretKey($config),
        OPENSSL_RAW_DATA,
        base64_decode($parts[1], true) ?: '',
        base64_decode($parts[2], true) ?: ''
    );
    return $plain === false ? '' : $plain;
}

function appUrl(array $config): string
{
    return rtrim((string) ($config['app_url'] ?? ''), '/');
}

function absoluteUrl(array $config, string $path): string
{
    return (appUrl($config) ?: '') . $path;
}

function mailFromName(array $config): string
{
    return trim((string) ($config['mail_from_name'] ?? $config['app_name'] ?? 'JeMa Jobs'));
}

function encodeMailHeader(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
        return $value;
    }
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtpReadResponse($stream, array $acceptedCodes): string
{
    $response = '';
    do {
        $line = fgets($stream, 2048);
        if ($line === false) {
            throw new RuntimeException('SMTP-Server hat die Verbindung unerwartet beendet.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $acceptedCodes, true)) {
        throw new RuntimeException('SMTP-Fehler: ' . trim($response));
    }
    return $response;
}

function smtpCommand($stream, string $command, array $acceptedCodes): string
{
    if (fwrite($stream, $command . "\r\n") === false) {
        throw new RuntimeException('SMTP-Befehl konnte nicht gesendet werden.');
    }
    return smtpReadResponse($stream, $acceptedCodes);
}

function dotStuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $body = str_replace("\n.", "\n..", $body);
    if (str_starts_with($body, '.')) {
        $body = '.' . $body;
    }
    return str_replace("\n", "\r\n", $body);
}

function sendSmtpMail(array $config, string $to, string $subject, string $textBody, array $attachments = []): void
{
    $host = trim((string) ($config['smtp_host'] ?? ''));
    $from = trim((string) ($config['mail_from'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || $host === '') {
        throw new RuntimeException('SMTP-Konfiguration oder Empfängeradresse ist ungültig.');
    }

    $encryption = strtolower(trim((string) ($config['smtp_encryption'] ?? 'tls')));
    $port = (int) ($config['smtp_port'] ?? ($encryption === 'ssl' ? 465 : 587));
    $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $stream = @stream_socket_client($target, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
    if (!is_resource($stream)) {
        throw new RuntimeException('SMTP-Verbindung fehlgeschlagen: ' . $errstr);
    }
    stream_set_timeout($stream, 30);

    try {
        smtpReadResponse($stream, [220]);
        $domain = parse_url(appUrl($config), PHP_URL_HOST) ?: ($_SERVER['SERVER_NAME'] ?? 'localhost');
        smtpCommand($stream, 'EHLO ' . $domain, [250]);
        if ($encryption === 'tls') {
            smtpCommand($stream, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS konnte nicht aktiviert werden.');
            }
            smtpCommand($stream, 'EHLO ' . $domain, [250]);
        }

        $username = (string) ($config['smtp_username'] ?? '');
        if ($username !== '') {
            smtpCommand($stream, 'AUTH LOGIN', [334]);
            smtpCommand($stream, base64_encode($username), [334]);
            smtpCommand($stream, base64_encode((string) ($config['smtp_password'] ?? '')), [235]);
        }

        smtpCommand($stream, 'MAIL FROM:<' . $from . '>', [250]);
        smtpCommand($stream, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtpCommand($stream, 'DATA', [354]);

        $fromHeader = encodeMailHeader(mailFromName($config)) . ' <' . $from . '>';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . encodeMailHeader($subject),
            'MIME-Version: 1.0',
        ];
        if ($attachments) {
            $boundary = 'jema_jobs_' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $message = '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . $textBody . "\r\n";
            foreach ($attachments as $attachment) {
                $path = (string) ($attachment['path'] ?? '');
                if (!is_file($path)) {
                    continue;
                }
                $filename = basename((string) ($attachment['filename'] ?? basename($path)));
                $mime = (string) ($attachment['mime'] ?? 'application/octet-stream');
                $message .= '--' . $boundary . "\r\n"
                    . 'Content-Type: ' . $mime . '; name="' . addslashes($filename) . '"' . "\r\n"
                    . "Content-Transfer-Encoding: base64\r\n"
                    . 'Content-Disposition: attachment; filename="' . addslashes($filename) . '"' . "\r\n\r\n"
                    . chunk_split(base64_encode((string) file_get_contents($path))) . "\r\n";
            }
            $message .= '--' . $boundary . "--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $message = $textBody;
        }
        smtpCommand($stream, implode("\r\n", $headers) . "\r\n\r\n" . dotStuff($message) . "\r\n.", [250]);
        smtpCommand($stream, 'QUIT', [221]);
    } finally {
        fclose($stream);
    }
}

function userSmtpSettings(mysqli $db, array $config, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    try {
        $settings = dbOne($db, 'SELECT * FROM user_smtp_settings WHERE user_id=? AND is_active=1 LIMIT 1', 'i', [$userId]);
    } catch (Throwable) {
        return null;
    }
    if (!$settings || trim((string) ($settings['smtp_host'] ?? '')) === '' || trim((string) ($settings['from_email'] ?? '')) === '') {
        return null;
    }
    return [
        'app_url' => $config['app_url'] ?? '',
        'app_name' => $config['app_name'] ?? 'JeMa Jobs',
        'smtp_enabled' => true,
        'smtp_host' => (string) $settings['smtp_host'],
        'smtp_port' => (int) $settings['smtp_port'],
        'smtp_encryption' => (string) $settings['smtp_encryption'],
        'smtp_username' => (string) ($settings['smtp_username'] ?? ''),
        'smtp_password' => decryptSecret($config, $settings['smtp_password_encrypted'] ?? null),
        'mail_from' => (string) $settings['from_email'],
        'mail_from_name' => trim((string) ($settings['from_name'] ?? '')) ?: mailFromName($config),
    ];
}

function smtpConfigForOwner(mysqli $db, array $config, int $ownerUserId): ?array
{
    return userSmtpSettings($db, $config, $ownerUserId);
}

function mailEnabledForUser(mysqli $db, array $config, int $ownerUserId): bool
{
    return smtpConfigForOwner($db, $config, $ownerUserId) !== null;
}

function logOutboundEmail(mysqli $db, int $userId, string $recipient, string $subject, string $body, string $status, ?string $error = null): void
{
    try {
        $sentAt = $status === 'sent' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare('INSERT INTO outbound_emails (owner_user_id, recipient_email, subject, body_text, status, sent_at, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssss', $userId, $recipient, $subject, $body, $status, $sentAt, $error);
        $stmt->execute();
    } catch (Throwable) {
        // Mail-Protokollierung darf die Kontowiederherstellung nie blockieren.
    }
}

function sendConfiguredMail(mysqli $db, array $config, int $ownerUserId, string $to, string $subject, string $body, array $attachments = []): bool
{
    $mailConfig = smtpConfigForOwner($db, $config, $ownerUserId);
    if (!$mailConfig) {
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'draft');
        return false;
    }
    try {
        sendSmtpMail($mailConfig, $to, $subject, $body, $attachments);
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'sent');
        return true;
    } catch (Throwable $exception) {
        logOutboundEmail($db, $ownerUserId, $to, $subject, $body, 'failed', $exception->getMessage());
        throw $exception;
    }
}

function base32Encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (unpack('C*', $bytes) as $byte) {
        $bits .= str_pad(decbin((int) $byte), 8, '0', STR_PAD_LEFT);
    }
    $encoded = '';
    foreach (str_split($bits, 5) as $chunk) {
        $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }
    return $encoded;
}

function base32Decode(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
    $bits = '';
    foreach (str_split($clean) as $char) {
        $pos = strpos($alphabet, $char);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr(bindec($chunk));
        }
    }
    return $bytes;
}

function generateTotpSecret(): string
{
    return base32Encode(random_bytes(20));
}

function totpCode(string $secret, ?int $time = null): string
{
    $counter = intdiv($time ?? time(), 30);
    $binaryCounter = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
    $hash = hash_hmac('sha1', $binaryCounter, base32Decode($secret), true);
    $offset = ord($hash[19]) & 0x0f;
    $value = ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }
    $now = time();
    foreach ([-30, 0, 30] as $offset) {
        if (hash_equals(totpCode($secret, $now + $offset), $code)) {
            return true;
        }
    }
    return false;
}

function totpUri(array $config, array $user, string $secret): string
{
    $issuer = rawurlencode((string) ($config['app_name'] ?? 'JeMa Jobs'));
    $label = rawurlencode(($config['app_name'] ?? 'JeMa Jobs') . ':' . (string) $user['email']);
    return 'otpauth://totp/' . $label . '?secret=' . rawurlencode($secret) . '&issuer=' . $issuer . '&algorithm=SHA1&digits=6&period=30';
}

function activeTotpMethod(mysqli $db, int $userId): ?array
{
    return dbOne($db, "SELECT * FROM two_factor_methods WHERE user_id=? AND method='totp' AND verified_at IS NOT NULL ORDER BY is_primary DESC, id DESC LIMIT 1", 'i', [$userId]);
}

function shareToken(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function shareTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function deviceHash(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500));
}

function activeGuestShare(mysqli $db, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    $share = dbOne(
        $db,
        'SELECT * FROM guest_shares WHERE token_hash=? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1',
        's',
        [shareTokenHash($token)]
    );
    if ($share) {
        $stmt = $db->prepare('UPDATE guest_shares SET last_accessed_at=NOW() WHERE id=?');
        $id = (int) $share['id'];
        $stmt->bind_param('i', $id);
        $stmt->execute();
    }
    return $share;
}

function shareAllowsTarget(array $share, string $targetType, int $targetId): bool
{
    if (($share['target_type'] ?? '') === 'area') {
        return true;
    }
    return ($share['target_type'] ?? '') === $targetType && (int) ($share['target_id'] ?? 0) === $targetId;
}

function storageUsageBytes(mysqli $db, int $userId): int
{
    $documents = dbOne($db, 'SELECT COALESCE(SUM(file_size),0) total FROM user_documents WHERE user_id=? AND deleted_at IS NULL', 'i', [$userId]);
    $generated = dbOne($db, 'SELECT COALESCE(SUM(file_size),0) total FROM generated_files WHERE owner_user_id=? AND (expires_at IS NULL OR expires_at > NOW())', 'i', [$userId]);
    return (int) ($documents['total'] ?? 0) + (int) ($generated['total'] ?? 0);
}

function bytesLabel(int $bytes): string
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    return number_format($bytes / 1024, 1) . ' KB';
}

function cleanupPreview(mysqli $db, int $userId, string $cutoffDate): array
{
    $jobs = dbOne($db, "SELECT COUNT(*) c FROM jobs WHERE owner_user_id=? AND deleted_at IS NULL AND updated_at < ? AND status IN ('rejected','closed')", 'is', [$userId, $cutoffDate . ' 00:00:00']);
    $apps = dbOne($db, "SELECT COUNT(*) c FROM applications WHERE user_id=? AND deleted_at IS NULL AND updated_at < ? AND status IN ('rejected','withdrawn','closed')", 'is', [$userId, $cutoffDate . ' 00:00:00']);
    $documents = dbOne($db, "SELECT COUNT(*) c, COALESCE(SUM(file_size),0) bytes FROM user_documents WHERE user_id=? AND deleted_at IS NULL AND is_current=0 AND updated_at < ?", 'is', [$userId, $cutoffDate . ' 00:00:00']);
    return [
        'jobs' => (int) ($jobs['c'] ?? 0),
        'applications' => (int) ($apps['c'] ?? 0),
        'old_document_versions' => (int) ($documents['c'] ?? 0),
        'document_bytes' => (int) ($documents['bytes'] ?? 0),
    ];
}

function csvResponse(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

function pdfEscape(string $value): string
{
    $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
}

function pdfTextOperand(string $value): string
{
    $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
    if ($encoded === false) {
        $encoded = $value;
    }
    $out = '';
    $length = strlen($encoded);
    for ($i = 0; $i < $length; $i++) {
        $byte = ord($encoded[$i]);
        if ($byte === 40 || $byte === 41 || $byte === 92) {
            $out .= '\\' . chr($byte);
        } elseif ($byte < 32 || $byte > 126) {
            $out .= sprintf('\\%03o', $byte);
        } else {
            $out .= chr($byte);
        }
    }
    return '(' . $out . ')';
}

function pdfUiText(string $value, ?string $locale = null): string
{
    return $value;
}

function pdfResponse(string $filename, string $title, array $headers, array $rows): never
{
    global $appLocale;
    $pdfLocale = normalizeLocale((string) ($appLocale ?? 'de-CH'));
    $title = pdfUiText($title, $pdfLocale);
    $headers = array_map(static fn($header): string => pdfUiText((string) $header, $pdfLocale), $headers);
    $createdLabel = tr('common.created_at', $pdfLocale);
    $pageLabel = tr('common.page', $pdfLocale);
    $ofLabel = tr('common.of', $pdfLocale);
    $objects = [];
    $pages = [];
    $fontObjectNo = 3;

    $pageWidth = 842;
    $pageHeight = 595;
    $margin = 32;
    $tableWidth = $pageWidth - ($margin * 2);
    $headerHeight = 24;
    $rowHeight = 23;
    $tableTop = 500;
    $rowsPerPage = 18;
    $columnCount = max(1, count($headers));
    $weights = [];
    foreach ($headers as $index => $header) {
        $max = mb_strlen((string) $header);
        foreach (array_slice($rows, 0, 60) as $row) {
            $max = max($max, mb_strlen((string) ($row[$index] ?? '')));
        }
        $weights[] = max(5, min(26, $max));
    }
    $weightTotal = array_sum($weights) ?: $columnCount;
    $widths = [];
    $remaining = $tableWidth;
    foreach ($weights as $index => $weight) {
        $width = $index === $columnCount - 1 ? $remaining : round($tableWidth * ($weight / $weightTotal), 2);
        $width = max(42, $width);
        $widths[] = $width;
        $remaining -= $width;
    }
    if ($remaining < 0) {
        $scale = $tableWidth / array_sum($widths);
        foreach ($widths as $index => $width) {
            $widths[$index] = round($width * $scale, 2);
        }
    }

    $chunks = array_chunk($rows ?: [array_fill(0, $columnCount, '')], $rowsPerPage);
    $pageTotal = count($chunks);
    foreach ($chunks as $pageIndex => $chunk) {
        $content = "0.09 0.13 0.16 rg\nBT /F1 18 Tf {$margin} 548 Td " . pdfTextOperand($title) . " Tj ET\n";
        $content .= "0.39 0.45 0.48 rg\nBT /F1 8 Tf {$margin} 529 Td " . pdfTextOperand($createdLabel . ' ' . date('d.m.Y H:i') . ' | ' . $pageLabel . ' ' . ($pageIndex + 1) . ' ' . $ofLabel . ' ' . $pageTotal) . " Tj ET\n";

        $x = $margin;
        $headerY = $tableTop - $headerHeight;
        $content .= "0.91 0.94 0.95 rg {$margin} {$headerY} {$tableWidth} {$headerHeight} re f\n";
        $content .= "0.62 0.67 0.70 RG 0.7 w {$margin} {$headerY} {$tableWidth} {$headerHeight} re S\n";
        foreach ($headers as $index => $header) {
            $width = $widths[$index] ?? ($tableWidth / $columnCount);
            $text = mb_strimwidth((string) $header, 0, max(4, (int) floor(($width - 10) / 4.5)), '...');
            $content .= "0.09 0.13 0.16 rg\nBT /F1 8.5 Tf " . ($x + 5) . ' ' . ($headerY + 9) . ' Td ' . pdfTextOperand($text) . " Tj ET\n";
            if ($index > 0) {
                $content .= "0.62 0.67 0.70 RG {$x} {$headerY} m {$x} " . ($headerY + $headerHeight) . " l S\n";
            }
            $x += $width;
        }

        foreach ($chunk as $rowIndex => $row) {
            $rowY = $headerY - (($rowIndex + 1) * $rowHeight);
            $fill = $rowIndex % 2 === 0 ? '1 1 1' : '0.97 0.98 0.98';
            $content .= "{$fill} rg {$margin} {$rowY} {$tableWidth} {$rowHeight} re f\n";
            $content .= "0.82 0.85 0.86 RG 0.45 w {$margin} {$rowY} {$tableWidth} {$rowHeight} re S\n";
            $x = $margin;
            for ($index = 0; $index < $columnCount; $index++) {
                $width = $widths[$index] ?? ($tableWidth / $columnCount);
                $value = is_array($row) ? ($row[$index] ?? '') : '';
                $text = mb_strimwidth(pdfUiText((string) $value, $pdfLocale), 0, max(4, (int) floor(($width - 10) / 4.1)), '...');
                if ($index > 0) {
                    $content .= "0.82 0.85 0.86 RG {$x} {$rowY} m {$x} " . ($rowY + $rowHeight) . " l S\n";
                }
                $content .= "0.10 0.13 0.16 rg\nBT /F1 8 Tf " . ($x + 5) . ' ' . ($rowY + 8) . ' Td ' . pdfTextOperand($text) . " Tj ET\n";
                $x += $width;
            }
        }
        $contentNo = count($objects) + 4;
        $pageNo = $contentNo + 1;
        $objects[$contentNo] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        $objects[$pageNo] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 {$fontObjectNo} 0 R >> >> /Contents {$contentNo} 0 R >>";
        $pages[] = $pageNo . ' 0 R';
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pages) . '] /Count ' . count($pages) . ' >>';
    $objects[$fontObjectNo] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= max(array_keys($objects)); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function pdfTextResponse(string $filename, string $title, array $sections): never
{
    global $appLocale;
    $pdfLocale = normalizeLocale((string) ($appLocale ?? 'de-CH'));
    $title = pdfUiText($title, $pdfLocale);
    $createdLabel = tr('common.created_at', $pdfLocale);
    $pageLabel = tr('common.page', $pdfLocale);
    $pageWidth = 595;
    $pageHeight = 842;
    $margin = 42;
    $lineHeight = 13;
    $fontObjectNo = 3;
    $objects = [];
    $pages = [];
    $pageContents = [];
    $content = '';
    $pageNo = 1;
    $y = 792;
    $lineWidth = 96;

    $newPage = function () use (&$content, &$pageContents, &$pageNo, &$y, $pageWidth, $pageHeight, $margin, $title, $createdLabel, $pageLabel): void {
        if ($content !== '') {
            $pageContents[] = $content;
        }
        $content = "0.09 0.13 0.16 rg\nBT /F1 16 Tf {$margin} 806 Td " . pdfTextOperand($title) . " Tj ET\n";
        $content .= "0.45 0.50 0.54 rg\nBT /F1 8 Tf {$margin} 788 Td " . pdfTextOperand($createdLabel . ' ' . date('d.m.Y H:i') . ' | ' . $pageLabel . ' ' . $pageNo) . " Tj ET\n";
        $content .= "0.78 0.81 0.84 RG 0.6 w {$margin} 778 m " . ($pageWidth - $margin) . " 778 l S\n";
        $y = 758;
        $pageNo++;
    };
    $addLine = function (string $text, int $size = 9, bool $heading = false) use (&$content, &$y, $lineHeight, $margin, $newPage): void {
        if ($y < 52) {
            $newPage();
        }
        $fontSize = $heading ? 12 : $size;
        $color = $heading ? '0.77 0.20 0.00' : '0.10 0.13 0.16';
        $content .= "{$color} rg\nBT /F1 {$fontSize} Tf {$margin} {$y} Td " . pdfTextOperand($text) . " Tj ET\n";
        $y -= $heading ? 18 : $lineHeight;
    };
    $newPage();
    foreach ($sections as $sectionTitle => $lines) {
        $addLine(pdfUiText((string) $sectionTitle, $pdfLocale), 12, true);
        foreach ((array) $lines as $line) {
            $parts = preg_split('/\R/u', pdfUiText((string) $line, $pdfLocale)) ?: [''];
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part === '') {
                    $addLine('');
                    continue;
                }
                foreach (explode("\n", wordwrap($part, $lineWidth, "\n", true)) as $wrappedLine) {
                    if ($wrappedLine !== '') {
                        $addLine($wrappedLine);
                    }
                }
            }
        }
        $addLine('');
    }
    if ($content !== '') {
        $pageContents[] = $content;
    }
    foreach ($pageContents as $pageContent) {
        $contentNo = count($objects) + 4;
        $pageObjNo = $contentNo + 1;
        $objects[$contentNo] = "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream";
        $objects[$pageObjNo] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /Font << /F1 {$fontObjectNo} 0 R >> >> /Contents {$contentNo} 0 R >>";
        $pages[] = $pageObjNo . ' 0 R';
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pages) . '] /Count ' . count($pages) . ' >>';
    $objects[$fontObjectNo] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $number => $body) {
        $offsets[$number] = strlen($pdf);
        $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (max(array_keys($objects)) + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= max(array_keys($objects)); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
    }
    $pdf .= "trailer\n<< /Size " . (max(array_keys($objects)) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

function sfSessionKey(string $context): string
{
    return 'sf_' . preg_replace('/[^a-z0-9_]/i', '_', $context);
}

function sfState(string $context, array $fields, array $defaults = []): array
{
    $key = sfSessionKey($context);
    $_SESSION[$key] ??= [
        'filters' => [],
        'sort' => [
            'field' => (string) ($defaults['sort'] ?? array_key_first($fields)),
            'dir' => strtolower((string) ($defaults['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ],
    ];
    $state = is_array($_SESSION[$key]) ? $_SESSION[$key] : ['filters' => [], 'sort' => []];
    $state['filters'] = is_array($state['filters'] ?? null) ? $state['filters'] : [];
    $state['sort'] = is_array($state['sort'] ?? null) ? $state['sort'] : [];

    if ((string) ($_GET['sf_context'] ?? '') === $context) {
        if (!empty($_GET['sf_reset'])) {
            $state = ['filters' => [], 'sort' => ['field' => (string) ($defaults['sort'] ?? array_key_first($fields)), 'dir' => strtolower((string) ($defaults['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc']];
        } else {
            $field = (string) ($_GET['sf_field'] ?? '');
            if (isset($fields[$field])) {
                if (!empty($_GET['sf_clear_filter'])) {
                    unset($state['filters'][$field]);
                } elseif (isset($fields[$field]['choices'])) {
                    $allowed = array_map('strval', array_keys((array) $fields[$field]['choices']));
                    $selected = array_values(array_intersect(array_map('strval', (array) ($_GET['sf_filter_multi'] ?? [])), $allowed));
                    if ($selected) {
                        $state['filters'][$field] = $selected;
                    } else {
                        unset($state['filters'][$field]);
                    }
                } else {
                    $filter = trim((string) ($_GET['sf_filter'] ?? ''));
                    if ($filter === '') {
                        unset($state['filters'][$field]);
                    } else {
                        $state['filters'][$field] = $filter;
                    }
                }
                $sort = strtolower((string) ($_GET['sf_sort'] ?? ''));
                if (in_array($sort, ['asc', 'desc'], true)) {
                    $state['sort'] = ['field' => $field, 'dir' => $sort];
                } elseif ($sort === 'none' && (string) ($state['sort']['field'] ?? '') === $field) {
                    $state['sort'] = [];
                }
            }
        }
        $_SESSION[$key] = $state;
    }

    $state['filters'] = array_intersect_key($state['filters'], $fields);
    if ($state['sort'] !== [] && !isset($fields[(string) ($state['sort']['field'] ?? '')])) {
        $state['sort'] = [
            'field' => (string) ($defaults['sort'] ?? array_key_first($fields)),
            'dir' => strtolower((string) ($defaults['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
    if ($state['sort'] !== [] && !in_array(strtolower((string) ($state['sort']['dir'] ?? 'asc')), ['asc', 'desc'], true)) {
        $state['sort']['dir'] = 'asc';
    }
    $_SESSION[$key] = $state;
    $state['_fields'] = $fields;
    return $state;
}

function sfApplySql(array $state, array $fields, string &$types, array &$values): string
{
    $clauses = [];
    foreach ((array) ($state['filters'] ?? []) as $field => $filter) {
        if (!isset($fields[$field])) {
            continue;
        }
        $expr = (string) ($fields[$field]['expr'] ?? '');
        if ($expr === '') {
            continue;
        }
        if (isset($fields[$field]['choices'])) {
            $selected = array_values(array_intersect(array_map('strval', (array) $filter), array_map('strval', array_keys((array) $fields[$field]['choices']))));
            if (!$selected) {
                continue;
            }
            $clauses[] = $expr . ' IN (' . implode(',', array_fill(0, count($selected), '?')) . ')';
            $types .= str_repeat('s', count($selected));
            array_push($values, ...$selected);
        } else {
            $filter = trim((string) $filter);
            if ($filter === '') {
                continue;
            }
            $clauses[] = $expr . ' LIKE ?';
            $types .= 's';
            $values[] = '%' . $filter . '%';
        }
    }
    return $clauses ? ' AND ' . implode(' AND ', $clauses) : '';
}

function sfOrderSql(array $state, array $fields, string $fallbackField): string
{
    $field = (string) ($state['sort']['field'] ?? $fallbackField);
    if (($state['sort'] ?? []) === []) {
        return '';
    }
    if (!isset($fields[$field])) {
        $field = $fallbackField;
    }
    $expr = (string) ($fields[$field]['expr'] ?? '');
    if ($expr === '') {
        return '';
    }
    $dir = strtolower((string) ($state['sort']['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    return ' ORDER BY ' . $expr . ' ' . $dir;
}

function sfApplyRows(array $rows, array $state, array $fields): array
{
    $filters = (array) ($state['filters'] ?? []);
    if ($filters) {
        $rows = array_values(array_filter($rows, static function (array $row) use ($filters, $fields): bool {
            foreach ($filters as $field => $filter) {
                if (!isset($fields[$field])) {
                    continue;
                }
                if (isset($fields[$field]['choices'])) {
                    $selected = array_values(array_intersect(array_map('strval', (array) $filter), array_map('strval', array_keys((array) $fields[$field]['choices']))));
                    if ($selected && !in_array((string) ($row[$field] ?? ''), $selected, true)) {
                        return false;
                    }
                    continue;
                }
                $filter = mb_strtolower(trim((string) $filter));
                if ($filter !== '' && !str_contains(mb_strtolower((string) ($row[$field] ?? '')), $filter)) {
                    return false;
                }
            }
            return true;
        }));
    }
    $sortField = (string) ($state['sort']['field'] ?? '');
    $dir = strtolower((string) ($state['sort']['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    if ($sortField !== '' && isset($fields[$sortField])) {
        usort($rows, static function (array $a, array $b) use ($sortField, $dir): int {
            $result = strnatcasecmp((string) ($a[$sortField] ?? ''), (string) ($b[$sortField] ?? ''));
            return $dir === 'desc' ? -$result : $result;
        });
    }
    return $rows;
}

function sfHiddenInputs(array $preserve): string
{
    $html = '';
    foreach ($preserve as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $html .= '<input type="hidden" name="' . e((string) $name) . '" value="' . e((string) $value) . '">';
    }
    return $html;
}

function sfHeader(string $context, string $field, string $label, array $state, array $preserve = [], array $fields = []): string
{
    $fieldDef = $fields[$field] ?? [];
    if (!$fieldDef && isset($state['_fields'][$field])) {
        $fieldDef = (array) $state['_fields'][$field];
    }
    $rawFilter = $state['filters'][$field] ?? '';
    $currentFilter = isset($fieldDef['choices']) ? array_values(array_map('strval', (array) $rawFilter)) : (string) $rawFilter;
    $isSort = (string) ($state['sort']['field'] ?? '') === $field;
    $sortDir = $isSort ? strtolower((string) ($state['sort']['dir'] ?? 'asc')) : '';
    $hasFilter = is_array($currentFilter) ? $currentFilter !== [] : $currentFilter !== '';
    $active = $hasFilter || $isSort;
    $summary = '<summary class="sf-button ' . ($active ? 'is-active' : '') . '" title="' . e(tr('sf.title')) . '">' . ($isSort ? ($sortDir === 'desc' ? 'v' : '^') : '=') . ($hasFilter ? '*' : '') . '</summary>';
    $html = '<th><div class="sf-head"><span>' . e($label) . '</span><details class="sf-menu">' . $summary;
    $html .= '<form method="get" class="sf-form">' . sfHiddenInputs($preserve);
    $html .= '<input type="hidden" name="sf_context" value="' . e($context) . '"><input type="hidden" name="sf_field" value="' . e($field) . '">';
    if (isset($fieldDef['choices'])) {
        $html .= '<fieldset class="sf-multi"><legend>' . e(tr('sf.filter')) . '</legend>';
        foreach ((array) $fieldDef['choices'] as $value => $choiceLabel) {
            $checked = in_array((string) $value, $currentFilter, true) ? ' checked' : '';
            $html .= '<label><input type="checkbox" name="sf_filter_multi[]" value="' . e((string) $value) . '"' . $checked . '> ' . e((string) $choiceLabel) . '</label>';
        }
        $html .= '</fieldset>';
    } else {
        $html .= '<label>' . e(tr('sf.filter')) . '<input name="sf_filter" value="' . e((string) $currentFilter) . '" placeholder="' . e(tr('sf.contains_placeholder', null, ['field' => $label])) . '"></label>';
    }
    $html .= '<label>' . e(tr('sf.sorting')) . '<select name="sf_sort"><option value="none">' . e(tr('sf.none')) . '</option><option value="asc" ' . ($sortDir === 'asc' ? 'selected' : '') . '>' . e(tr('sf.asc')) . '</option><option value="desc" ' . ($sortDir === 'desc' ? 'selected' : '') . '>' . e(tr('sf.desc')) . '</option></select></label>';
    $html .= '<div class="actions"><button class="primary">' . e(tr('sf.apply')) . '</button><button name="sf_clear_filter" value="1">' . e(tr('sf.clear_filter')) . '</button></div>';
    $html .= '</form></details></div></th>';
    return $html;
}

function sfToolbar(string $context, array $state, array $preserve = [], array $fields = []): string
{
    $count = count((array) ($state['filters'] ?? []));
    $sort = (array) ($state['sort'] ?? []);
    $label = $count > 0 ? tr('sf.active_filters', null, ['count' => (string) $count]) : tr('sf.no_field_filters');
    if (!empty($sort['field'])) {
        $fieldLabel = (string) ($fields[(string) $sort['field']]['label'] ?? $sort['field']);
        $label .= ' · ' . tr('sf.sort_summary', null, ['field' => $fieldLabel, 'direction' => strtolower((string) ($sort['dir'] ?? 'asc')) === 'desc' ? tr('sf.desc_lower') : tr('sf.asc_lower')]);
    }
    return '<form method="get" class="sf-toolbar">' . sfHiddenInputs($preserve) . '<input type="hidden" name="sf_context" value="' . e($context) . '"><input type="hidden" name="sf_reset" value="1"><span>' . e($label) . '</span><button>' . e(tr('sf.reset')) . '</button></form>';
}

function reportBaseOptions(): array
{
    return ['jobs'=>tr('nav.jobs'),'applications'=>tr('nav.applications'),'companies'=>tr('nav.companies'),'contacts'=>tr('nav.contacts'),'documents'=>tr('nav.documents'),'calendar'=>tr('nav.calendar')];
}

function reportDisplayOptions(): array
{
    return ['table'=>tr('common.table'),'list'=>tr('common.list'),'cards'=>tr('common.cards'),'preview'=>tr('common.preview'),'calendar_day'=>tr('calendar.day_view'),'calendar_week'=>tr('calendar.week_view'),'calendar_month'=>tr('calendar.month_view')];
}

function reportOpenUrl(array $report): string
{
    $base = (string) ($report['base_entity'] ?? 'jobs');
    $display = (string) ($report['display_type'] ?? 'table');
    $page = ['jobs'=>'jobs','applications'=>'applications','companies'=>'companies','contacts'=>'contacts','documents'=>'documents','calendar'=>'calendar'][$base] ?? 'jobs';
    $params = ['page' => $page];
    if (in_array($page, ['jobs', 'applications'], true) && in_array($display, ['cards', 'table'], true)) {
        $params['view'] = $display;
    }
    return '/?' . http_build_query($params);
}

function reportExportType(array $report): ?string
{
    $base = (string) ($report['base_entity'] ?? '');
    return in_array($base, ['jobs', 'applications', 'companies', 'contacts', 'documents'], true) ? $base : null;
}

function reportFieldOptions(string $base): array
{
    return match ($base) {
        'applications' => ['title'=>tr('reports.field.job'),'company'=>tr('companies.company'),'status'=>tr('common.status'),'channel'=>tr('applications.channel'),'application_url'=>tr('applications.online_url'),'reference_number'=>tr('applications.reference_number'),'applied_at'=>tr('applications.sent_at'),'next_action'=>tr('applications.next_action'),'next_action_at'=>tr('common.due')],
        'companies' => ['name'=>tr('companies.company'),'city'=>tr('companies.city'),'website'=>tr('companies.website'),'is_intermediary'=>tr('companies.intermediary'),'updated_at'=>tr('common.updated')],
        'contacts' => ['name'=>tr('contacts.contact'),'company'=>tr('companies.company'),'email'=>tr('auth.email'),'phone'=>tr('profile.phone'),'position'=>tr('contacts.position'),'open_logs'=>tr('applications.next_action'),'updated_at'=>tr('common.updated')],
        'documents' => ['title'=>tr('documents.document'),'type'=>tr('documents.type'),'filename'=>tr('documents.file'),'scope'=>tr('common.area'),'version'=>tr('documents.version'),'created_at'=>tr('common.created')],
        'calendar' => ['starts_at'=>tr('common.time'),'title'=>tr('calendar.event'),'type'=>tr('documents.type'),'status'=>tr('common.status'),'meta'=>tr('calendar.reference')],
        default => ['title'=>tr('common.title'),'company'=>tr('companies.company'),'location'=>tr('jobs.location'),'status'=>tr('common.status'),'workplace_type'=>tr('jobs.workplace_type'),'updated_at'=>tr('common.updated')],
    };
}

function reportDefaultColumns(string $base): array
{
    return array_slice(array_keys(reportFieldOptions($base)), 0, 6);
}

function reportStatusOptions(string $base): array
{
    return match ($base) {
        'applications' => applicationStatusOptions(),
        'contacts' => ['open'=>tr('contacts.open_planned_logs'),'none'=>tr('contacts.no_open_logs')],
        'calendar' => calendarStatusOptions(),
        default => jobStatusOptions(),
    };
}

function jobStatusOptions(): array
{
    return ['open'=>tr('jobs.status.open'),'interesting'=>tr('jobs.status.interesting'),'applied'=>tr('jobs.status.applied'),'interview'=>tr('jobs.status.interview'),'offer'=>tr('jobs.status.offer'),'rejected'=>tr('jobs.status.rejected'),'closed'=>tr('jobs.status.closed')];
}

function workplaceTypeOptions(): array
{
    return ['unknown'=>tr('common.unknown'),'onsite'=>tr('jobs.workplace.onsite'),'hybrid'=>tr('jobs.workplace.hybrid'),'remote'=>tr('jobs.workplace.remote')];
}

function engagementTypeOptions(): array
{
    return ['permanent'=>tr('jobs.engagement.permanent'),'temporary'=>tr('jobs.engagement.temporary')];
}

function contractTermOptions(): array
{
    return ['unknown'=>tr('jobs.contract.unknown'),'open_ended'=>tr('jobs.contract.open_ended'),'fixed_term'=>tr('jobs.contract.fixed_term')];
}

function salaryPeriodOptions(): array
{
    return ['hour'=>tr('profile.salary.hour'),'month'=>tr('profile.salary.month'),'year'=>tr('profile.salary.year')];
}

function optionLabel(array $options, mixed $value): string
{
    $key = (string) $value;
    return (string) ($options[$key] ?? $key);
}

function applicationStatusOptions(): array
{
    return ['draft'=>tr('applications.status.draft'),'ready'=>tr('applications.status.ready'),'sent'=>tr('applications.status.sent'),'confirmed'=>tr('applications.status.confirmed'),'interview'=>tr('applications.status.interview'),'assessment'=>tr('applications.status.assessment'),'offer'=>tr('applications.status.offer'),'accepted'=>tr('applications.status.accepted'),'rejected'=>tr('applications.status.rejected'),'withdrawn'=>tr('applications.status.withdrawn'),'closed'=>tr('applications.status.closed')];
}

function applicationChannelOptions(): array
{
    return ['email'=>tr('contact_log.channel.email'),'portal'=>tr('applications.channel.portal'),'website'=>tr('applications.channel.website'),'mail'=>tr('applications.channel.mail'),'referral'=>tr('applications.channel.referral'),'other'=>tr('common.other')];
}

function calendarEventTypeOptions(): array
{
    return ['task'=>tr('calendar.type.task'),'follow_up'=>tr('calendar.type.follow_up'),'interview'=>tr('calendar.type.interview'),'deadline'=>tr('calendar.type.deadline'),'meeting'=>tr('calendar.type.meeting'),'reminder'=>tr('calendar.type.reminder'),'other'=>tr('common.other')];
}

function calendarStatusOptions(): array
{
    return ['planned'=>tr('calendar.status.planned'),'completed'=>tr('calendar.status.completed'),'cancelled'=>tr('calendar.status.cancelled')];
}

function saveReportSettings(mysqli $db, int $reportId, string $base): void
{
    $db->query('DELETE FROM saved_report_columns WHERE report_id=' . $reportId);
    $db->query('DELETE FROM saved_report_filters WHERE report_id=' . $reportId);
    $db->query('DELETE FROM saved_report_sorts WHERE report_id=' . $reportId);

    $fields = reportFieldOptions($base);
    $columns = array_values(array_filter((array) ($_POST['report_columns'] ?? []), static fn($field): bool => isset($fields[(string)$field])));
    if (!$columns) {
        $columns = reportDefaultColumns($base);
    }
    $columnStmt = $db->prepare('INSERT INTO saved_report_columns (report_id, field_name, label, sort_order, is_visible) VALUES (?, ?, ?, ?, 1)');
    foreach ($columns as $index => $field) {
        $field = (string) $field;
        $label = $fields[$field];
        $order = $index + 1;
        $columnStmt->bind_param('issi', $reportId, $field, $label, $order);
        $columnStmt->execute();
    }

    $filterStmt = $db->prepare('INSERT INTO saved_report_filters (report_id, field_name, operator, value_json, sort_order) VALUES (?, ?, ?, ?, ?)');
    $order = 1;
    $query = trim((string) ($_POST['report_q'] ?? ''));
    if ($query !== '') {
        $field = 'q';
        $operator = 'contains';
        $value = json_encode($query, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $filterStmt->bind_param('isssi', $reportId, $field, $operator, $value, $order);
        $filterStmt->execute();
        $order++;
    }
    $status = trim((string) ($_POST['report_status'] ?? ''));
    if ($status !== '' && isset(reportStatusOptions($base)[$status])) {
        $field = 'status';
        $operator = 'eq';
        $value = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $filterStmt->bind_param('isssi', $reportId, $field, $operator, $value, $order);
        $filterStmt->execute();
    }

    $sortField = (string) ($_POST['report_sort'] ?? '');
    if (!isset($fields[$sortField])) {
        $sortField = $columns[0] ?? array_key_first($fields);
    }
    $direction = strtolower((string) ($_POST['report_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    $priority = 1;
    $sortStmt = $db->prepare('INSERT INTO saved_report_sorts (report_id, field_name, direction, priority) VALUES (?, ?, ?, ?)');
    $sortStmt->bind_param('issi', $reportId, $sortField, $direction, $priority);
    $sortStmt->execute();
}

function loadReportSettings(mysqli $db, int $reportId, string $base): array
{
    $columns = dbAll($db, 'SELECT field_name, label FROM saved_report_columns WHERE report_id=? AND is_visible=1 ORDER BY sort_order, id', 'i', [$reportId]);
    $filters = dbAll($db, 'SELECT field_name, value_json FROM saved_report_filters WHERE report_id=? ORDER BY sort_order, id', 'i', [$reportId]);
    $sort = dbOne($db, 'SELECT field_name, direction FROM saved_report_sorts WHERE report_id=? ORDER BY priority, id LIMIT 1', 'i', [$reportId]);
    $selected = $columns ? array_map(static fn(array $row): string => (string)$row['field_name'], $columns) : reportDefaultColumns($base);
    $filterValues = [];
    foreach ($filters as $filter) {
        $filterValues[(string)$filter['field_name']] = json_decode((string)$filter['value_json'], true) ?: '';
    }
    return ['columns'=>$selected, 'filters'=>$filterValues, 'sort'=>$sort ?: ['field_name'=>$selected[0] ?? 'title', 'direction'=>'asc']];
}

function reportDataset(mysqli $db, int $userId, array $report, array $settings, array $currentUser): array
{
    $base = (string) ($report['base_entity'] ?? 'jobs');
    $fields = reportFieldOptions($base);
    $columns = array_values(array_filter((array)$settings['columns'], static fn($field): bool => isset($fields[(string)$field])));
    if (!$columns) {
        $columns = reportDefaultColumns($base);
    }
    $headers = array_map(static fn(string $field): string => $fields[$field], $columns);
    $filters = (array) ($settings['filters'] ?? []);
    $q = strtolower((string) ($filters['q'] ?? ''));
    $status = (string) ($filters['status'] ?? '');

    $rows = match ($base) {
        'applications' => dbAll($db, 'SELECT a.status, a.channel, a.application_url, a.reference_number, a.applied_at, a.next_action, a.next_action_at, j.title, c.name company FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL', 'i', [$userId]),
        'companies' => dbAll($db, 'SELECT name, city, website, is_intermediary, updated_at FROM companies WHERE owner_user_id=? AND deleted_at IS NULL', 'i', [$userId]),
        'contacts' => dbAll($db, 'SELECT CONCAT(c.last_name, " ", c.first_name) name, co.name company, c.email, COALESCE(NULLIF(c.phone,""), c.mobile) phone, c.position, c.updated_at, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=c.id AND l.status IN ("open","planned")) open_logs FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND c.deleted_at IS NULL', 'i', [$userId]),
        'documents' => dbAll($db, 'SELECT d.title, dt.name_key type, d.original_filename filename, d.scope, d.version, d.created_at FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.deleted_at IS NULL', 'i', [$userId]),
        'calendar' => calendarEventRows($db, $userId, (new DateTimeImmutable('-30 days'))->setTime(0, 0), (new DateTimeImmutable('+90 days'))->setTime(23, 59, 59)),
        default => dbAll($db, 'SELECT j.title, c.name company, j.location_text location, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL', 'i', [$userId]),
    };

    $rows = array_values(array_filter($rows, static function(array $row) use ($q, $status, $base): bool {
        if ($status !== '') {
            if ($base === 'contacts') {
                $hasOpen = (int)($row['open_logs'] ?? 0) > 0;
                if (($status === 'open' && !$hasOpen) || ($status === 'none' && $hasOpen)) {
                    return false;
                }
            } elseif ((string)($row['status'] ?? '') !== $status) {
                return false;
            }
        }
        if ($q === '') {
            return true;
        }
        return str_contains(strtolower(implode(' ', array_map('strval', $row))), $q);
    }));

    $sort = (array) ($settings['sort'] ?? []);
    $sortField = isset($fields[(string)($sort['field_name'] ?? '')]) ? (string)$sort['field_name'] : ($columns[0] ?? array_key_first($fields));
    $dir = strtolower((string)($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
    usort($rows, static function(array $a, array $b) use ($sortField, $dir): int {
        $result = strcmp((string)($a[$sortField] ?? ''), (string)($b[$sortField] ?? ''));
        return $dir === 'desc' ? -$result : $result;
    });

    $data = [];
    foreach ($rows as $row) {
        $data[] = array_map(static function(string $field) use ($row, $currentUser): string {
            $value = $row[$field] ?? '';
            if (in_array($field, ['updated_at','created_at','applied_at','next_action_at','starts_at'], true)) {
                return displayDateTime($value ?: null, $currentUser);
            }
            if ($field === 'is_intermediary') {
                return !empty($value) ? 'Ja' : 'Nein';
            }
            return (string) $value;
        }, $columns);
    }

    return [$headers, $data];
}

function calendarViewOptions(): array
{
    return ['agenda'=>tr('calendar.view.agenda'),'day'=>tr('calendar.view.day'),'workweek'=>tr('calendar.view.workweek'),'week'=>tr('calendar.view.week'),'month'=>tr('calendar.view.month')];
}

function calendarAnchorDate(array $user): DateTimeImmutable
{
    $timezone = new DateTimeZone((string) ($user['timezone'] ?? 'Europe/Zurich'));
    $date = (string) ($_GET['date'] ?? '');
    try {
        return $date !== '' ? new DateTimeImmutable($date, $timezone) : new DateTimeImmutable('today', $timezone);
    } catch (Throwable) {
        return new DateTimeImmutable('today', $timezone);
    }
}

function calendarRange(string $view, DateTimeImmutable $anchor): array
{
    return match ($view) {
        'day' => [$anchor->setTime(0, 0), $anchor->setTime(23, 59, 59), '-1 day', '+1 day'],
        'workweek' => [$anchor->modify('monday this week')->setTime(0, 0), $anchor->modify('friday this week')->setTime(23, 59, 59), '-1 week', '+1 week'],
        'week' => [$anchor->modify('monday this week')->setTime(0, 0), $anchor->modify('sunday this week')->setTime(23, 59, 59), '-1 week', '+1 week'],
        'month' => [$anchor->modify('first day of this month')->setTime(0, 0), $anchor->modify('last day of this month')->setTime(23, 59, 59), '-1 month', '+1 month'],
        default => [$anchor->setTime(0, 0), $anchor->modify('+60 days')->setTime(23, 59, 59), '-30 days', '+30 days'],
    };
}

function calendarEventRows(mysqli $db, int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $startSql = $start->format('Y-m-d H:i:s');
    $endSql = $end->format('Y-m-d H:i:s');
    $rows = [];
    foreach (dbAll($db, 'SELECT ce.id, ce.title, ce.event_type, ce.starts_at, ce.ends_at, ce.status, ce.notes, ce.application_id, ce.contact_id, j.title job_title, c.name company_name FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id LEFT JOIN jobs j ON j.id=a.job_id LEFT JOIN companies c ON c.id=j.company_id WHERE ce.owner_user_id=? AND ce.starts_at BETWEEN ? AND ? ORDER BY ce.starts_at ASC', 'iss', [$userId, $startSql, $endSql]) as $event) {
        $rows[] = [
            'source' => 'calendar',
            'id' => (int) $event['id'],
            'title' => (string) $event['title'],
            'type' => calendarEventTypeOptions()[(string) $event['event_type']] ?? (string) $event['event_type'],
            'status' => calendarStatusOptions()[(string) $event['status']] ?? (string) $event['status'],
            'starts_at' => (string) $event['starts_at'],
            'ends_at' => (string) ($event['ends_at'] ?: date('Y-m-d H:i:s', strtotime((string) $event['starts_at']) + 1800)),
            'meta' => trim((string) (($event['job_title'] ?? '') . (($event['company_name'] ?? '') ? ' · ' . $event['company_name'] : ''))),
            'notes' => (string) ($event['notes'] ?? ''),
            'href' => !empty($event['application_id']) ? '/?page=applications&edit=' . (int) $event['application_id'] . '#application-form' : '#event-' . (int) $event['id'],
        ];
    }
    foreach (dbAll($db, 'SELECT a.id, a.next_action, a.next_action_at, j.title job_title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL AND a.next_action_at BETWEEN ? AND ? ORDER BY a.next_action_at ASC', 'iss', [$userId, $startSql, $endSql]) as $todo) {
        $rows[] = [
            'source' => 'application',
            'id' => (int) $todo['id'],
            'title' => (string) ($todo['next_action'] ?: tr('nav.pendents')),
            'type' => tr('nav.pendents'),
            'status' => tr('calendar.status.open'),
            'starts_at' => (string) $todo['next_action_at'],
            'ends_at' => date('Y-m-d H:i:s', strtotime((string) $todo['next_action_at']) + 1800),
            'meta' => trim((string) ($todo['job_title'] . ' · ' . $todo['company_name'])),
            'notes' => '',
            'href' => '/?page=applications&edit=' . (int) $todo['id'] . '#application-form',
        ];
    }
    foreach (dbAll($db, 'SELECT l.id, l.contact_id, l.channel, l.status, l.subject, l.follow_up_at, c.first_name, c.last_name, co.name company_name FROM contact_logs l JOIN contacts c ON c.id=l.contact_id JOIN companies co ON co.id=l.company_id WHERE l.owner_user_id=? AND l.follow_up_at BETWEEN ? AND ? ORDER BY l.follow_up_at ASC', 'iss', [$userId, $startSql, $endSql]) as $log) {
        $rows[] = [
            'source' => 'contact_log',
            'id' => (int) $log['id'],
            'title' => (string) ($log['subject'] ?: tr('contact_log.follow_up')),
            'type' => contactLogChannelOptions()[(string)$log['channel']] ?? (string) $log['channel'],
            'status' => contactLogStatusOptions()[(string) $log['status']] ?? (string) $log['status'],
            'starts_at' => (string) $log['follow_up_at'],
            'ends_at' => date('Y-m-d H:i:s', strtotime((string) $log['follow_up_at']) + 1800),
            'meta' => trim((string) ($log['first_name'] . ' ' . $log['last_name'] . ' · ' . $log['company_name'])),
            'notes' => '',
            'href' => '/?page=contacts&edit_contact=' . (int) $log['contact_id'] . '#contact-log',
        ];
    }
    usort($rows, static fn(array $a, array $b): int => strcmp((string)$a['starts_at'], (string)$b['starts_at']));
    return $rows;
}

function calendarIcsEscape(string $value): string
{
    return str_replace(["\\", "\r\n", "\n", ",", ";"], ["\\\\", "\\n", "\\n", "\\,", "\\;"], $value);
}

function calendarIcsResponse(string $filename, array $events): never
{
    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//JeMa Jobs//Calendar//DE\r\nCALSCALE:GREGORIAN\r\n";
    foreach ($events as $event) {
        $start = gmdate('Ymd\THis\Z', strtotime((string)$event['starts_at']));
        $end = gmdate('Ymd\THis\Z', strtotime((string)$event['ends_at']));
        $uid = calendarIcsEscape($event['source'] . '-' . $event['id'] . '@jobs.jema.business');
        $summary = calendarIcsEscape((string)$event['title']);
        $description = calendarIcsEscape(trim((string)$event['meta'] . "\n" . (string)$event['notes']));
        $ics .= "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:{$start}\r\nDTEND:{$end}\r\nSUMMARY:{$summary}\r\nDESCRIPTION:{$description}\r\nEND:VEVENT\r\n";
    }
    $ics .= "END:VCALENDAR\r\n";
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    header('Content-Length: ' . strlen($ics));
    echo $ics;
    exit;
}

function localeForCountry(?string $countryCode): string
{
    return match (strtoupper((string) $countryCode)) {
        'CH' => 'de_CH',
        'DE' => 'de_DE',
        'AT' => 'de_AT',
        'FR' => 'fr_FR',
        'IT' => 'it_IT',
        'ES' => 'es_ES',
        'PT' => 'pt_PT',
        'GB' => 'en_GB',
        default => 'de_CH',
    };
}

function displayDateTime(?string $value, ?array $user = null, bool $withTime = true): string
{
    if (!$value) {
        return '';
    }
    try {
        $timezone = new DateTimeZone((string) ($user['timezone'] ?? 'Europe/Zurich'));
        $date = new DateTimeImmutable($value, $timezone);
        $locale = localeForCountry($user['country_code'] ?? 'CH');
        if (class_exists(IntlDateFormatter::class)) {
            $formatter = new IntlDateFormatter(
                $locale,
                IntlDateFormatter::MEDIUM,
                $withTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE,
                $timezone->getName()
            );
            $formatted = $formatter->format($date);
            if ($formatted !== false) {
                return $formatted;
            }
        }
        return $date->format($withTime ? 'd.m.Y H:i' : 'd.m.Y');
    } catch (Throwable) {
        return (string) $value;
    }
}

function storageRoot(): string
{
    return __DIR__ . '/storage/documents';
}

function ensureDocumentStorage(int $userId): string
{
    $root = storageRoot();
    if (!is_dir($root)) {
        mkdir($root, 0775, true);
    }
    $deny = dirname($root) . '/.htaccess';
    if (!is_file($deny)) {
        file_put_contents($deny, "Require all denied\n");
    }
    $dir = $root . '/' . $userId;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function uploadDocumentFile(array $file, int $userId): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(tr('documents.error_upload_failed'));
    }
    if ((int) $file['size'] > 25 * 1024 * 1024) {
        throw new RuntimeException(tr('documents.error_too_large'));
    }
    $original = basename((string) $file['name']);
    $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','jpg','jpeg','png','txt'];
    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException(tr('documents.error_type_not_allowed'));
    }
    $dir = ensureDocumentStorage($userId);
    $name = bin2hex(random_bytes(18)) . '.' . $extension;
    $target = $dir . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException(tr('documents.error_save_failed'));
    }
    $mime = function_exists('mime_content_type') ? (mime_content_type($target) ?: 'application/octet-stream') : 'application/octet-stream';
    return [
        'original' => $original,
        'path' => 'storage/documents/' . $userId . '/' . $name,
        'mime' => $mime,
        'size' => filesize($target) ?: (int) $file['size'],
        'sha256' => hash_file('sha256', $target),
    ];
}

function dbOne(mysqli $db, string $sql, string $types = '', array $values = []): ?array
{
    if (!method_exists(mysqli_stmt::class, 'get_result')) {
        $rows = queryRowsWithoutMysqlnd($db, $sql, $types, $values, 1);
        return $rows[0] ?? null;
    }
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    $rows = statementRows($stmt, 1);
    return $rows[0] ?? null;
}

function dbAll(mysqli $db, string $sql, string $types = '', array $values = []): array
{
    if (!method_exists(mysqli_stmt::class, 'get_result')) {
        return queryRowsWithoutMysqlnd($db, $sql, $types, $values);
    }
    $stmt = $db->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    return statementRows($stmt);
}

function statementRows(mysqli_stmt $stmt, ?int $limit = null): array
{
    $result = $stmt->get_result();
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        if ($limit !== null && count($rows) >= $limit) {
            break;
        }
    }
    return $rows;
}

function queryRowsWithoutMysqlnd(mysqli $db, string $sql, string $types = '', array $values = [], ?int $limit = null): array
{
    $query = interpolateSql($db, $sql, $types, $values);
    $result = $db->query($query);
    if (!($result instanceof mysqli_result)) {
        return [];
    }
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        if ($limit !== null && count($rows) >= $limit) {
            break;
        }
    }
    $result->free();
    return $rows;
}

function interpolateSql(mysqli $db, string $sql, string $types, array $values): string
{
    if ($types === '') {
        return $sql;
    }
    $offset = 0;
    foreach ($values as $index => $value) {
        $pos = strpos($sql, '?', $offset);
        if ($pos === false) {
            break;
        }
        $type = $types[$index] ?? 's';
        if ($value === null) {
            $replacement = 'NULL';
        } elseif ($type === 'i') {
            $replacement = (string) (int) $value;
        } elseif ($type === 'd') {
            $replacement = (string) (float) $value;
        } else {
            $replacement = "'" . $db->real_escape_string((string) $value) . "'";
        }
        $sql = substr($sql, 0, $pos) . $replacement . substr($sql, $pos + 1);
        $offset = $pos + strlen($replacement);
    }
    return $sql;
}

function applicationDocumentFiles(mysqli $db, int $userId, int $applicationId): array
{
    $rows = dbAll(
        $db,
        'SELECT d.id, d.original_filename, d.storage_path, d.mime_type, d.file_size
           FROM application_documents ad
           JOIN user_documents d ON d.id=ad.user_document_id
          WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL
          ORDER BY ad.sort_order, d.scope DESC, d.title, d.version DESC',
        'ii',
        [$applicationId, $userId]
    );
    $root = realpath(storageRoot());
    if (!$root) {
        return [];
    }
    $files = [];
    foreach ($rows as $row) {
        $path = realpath(__DIR__ . '/' . (string) $row['storage_path']);
        if (!$path || !str_starts_with($path, $root) || !is_file($path)) {
            continue;
        }
        $files[] = [
            'id' => (int) $row['id'],
            'filename' => (string) $row['original_filename'],
            'path' => $path,
            'mime' => (string) ($row['mime_type'] ?: 'application/octet-stream'),
            'size' => (int) $row['file_size'],
        ];
    }
    return $files;
}

function zipSafeName(string $filename, array &$used): string
{
    $name = preg_replace('/[\\\\\/:*?"<>|]+/', '_', basename($filename)) ?: 'dokument.pdf';
    $base = pathinfo($name, PATHINFO_FILENAME);
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $candidate = $name;
    $i = 2;
    while (isset($used[mb_strtolower($candidate)])) {
        $candidate = $base . '-' . $i . ($extension !== '' ? '.' . $extension : '');
        $i++;
    }
    $used[mb_strtolower($candidate)] = true;
    return $candidate;
}

function applicationTempRoot(): string
{
    return __DIR__ . '/storage/temp/application-documents';
}

function removeTree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $target = $path . '/' . $entry;
        is_dir($target) ? removeTree($target) : @unlink($target);
    }
    @rmdir($path);
}

function cleanupApplicationTempPackages(): void
{
    $packages = $_SESSION['application_temp_packages'] ?? [];
    $root = applicationTempRoot();
    foreach ($packages as $token => $package) {
        if ((int) ($package['expires_at'] ?? 0) >= time()) {
            continue;
        }
        removeTree((string) ($package['dir'] ?? ''));
        unset($packages[$token]);
    }
    $_SESSION['application_temp_packages'] = $packages;
    if (is_dir($root)) {
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $target = $root . '/' . $entry;
            if (is_dir($target) && filemtime($target) !== false && filemtime($target) < time() - 7200) {
                removeTree($target);
            }
        }
    }
}

function createApplicationTempPackage(mysqli $db, int $userId, int $applicationId): ?array
{
    cleanupApplicationTempPackages();
    $files = applicationDocumentFiles($db, $userId, $applicationId);
    if (!$files) {
        return null;
    }
    $root = applicationTempRoot();
    if (!is_dir($root)) {
        mkdir($root, 0775, true);
    }
    $token = bin2hex(random_bytes(16));
    $dir = $root . '/' . $userId . '-' . $applicationId . '-' . $token;
    mkdir($dir, 0775, true);
    $used = [];
    $items = [];
    foreach ($files as $file) {
        $name = zipSafeName((string) $file['filename'], $used);
        $target = $dir . '/' . $name;
        if (!copy((string) $file['path'], $target)) {
            continue;
        }
        $items[] = [
            'name' => $name,
            'size' => (int) filesize($target),
            'mime' => (string) $file['mime'],
        ];
    }
    if (!$items) {
        removeTree($dir);
        return null;
    }
    $package = [
        'application_id' => $applicationId,
        'dir' => $dir,
        'expires_at' => time() + 3600,
        'items' => $items,
    ];
    $_SESSION['application_temp_packages'][$token] = $package;
    return ['token' => $token] + $package;
}

function isAdmin(mysqli $db, int $userId, array $config = []): bool
{
    if ($userId < 1) {
        return false;
    }
    $user = dbOne($db, 'SELECT email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$userId]);
    $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
    if ($user && in_array(strtolower((string) $user['email']), $adminEmails, true)) {
        return true;
    }
    $role = dbOne(
        $db,
        "SELECT 1 is_admin FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.code='admin' LIMIT 1",
        'i',
        [$userId]
    );
    return (bool) $role;
}

function activeSupportGrant(mysqli $db, int $userId): ?array
{
    if ($userId < 1) {
        return null;
    }
    try {
        return dbOne($db, 'SELECT * FROM support_access_grants WHERE user_id=? AND revoked_at IS NULL ORDER BY granted_at DESC LIMIT 1', 'i', [$userId]);
    } catch (Throwable) {
        return null;
    }
}

function userLabel(array $user): string
{
    $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
    return $name !== '' ? $name : (string) ($user['email'] ?? 'Benutzer');
}

function audit(mysqli $db, int $userId, string $action, string $entityType, int $entityId, ?array $old, ?array $new): void
{
    $stmt = $db->prepare(
        'INSERT INTO audit_log (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent) '
        . 'VALUES (?, ?, ?, ?, ?, ?, INET6_ATON(?), ?)'
    );
    $oldJson = $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $newJson = $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $stmt->bind_param('ississss', $userId, $action, $entityType, $entityId, $oldJson, $newJson, $ip, $agent);
    $stmt->execute();
}

function applicationNextActionChoices(): array
{
    return [
        'Unterlagen prüfen',
        'Motivationsschreiben erstellen',
        'E-Mail-Betreff und Begleittext erstellen',
        'Bewerbung senden',
        'Antwort auf Bewerbung pendent',
        'Nachfassen',
        'Interview vorbereiten',
        'Referenzen nachreichen',
        'Angebot prüfen',
        'Absage verarbeiten',
        'Archivieren',
    ];
}

function applicationNextActionOptions(): array
{
    $labels = [
        'Unterlagen prüfen' => tr('next_action.review_documents'),
        'Motivationsschreiben erstellen' => tr('next_action.create_cover_letter'),
        'E-Mail-Betreff und Begleittext erstellen' => tr('next_action.create_email_text'),
        'Bewerbung senden' => tr('next_action.send_application'),
        'Antwort auf Bewerbung pendent' => tr('next_action.await_response'),
        'Nachfassen' => tr('next_action.follow_up'),
        'Interview vorbereiten' => tr('next_action.prepare_interview'),
        'Referenzen nachreichen' => tr('next_action.send_references'),
        'Angebot prüfen' => tr('next_action.review_offer'),
        'Absage verarbeiten' => tr('next_action.process_rejection'),
        'Archivieren' => tr('next_action.archive'),
    ];
    return array_intersect_key($labels, array_flip(applicationNextActionChoices()));
}

function applicationPrompt(mysqli $db, int $userId, int $applicationId, array $currentUser): string
{
    $application = dbOne($db, 'SELECT a.*, j.company_id, j.title job_title, j.location_text, j.status job_status, j.workplace_type, j.engagement_type, j.contract_term, j.source_url, SUBSTRING(j.description,1,65535) job_description, c.name company_name, c.website company_website, c.phone company_phone, c.address_line1, c.address_line2, c.postal_code, c.city company_city, c.region company_region, c.country_code company_country, i.name intermediary_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, $userId]);
    if (!$application) {
        return '';
    }
    $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [$userId]) ?: [];
    $languages = dbAll($db, 'SELECT language_name, cefr_level FROM user_language_skills WHERE user_id=? ORDER BY language_name', 'i', [$userId]);
    $contacts = dbAll($db, 'SELECT co.name company_name, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, c.notes FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND (c.application_id=? OR c.job_id=? OR c.company_id=? OR c.company_id=?) AND c.deleted_at IS NULL ORDER BY co.name, c.last_name, c.first_name', 'iiiii', [$userId, $applicationId, (int)$application['job_id'], (int)$application['company_id'], (int)($application['intermediary_company_id'] ?? 0)]);
    $logs = dbAll($db, 'SELECT channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND application_id=? ORDER BY occurred_at DESC LIMIT 20', 'ii', [$userId, $applicationId]);
    $documents = dbAll($db, "SELECT d.scope, d.title, d.version, d.original_filename, dt.code type_code FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL ORDER BY d.scope DESC, d.title", 'ii', [$applicationId, $userId]);
    $history = dbAll($db, 'SELECT old_status, new_status, comment, changed_at FROM application_status_history WHERE application_id=? ORDER BY changed_at DESC', 'i', [$applicationId]);

    $lines = [
        'Erstelle für diese Bewerbung drei Texte:',
        '1. einen prägnanten E-Mail-Betreff',
        '2. einen kurzen professionellen E-Mail-Begleittext',
        '3. ein individuelles Motivationsschreiben',
        '',
        'Sprache/Ton: ' . (documentLanguageChoices()[normalizeLocale((string)($currentUser['preferred_language'] ?? 'de-CH'))] ?? 'Deutsch (Schweiz)') . ', professionell, klar, natürlich, nicht übertrieben.',
        'Bitte keine Fakten erfinden. Wenn eine Information fehlt, formuliere neutral oder markiere sie als Platzhalter.',
        '',
        '=== Bewerberprofil ===',
        'Name: ' . trim((string)($currentUser['first_name'] ?? '') . ' ' . (string)($currentUser['last_name'] ?? '')),
        'E-Mail: ' . (string)($currentUser['email'] ?? ''),
        'Telefon: ' . trim((string)($currentUser['phone'] ?? '') . ' ' . (string)($currentUser['mobile'] ?? '')),
        'Ort/Region/Land: ' . trim((string)($currentUser['city'] ?? '') . ' / ' . (string)($currentUser['region'] ?? '') . ' / ' . (string)($currentUser['country_code'] ?? '')),
        'Sprachen: ' . ($languages ? implode(', ', array_map(static fn(array $row): string => $row['language_name'] . ' ' . $row['cefr_level'], $languages)) : 'keine erfasst'),
        '',
        '=== Job-Referenzen / Wünsche ===',
        'Gewünschte Rollen: ' . (string)($preference['desired_roles'] ?? ''),
        'Gewünschte Orte: ' . (string)($preference['desired_locations'] ?? ''),
        'Arbeitsmodell: ' . (string)($preference['remote_preference'] ?? ''),
        'Stellenarten: ' . (string)($preference['employment_types'] ?? ''),
        'Pensum: ' . trim((string)($preference['workload_min'] ?? '') . ' - ' . (string)($preference['workload_max'] ?? '') . '%'),
        'Lohnvorstellung: ' . trim((string)($preference['salary_min'] ?? '') . ' ' . (string)($preference['salary_currency'] ?? '') . ' / ' . (string)($preference['salary_period'] ?? '')),
        'Benefits/PK/Extras: ' . (string)($preference['desired_benefits'] ?? ''),
        'Ausschlüsse: ' . (string)($preference['excluded_industries'] ?? ''),
        'Notizen: ' . (string)($preference['notes'] ?? ''),
        '',
        '=== Stelle ===',
        'Jobtitel: ' . (string)$application['job_title'],
        'Firma: ' . (string)$application['company_name'],
        'Vermittlerfirma: ' . (string)($application['intermediary_name'] ?? ''),
        'Ort: ' . (string)$application['location_text'],
        'Arbeitsort-Modell: ' . (string)$application['workplace_type'],
        'Stellentyp: ' . (string)$application['engagement_type'] . ' / ' . (string)$application['contract_term'],
        'Quelle: ' . (string)$application['source_url'],
        'Beschreibung: ' . (string)$application['job_description'],
        '',
        '=== Firma ===',
        'Website: ' . (string)$application['company_website'],
        'Telefon: ' . (string)$application['company_phone'],
        'Adresse: ' . trim((string)$application['address_line1'] . "\n" . (string)$application['address_line2'] . "\n" . (string)$application['postal_code'] . ' ' . (string)$application['company_city']),
        'Region/Land: ' . (string)$application['company_region'] . ' / ' . (string)$application['company_country'],
        '',
        '=== Bewerbung ===',
        'Status: ' . (string)$application['status'],
        'Kanal: ' . (string)$application['channel'],
        'Gesendet am: ' . displayDateTime($application['applied_at'] ?? null, $currentUser),
        'Online-Bewerbungs-URL: ' . (string)($application['application_url'] ?? ''),
        'Portal / Konto-Hinweis: ' . (string)($application['portal_account'] ?? ''),
        'Referenznummer: ' . (string)($application['reference_number'] ?? ''),
        'Online-Notizen: ' . (string)($application['online_notes'] ?? ''),
        'Nächster Schritt: ' . (string)$application['next_action'],
        'Fällig am: ' . displayDateTime($application['next_action_at'] ?? null, $currentUser),
        'Bestehender Betreff: ' . (string)$application['email_subject'],
        'Bestehender Begleittext: ' . (string)$application['email_body'],
        'Interne Notizen: ' . (string)$application['notes'],
        '',
        '=== Kontakte ===',
    ];
    foreach ($contacts as $contact) {
        $lines[] = trim($contact['company_name'] . ': ' . $contact['first_name'] . ' ' . $contact['last_name'] . ', ' . $contact['position'] . ' ' . $contact['department'] . ', ' . $contact['email'] . ', ' . $contact['phone'] . ' ' . $contact['mobile'] . ', Notizen: ' . $contact['notes']);
    }
    if (!$contacts) {
        $lines[] = 'keine Kontakte erfasst';
    }
    $lines[] = '';
    $lines[] = '=== Kontakt-Log ===';
    foreach ($logs as $log) {
        $lines[] = displayDateTime($log['occurred_at'] ?? null, $currentUser) . ' · ' . $log['channel'] . ' · ' . $log['direction'] . ' · ' . $log['status'] . ' · ' . $log['subject'] . ' · ' . $log['body'] . ' · Ergebnis: ' . $log['outcome'] . ' · Wiedervorlage: ' . displayDateTime($log['follow_up_at'] ?? null, $currentUser);
    }
    if (!$logs) {
        $lines[] = 'keine Kontaktaktivitäten erfasst';
    }
    $lines[] = '';
    $lines[] = '=== Zugeordnete Dokumente ===';
    foreach ($documents as $document) {
        $lines[] = ($document['scope'] === 'profile' ? 'Stammdaten' : 'Bewerbungsdaten') . ' · ' . documentTypeLabel((string)$document['type_code'], (string)($currentUser['preferred_language'] ?? 'de-CH')) . ' · ' . $document['title'] . ' · v' . $document['version'] . ' · ' . $document['original_filename'];
    }
    if (!$documents) {
        $lines[] = 'keine Dokumente zugeordnet';
    }
    $lines[] = '';
    $lines[] = '=== Statusverlauf ===';
    foreach ($history as $entry) {
        $lines[] = displayDateTime($entry['changed_at'] ?? null, $currentUser) . ' · ' . $entry['old_status'] . ' -> ' . $entry['new_status'] . ' · ' . $entry['comment'];
    }
    if (!$history) {
        $lines[] = 'kein Statusverlauf vorhanden';
    }
    $lines[] = '';
    $lines[] = 'Ausgabe bitte mit Überschriften: E-Mail-Betreff, E-Mail-Begleittext, Motivationsschreiben.';

    return trim(implode("\n", $lines));
}

function matchJob(array $job): array
{
    $score = 50;
    $reasons = [];
    if (($job['workplace_type'] ?? '') === 'remote') {
        $score += 15;
        $reasons[] = tr('jobs.match_reason.remote');
    }
    if (!empty($job['salary_min'])) {
        $score += 10;
        $reasons[] = tr('jobs.match_reason.salary');
    }
    if (!empty($job['description'])) {
        $score += 10;
        $reasons[] = tr('jobs.match_reason.description');
    }
    if (($job['status'] ?? '') === 'interesting') {
        $score += 15;
        $reasons[] = tr('jobs.match_reason.interesting');
    }
    return [min(100, $score), $reasons ?: [tr('jobs.match_reason.insufficient_data')]];
}

function plainText(string $value): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function publicHttpUrl(string $url): bool
{
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
        return false;
    }
    $records = dns_get_record($parts['host'], DNS_A | DNS_AAAA);
    if (!$records) {
        return false;
    }
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? '';
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
    }
    return true;
}

function extractImportUrls(string $payload): array
{
    $payload = html_entity_decode($payload, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    preg_match_all('~https?://[^\s<>"\'\]\)]+~iu', $payload, $matches);
    $urls = [];
    foreach ($matches[0] ?? [] as $url) {
        $url = rtrim((string) $url, ".,;:!?)]}\r\n\t ");
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $urls[$url] = $url;
        }
    }
    return array_values($urls);
}

function importPayloadIsUrlOnly(string $payload, array $urls): bool
{
    if (!$urls) {
        return false;
    }
    $rest = html_entity_decode($payload, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    foreach ($urls as $url) {
        $rest = str_replace($url, '', $rest);
    }
    $rest = trim((string) preg_replace('/[\s\[\]\(\),.;:!?\-_|]+/u', '', $rest));
    return $rest === '';
}

function findJobPosting(mixed $value): ?array
{
    if (!is_array($value)) {
        return null;
    }
    $type = $value['@type'] ?? null;
    if ($type === 'JobPosting' || (is_array($type) && in_array('JobPosting', $type, true))) {
        return $value;
    }
    foreach ($value as $child) {
        $found = findJobPosting($child);
        if ($found) {
            return $found;
        }
    }
    return null;
}

function importMetaContent(DOMXPath $xpath, string $selector): string
{
    $node = $xpath->query($selector)->item(0);
    return plainText((string) ($node?->nodeValue ?? ''));
}

function importHtmlMatch(string $html, array $patterns): string
{
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $match)) {
            return plainText(html_entity_decode(strip_tags((string) ($match[1] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    return '';
}

function importDetailPathPattern(): string
{
    return '~/(?:vacancies/detail|emplois/detail|stellenangebote/detail|offres-emplois/detail|detail|jobs/view)/~iu';
}

function importUrlLooksLikeDetail(string $url): bool
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
    return (bool) preg_match(importDetailPathPattern(), $path);
}

function importAbsoluteUrl(string $href, string $baseUrl): string
{
    $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($href === '') {
        return '';
    }
    if (str_starts_with($href, '//')) {
        $scheme = (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https');
        return $scheme . ':' . $href;
    }
    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }
    $scheme = (string) (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https');
    $host = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: '');
    if ($host === '') {
        return '';
    }
    if (str_starts_with($href, '/')) {
        return $scheme . '://' . $host . $href;
    }
    $path = rtrim(dirname((string) (parse_url($baseUrl, PHP_URL_PATH) ?: '/')), '/');
    return $scheme . '://' . $host . ($path === '' ? '' : $path) . '/' . $href;
}

function importDiscoverDetailUrls(string $url, int $limit = 30): array
{
    if (importUrlLooksLikeDetail($url)) {
        return [$url];
    }
    if (!publicHttpUrl($url) || !function_exists('curl_init')) {
        return [$url];
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_USERAGENT => 'JeMaJobs/0.1 (+https://jobs.jema.business)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $html = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $finalUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    curl_close($curl);
    if (!is_string($html) || $status >= 400 || strlen($html) > 5_000_000) {
        return [$url];
    }
    $baseUrl = $finalUrl ?: $url;
    preg_match_all('~href=["\']([^"\']*(?:vacancies/detail|emplois/detail|stellenangebote/detail|offres-emplois/detail|/detail/)[^"\']*)["\']~iu', $html, $matches);
    $urls = [];
    foreach ($matches[1] ?? [] as $href) {
        $candidate = importAbsoluteUrl((string) $href, $baseUrl);
        if ($candidate !== '' && publicHttpUrl($candidate) && importUrlLooksLikeDetail($candidate)) {
            $urls[$candidate] = $candidate;
        }
        if (count($urls) >= $limit) {
            break;
        }
    }
    return $urls ? array_values($urls) : [$url];
}

function importJobLocation(array $job): string
{
    $locations = $job['jobLocation'] ?? [];
    if (isset($locations['address'])) {
        $locations = [$locations];
    }
    foreach ((array) $locations as $location) {
        $address = is_array($location) ? ($location['address'] ?? []) : [];
        if (!is_array($address)) {
            continue;
        }
        foreach (['addressLocality', 'addressRegion', 'addressCountry'] as $field) {
            $value = plainText((string) ($address[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

function importHiringOrganization(array $job): string
{
    $organizations = $job['hiringOrganization'] ?? [];
    if (isset($organizations['name'])) {
        $organizations = [$organizations];
    }
    foreach ((array) $organizations as $organization) {
        if (!is_array($organization)) {
            continue;
        }
        $name = plainText((string) ($organization['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }
    return '';
}

function importCleanTitle(string $title): string
{
    $title = plainText($title);
    $title = (string) preg_replace('~\s+-\s+(?:Annonce sur jobs\.ch|Stellenangebot bei .+? - jobs\.ch|Offre d\'emploi chez .+? - jobup\.ch|Job at .+? - jobs\.ch)\s*$~iu', '', $title);
    $title = (string) preg_replace('~^(.+?\d{1,3}\s*%)\p{Lu}.*$~u', '$1', $title);
    $title = (string) preg_replace('~^(.+?)\s+in\s+.+?\s+\|\s+LinkedIn$~iu', '$1', $title);
    $title = (string) preg_replace('~^.+?\s+sucht\s+(.+?)$~iu', '$1', $title);
    $title = (string) preg_replace('~\s+-\s+(?:jobs\.ch|jobup\.ch)\s*$~iu', '', $title);
    return trim($title);
}

function importCompanyFromText(string $text): string
{
    $text = plainText($text);
    $patterns = [
        '~\b(?:Stellenangebot bei|Offre d\'emploi chez|Job at)\s+(.+?)\s+-\s+(?:jobs\.ch|jobup\.ch)\b~iu',
        '~^(?:.+?)\s+-\s+(?:Stellenangebot bei|Offre d\'emploi chez|Job at)\s+(.+?)\s+-\s+(?:jobs\.ch|jobup\.ch)\b~iu',
        '~^(.+?)\s+sucht\s+.+?\s+\|\s+LinkedIn$~iu',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $match)) {
            return trim(plainText((string) ($match[1] ?? '')));
        }
    }
    return '';
}

function importVisibleCompany(DOMXPath $xpath): string
{
    $queries = [
        '//h1[@data-cy="vacancy-title"]/preceding::*[contains(concat(" ", normalize-space(@class), " "), " notranslate ")][1]',
    ];
    foreach ($queries as $query) {
        foreach ($xpath->query($query) ?: [] as $node) {
            $value = plainText((string) $node->textContent);
            if ($value !== '' && !preg_match('~^(Employeur non divulgué|Arbeitgeber nicht sichtbar|Confidential employer)$~iu', $value)) {
                return $value;
            }
        }
    }
    return '';
}

function importLooksLikeJobDetail(string $url, ?array $job, DOMXPath $xpath): bool
{
    if ($job) {
        return true;
    }
    $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
    if (!preg_match(importDetailPathPattern(), $path)) {
        return false;
    }
    return importMetaContent($xpath, '//h1[@data-cy="vacancy-title"]') !== ''
        || importMetaContent($xpath, '//meta[@property="og:title"]/@content') !== ''
        || importMetaContent($xpath, '//h1[contains(@class, "top-card-layout__title")]') !== '';
}

function importUpsertCompany(mysqli $db, int $uid, string $companyName): int
{
    $company = dbOne($db, 'SELECT id FROM companies WHERE owner_user_id=? AND name=? AND deleted_at IS NULL LIMIT 1', 'is', [$uid, $companyName]);
    if ($company) {
        return (int) $company['id'];
    }
    $empty = '';
    $stmt = $db->prepare('INSERT INTO companies (owner_user_id, name, city, website) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('isss', $uid, $companyName, $empty, $empty);
    $stmt->execute();
    $companyId = (int) $stmt->insert_id;
    audit($db, $uid, 'create', 'company', $companyId, null, ['name' => $companyName]);
    return $companyId;
}

function importRepairExistingJob(mysqli $db, int $uid, array $existing, array $draft, int $companyId): bool
{
    $title = trim((string) ($draft['title'] ?? ''));
    if ($title === '') {
        return false;
    }
    $fallbackCompany = tr('jobs.new_company_from_import');
    $currentTitle = trim((string) ($existing['title'] ?? ''));
    $currentCompany = trim((string) ($existing['company_name'] ?? ''));
    if ($currentTitle !== '' && $currentTitle !== 'Job aus Import' && $currentCompany !== $fallbackCompany) {
        return false;
    }
    $location = trim((string) ($draft['location'] ?? $draft['location_text'] ?? ''));
    $description = trim((string) ($draft['description'] ?? ''));
    $stmt = $db->prepare('UPDATE jobs SET title=?, company_id=?, location_text=IF(?="", location_text, ?), description=IF(?="", description, ?) WHERE id=? AND owner_user_id=?');
    $jobId = (int) $existing['id'];
    $stmt->bind_param('sissssii', $title, $companyId, $location, $location, $description, $description, $jobId, $uid);
    $stmt->execute();
    audit($db, $uid, 'update', 'job', $jobId, $existing, ['title' => $title, 'company_id' => $companyId]);
    return true;
}

function importFromUrl(string $url): array
{
    if (!publicHttpUrl($url) || !function_exists('curl_init')) {
        throw new RuntimeException('Die URL ist nicht erreichbar oder aus Sicherheitsgründen nicht erlaubt.');
    }
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'JeMaJobs/0.1 (+https://jobs.jema.business)',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);
    $html = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $finalUrl = (string) curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
    $error = curl_error($curl);
    curl_close($curl);
    if ($finalUrl !== '' && !publicHttpUrl($finalUrl)) {
        throw new RuntimeException('Die Weiterleitung der URL ist aus Sicherheitsgründen nicht erlaubt.');
    }
    if (!is_string($html) || $status >= 400 || strlen($html) > 5_000_000) {
        throw new RuntimeException($error ?: 'Die Stellenanzeige konnte nicht gelesen werden.');
    }

    $document = new DOMDocument();
    @$document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    $xpath = new DOMXPath($document);
    $job = null;
    foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
        try {
            $json = json_decode((string) $script->textContent, true, 64, JSON_THROW_ON_ERROR);
            $job = findJobPosting($json);
            if ($job) {
                break;
            }
        } catch (Throwable) {
        }
    }

    $title = importCleanTitle((string) ($job['title'] ?? ''));
    $company = $job ? importHiringOrganization($job) : '';
    $description = plainText((string) ($job['description'] ?? ''));
    $location = $job ? importJobLocation($job) : '';

    if (!importLooksLikeJobDetail($finalUrl ?: $url, $job, $xpath)) {
        throw new RuntimeException('Die URL ist keine einzelne Stellenanzeige.');
    }
    $visibleTitle = importMetaContent($xpath, '//h1[@data-cy="vacancy-title"]')
        ?: importHtmlMatch($html, ['~<h1[^>]+top-card-layout__title[^>]*>(.*?)</h1>~is']);
    $ogTitle = importMetaContent($xpath, '//meta[@property="og:title"]/@content');
    $metaDescription = importMetaContent($xpath, '//meta[@name="description"]/@content');
    if ($title === '') {
        $title = importCleanTitle($visibleTitle ?: ($ogTitle ?: importHtmlMatch($html, ['~<title[^>]*>(.*?)</title>~is'])));
    }
    if (preg_match('~^\d+\s+.+\s+Jobs$~iu', $title)) {
        throw new RuntimeException('Die URL zeigt eine Ergebnisliste, keine einzelne Stellenanzeige.');
    }
    if ($company === '') {
        $company = importCompanyFromText($ogTitle)
            ?: importCompanyFromText(importHtmlMatch($html, ['~<title[^>]*>(.*?)</title>~is']))
            ?: importHtmlMatch($html, ['~<a[^>]+topcard__org-name-link[^>]*>(.*?)</a>~is'])
            ?: importCompanyFromText($metaDescription)
            ?: importVisibleCompany($xpath);
    }
    if ($description === '') {
        $description = $metaDescription;
    }
    return compact('title', 'company', 'location', 'description') + ['source_url' => $finalUrl ?: $url];
}

function importFromText(string $text): array
{
    $text = trim($text);
    $lines = preg_split('/\R/u', $text) ?: [];
    $values = ['title' => '', 'company' => '', 'location' => '', 'description' => $text, 'source_url' => ''];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if ($values['title'] === '') {
            $values['title'] = $line;
        }
        foreach (['title' => 'Titel|Position|Stelle', 'company' => 'Firma|Unternehmen|Arbeitgeber', 'location' => 'Ort|Standort|Arbeitsort'] as $field => $labels) {
            if (preg_match('/^(?:' . $labels . ')\s*:\s*(.+)$/iu', $line, $match)) {
                $values[$field] = trim($match[1]);
            }
        }
        if ($values['source_url'] === '' && preg_match('~https?://\S+~i', $line, $match)) {
            $values['source_url'] = rtrim($match[0], '.,;)');
        }
    }
    return $values;
}

function originalPdfStatusLabel(?string $status): string
{
    return match ($status) {
        'rendered' => tr('jobs.original_pdf_ready'),
        'pending' => '',
        'failed' => tr('jobs.original_pdf_failed'),
        default => tr('jobs.no_original_pdf'),
    };
}

function timezoneChoices(): array
{
    return [
        'Europa' => [
            'Europe/Zurich' => 'Zürich / Schweiz',
            'Europe/Berlin' => 'Berlin / Deutschland',
            'Europe/Vienna' => 'Wien / Österreich',
            'Europe/Paris' => 'Paris / Frankreich',
            'Europe/Rome' => 'Rom / Italien',
            'Europe/Madrid' => 'Madrid / Spanien',
            'Europe/Lisbon' => 'Lissabon / Portugal',
            'Europe/London' => 'London / UK',
        ],
        'Amerika' => [
            'America/New_York' => 'New York',
            'America/Chicago' => 'Chicago',
            'America/Denver' => 'Denver',
            'America/Los_Angeles' => 'Los Angeles',
            'America/Sao_Paulo' => 'São Paulo',
        ],
        'Afrika' => [
            'Africa/Casablanca' => 'Casablanca',
            'Africa/Johannesburg' => 'Johannesburg',
        ],
        'Asien' => [
            'Asia/Dubai' => 'Dubai',
            'Asia/Singapore' => 'Singapur',
            'Asia/Tokyo' => 'Tokio',
        ],
        'Ozeanien' => [
            'Australia/Sydney' => 'Sydney',
        ],
    ];
}

function countryChoices(): array
{
    return [
        'CH' => tr('country.CH'),
        'LI' => tr('country.LI'),
        'DE' => tr('country.DE'),
        'AT' => tr('country.AT'),
        'FR' => tr('country.FR'),
        'IT' => tr('country.IT'),
        'ES' => tr('country.ES'),
        'PT' => tr('country.PT'),
        'GB' => tr('country.GB'),
        'US' => tr('country.US'),
        'BR' => tr('country.BR'),
    ];
}

function regionChoices(): array
{
    return [
        'CH' => [
            'Seeland',
            'Mittelland',
            'Zürcher Oberland',
            'Zürich Stadt',
            'Ostschweiz',
            'Rheintal',
            'Nordwestschweiz',
            'Zentralschweiz',
            'Berner Oberland',
            'Genferseeregion',
            'Tessin',
            'Graubünden',
        ],
        'LI' => ['Oberland', 'Unterland'],
        'DE' => ['Baden-Württemberg', 'Bayern', 'Rhein-Main', 'Rheinland', 'Ruhrgebiet', 'Berlin/Brandenburg', 'Hamburg/Norddeutschland'],
        'AT' => ['Vorarlberg', 'Tirol', 'Salzburg', 'Oberösterreich', 'Wien', 'Steiermark'],
        'FR' => ['Grand Est', 'Auvergne-Rhône-Alpes', 'Île-de-France', 'Provence-Alpes-Côte d’Azur'],
        'IT' => ['Lombardei', 'Piemont', 'Südtirol/Trentino', 'Venetien'],
        'ES' => ['Katalonien', 'Madrid', 'Valencia', 'Andalusien'],
        'PT' => ['Norte', 'Centro', 'Lissabon', 'Alentejo', 'Algarve'],
        'GB' => ['Greater London', 'South East England', 'North West England', 'Scotland'],
        'US' => ['Northeast', 'Midwest', 'South', 'West Coast'],
        'BR' => ['Sudeste', 'Sul', 'Nordeste', 'Centro-Oeste', 'Norte'],
    ];
}

function countryForRegion(string $regionKey): array
{
    if (!str_contains($regionKey, '|')) {
        return [null, null];
    }
    [$country, $region] = explode('|', $regionKey, 2);
    $choices = regionChoices();
    if (!isset($choices[$country]) || !in_array($region, $choices[$country], true)) {
        return [null, null];
    }
    return [$country, $region];
}

function currencyForCountry(?string $countryCode): string
{
    return match ($countryCode) {
        'CH', 'LI' => 'CHF',
        'GB' => 'GBP',
        'US' => 'USD',
        'BR' => 'BRL',
        default => 'EUR',
    };
}

function salaryLabel(array $row, ?string $currencyOverride = null): string
{
    $min = $row['salary_min'] ?? null;
    $max = $row['salary_max'] ?? null;
    if ($min === null && $max === null) {
        return '';
    }
    $currency = (string)($currencyOverride ?: ($row['salary_currency'] ?? 'CHF'));
    $period = salaryPeriodOptions()[(string)($row['salary_period'] ?? 'year')] ?? salaryPeriodOptions()['year'];
    $format = static fn($value): string => rtrim(rtrim(number_format((float)$value, 2, '.', "'"), '0'), '.');
    return $format($min ?? $max) . ' ' . $currency . ' ' . $period;
}

function europeanLanguageChoices(): array
{
    return [
        'de' => tr('language.skill.de'),
        'en' => tr('language.skill.en'),
        'fr' => tr('language.skill.fr'),
        'it' => tr('language.skill.it'),
        'es' => tr('language.skill.es'),
        'pt' => tr('language.skill.pt'),
        'nl' => tr('language.skill.nl'),
        'da' => tr('language.skill.da'),
        'sv' => tr('language.skill.sv'),
        'no' => tr('language.skill.no'),
        'fi' => tr('language.skill.fi'),
        'is' => tr('language.skill.is'),
        'ga' => tr('language.skill.ga'),
        'cy' => tr('language.skill.cy'),
        'pl' => tr('language.skill.pl'),
        'cs' => tr('language.skill.cs'),
        'sk' => tr('language.skill.sk'),
        'sl' => tr('language.skill.sl'),
        'hr' => tr('language.skill.hr'),
        'bs' => tr('language.skill.bs'),
        'sr' => tr('language.skill.sr'),
        'bg' => tr('language.skill.bg'),
        'ro' => tr('language.skill.ro'),
        'hu' => tr('language.skill.hu'),
        'el' => tr('language.skill.el'),
        'tr' => tr('language.skill.tr'),
        'uk' => tr('language.skill.uk'),
        'ru' => tr('language.skill.ru'),
        'et' => tr('language.skill.et'),
        'lv' => tr('language.skill.lv'),
        'lt' => tr('language.skill.lt'),
        'mt' => tr('language.skill.mt'),
        'sq' => tr('language.skill.sq'),
        'ca' => tr('language.skill.ca'),
        'eu' => tr('language.skill.eu'),
        'gl' => tr('language.skill.gl'),
        'lb' => tr('language.skill.lb'),
        'rm' => tr('language.skill.rm'),
    ];
}

function documentTypeLabel(string $code, string $language = 'de-CH'): string
{
    $language = normalizeLocale($language);
    $known = ['cv', 'certificate', 'reference_letter', 'diploma', 'cover_letter', 'portfolio', 'other'];
    $code = in_array($code, $known, true) ? $code : 'other';
    return tr('document_type.' . $code, $language);
}

function documentPurposeForType(string $code): string
{
    return match ($code) {
        'cv' => 'cv',
        'cover_letter' => 'cover_letter',
        'certificate', 'diploma' => 'certificate',
        'reference_letter' => 'reference',
        'portfolio' => 'portfolio',
        default => 'other',
    };
}

function documentPurposeLabel(string $purpose, string $language = 'de-CH'): string
{
    $code = match ($purpose) {
        'cv' => 'cv',
        'cover_letter' => 'cover_letter',
        'certificate' => 'certificate',
        'reference' => 'reference_letter',
        'portfolio' => 'portfolio',
        default => 'other',
    };
    return documentTypeLabel($code, $language);
}

function documentLanguageChoices(): array
{
    return [
        'de-CH' => tr('language.name.de_ch'),
        'fr-CH' => tr('language.name.fr_ch'),
        'en-GB' => tr('language.name.en_gb'),
        'pt-BR' => tr('language.name.pt_br'),
        'es-MX' => tr('language.name.es_mx'),
    ];
}

function defaultJobPlatforms(): array
{
    return [
        ['LinkedIn Jobs','https://www.linkedin.com/jobs/','https://www.linkedin.com/jobs/search/?keywords={q}&location={location}',10],
        ['Indeed Schweiz','https://ch.indeed.com/','https://ch.indeed.com/jobs?q={q}&l={location}',20],
        ['Jobs.ch','https://www.jobs.ch/','https://www.jobs.ch/de/stellenangebote/?term={q}&location={location}',30],
        ['JobScout24','https://www.jobscout24.ch/','https://www.jobscout24.ch/de/jobs/?q={q}&l={location}',40],
        ['JobUp','https://www.jobup.ch/','https://www.jobup.ch/de/stellenangebote/?term={q}&location={location}',50],
        ['JobCloud','https://www.jobcloud.ch/','https://www.jobcloud.ch/c/de-ch/jobs?query={q}&location={location}',60],
        ['SwissDevJobs','https://swissdevjobs.ch/','https://swissdevjobs.ch/jobs/all/{q}',70],
        ['ICTjobs','https://www.ictjobs.ch/','https://www.ictjobs.ch/jobs/?query={q}&location={location}',80],
        ['Xing Jobs','https://www.xing.com/jobs','https://www.xing.com/jobs/search?keywords={q}&location={location}',90],
        ['Monster Schweiz','https://www.monster.ch/','https://www.monster.ch/jobs/suche?q={q}&where={location}',100],
        ['Glassdoor','https://www.glassdoor.com/','https://www.glassdoor.com/Job/jobs.htm?sc.keyword={q}&locT=C&locKeyword={location}',110],
        ['Jooble Schweiz','https://ch.jooble.org/','https://ch.jooble.org/SearchResult?ukw={q}&rgns={location}',120],
        ['Adzuna Schweiz','https://www.adzuna.ch/','https://www.adzuna.ch/search?q={q}&where={location}',130],
        ['Google Jobs','https://www.google.com/search?q=jobs','https://www.google.com/search?q={q}+jobs+{location}',140],
        ['Jobs für Macher','https://www.jobsfuermacher.ch/','https://www.jobsfuermacher.ch/jobs?query={q}&location={location}',150],
    ];
}

function seedJobPlatforms(mysqli $db): void
{
    $count = dbOne($db, 'SELECT COUNT(*) c FROM job_platforms WHERE deleted_at IS NULL');
    if ((int)($count['c'] ?? 0) > 0) {
        return;
    }
    $stmt = $db->prepare('INSERT IGNORE INTO job_platforms (name, base_url, search_url_template, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
    foreach (defaultJobPlatforms() as [$name, $baseUrl, $template, $sortOrder]) {
        $stmt->bind_param('sssi', $name, $baseUrl, $template, $sortOrder);
        $stmt->execute();
    }
}

function jobPreferenceQuery(array $preference): string
{
    $roles = preg_split('/[\R,;]+/u', (string)($preference['desired_roles'] ?? '')) ?: [];
    $level = trim((string)($preference['desired_level'] ?? ''));
    $terms = array_values(array_filter(array_map('trim', $roles)));
    if ($level !== '') {
        $terms[] = $level;
    }
    return trim(implode(' ', array_slice($terms, 0, 4)));
}

function jobPreferenceLocation(array $preference, array $currentUser): string
{
    $locations = preg_split('/[\R,;]+/u', (string)($preference['desired_locations'] ?? '')) ?: [];
    $location = trim((string)($locations[0] ?? ''));
    if ($location !== '') {
        return $location;
    }
    return trim((string)($currentUser['city'] ?? '') . ' ' . (string)($currentUser['region'] ?? ''));
}

function platformSearchUrl(array $platform, string $query, string $location, int $limit): string
{
    $template = (string)($platform['search_url_template'] ?? '');
    return strtr($template, [
        '{q}' => rawurlencode($query),
        '{query}' => rawurlencode($query),
        '{location}' => rawurlencode($location),
        '{limit}' => (string)$limit,
    ]);
}

function translationTargetOptions(mysqli $db, int $userId): array
{
    return [
        'job' => [
            'label' => tr('nav.jobs'),
            'rows' => dbAll($db, 'SELECT j.id, CONCAT(j.title, " · ", c.name) label FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.title ASC LIMIT 250', 'i', [$userId]),
        ],
        'company' => [
            'label' => tr('nav.companies'),
            'rows' => dbAll($db, 'SELECT id, name label FROM companies WHERE owner_user_id=? AND deleted_at IS NULL ORDER BY name ASC LIMIT 250', 'i', [$userId]),
        ],
        'application' => [
            'label' => tr('nav.applications'),
            'rows' => dbAll($db, 'SELECT a.id, CONCAT(j.title, " · ", c.name, " · ", UPPER(a.status)) label FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC LIMIT 250', 'i', [$userId]),
        ],
        'contact' => [
            'label' => tr('nav.contacts'),
            'rows' => dbAll($db, 'SELECT ct.id, TRIM(CONCAT(COALESCE(ct.last_name, ""), " ", COALESCE(ct.first_name, ""), IF(c.name IS NULL OR c.name="", "", CONCAT(" · ", c.name)))) label FROM contacts ct LEFT JOIN companies c ON c.id=ct.company_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL ORDER BY ct.last_name ASC, ct.first_name ASC LIMIT 250', 'i', [$userId]),
        ],
        'document' => [
            'label' => tr('nav.documents'),
            'rows' => dbAll($db, 'SELECT ud.id, CONCAT(ud.title, " · v", ud.version, IF(ud.is_current=1, " · aktuell", "")) label FROM user_documents ud WHERE ud.user_id=? AND ud.deleted_at IS NULL ORDER BY ud.title ASC, ud.version DESC LIMIT 250', 'i', [$userId]),
        ],
    ];
}

function translationTargetExists(mysqli $db, int $userId, string $entityType, int $entityId): bool
{
    return match ($entityType) {
        'job' => (bool) dbOne($db, 'SELECT id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$entityId, $userId]),
        'company' => (bool) dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$entityId, $userId]),
        'application' => (bool) dbOne($db, 'SELECT id FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$entityId, $userId]),
        'contact' => (bool) dbOne($db, 'SELECT id FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$entityId, $userId]),
        'document' => (bool) dbOne($db, 'SELECT id FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$entityId, $userId]),
        default => false,
    };
}

function translationSource(mysqli $db, int $userId, string $entityType, int $entityId, array $currentUser): array
{
    $lines = [];
    $title = '';
    if ($entityType === 'job') {
        $row = dbOne($db, 'SELECT j.title, j.location_text, j.source_url, SUBSTRING(j.description,1,65535) description, c.name company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.id=? AND j.owner_user_id=? AND j.deleted_at IS NULL', 'ii', [$entityId, $userId]);
        if ($row) {
            $title = (string)$row['title'];
            $lines = ['Job: ' . $row['title'], 'Firma: ' . $row['company_name'], 'Ort: ' . $row['location_text'], 'Quelle: ' . $row['source_url'], '', (string)$row['description']];
        }
    } elseif ($entityType === 'company') {
        $row = dbOne($db, 'SELECT name, legal_name, website, email, phone, industry, employee_count, address_line1, address_line2, postal_code, city, region, country_code, notes FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$entityId, $userId]);
        if ($row) {
            $title = (string)$row['name'];
            $lines = ['Firma: ' . $row['name'], 'Rechtsname: ' . $row['legal_name'], 'Website: ' . $row['website'], 'E-Mail: ' . $row['email'], 'Telefon: ' . $row['phone'], 'Branche: ' . $row['industry'], 'Grösse: ' . $row['employee_count'], 'Adresse: ' . trim((string)$row['address_line1'] . "\n" . (string)$row['address_line2'] . "\n" . (string)$row['postal_code'] . ' ' . (string)$row['city']), 'Region/Land: ' . $row['region'] . ' / ' . $row['country_code'], 'Notizen: ' . $row['notes']];
        }
    } elseif ($entityType === 'application') {
        $row = dbOne($db, 'SELECT a.status, a.channel, a.application_url, a.portal_account, a.reference_number, SUBSTRING(a.online_notes,1,65535) online_notes, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, SUBSTRING(a.notes,1,65535) notes, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$entityId, $userId]);
        if ($row) {
            $title = (string)$row['title'];
            $lines = ['Bewerbung: ' . $row['title'], 'Firma: ' . $row['company_name'], 'Status: ' . $row['status'], 'Kanal: ' . $row['channel'], 'URL: ' . $row['application_url'], 'Portal: ' . $row['portal_account'], 'Referenz: ' . $row['reference_number'], 'E-Mail-Betreff: ' . $row['email_subject'], '', 'E-Mail-Text:', (string)$row['email_body'], '', 'Motivationsschreiben:', (string)$row['cover_letter_text'], '', 'Online-Notizen:', (string)$row['online_notes'], '', 'Interne Notizen:', (string)$row['notes']];
        }
    } elseif ($entityType === 'contact') {
        $row = dbOne($db, 'SELECT ct.first_name, ct.last_name, ct.position, ct.department, ct.email, ct.phone, ct.mobile, ct.linkedin_url, SUBSTRING(ct.notes,1,65535) notes, c.name company_name FROM contacts ct LEFT JOIN companies c ON c.id=ct.company_id WHERE ct.id=? AND ct.owner_user_id=? AND ct.deleted_at IS NULL', 'ii', [$entityId, $userId]);
        if ($row) {
            $title = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
            $lines = ['Kontakt: ' . $title, 'Firma: ' . $row['company_name'], 'Funktion: ' . $row['position'], 'Abteilung: ' . $row['department'], 'E-Mail: ' . $row['email'], 'Telefon: ' . trim((string)$row['phone'] . ' ' . (string)$row['mobile']), 'LinkedIn: ' . $row['linkedin_url'], 'Notizen: ' . $row['notes']];
        }
    } elseif ($entityType === 'document') {
        $row = dbOne($db, 'SELECT ud.title, ud.description, ud.original_filename, COALESCE(NULLIF(dt.corrected_text,""), NULLIF(dt.extracted_text,""), NULLIF(dt.ocr_text,"")) document_text FROM user_documents ud LEFT JOIN document_texts dt ON dt.user_document_id=ud.id WHERE ud.id=? AND ud.user_id=? AND ud.deleted_at IS NULL', 'ii', [$entityId, $userId]);
        if ($row) {
            $title = (string)$row['title'];
            $lines = ['Dokument: ' . $row['title'], 'Datei: ' . $row['original_filename'], 'Beschreibung: ' . $row['description'], '', (string)$row['document_text']];
        }
    }
    $source = trim((string)preg_replace("/\n{3,}/", "\n\n", implode("\n", array_filter($lines, static fn($line): bool => trim((string)$line) !== ''))));
    return ['title' => $title, 'source' => $source];
}

function translationPrompt(string $targetLanguage, string $title, string $source): string
{
    $language = documentLanguageChoices()[$targetLanguage] ?? $targetLanguage;
    $source = mb_substr($source, 0, 12000);
    return trim("Übersetze den folgenden Inhalt nach {$language}.\n\nBitte fachlich korrekt, natürlich und professionell formulieren. Struktur, Namen, Firmennamen, URLs, E-Mail-Adressen, Telefonnummern und Datumsangaben unverändert lassen. Keine Fakten erfinden.\n\nTitel/Kontext: {$title}\n\n--- AUSGANGSTEXT ---\n{$source}");
}

function applicationDossier(mysqli $db, int $userId, int $applicationId, array $currentUser): ?array
{
    $application = dbOne(
        $db,
        'SELECT a.id, a.job_id, a.intermediary_company_id, a.primary_contact_id, a.status, a.applied_at, a.channel, a.application_url, a.portal_account, a.reference_number, SUBSTRING(a.online_notes,1,65535) online_notes, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, SUBSTRING(a.notes,1,65535) application_notes, a.next_action, a.next_action_at, a.created_at application_created_at, a.updated_at application_updated_at,
                j.company_id, j.title job_title, j.location_text, j.status job_status, j.workplace_type, j.engagement_type, j.contract_term, j.salary_min, j.salary_currency, j.salary_period, j.source_url, SUBSTRING(j.description,1,65535) job_description, SUBSTRING(j.notes,1,65535) job_notes,
                c.name company_name, c.website company_website, c.email company_email, c.phone company_phone, c.address_line1, c.address_line2, c.postal_code, c.city company_city, c.region company_region, c.country_code company_country, SUBSTRING(c.notes,1,65535) company_notes,
                i.name intermediary_company_name
           FROM applications a
           JOIN jobs j ON j.id=a.job_id
           JOIN companies c ON c.id=j.company_id
           LEFT JOIN companies i ON i.id=a.intermediary_company_id
          WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL',
        'ii',
        [$applicationId, $userId]
    );
    if (!$application) {
        return null;
    }
    $jobId = (int) $application['job_id'];
    $companyId = (int) $application['company_id'];
    $intermediaryId = (int) ($application['intermediary_company_id'] ?? 0);
    $contacts = dbAll($db, 'SELECT ct.id, ct.company_id, ct.application_id, ct.job_id, ct.first_name, ct.last_name, ct.position, ct.department, ct.email, ct.phone, ct.mobile, ct.linkedin_url, ct.preferred_language, SUBSTRING(ct.notes,1,65535) notes, co.name company_name FROM contacts ct JOIN companies co ON co.id=ct.company_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL AND (ct.company_id=? OR ct.company_id=? OR ct.application_id=? OR ct.job_id=?) ORDER BY co.name, ct.last_name, ct.first_name', 'iiiii', [$userId, $companyId, $intermediaryId, $applicationId, $jobId]);
    $questions = dbAll($db, 'SELECT id, question_text, answer_text, sort_order, created_at, updated_at FROM job_questions WHERE owner_user_id=? AND job_id=? AND deleted_at IS NULL ORDER BY sort_order, id', 'ii', [$userId, $jobId]);
    try {
        $documents = dbAll($db, 'SELECT ad.purpose, d.id, d.scope, d.title, d.version, d.original_filename, d.created_at, d.file_size, dt.code type_code, COALESCE(NULLIF(txt.corrected_text,""), NULLIF(txt.extracted_text,""), NULLIF(txt.ocr_text,"")) document_text FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id LEFT JOIN document_texts txt ON txt.user_document_id=d.id WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL ORDER BY ad.sort_order, d.scope DESC, d.is_current DESC, d.title, d.version DESC', 'ii', [$applicationId, $userId]);
    } catch (Throwable) {
        $documents = dbAll($db, 'SELECT ad.purpose, d.id, d.scope, d.title, d.version, d.original_filename, d.created_at, d.file_size, dt.code type_code, "" document_text FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL ORDER BY ad.sort_order, d.scope DESC, d.is_current DESC, d.title, d.version DESC', 'ii', [$applicationId, $userId]);
    }
    $history = dbAll($db, 'SELECT old_status, new_status, comment, changed_at FROM application_status_history WHERE application_id=? ORDER BY changed_at DESC', 'i', [$applicationId]);
    $contactLogs = dbAll($db, 'SELECT l.id, l.contact_id, l.application_id, l.job_id, l.channel, l.direction, l.status, l.subject, SUBSTRING(l.body,1,65535) body, l.occurred_at, l.follow_up_at, l.outcome, ct.first_name, ct.last_name, co.name company_name FROM contact_logs l JOIN contacts ct ON ct.id=l.contact_id JOIN companies co ON co.id=l.company_id WHERE l.owner_user_id=? AND (l.application_id=? OR l.job_id=? OR l.company_id=? OR ct.company_id=? OR ct.company_id=?) ORDER BY l.occurred_at DESC', 'iiiiii', [$userId, $applicationId, $jobId, $companyId, $companyId, $intermediaryId]);
    $calendarEvents = dbAll($db, 'SELECT id, title, event_type, starts_at, ends_at, all_day, status, location, notes FROM calendar_events WHERE owner_user_id=? AND application_id=? ORDER BY starts_at DESC', 'ii', [$userId, $applicationId]);
    return ['application' => $application, 'contacts' => $contacts, 'questions' => $questions, 'documents' => $documents, 'history' => $history, 'contact_logs' => $contactLogs, 'calendar_events' => $calendarEvents, 'generated_at' => date('Y-m-d H:i:s'), 'user' => $currentUser];
}

function dossierPdfSections(array $dossier): array
{
    $a = $dossier['application'];
    $statusLabels = applicationStatusOptions();
    $channelLabels = applicationChannelOptions();
    $workplaceLabels = workplaceTypeOptions();
    $contractLabels = contractTermOptions();
    $sections = [
        tr('dossier.overview') => [
            tr('applications.application') . ': ' . (string)$a['job_title'],
            tr('companies.company') . ': ' . (string)$a['company_name'] . ((string)($a['intermediary_company_name'] ?? '') !== '' ? ' ' . tr('companies.by') . ' ' . (string)$a['intermediary_company_name'] : ''),
            tr('common.status') . ': ' . ($statusLabels[(string)$a['status']] ?? (string)$a['status']) . ' | ' . tr('applications.channel') . ': ' . ($channelLabels[(string)$a['channel']] ?? (string)$a['channel']),
            tr('applications.sent') . ': ' . (string)$a['applied_at'] . ' | ' . tr('nav.pendents') . ': ' . trim((string)$a['next_action'] . ' ' . (string)$a['next_action_at']),
            tr('applications.online_url') . ': ' . (string)$a['application_url'],
            tr('applications.portal_account') . '/' . tr('applications.reference_number') . ': ' . trim((string)$a['portal_account'] . ' / ' . (string)$a['reference_number'], ' /'),
        ],
        tr('companies.company') => [
            tr('common.name') . ': ' . (string)$a['company_name'],
            tr('companies.website') . ': ' . (string)$a['company_website'],
            tr('auth.email') . '/' . tr('profile.phone') . ': ' . trim((string)$a['company_email'] . ' / ' . (string)$a['company_phone'], ' /'),
            tr('companies.address') . ': ' . trim((string)$a['address_line1'] . ' ' . (string)$a['address_line2'] . ', ' . (string)$a['postal_code'] . ' ' . (string)$a['company_city'], ' ,'),
            tr('common.comment') . ': ' . (string)$a['company_notes'],
        ],
        tr('jobs.job') => [
            tr('common.title') . ': ' . (string)$a['job_title'],
            tr('jobs.location') . ': ' . (string)$a['location_text'],
            tr('common.status') . '/' . tr('jobs.workplace_type') . ': ' . (jobStatusOptions()[(string)$a['job_status']] ?? (string)$a['job_status']) . ' / ' . ($workplaceLabels[(string)$a['workplace_type']] ?? (string)$a['workplace_type']),
            tr('jobs.source_url') . ': ' . (string)$a['source_url'],
            tr('profile.salary') . ': ' . trim((string)$a['salary_min'] . ' ' . (string)$a['salary_currency'] . ' / ' . (salaryPeriodOptions()[(string)$a['salary_period']] ?? (string)$a['salary_period']), ' /'),
            tr('common.comment') . ': ' . (string)$a['job_notes'],
            tr('common.description') . ': ' . (string)$a['job_description'],
        ],
        tr('applications.application') => [
            tr('applications.online_notes') . ': ' . (string)$a['online_notes'],
            tr('applications.internal_notes') . ': ' . (string)$a['application_notes'],
            tr('applications.email_subject') . ': ' . (string)$a['email_subject'],
            tr('applications.email_body') . ': ' . (string)$a['email_body'],
            tr('applications.cover_letter') . ': ' . (string)$a['cover_letter_text'],
        ],
        tr('nav.contacts') => [],
        tr('jobs.questions') => [],
        tr('nav.documents') => [],
        tr('dossier.activities') => [],
    ];
    $contactsKey = tr('nav.contacts');
    $questionsKey = tr('jobs.questions');
    $documentsKey = tr('nav.documents');
    $activitiesKey = tr('dossier.activities');
    foreach ((array)$dossier['contacts'] as $c) {
        $sections[$contactsKey][] = trim((string)$c['last_name'] . ' ' . (string)$c['first_name']) . ' | ' . (string)$c['company_name'] . ' | ' . (string)$c['position'] . ' | ' . trim((string)$c['email'] . ' ' . (string)$c['phone'] . ' ' . (string)$c['mobile']) . ' | ' . tr('common.comment') . ': ' . (string)$c['notes'];
    }
    foreach ((array)$dossier['questions'] as $q) {
        $sections[$questionsKey][] = tr('dossier.question_short') . ': ' . (string)$q['question_text'] . "\n" . tr('dossier.answer_short') . ': ' . (string)$q['answer_text'];
    }
    foreach ((array)$dossier['documents'] as $doc) {
        $sections[$documentsKey][] = (string)$doc['title'] . ' v' . (int)$doc['version'] . ' | ' . (string)$doc['original_filename'] . ' | ' . bytesLabel((int)$doc['file_size']);
        $sections[$documentsKey][] = trim((string)($doc['document_text'] ?? '')) !== '' ? mb_substr((string)$doc['document_text'], 0, 12000) : tr('dossier.no_document_text');
    }
    foreach ((array)$dossier['history'] as $row) {
        $sections[$activitiesKey][] = (string)$row['changed_at'] . ' | ' . tr('common.status') . ': ' . (string)$row['old_status'] . ' -> ' . (string)$row['new_status'] . ' | ' . (string)$row['comment'];
    }
    foreach ((array)$dossier['contact_logs'] as $row) {
        $sections[$activitiesKey][] = (string)$row['occurred_at'] . ' | ' . tr('contacts.contact') . ': ' . trim((string)$row['first_name'] . ' ' . (string)$row['last_name']) . ' | ' . (string)$row['subject'] . ' | ' . (string)$row['body'] . ' | ' . (string)$row['outcome'];
    }
    foreach ((array)$dossier['calendar_events'] as $row) {
        $sections[$activitiesKey][] = (string)$row['starts_at'] . ' | ' . tr('nav.calendar') . ': ' . (string)$row['title'] . ' | ' . (calendarStatusOptions()[(string)$row['status']] ?? (string)$row['status']) . ' | ' . (string)$row['notes'];
    }
    foreach ($sections as $key => $lines) {
        if (!$lines) {
            $sections[$key] = [tr('common.no_entries')];
        }
    }
    return $sections;
}

function ravDossierPdfSections(mysqli $db, int $userId, array $currentUser): array
{
    $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [$userId]) ?: [];
    $languages = dbAll($db, 'SELECT language_name, cefr_level FROM user_language_skills WHERE user_id=? ORDER BY language_name', 'i', [$userId]);
    $applicationStatuses = applicationStatusOptions();
    $applicationChannels = applicationChannelOptions();
    $nextActionLabels = applicationNextActionOptions();
    $contactLogStatuses = contactLogStatusOptions();
    $calendarStatuses = calendarStatusOptions();

    $applicationRows = dbAll($db, 'SELECT a.id, a.job_id, j.company_id, a.status, a.channel, a.applied_at, a.next_action, a.next_action_at, j.title job_title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY c.name, j.title, COALESCE(a.applied_at, a.created_at) DESC, a.id DESC', 'i', [$userId]);
    $applicationPendents = dbAll($db, "SELECT a.id, a.status, a.next_action, a.next_action_at due_at, j.title job_title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE a.user_id=? AND a.deleted_at IS NULL AND a.next_action_at IS NOT NULL AND a.status NOT IN ('rejected','withdrawn','closed') ORDER BY a.next_action_at ASC", 'i', [$userId]);
    $contactPendents = dbAll($db, 'SELECT l.status, l.subject, l.follow_up_at due_at, ct.first_name, ct.last_name, co.name company_name FROM contact_logs l JOIN contacts ct ON ct.id=l.contact_id AND ct.deleted_at IS NULL JOIN companies co ON co.id=l.company_id AND co.deleted_at IS NULL WHERE l.owner_user_id=? AND l.follow_up_at IS NOT NULL AND l.status IN ("open","planned") ORDER BY l.follow_up_at ASC', 'i', [$userId]);
    $calendarPendents = dbAll($db, 'SELECT ce.title, ce.event_type, ce.status, ce.starts_at due_at, j.title job_title, c.name company_name FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id AND a.deleted_at IS NULL LEFT JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL LEFT JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE ce.owner_user_id=? AND ce.status="planned" ORDER BY ce.starts_at ASC', 'i', [$userId]);
    $contactLogs = dbAll($db, 'SELECT l.application_id, l.job_id, l.channel, l.direction, l.status, l.subject, SUBSTRING(l.body,1,1200) body, l.occurred_at, l.follow_up_at, l.outcome, ct.first_name, ct.last_name, co.name company_name, j.title job_title FROM contact_logs l JOIN contacts ct ON ct.id=l.contact_id JOIN companies co ON co.id=l.company_id LEFT JOIN jobs j ON j.id=l.job_id WHERE l.owner_user_id=? ORDER BY l.occurred_at DESC LIMIT 120', 'i', [$userId]);
    $calendarEvents = dbAll($db, 'SELECT ce.application_id, ce.title, ce.event_type, ce.status, ce.starts_at, ce.ends_at, ce.notes, j.title job_title, c.name company_name FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id LEFT JOIN jobs j ON j.id=a.job_id LEFT JOIN companies c ON c.id=j.company_id WHERE ce.owner_user_id=? ORDER BY ce.starts_at DESC LIMIT 120', 'i', [$userId]);
    $documents = dbAll($db, 'SELECT d.title, d.version, d.original_filename, d.file_size, d.created_at, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope="profile" AND d.deleted_at IS NULL AND d.is_current=1 ORDER BY d.title', 'i', [$userId]);
    $applicationDocuments = dbAll($db, 'SELECT ad.application_id, ad.purpose, d.title, d.version, d.original_filename, d.file_size, dt.code type_code FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.deleted_at IS NULL ORDER BY ad.application_id, d.title', 'i', [$userId]);

    $name = trim((string)($currentUser['first_name'] ?? '') . ' ' . (string)($currentUser['last_name'] ?? ''));
    $phone = trim((string)($currentUser['phone'] ?? '') . ' ' . (string)($currentUser['mobile'] ?? ''));
    $location = trim((string)($currentUser['city'] ?? '') . ' / ' . (string)($currentUser['region'] ?? '') . ' / ' . (string)($currentUser['country_code'] ?? ''), ' /');
    $languageLine = $languages ? implode(', ', array_map(static fn(array $row): string => trim((string)$row['language_name'] . ' ' . (string)$row['cefr_level']), $languages)) : 'keine Sprachkenntnisse erfasst';
    $salaryLine = trim((string)($preference['salary_min'] ?? '') . ' ' . (string)($preference['salary_currency'] ?? '') . ' / ' . (salaryPeriodOptions()[(string)($preference['salary_period'] ?? '')] ?? (string)($preference['salary_period'] ?? '')), ' /');
    $workloadLine = trim((string)($preference['workload_min'] ?? '') . ' - ' . (string)($preference['workload_max'] ?? '') . '%', ' -%');
    $submittedCount = 0;
    foreach ($applicationRows as $row) {
        if (!empty($row['applied_at']) || in_array((string)$row['status'], ['sent','confirmed','interview','assessment','offer','accepted','rejected','withdrawn','closed'], true)) {
            $submittedCount++;
        }
    }

    $sections = [
        'Bewerbungsnachweis' => [
            'Bewerber: ' . ($name !== '' ? $name : (string)($currentUser['email'] ?? '')),
            'Erstellt am: ' . displayDateTime(date('Y-m-d H:i:s'), $currentUser),
            'Erfasste Bewerbungen: ' . count($applicationRows) . ' | Eingereicht: ' . $submittedCount,
            '',
        ],
    ];
    $lastCompany = null;
    foreach ($applicationRows as $row) {
        $companyName = trim((string)$row['company_name']);
        $jobTitle = trim((string)$row['job_title']);
        if ($companyName !== $lastCompany) {
            $sections['Bewerbungsnachweis'][] = 'Firma: ' . $companyName;
            $lastCompany = $companyName;
        }
        $submittedAt = !empty($row['applied_at']) ? displayDateTime((string)$row['applied_at'], $currentUser) : 'noch nicht eingereicht';
        $status = $applicationStatuses[(string)$row['status']] ?? (string)$row['status'];
        $channel = $applicationChannels[(string)$row['channel']] ?? (string)$row['channel'];
        $sections['Bewerbungsnachweis'][] = 'Stelle: ' . $jobTitle;
        $sections['Bewerbungsnachweis'][] = 'Nachweis: ' . $submittedAt . ' | Status: ' . $status . ' | Kanal: ' . $channel;
        $nextAction = trim((string)($row['next_action'] ?? ''));
        if ($nextAction !== '' || !empty($row['next_action_at'])) {
            $sections['Bewerbungsnachweis'][] = 'Pendent: ' . ($nextAction !== '' ? ($nextActionLabels[$nextAction] ?? $nextAction) : 'offen') . (!empty($row['next_action_at']) ? ' | Fällig: ' . displayDateTime((string)$row['next_action_at'], $currentUser) : '');
        }
        $sections['Bewerbungsnachweis'][] = '';
    }
    if (!$applicationRows) {
        $sections['Bewerbungsnachweis'][] = 'Keine Bewerbungen erfasst.';
    }
    return $sections;

    $sections = [
        'Übersicht' => [
            'Erstellt am: ' . displayDateTime(date('Y-m-d H:i:s'), $currentUser),
            'Bewerber: ' . ($name !== '' ? $name : (string)($currentUser['email'] ?? '')),
            'Erfasste Bewerbungen: ' . count($applicationRows),
            'Eingereichte Bewerbungen: ' . $submittedCount,
            'Offene Pendenzen: ' . (count($applicationPendents) + count($contactPendents) + count($calendarPendents)),
        ],
        'Personalien' => [
            'Name: ' . $name,
            'E-Mail: ' . (string)($currentUser['email'] ?? ''),
            'Telefon/Mobil: ' . $phone,
            'Ort/Region/Land: ' . $location,
            'LinkedIn: ' . (string)($currentUser['linkedin_url'] ?? ''),
            'Facebook: ' . (string)($currentUser['facebook_url'] ?? ''),
            'X: ' . (string)($currentUser['x_url'] ?? ''),
            'Weitere Profile: ' . (string)($currentUser['other_profile_url'] ?? ''),
            'Sprachen: ' . $languageLine,
        ],
        'Job-Präferenzen' => [
            'Gewünschte Tätigkeiten/Rollen: ' . (string)($preference['desired_roles'] ?? ''),
            'Gewünschte Orte/Regionen: ' . (string)($preference['desired_locations'] ?? ''),
            'Arbeitsmodell: ' . (string)($preference['remote_preference'] ?? ''),
            'Stellenarten: ' . (string)($preference['employment_types'] ?? ''),
            'Pensum: ' . $workloadLine,
            'Lohnvorstellung: ' . $salaryLine,
            'Level/Lage: ' . (string)($preference['desired_level'] ?? ''),
            'PK / Extras / Benefits: ' . (string)($preference['desired_benefits'] ?? ''),
            'Ausschlüsse: ' . (string)($preference['excluded_industries'] ?? ''),
            'Umzug möglich: ' . (!empty($preference['willing_to_relocate']) ? 'ja' : 'nein'),
            'Reisebereitschaft: ' . (string)($preference['travel_percentage'] ?? '') . '%',
            'Verfügbar ab: ' . (string)($preference['available_from'] ?? ''),
            'Notizen: ' . (string)($preference['notes'] ?? ''),
        ],
        'Pendenzenliste' => [],
        'Stammdokumente' => [],
        'Bewerbungsbemühungen (kaskadierend)' => [],
        'Aktivitäten / Nachweise' => [],
    ];

    foreach ($applicationPendents as $row) {
        $title = (string)($row['next_action'] ?: 'Pendent');
        $sections['Pendenzenliste'][] = displayDateTime((string)$row['due_at'], $currentUser) . ' | Bewerbung | ' . ($nextActionLabels[$title] ?? $title) . ' | ' . (string)$row['job_title'] . ' | ' . (string)$row['company_name'];
    }
    foreach ($contactPendents as $row) {
        $sections['Pendenzenliste'][] = displayDateTime((string)$row['due_at'], $currentUser) . ' | Kontakt | ' . (string)$row['subject'] . ' | ' . trim((string)$row['first_name'] . ' ' . (string)$row['last_name']) . ' | ' . (string)$row['company_name'] . ' | ' . ($contactLogStatuses[(string)$row['status']] ?? (string)$row['status']);
    }
    foreach ($calendarPendents as $row) {
        $sections['Pendenzenliste'][] = displayDateTime((string)$row['due_at'], $currentUser) . ' | Kalender | ' . (string)$row['title'] . ' | ' . trim((string)($row['job_title'] ?? '') . ' ' . (string)($row['company_name'] ?? '')) . ' | ' . ($calendarStatuses[(string)$row['status']] ?? (string)$row['status']);
    }

    foreach ($documents as $row) {
        $sections['Stammdokumente'][] = documentTypeLabel((string)$row['type_code'], (string)($currentUser['preferred_language'] ?? 'de-CH')) . ' | ' . (string)$row['title'] . ' | v' . (int)$row['version'] . ' | ' . (string)$row['original_filename'] . ' | ' . bytesLabel((int)$row['file_size']) . ' | ' . displayDateTime((string)$row['created_at'], $currentUser);
    }

    $logsByApplication = [];
    foreach ($contactLogs as $row) {
        $applicationId = (int)($row['application_id'] ?? 0);
        if ($applicationId > 0) {
            $logsByApplication[$applicationId][] = $row;
        }
    }
    $eventsByApplication = [];
    foreach ($calendarEvents as $row) {
        $applicationId = (int)($row['application_id'] ?? 0);
        if ($applicationId > 0) {
            $eventsByApplication[$applicationId][] = $row;
        }
    }
    $documentsByApplication = [];
    foreach ($applicationDocuments as $row) {
        $applicationId = (int)($row['application_id'] ?? 0);
        if ($applicationId > 0) {
            $documentsByApplication[$applicationId][] = $row;
        }
    }
    $effortKey = 'Bewerbungsbemühungen (kaskadierend)';
    $lastCompany = null;
    $lastJob = null;
    foreach ($applicationRows as $row) {
        $applicationId = (int)$row['id'];
        $companyName = (string)$row['company_name'];
        $jobTitle = (string)$row['job_title'];
        if ($companyName !== $lastCompany) {
            $sections[$effortKey][] = 'Firma > ' . $companyName;
            $lastCompany = $companyName;
            $lastJob = null;
        }
        if ($jobTitle !== $lastJob) {
            $sections[$effortKey][] = 'Stelle > ' . $jobTitle;
            $lastJob = $jobTitle;
        }
        $title = (string)($row['next_action'] ?: '');
        $sections[$effortKey][] = 'Bewerbung > ' . implode(' | ', array_filter([
            displayDateTime((string)($row['applied_at'] ?: ''), $currentUser) ?: 'noch nicht eingereicht',
            $applicationStatuses[(string)$row['status']] ?? (string)$row['status'],
            $applicationChannels[(string)$row['channel']] ?? (string)$row['channel'],
            $title !== '' ? 'Nächster Schritt: ' . ($nextActionLabels[$title] ?? $title) : '',
            !empty($row['next_action_at']) ? 'Fällig: ' . displayDateTime((string)$row['next_action_at'], $currentUser) : '',
        ], static fn($value): bool => trim((string)$value) !== ''));
        foreach ($documentsByApplication[$applicationId] ?? [] as $doc) {
            $sections[$effortKey][] = 'Dokument > ' . documentTypeLabel((string)$doc['type_code'], (string)($currentUser['preferred_language'] ?? 'de-CH')) . ' | ' . (string)$doc['title'] . ' | v' . (int)$doc['version'] . ' | ' . (string)$doc['original_filename'];
        }
        foreach ($logsByApplication[$applicationId] ?? [] as $log) {
            $sections[$effortKey][] = 'Kontaktlog > ' . displayDateTime((string)$log['occurred_at'], $currentUser) . ' | ' . (string)$log['subject'] . ' | ' . trim((string)$log['first_name'] . ' ' . (string)$log['last_name']) . ' | ' . (string)$log['outcome'];
        }
        foreach ($eventsByApplication[$applicationId] ?? [] as $event) {
            $sections[$effortKey][] = 'Kalender > ' . displayDateTime((string)$event['starts_at'], $currentUser) . ' | ' . (string)$event['title'] . ' | ' . ($calendarStatuses[(string)$event['status']] ?? (string)$event['status']);
        }
    }

    foreach ($contactLogs as $row) {
        $sections['Aktivitäten / Nachweise'][] = displayDateTime((string)$row['occurred_at'], $currentUser) . ' | Kontaktlog | ' . (string)$row['subject'] . ' | ' . trim((string)$row['first_name'] . ' ' . (string)$row['last_name']) . ' | ' . (string)$row['company_name'] . ' | ' . (string)($row['job_title'] ?? '') . ' | ' . (string)$row['outcome'];
    }
    foreach ($calendarEvents as $row) {
        $sections['Aktivitäten / Nachweise'][] = displayDateTime((string)$row['starts_at'], $currentUser) . ' | Kalender | ' . (string)$row['title'] . ' | ' . (string)($row['job_title'] ?? '') . ' | ' . (string)($row['company_name'] ?? '') . ' | ' . ($calendarStatuses[(string)$row['status']] ?? (string)$row['status']);
    }

    foreach ($sections as $section => $lines) {
        if (!$lines) {
            $sections[$section] = ['Keine Einträge vorhanden.'];
        }
    }
    return $sections;
}

function allowedDocumentTypeCodes(string $scope): array
{
    if ($scope === 'application') {
        return ['cover_letter', 'other'];
    }
    return ['cv', 'certificate', 'reference_letter', 'diploma', 'portfolio', 'other'];
}

function documentTypesForScope(array $types, string $scope): array
{
    $allowed = allowedDocumentTypeCodes($scope);
    return array_values(array_filter($types, static fn (array $type): bool => in_array((string) $type['code'], $allowed, true)));
}

function contactLogChannelOptions(): array
{
    return [
        'email' => tr('contact_log.channel.email'),
        'external_email' => tr('contact_log.channel.external_email'),
        'onsite' => tr('contact_log.channel.onsite'),
        'phone' => tr('contact_log.channel.phone'),
        'video' => tr('contact_log.channel.video'),
        'whatsapp' => 'WhatsApp',
        'sms' => 'SMS',
        'message' => tr('contact_log.channel.message'),
        'letter' => tr('contact_log.channel.letter'),
        'note' => tr('contact_log.channel.note'),
        'other' => tr('common.other'),
    ];
}

function contactLogStatusOptions(): array
{
    return ['planned'=>tr('calendar.status.planned'),'open'=>tr('jobs.status.open'),'done'=>tr('contact_log.status.done'),'cancelled'=>tr('calendar.status.cancelled')];
}

function ensureSubmittedApplicationContactLog(mysqli $db, int $userId, int $applicationId): void
{
    $application = dbOne(
        $db,
        "SELECT a.id, a.user_id, a.primary_contact_id, a.applied_at, a.channel, a.job_id, j.title job_title, c.name company_name, ct.company_id contact_company_id
           FROM applications a
           JOIN jobs j ON j.id=a.job_id
           JOIN companies c ON c.id=j.company_id
           JOIN contacts ct ON ct.id=a.primary_contact_id AND ct.owner_user_id=a.user_id AND ct.deleted_at IS NULL
          WHERE a.id=? AND a.user_id=? AND a.status='sent' AND a.deleted_at IS NULL AND a.primary_contact_id IS NOT NULL
          LIMIT 1",
        'ii',
        [$applicationId, $userId]
    );
    if (!$application) {
        return;
    }
    $contactId = (int) $application['primary_contact_id'];
    $exists = dbOne($db, "SELECT id FROM contact_logs WHERE owner_user_id=? AND application_id=? AND contact_id=? AND subject='Bewerbung eingereicht' LIMIT 1", 'iii', [$userId, $applicationId, $contactId]);
    if ($exists) {
        return;
    }
    $channel = (string) ($application['channel'] ?? '');
    $logChannel = match ($channel) {
        'email' => 'email',
        'mail' => 'letter',
        'portal', 'website' => 'other',
        default => 'note',
    };
    $subject = 'Bewerbung eingereicht';
    $body = 'Bewerbung eingereicht: ' . trim((string)$application['job_title'] . ' · ' . (string)$application['company_name'], ' ·');
    $occurredAt = (string) ($application['applied_at'] ?: date('Y-m-d H:i:s'));
    $direction = 'outgoing';
    $status = 'done';
    $outcome = 'Antwort auf Bewerbung pendent';
    $stmt = $db->prepare('INSERT INTO contact_logs (owner_user_id, contact_id, company_id, application_id, job_id, channel, direction, status, subject, body, occurred_at, follow_up_at, outcome) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?)');
    $companyId = (int) $application['contact_company_id'];
    $jobId = (int) $application['job_id'];
    $stmt->bind_param('iiiiisssssss', $userId, $contactId, $companyId, $applicationId, $jobId, $logChannel, $direction, $status, $subject, $body, $occurredAt, $outcome);
    $stmt->execute();
}

function ensureSubmittedApplicationCalendarEvent(mysqli $db, int $userId, int $applicationId): void
{
    $application = dbOne(
        $db,
        "SELECT a.id, a.applied_at, j.title job_title, c.name company_name
           FROM applications a
           JOIN jobs j ON j.id=a.job_id
           JOIN companies c ON c.id=j.company_id
          WHERE a.id=? AND a.user_id=? AND a.status='sent' AND a.deleted_at IS NULL
          LIMIT 1",
        'ii',
        [$applicationId, $userId]
    );
    if (!$application) {
        return;
    }
    $exists = dbOne($db, "SELECT id FROM calendar_events WHERE owner_user_id=? AND application_id=? AND title='Bewerbung online eingereicht' LIMIT 1", 'ii', [$userId, $applicationId]);
    if ($exists) {
        return;
    }
    $title = 'Bewerbung online eingereicht';
    $type = 'task';
    $startsAt = (string) ($application['applied_at'] ?: date('Y-m-d H:i:s'));
    $status = 'completed';
    $notes = 'Online-Bewerbung eingereicht: ' . trim((string)$application['job_title'] . ' · ' . (string)$application['company_name'], ' ·');
    $stmt = $db->prepare('INSERT INTO calendar_events (owner_user_id, application_id, title, event_type, starts_at, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iisssss', $userId, $applicationId, $title, $type, $startsAt, $status, $notes);
    $stmt->execute();
}

function saveContactLogAttachment(mysqli $db, int $userId, int $contactId, int $logId, string $contactName, string $subject): ?int
{
    $file = $_FILES['log_attachment'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $type = dbOne($db, "SELECT id FROM document_types WHERE code='other' LIMIT 1");
    if (!$type) {
        throw new RuntimeException('Dokumenttyp für Anhänge fehlt.');
    }
    $uploaded = uploadDocumentFile($file, $userId);
    $scope = 'profile';
    $documentTypeId = (int) $type['id'];
    $languageCode = null;
    $applicationId = null;
    $jobId = null;
    $title = 'Kontakt-Log: ' . mb_strimwidth($subject !== '' ? $subject : $contactName, 0, 150, '...');
    $description = 'Anhang zu Kontakt ' . $contactName . ' / Kontakt-Log ' . $logId;
    $validFrom = null;
    $validUntil = null;
    $version = 1;
    $stmt = $db->prepare('INSERT INTO user_documents (user_id, document_type_id, language_code, scope, application_id, job_id, title, description, original_filename, storage_path, mime_type, file_size, sha256, valid_from, valid_until, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
    $stmt->bind_param('iissiisssssisssi', $userId, $documentTypeId, $languageCode, $scope, $applicationId, $jobId, $title, $description, $uploaded['original'], $uploaded['path'], $uploaded['mime'], $uploaded['size'], $uploaded['sha256'], $validFrom, $validUntil, $version);
    $stmt->execute();
    $documentId = (int) $stmt->insert_id;
    $extractStatus = in_array($uploaded['mime'], ['text/plain','application/pdf'], true) ? 'pending' : 'skipped';
    $textStmt = $db->prepare('INSERT INTO document_texts (user_document_id, extraction_status, language_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE extraction_status=VALUES(extraction_status), language_code=VALUES(language_code)');
    $textStmt->bind_param('iss', $documentId, $extractStatus, $languageCode);
    $textStmt->execute();
    $linkStmt = $db->prepare('INSERT IGNORE INTO contact_log_documents (contact_log_id, user_document_id) VALUES (?, ?)');
    $linkStmt->bind_param('ii', $logId, $documentId);
    $linkStmt->execute();
    audit($db, $userId, 'create', 'contact_log_attachment', $documentId, null, ['contact_id'=>$contactId,'contact_log_id'=>$logId,'title'=>$title]);
    return $documentId;
}

function contactLogAttachments(mysqli $db, int $userId, int $contactId): array
{
    $rows = dbAll($db, 'SELECT cld.contact_log_id, d.id, d.original_filename FROM contact_log_documents cld JOIN contact_logs l ON l.id=cld.contact_log_id JOIN user_documents d ON d.id=cld.user_document_id WHERE l.owner_user_id=? AND l.contact_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC', 'ii', [$userId, $contactId]);
    $grouped = [];
    foreach ($rows as $row) {
        $grouped[(int)$row['contact_log_id']][] = $row;
    }
    return $grouped;
}

function contactLogFormHtml(?array $entry, int $applicationId, int $contactId, array $channels, array $statuses): string
{
    $isEdit = (bool) $entry;
    $occurred = $entry && !empty($entry['occurred_at']) ? date('Y-m-d\TH:i', strtotime((string)$entry['occurred_at'])) : date('Y-m-d\TH:i');
    $followUp = $entry && !empty($entry['follow_up_at']) ? date('Y-m-d\TH:i', strtotime((string)$entry['follow_up_at'])) : '';
    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data" class="stack">
        <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
        <?php if($applicationId > 0): ?><input type="hidden" name="application_id" value="<?= $applicationId ?>"><?php endif; ?>
        <input type="hidden" name="contact_id" value="<?= $contactId ?>">
        <?php if($isEdit): ?><input type="hidden" name="log_id" value="<?= (int)$entry['id'] ?>"><?php endif; ?>
        <div class="three">
            <label><?= e(tr('applications.channel')) ?><select name="log_channel"><?php foreach($channels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($entry['channel'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label><?= e(tr('contact_log.direction')) ?><select name="direction"><?php foreach(['outgoing'=>tr('contact_log.direction.outgoing'),'incoming'=>tr('contact_log.direction.incoming'),'internal'=>tr('contact_log.direction.internal')] as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($entry['direction'] ?? 'outgoing')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label><?= e(tr('common.status')) ?><select name="log_status"><?php foreach($statuses as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($entry['status'] ?? 'open')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="two"><label><?= e(tr('contact_log.occurred_at')) ?><input type="datetime-local" name="occurred_at" value="<?= e($occurred) ?>" required></label><label><?= e(tr('contact_log.follow_up_at')) ?><input type="datetime-local" name="follow_up_at" value="<?= e($followUp) ?>"></label></div>
        <label><?= e(tr('contact_log.subject')) ?><input name="subject" value="<?= e($entry['subject'] ?? '') ?>"></label>
        <label><?= e(tr('contact_log.message')) ?><textarea name="log_body" rows="5"><?= e($entry['body'] ?? '') ?></textarea></label>
        <div class="two"><label><?= e(tr('contact_log.outcome')) ?><input name="outcome" value="<?= e($entry['outcome'] ?? '') ?>"></label><?= filePickerHtml('log_attachment', false) ?></div>
        <div class="actions"><button class="primary" name="action" value="<?= $isEdit ? 'update_contact_log' : 'save_contact_log' ?>"><?= e($isEdit ? tr('contact_log.update_activity') : tr('contact_log.save_activity')) ?></button><?php if($isEdit): ?><a class="button" href="<?= e($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log' : '/?page=contacts&edit_contact=' . $contactId . '#contact-log') ?>"><?= e(tr('common.cancel')) ?></a><?php endif; ?></div>
    </form>
    <?php
    return (string) ob_get_clean();
}

function contactLogTimelineHtml(array $logs, array $attachments, array $channels, array $statuses, array $currentUser, int $applicationId = 0): string
{
    ob_start();
    ?>
    <div class="log-timeline">
        <?php foreach($logs as $entry): ?><article class="log-status-<?= e($entry['status']) ?>">
            <div><strong><?= e($entry['subject'] ?: ($channels[$entry['channel']] ?? ucfirst((string)$entry['channel']))) ?></strong><span><?= e(($statuses[$entry['status']] ?? $entry['status']).' · '.($channels[$entry['channel']] ?? $entry['channel']).' · '.$entry['direction'].' · '.displayDateTime($entry['occurred_at'], $currentUser)) ?></span></div>
            <?php if($entry['body']): ?><p><?= nl2br(e($entry['body'])) ?></p><?php endif; ?>
            <?php if($entry['outcome']): ?><small><?= e(tr('contact_log.outcome')) ?>: <?= e($entry['outcome']) ?></small><?php endif; ?>
            <?php if($entry['follow_up_at']): ?><small><?= e(tr('contact_log.follow_up_at')) ?>: <?= e(displayDateTime($entry['follow_up_at'], $currentUser)) ?></small><?php endif; ?>
            <?php foreach(($attachments[(int)$entry['id']] ?? []) as $doc): ?><small><?= e(tr('contact_log.attachment')) ?>: <a href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['original_filename']) ?></a></small><?php endforeach; ?>
            <div class="actions"><a href="<?= e($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . (int)$entry['contact_id'] . '&edit_log=' . (int)$entry['id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$entry['contact_id'] . '&edit_log=' . (int)$entry['id'] . '#contact-log') ?>"><?= e(tr('common.edit')) ?></a><form method="post" onsubmit="return confirm('<?= e(tr('contact_log.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="log_id" value="<?= (int)$entry['id'] ?>"><button name="action" value="delete_contact_log"><?= e(tr('common.delete')) ?></button></form></div>
        </article><?php endforeach; ?>
        <?php if(!$logs): ?><p class="empty"><?= e(tr('contact_log.empty')) ?></p><?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

$page = (string) ($_GET['page'] ?? (userId() ? 'dashboard' : 'login'));
$action = (string) ($_POST['action'] ?? '');
if (isset($_GET['lang'])) {
    $_SESSION['locale'] = normalizeLocale((string) $_GET['lang']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($action === 'register') {
        $email = strtolower(trim((string) $_POST['email']));
        $first = trim((string) $_POST['first_name']);
        $last = trim((string) $_POST['last_name']);
        $password = (string) $_POST['password'];
        $preferredLanguage = normalizeLocale((string) ($_POST['preferred_language'] ?? currentLocale(null)));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || $first === '' || $last === '') {
            flash('Bitte gültige Daten und mindestens 10 Passwortzeichen eingeben.', 'danger');
            redirect('/?page=register');
        }
        try {
            $emailNeedsVerification = outboundEmailEnabled($config);
            $stmt = $db->prepare(
                "INSERT INTO users (email, password_hash, status, preferred_language, first_name, last_name, email_verified_at) "
                . "VALUES (?, ?, 'active', ?, ?, ?, " . ($emailNeedsVerification ? 'NULL' : 'NOW()') . ")"
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt->bind_param('sssss', $email, $hash, $preferredLanguage, $first, $last);
            $stmt->execute();
            $newUserId = (int) $stmt->insert_id;
            audit($db, $newUserId, 'create', 'user', $newUserId, null, ['email' => $email]);
            unset($_SESSION['email_verify_link'], $_SESSION['email_verify_notice']);
            if ($emailNeedsVerification) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
                $tokenType = 'email_verify';
                $tokenHash = hash('sha256', $token);
                $tokenStmt = $db->prepare('INSERT INTO auth_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, ?, ?, ?)');
                $tokenStmt->bind_param('isss', $newUserId, $tokenType, $tokenHash, $expiresAt);
                $tokenStmt->execute();
                $verifyLink = absoluteUrl($config, '/?page=verify_email&token=' . urlencode($token));
                $subject = 'E-Mail fur JeMa Jobs bestaetigen';
                $body = "Hallo {$first}\n\nbitte bestaetige deine E-Mail-Adresse fur JeMa Jobs innerhalb von 24 Stunden:\n" . $verifyLink . "\n\nWenn du dich nicht registriert hast, kannst du diese Nachricht ignorieren.\n";
                try {
                    sendSmtpMail($config, $email, $subject, $body);
                    logOutboundEmail($db, $newUserId, $email, $subject, $body, 'sent');
                    $_SESSION['email_verify_notice'] = 'Registrierung gespeichert. Bitte bestätige deine E-Mail-Adresse über den Link in deinem Postfach.';
                } catch (Throwable $exception) {
                    logOutboundEmail($db, $newUserId, $email, $subject, $body, 'failed', $exception->getMessage());
                    $_SESSION['email_verify_notice'] = 'Registrierung gespeichert, aber die Bestätigungs-E-Mail konnte nicht versendet werden. Bitte System-SMTP-Konfiguration prüfen.';
                }
            } else {
                $_SESSION['email_verify_notice'] = 'Registrierung gespeichert. E-Mail-Versand ist deaktiviert; das Konto wurde für die Testphase direkt bestätigt.';
            }
            flash($emailNeedsVerification ? 'Registrierung gespeichert. Bitte E-Mail bestätigen.' : 'Registrierung gespeichert. Du kannst dich jetzt direkt anmelden.');
            redirect('/?page=login');
        } catch (mysqli_sql_exception $exception) {
            flash('Diese E-Mail-Adresse ist bereits registriert.', 'danger');
            redirect('/?page=register');
        }
    }

    if ($action === 'login') {
        $email = strtolower(trim((string) $_POST['email']));
        $password = (string) $_POST['password'];
        $user = dbOne($db, 'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', 's', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            flash('E-Mail oder Passwort ist falsch.', 'danger');
            redirect('/?page=login');
        }
        if (in_array((string) $user['status'], ['locked', 'disabled'], true)) {
            flash('Dieses Konto ist gesperrt oder deaktiviert.', 'warning');
            redirect('/?page=login');
        }
        if (empty($user['email_verified_at'])) {
            flash('Bitte bestätige zuerst deine E-Mail-Adresse.', 'warning');
            redirect('/?page=login');
        }
        $loginLocale = !empty($_SESSION['locale'])
            ? normalizeLocale((string) $_SESSION['locale'])
            : normalizeLocale((string) ($user['preferred_language'] ?? 'de-CH'));
        if ($loginLocale !== normalizeLocale((string) ($user['preferred_language'] ?? 'de-CH'))) {
            $localeStmt = $db->prepare('UPDATE users SET preferred_language=? WHERE id=?');
            $userIdForLocale = (int) $user['id'];
            $localeStmt->bind_param('si', $loginLocale, $userIdForLocale);
            $localeStmt->execute();
            $user['preferred_language'] = $loginLocale;
        }
        $_SESSION['locale'] = $loginLocale;
        $totp = activeTotpMethod($db, (int) $user['id']);
        if ($totp) {
            session_regenerate_id(true);
            clearAuthenticatedSession();
            $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
            $_SESSION['pending_2fa_user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            redirect('/?page=two_factor');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $db->query('UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = ' . (int) $user['id']);
        touchUserPresence($db, (int) $user['id']);
        audit($db, (int) $user['id'], 'login', 'user', (int) $user['id'], null, null);
        redirect('/');
    }

    if ($action === 'verify_two_factor') {
        $pendingUserId = (int) ($_SESSION['pending_2fa_user_id'] ?? 0);
        $user = $pendingUserId ? dbOne($db, 'SELECT * FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$pendingUserId]) : null;
        $totp = $user ? activeTotpMethod($db, $pendingUserId) : null;
        if (!$user || !$totp || !verifyTotpCode((string) $totp['secret_encrypted'], (string) ($_POST['totp_code'] ?? ''))) {
            clearAuthenticatedSession();
            flash(tr('auth.totp_invalid'), 'danger');
            redirect('/?page=two_factor');
        }
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_user_name']);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $pendingUserId;
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $loginLocale = !empty($_SESSION['locale'])
            ? normalizeLocale((string) $_SESSION['locale'])
            : normalizeLocale((string) ($user['preferred_language'] ?? 'de-CH'));
        if ($loginLocale !== normalizeLocale((string) ($user['preferred_language'] ?? 'de-CH'))) {
            $localeStmt = $db->prepare('UPDATE users SET preferred_language=? WHERE id=?');
            $localeStmt->bind_param('si', $loginLocale, $pendingUserId);
            $localeStmt->execute();
            $user['preferred_language'] = $loginLocale;
        }
        $_SESSION['locale'] = $loginLocale;
        $db->query('UPDATE users SET last_login_at = NOW(), last_seen_at = NOW() WHERE id = ' . $pendingUserId);
        touchUserPresence($db, $pendingUserId);
        $stmt = $db->prepare('UPDATE two_factor_methods SET last_used_at=NOW() WHERE id=?');
        $methodId = (int) $totp['id'];
        $stmt->bind_param('i', $methodId);
        $stmt->execute();
        audit($db, $pendingUserId, 'login', 'user', $pendingUserId, null, ['two_factor' => 'totp']);
        redirect('/');
    }

    if ($action === 'request_password_reset') {
        $email = strtolower(trim((string) $_POST['email']));
        $user = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? dbOne($db, "SELECT id, status FROM users WHERE email=? AND deleted_at IS NULL AND status NOT IN ('locked','disabled')", 's', [$email])
            : null;
        unset($_SESSION['password_reset_link']);
        unset($_SESSION['password_reset_notice']);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $tokenType = 'password_reset';
            $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND token_type=? AND consumed_at IS NULL');
            $stmt->bind_param('is', $user['id'], $tokenType);
            $stmt->execute();
            $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
            $stmt = $db->prepare('INSERT INTO auth_tokens (user_id, token_type, token_hash, expires_at) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $user['id'], $tokenType, $tokenHash, $expiresAt);
            $stmt->execute();
            $resetPath = '/?page=reset_password&token=' . urlencode($token);
            $resetLink = (appUrl($config) ?: '') . $resetPath;
            $subject = 'Passwort fur JeMa Jobs zurucksetzen';
            $body = "Hallo\n\nfur dein JeMa Jobs Konto wurde ein Passwort-Reset angefordert.\n\nBitte offne diesen Link innerhalb von 60 Minuten:\n" . $resetLink . "\n\nWenn du den Reset nicht angefordert hast, kannst du diese Nachricht ignorieren.\n";
            if (mailEnabledForUser($db, $config, (int) $user['id'])) {
                try {
                    sendConfiguredMail($db, $config, (int) $user['id'], $email, $subject, $body);
                    $_SESSION['password_reset_notice'] = 'Reset-Link wurde per E-Mail versendet.';
                } catch (Throwable $exception) {
                    $_SESSION['password_reset_notice'] = 'E-Mail konnte nicht versendet werden. Bitte eigene SMTP-Konfiguration prüfen.';
                }
            } else {
                $_SESSION['password_reset_link'] = $resetLink;
                $_SESSION['password_reset_notice'] = 'Reset-Link wurde erstellt.';
            }
            audit($db, (int) $user['id'], 'other', 'auth_token', (int) $stmt->insert_id, null, ['token_type' => 'password_reset']);
        } else {
            $_SESSION['password_reset_notice'] = 'Testphase: Für diese E-Mail wurde kein aktives Konto gefunden.';
        }
        flash('Falls das Konto existiert, wurde ein Zurücksetzen vorbereitet.', 'success');
        redirect('/?page=forgot_password&sent=1');
    }

    if ($action === 'reset_password') {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        if ($token === '' || strlen($password) < 10 || $password !== $confirm) {
            flash('Token ungültig oder Passwörter passen nicht zusammen. Mindestens 10 Zeichen.', 'danger');
            redirect('/?page=reset_password&token=' . urlencode($token));
        }
        $tokenHash = hash('sha256', $token);
        $reset = dbOne(
            $db,
            "SELECT t.id token_id, t.user_id, u.status
               FROM auth_tokens t
               JOIN users u ON u.id=t.user_id
              WHERE t.token_type='password_reset'
                AND t.token_hash=?
                AND t.consumed_at IS NULL
                AND t.expires_at > NOW()
                AND u.deleted_at IS NULL
              LIMIT 1",
            's',
            [$tokenHash]
        );
        if (!$reset || in_array((string) $reset['status'], ['locked', 'disabled'], true)) {
            flash('Dieser Link ist ungültig oder abgelaufen.', 'danger');
            redirect('/?page=forgot_password');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash=?, failed_login_count=0, locked_until=NULL WHERE id=?');
        $stmt->bind_param('si', $hash, $reset['user_id']);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE id=?');
        $stmt->bind_param('i', $reset['token_id']);
        $stmt->execute();
        audit($db, (int) $reset['user_id'], 'update', 'user', (int) $reset['user_id'], null, ['password_reset' => true]);
        unset($_SESSION['password_reset_link']);
        flash('Passwort wurde geändert. Du kannst dich jetzt anmelden.');
        redirect('/?page=login');
    }

    if ($action === 'logout') {
        endUserPresenceSession($db, realUserId());
        session_destroy();
        redirect('/?page=login');
    }

    requireLogin();

    if ($action === 'stop_admin_support') {
        if (!isSupportImpersonation()) {
            redirect('/');
        }
        $targetId = userId();
        $adminId = realUserId();
        audit($db, $targetId, 'other', 'support_access', $targetId, null, ['admin_user_id' => $adminId, 'ended' => true]);
        endSupportImpersonationSession();
        flash('Support-Umgebung beendet. Du bist wieder in deinem Admin-Konto.');
        redirect('/?page=admin_users');
    }

    if ($action === 'grant_admin_support') {
        if (isSupportImpersonation()) {
            flash('Support-Freigaben können während einer Support-Sitzung nicht geändert werden.', 'warning');
            redirect('/?page=profile#support-access');
        }
        $uid = userId();
        $stmt = $db->prepare('UPDATE support_access_grants SET revoked_at=NOW(), revoked_by_user_id=? WHERE user_id=? AND revoked_at IS NULL');
        $stmt->bind_param('ii', $uid, $uid);
        $stmt->execute();
        $stmt = $db->prepare('INSERT INTO support_access_grants (user_id, granted_by_user_id) VALUES (?, ?)');
        $stmt->bind_param('ii', $uid, $uid);
        $stmt->execute();
        audit($db, $uid, 'create', 'support_access', (int) $stmt->insert_id, null, ['granted' => true]);
        flash('ADMIN Support ist freigegeben. Ein Administrator kann sich jetzt in deine Umgebung einklinken.');
        redirect('/?page=profile#support-access');
    }

    if ($action === 'revoke_admin_support') {
        if (isSupportImpersonation()) {
            flash('Support-Freigaben können während einer Support-Sitzung nicht geändert werden.', 'warning');
            redirect('/?page=profile#support-access');
        }
        $uid = userId();
        $stmt = $db->prepare('UPDATE support_access_grants SET revoked_at=NOW(), revoked_by_user_id=? WHERE user_id=? AND revoked_at IS NULL');
        $stmt->bind_param('ii', $uid, $uid);
        $stmt->execute();
        audit($db, $uid, 'delete', 'support_access', $uid, null, ['revoked' => true]);
        flash('ADMIN Support wurde widerrufen.');
        redirect('/?page=profile#support-access');
    }

    if ($action === 'admin_start_support') {
        $adminId = realUserId();
        if (!isAdmin($db, $adminId, $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        if (isSupportImpersonation()) {
            flash('Beende zuerst die aktuelle Support-Umgebung.', 'warning');
            redirect('/');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === $adminId) {
            flash('Das eigene Konto kann nicht als Support-Umgebung geöffnet werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, "SELECT id, email, first_name, last_name, status FROM users WHERE id=? AND deleted_at IS NULL AND status NOT IN ('locked','disabled')", 'i', [$targetUserId]);
        $grant = activeSupportGrant($db, $targetUserId);
        if (!$target || !$grant) {
            flash('Für diesen Benutzer liegt keine aktive ADMIN-Support-Freigabe vor.', 'danger');
            redirect('/?page=admin_users');
        }
        $admin = dbOne($db, 'SELECT first_name, last_name, email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$adminId]) ?: [];
        session_regenerate_id(true);
        $_SESSION['support_admin_user_id'] = $adminId;
        $_SESSION['support_admin_name'] = userLabel($admin);
        $_SESSION['support_target_user_id'] = $targetUserId;
        $_SESSION['support_target_name'] = userLabel($target);
        $_SESSION['user_id'] = $targetUserId;
        $_SESSION['user_name'] = userLabel($target);
        audit($db, $targetUserId, 'other', 'support_access', (int) $grant['id'], null, ['admin_user_id' => $adminId, 'started' => true]);
        flash('Support-Umgebung geöffnet: ' . userLabel($target));
        redirect('/');
    }

    if ($action === 'admin_update_user') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Das eigene Admin-Konto kann hier nicht geändert werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email, first_name, last_name, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '') {
            flash('Name und gültige E-Mail sind erforderlich.', 'danger');
            redirect('/?page=admin_users');
        }
        $status = in_array($_POST['status'] ?? '', ['invited','active','locked','disabled'], true) ? (string) $_POST['status'] : (string) $target['status'];
        try {
            $stmt = $db->prepare('UPDATE users SET email=?, first_name=?, last_name=?, status=?, email_verified_at=CASE WHEN ?="active" THEN COALESCE(email_verified_at, NOW()) ELSE email_verified_at END WHERE id=?');
            $stmt->bind_param('sssssi', $email, $firstName, $lastName, $status, $status, $targetUserId);
            $stmt->execute();
        } catch (mysqli_sql_exception) {
            flash('Diese E-Mail-Adresse ist bereits vergeben.', 'danger');
            redirect('/?page=admin_users');
        }

        $adminRole = dbOne($db, "SELECT id FROM roles WHERE code='admin' LIMIT 1");
        $isAdminTarget = !empty($_POST['is_admin']);
        if ($adminRole) {
            $roleId = (int) $adminRole['id'];
            if ($isAdminTarget) {
                $stmt = $db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)');
                $uid = realUserId();
                $stmt->bind_param('iii', $targetUserId, $roleId, $uid);
                $stmt->execute();
            } else {
                $stmt = $db->prepare('DELETE FROM user_roles WHERE user_id=? AND role_id=?');
                $stmt->bind_param('ii', $targetUserId, $roleId);
                $stmt->execute();
            }
        }
        audit($db, realUserId(), 'update', 'user', $targetUserId, $target, ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName, 'status' => $status, 'is_admin' => $isAdminTarget]);
        flash('Benutzer aktualisiert.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_reset_user_password') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Das eigene Admin-Passwort bitte über Passwort vergessen oder Profil-Sicherheit ändern.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        $password = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');
        if (!$target || strlen($password) < 10 || $password !== $confirm) {
            flash('Benutzer nicht gefunden oder Passwort ungültig. Mindestens 10 Zeichen und beide Felder gleich.', 'danger');
            redirect('/?page=admin_users');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET password_hash=?, failed_login_count=0, locked_until=NULL WHERE id=?');
        $stmt->bind_param('si', $hash, $targetUserId);
        $stmt->execute();
        $stmt = $db->prepare("UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND token_type='password_reset' AND consumed_at IS NULL");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        audit($db, realUserId(), 'update', 'user', $targetUserId, ['admin_password_reset' => true], ['target_email' => $target['email']]);
        flash('Passwort wurde zurückgesetzt.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_delete_user') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Das eigene Admin-Konto kann hier nicht gelöscht werden.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email, status FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
        if (in_array(strtolower((string) $target['email']), $adminEmails, true)) {
            flash('Ein Config-Admin kann hier nicht gelöscht werden.', 'warning');
            redirect('/?page=admin_users');
        }
        cleanupUserCascade($db, $targetUserId);
        $stmt = $db->prepare('UPDATE users SET deleted_at=NOW(), status="disabled" WHERE id=?');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND consumed_at IS NULL');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        audit($db, realUserId(), 'delete', 'user', $targetUserId, $target, ['soft_delete' => true]);
        flash('Benutzer wurde gelöscht.');
        redirect('/?page=admin_users');
    }

    if ($action === 'admin_reset_user_2fa') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId === realUserId()) {
            flash('Die eigene 2FA bitte im Profil verwalten.', 'warning');
            redirect('/?page=admin_users');
        }
        $target = dbOne($db, 'SELECT id, email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$targetUserId]);
        if (!$target) {
            flash('Benutzer nicht gefunden.', 'danger');
            redirect('/?page=admin_users');
        }
        $stmt = $db->prepare('DELETE FROM two_factor_methods WHERE user_id=?');
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        $stmt = $db->prepare("UPDATE auth_tokens SET consumed_at=NOW() WHERE user_id=? AND token_type='two_factor' AND consumed_at IS NULL");
        $stmt->bind_param('i', $targetUserId);
        $stmt->execute();
        audit($db, realUserId(), 'delete', 'user', $targetUserId, ['two_factor_reset' => true], ['target_email' => $target['email']]);
        flash('2FA wurde für den Benutzer zurückgesetzt.');
        redirect('/?page=admin_users');
    }

    if ($action === 'enable_totp') {
        $code = (string) ($_POST['totp_code'] ?? '');
        $secret = (string) ($_SESSION['totp_setup_secret'] ?? '');
        if ($secret === '' || !verifyTotpCode($secret, $code)) {
            flash(tr('profile.totp_code_mismatch'), 'danger');
            redirect('/?page=profile#security');
        }
        $uid = userId();
        $stmt = $db->prepare("DELETE FROM two_factor_methods WHERE user_id=? AND method='totp'");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $label = 'Authenticator-App';
        $stmt = $db->prepare("INSERT INTO two_factor_methods (user_id, method, label, secret_encrypted, is_primary, verified_at) VALUES (?, 'totp', ?, ?, 1, NOW())");
        $stmt->bind_param('iss', $uid, $label, $secret);
        $stmt->execute();
        unset($_SESSION['totp_setup_secret']);
        audit($db, $uid, 'create', 'user', $uid, null, ['two_factor' => 'totp']);
        flash(tr('profile.totp_enabled'));
        redirect('/?page=profile#security');
    }

    if ($action === 'disable_totp') {
        $uid = userId();
        $stmt = $db->prepare("DELETE FROM two_factor_methods WHERE user_id=? AND method='totp'");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        unset($_SESSION['totp_setup_secret']);
        audit($db, $uid, 'delete', 'user', $uid, ['two_factor' => 'totp'], null);
        flash(tr('profile.totp_disabled'));
        redirect('/?page=profile#security');
    }

    if ($action === 'create_share') {
        $shareTarget = (string) ($_POST['share_target'] ?? 'area');
        if ($shareTarget === 'area') {
            $targetType = 'area';
            $targetId = null;
        } elseif (preg_match('/^(job|application|document):(\d+)$/', $shareTarget, $targetMatches)) {
            $targetType = $targetMatches[1];
            $targetId = (int) $targetMatches[2];
        } else {
            $targetType = in_array($_POST['target_type'] ?? '', ['area','company','job','application','contact','document','report'], true) ? (string) $_POST['target_type'] : 'area';
            $targetId = $targetType === 'area' ? null : max(1, (int) ($_POST['target_id'] ?? 0));
        }
        $recipient = strtolower(trim((string) ($_POST['recipient_email'] ?? '')));
        $permission = in_array($_POST['permission'] ?? '', ['view','comment','edit'], true) ? (string) $_POST['permission'] : 'view';
        $downloadPolicy = in_array($_POST['download_policy'] ?? '', ['none','original','pdf','both'], true) ? (string) $_POST['download_policy'] : 'none';
        $title = trim((string) ($_POST['title'] ?? '')) ?: 'Freigabe';
        $expiresAt = trim((string) ($_POST['expires_at'] ?? '')) ?: null;
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || ($targetType !== 'area' && (!$targetId || !translationTargetExists($db, userId(), $targetType, (int)$targetId)))) {
            flash('Empfänger und Ziel der Freigabe sind erforderlich.', 'danger');
            redirect('/?page=sharing');
        }
        if ($expiresAt) {
            $expiresAt = str_replace('T', ' ', $expiresAt) . (strlen($expiresAt) === 16 ? ':00' : '');
        }
        $token = shareToken();
        $hash = shareTokenHash($token);
        $watermark = !empty($_POST['watermark_enabled']) ? 1 : 0;
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO guest_shares (owner_user_id, token_hash, title, target_type, target_id, recipient_email, permission, download_policy, watermark_enabled, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssisssis', $uid, $hash, $title, $targetType, $targetId, $recipient, $permission, $downloadPolicy, $watermark, $expiresAt);
        $stmt->execute();
        $shareId = (int) $stmt->insert_id;
        $link = absoluteUrl($config, '/?page=guest&token=' . urlencode($token));
        $_SESSION['last_share_link'] = $link;
        $subject = 'JeMa Jobs Freigabe';
        $body = "Hallo\n\nes wurde ein JeMa Jobs Bereich für dich freigegeben:\n" . $link . "\n\nDiese Freigabe ist persönlich und kann widerrufen werden.\n";
        try {
            if (sendConfiguredMail($db, $config, $uid, $recipient, $subject, $body)) {
                flash('Freigabe erstellt und per E-Mail versendet.');
            } else {
                flash('Freigabe erstellt. Eigener SMTP-Versand ist nicht aktiv; Link wird angezeigt.', 'warning');
            }
        } catch (Throwable) {
            flash('Freigabe erstellt. E-Mail konnte nicht versendet werden; Link wird angezeigt.', 'warning');
        }
        audit($db, $uid, 'create', 'guest_share', $shareId, null, ['target_type' => $targetType, 'target_id' => $targetId, 'recipient_email' => $recipient]);
        redirect('/?page=sharing');
    }

    if ($action === 'revoke_share') {
        $id = (int) ($_POST['share_id'] ?? 0);
        $old = dbOne($db, 'SELECT * FROM guest_shares WHERE id=? AND owner_user_id=? AND revoked_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $stmt = $db->prepare('UPDATE guest_shares SET revoked_at=NOW() WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'guest_share', $id, $old, ['revoked' => true]);
            flash('Freigabe widerrufen.');
        }
        redirect('/?page=sharing');
    }

    if ($action === 'save_translation') {
        $target = (string) ($_POST['translation_target'] ?? '');
        if (preg_match('/^(company|job|contact|application|document):(\d+)$/', $target, $matches)) {
            $entityType = $matches[1];
            $entityId = (int) $matches[2];
        } else {
            $entityType = in_array($_POST['entity_type'] ?? '', ['company','job','contact','application','document'], true) ? (string) $_POST['entity_type'] : '';
            $entityId = max(0, (int) ($_POST['entity_id'] ?? 0));
        }
        $targetLanguage = normalizeLocale((string) ($_POST['target_language'] ?? 'de-CH'));
        $title = trim((string) ($_POST['translation_title'] ?? '')) ?: null;
        $body = trim((string) ($_POST['translation_body'] ?? ''));
        $uid = userId();
        if ($body === '' || $entityType === '' || $entityId < 1 || !translationTargetExists($db, $uid, $entityType, $entityId)) {
            flash('Datensatz und Übersetzungstext sind erforderlich.', 'danger');
            redirect('/?page=translations');
        }
        $existing = dbOne($db, 'SELECT COALESCE(MAX(version),0) v FROM record_translations WHERE owner_user_id=? AND entity_type=? AND entity_id=? AND target_language=?', 'isis', [$uid, $entityType, $entityId, $targetLanguage]);
        $version = (int) ($existing['v'] ?? 0) + 1;
        $stmt = $db->prepare('UPDATE record_translations SET is_current=0 WHERE owner_user_id=? AND entity_type=? AND entity_id=? AND target_language=?');
        $stmt->bind_param('isis', $uid, $entityType, $entityId, $targetLanguage);
        $stmt->execute();
        $stmt = $db->prepare('INSERT INTO record_translations (owner_user_id, entity_type, entity_id, target_language, title, body, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $stmt->bind_param('isisssi', $uid, $entityType, $entityId, $targetLanguage, $title, $body, $version);
        $stmt->execute();
        audit($db, $uid, 'create', 'record_translation', (int) $stmt->insert_id, null, ['entity_type' => $entityType, 'entity_id' => $entityId, 'target_language' => $targetLanguage, 'version' => $version]);
        flash('Übersetzung gespeichert.');
        redirect('/?page=translations');
    }

    if ($action === 'prepare_translation') {
        $target = (string) ($_POST['translation_target'] ?? '');
        $targetLanguage = normalizeLocale((string) ($_POST['target_language'] ?? 'de-CH'));
        if (!preg_match('/^(company|job|contact|application|document):(\d+)$/', $target, $matches)) {
            flash('Bitte zuerst einen Datensatz auswählen.', 'danger');
            redirect('/?page=translations');
        }
        $entityType = $matches[1];
        $entityId = (int) $matches[2];
        $uid = userId();
        if (!translationTargetExists($db, $uid, $entityType, $entityId)) {
            flash('Der gewählte Datensatz ist nicht verfügbar.', 'danger');
            redirect('/?page=translations');
        }
        $source = translationSource($db, $uid, $entityType, $entityId, $currentUser ?? []);
        if (trim((string)$source['source']) === '') {
            flash('Für diesen Datensatz ist kein Ausgangstext vorhanden.', 'warning');
            redirect('/?page=translations');
        }
        $_SESSION['translation_draft'] = [
            'target' => $target,
            'target_language' => $targetLanguage,
            'title' => trim((string) ($_POST['translation_title'] ?? '')) ?: (string)$source['title'],
            'body' => translationPrompt($targetLanguage, (string)$source['title'], (string)$source['source']),
        ];
        flash('Übersetzungsauftrag vorbereitet. Text kopieren, übersetzen lassen, Ergebnis hier einfügen und speichern.');
        redirect('/?page=translations#translation-form');
    }

    if ($action === 'save_calendar_event') {
        $title = trim((string) ($_POST['event_title'] ?? ''));
        $startsAt = trim((string) ($_POST['starts_at'] ?? ''));
        $eventType = in_array($_POST['event_type'] ?? '', ['task','follow_up','interview','deadline','meeting','reminder','other'], true) ? (string) $_POST['event_type'] : 'reminder';
        if ($title === '' || $startsAt === '') {
            flash('Titel und Startzeit sind erforderlich.', 'danger');
            redirect('/?page=calendar&view=agenda');
        }
        $startsAt = str_replace('T', ' ', $startsAt) . (strlen($startsAt) === 16 ? ':00' : '');
        $applicationId = (int) ($_POST['application_id'] ?? 0) ?: null;
        $notes = trim((string) ($_POST['event_notes'] ?? '')) ?: null;
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO calendar_events (owner_user_id, application_id, title, event_type, starts_at, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iissss', $uid, $applicationId, $title, $eventType, $startsAt, $notes);
        $stmt->execute();
        audit($db, $uid, 'create', 'calendar_event', (int) $stmt->insert_id, null, ['title' => $title, 'starts_at' => $startsAt]);
        flash('Kalendereintrag gespeichert.');
        redirect('/?page=calendar&view=agenda');
    }

    if ($action === 'save_report') {
        $name = trim((string) ($_POST['report_name'] ?? ''));
        $description = trim((string) ($_POST['report_description'] ?? '')) ?: null;
        $baseEntity = array_key_exists((string) ($_POST['base_entity'] ?? ''), reportBaseOptions()) ? (string) $_POST['base_entity'] : 'jobs';
        $displayType = array_key_exists((string) ($_POST['display_type'] ?? ''), reportDisplayOptions()) ? (string) $_POST['display_type'] : 'table';
        if ($name === '') {
            flash('Report-Name ist erforderlich.', 'danger');
            redirect('/?page=reports');
        }
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO saved_reports (owner_user_id, name, description, base_entity, display_type, is_shared) VALUES (?, ?, ?, ?, ?, 0)');
        $stmt->bind_param('issss', $uid, $name, $description, $baseEntity, $displayType);
        $stmt->execute();
        $reportId = (int) $stmt->insert_id;
        saveReportSettings($db, $reportId, $baseEntity);
        audit($db, $uid, 'create', 'saved_report', $reportId, null, ['name' => $name, 'base_entity' => $baseEntity, 'display_type' => $displayType]);
        flash('Report gespeichert.');
        redirect('/?page=reports');
    }

    if ($action === 'update_report') {
        $id = (int) ($_POST['report_id'] ?? 0);
        $name = trim((string) ($_POST['report_name'] ?? ''));
        $description = trim((string) ($_POST['report_description'] ?? '')) ?: null;
        $baseEntity = array_key_exists((string) ($_POST['base_entity'] ?? ''), reportBaseOptions()) ? (string) $_POST['base_entity'] : 'jobs';
        $displayType = array_key_exists((string) ($_POST['display_type'] ?? ''), reportDisplayOptions()) ? (string) $_POST['display_type'] : 'table';
        $old = dbOne($db, 'SELECT id, name, description, base_entity, display_type FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$id, userId()]);
        if (!$old || $name === '') {
            flash('Report konnte nicht aktualisiert werden.', 'danger');
            redirect('/?page=reports');
        }
        $uid = userId();
        $stmt = $db->prepare('UPDATE saved_reports SET name=?, description=?, base_entity=?, display_type=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ssssii', $name, $description, $baseEntity, $displayType, $id, $uid);
        $stmt->execute();
        saveReportSettings($db, $id, $baseEntity);
        audit($db, $uid, 'update', 'saved_report', $id, $old, ['name' => $name, 'description' => $description, 'base_entity' => $baseEntity, 'display_type' => $displayType]);
        flash('Report aktualisiert.');
        redirect('/?page=reports&edit_report=' . $id);
    }

    if ($action === 'delete_report') {
        $id = (int) ($_POST['report_id'] ?? 0);
        $old = dbOne($db, 'SELECT id, name, base_entity, display_type FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$id, userId()]);
        if ($old) {
            $uid = userId();
            cleanupReportCascade($db, $uid, $id);
            $stmt = $db->prepare('DELETE FROM saved_reports WHERE id=? AND owner_user_id=?');
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'saved_report', $id, $old, null);
        }
        flash('Report gelöscht.');
        redirect('/?page=reports');
    }

    if ($action === 'request_cleanup') {
        $cutoff = trim((string) ($_POST['cutoff_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cutoff)) {
            flash('Stichtag ist erforderlich.', 'danger');
            redirect('/?page=privacy');
        }
        $preview = cleanupPreview($db, userId(), $cutoff);
        $previewJson = json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $status = 'requested';
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO cleanup_requests (user_id, cutoff_date, status, preview_json, requested_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->bind_param('isss', $uid, $cutoff, $status, $previewJson);
        $stmt->execute();
        audit($db, $uid, 'create', 'cleanup_request', (int) $stmt->insert_id, null, $preview);
        flash(tr('privacy.cleanup_requested'));
        redirect('/?page=privacy');
    }

    if ($action === 'save_job_platform') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $platformId = (int)($_POST['platform_id'] ?? 0);
        $name = trim((string)($_POST['platform_name'] ?? ''));
        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        $template = trim((string)($_POST['search_url_template'] ?? ''));
        $notes = trim((string)($_POST['platform_notes'] ?? ''));
        $sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        if ($name === '' || $template === '' || !str_contains($template, '{q}')) {
            flash('Name und Such-URL mit Platzhalter {q} sind erforderlich.', 'danger');
            redirect('/?page=admin_job_platforms');
        }
        if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            flash('Basis-URL ist ungültig.', 'danger');
            redirect('/?page=admin_job_platforms');
        }
        if ($platformId > 0) {
            $old = dbOne($db, 'SELECT * FROM job_platforms WHERE id=? AND deleted_at IS NULL', 'i', [$platformId]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $stmt = $db->prepare('UPDATE job_platforms SET name=?, base_url=?, search_url_template=?, notes=?, sort_order=?, is_active=? WHERE id=?');
            $stmt->bind_param('ssssiii', $name, $baseUrl, $template, $notes, $sortOrder, $isActive, $platformId);
            $stmt->execute();
            audit($db, realUserId(), 'update', 'job_platform', $platformId, $old, ['name'=>$name,'is_active'=>$isActive]);
            flash('Jobplattform gespeichert.');
        } else {
            $stmt = $db->prepare('INSERT INTO job_platforms (name, base_url, search_url_template, notes, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssii', $name, $baseUrl, $template, $notes, $sortOrder, $isActive);
            $stmt->execute();
            audit($db, realUserId(), 'create', 'job_platform', (int)$stmt->insert_id, null, ['name'=>$name,'is_active'=>$isActive]);
            flash('Jobplattform erstellt.');
        }
        redirect('/?page=admin_job_platforms');
    }

    if ($action === 'delete_job_platform') {
        if (!isAdmin($db, realUserId(), $config)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $platformId = (int)($_POST['platform_id'] ?? 0);
        $old = dbOne($db, 'SELECT * FROM job_platforms WHERE id=? AND deleted_at IS NULL', 'i', [$platformId]);
        if ($old) {
            $stmt = $db->prepare('UPDATE job_platforms SET deleted_at=NOW(), is_active=0 WHERE id=?');
            $stmt->bind_param('i', $platformId);
            $stmt->execute();
            audit($db, realUserId(), 'delete', 'job_platform', $platformId, $old, null);
            flash('Jobplattform deaktiviert.');
        }
        redirect('/?page=admin_job_platforms');
    }

    if ($action === 'generate_platform_search') {
        try {
            $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [userId()]) ?: [];
            $query = trim((string)($_POST['search_query'] ?? ''));
            if ($query === '') {
                $query = jobPreferenceQuery($preference);
            }
            $location = jobPreferenceLocation($preference, is_array($currentUser) ? $currentUser : []);
            $platformIds = array_values(array_filter(array_map('intval', (array)($_POST['platform_ids'] ?? []))));
            $total = min(100, max(1, (int)($_POST['total_count'] ?? 15)));
            if ($query === '') {
                flash('Bitte einen Suchbegriff erfassen oder im Profil gewünschte Tätigkeiten / Rollen pflegen.', 'warning');
                redirect('/?page=job_platform_search');
            }
            if (!$platformIds) {
                flash('Bitte mindestens ein Portal auswählen.', 'warning');
                redirect('/?page=job_platform_search');
            }
            $platformIdSql = implode(',', array_map('intval', array_unique($platformIds)));
            $platforms = dbAll($db, "SELECT * FROM job_platforms WHERE id IN ($platformIdSql) AND is_active=1 AND deleted_at IS NULL ORDER BY sort_order, name");
            if (!$platforms) {
                flash('Keine aktiven Portale gefunden.', 'warning');
                redirect('/?page=job_platform_search');
            }
            $perPlatform = max(1, (int)ceil($total / count($platforms)));
            $results = [];
            foreach ($platforms as $platform) {
                $results[] = [
                    'platform_id' => (int)$platform['id'],
                    'name' => (string)$platform['name'],
                    'limit' => $perPlatform,
                    'query' => $query,
                    'location' => $location,
                    'url' => platformSearchUrl($platform, $query, $location, $perPlatform),
                ];
            }
            $_SESSION['platform_search_results'] = $results;
            flash(tr('job_search.package_created'));
            redirect('/?page=job_platform_search#results');
        } catch (Throwable $exception) {
            error_log('Job platform search failed: ' . $exception->getMessage());
            flash(tr('job_search.package_failed'), 'danger');
            redirect('/?page=job_platform_search');
        }
    }

    if ($action === 'prepare_platform_import') {
        $results = is_array($_SESSION['platform_search_results'] ?? null) ? $_SESSION['platform_search_results'] : [];
        $_SESSION['platform_import_payload'] = implode("\n", array_map(static fn(array $row): string => (string)($row['url'] ?? ''), $results));
        flash(tr('job_search.links_moved_to_import'));
        redirect('/?page=jobs#quick-import');
    }

    if ($action === 'preview_import') {
        $payload = trim((string) ($_POST['import_payload'] ?? ''));
        if ($payload === '') {
            flash('Bitte eine Stellen-URL oder den Ausschreibungstext einfügen.', 'danger');
            redirect('/?page=jobs');
        }
        $rawImportUrls = extractImportUrls($payload);
        $importUrls = [];
        foreach ($rawImportUrls as $importUrl) {
            foreach (importDiscoverDetailUrls($importUrl) as $detailUrl) {
                $importUrls[$detailUrl] = $detailUrl;
            }
        }
        $importUrls = array_values($importUrls);
        if (count($importUrls) > 1) {
            $created = 0; $skipped = 0; $failed = 0; $uid = userId(); $failReasons = [];
            foreach ($importUrls as $sourceUrl) {
                try {
                    $draft = importFromUrl($sourceUrl);
                    $sourceUrl = (string) ($draft['source_url'] ?? $sourceUrl);
                    $title = trim((string) ($draft['title'] ?? ''));
                    if ($title === '') {
                        $failed++;
                        if (count($failReasons) < 3) {
                            $failReasons[] = (parse_url($sourceUrl, PHP_URL_HOST) ?: $sourceUrl) . ': kein Titel erkannt';
                        }
                        continue;
                    }
                    $companyName = trim((string) ($draft['company'] ?? '')) ?: tr('jobs.new_company_from_import');
                    $companyId = importUpsertCompany($db, $uid, $companyName);
                    $existing = dbOne($db, 'SELECT j.id,j.title,c.name AS company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.source_url=? AND j.deleted_at IS NULL LIMIT 1', 'is', [$uid, $sourceUrl]);
                    if ($existing) {
                        importRepairExistingJob($db, $uid, $existing, $draft, $companyId);
                        $skipped++;
                        continue;
                    }
                    $location = trim((string) ($draft['location'] ?? $draft['location_text'] ?? ''));
                    $description = trim((string) ($draft['description'] ?? $sourceUrl));
                    $status = 'open'; $workplace = 'unknown'; $engagement = 'permanent'; $term = 'unknown';
                    $pdfStatus = $sourceUrl !== '' ? 'pending' : 'none';
                    $pdfRequestedAt = $sourceUrl !== '' ? date('Y-m-d H:i:s') : null;
                    $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, source_url, original_pdf_status, original_pdf_requested_at, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('iissssssssss', $uid, $companyId, $title, $location, $status, $workplace, $engagement, $term, $sourceUrl, $pdfStatus, $pdfRequestedAt, $description);
                    $stmt->execute();
                    $jobId = (int) $stmt->insert_id;
                    audit($db, $uid, 'create', 'job', $jobId, null, ['title' => $title, 'company_id' => $companyId, 'source_url' => $sourceUrl]);
                    $created++;
                } catch (Throwable $exception) {
                    $failed++;
                    if (count($failReasons) < 3) {
                        $failReasons[] = (parse_url($sourceUrl, PHP_URL_HOST) ?: $sourceUrl) . ': ' . $exception->getMessage();
                    }
                }
            }
            $message = $created . ' Jobs importiert, ' . $skipped . ' Dubletten übersprungen, ' . $failed . ' fehlgeschlagen.';
            if ($failReasons) {
                $message .= ' Fehlerbeispiele: ' . implode(' | ', $failReasons);
            }
            flash($message);
            redirect('/?page=jobs');
        }
        try {
            $_SESSION['import_draft'] = count($importUrls) === 1 && importPayloadIsUrlOnly($payload, $importUrls)
                ? importFromUrl($importUrls[0])
                : importFromText($payload);
            flash('Import gelesen. Bitte Vorschlag prüfen und speichern.');
        } catch (Throwable $exception) {
            flash('Import nicht möglich: ' . $exception->getMessage(), 'danger');
        }
        redirect('/?page=jobs#new');
    }

    if ($action === 'save_profile') {
        $uid = userId();
        $storedUser = dbOne($db, 'SELECT first_name,last_name,preferred_language,timezone,phone,mobile,linkedin_url,facebook_url,x_url,other_profile_url,city,region,country_code FROM users WHERE id=?', 'i', [$uid]);
        $first = trim((string) ($_POST['first_name'] ?? ''));
        $last = trim((string) ($_POST['last_name'] ?? ''));
        $language = normalizeLocale((string) ($_POST['preferred_language'] ?? ($storedUser['preferred_language'] ?? 'de-CH')));
        $validTimezones = array_keys(array_merge(...array_values(timezoneChoices())));
        $timezone = in_array($_POST['timezone'] ?? '', $validTimezones, true) ? (string) $_POST['timezone'] : 'Europe/Zurich';
        $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
        $mobile = trim((string) ($_POST['mobile'] ?? '')) ?: null;
        $linkedinUrl = trim((string) ($_POST['linkedin_url'] ?? '')) ?: null;
        $facebookUrl = trim((string) ($_POST['facebook_url'] ?? '')) ?: null;
        $xUrl = trim((string) ($_POST['x_url'] ?? '')) ?: null;
        $otherProfileUrl = trim((string) ($_POST['other_profile_url'] ?? '')) ?: null;
        $city = trim((string) ($_POST['city'] ?? '')) ?: null;
        [$country, $region] = countryForRegion((string) ($_POST['region_key'] ?? ''));
        if ($first === '' || $last === '') {
            flash('Vorname und Nachname sind erforderlich.', 'danger');
            redirect('/?page=profile');
        }
        foreach (['LinkedIn'=>$linkedinUrl, 'Facebook'=>$facebookUrl, 'X'=>$xUrl, 'Andere'=>$otherProfileUrl] as $label => $url) {
            if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
                flash($label . '-URL ist ungültig.', 'danger');
                redirect('/?page=profile');
            }
        }
        $old = $storedUser;
        $stmt = $db->prepare('UPDATE users SET first_name=?, last_name=?, preferred_language=?, timezone=?, phone=?, mobile=?, linkedin_url=?, facebook_url=?, x_url=?, other_profile_url=?, city=?, region=?, country_code=? WHERE id=?');
        $stmt->bind_param('sssssssssssssi', $first, $last, $language, $timezone, $phone, $mobile, $linkedinUrl, $facebookUrl, $xUrl, $otherProfileUrl, $city, $region, $country, $uid);
        $stmt->execute();
        $allowedRemote = ['onsite','hybrid','remote','any'];
        $allowedEmployment = ['full_time','part_time','temporary','contract','internship','freelance'];
        $allowedSalaryPeriods = ['hour','month','year'];
        $desiredRoles = trim((string) ($_POST['desired_roles'] ?? '')) ?: null;
        $desiredLocations = trim((string) ($_POST['desired_locations'] ?? '')) ?: null;
        $remotePreference = in_array($_POST['remote_preference'] ?? '', $allowedRemote, true) ? (string) $_POST['remote_preference'] : 'any';
        $employmentTypes = array_values(array_intersect((array) ($_POST['employment_types'] ?? []), $allowedEmployment));
        $employmentTypeSet = $employmentTypes ? implode(',', $employmentTypes) : null;
        $workloadMin = $_POST['workload_min'] !== '' ? min(100, max(0, (int) $_POST['workload_min'])) : null;
        $workloadMax = $_POST['workload_max'] !== '' ? min(100, max(0, (int) $_POST['workload_max'])) : null;
        $salaryMin = $_POST['salary_min'] !== '' ? (float) $_POST['salary_min'] : null;
        $salaryMax = null;
        $salaryCurrency = currencyForCountry($country ?: ($storedUser['country_code'] ?? 'CH'));
        $salaryPeriod = in_array($_POST['salary_period'] ?? '', $allowedSalaryPeriods, true) ? (string) $_POST['salary_period'] : 'year';
        $desiredLevel = trim((string) ($_POST['desired_level'] ?? '')) ?: null;
        $desiredBenefits = trim((string) ($_POST['desired_benefits'] ?? '')) ?: null;
        $excludedIndustries = trim((string) ($_POST['excluded_industries'] ?? '')) ?: null;
        $willingToRelocate = !empty($_POST['willing_to_relocate']) ? 1 : 0;
        $travelPercentage = $_POST['travel_percentage'] !== '' ? min(100, max(0, (int) $_POST['travel_percentage'])) : null;
        $availableFrom = trim((string) ($_POST['available_from'] ?? '')) ?: null;
        $preferenceNotes = trim((string) ($_POST['preference_notes'] ?? '')) ?: null;
        $oldPreference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [$uid]);
        if ($oldPreference) {
            $preferenceId = (int) $oldPreference['id'];
            $prefStmt = $db->prepare('UPDATE user_preferences SET desired_roles=?, desired_locations=?, remote_preference=?, employment_types=?, workload_min=?, workload_max=?, salary_min=?, salary_max=?, salary_currency=?, salary_period=?, desired_level=?, desired_benefits=?, excluded_industries=?, willing_to_relocate=?, travel_percentage=?, available_from=?, notes=? WHERE id=? AND user_id=?');
            $prefStmt->bind_param('ssssiiddsssssiissii', $desiredRoles, $desiredLocations, $remotePreference, $employmentTypeSet, $workloadMin, $workloadMax, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $desiredLevel, $desiredBenefits, $excludedIndustries, $willingToRelocate, $travelPercentage, $availableFrom, $preferenceNotes, $preferenceId, $uid);
            $prefStmt->execute();
        } else {
            $title = 'Default';
            $prefStmt = $db->prepare('INSERT INTO user_preferences (user_id, title, desired_roles, desired_locations, remote_preference, employment_types, workload_min, workload_max, salary_min, salary_max, salary_currency, salary_period, desired_level, desired_benefits, excluded_industries, willing_to_relocate, travel_percentage, available_from, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $prefStmt->bind_param('isssssiiddsssssiiss', $uid, $title, $desiredRoles, $desiredLocations, $remotePreference, $employmentTypeSet, $workloadMin, $workloadMax, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $desiredLevel, $desiredBenefits, $excludedIndustries, $willingToRelocate, $travelPercentage, $availableFrom, $preferenceNotes);
            $prefStmt->execute();
        }
        $languageCodes = (array) ($_POST['language_codes'] ?? []);
        $languageLevels = (array) ($_POST['language_levels'] ?? []);
        $removeLanguageIndexes = array_map('intval', (array) ($_POST['remove_language_indexes'] ?? []));
        $validLevels = ['A1','A2','B1','B2','C1','C2'];
        $languageChoices = europeanLanguageChoices();
        $languageSkillMap = [];
        $languageErrors = [];
        $seenLanguageCodes = [];
        foreach ($languageCodes as $index => $code) {
            $code = (string) $code;
            $level = (string) ($languageLevels[$index] ?? '');
            if (in_array((int)$index, $removeLanguageIndexes, true) || ($code === '' && $level === '')) {
                continue;
            }
            if (!isset($languageChoices[$code]) || !in_array($level, $validLevels, true)) {
                $languageErrors[] = 'Bitte bei jedem Sprachrecord Sprache und Niveau auswählen.';
                continue;
            }
            if (isset($seenLanguageCodes[$code])) {
                $languageErrors[] = 'Sprache "' . $languageChoices[$code] . '" ist doppelt erfasst.';
                continue;
            }
            $seenLanguageCodes[$code] = true;
            $languageSkillMap[$code] = $level;
        }
        if (!$languageSkillMap) {
            $languageErrors[] = 'Bitte mindestens eine Sprache erfassen.';
        }
        if (!in_array('C2', $languageSkillMap, true)) {
            $languageErrors[] = 'Bitte mindestens eine Muttersprache mit Niveau C2 erfassen.';
        }
        if ($languageErrors) {
            flash(implode(' ', array_unique($languageErrors)), 'danger');
            redirect('/?page=profile');
        }
        $db->query('DELETE FROM user_language_skills WHERE user_id=' . $uid);
        $skillStmt = $db->prepare('INSERT INTO user_language_skills (user_id, language_code, language_name, cefr_level) VALUES (?, ?, ?, ?)');
        $savedLanguageSkills = [];
        foreach ($languageSkillMap as $code => $level) {
            $name = $languageChoices[$code];
            $skillStmt->bind_param('isss', $uid, $code, $name, $level);
            $skillStmt->execute();
            $savedLanguageSkills[$code] = $level;
        }
        $_SESSION['user_name'] = $first . ' ' . $last;
        $_SESSION['locale'] = $language;
        audit($db, $uid, 'update', 'profile', $uid, $old, ['first_name'=>$first,'last_name'=>$last,'preferred_language'=>$language,'timezone'=>$timezone,'language_skills'=>$savedLanguageSkills]);
        flash('Profil gespeichert.');
        redirect('/?page=profile');
    }

    if ($action === 'save_smtp_settings' || $action === 'test_smtp_settings') {
        requireLogin();
        $uid = userId();
        $host = trim((string) ($_POST['smtp_host'] ?? ''));
        $port = (int) ($_POST['smtp_port'] ?? 587);
        $encryption = in_array((string) ($_POST['smtp_encryption'] ?? 'tls'), ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_encryption'] : 'tls';
        $username = trim((string) ($_POST['smtp_username'] ?? '')) ?: null;
        $password = (string) ($_POST['smtp_password'] ?? '');
        $fromEmail = strtolower(trim((string) ($_POST['from_email'] ?? '')));
        $fromName = trim((string) ($_POST['from_name'] ?? '')) ?: null;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        if ($host === '' || $port < 1 || $port > 65535 || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            flash('SMTP-Host, Port und Absender-E-Mail sind erforderlich.', 'danger');
            redirect('/?page=profile#smtp');
        }
        $existing = dbOne($db, 'SELECT smtp_password_encrypted FROM user_smtp_settings WHERE user_id=? LIMIT 1', 'i', [$uid]);
        $encryptedPassword = $existing['smtp_password_encrypted'] ?? null;
        if ($password !== '') {
            $encryptedPassword = encryptSecret($config, $password);
        }
        if ($existing) {
            $stmt = $db->prepare('UPDATE user_smtp_settings SET smtp_host=?, smtp_port=?, smtp_encryption=?, smtp_username=?, smtp_password_encrypted=?, from_email=?, from_name=?, is_active=? WHERE user_id=?');
            $stmt->bind_param('sisssssii', $host, $port, $encryption, $username, $encryptedPassword, $fromEmail, $fromName, $isActive, $uid);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('INSERT INTO user_smtp_settings (user_id, smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_encrypted, from_email, from_name, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isisssssi', $uid, $host, $port, $encryption, $username, $encryptedPassword, $fromEmail, $fromName, $isActive);
            $stmt->execute();
        }
        audit($db, $uid, 'update', 'user_smtp_settings', $uid, null, ['smtp_host' => $host, 'from_email' => $fromEmail, 'is_active' => $isActive]);
        if ($action === 'test_smtp_settings') {
            try {
                $smtpTestUser = dbOne($db, 'SELECT email FROM users WHERE id=? AND deleted_at IS NULL', 'i', [$uid]);
                sendConfiguredMail($db, $config, $uid, (string) ($smtpTestUser['email'] ?? $fromEmail), 'JeMa Jobs SMTP-Test', "Hallo\n\nDer E-Mail-Versand über deine SMTP-Konfiguration funktioniert.\n");
                flash('SMTP gespeichert und Test-E-Mail versendet.');
            } catch (Throwable $exception) {
                flash('SMTP gespeichert, Test-E-Mail fehlgeschlagen: ' . $exception->getMessage(), 'danger');
            }
        } else {
            flash('SMTP-Einstellungen gespeichert.');
        }
        redirect('/?page=profile#smtp');
    }

    if ($action === 'upload_document') {
        $uid = userId();
        $replaceId = (int) ($_POST['replace_document_id'] ?? 0);
        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $documentLanguageInput = trim((string) ($_POST['document_language'] ?? ''));
        $languageCode = $documentLanguageInput !== '' ? normalizeLocale($documentLanguageInput) : null;
        $title = trim((string) ($_POST['document_title'] ?? ''));
        $description = trim((string) ($_POST['document_description'] ?? '')) ?: null;
        $validFrom = trim((string) ($_POST['valid_from'] ?? '')) ?: null;
        $validUntil = trim((string) ($_POST['valid_until'] ?? '')) ?: null;
        $scope = ($_POST['document_scope'] ?? '') === 'application' ? 'application' : 'profile';
        $applicationId = $scope === 'application' ? (int) ($_POST['application_id'] ?? 0) : null;
        $application = null;
        $jobId = null;
        $returnTo = (string) ($_POST['document_return'] ?? '');
        $redirectTarget = $scope === 'application' && $applicationId ? '/?page=applications&edit=' . $applicationId . '#documents' : ($returnTo === 'documents' ? '/?page=documents' : '/?page=profile#documents');
        if ($scope === 'application') {
            $application = dbOne($db, 'SELECT a.id, a.job_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL AND j.deleted_at IS NULL', 'ii', [$applicationId, $uid]);
            if (!$application) {
                flash(tr('applications.job_missing'), 'danger');
                redirect('/?page=applications');
            }
            $jobId = (int) $application['job_id'];
        }
        $type = dbOne($db, 'SELECT id, code FROM document_types WHERE id=?', 'i', [$documentTypeId]);
        if (!$type || !in_array((string) $type['code'], allowedDocumentTypeCodes($scope), true) || $title === '') {
            flash('Dokumenttyp und Titel sind erforderlich.', 'danger');
            redirect($redirectTarget);
        }
        try {
            $uploaded = uploadDocumentFile($_FILES['user_document'] ?? [], $uid);
            $version = 1;
            if ($replaceId > 0) {
                $oldDoc = dbOne($db, 'SELECT id, title, document_type_id, version, scope, application_id FROM user_documents WHERE id=? AND user_id=? AND scope=? AND deleted_at IS NULL', 'iis', [$replaceId, $uid, $scope]);
                if ($oldDoc) {
                    if ($scope === 'application' && (int) ($oldDoc['application_id'] ?? 0) !== $applicationId) {
                        flash(tr('applications.document_wrong_application'), 'danger');
                        redirect($redirectTarget);
                    }
                    $documentTypeId = (int) $oldDoc['document_type_id'];
                    $title = (string) $oldDoc['title'];
                    $version = (int) $oldDoc['version'] + 1;
                    $stmt = $db->prepare('UPDATE user_documents SET is_current=0 WHERE id=? AND user_id=? AND scope=?');
                    $stmt->bind_param('iis', $oldDoc['id'], $uid, $scope);
                    $stmt->execute();
                }
            } else {
                $existing = $scope === 'application'
                    ? dbOne($db, 'SELECT MAX(version) max_version FROM user_documents WHERE user_id=? AND scope=? AND application_id=? AND document_type_id=? AND title=?', 'isiis', [$uid, $scope, $applicationId, $documentTypeId, $title])
                    : dbOne($db, 'SELECT MAX(version) max_version FROM user_documents WHERE user_id=? AND scope=? AND document_type_id=? AND title=?', 'isis', [$uid, $scope, $documentTypeId, $title]);
                $version = ((int) ($existing['max_version'] ?? 0)) + 1;
                if ($scope === 'application') {
                    $stmt = $db->prepare('UPDATE user_documents SET is_current=0 WHERE user_id=? AND scope=? AND application_id=? AND document_type_id=? AND title=? AND deleted_at IS NULL');
                    $stmt->bind_param('isiis', $uid, $scope, $applicationId, $documentTypeId, $title);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare('UPDATE user_documents SET is_current=0 WHERE user_id=? AND scope=? AND document_type_id=? AND title=? AND deleted_at IS NULL');
                    $stmt->bind_param('isis', $uid, $scope, $documentTypeId, $title);
                    $stmt->execute();
                }
            }
            $stmt = $db->prepare('INSERT INTO user_documents (user_id, document_type_id, language_code, scope, application_id, job_id, title, description, original_filename, storage_path, mime_type, file_size, sha256, valid_from, valid_until, version, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)');
            $stmt->bind_param('iissiisssssisssi', $uid, $documentTypeId, $languageCode, $scope, $applicationId, $jobId, $title, $description, $uploaded['original'], $uploaded['path'], $uploaded['mime'], $uploaded['size'], $uploaded['sha256'], $validFrom, $validUntil, $version);
            $stmt->execute();
            $newDocumentId = (int) $stmt->insert_id;
            $extractStatus = in_array($uploaded['mime'], ['text/plain','application/pdf'], true) ? 'pending' : 'skipped';
            $textStmt = $db->prepare('INSERT INTO document_texts (user_document_id, extraction_status, language_code) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE extraction_status=VALUES(extraction_status), language_code=VALUES(language_code)');
            $textStmt->bind_param('iss', $newDocumentId, $extractStatus, $languageCode);
            $textStmt->execute();
            if ($scope === 'application') {
                $purpose = documentPurposeForType((string) $type['code']);
                $linkStmt = $db->prepare('INSERT IGNORE INTO application_documents (application_id, user_document_id, purpose, sort_order) VALUES (?, ?, ?, 0)');
                $linkStmt->bind_param('iis', $applicationId, $newDocumentId, $purpose);
                $linkStmt->execute();
            }
            audit($db, $uid, 'create', 'user_document', $newDocumentId, null, ['title'=>$title,'version'=>$version,'scope'=>$scope,'application_id'=>$applicationId]);
            flash('Dokument gespeichert.');
        } catch (Throwable $exception) {
            flash('Dokument konnte nicht gespeichert werden: ' . $exception->getMessage(), 'danger');
        }
        redirect($redirectTarget);
    }

    if ($action === 'update_document') {
        $id = (int) ($_POST['document_id'] ?? 0);
        $uid = userId();
        $documentTypeId = (int) ($_POST['document_type_id'] ?? 0);
        $documentLanguageInput = trim((string) ($_POST['document_language'] ?? ''));
        $languageCode = $documentLanguageInput !== '' ? normalizeLocale($documentLanguageInput) : null;
        $title = trim((string) ($_POST['document_title'] ?? ''));
        $description = trim((string) ($_POST['document_description'] ?? '')) ?: null;
        $validFrom = trim((string) ($_POST['valid_from'] ?? '')) ?: null;
        $validUntil = trim((string) ($_POST['valid_until'] ?? '')) ?: null;
        $old = dbOne($db, "SELECT id, document_type_id, language_code, title, description, valid_from, valid_until FROM user_documents WHERE id=? AND user_id=? AND scope='profile' AND deleted_at IS NULL", 'ii', [$id, $uid]);
        $type = dbOne($db, 'SELECT id, code FROM document_types WHERE id=?', 'i', [$documentTypeId]);
        if (!$old || !$type || !in_array((string) $type['code'], allowedDocumentTypeCodes('profile'), true) || $title === '') {
            flash('Dokument konnte nicht aktualisiert werden.', 'danger');
            redirect('/?page=documents');
        }
        $stmt = $db->prepare('UPDATE user_documents SET document_type_id=?, language_code=?, title=?, description=?, valid_from=?, valid_until=? WHERE id=? AND user_id=?');
        $stmt->bind_param('isssssii', $documentTypeId, $languageCode, $title, $description, $validFrom, $validUntil, $id, $uid);
        $stmt->execute();
        audit($db, $uid, 'update', 'user_document', $id, $old, ['document_type_id'=>$documentTypeId,'language_code'=>$languageCode,'title'=>$title,'description'=>$description,'valid_from'=>$validFrom,'valid_until'=>$validUntil]);
        flash('Dokument aktualisiert.');
        redirect('/?page=documents&edit_document=' . $id);
    }

    if ($action === 'delete_document') {
        $id = (int) ($_POST['id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id,title,version,scope,application_id FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, $uid]);
        if ($old) {
            cleanupDocumentCascade($db, $uid, $id);
            $stmt = $db->prepare('UPDATE user_documents SET deleted_at=NOW(), is_current=0 WHERE id=? AND user_id=?');
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'user_document', $id, $old, null);
            flash('Dokument gelöscht.');
        }
        $returnTo = (string) ($_POST['document_return'] ?? '');
        $target = $old && ($old['scope'] ?? '') === 'application' && !empty($old['application_id'])
            ? '/?page=applications&edit=' . (int) $old['application_id'] . '#documents'
            : ($returnTo === 'documents' ? '/?page=documents' : '/?page=profile#documents');
        redirect($target);
    }

    if ($action === 'attach_application_document') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $documentIds = array_map('intval', (array) ($_POST['user_document_ids'] ?? []));
        $documentId = (int) ($_POST['user_document_id'] ?? 0);
        if ($documentId > 0) {
            $documentIds[] = $documentId;
        }
        $documentIds = array_values(array_unique(array_filter($documentIds)));
        $purpose = in_array($_POST['purpose'] ?? '', ['cv','cover_letter','certificate','reference','portfolio','other'], true) ? (string) $_POST['purpose'] : 'other';
        $application = dbOne($db, 'SELECT id FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$applicationId, userId()]);
        $attached = 0;
        if ($application && $documentIds) {
            foreach ($documentIds as $documentId) {
                $document = dbOne($db, "SELECT d.id, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.id=? AND d.user_id=? AND d.scope='profile' AND d.is_current=1 AND d.deleted_at IS NULL", 'ii', [$documentId, userId()]);
                if (!$document) {
                    continue;
                }
                $purpose = documentPurposeForType((string) $document['type_code']);
                $stmt = $db->prepare('INSERT IGNORE INTO application_documents (application_id, user_document_id, purpose, sort_order) VALUES (?, ?, ?, 0)');
                $stmt->bind_param('iis', $applicationId, $documentId, $purpose);
                $stmt->execute();
                $attached += max(0, $stmt->affected_rows);
                audit($db, userId(), 'create', 'application_document', $documentId, null, ['application_id'=>$applicationId,'purpose'=>$purpose]);
            }
            flash($attached > 0 ? tr('applications.documents_attached', null, ['count' => (string) $attached]) : tr('applications.documents_already_attached'));
        }
        redirect('/?page=applications&edit=' . $applicationId . '#documents');
    }

    if ($action === 'detach_application_document') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $documentId = (int) ($_POST['user_document_id'] ?? 0);
        $stmt = $db->prepare('DELETE ad FROM application_documents ad JOIN applications a ON a.id=ad.application_id WHERE ad.application_id=? AND ad.user_document_id=? AND a.user_id=?');
        $uid = userId();
        $stmt->bind_param('iii', $applicationId, $documentId, $uid);
        $stmt->execute();
        audit($db, $uid, 'delete', 'application_document', $documentId, ['application_id'=>$applicationId], null);
        flash('Dokument-Zuordnung entfernt.');
        redirect('/?page=applications&edit=' . $applicationId . '#documents');
    }

    if ($action === 'save_company') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) $_POST['name']);
        $city = trim((string) $_POST['city']);
        $website = trim((string) $_POST['website']);
        $phone = trim((string) ($_POST['company_phone'] ?? ''));
        $notes = trim((string) ($_POST['company_notes'] ?? ''));
        $addressLines = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($_POST['address'] ?? '')))));
        $addressLine1 = $addressLines[0] ?? '';
        $addressLine2 = implode("\n", array_slice($addressLines, 1));
        $postalCode = trim((string) ($_POST['postal_code'] ?? ''));
        [$countryCode, $region] = countryForRegion((string) ($_POST['company_region_key'] ?? ''));
        $countryCode = $countryCode ?? '';
        $region = $region ?? '';
        $isIntermediary = !empty($_POST['is_intermediary']) ? 1 : 0;
        if ($name === '') {
            flash('Firmenname ist erforderlich.', 'danger');
            redirect('/?page=companies');
        }
        if ($id > 0) {
            $old = dbOne($db, 'SELECT * FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) {
                http_response_code(404); exit('Not found');
            }
            $stmt = $db->prepare('UPDATE companies SET name = ?, city = ?, website = ?, phone = ?, address_line1 = ?, address_line2 = ?, postal_code = ?, region = ?, country_code = ?, notes = ?, is_intermediary = ? WHERE id = ? AND owner_user_id = ?');
            $uid = userId();
            $stmt->bind_param('ssssssssssiii', $name, $city, $website, $phone, $addressLine1, $addressLine2, $postalCode, $region, $countryCode, $notes, $isIntermediary, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'company', $id, $old, ['name' => $name, 'city' => $city, 'website' => $website, 'phone' => $phone, 'address_line1' => $addressLine1, 'notes' => $notes, 'is_intermediary' => $isIntermediary]);
        } else {
            $stmt = $db->prepare('INSERT INTO companies (owner_user_id, name, city, website, phone, address_line1, address_line2, postal_code, region, country_code, notes, is_intermediary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('issssssssssi', $uid, $name, $city, $website, $phone, $addressLine1, $addressLine2, $postalCode, $region, $countryCode, $notes, $isIntermediary);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'company', $id, null, ['name' => $name, 'city' => $city, 'website' => $website, 'phone' => $phone, 'address_line1' => $addressLine1, 'notes' => $notes, 'is_intermediary' => $isIntermediary]);
        }
        flash('Firma gespeichert.');
        redirect('/?page=companies');
    }

    if ($action === 'delete_company') {
        $id = (int) $_POST['id'];
        $old = dbOne($db, 'SELECT * FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $uid = userId();
            cleanupCompanyCascade($db, $uid, $id);
            $stmt = $db->prepare('UPDATE companies SET deleted_at = NOW() WHERE id = ? AND owner_user_id = ?');
            $stmt->bind_param('ii', $id, $uid); $stmt->execute();
            audit($db, userId(), 'delete', 'company', $id, $old, null);
        }
        flash('Firma gelöscht.');
        redirect('/?page=companies');
    }

    if ($action === 'save_job') {
        $id = (int) ($_POST['id'] ?? 0);
        $companyId = (int) $_POST['company_id'];
        $newCompanyName = trim((string) ($_POST['new_company_name'] ?? ''));
        $title = trim((string) $_POST['title']);
        $location = trim((string) $_POST['location_text']);
        $description = trim((string) $_POST['description']);
        $jobNotes = trim((string) ($_POST['job_notes'] ?? ''));
        $status = (string) $_POST['status'];
        $workplace = (string) $_POST['workplace_type'];
        $engagementType = in_array($_POST['engagement_type'] ?? '', ['permanent','temporary'], true) ? (string) $_POST['engagement_type'] : 'permanent';
        $contractTerm = in_array($_POST['contract_term'] ?? '', ['open_ended','fixed_term','unknown'], true) ? (string) $_POST['contract_term'] : 'unknown';
        $fixedTermStart = trim((string) ($_POST['fixed_term_start'] ?? '')) ?: null;
        $fixedTermEnd = trim((string) ($_POST['fixed_term_end'] ?? '')) ?: null;
        if ($contractTerm !== 'fixed_term') { $fixedTermStart = null; $fixedTermEnd = null; }
        $sourceUrl = trim((string) $_POST['source_url']);
        $salaryMin = trim((string)($_POST['salary_min'] ?? '')) !== '' ? (float)$_POST['salary_min'] : null;
        $salaryMax = null;
        $salaryCurrency = currencyForCountry($currentUser['country_code'] ?? 'CH');
        $salaryPeriod = in_array((string)($_POST['salary_period'] ?? ''), ['hour','month','year'], true) ? (string)$_POST['salary_period'] : 'year';
        if ($companyId < 1 && $newCompanyName !== '') {
            $existingCompany = dbOne($db, 'SELECT id FROM companies WHERE owner_user_id = ? AND name = ? AND deleted_at IS NULL', 'is', [userId(), $newCompanyName]);
            if ($existingCompany) {
                $companyId = (int) $existingCompany['id'];
            } else {
                $companyStmt = $db->prepare('INSERT INTO companies (owner_user_id, name) VALUES (?, ?)');
                $uid = userId();
                $companyStmt->bind_param('is', $uid, $newCompanyName);
                $companyStmt->execute();
                $companyId = (int) $companyStmt->insert_id;
                audit($db, userId(), 'create', 'company', $companyId, null, ['name' => $newCompanyName, 'source' => 'job_import']);
            }
        }
        $company = dbOne($db, 'SELECT id FROM companies WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$companyId, userId()]);
        if (!$company || $title === '') {
            flash('Firma und Jobtitel sind erforderlich.', 'danger'); redirect('/?page=jobs');
        }
        $duplicate = dbOne(
            $db,
            'SELECT id, title FROM jobs WHERE owner_user_id = ? AND company_id = ? AND title = ? AND id <> ? AND deleted_at IS NULL',
            'iisi', [userId(), $companyId, $title, $id]
        );
        if ($duplicate && empty($_POST['confirm_duplicate'])) {
            flash('Mögliche Dublette gefunden: ' . $duplicate['title'] . '. Zum Speichern Dublette bestätigen.', 'warning');
            redirect('/?page=jobs&duplicate=1');
        }
        if ($id > 0) {
            $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, salary_min, salary_max, salary_currency, salary_period, source_url, original_pdf_status, original_pdf_requested_at, original_pdf_rendered_at, original_pdf_error, SUBSTRING(description,1,65535) description, SUBSTRING(notes,1,65535) notes FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $pdfStatus = (string) ($old['original_pdf_status'] ?? 'none');
            $pdfRequestedAt = $old['original_pdf_requested_at'] ?? null;
            $pdfRenderedAt = $old['original_pdf_rendered_at'] ?? null;
            $pdfError = $old['original_pdf_error'] ?? null;
            if ($sourceUrl === '') {
                $pdfStatus = 'none';
                $pdfRequestedAt = null;
                $pdfRenderedAt = null;
                $pdfError = null;
            } elseif ($sourceUrl !== (string) ($old['source_url'] ?? '')) {
                $pdfStatus = 'pending';
                $pdfRequestedAt = date('Y-m-d H:i:s');
                $pdfRenderedAt = null;
                $pdfError = null;
            }
            $stmt = $db->prepare('UPDATE jobs SET company_id=?, title=?, location_text=?, description=?, notes=?, status=?, workplace_type=?, engagement_type=?, contract_term=?, fixed_term_start=?, fixed_term_end=?, salary_min=?, salary_max=?, salary_currency=?, salary_period=?, source_url=?, original_pdf_status=?, original_pdf_requested_at=?, original_pdf_rendered_at=?, original_pdf_error=? WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('issssssssssddsssssssii', $companyId, $title, $location, $description, $jobNotes, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $sourceUrl, $pdfStatus, $pdfRequestedAt, $pdfRenderedAt, $pdfError, $id, $uid);
            $stmt->execute();
            audit($db, userId(), 'update', 'job', $id, $old, ['title' => $title, 'status' => $status, 'salary_min' => $salaryMin, 'salary_max' => $salaryMax, 'notes' => $jobNotes, 'original_pdf_status' => $pdfStatus]);
        } else {
            $pdfStatus = $sourceUrl !== '' ? 'pending' : 'none';
            $pdfRequestedAt = $sourceUrl !== '' ? date('Y-m-d H:i:s') : null;
            $stmt = $db->prepare('INSERT INTO jobs (owner_user_id, company_id, title, location_text, description, notes, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, salary_min, salary_max, salary_currency, salary_period, source_url, original_pdf_status, original_pdf_requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $uid = userId();
            $stmt->bind_param('iissssssssssddsssss', $uid, $companyId, $title, $location, $description, $jobNotes, $status, $workplace, $engagementType, $contractTerm, $fixedTermStart, $fixedTermEnd, $salaryMin, $salaryMax, $salaryCurrency, $salaryPeriod, $sourceUrl, $pdfStatus, $pdfRequestedAt);
            $stmt->execute();
            $id = (int) $stmt->insert_id;
            audit($db, userId(), 'create', 'job', $id, null, ['title' => $title, 'status' => $status, 'salary_min' => $salaryMin, 'salary_max' => $salaryMax, 'notes' => $jobNotes, 'original_pdf_status' => $pdfStatus]);
        }
        flash('Job gespeichert.');
        unset($_SESSION['import_draft']);
        redirect('/?page=jobs');
    }

    if ($action === 'delete_job') {
        $id = (int) $_POST['id'];
        $old = dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, source_url, SUBSTRING(description,1,65535) description FROM jobs WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $uid = userId();
            cleanupJobCascade($db, $uid, $id);
            $stmt = $db->prepare('UPDATE jobs SET deleted_at = NOW() WHERE id = ? AND owner_user_id = ?');
            $stmt->bind_param('ii', $id, $uid); $stmt->execute();
            audit($db, userId(), 'delete', 'job', $id, $old, null);
        }
        flash('Job gelöscht.');
        redirect('/?page=jobs');
    }

    if ($action === 'save_job_question') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $question = trim((string) ($_POST['question_text'] ?? ''));
        $answer = trim((string) ($_POST['answer_text'] ?? ''));
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 0));
        $job = dbOne($db, 'SELECT id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$jobId, userId()]);
        if (!$job || $question === '') {
            flash('Frage konnte nicht gespeichert werden.', 'danger');
            redirect('/?page=jobs&edit=' . $jobId . '#job-questions');
        }
        $uid = userId();
        $stmt = $db->prepare('INSERT INTO job_questions (owner_user_id, job_id, question_text, answer_text, sort_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iissi', $uid, $jobId, $question, $answer, $sortOrder);
        $stmt->execute();
        audit($db, $uid, 'create', 'job_question', (int)$stmt->insert_id, null, ['job_id'=>$jobId,'question_text'=>$question]);
        flash('Frage gespeichert.');
        redirect('/?page=jobs&edit=' . $jobId . '#job-questions');
    }

    if ($action === 'delete_job_question') {
        $questionId = (int) ($_POST['question_id'] ?? 0);
        $question = dbOne($db, 'SELECT id, job_id, question_text FROM job_questions WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$questionId, userId()]);
        if ($question) {
            $stmt = $db->prepare('UPDATE job_questions SET deleted_at=NOW() WHERE id=? AND owner_user_id=?');
            $uid = userId();
            $stmt->bind_param('ii', $questionId, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'job_question', $questionId, $question, null);
            flash('Frage gelöscht.');
            redirect('/?page=jobs&edit=' . (int)$question['job_id'] . '#job-questions');
        }
        flash('Frage nicht gefunden.', 'danger');
        redirect('/?page=jobs');
    }

    if ($action === 'start_application') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $job = dbOne($db, 'SELECT id, title, source_url FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$jobId, userId()]);
        if (!$job) {
            flash('Diese Stelle ist nicht mehr verfügbar.', 'danger');
            redirect('/?page=jobs');
        }
        $existing = dbOne($db, 'SELECT id FROM applications WHERE user_id=? AND job_id=? AND deleted_at IS NULL', 'ii', [userId(), $jobId]);
        if ($existing) {
            redirect('/?page=applications&edit=' . (int) $existing['id'] . '#application-form');
        }
        $uid = userId();
        try {
            $db->begin_transaction();
            $applicationUrl = trim((string) ($job['source_url'] ?? '')) ?: null;
            $stmt = $db->prepare("INSERT INTO applications (user_id, job_id, status, channel, application_url, next_action) VALUES (?, ?, 'ready', 'website', ?, 'Online bewerben')");
            $stmt->bind_param('iis', $uid, $jobId, $applicationUrl);
            $stmt->execute();
            $applicationId = (int) $stmt->insert_id;
            $history = $db->prepare("INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, NULL, 'ready', 'Online-Bewerbung vorbereitet')");
            $history->bind_param('ii', $applicationId, $uid);
            $history->execute();
            audit($db, $uid, 'create', 'application', $applicationId, null, ['job_id' => $jobId, 'status' => 'ready', 'channel' => 'website', 'application_url' => $applicationUrl]);
            $db->commit();
            flash(tr('applications.prepared'));
            redirect('/?page=applications&edit=' . $applicationId . '#application-form');
        } catch (Throwable $exception) {
            try { $db->rollback(); } catch (Throwable) {}
            error_log('Start application failed for job ' . $jobId . ': ' . $exception->getMessage());
            flash(tr('applications.prepare_failed'), 'danger');
            redirect('/?page=jobs&edit=' . $jobId . '#new');
        }
    }

    if ($action === 'set_intermediary') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $intermediaryCompanyId = (int) ($_POST['intermediary_company_id'] ?? 0);
        $application = dbOne($db, 'SELECT a.id, a.primary_contact_id, j.company_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, userId()]);
        if (!$application) { http_response_code(404); exit('Not found'); }
        if ($intermediaryCompanyId > 0) {
            $intermediary = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND is_intermediary=1 AND deleted_at IS NULL', 'ii', [$intermediaryCompanyId, userId()]);
            if (!$intermediary || $intermediaryCompanyId === (int)$application['company_id']) { $intermediaryCompanyId = 0; }
        }
        $uid=userId();
        if ($intermediaryCompanyId > 0) {
            $relationship = $db->prepare("INSERT INTO company_relationships (owner_user_id, intermediary_company_id, client_company_id, relationship_type) VALUES (?, ?, ?, 'recruitment_agency') ON DUPLICATE KEY UPDATE deleted_at=NULL, updated_at=NOW()");
            $clientCompanyId=(int)$application['company_id'];
            $relationship->bind_param('iii', $uid, $intermediaryCompanyId, $clientCompanyId);
            $relationship->execute();
        }
        $stmt=$db->prepare('UPDATE applications SET intermediary_company_id=NULLIF(?,0) WHERE id=? AND user_id=?');
        $stmt->bind_param('iii', $intermediaryCompanyId, $applicationId, $uid);
        $stmt->execute();
        audit($db, $uid, 'update', 'application_intermediary', $applicationId, null, ['intermediary_company_id'=>$intermediaryCompanyId]);
        flash($intermediaryCompanyId ? tr('applications.intermediary_assigned') : tr('applications.intermediary_removed'));
        redirect('/?page=applications&edit=' . $applicationId . '#companies');
    }

    if ($action === 'save_contact') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $application = dbOne($db, 'SELECT a.id, a.job_id, a.intermediary_company_id, j.company_id FROM applications a JOIN jobs j ON j.id=a.job_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, userId()]);
        if (!$application) { http_response_code(404); exit('Not found'); }
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));
        $languageInput = trim((string) ($_POST['preferred_language'] ?? ''));
        $language = $languageInput !== '' ? normalizeLocale($languageInput) : null;
        $notes = trim((string) ($_POST['contact_notes'] ?? ''));
        if ($firstName === '' || $lastName === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Vorname, Nachname und eine gültige E-Mail-Adresse sind erforderlich.', 'danger');
            redirect('/?page=applications&edit=' . $applicationId . '#contacts');
        }
        $uid = userId(); $employerCompanyId = (int) $application['company_id']; $jobId = (int) $application['job_id'];
        $companyId = (int) ($_POST['contact_company_id'] ?? $employerCompanyId);
        $allowedCompanyIds = [$employerCompanyId];
        if (!empty($application['intermediary_company_id'])) { $allowedCompanyIds[] = (int) $application['intermediary_company_id']; }
        if (!in_array($companyId, $allowedCompanyIds, true)) { http_response_code(422); exit('Invalid contact company.'); }
        if ($contactId > 0) {
            $old = dbOne($db, 'SELECT * FROM contacts WHERE id=? AND owner_user_id=? AND company_id=? AND deleted_at IS NULL', 'iii', [$contactId, $uid, $companyId]);
            if (!$old) { http_response_code(404); exit('Not found'); }
            $stmt = $db->prepare('UPDATE contacts SET application_id=?, job_id=?, first_name=?, last_name=?, position=?, department=?, email=?, phone=?, mobile=?, linkedin_url=?, preferred_language=?, notes=? WHERE id=? AND owner_user_id=?');
            $stmt->bind_param('iissssssssssii', $applicationId, $jobId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes, $contactId, $uid);
            $stmt->execute();
            audit($db, $uid, 'update', 'contact', $contactId, $old, ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,'application_id'=>$applicationId,'job_id'=>$jobId]);
        } else {
            $stmt = $db->prepare('INSERT INTO contacts (owner_user_id, company_id, application_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iiiissssssssss', $uid, $companyId, $applicationId, $jobId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes);
            $stmt->execute();
            $contactId = (int) $stmt->insert_id;
            audit($db, $uid, 'create', 'contact', $contactId, null, ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,'application_id'=>$applicationId,'job_id'=>$jobId]);
        }
        if (!empty($_POST['set_primary'])) {
            $stmt = $db->prepare('UPDATE applications SET primary_contact_id=? WHERE id=? AND user_id=?');
            $stmt->bind_param('iii', $contactId, $applicationId, $uid);
            $stmt->execute();
        }
        flash('Kontakt gespeichert.');
        redirect('/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contacts');
    }

    if ($action === 'save_job_contact') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $job = dbOne($db, 'SELECT id, company_id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$jobId, userId()]);
        if (!$job) { http_response_code(404); exit('Not found'); }
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));
        $languageInput = trim((string) ($_POST['preferred_language'] ?? ''));
        $language = $languageInput !== '' ? normalizeLocale($languageInput) : null;
        $notes = trim((string) ($_POST['contact_notes'] ?? ''));
        if ($firstName === '' || $lastName === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Vorname, Nachname und eine gültige E-Mail-Adresse sind erforderlich.', 'danger');
            redirect('/?page=jobs&edit=' . $jobId . '#job-contacts');
        }
        $uid = userId();
        $companyId = (int) $job['company_id'];
        $stmt = $db->prepare('INSERT INTO contacts (owner_user_id, company_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiissssssssss', $uid, $companyId, $jobId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes);
        $stmt->execute();
        $contactId = (int) $stmt->insert_id;
        audit($db, $uid, 'create', 'contact', $contactId, null, ['first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,'job_id'=>$jobId]);
        flash('Kontakt zur Stelle gespeichert.');
        redirect('/?page=jobs&edit=' . $jobId . '#job-contacts');
    }

    if ($action === 'update_contact_global') {
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $companyId = (int) ($_POST['contact_company_id'] ?? 0);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $position = trim((string) ($_POST['position'] ?? ''));
        $department = trim((string) ($_POST['department'] ?? ''));
        $email = strtolower(trim((string) ($_POST['contact_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $linkedin = trim((string) ($_POST['linkedin_url'] ?? ''));
        $languageInput = trim((string) ($_POST['preferred_language'] ?? ''));
        $language = $languageInput !== '' ? normalizeLocale($languageInput) : null;
        $notes = trim((string) ($_POST['contact_notes'] ?? ''));
        $uid = userId();
        $old = dbOne($db, 'SELECT id, company_id, first_name, last_name, email FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$contactId, $uid]);
        $company = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$companyId, $uid]);
        if (!$old || !$company || $firstName === '' || $lastName === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('Kontakt konnte nicht gespeichert werden. Bitte Pflichtfelder prüfen.', 'danger');
            redirect('/?page=contacts&edit_contact=' . $contactId);
        }
        $stmt = $db->prepare('UPDATE contacts SET company_id=?, first_name=?, last_name=?, position=?, department=?, email=?, phone=?, mobile=?, linkedin_url=?, preferred_language=?, notes=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('issssssssssii', $companyId, $firstName, $lastName, $position, $department, $email, $phone, $mobile, $linkedin, $language, $notes, $contactId, $uid);
        $stmt->execute();
        audit($db, $uid, 'update', 'contact', $contactId, $old, ['company_id'=>$companyId,'first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email]);
        flash('Kontakt aktualisiert.');
        redirect('/?page=contacts&edit_contact=' . $contactId);
    }

    if ($action === 'save_contact_log') {
        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $contactId = (int) ($_POST['contact_id'] ?? 0);
        $relation = $applicationId > 0
            ? dbOne($db, 'SELECT a.id application_id, a.job_id, c.company_id, c.first_name, c.last_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN contacts c ON c.id=? AND c.owner_user_id=a.user_id AND c.deleted_at IS NULL AND (c.company_id=j.company_id OR c.company_id=a.intermediary_company_id OR c.application_id=a.id OR c.job_id=a.job_id) WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'iii', [$contactId, $applicationId, userId()])
            : dbOne($db, 'SELECT application_id, job_id, company_id, first_name, last_name FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$contactId, userId()]);
        if (!$relation) { http_response_code(404); exit('Not found'); }
        $allowedChannels = array_keys(contactLogChannelOptions());
        $allowedDirections = ['incoming','outgoing','internal'];
        $allowedLogStatuses = array_keys(contactLogStatusOptions());
        $channel = in_array($_POST['log_channel'] ?? '', $allowedChannels, true) ? (string) $_POST['log_channel'] : 'note';
        $direction = in_array($_POST['direction'] ?? '', $allowedDirections, true) ? (string) $_POST['direction'] : 'internal';
        $logStatus = in_array($_POST['log_status'] ?? '', $allowedLogStatuses, true) ? (string) $_POST['log_status'] : 'done';
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['log_body'] ?? ''));
        $occurredAt = trim((string) ($_POST['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $followUpAt = trim((string) ($_POST['follow_up_at'] ?? '')) ?: null;
        $outcome = trim((string) ($_POST['outcome'] ?? ''));
        $occurredAt = str_replace('T', ' ', $occurredAt) . (strlen($occurredAt) === 16 ? ':00' : '');
        if ($followUpAt) { $followUpAt = str_replace('T', ' ', $followUpAt) . (strlen($followUpAt) === 16 ? ':00' : ''); }
        $stmt = $db->prepare('INSERT INTO contact_logs (owner_user_id, contact_id, company_id, application_id, job_id, channel, direction, status, subject, body, occurred_at, follow_up_at, outcome) VALUES (?, ?, ?, NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?, ?, ?)');
        $uid=userId(); $companyId=(int)$relation['company_id']; $jobId=(int)($relation['job_id'] ?? 0); $logApplicationId=(int)($relation['application_id'] ?? 0);
        $stmt->bind_param('iiiiissssssss', $uid, $contactId, $companyId, $logApplicationId, $jobId, $channel, $direction, $logStatus, $subject, $body, $occurredAt, $followUpAt, $outcome);
        $stmt->execute();
        $logId=(int)$stmt->insert_id;
        $attachmentId = null;
        try {
            $attachmentId = saveContactLogAttachment($db, $uid, $contactId, $logId, trim((string)$relation['first_name'] . ' ' . (string)$relation['last_name']), $subject);
        } catch (Throwable $exception) {
            flash('Kontaktaktivität gespeichert, aber Anhang konnte nicht abgelegt werden: ' . $exception->getMessage(), 'warning');
            redirect($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log' : '/?page=contacts&edit_contact=' . $contactId . '#contact-log');
        }
        audit($db, $uid, 'create', 'contact_log', $logId, null, ['contact_id'=>$contactId,'application_id'=>$logApplicationId,'channel'=>$channel,'direction'=>$direction,'status'=>$logStatus,'subject'=>$subject]);
        flash('Kontaktaktivität gespeichert.');
        redirect($applicationId > 0 ? '/?page=applications&edit=' . $applicationId . '&contact=' . $contactId . '#contact-log' : '/?page=contacts&edit_contact=' . $contactId . '#contact-log');
    }

    if ($action === 'update_contact_log') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id, contact_id, application_id, status, subject FROM contact_logs WHERE id=? AND owner_user_id=?', 'ii', [$logId, $uid]);
        if (!$old) { http_response_code(404); exit('Not found'); }
        $allowedChannels = array_keys(contactLogChannelOptions());
        $allowedDirections = ['incoming','outgoing','internal'];
        $allowedLogStatuses = array_keys(contactLogStatusOptions());
        $channel = in_array($_POST['log_channel'] ?? '', $allowedChannels, true) ? (string) $_POST['log_channel'] : 'note';
        $direction = in_array($_POST['direction'] ?? '', $allowedDirections, true) ? (string) $_POST['direction'] : 'internal';
        $logStatus = in_array($_POST['log_status'] ?? '', $allowedLogStatuses, true) ? (string) $_POST['log_status'] : 'done';
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['log_body'] ?? ''));
        $occurredAt = trim((string) ($_POST['occurred_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $followUpAt = trim((string) ($_POST['follow_up_at'] ?? '')) ?: null;
        $outcome = trim((string) ($_POST['outcome'] ?? ''));
        $occurredAt = str_replace('T', ' ', $occurredAt) . (strlen($occurredAt) === 16 ? ':00' : '');
        if ($followUpAt) { $followUpAt = str_replace('T', ' ', $followUpAt) . (strlen($followUpAt) === 16 ? ':00' : ''); }
        $stmt = $db->prepare('UPDATE contact_logs SET channel=?, direction=?, status=?, subject=?, body=?, occurred_at=?, follow_up_at=?, outcome=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ssssssssii', $channel, $direction, $logStatus, $subject, $body, $occurredAt, $followUpAt, $outcome, $logId, $uid);
        $stmt->execute();
        $contact = dbOne($db, 'SELECT first_name, last_name FROM contacts WHERE id=? AND owner_user_id=?', 'ii', [(int)$old['contact_id'], $uid]);
        try {
            saveContactLogAttachment($db, $uid, (int)$old['contact_id'], $logId, trim((string)($contact['first_name'] ?? '') . ' ' . (string)($contact['last_name'] ?? '')), $subject);
        } catch (Throwable $exception) {
            flash('Kontaktaktivität aktualisiert, aber Anhang konnte nicht abgelegt werden: ' . $exception->getMessage(), 'warning');
            redirect((int)($old['application_id'] ?? 0) > 0 ? '/?page=applications&edit=' . (int)$old['application_id'] . '&contact=' . (int)$old['contact_id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$old['contact_id'] . '#contact-log');
        }
        audit($db, $uid, 'update', 'contact_log', $logId, $old, ['status'=>$logStatus,'subject'=>$subject]);
        flash('Kontaktaktivität aktualisiert.');
        redirect((int)($old['application_id'] ?? 0) > 0 ? '/?page=applications&edit=' . (int)$old['application_id'] . '&contact=' . (int)$old['contact_id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$old['contact_id'] . '#contact-log');
    }

    if ($action === 'delete_contact_log') {
        $logId = (int) ($_POST['log_id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id, contact_id, application_id, subject, status FROM contact_logs WHERE id=? AND owner_user_id=?', 'ii', [$logId, $uid]);
        if ($old) {
            cleanupContactLogCascade($db, $uid, $logId);
            $stmt = $db->prepare('DELETE FROM contact_logs WHERE id=? AND owner_user_id=?');
            $stmt->bind_param('ii', $logId, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'contact_log', $logId, $old, null);
            flash('Kontaktaktivität gelöscht.');
            redirect((int)($old['application_id'] ?? 0) > 0 ? '/?page=applications&edit=' . (int)$old['application_id'] . '&contact=' . (int)$old['contact_id'] . '#contact-log' : '/?page=contacts&edit_contact=' . (int)$old['contact_id'] . '#contact-log');
        }
        flash('Kontaktaktivität nicht gefunden.', 'danger');
        redirect('/?page=contacts');
    }

    if ($action === 'save_application') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = dbOne($db, 'SELECT id, job_id, intermediary_company_id, primary_contact_id, status, next_action, next_action_at FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if (!$old) { http_response_code(404); exit('Not found'); }
        $allowedStatuses = ['draft','ready','sent','confirmed','interview','assessment','offer','accepted','rejected','withdrawn','closed'];
        $allowedChannels = ['email','portal','website','mail','referral','other'];
        $status = in_array($_POST['status'] ?? '', $allowedStatuses, true) ? (string) $_POST['status'] : 'draft';
        $channel = in_array($_POST['channel'] ?? '', $allowedChannels, true) ? (string) $_POST['channel'] : null;
        $appliedAt = trim((string) ($_POST['applied_at'] ?? '')) ?: null;
        $nextActionInput = trim((string) ($_POST['next_action'] ?? ''));
        $nextAction = in_array($nextActionInput, applicationNextActionChoices(), true) ? $nextActionInput : null;
        $nextActionAt = trim((string) ($_POST['next_action_at'] ?? '')) ?: null;
        $emailSubject = trim((string) ($_POST['email_subject'] ?? '')) ?: null;
        $emailBody = trim((string) ($_POST['email_body'] ?? '')) ?: null;
        $applicationUrl = trim((string) ($_POST['application_url'] ?? '')) ?: null;
        if ($applicationUrl !== null && !filter_var($applicationUrl, FILTER_VALIDATE_URL)) {
            flash(tr('applications.online_url_invalid'), 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        $portalAccount = trim((string) ($_POST['portal_account'] ?? '')) ?: null;
        $referenceNumber = trim((string) ($_POST['reference_number'] ?? '')) ?: null;
        $onlineNotes = trim((string) ($_POST['online_notes'] ?? '')) ?: null;
        $coverLetter = trim((string) ($_POST['cover_letter_text'] ?? '')) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $primaryContactId = (int) ($_POST['primary_contact_id'] ?? 0);
        $intermediaryCompanyId = (int) ($_POST['intermediary_company_id'] ?? 0);
        if ($intermediaryCompanyId > 0) {
            $jobCompany = dbOne($db, 'SELECT company_id FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$old['job_id'], userId()]);
            $intermediary = dbOne($db, 'SELECT id FROM companies WHERE id=? AND owner_user_id=? AND is_intermediary=1 AND deleted_at IS NULL', 'ii', [$intermediaryCompanyId, userId()]);
            if (!$jobCompany || !$intermediary || $intermediaryCompanyId === (int)$jobCompany['company_id']) {
                $intermediaryCompanyId = 0;
            } else {
                $relationship = $db->prepare("INSERT INTO company_relationships (owner_user_id, intermediary_company_id, client_company_id, relationship_type) VALUES (?, ?, ?, 'recruitment_agency') ON DUPLICATE KEY UPDATE deleted_at=NULL, updated_at=NOW()");
                $uid = userId(); $clientCompanyId = (int)$jobCompany['company_id'];
                $relationship->bind_param('iii', $uid, $intermediaryCompanyId, $clientCompanyId);
                $relationship->execute();
            }
        }
        if ($primaryContactId > 0) {
            $validContact = dbOne($db, 'SELECT c.id FROM contacts c JOIN jobs j ON j.id=? WHERE c.id=? AND c.owner_user_id=? AND c.deleted_at IS NULL AND (c.company_id=j.company_id OR c.company_id=?)', 'iiii', [(int)$old['job_id'], $primaryContactId, userId(), $intermediaryCompanyId]);
            if (!$validContact) { $primaryContactId = 0; }
        }
        if ($appliedAt) { $appliedAt = str_replace('T', ' ', $appliedAt) . (strlen($appliedAt) === 16 ? ':00' : ''); }
        if ($nextActionAt) { $nextActionAt = str_replace('T', ' ', $nextActionAt) . (strlen($nextActionAt) === 16 ? ':00' : ''); }
        if ($status === 'sent' && !$appliedAt) { $appliedAt = date('Y-m-d H:i:s'); }
        if ($status === 'sent') {
            $nextAction = 'Antwort auf Bewerbung pendent';
            $nextActionAt = $appliedAt ?: date('Y-m-d H:i:s');
        }
        $stmt = $db->prepare('UPDATE applications SET intermediary_company_id=NULLIF(?,0), primary_contact_id=NULLIF(?,0), status=?, channel=?, applied_at=?, next_action=?, next_action_at=?, application_url=?, portal_account=?, reference_number=?, online_notes=?, email_subject=?, email_body=?, cover_letter_text=?, notes=? WHERE id=? AND user_id=?');
        $uid = userId();
        $stmt->bind_param('iisssssssssssssii', $intermediaryCompanyId, $primaryContactId, $status, $channel, $appliedAt, $nextAction, $nextActionAt, $applicationUrl, $portalAccount, $referenceNumber, $onlineNotes, $emailSubject, $emailBody, $coverLetter, $notes, $id, $uid);
        $stmt->execute();
        if ($old['status'] !== $status) {
            $comment = trim((string) ($_POST['status_comment'] ?? '')) ?: null;
            $history = $db->prepare('INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)');
            $history->bind_param('iisss', $id, $uid, $old['status'], $status, $comment);
            $history->execute();
            $jobStatus = ['sent'=>'applied','confirmed'=>'applied','interview'=>'interview','assessment'=>'interview','offer'=>'offer','accepted'=>'offer','rejected'=>'rejected','withdrawn'=>'closed','closed'=>'closed'][$status] ?? null;
            if ($jobStatus) {
                $jobStmt = $db->prepare('UPDATE jobs SET status=? WHERE id=? AND owner_user_id=?');
                $jobId = (int) $old['job_id'];
                $jobStmt->bind_param('sii', $jobStatus, $jobId, $uid);
                $jobStmt->execute();
            }
        }
        audit($db, $uid, 'update', 'application', $id, ['status' => $old['status']], ['status' => $status, 'channel' => $channel, 'application_url' => $applicationUrl, 'reference_number' => $referenceNumber, 'next_action' => $nextAction, 'next_action_at' => $nextActionAt]);
        if ($status === 'sent' && $primaryContactId > 0) {
            ensureSubmittedApplicationContactLog($db, $uid, $id);
        }
        if ($status === 'sent') {
            ensureSubmittedApplicationCalendarEvent($db, $uid, $id);
        }
        flash(tr('applications.saved'));
        redirect('/?page=applications&edit=' . $id . '#application-form');
    }

    if ($action === 'submit_online_application') {
        $id = (int) ($_POST['id'] ?? 0);
        $uid = userId();
        $old = dbOne($db, 'SELECT id, job_id, primary_contact_id, status FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, $uid]);
        if (!$old) { http_response_code(404); exit('Not found'); }
        $applicationUrl = trim((string) ($_POST['application_url'] ?? '')) ?: null;
        if ($applicationUrl !== null && !filter_var($applicationUrl, FILTER_VALIDATE_URL)) {
            flash(tr('applications.online_url_invalid'), 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        $appliedAt = trim((string) ($_POST['applied_at'] ?? '')) ?: date('Y-m-d H:i:s');
        $appliedAt = str_replace('T', ' ', $appliedAt) . (strlen($appliedAt) === 16 ? ':00' : '');
        $channel = in_array($_POST['channel'] ?? '', ['portal','website','other'], true) ? (string) $_POST['channel'] : 'website';
        $portalAccount = trim((string) ($_POST['portal_account'] ?? '')) ?: null;
        $referenceNumber = trim((string) ($_POST['reference_number'] ?? '')) ?: null;
        $onlineNotes = trim((string) ($_POST['online_notes'] ?? '')) ?: null;
        $coverLetter = trim((string) ($_POST['cover_letter_text'] ?? '')) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $primaryContactId = (int) ($_POST['primary_contact_id'] ?? 0);
        if ($primaryContactId > 0) {
            $validContact = dbOne($db, 'SELECT id FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [$primaryContactId, $uid]);
            if (!$validContact) { $primaryContactId = 0; }
        }
        $status = 'sent';
        $nextAction = 'Antwort auf Bewerbung pendent';
        $stmt = $db->prepare('UPDATE applications SET primary_contact_id=NULLIF(?,0), status=?, channel=?, applied_at=?, next_action=?, next_action_at=?, application_url=?, portal_account=?, reference_number=?, online_notes=?, cover_letter_text=?, notes=? WHERE id=? AND user_id=?');
        $stmt->bind_param('isssssssssssii', $primaryContactId, $status, $channel, $appliedAt, $nextAction, $appliedAt, $applicationUrl, $portalAccount, $referenceNumber, $onlineNotes, $coverLetter, $notes, $id, $uid);
        $stmt->execute();
        if ((string)$old['status'] !== $status) {
            $history = $db->prepare('INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)');
            $comment = 'Online-Bewerbung eingereicht';
            $oldStatus = (string) $old['status'];
            $history->bind_param('iisss', $id, $uid, $oldStatus, $status, $comment);
            $history->execute();
        }
        $jobStatus = 'applied';
        $jobId = (int) $old['job_id'];
        $jobStmt = $db->prepare('UPDATE jobs SET status=? WHERE id=? AND owner_user_id=?');
        $jobStmt->bind_param('sii', $jobStatus, $jobId, $uid);
        $jobStmt->execute();
        if ($primaryContactId > 0) {
            ensureSubmittedApplicationContactLog($db, $uid, $id);
        }
        ensureSubmittedApplicationCalendarEvent($db, $uid, $id);
        audit($db, $uid, 'submit', 'application', $id, ['status' => $old['status']], ['status' => $status, 'channel' => $channel, 'application_url' => $applicationUrl, 'next_action' => $nextAction, 'next_action_at' => $appliedAt]);
        flash(tr('applications.online_submitted_logged'));
        redirect('/?page=applications&edit=' . $id . '#application-form');
    }

    if ($action === 'send_application_email') {
        $id = (int) ($_POST['id'] ?? 0);
        $application = dbOne(
            $db,
            'SELECT a.id, a.primary_contact_id, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, a.status, j.title, c.name company_name, ct.email contact_email, ct.first_name contact_first_name, ct.last_name contact_last_name
               FROM applications a
               JOIN jobs j ON j.id=a.job_id
               JOIN companies c ON c.id=j.company_id
               LEFT JOIN contacts ct ON ct.id=a.primary_contact_id AND ct.owner_user_id=a.user_id AND ct.deleted_at IS NULL
              WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL
              LIMIT 1',
            'ii',
            [$id, userId()]
        );
        $recipient = strtolower(trim((string) ($_POST['recipient_email'] ?? ($application['contact_email'] ?? ''))));
        $subject = trim((string) ($_POST['email_subject'] ?? ($application['email_subject'] ?? '')));
        $body = trim((string) ($_POST['email_body'] ?? ($application['email_body'] ?? '')));
        if (!$application || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || $subject === '' || $body === '') {
            flash('Empfänger, Betreff und Begleittext sind erforderlich.', 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        $uid = userId();
        $attachments = applicationDocumentFiles($db, $uid, $id);
        $sent = false;
        try {
            $sent = sendConfiguredMail($db, $config, $uid, $recipient, $subject, $body, $attachments);
        } catch (Throwable) {
            flash('E-Mail konnte nicht versendet werden. Bitte eigene SMTP-Konfiguration prüfen.', 'danger');
            redirect('/?page=applications&edit=' . $id . '#application-form');
        }
        if ($sent) {
            $now = date('Y-m-d H:i:s');
            $sentStatus = 'sent';
            $nextAction = 'Antwort auf Bewerbung pendent';
            $stmt = $db->prepare('UPDATE applications SET status=?, channel="email", applied_at=COALESCE(applied_at, ?), next_action=?, next_action_at=COALESCE(applied_at, ?), email_subject=?, email_body=? WHERE id=? AND user_id=?');
            $stmt->bind_param('ssssssii', $sentStatus, $now, $nextAction, $now, $subject, $body, $id, $uid);
            $stmt->execute();
            $history = $db->prepare('INSERT INTO application_status_history (application_id, changed_by, old_status, new_status, comment) VALUES (?, ?, ?, ?, ?)');
            $comment = 'Bewerbungs-E-Mail versendet an ' . $recipient;
            $oldStatus = (string) $application['status'];
            $history->bind_param('iisss', $id, $uid, $oldStatus, $sentStatus, $comment);
            $history->execute();
            audit($db, $uid, 'send', 'outbound_email', $id, null, ['recipient' => $recipient, 'application_id' => $id, 'attachments' => count($attachments)]);
            ensureSubmittedApplicationContactLog($db, $uid, $id);
            ensureSubmittedApplicationCalendarEvent($db, $uid, $id);
            flash(tr('applications.email_sent'));
        } else {
            flash('Eigener SMTP-Versand ist nicht aktiv. E-Mail wurde als Entwurf protokolliert.', 'warning');
        }
        redirect('/?page=applications&edit=' . $id . '#application-form');
    }

    if ($action === 'delete_application') {
        $id = (int) ($_POST['id'] ?? 0);
        $old = dbOne($db, 'SELECT id, job_id, status, next_action, next_action_at FROM applications WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$id, userId()]);
        if ($old) {
            $uid = userId();
            cleanupApplicationCascade($db, $uid, $id);
            $stmt = $db->prepare('UPDATE applications SET deleted_at=NOW() WHERE id=? AND user_id=?');
            $stmt->bind_param('ii', $id, $uid);
            $stmt->execute();
            audit($db, $uid, 'delete', 'application', $id, $old, null);
        }
        flash(tr('applications.deleted'));
        redirect('/?page=applications');
    }
}

if ($page === 'two_factor' && !empty($_SESSION['pending_2fa_user_id'])) {
    clearAuthenticatedSession();
}

$currentUser = userId() ? dbOne($db, 'SELECT * FROM users WHERE id = ?', 'i', [userId()]) : null;
$currentUserIsAdmin = $currentUser ? isAdmin($db, realUserId(), $config) : false;
if ($currentUser && isset($_GET['lang'])) {
    $requestedLocale = normalizeLocale((string) $_GET['lang']);
    if ($requestedLocale !== normalizeLocale((string) ($currentUser['preferred_language'] ?? 'de-CH'))) {
        try {
            $stmt = $db->prepare('UPDATE users SET preferred_language=? WHERE id=?');
            $uid = userId();
            $stmt->bind_param('si', $requestedLocale, $uid);
            $stmt->execute();
            $currentUser['preferred_language'] = $requestedLocale;
        } catch (Throwable $exception) {
            error_log('Locale preference update failed: ' . $exception->getMessage());
        }
    }
    $_SESSION['locale'] = $requestedLocale;
}
$appLocale = currentLocale($currentUser ?: null);
if (!pageSupportsMultilingualUi($page)) {
    $appLocale = 'de-CH';
}
$codeVersion = '1.15.39';
$configuredVersion = (string) ($config['app_version'] ?? '');
$appVersion = version_compare($configuredVersion, $codeVersion, '>=') ? $configuredVersion : $codeVersion;
seedDbUiTextCatalog();
if ($currentUser) {
    touchUserPresence($db, realUserId());
    if (realUserId() === userId()) {
        $currentUser['last_seen_at'] = date('Y-m-d H:i:s');
    }
}
if (isSupportImpersonation()) {
    $targetId = (int) ($_SESSION['support_target_user_id'] ?? 0);
    if ($targetId !== userId() || !activeSupportGrant($db, $targetId) || !$currentUser) {
        endSupportImpersonationSession();
        flash('ADMIN Support wurde beendet, weil die Freigabe nicht mehr aktiv ist.', 'warning');
        redirect('/?page=admin_users');
    }
}
if ($currentUser && in_array($currentUser['timezone'], timezone_identifiers_list(), true)) {
    date_default_timezone_set($currentUser['timezone']);
}
if ($page === 'verify_email') {
    $token = trim((string) ($_GET['token'] ?? ''));
    $reset = null;
    if ($token !== '') {
        $tokenHash = hash('sha256', $token);
        $reset = dbOne(
            $db,
            "SELECT t.id token_id, t.user_id
               FROM auth_tokens t
               JOIN users u ON u.id=t.user_id
              WHERE t.token_type='email_verify'
                AND t.token_hash=?
                AND t.consumed_at IS NULL
                AND t.expires_at > NOW()
                AND u.deleted_at IS NULL
              LIMIT 1",
            's',
            [$tokenHash]
        );
    }
    if ($reset) {
        $stmt = $db->prepare('UPDATE users SET email_verified_at=COALESCE(email_verified_at, NOW()), status="active" WHERE id=?');
        $stmt->bind_param('i', $reset['user_id']);
        $stmt->execute();
        $stmt = $db->prepare('UPDATE auth_tokens SET consumed_at=NOW() WHERE id=?');
        $stmt->bind_param('i', $reset['token_id']);
        $stmt->execute();
        audit($db, (int) $reset['user_id'], 'update', 'user', (int) $reset['user_id'], null, ['email_verified' => true]);
        flash('E-Mail-Adresse bestätigt. Du kannst dich jetzt anmelden.');
    } else {
        flash('Dieser Bestätigungslink ist ungültig oder abgelaufen.', 'danger');
    }
    redirect('/?page=login');
}
if ($page === 'document_download') {
    requireLogin();
    $documentId = (int) ($_GET['id'] ?? 0);
    $document = dbOne($db, 'SELECT original_filename, storage_path, mime_type, file_size FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$documentId, userId()]);
    $path = $document ? realpath(__DIR__ . '/' . $document['storage_path']) : false;
    $root = realpath(storageRoot());
    if (!$document || !$path || !$root || !str_starts_with($path, $root) || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . (string) $document['file_size']);
    header('Content-Disposition: attachment; filename="' . addslashes($document['original_filename']) . '"');
    readfile($path);
    exit;
}
if ($page === 'application_dossier') {
    requireLogin();
    $applicationId = (int) ($_GET['id'] ?? 0);
    $dossier = applicationDossier($db, userId(), $applicationId, $currentUser);
    if (!$dossier) {
        http_response_code(404);
        exit('Not found');
    }
    $application = $dossier['application'];
    $filenameBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$application['company_name'] . '-' . (string)$application['job_title']) ?: 'bewerbungsdokumentation';
    if (($_GET['format'] ?? '') === 'pdf') {
        pdfTextResponse('Bewerbungsdokumentation-' . $filenameBase . '.pdf', 'Bewerbungsdokumentation', dossierPdfSections($dossier));
    }
    $statusLabels = applicationStatusOptions();
    $channelLabels = applicationChannelOptions();
    $contactLogChannels = contactLogChannelOptions();
    $contactLogStatuses = contactLogStatusOptions();
    startUiTranslationBuffer($appLocale);
    ?><!doctype html>
    <html lang="<?= e(localeHtmlLang($appLocale)) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(tr('dossier.title')) ?></title><link rel="stylesheet" href="/assets/app.css?v=<?= e($appVersion) ?>"></head>
    <body><main class="container dossier-page">
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('dossier.title')) ?></p><h1><?= e((string)$application['company_name']) ?></h1><p><?= e((string)$application['job_title']) ?></p></div><span><?= e(displayDateTime((string)$dossier['generated_at'], $currentUser)) ?></span></div>
        <div class="actions export-actions"><a class="button" href="/?page=applications&edit=<?= (int)$applicationId ?>#application-form"><?= e(tr('dossier.back_to_application')) ?></a><a class="button primary" href="/?page=application_dossier&id=<?= (int)$applicationId ?>&format=pdf"><?= e(tr('dossier.create_pdf')) ?></a></div>
        <section class="panel dossier-hero">
            <div><h2><?= e((string)$application['job_title']) ?></h2><p><?= e((string)$application['company_name']) ?><?= $application['intermediary_company_name'] ? ' · ' . e(tr('companies.by')) . ' ' . e((string)$application['intermediary_company_name']) : '' ?></p></div>
            <dl><div><dt><?= e(tr('common.status')) ?></dt><dd><?= e($statusLabels[(string)$application['status']] ?? (string)$application['status']) ?></dd></div><div><dt><?= e(tr('applications.channel')) ?></dt><dd><?= e($channelLabels[(string)$application['channel']] ?? (string)$application['channel']) ?></dd></div><div><dt><?= e(tr('applications.sent')) ?></dt><dd><?= e($application['applied_at'] ? displayDateTime((string)$application['applied_at'], $currentUser) : tr('applications.not_submitted')) ?></dd></div><div><dt><?= e(tr('nav.pendents')) ?></dt><dd><?= e(trim((string)$application['next_action'] . ' ' . ($application['next_action_at'] ? displayDateTime((string)$application['next_action_at'], $currentUser) : ''))) ?></dd></div></dl>
        </section>
        <section class="panel dossier-grid"><article><h2><?= e(tr('companies.company')) ?></h2><p><strong><?= e((string)$application['company_name']) ?></strong></p><p><?= e(trim((string)$application['address_line1'] . ' ' . (string)$application['address_line2'])) ?><br><?= e(trim((string)$application['postal_code'] . ' ' . (string)$application['company_city'])) ?></p><p><?= $application['company_website'] ? '<a href="' . e((string)$application['company_website']) . '" target="_blank" rel="noopener">' . e((string)$application['company_website']) . '</a>' : '' ?></p><p><?= e(trim((string)$application['company_email'] . ' ' . (string)$application['company_phone'])) ?></p><p><?= nl2br(e((string)$application['company_notes'])) ?></p></article><article><h2><?= e(tr('jobs.job')) ?></h2><p><strong><?= e((string)$application['job_title']) ?></strong></p><p><?= e((string)$application['location_text']) ?> · <?= e(workplaceTypeOptions()[(string)$application['workplace_type']] ?? (string)$application['workplace_type']) ?> · <?= e(contractTermOptions()[(string)$application['contract_term']] ?? (string)$application['contract_term']) ?></p><p><?= $application['source_url'] ? '<a href="' . e((string)$application['source_url']) . '" target="_blank" rel="noopener">' . e(tr('jobs.open_source_url')) . '</a>' : '' ?></p><p><?= nl2br(e((string)$application['job_notes'])) ?></p></article><article><h2><?= e(tr('applications.application')) ?></h2><p><?= e(tr('applications.online_url')) ?>: <?= $application['application_url'] ? '<a href="' . e((string)$application['application_url']) . '" target="_blank" rel="noopener">' . e((string)$application['application_url']) . '</a>' : e(tr('applications.no_url')) ?></p><p><?= e(tr('applications.portal_account')) ?>: <?= e((string)$application['portal_account']) ?><br><?= e(tr('applications.reference_number')) ?>: <?= e((string)$application['reference_number']) ?></p><p><?= nl2br(e((string)$application['application_notes'])) ?></p></article></section>
        <section class="panel"><h2><?= e(tr('dossier.job_description')) ?></h2><div class="dossier-text"><?= nl2br(e((string)$application['job_description'])) ?></div></section>
        <section class="panel"><h2><?= e(tr('dossier.company_contacts')) ?></h2><div class="dossier-list"><?php foreach($dossier['contacts'] as $contact): ?><article><strong><?= e(trim((string)$contact['first_name'] . ' ' . (string)$contact['last_name'])) ?></strong><span><?= e((string)$contact['company_name']) ?> · <?= e(trim((string)$contact['position'] . ' ' . (string)$contact['department'])) ?></span><small><?= e(trim((string)$contact['email'] . ' ' . (string)$contact['phone'] . ' ' . (string)$contact['mobile'])) ?></small><?php if($contact['linkedin_url']): ?><a href="<?= e((string)$contact['linkedin_url']) ?>" target="_blank" rel="noopener">LinkedIn</a><?php endif; ?><p><?= nl2br(e((string)$contact['notes'])) ?></p></article><?php endforeach; ?><?php if(!$dossier['contacts']): ?><p class="empty"><?= e(tr('dossier.no_contacts')) ?></p><?php endif; ?></div></section>
        <section class="panel"><h2><?= e(tr('jobs.questions')) ?></h2><div class="dossier-list"><?php foreach($dossier['questions'] as $question): ?><article><strong><?= nl2br(e((string)$question['question_text'])) ?></strong><p><?= nl2br(e((string)$question['answer_text'])) ?></p></article><?php endforeach; ?><?php if(!$dossier['questions']): ?><p class="empty"><?= e(tr('dossier.no_questions')) ?></p><?php endif; ?></div></section>
        <section class="panel"><h2><?= e(tr('dossier.submitted_documents')) ?></h2><div class="dossier-list"><?php foreach($dossier['documents'] as $doc): ?><article><strong><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e((string)$doc['title']) ?> · v<?= (int)$doc['version'] ?></a></strong><span><?= e(documentPurposeLabel((string)$doc['purpose'], (string)($currentUser['preferred_language'] ?? 'de-CH'))) ?> · <?= e((string)$doc['original_filename']) ?> · <?= e(bytesLabel((int)$doc['file_size'])) ?></span><div class="dossier-document-text"><?= trim((string)($doc['document_text'] ?? '')) !== '' ? nl2br(e((string)$doc['document_text'])) : '<p class="empty">' . e(tr('dossier.no_document_text')) . '</p>' ?></div></article><?php endforeach; ?><?php if(!$dossier['documents']): ?><p class="empty"><?= e(tr('dossier.no_documents')) ?></p><?php endif; ?></div></section>
        <section class="panel"><h2><?= e(tr('dossier.activities')) ?></h2><div class="log-timeline"><?php foreach($dossier['history'] as $entry): ?><article><strong><?= e(tr('common.status')) ?>: <?= e((string)$entry['old_status']) ?> → <?= e((string)$entry['new_status']) ?></strong><span><?= e(displayDateTime((string)$entry['changed_at'], $currentUser)) ?></span><p><?= e((string)$entry['comment']) ?></p></article><?php endforeach; ?><?php foreach($dossier['contact_logs'] as $entry): ?><article class="log-status-<?= e((string)$entry['status']) ?>"><strong><?= e((string)$entry['subject']) ?></strong><span><?= e(displayDateTime((string)$entry['occurred_at'], $currentUser)) ?> · <?= e($contactLogChannels[(string)$entry['channel']] ?? (string)$entry['channel']) ?> · <?= e($contactLogStatuses[(string)$entry['status']] ?? (string)$entry['status']) ?></span><small><?= e(trim((string)$entry['first_name'] . ' ' . (string)$entry['last_name'])) ?> · <?= e((string)$entry['company_name']) ?></small><p><?= nl2br(e((string)$entry['body'])) ?></p><?php if($entry['outcome']): ?><small><?= e(tr('contact_log.outcome')) ?>: <?= e((string)$entry['outcome']) ?></small><?php endif; ?></article><?php endforeach; ?><?php foreach($dossier['calendar_events'] as $entry): ?><article><strong><?= e(tr('nav.calendar')) ?>: <?= e((string)$entry['title']) ?></strong><span><?= e(displayDateTime((string)$entry['starts_at'], $currentUser)) ?> · <?= e(calendarStatusOptions()[(string)$entry['status']] ?? (string)$entry['status']) ?></span><p><?= nl2br(e((string)$entry['notes'])) ?></p></article><?php endforeach; ?><?php if(!$dossier['history'] && !$dossier['contact_logs'] && !$dossier['calendar_events']): ?><p class="empty"><?= e(tr('dossier.no_activities')) ?></p><?php endif; ?></div></section>
    </main></body></html><?php
    exit;
}
if ($page === 'application_documents_zip') {
    requireLogin();
    $applicationId = (int) ($_GET['id'] ?? 0);
    $application = dbOne($db, 'SELECT a.id, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, userId()]);
    if (!$application || !class_exists(ZipArchive::class)) {
        http_response_code(404);
        exit('Not found');
    }
    $files = applicationDocumentFiles($db, userId(), $applicationId);
    if (!$files) {
        http_response_code(404);
        exit('Not found');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'jema-docs-');
    $zip = new ZipArchive();
    if (!$tmp || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        exit('ZIP konnte nicht erstellt werden.');
    }
    $used = [];
    foreach ($files as $file) {
        $zip->addFile((string) $file['path'], zipSafeName((string) $file['filename'], $used));
    }
    $zip->close();
    $filename = 'Bewerbungsunterlagen-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $application['company_name'] . '-' . (string) $application['title']) . '.zip';
    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) filesize($tmp));
    header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
if ($page === 'application_documents_temp') {
    requireLogin();
    $applicationId = (int) ($_GET['id'] ?? 0);
    $application = dbOne($db, 'SELECT a.id, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$applicationId, userId()]);
    if (!$application) {
        http_response_code(404);
        exit('Not found');
    }
    $package = createApplicationTempPackage($db, userId(), $applicationId);
    if (!$package) {
        flash('Keine Bewerbungsdokumente für einen temporären Ordner vorhanden.', 'warning');
        redirect('/?page=applications&edit=' . $applicationId . '#documents');
    }
    startUiTranslationBuffer($appLocale);
    ?><!doctype html>
        <html lang="<?= e(localeHtmlLang($appLocale)) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(tr('application_docs.temp_folder')) ?></title><link rel="stylesheet" href="/assets/app.css?v=<?= e($appVersion) ?>"></head>
    <body><main class="container"><section class="panel"><p class="eyebrow"><?= e(tr('application_docs.temp_folder')) ?></p><h1><?= e((string)$application['company_name']) ?></h1><p><?= e((string)$application['title']) ?></p><p class="meta-line"><?= e(tr('application_docs.temp_folder_hint')) ?></p><div class="log-timeline application-documents"><?php foreach($package['items'] as $item): ?><article draggable="true" data-download-url="/?page=application_temp_file&token=<?= e($package['token']) ?>&file=<?= rawurlencode((string)$item['name']) ?>"><div><strong><a href="/?page=application_temp_file&token=<?= e($package['token']) ?>&file=<?= rawurlencode((string)$item['name']) ?>"><?= e((string)$item['name']) ?></a></strong><span><?= number_format(((int)$item['size']) / 1024, 1) ?> KB</span></div></article><?php endforeach; ?></div><div class="actions"><a class="button" href="/?page=applications&edit=<?= (int)$applicationId ?>#documents"><?= e(tr('dossier.back_to_application')) ?></a><a class="button primary" href="/?page=application_documents_zip&id=<?= (int)$applicationId ?>"><?= e(tr('application_docs.download_zip')) ?></a></div></section></main><script>(()=>{document.querySelectorAll('[draggable="true"]').forEach((card)=>{card.addEventListener('dragstart',(event)=>{const url=new URL(card.dataset.downloadUrl||'',location.origin).href; const title=card.querySelector('strong')?.innerText||url; event.dataTransfer?.setData('text/uri-list',url); event.dataTransfer?.setData('text/plain',title+'\\n'+url);});});})();</script></body></html><?php
    exit;
}
if ($page === 'application_temp_file') {
    requireLogin();
    cleanupApplicationTempPackages();
    $token = (string) ($_GET['token'] ?? '');
    $file = basename((string) ($_GET['file'] ?? ''));
    $package = $_SESSION['application_temp_packages'][$token] ?? null;
    if (!$package || $file === '') {
        http_response_code(404);
        exit('Not found');
    }
    $dir = realpath((string) $package['dir']);
    $path = realpath((string) $package['dir'] . '/' . $file);
    if (!$dir || !$path || !str_starts_with($path, $dir) || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    $mime = 'application/octet-stream';
    foreach ((array) ($package['items'] ?? []) as $item) {
        if (($item['name'] ?? '') === $file) {
            $mime = (string) ($item['mime'] ?? $mime);
            break;
        }
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="' . addslashes($file) . '"');
    readfile($path);
    exit;
}
if ($page === 'guest_download') {
    $token = (string) ($_GET['token'] ?? '');
    $share = activeGuestShare($db, $token);
    $documentId = (int) ($_GET['id'] ?? 0);
    if (!$share || !in_array((string) $share['download_policy'], ['original','both'], true)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $document = dbOne($db, 'SELECT id, user_id, application_id, job_id, original_filename, storage_path, mime_type, file_size FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$documentId, (int) $share['owner_user_id']]);
    if (!$document) {
        http_response_code(404);
        exit('Not found');
    }
    $allowed = shareAllowsTarget($share, 'document', (int) $document['id'])
        || (($share['target_type'] ?? '') === 'application' && (int) ($document['application_id'] ?? 0) === (int) $share['target_id'])
        || (($share['target_type'] ?? '') === 'job' && (int) ($document['job_id'] ?? 0) === (int) $share['target_id'])
        || (($share['target_type'] ?? '') === 'area');
    if (!$allowed) {
        http_response_code(403);
        exit('Forbidden');
    }
    $path = realpath(__DIR__ . '/' . $document['storage_path']);
    $root = realpath(storageRoot());
    if (!$path || !$root || !str_starts_with($path, $root) || !is_file($path)) {
        http_response_code(404);
        exit('Not found');
    }
    audit($db, (int) $share['owner_user_id'], 'read', 'guest_document', (int) $document['id'], null, ['share_id' => (int) $share['id']]);
    header('Content-Type: ' . $document['mime_type']);
    header('Content-Length: ' . (string) $document['file_size']);
    header('Content-Disposition: attachment; filename="' . addslashes($document['original_filename']) . '"');
    readfile($path);
    exit;
}
if ($page === 'export_csv') {
    requireLogin();
    $type = (string) ($_GET['type'] ?? 'jobs');
    if ($type === 'applications') {
        $applicationStatuses = applicationStatusOptions();
        $applicationChannels = applicationChannelOptions();
        $rows = dbAll($db, 'SELECT a.id, j.title, c.name company, a.status, a.channel, a.applied_at, a.next_action, a.next_action_at FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC', 'i', [userId()]);
        csvResponse('bewerbungen.csv', [tr('jobs.job'),tr('companies.company'),tr('common.status'),tr('applications.channel'),tr('applications.sent'),tr('applications.next_action'),tr('common.due')], array_map(static fn(array $r): array => [$r['title'], $r['company'], optionLabel($applicationStatuses, $r['status']), optionLabel($applicationChannels, $r['channel']), $r['applied_at'], $r['next_action'], $r['next_action_at']], $rows));
    }
    if ($type === 'audit') {
        $rows = dbAll($db, 'SELECT action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 1000', 'i', [userId()]);
        csvResponse('audit.csv', [tr('audit.action'),tr('calendar.type'),tr('common.time')], array_map(static fn(array $r): array => [$r['action'], $r['entity_type'], $r['created_at']], $rows));
    }
    $jobStatuses = jobStatusOptions();
    $workplaceTypes = workplaceTypeOptions();
    $rows = dbAll($db, 'SELECT j.id, j.title, c.name company, j.location_text, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC', 'i', [userId()]);
    csvResponse('jobs.csv', [tr('common.title'),tr('companies.company'),tr('jobs.location'),tr('common.status'),tr('jobs.workplace_type'),tr('common.updated')], array_map(static fn(array $r): array => [$r['title'], $r['company'], $r['location_text'], optionLabel($jobStatuses, $r['status']), optionLabel($workplaceTypes, $r['workplace_type']), $r['updated_at']], $rows));
}
if ($page === 'export_pdf') {
    requireLogin();
    $type = (string) ($_GET['type'] ?? 'jobs');
    if ($type === 'report') {
        $reportId = (int) ($_GET['report_id'] ?? 0);
        $report = dbOne($db, 'SELECT id, name, base_entity FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$reportId, userId()]);
        if (!$report) { http_response_code(404); exit('Not found'); }
        $settings = loadReportSettings($db, $reportId, (string)$report['base_entity']);
        [$headers, $rows] = reportDataset($db, userId(), $report, $settings, $currentUser);
        pdfResponse('report-' . $reportId . '.pdf', (string)$report['name'], $headers, $rows);
    }
    if ($type === 'rav') {
        try {
            $ravSections = ravDossierPdfSections($db, userId(), $currentUser);
        } catch (Throwable $exception) {
            error_log('RAV dossier export failed: ' . $exception->getMessage());
            $ravSections = [
                'RAV-Bewerbungsdossier' => [
                    'Das Dossier konnte nicht vollständig erstellt werden.',
                    'Technischer Hinweis: ' . $exception->getMessage(),
                    'Zeitpunkt: ' . date('Y-m-d H:i:s'),
                ],
            ];
        }
        pdfTextResponse('RAV-Bewerbungsdossier-' . date('Y-m-d') . '.pdf', 'RAV-Bewerbungsdossier', $ravSections);
    }
    if ($type === 'applications') {
        $applicationStatuses = applicationStatusOptions();
        $applicationChannels = applicationChannelOptions();
        $rows = dbAll($db, 'SELECT a.id, j.title, c.name company, a.status, a.channel, a.applied_at, a.next_action FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC', 'i', [userId()]);
        pdfResponse('bewerbungen.pdf', tr('nav.applications'), [tr('jobs.job'),tr('companies.company'),tr('common.status'),tr('applications.channel'),tr('applications.sent'),tr('applications.next_action')], array_map(static fn(array $r): array => [$r['title'], $r['company'], optionLabel($applicationStatuses, $r['status']), optionLabel($applicationChannels, $r['channel']), $r['applied_at'], $r['next_action']], $rows));
    }
    if ($type === 'companies') {
        $rows = dbAll($db, 'SELECT id, name, city, phone, website, updated_at FROM companies WHERE owner_user_id=? AND deleted_at IS NULL ORDER BY name', 'i', [userId()]);
        pdfResponse('firmen.pdf', tr('nav.companies'), [tr('common.name'),tr('companies.city'),tr('profile.phone'),tr('companies.website'),tr('common.updated')], array_map(static fn(array $r): array => [$r['name'], $r['city'], $r['phone'], $r['website'], $r['updated_at']], $rows));
    }
    if ($type === 'contacts') {
        $rows = dbAll($db, 'SELECT ct.id, ct.first_name, ct.last_name, c.name company_name, ct.email, ct.phone FROM contacts ct JOIN companies c ON c.id=ct.company_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL ORDER BY c.name, ct.last_name', 'i', [userId()]);
        pdfResponse('kontakte.pdf', tr('nav.contacts'), [tr('auth.first_name'),tr('auth.last_name'),tr('companies.company'),tr('auth.email'),tr('profile.phone')], array_map(static fn(array $r): array => [$r['first_name'], $r['last_name'], $r['company_name'], $r['email'], $r['phone']], $rows));
    }
    if ($type === 'documents') {
        $rows = dbAll($db, 'SELECT d.id, d.title, dt.code type_code, d.version, d.original_filename, d.file_size, d.created_at FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC', 'i', [userId()]);
        pdfResponse('dokumente.pdf', tr('nav.documents'), [tr('common.title'),tr('documents.type'),tr('documents.version'),tr('documents.file'),tr('documents.size'),tr('common.date')], array_map(static fn(array $r): array => [$r['title'], documentTypeLabel((string)$r['type_code'], currentLocale($currentUser ?? null)), 'v'.$r['version'], $r['original_filename'], bytesLabel((int)$r['file_size']), $r['created_at']], $rows));
    }
    $jobStatuses = jobStatusOptions();
    $workplaceTypes = workplaceTypeOptions();
    $rows = dbAll($db, 'SELECT j.id, j.title, c.name company, j.location_text, j.status, j.workplace_type, j.updated_at FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC', 'i', [userId()]);
    pdfResponse('jobs.pdf', tr('nav.jobs'), [tr('common.title'),tr('companies.company'),tr('jobs.location'),tr('common.status'),tr('jobs.workplace_type'),tr('common.updated')], array_map(static fn(array $r): array => [$r['title'], $r['company'], $r['location_text'], optionLabel($jobStatuses, $r['status']), optionLabel($workplaceTypes, $r['workplace_type']), $r['updated_at']], $rows));
}
if ($page === 'export_ics') {
    requireLogin();
    $requestedView = (string) ($_GET['view'] ?? 'agenda');
    $view = array_key_exists($requestedView, calendarViewOptions()) ? $requestedView : 'agenda';
    $anchor = calendarAnchorDate($currentUser ?? []);
    [$rangeStart, $rangeEnd] = calendarRange($view, $anchor);
    calendarIcsResponse('jema-kalender-' . $view . '.ics', calendarEventRows($db, userId(), $rangeStart, $rangeEnd));
}
if ($currentUser && in_array($page, ['login', 'register', 'forgot_password', 'reset_password', 'two_factor'], true)) {
    redirect('/?page=dashboard');
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$companies = userId() ? dbAll($db, 'SELECT * FROM companies WHERE owner_user_id = ? AND deleted_at IS NULL ORDER BY name', 'i', [userId()]) : [];
$supportGrant = $currentUser ? activeSupportGrant($db, userId()) : null;
$supportImpersonating = isSupportImpersonation();
$bodyClasses = array_filter([
    $supportGrant ? 'support-granted' : '',
    $supportImpersonating ? 'support-impersonating' : '',
]);
$appDisplayVersion = preg_replace('/^0\./', '', $appVersion) ?: $appVersion;
$contextHelpTopics = localizedContextHelpTopics($appLocale);
$contextHelp = $currentUser ? ($contextHelpTopics[$page] ?? null) : null;
startUiTranslationBuffer($appLocale);

?><!doctype html>
<html lang="<?= e(localeHtmlLang($appLocale)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($config['app_name']) ?></title>
<link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="/assets/app.css?v=<?= e($appVersion) ?>">
</head>
<body class="<?= e(implode(' ', $bodyClasses)) ?>">
<header class="topbar <?= $supportGrant ? 'topbar-support-granted' : '' ?> <?= $supportImpersonating ? 'topbar-support-admin' : '' ?>">
    <a class="brand" href="/"><img src="/assets/favicon.svg" alt="" width="32" height="32"> <span translate="no" data-i18n-skip>JeMa <strong>Jobs</strong></span></a>
    <?php if ($currentUser): ?>
        <button class="menu-button" type="button" onclick="document.body.classList.toggle('nav-open')"><?= e(tr('nav.menu')) ?></button>
        <nav class="menubar" aria-label="<?= e(tr('nav.menu')) ?>">
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.file')) ?></button><div class="menu-panel"><a href="/?page=dashboard"><?= e(tr('nav.dashboard')) ?></a><div class="submenu"><button type="button"><?= e(tr('nav.master_data')) ?></button><div class="submenu-panel"><a href="/?page=profile"><?= e(tr('nav.profile')) ?></a><a href="/?page=documents"><?= e(tr('nav.documents')) ?></a><a href="/?page=translations"><?= e(tr('nav.translations')) ?></a></div></div><a href="/?page=privacy"><?= e(tr('nav.privacy')) ?></a><a href="/?page=audit"><?= e(tr('nav.audit')) ?></a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.crm')) ?></button><div class="menu-panel"><a href="/?page=companies"><?= e(tr('nav.companies')) ?></a><a href="/?page=contacts"><?= e(tr('nav.contacts')) ?></a><a href="/?page=sharing"><?= e(tr('nav.sharing')) ?></a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.application')) ?></button><div class="menu-panel"><a href="/?page=jobs"><?= e(tr('nav.jobs')) ?></a><a href="/?page=job_platform_search"><?= e(tr('nav.job_search')) ?></a><a href="/?page=applications"><?= e(tr('nav.applications')) ?></a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.planning')) ?></button><div class="menu-panel"><a href="/?page=pendents"><?= e(tr('nav.pendents')) ?></a><a href="/?page=calendar&view=agenda"><?= e(tr('nav.calendar')) ?></a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.reporting')) ?></button><div class="menu-panel"><a href="/?page=reports"><?= e(tr('nav.reports')) ?></a><a href="/?page=export_pdf&type=rav">RAV-Dossier PDF</a><a href="/?page=export_pdf&type=jobs"><?= e(tr('nav.jobs_pdf')) ?></a><a href="/?page=export_pdf&type=applications"><?= e(tr('nav.applications_pdf')) ?></a></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.account')) ?></button><div class="menu-panel"><a href="/?page=profile"><?= e(tr('nav.profile')) ?></a><?php if ($currentUserIsAdmin): ?><a href="/?page=admin_users"><?= e(tr('nav.admin_users')) ?></a><a href="/?page=admin_job_platforms"><?= e(tr('nav.admin_job_platforms')) ?></a><?php endif; ?></div></div>
            <div class="menu-group"><button type="button" class="menu-trigger"><?= e(tr('nav.help')) ?></button><div class="menu-panel menu-panel-right"><a href="/?page=help"><?= e(tr('nav.help')) ?></a><a href="/?page=about"><?= e(tr('nav.about')) ?></a></div></div>
        </nav>
        <?php if($supportImpersonating): ?>
            <div class="support-context"><strong><?= e(tr('support.admin')) ?></strong><span><?= e(tr('support.in_environment', null, ['admin' => (string)($_SESSION['support_admin_name'] ?? 'Admin'), 'user' => (string)($_SESSION['support_target_name'] ?? userLabel($currentUser))])) ?></span></div>
        <?php elseif($supportGrant): ?>
            <div class="support-context"><strong><?= e(tr('support.granted')) ?></strong><span><?= e(tr('support.granted_hint')) ?></span></div>
        <?php endif; ?>
        <div class="topbar-actions">
            <?php if($supportImpersonating): ?><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="stop_admin_support"><?= e(tr('support.stop')) ?></button></form><?php endif; ?>
            <form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="logout"><?= e(tr('nav.logout')) ?></button></form>
        </div>
    <?php endif; ?>
</header>
<main class="container">
<?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($contextHelp): ?>
    <div class="context-help-bar" data-context-help-container>
        <button type="button" class="context-help-button" data-context-help-open aria-haspopup="dialog" aria-controls="context-help-modal" title="<?= e(tr('context.help')) ?>" aria-label="<?= e(tr('context.help')) ?>">
            <span class="bulb-icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path d="M9 21h6M10 17h4M8.6 14.7c-1.4-1-2.3-2.7-2.3-4.5A5.7 5.7 0 0 1 12 4.5a5.7 5.7 0 0 1 5.7 5.7c0 1.8-.9 3.5-2.3 4.5-.7.5-1.1 1.2-1.2 2H9.8c-.1-.8-.5-1.5-1.2-2Z"/></svg></span>
        </button>
    </div>
    <div class="context-help-modal" id="context-help-modal" data-context-help-modal hidden>
        <div class="context-help-backdrop" data-context-help-close></div>
        <section class="context-help-dialog" role="dialog" aria-modal="true" aria-labelledby="context-help-title" tabindex="-1">
            <div class="context-help-dialog-head">
                <div><p class="eyebrow"><?= e(tr('nav.help')) ?></p><h2 id="context-help-title"><?= e((string)$contextHelp['title']) ?></h2></div>
                <button type="button" class="context-help-close" data-context-help-close aria-label="<?= e(tr('context.close')) ?>"><?= e(tr('context.close')) ?></button>
            </div>
            <p><?= e((string)$contextHelp['intro']) ?></p>
            <h3><?= e(tr('context.how_to')) ?></h3>
            <ol><?php foreach((array)$contextHelp['steps'] as $step): ?><li><?= e((string)$step) ?></li><?php endforeach; ?></ol>
            <h3><?= e(tr('context.remember')) ?></h3>
            <ul><?php foreach((array)$contextHelp['tips'] as $tip): ?><li><?= e((string)$tip) ?></li><?php endforeach; ?></ul>
            <?php if(!empty($contextHelp['link'])): ?><div class="actions"><a class="button primary" href="<?= e((string)$contextHelp['link'][1]) ?>"><?= e((string)$contextHelp['link'][0]) ?></a><a class="button" href="/?page=help"><?= e(tr('context.all_topics')) ?></a></div><?php endif; ?>
        </section>
    </div>
<?php endif; ?>

<?php if ($page === 'login' && !$currentUser): ?>
    <section class="auth-card">
        <?= languagePickerHtml($appLocale, 'locale-picker-auth') ?>
        <p class="eyebrow"><?= e(tr('auth.welcome_back')) ?></p><h1><?= e(tr('auth.login_title')) ?></h1>
        <?php if(!empty($_SESSION['email_verify_notice'])): ?>
            <div class="alert warning"><?= e($_SESSION['email_verify_notice']) ?></div>
            <?php unset($_SESSION['email_verify_notice'], $_SESSION['email_verify_link']); ?>
        <?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label><?= e(tr('auth.email')) ?><input type="email" name="email" required></label>
            <label><?= e(tr('auth.password')) ?><input type="password" name="password" required></label>
            <button class="primary" name="action" value="login"><?= e(tr('auth.login_button')) ?></button>
        </form>
        <p><?= e(tr('auth.no_account')) ?> <a href="/?page=register"><?= e(tr('auth.register_link')) ?></a></p>
        <p><a href="/?page=forgot_password"><?= e(tr('auth.forgot_password')) ?></a></p>
    </section>
<?php elseif ($page === 'two_factor' && !$currentUser): ?>
    <?php if (empty($_SESSION['pending_2fa_user_id'])) { redirect('/?page=login'); } ?>
    <section class="auth-card">
        <?= languagePickerHtml($appLocale, 'locale-picker-auth') ?>
        <p class="eyebrow"><?= e(tr('auth.security')) ?></p><h1><?= e(tr('auth.two_factor_title')) ?></h1>
        <p class="meta-line"><?= e(tr('auth.two_factor_intro')) ?></p>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label><?= e(tr('auth.authenticator_code')) ?><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
            <button class="primary" name="action" value="verify_two_factor"><?= e(tr('auth.confirm')) ?></button>
        </form>
        <p><a href="/?page=login"><?= e(tr('auth.back_to_login')) ?></a></p>
    </section>
<?php elseif ($page === 'forgot_password' && !$currentUser): ?>
    <section class="auth-card">
        <?= languagePickerHtml($appLocale, 'locale-picker-auth') ?>
        <p class="eyebrow"><?= e(tr('auth.account')) ?></p><h1><?= e(tr('auth.forgot_title')) ?></h1>
        <?php if(!empty($_SESSION['password_reset_link'])): ?>
            <div class="alert warning"><strong>Testphase:</strong> E-Mail-Versand ist deaktiviert. Nutze diesen Link einmalig: <a href="<?= e($_SESSION['password_reset_link']) ?>">Passwort zurücksetzen</a><input value="<?= e($_SESSION['password_reset_link']) ?>" readonly onclick="this.select()"></div>
        <?php elseif(!empty($_SESSION['password_reset_notice'])): ?>
            <div class="alert warning"><?= e($_SESSION['password_reset_notice']) ?></div>
        <?php endif; ?>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label><?= e(tr('auth.email')) ?><input type="email" name="email" required></label>
            <button class="primary" name="action" value="request_password_reset"><?= e(tr('auth.create_reset_link')) ?></button>
        </form>
        <p><a href="/?page=login"><?= e(tr('auth.back_to_login')) ?></a></p>
    </section>
<?php elseif ($page === 'reset_password' && !$currentUser): ?>
    <?php $resetToken = trim((string) ($_GET['token'] ?? '')); ?>
    <section class="auth-card">
        <?= languagePickerHtml($appLocale, 'locale-picker-auth') ?>
        <p class="eyebrow"><?= e(tr('auth.account')) ?></p><h1><?= e(tr('auth.new_password_title')) ?></h1>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="token" value="<?= e($resetToken) ?>">
            <label><?= e(tr('auth.new_password')) ?><input type="password" name="password" minlength="10" required></label>
            <label><?= e(tr('auth.repeat_password')) ?><input type="password" name="password_confirm" minlength="10" required></label>
            <button class="primary" name="action" value="reset_password"><?= e(tr('auth.change_password')) ?></button>
        </form>
        <p><a href="/?page=login"><?= e(tr('auth.back_to_login')) ?></a></p>
    </section>
<?php elseif ($page === 'register' && !$currentUser): ?>
    <section class="auth-card">
        <?= languagePickerHtml($appLocale, 'locale-picker-auth') ?>
        <p class="eyebrow"><?= e(tr('auth.private_job_crm')) ?></p><h1><?= e(tr('auth.create_account')) ?></h1>
        <p class="meta-line"><?= e(tr('auth.language_notice')) ?></p>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <input type="hidden" name="preferred_language" value="<?= e($appLocale) ?>">
            <div class="two"><label><?= e(tr('auth.first_name')) ?><input name="first_name" required></label><label><?= e(tr('auth.last_name')) ?><input name="last_name" required></label></div>
            <label><?= e(tr('auth.email')) ?><input type="email" name="email" required></label>
            <label><?= e(tr('auth.password')) ?><input type="password" name="password" minlength="10" required></label>
            <button class="primary" name="action" value="register"><?= e(tr('auth.create_account_button')) ?></button>
        </form>
        <p><a href="/?page=login"><?= e(tr('auth.back_to_login')) ?></a></p>
    </section>
<?php elseif ($page === 'guest'): ?>
    <?php
    $guestToken = (string) ($_GET['token'] ?? '');
    $share = activeGuestShare($db, $guestToken);
    if (!$share) {
        http_response_code(404);
        echo '<section class="auth-card"><h1>' . e(tr('sharing.unavailable_title')) . '</h1><p>' . e(tr('sharing.unavailable_body')) . '</p></section>';
    } else {
        $guestSessionId = null;
        $device = deviceHash();
        $session = dbOne($db, 'SELECT id FROM guest_sessions WHERE share_id=? AND device_hash=? AND revoked_at IS NULL', 'is', [(int)$share['id'], $device]);
        if ($session) {
            $guestSessionId = (int) $session['id'];
            $stmt = $db->prepare('UPDATE guest_sessions SET last_seen_at=NOW() WHERE id=?');
            $stmt->bind_param('i', $guestSessionId);
            $stmt->execute();
        } else {
            $stmt = $db->prepare('INSERT INTO guest_sessions (share_id, recipient_email, device_hash, verified_at, last_seen_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->bind_param('iss', $share['id'], $share['recipient_email'], $device);
            $stmt->execute();
            $guestSessionId = (int) $stmt->insert_id;
        }
        $ownerId = (int) $share['owner_user_id'];
        $targetType = (string) $share['target_type'];
        $targetId = (int) ($share['target_id'] ?? 0);
        $guestJobs = [];
        $guestApplications = [];
        $guestDocuments = [];
        if ($targetType === 'area') {
            $guestJobs = dbAll($db, 'SELECT j.id, j.title, j.location_text, j.status, c.name company_name FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL ORDER BY j.updated_at DESC LIMIT 50', 'i', [$ownerId]);
            $guestApplications = dbAll($db, 'SELECT a.id, a.status, a.next_action, a.next_action_at, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC LIMIT 50', 'i', [$ownerId]);
        } elseif ($targetType === 'job') {
            $guestJobs = dbAll($db, 'SELECT j.id, j.title, j.location_text, j.status, c.name company_name, SUBSTRING(j.description,1,65535) description FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.id=? AND j.owner_user_id=? AND j.deleted_at IS NULL', 'ii', [$targetId, $ownerId]);
            $guestDocuments = dbAll($db, 'SELECT id, title, original_filename, file_size FROM user_documents WHERE user_id=? AND job_id=? AND deleted_at IS NULL ORDER BY created_at DESC', 'ii', [$ownerId, $targetId]);
        } elseif ($targetType === 'application') {
            $guestApplications = dbAll($db, 'SELECT a.id, a.status, a.next_action, a.next_action_at, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [$targetId, $ownerId]);
            $guestDocuments = dbAll($db, "SELECT d.id, d.title, d.original_filename, d.file_size FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id WHERE ad.application_id=? AND d.user_id=? AND d.deleted_at IS NULL ORDER BY d.created_at DESC", 'ii', [$targetId, $ownerId]);
        } elseif ($targetType === 'document') {
            $guestDocuments = dbAll($db, 'SELECT id, title, original_filename, file_size FROM user_documents WHERE id=? AND user_id=? AND deleted_at IS NULL', 'ii', [$targetId, $ownerId]);
        }
        $guestTranslations = dbAll($db, 'SELECT entity_type, entity_id, target_language, title, SUBSTRING(body,1,65535) body, version FROM record_translations WHERE owner_user_id=? AND is_current=1 ORDER BY updated_at DESC LIMIT 20', 'i', [$ownerId]);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('sharing.title')) ?></p><h1><?= e($share['title']) ?></h1></div><span><?= e($share['permission']) ?> · <?= e(tr('common.download')) ?> <?= e($share['download_policy']) ?></span></div>
        <?php if(!empty($share['watermark_enabled'])): ?><p class="filter-note"><?= e(tr('sharing.personal_share_notice', null, ['email' => (string)$share['recipient_email']])) ?></p><?php endif; ?>
        <?php if($guestJobs): ?><section class="panel table-wrap"><h2><?= e(tr('nav.jobs')) ?></h2><table><thead><tr><th><?= e(tr('common.title')) ?></th><th><?= e(tr('companies.company')) ?></th><th><?= e(tr('jobs.location')) ?></th><th><?= e(tr('common.status')) ?></th></tr></thead><tbody><?php foreach($guestJobs as $job): ?><tr><td><strong><?= e($job['title']) ?></strong><?php if(!empty($job['description'])): ?><small><?= e(mb_strimwidth((string)$job['description'],0,220,'...')) ?></small><?php endif; ?></td><td><?= e($job['company_name']) ?></td><td><?= e($job['location_text']) ?></td><td><?= e(jobStatusOptions()[(string)$job['status']] ?? (string)$job['status']) ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
        <?php if($guestApplications): ?><section class="panel table-wrap"><h2><?= e(tr('nav.applications')) ?></h2><table><thead><tr><th><?= e(tr('jobs.job')) ?></th><th><?= e(tr('companies.company')) ?></th><th><?= e(tr('common.status')) ?></th><th><?= e(tr('applications.next_action')) ?></th></tr></thead><tbody><?php foreach($guestApplications as $app): ?><tr><td><strong><?= e($app['title']) ?></strong><?php if(!empty($app['cover_letter_text'])): ?><small><?= nl2br(e(mb_strimwidth((string)$app['cover_letter_text'],0,300,'...'))) ?></small><?php endif; ?></td><td><?= e($app['company_name']) ?></td><td><?= e(applicationStatusOptions()[(string)$app['status']] ?? (string)$app['status']) ?></td><td><?= e($app['next_action']) ?><?php if($app['next_action_at']): ?><small><?= e(displayDateTime($app['next_action_at'])) ?></small><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
        <?php if($guestDocuments): ?><section class="panel table-wrap"><h2><?= e(tr('nav.documents')) ?></h2><table><thead><tr><th><?= e(tr('documents.document')) ?></th><th><?= e(tr('documents.file')) ?></th><th><?= e(tr('documents.size')) ?></th><th><?= e(tr('common.download')) ?></th></tr></thead><tbody><?php foreach($guestDocuments as $doc): ?><tr><td><?= e($doc['title']) ?></td><td><?= e($doc['original_filename']) ?></td><td><?= e(bytesLabel((int)$doc['file_size'])) ?></td><td><?php if(in_array((string)$share['download_policy'], ['original','both'], true)): ?><a href="/?page=guest_download&token=<?= e(urlencode($guestToken)) ?>&id=<?= (int)$doc['id'] ?>"><?= e(tr('common.download')) ?></a><?php else: ?><?= e(tr('sharing.download_blocked')) ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></section><?php endif; ?>
        <?php if($guestTranslations): ?><section class="panel"><h2><?= e(tr('translations.title')) ?></h2><div class="log-timeline"><?php foreach($guestTranslations as $translation): ?><article><div><strong><?= e($translation['title'] ?: $translation['entity_type'].' #'.$translation['entity_id']) ?></strong><span><?= e($translation['target_language']) ?> · v<?= (int)$translation['version'] ?></span></div><p><?= nl2br(e($translation['body'])) ?></p></article><?php endforeach; ?></div></section><?php endif; ?>
    <?php } ?>
<?php else: requireLogin(); ?>
    <?php if ($page === 'dashboard'): 
        $stats = [
            ['label' => tr('dashboard.stats.jobs'), 'value' => dbOne($db, 'SELECT COUNT(*) c FROM jobs WHERE owner_user_id=? AND deleted_at IS NULL', 'i', [userId()])['c'], 'href' => '/?page=jobs'],
            ['label' => tr('dashboard.stats.companies'), 'value' => count($companies), 'href' => '/?page=companies'],
            ['label' => tr('dashboard.stats.applications'), 'value' => dbOne($db, 'SELECT COUNT(*) c FROM applications WHERE user_id=? AND deleted_at IS NULL', 'i', [userId()])['c'], 'href' => '/?page=applications'],
            ['label' => tr('dashboard.stats.pendents'), 'value' => dbOne($db, "SELECT COUNT(*) c FROM applications a JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE a.user_id=? AND a.next_action_at IS NOT NULL AND a.deleted_at IS NULL AND a.status NOT IN ('rejected','withdrawn','closed')", 'i', [userId()])['c'], 'href' => '/?page=pendents'],
        ]; ?>
        <div class="hero"><div><p class="eyebrow"><?= e(tr('dashboard.greeting', null, ['name' => (string)$currentUser['first_name']])) ?></p><h1><?= e(tr('dashboard.title')) ?></h1><p><?= e(tr('dashboard.subtitle')) ?></p></div><a class="button primary" href="/?page=jobs#new"><?= e(tr('dashboard.create_job')) ?></a></div>
        <div class="stats"><?php foreach ($stats as $stat): ?><a class="stat-link" href="<?= e($stat['href']) ?>"><article><strong><?= e((string) $stat['value']) ?></strong><span><?= e($stat['label']) ?></span></article></a><?php endforeach; ?></div>
        <section class="panel"><h2><?= e(tr('dashboard.next_title')) ?></h2><p><?= e(tr('dashboard.next_body')) ?></p></section>
    <?php elseif ($page === 'pendents'): ?>
        <?php
        $pendentTypes = [
            'application' => tr('nav.applications'),
            'contact' => tr('nav.contacts'),
            'calendar' => tr('nav.calendar'),
        ];
        $pendentSfFields = [
            'due_at'=>['label'=>tr('common.due')],
            'type'=>['label'=>tr('common.area'), 'choices'=>array_combine(array_values($pendentTypes), array_values($pendentTypes))],
            'title'=>['label'=>tr('common.title')],
            'status'=>['label'=>tr('common.status')],
            'ref'=>['label'=>tr('common.reference')],
        ];
        $pendentSf = sfState('pendents', $pendentSfFields, ['sort'=>'due_at','dir'=>'asc']);
        $pendentPreserve = ['page'=>'pendents'];
        $now = new DateTimeImmutable('now', new DateTimeZone((string)($currentUser['timezone'] ?? 'Europe/Zurich')));
        $todayStart = $now->setTime(0, 0)->format('Y-m-d H:i:s');
        $pendents = [];
        $nextActionLabels = applicationNextActionOptions();
        foreach (dbAll($db, "SELECT a.id, a.status, a.next_action title, a.next_action_at due_at, j.title job_title, c.name company FROM applications a JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE a.user_id=? AND a.deleted_at IS NULL AND a.next_action_at IS NOT NULL AND a.status NOT IN ('rejected','withdrawn','closed')", 'i', [userId()]) as $row) {
            $pendentTitle = (string)($row['title'] ?: tr('nav.pendents'));
            $pendents[] = ['type'=>$pendentTypes['application'],'status'=>applicationStatusOptions()[(string)$row['status']] ?? (string)$row['status'],'title'=>$nextActionLabels[$pendentTitle] ?? $pendentTitle,'due_at'=>(string)$row['due_at'],'ref'=>$row['job_title'].' · '.$row['company'],'href'=>'/?page=applications&edit='.(int)$row['id'].'#application-form'];
        }
        foreach (dbAll($db, 'SELECT l.id, l.contact_id, l.status, l.subject title, l.follow_up_at due_at, c.first_name, c.last_name, co.name company FROM contact_logs l JOIN contacts c ON c.id=l.contact_id AND c.deleted_at IS NULL JOIN companies co ON co.id=l.company_id AND co.deleted_at IS NULL WHERE l.owner_user_id=? AND l.follow_up_at IS NOT NULL AND l.status IN ("open","planned")', 'i', [userId()]) as $row) {
            $pendents[] = ['type'=>$pendentTypes['contact'],'status'=>contactLogStatusOptions()[(string)$row['status']] ?? (string)$row['status'],'title'=>(string)($row['title'] ?: tr('contact_log.follow_up')),'due_at'=>(string)$row['due_at'],'ref'=>trim($row['first_name'].' '.$row['last_name'].' · '.$row['company']),'href'=>'/?page=contacts&edit_contact='.(int)$row['contact_id'].'#contact-log'];
        }
        foreach (dbAll($db, 'SELECT ce.id, ce.title, ce.event_type, ce.status, ce.starts_at due_at, ce.application_id, j.title job_title, c.name company FROM calendar_events ce LEFT JOIN applications a ON a.id=ce.application_id AND a.deleted_at IS NULL LEFT JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL LEFT JOIN companies c ON c.id=j.company_id AND c.deleted_at IS NULL WHERE ce.owner_user_id=? AND ce.status="planned" AND (ce.application_id IS NULL OR a.id IS NOT NULL)', 'i', [userId()]) as $row) {
            $pendents[] = ['type'=>$pendentTypes['calendar'],'status'=>calendarEventTypeOptions()[(string)$row['event_type']] ?? (string)$row['event_type'],'title'=>(string)$row['title'],'due_at'=>(string)$row['due_at'],'ref'=>trim((string)($row['job_title'] ?? '').' · '.(string)($row['company'] ?? ''), ' ·'),'href'=>!empty($row['application_id'])?'/?page=applications&edit='.(int)$row['application_id'].'#application-form':'/?page=calendar'];
        }
        $pendents = sfApplyRows($pendents, $pendentSf, $pendentSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('nav.pendents')) ?></p><h1><?= e(tr('pendents.title')) ?></h1></div><span><?= e(tr('common.entries_count', null, ['count' => (string) count($pendents)])) ?></span></div>
        <div class="actions export-actions"><?= sfToolbar('pendents', $pendentSf, $pendentPreserve, $pendentSfFields) ?></div>
        <section class="panel table-wrap"><table><thead><tr><?= sfHeader('pendents','due_at',tr('common.due'),$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','type',tr('common.area'),$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','title',tr('common.title'),$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','status',tr('common.status'),$pendentSf,$pendentPreserve) ?><?= sfHeader('pendents','ref',tr('common.reference'),$pendentSf,$pendentPreserve) ?><th><?= e(tr('common.action')) ?></th></tr></thead><tbody><?php foreach($pendents as $item): $isOverdue=(string)$item['due_at'] < $todayStart; ?><tr class="<?= $isOverdue ? 'is-overdue' : '' ?>"><td><?= e(displayDateTime($item['due_at'], $currentUser)) ?></td><td><?= e($item['type']) ?></td><td><strong><?= e($item['title']) ?></strong></td><td><?= e($item['status']) ?></td><td><?= e($item['ref']) ?></td><td><a href="<?= e($item['href']) ?>"><?= e(tr('common.open')) ?></a></td></tr><?php endforeach; ?><?php if(!$pendents): ?><tr><td colspan="6" class="empty"><?= e(tr('pendents.empty')) ?></td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'sharing'): ?>
        <?php
        $shares = dbAll($db, 'SELECT * FROM guest_shares WHERE owner_user_id=? ORDER BY created_at DESC', 'i', [userId()]);
        $shareTargets = [
            'area' => tr('sharing.target.area'),
            'job' => tr('sharing.target.job'),
            'application' => tr('sharing.target.application'),
            'document' => tr('sharing.target.document'),
        ];
        $shareTargetGroups = array_intersect_key(translationTargetOptions($db, userId()), array_flip(['job','application','document']));
        $shareTargetLabels = [];
        foreach ($shareTargetGroups as $type => $group) {
            foreach ($group['rows'] as $target) {
                $shareTargetLabels[$type . ':' . (int)$target['id']] = (string)$target['label'];
            }
        }
        foreach ($shares as &$shareRow) {
            $shareKey = (string)$shareRow['target_type'] . ':' . (int)$shareRow['target_id'];
            $shareRow['target_label'] = (string) ($shareRow['target_type'] === 'area' ? tr('sharing.target.area') : ($shareTargetLabels[$shareKey] ?? ($shareTargets[$shareRow['target_type']] ?? $shareRow['target_type'])));
            $shareRow['status_label'] = $shareRow['revoked_at'] ? tr('sharing.status.revoked') : (($shareRow['expires_at'] && strtotime((string)$shareRow['expires_at']) < time()) ? tr('sharing.status.expired') : tr('sharing.status.active'));
        }
        unset($shareRow);
        $shareSfFields = [
            'title'=>['label'=>tr('common.title')],
            'target_label'=>['label'=>tr('sharing.target')],
            'recipient_email'=>['label'=>tr('sharing.recipient')],
            'status_label'=>['label'=>tr('common.status'), 'choices'=>array_combine([tr('sharing.status.active'), tr('sharing.status.expired'), tr('sharing.status.revoked')], [tr('sharing.status.active'), tr('sharing.status.expired'), tr('sharing.status.revoked')])],
        ];
        $shareSf = sfState('sharing', $shareSfFields, ['sort'=>'title','dir'=>'asc']);
        $sharePreserve = ['page'=>'sharing'];
        $shares = sfApplyRows($shares, $shareSf, $shareSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('sharing.section')) ?></p><h1><?= e(tr('sharing.title')) ?></h1></div><span><?= e(tr('sharing.links_count', null, ['count' => (string) count($shares)])) ?></span></div>
        <?php if(!empty($_SESSION['last_share_link'])): ?><div class="alert warning"><strong><?= e(tr('sharing.share_link')) ?>:</strong> <a href="<?= e($_SESSION['last_share_link']) ?>"><?= e($_SESSION['last_share_link']) ?></a><input value="<?= e($_SESSION['last_share_link']) ?>" readonly onclick="this.select()"></div><?php unset($_SESSION['last_share_link']); endif; ?>
        <div class="split"><section class="panel"><h2><?= e(tr('sharing.new_share')) ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <label><?= e(tr('common.title')) ?><input name="title" placeholder="<?= e(tr('sharing.title_placeholder')) ?>"></label>
            <label><?= e(tr('sharing.recipient_email')) ?><input type="email" name="recipient_email" required></label>
            <label><?= e(tr('sharing.target')) ?><select name="share_target" required><option value="area"><?= e(tr('sharing.target.area')) ?></option><?php foreach($shareTargetGroups as $type=>$group): if(!$group['rows']) continue; ?><optgroup label="<?= e((string)$group['label']) ?>"><?php foreach($group['rows'] as $target): ?><option value="<?= e($type . ':' . (int)$target['id']) ?>"><?= e((string)$target['label']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <div class="two"><label><?= e(tr('sharing.permission')) ?><select name="permission"><option value="view"><?= e(tr('sharing.permission.view')) ?></option><option value="comment"><?= e(tr('sharing.permission.comment')) ?></option><option value="edit"><?= e(tr('sharing.permission.edit_prepared')) ?></option></select></label><label><?= e(tr('common.download')) ?><select name="download_policy"><option value="none"><?= e(tr('sharing.download.none')) ?></option><option value="original"><?= e(tr('sharing.download.original')) ?></option><option value="pdf"><?= e(tr('sharing.download.pdf')) ?></option><option value="both"><?= e(tr('sharing.download.both')) ?></option></select></label></div>
            <label><?= e(tr('sharing.expires_at')) ?><input type="datetime-local" name="expires_at"></label>
            <label class="check"><input type="checkbox" name="watermark_enabled" value="1" checked> <?= e(tr('sharing.watermark')) ?></label>
            <button class="primary" name="action" value="create_share"><?= e(tr('sharing.create_share')) ?></button>
        </form></section>
        <section class="panel table-wrap"><h2><?= e(tr('sharing.active_previous')) ?></h2><div class="actions export-actions"><?= sfToolbar('sharing', $shareSf, $sharePreserve, $shareSfFields) ?></div><table><thead><tr><?= sfHeader('sharing','title',tr('common.title'),$shareSf,$sharePreserve) ?><?= sfHeader('sharing','target_label',tr('sharing.target'),$shareSf,$sharePreserve) ?><?= sfHeader('sharing','recipient_email',tr('sharing.recipient'),$shareSf,$sharePreserve) ?><?= sfHeader('sharing','status_label',tr('common.status'),$shareSf,$sharePreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($shares as $share): ?><tr><td><strong><?= e($share['title']) ?></strong><small><?= e($share['permission']) ?> · <?= e(tr('common.download')) ?> <?= e($share['download_policy']) ?></small></td><td><?= e($share['target_label']) ?></td><td><?= e($share['recipient_email']) ?></td><td><?= e($share['status_label']) ?><small><?= e($share['last_accessed_at'] ? tr('sharing.last_access', null, ['date' => displayDateTime($share['last_accessed_at'], $currentUser)]) : tr('sharing.no_access')) ?></small></td><td><?php if(!$share['revoked_at']): ?><form method="post" onsubmit="return confirm('<?= e(tr('sharing.revoke_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="share_id" value="<?= (int)$share['id'] ?>"><button name="action" value="revoke_share"><?= e(tr('sharing.revoke')) ?></button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$shares): ?><tr><td colspan="5" class="empty"><?= e(tr('sharing.empty')) ?></td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'reports'): ?>
        <?php
        $reports = dbAll($db, 'SELECT id, name, description, base_entity, display_type, updated_at FROM saved_reports WHERE owner_user_id=? ORDER BY updated_at DESC', 'i', [userId()]);
        $editReportId = (int) ($_GET['edit_report'] ?? 0);
        $editReport = $editReportId > 0 ? dbOne($db, 'SELECT id, name, description, base_entity, display_type FROM saved_reports WHERE id=? AND owner_user_id=?', 'ii', [$editReportId, userId()]) : null;
        $reportEditMissing = $editReportId > 0 && !$editReport;
        $reportBaseOptions = reportBaseOptions();
        $reportDisplayOptions = reportDisplayOptions();
        foreach ($reports as &$reportRow) {
            $reportRow['base_label'] = (string) ($reportBaseOptions[$reportRow['base_entity']] ?? $reportRow['base_entity']);
            $reportRow['display_label'] = (string) ($reportDisplayOptions[$reportRow['display_type']] ?? $reportRow['display_type']);
        }
        unset($reportRow);
        $reportListSfFields = [
            'name'=>['label'=>tr('common.name')],
            'base_label'=>['label'=>tr('reports.base'), 'choices'=>array_combine(array_values($reportBaseOptions), array_values($reportBaseOptions))],
            'display_label'=>['label'=>tr('reports.view'), 'choices'=>array_combine(array_values($reportDisplayOptions), array_values($reportDisplayOptions))],
            'updated_at'=>['label'=>tr('common.updated')],
        ];
        $reportListSf = sfState('reports', $reportListSfFields, ['sort'=>'updated_at','dir'=>'desc']);
        $reportListPreserve = ['page'=>'reports', 'edit_report'=>$editReportId ?: ''];
        $reports = sfApplyRows($reports, $reportListSf, $reportListSfFields);
        $reportBase = (string)($editReport['base_entity'] ?? 'jobs');
        $reportFields = reportFieldOptions($reportBase);
        $reportSettings = $editReport ? loadReportSettings($db, (int)$editReport['id'], $reportBase) : ['columns'=>reportDefaultColumns($reportBase), 'filters'=>[], 'sort'=>['field_name'=>array_key_first($reportFields), 'direction'=>'asc']];
        $reportStatuses = reportStatusOptions($reportBase);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('nav.reporting')) ?></p><h1><?= e(tr('reports.title')) ?></h1></div><span><?= e(tr('reports.count', null, ['count' => (string) count($reports)])) ?></span></div>
        <?php if($reportEditMissing): ?><div class="alert warning"><?= e(tr('reports.not_found')) ?></div><?php endif; ?>
        <div class="reports-layout">
            <section class="panel report-editor-panel" id="report-editor">
                <h2><?= e($editReport ? tr('reports.edit') : tr('reports.save')) ?></h2>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <?php if($editReport): ?><input type="hidden" name="report_id" value="<?= (int)$editReport['id'] ?>"><?php endif; ?>
                    <label><?= e(tr('common.name')) ?><input name="report_name" value="<?= e($editReport['name'] ?? '') ?>" required></label>
                    <label><?= e(tr('common.description')) ?><textarea name="report_description" rows="3"><?= e($editReport['description'] ?? '') ?></textarea></label>
                    <div class="two">
                        <label><?= e(tr('reports.base')) ?><select name="base_entity"><?php foreach($reportBaseOptions as $v=>$l): ?><option value="<?= e($v) ?>" <?= $reportBase===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                        <label><?= e(tr('reports.view')) ?><select name="display_type"><?php foreach($reportDisplayOptions as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($editReport['display_type'] ?? 'table')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <fieldset class="report-config"><legend><?= e(tr('reports.columns')) ?></legend><?php foreach($reportFields as $field=>$label): ?><label class="check"><input type="checkbox" name="report_columns[]" value="<?= e($field) ?>" <?= in_array($field, $reportSettings['columns'], true)?'checked':'' ?>> <?= e($label) ?></label><?php endforeach; ?></fieldset>
                    <div class="two"><label><?= e(tr('reports.filter_text')) ?><input name="report_q" value="<?= e((string)($reportSettings['filters']['q'] ?? '')) ?>" placeholder="<?= e(tr('reports.all_columns')) ?>"></label><label><?= e(tr('common.status')) ?><select name="report_status"><option value=""><?= e(tr('common.all')) ?></option><?php foreach($reportStatuses as $v=>$l): ?><option value="<?= e($v) ?>" <?= (string)($reportSettings['filters']['status'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
                    <div class="two"><label><?= e(tr('reports.sort_by')) ?><select name="report_sort"><?php foreach($reportFields as $field=>$label): ?><option value="<?= e($field) ?>" <?= (string)($reportSettings['sort']['field_name'] ?? '')===$field?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label><?= e(tr('reports.direction')) ?><select name="report_dir"><option value="asc" <?= ($reportSettings['sort']['direction'] ?? 'asc')==='asc'?'selected':'' ?>><?= e(tr('sf.asc')) ?></option><option value="desc" <?= ($reportSettings['sort']['direction'] ?? '')==='desc'?'selected':'' ?>><?= e(tr('sf.desc')) ?></option></select></label></div>
                    <div class="actions"><button class="primary" name="action" value="<?= $editReport ? 'update_report' : 'save_report' ?>"><?= e($editReport ? tr('common.save_changes') : tr('common.save')) ?></button><?php if($editReport): ?><a class="button" href="/?page=reports"><?= e(tr('common.new')) ?></a><a class="button" href="/?page=export_pdf&type=report&report_id=<?= (int)$editReport['id'] ?>">PDF</a><?php endif; ?></div>
                </form>
                <div class="actions export-actions"><?= sfToolbar('reports', $reportListSf, $reportListPreserve, $reportListSfFields) ?><a class="button primary" href="/?page=export_pdf&type=rav">RAV-Dossier PDF</a><a class="button" href="/?page=export_csv&type=jobs"><?= e(tr('nav.jobs')) ?> CSV</a><a class="button" href="/?page=export_pdf&type=jobs"><?= e(tr('nav.jobs')) ?> PDF</a><a class="button" href="/?page=export_csv&type=applications"><?= e(tr('nav.applications')) ?> CSV</a><a class="button" href="/?page=export_pdf&type=applications"><?= e(tr('nav.applications')) ?> PDF</a><a class="button" href="/?page=export_csv&type=audit"><?= e(tr('audit.title')) ?> CSV</a></div>
            </section>
            <section class="panel table-wrap"><h2><?= e(tr('reports.saved')) ?></h2><table><thead><tr><?= sfHeader('reports','name',tr('common.name'),$reportListSf,$reportListPreserve) ?><?= sfHeader('reports','base_label',tr('reports.base'),$reportListSf,$reportListPreserve) ?><?= sfHeader('reports','display_label',tr('reports.view'),$reportListSf,$reportListPreserve) ?><?= sfHeader('reports','updated_at',tr('common.updated'),$reportListSf,$reportListPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($reports as $report): ?><tr class="<?= $editReport && (int)$editReport['id']===(int)$report['id'] ? 'is-selected' : '' ?>"><td><strong><?= e($report['name']) ?></strong><small><?= e($report['description']) ?></small></td><td><?= e($report['base_label']) ?></td><td><?= e($report['display_label']) ?></td><td><?= e(displayDateTime($report['updated_at'], $currentUser)) ?></td><td class="actions"><a href="<?= e(reportOpenUrl($report)) ?>"><?= e(tr('common.show')) ?></a><a href="/?page=reports&edit_report=<?= (int)$report['id'] ?>#report-editor"><?= e(tr('common.edit')) ?></a><a href="/?page=export_pdf&type=report&report_id=<?= (int)$report['id'] ?>">PDF</a><form method="post" onsubmit="return confirm('<?= e(tr('reports.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>"><button name="action" value="delete_report"><?= e(tr('common.delete')) ?></button></form></td></tr><?php endforeach; ?><?php if(!$reports): ?><tr><td colspan="5" class="empty"><?= e(tr('reports.empty')) ?></td></tr><?php endif; ?></tbody></table></section>
        </div>
    <?php elseif ($page === 'calendar'): ?>
        <?php
        $calendarViews = calendarViewOptions();
        $calendarView = array_key_exists((string)($_GET['view'] ?? 'agenda'), $calendarViews) ? (string)$_GET['view'] : 'agenda';
        $anchor = calendarAnchorDate($currentUser);
        [$rangeStart, $rangeEnd, $prevStep, $nextStep] = calendarRange($calendarView, $anchor);
        $calendarEvents = calendarEventRows($db, userId(), $rangeStart, $rangeEnd);
        $calendarStatusLabels = calendarStatusOptions();
        $calendarSfFields = [
            'starts_at'=>['label'=>tr('common.time')],
            'title'=>['label'=>tr('calendar.event')],
            'type'=>['label'=>tr('calendar.type')],
            'status'=>['label'=>tr('common.status'), 'choices'=>array_combine(array_values($calendarStatusLabels), array_values($calendarStatusLabels))],
            'meta'=>['label'=>tr('common.reference')],
        ];
        $calendarSf = sfState('calendar_agenda', $calendarSfFields, ['sort'=>'starts_at','dir'=>'asc']);
        $calendarPreserve = ['page'=>'calendar', 'view'=>'agenda', 'date'=>$anchor->format('Y-m-d')];
        if ($calendarView === 'agenda') {
            $calendarEvents = sfApplyRows($calendarEvents, $calendarSf, $calendarSfFields);
        }
        $prevDate = $anchor->modify($prevStep)->format('Y-m-d');
        $nextDate = $anchor->modify($nextStep)->format('Y-m-d');
        $weekNo = $anchor->format('W');
        $weekdayNames = ['Mon'=>tr('weekday.mon_short'),'Tue'=>tr('weekday.tue_short'),'Wed'=>tr('weekday.wed_short'),'Thu'=>tr('weekday.thu_short'),'Fri'=>tr('weekday.fri_short'),'Sat'=>tr('weekday.sat_short'),'Sun'=>tr('weekday.sun_short')];
        $hours = range(7, 19);
        $eventsByDate = [];
        foreach ($calendarEvents as $entry) {
            $eventsByDate[substr((string)$entry['starts_at'], 0, 10)][] = $entry;
        }
        $isDayEntry = static fn(array $entry): bool => date('H:i:s', strtotime((string)$entry['starts_at'])) === '00:00:00';
        foreach ($eventsByDate as &$dateEvents) {
            usort($dateEvents, static function(array $a, array $b) use ($isDayEntry): int {
                $aDay = $isDayEntry($a) ? 0 : 1;
                $bDay = $isDayEntry($b) ? 0 : 1;
                return [$aDay, (string)$a['starts_at'], (string)$a['title']] <=> [$bDay, (string)$b['starts_at'], (string)$b['title']];
            });
        }
        unset($dateEvents);
        $viewUrl = static fn(string $view, string $date): string => '/?page=calendar&view=' . urlencode($view) . '&date=' . urlencode($date);
        $newStart = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string)($_GET['new_start'] ?? '')) ? (string)$_GET['new_start'] : '';
        $newEntryUrl = static fn(string $dateTime): string => '/?page=calendar&view=' . urlencode($calendarView) . '&date=' . urlencode(substr($dateTime, 0, 10)) . '&new_start=' . urlencode($dateTime) . '#new-calendar-entry';
        $icsUrl = '/?page=export_ics&view=' . urlencode($calendarView) . '&date=' . urlencode($anchor->format('Y-m-d'));
        $headline = match($calendarView) {
            'day' => $anchor->format('d.m.Y') . ' · ' . tr('calendar.week_number_short') . ' ' . $weekNo,
            'workweek' => $rangeStart->format('d.m.') . ' - ' . $rangeEnd->format('d.m.Y') . ' · ' . tr('calendar.week_number_short') . ' ' . $weekNo,
            'week' => $rangeStart->format('d.m.') . ' - ' . $rangeEnd->format('d.m.Y') . ' · ' . tr('calendar.week_number_short') . ' ' . $weekNo,
            'month' => $anchor->format('m.Y'),
            default => $rangeStart->format('d.m.Y') . ' - ' . $rangeEnd->format('d.m.Y'),
        };
        $renderEvent = static function(array $entry, bool $showTime = true) use ($isDayEntry): string {
            $time = date('H:i', strtotime((string)$entry['starts_at']));
            $title = (!$showTime || $isDayEntry($entry)) ? (string)$entry['title'] : $time . ' ' . (string)$entry['title'];
            $class = $isDayEntry($entry) ? 'calendar-event is-day-entry' : 'calendar-event';
            return '<a class="' . $class . '" href="' . e((string)$entry['href']) . '"><strong>' . e($title) . '</strong><span>' . e((string)$entry['type'] . ((string)$entry['meta'] !== '' ? ' · ' . (string)$entry['meta'] : '')) . '</span></a>';
        };
        $appsForCalendar = dbAll($db, 'SELECT a.id, j.title, c.name company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id WHERE a.user_id=? AND a.deleted_at IS NULL ORDER BY a.updated_at DESC LIMIT 100', 'i', [userId()]);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('calendar.section')) ?></p><h1><?= e(tr('calendar.title')) ?></h1></div><span><?= e($headline) ?> · <?= e(tr('common.entries_count', null, ['count' => (string) count($calendarEvents)])) ?></span></div>
        <div class="calendar-toolbar"><div class="actions"><a class="button" href="<?= e($viewUrl($calendarView, $prevDate)) ?>"><?= e(tr('calendar.previous')) ?></a><a class="button" href="<?= e($viewUrl($calendarView, (new DateTimeImmutable('today'))->format('Y-m-d'))) ?>"><?= e(tr('calendar.today')) ?></a><a class="button" href="<?= e($viewUrl($calendarView, $nextDate)) ?>"><?= e(tr('calendar.next')) ?></a><a class="button" href="<?= e($icsUrl) ?>">ICS</a></div><form method="get" class="actions"><input type="hidden" name="page" value="calendar"><input type="hidden" name="date" value="<?= e($anchor->format('Y-m-d')) ?>"><select name="view" onchange="this.form.submit()"><?php foreach($calendarViews as $value=>$label): ?><option value="<?= e($value) ?>" <?= $calendarView===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></form></div>
        <section class="panel calendar-panel matrix-first">
        <?php if($calendarView === 'agenda'): ?>
            <h2><?= e(tr('calendar.agenda')) ?></h2><div class="actions export-actions"><?= sfToolbar('calendar_agenda', $calendarSf, $calendarPreserve, $calendarSfFields) ?></div><div class="table-wrap"><table><thead><tr><?= sfHeader('calendar_agenda','starts_at',tr('common.time'),$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','title',tr('calendar.event'),$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','type',tr('calendar.type'),$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','status',tr('common.status'),$calendarSf,$calendarPreserve) ?><?= sfHeader('calendar_agenda','meta',tr('common.reference'),$calendarSf,$calendarPreserve) ?></tr></thead><tbody><?php foreach($calendarEvents as $event): ?><tr><td><?= e(displayDateTime($event['starts_at'], $currentUser)) ?></td><td><a href="<?= e($event['href']) ?>"><?= e($event['title']) ?></a></td><td><?= e($event['type']) ?></td><td><?= e($event['status']) ?></td><td><?= e($event['meta']) ?></td></tr><?php endforeach; ?><?php if(!$calendarEvents): ?><tr><td colspan="5" class="empty"><?= e(tr('calendar.empty')) ?></td></tr><?php endif; ?></tbody></table></div>
        <?php elseif(in_array($calendarView, ['day','workweek','week'], true)): ?>
            <?php $days=[]; for($d=$rangeStart; $d <= $rangeEnd; $d=$d->modify('+1 day')) { $days[]=$d; } ?>
            <h2><?= e($calendarView==='day' ? tr('calendar.day_plan') : ($calendarView==='workweek' ? tr('calendar.workweek_plan') : tr('calendar.week_plan'))) ?> · <?= e(tr('calendar.week_number_short')) ?> <?= e($weekNo) ?></h2><div class="time-grid" style="--day-count:<?= count($days) ?>;--matrix-min:<?= count($days) === 1 ? '520px' : (count($days) === 5 ? '980px' : '1180px') ?>"><div class="time-head"></div><?php foreach($days as $day): ?><div class="time-day-head"><?= e(($weekdayNames[$day->format('D')] ?? $day->format('D')) . ', ' . $day->format('d.m.')) ?></div><?php endforeach; ?><div class="time-all-day-label"><?= e(tr('calendar.all_day')) ?></div><?php foreach($days as $day): $dateKey=$day->format('Y-m-d'); $dayEntries=array_values(array_filter($eventsByDate[$dateKey] ?? [], $isDayEntry)); ?><div class="time-all-day-cell"><a class="calendar-add" href="<?= e($newEntryUrl($dateKey.'T00:00')) ?>">+</a><?php foreach($dayEntries as $event): ?><?= $renderEvent($event, false) ?><?php endforeach; ?></div><?php endforeach; ?><?php foreach($hours as $hour): ?><div class="time-slot"><?= sprintf('%02d:00', $hour) ?></div><?php foreach($days as $day): $dateKey=$day->format('Y-m-d'); $slotStart=$dateKey.'T'.sprintf('%02d:00',$hour); $hourEvents=array_values(array_filter($eventsByDate[$dateKey] ?? [], static fn(array $ev): bool => date('H:i:s', strtotime((string)$ev['starts_at'])) !== '00:00:00' && (int)date('G', strtotime((string)$ev['starts_at'])) === $hour)); ?><div class="time-cell"><a class="calendar-add" href="<?= e($newEntryUrl($slotStart)) ?>">+</a><?php foreach($hourEvents as $event): ?><?= $renderEvent($event) ?><?php endforeach; ?></div><?php endforeach; ?><?php endforeach; ?></div>
        <?php else: ?>
            <?php $monthStart=$rangeStart->modify('monday this week'); $monthEnd=$rangeEnd->modify('sunday this week'); $monthDays=[]; for($d=$monthStart; $d <= $monthEnd; $d=$d->modify('+1 day')) { $monthDays[]=$d; } ?>
            <h2><?= e(tr('calendar.month_plan')) ?> <?= e($anchor->format('m.Y')) ?></h2><div class="month-grid"><div class="month-week-head"><?= e(tr('calendar.week_number_short')) ?></div><?php foreach([tr('weekday.mon_short'),tr('weekday.tue_short'),tr('weekday.wed_short'),tr('weekday.thu_short'),tr('weekday.fri_short'),tr('weekday.sat_short'),tr('weekday.sun_short')] as $wd): ?><div class="month-day-head"><?= e($wd) ?></div><?php endforeach; ?><?php foreach(array_chunk($monthDays,7) as $week): ?><div class="month-week-no"><?= e($week[0]->format('W')) ?></div><?php foreach($week as $day): $dateKey=$day->format('Y-m-d'); ?><div class="month-day <?= $day->format('m')===$anchor->format('m')?'':'is-muted' ?>"><div class="month-day-top"><strong><?= e($day->format('d.m.')) ?></strong><a class="calendar-add" href="<?= e($newEntryUrl($dateKey.'T09:00')) ?>">+</a></div><?php foreach(($eventsByDate[$dateKey] ?? []) as $event): ?><?= $renderEvent($event, !$isDayEntry($event)) ?><?php endforeach; ?></div><?php endforeach; ?><?php endforeach; ?></div>
        <?php endif; ?>
        </section><details class="panel calendar-entry-panel" id="new-calendar-entry" <?= $newStart !== '' ? 'open' : '' ?>><summary><?= e(tr('calendar.create_entry')) ?></summary><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label><?= e(tr('common.title')) ?><input name="event_title" required></label><label><?= e(tr('calendar.type')) ?><select name="event_type"><?php foreach(calendarEventTypeOptions() as $value=>$label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label><label><?= e(tr('calendar.start')) ?><input type="datetime-local" name="starts_at" value="<?= e($newStart) ?>" required></label><label><?= e(tr('applications.application')) ?><select name="application_id"><option value="0"><?= e(tr('calendar.no_link')) ?></option><?php foreach($appsForCalendar as $app): ?><option value="<?= (int)$app['id'] ?>"><?= e($app['title'].' · '.$app['company_name']) ?></option><?php endforeach; ?></select></label><label><?= e(tr('contact_log.notes')) ?><textarea name="event_notes" rows="3"></textarea></label><button class="primary" name="action" value="save_calendar_event"><?= e(tr('common.save')) ?></button></form></details>
    <?php elseif ($page === 'translations'): ?>
        <?php
        $translationDraft = $_SESSION['translation_draft'] ?? [];
        unset($_SESSION['translation_draft']);
        $translations = dbAll($db, 'SELECT id, entity_type, entity_id, target_language, title, SUBSTRING(body,1,65535) body, version, is_current, updated_at FROM record_translations WHERE owner_user_id=? ORDER BY updated_at DESC LIMIT 100', 'i', [userId()]);
        $translationTargets = translationTargetOptions($db, userId());
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('translations.section')) ?></p><h1><?= e(tr('translations.title')) ?></h1></div><span><?= count($translations) ?> <?= e(tr('common.versions')) ?></span></div>
        <div class="split"><section class="panel" id="translation-form"><h2><?= e(tr('translations.save_title')) ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label><?= e(tr('translations.record')) ?><select name="translation_target" required><option value=""><?= e(tr('common.please_select')) ?></option><?php foreach($translationTargets as $type=>$group): if(!$group['rows']) continue; ?><optgroup label="<?= e((string)$group['label']) ?>"><?php foreach($group['rows'] as $target): $targetValue=$type . ':' . (int)$target['id']; ?><option value="<?= e($targetValue) ?>" <?= ($translationDraft['target'] ?? '')===$targetValue?'selected':'' ?>><?= e((string)$target['label']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label><?= e(tr('translations.target_language')) ?><select name="target_language"><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($translationDraft['target_language'] ?? '')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><label><?= e(tr('common.title')) ?><input name="translation_title" value="<?= e((string)($translationDraft['title'] ?? '')) ?>"></label><label><?= e(tr('translations.body')) ?><textarea name="translation_body" rows="12" required><?= e((string)($translationDraft['body'] ?? '')) ?></textarea><small><?= e(tr('translations.prepare_hint')) ?></small></label><div class="actions"><button name="action" value="prepare_translation"><?= e(tr('translations.prepare')) ?></button><button class="primary" name="action" value="save_translation"><?= e(tr('common.save')) ?></button></div></form></section><section class="panel"><h2><?= e(tr('translations.saved_title')) ?></h2><div class="log-timeline"><?php foreach($translations as $translation): ?><article><div><strong><?= e($translation['title'] ?: ucfirst((string)$translation['entity_type'])) ?></strong><span><?= e($translation['target_language']) ?> · v<?= (int)$translation['version'] ?><?= $translation['is_current'] ? ' · ' . e(tr('common.current')) : '' ?></span></div><p><?= nl2br(e(mb_strimwidth((string)$translation['body'],0,500,'...'))) ?></p></article><?php endforeach; ?><?php if(!$translations): ?><p class="empty"><?= e(tr('translations.empty')) ?></p><?php endif; ?></div></section></div>
    <?php elseif ($page === 'privacy'): ?>
        <?php
        $usage = storageUsageBytes($db, userId());
        $quota = dbOne($db, 'SELECT quota_bytes FROM storage_quotas WHERE user_id=?', 'i', [userId()]);
        $quotaBytes = (int) ($quota['quota_bytes'] ?? 5368709120);
        $cleanupRequests = dbAll($db, 'SELECT id, cutoff_date, status, SUBSTRING(preview_json,1,65535) preview_json, created_at FROM cleanup_requests WHERE user_id=? ORDER BY created_at DESC LIMIT 20', 'i', [userId()]);
        foreach ($cleanupRequests as &$cleanupRow) {
            $preview = json_decode((string)$cleanupRow['preview_json'], true) ?: [];
            $cleanupRow['preview_text'] = tr('privacy.preview_text', null, [
                'jobs' => (string) (int)($preview['jobs'] ?? 0),
                'applications' => (string) (int)($preview['applications'] ?? 0),
                'documents' => (string) (int)($preview['old_document_versions'] ?? 0),
                'size' => bytesLabel((int)($preview['document_bytes'] ?? 0)),
            ]);
        }
        unset($cleanupRow);
        $cleanupSfFields = [
            'cutoff_date'=>['label'=>tr('privacy.cutoff_date')],
            'status'=>['label'=>tr('common.status')],
            'preview_text'=>['label'=>tr('common.preview')],
            'created_at'=>['label'=>tr('common.created')],
        ];
        $cleanupSf = sfState('cleanup_requests', $cleanupSfFields, ['sort'=>'created_at','dir'=>'desc']);
        $cleanupPreserve = ['page'=>'privacy'];
        $cleanupRequests = sfApplyRows($cleanupRequests, $cleanupSf, $cleanupSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('privacy.section')) ?></p><h1><?= e(tr('privacy.title')) ?></h1></div><span><?= e(bytesLabel($usage)) ?> / <?= e(bytesLabel($quotaBytes)) ?></span></div>
        <div class="split"><section class="panel"><h2><?= e(tr('privacy.storage_quota')) ?></h2><p><?= e(tr('privacy.used_percent', null, ['percent' => number_format($quotaBytes > 0 ? ($usage / $quotaBytes) * 100 : 0, 1)])) ?></p><progress max="<?= (int)$quotaBytes ?>" value="<?= (int)$usage ?>" style="width:100%"></progress><div class="actions"><a class="button" href="/?page=export_csv&type=audit"><?= e(tr('privacy.export_audit')) ?></a><a class="button" href="/?page=export_csv&type=applications"><?= e(tr('privacy.export_applications')) ?></a></div></section><section class="panel"><h2><?= e(tr('privacy.request_cleanup')) ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><label><?= e(tr('privacy.older_than')) ?><input type="date" name="cutoff_date" value="<?= e((new DateTimeImmutable('-6 months'))->format('Y-m-d')) ?>" required></label><button class="primary" name="action" value="request_cleanup"><?= e(tr('privacy.create_preview_request')) ?></button></form></section></div>
        <section class="panel table-wrap"><h2><?= e(tr('privacy.cleanup_requests')) ?></h2><div class="actions export-actions"><?= sfToolbar('cleanup_requests', $cleanupSf, $cleanupPreserve, $cleanupSfFields) ?></div><table><thead><tr><?= sfHeader('cleanup_requests','cutoff_date',tr('privacy.cutoff_date'),$cleanupSf,$cleanupPreserve) ?><?= sfHeader('cleanup_requests','status',tr('common.status'),$cleanupSf,$cleanupPreserve) ?><?= sfHeader('cleanup_requests','preview_text',tr('common.preview'),$cleanupSf,$cleanupPreserve) ?><?= sfHeader('cleanup_requests','created_at',tr('common.created'),$cleanupSf,$cleanupPreserve) ?></tr></thead><tbody><?php foreach($cleanupRequests as $request): ?><tr><td><?= e($request['cutoff_date']) ?></td><td><?= e($request['status']) ?></td><td><small><?= e($request['preview_text']) ?></small></td><td><?= e(displayDateTime($request['created_at'], $currentUser)) ?></td></tr><?php endforeach; ?><?php if(!$cleanupRequests): ?><tr><td colspan="4" class="empty"><?= e(tr('privacy.no_cleanup_requests')) ?></td></tr><?php endif; ?></tbody></table></section>
    <?php elseif ($page === 'admin_job_platforms'): ?>
        <?php
        if (!$currentUserIsAdmin) {
            http_response_code(403);
            exit('Forbidden');
        }
        seedJobPlatforms($db);
        $platformEditId = (int)($_GET['edit_platform'] ?? 0);
        $platformEdit = $platformEditId > 0 ? dbOne($db, 'SELECT * FROM job_platforms WHERE id=? AND deleted_at IS NULL', 'i', [$platformEditId]) : null;
        $platformRows = dbAll($db, 'SELECT * FROM job_platforms WHERE deleted_at IS NULL ORDER BY sort_order, name');
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('admin_users.section')) ?></p><h1><?= e(tr('job_platforms.title')) ?></h1></div><span><?= e(tr('job_platforms.portals_count', null, ['count' => (string) count($platformRows)])) ?></span></div>
        <div class="split"><section class="panel"><h2><?= e($platformEdit ? tr('job_platforms.edit') : tr('job_platforms.create')) ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="platform_id" value="<?= (int)($platformEdit['id'] ?? 0) ?>"><label><?= e(tr('common.name')) ?><input name="platform_name" value="<?= e($platformEdit['name'] ?? '') ?>" required></label><label><?= e(tr('job_platforms.base_url')) ?><input type="url" name="base_url" value="<?= e($platformEdit['base_url'] ?? '') ?>" placeholder="https://..."></label><label><?= e(tr('job_platforms.search_url_template')) ?><input name="search_url_template" value="<?= e($platformEdit['search_url_template'] ?? '') ?>" placeholder="https://...q={q}&location={location}" required><small><?= e(tr('job_platforms.placeholders_hint')) ?></small></label><div class="two"><label><?= e(tr('job_platforms.sort_order')) ?><input type="number" min="0" name="sort_order" value="<?= e((string)($platformEdit['sort_order'] ?? 0)) ?>"></label><label class="check"><input type="checkbox" name="is_active" value="1" <?= !isset($platformEdit['is_active']) || (int)$platformEdit['is_active'] === 1 ? 'checked' : '' ?>> <?= e(tr('common.active')) ?></label></div><label><?= e(tr('common.comment')) ?><textarea name="platform_notes" rows="3"><?= e($platformEdit['notes'] ?? '') ?></textarea></label><div class="actions"><button class="primary" name="action" value="save_job_platform"><?= e(tr('common.save')) ?></button><?php if($platformEdit): ?><a class="button" href="/?page=admin_job_platforms"><?= e(tr('common.new')) ?></a><?php endif; ?></div></form></section><section class="panel table-wrap"><table><thead><tr><th><?= e(tr('job_platforms.portal')) ?></th><th><?= e(tr('job_platforms.search_template')) ?></th><th><?= e(tr('common.status')) ?></th><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($platformRows as $platform): ?><tr class="<?= $platformEdit && (int)$platformEdit['id']===(int)$platform['id'] ? 'is-selected' : '' ?>"><td><strong><?= e($platform['name']) ?></strong><?php if($platform['base_url']): ?><small><a href="<?= e($platform['base_url']) ?>" target="_blank" rel="noopener"><?= e($platform['base_url']) ?></a></small><?php endif; ?><small><?= e($platform['notes']) ?></small></td><td><small><?= e($platform['search_url_template']) ?></small></td><td><span class="badge"><?= e((int)$platform['is_active'] === 1 ? tr('common.active') : tr('common.inactive')) ?></span><small><?= e(tr('job_platforms.sort_short')) ?> <?= (int)$platform['sort_order'] ?></small></td><td class="actions"><a href="/?page=admin_job_platforms&edit_platform=<?= (int)$platform['id'] ?>"><?= e(tr('common.edit')) ?></a><form method="post" onsubmit="return confirm('<?= e(tr('job_platforms.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="platform_id" value="<?= (int)$platform['id'] ?>"><button name="action" value="delete_job_platform"><?= e(tr('common.delete')) ?></button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
    <?php elseif ($page === 'admin_users'): ?>
        <?php
        if (!$currentUserIsAdmin) {
            http_response_code(403);
            exit('Forbidden');
        }
        $adminEmails = array_map('strtolower', (array) ($config['admin_emails'] ?? ['admin@jema.business']));
        $users = dbAll(
            $db,
            "SELECT u.id, u.email, u.status, u.first_name, u.last_name, u.created_at, u.last_login_at, u.last_seen_at, u.email_verified_at,
                    (SELECT COUNT(*)
                       FROM user_sessions us
                      WHERE us.user_id=u.id
                        AND us.logged_out_at IS NULL
                        AND us.last_seen_at >= NOW() - INTERVAL 10 MINUTE) active_session_count,
                    (SELECT MAX(us.last_seen_at)
                       FROM user_sessions us
                      WHERE us.user_id=u.id
                        AND us.logged_out_at IS NULL) session_last_seen_at,
                    (SELECT GROUP_CONCAT(r.code ORDER BY r.code)
                       FROM user_roles ur
                       JOIN roles r ON r.id=ur.role_id
                      WHERE ur.user_id=u.id) role_codes,
                    (SELECT COUNT(*) FROM jobs j WHERE j.owner_user_id=u.id AND j.deleted_at IS NULL) job_count,
                    (SELECT COUNT(*) FROM applications a WHERE a.user_id=u.id AND a.deleted_at IS NULL) application_count,
                    (SELECT COUNT(*) FROM user_documents d WHERE d.user_id=u.id AND d.deleted_at IS NULL) document_count,
                    (SELECT COUNT(*) FROM two_factor_methods tf WHERE tf.user_id=u.id AND tf.verified_at IS NOT NULL) two_factor_count,
                    (SELECT sag.granted_at FROM support_access_grants sag WHERE sag.user_id=u.id AND sag.revoked_at IS NULL ORDER BY sag.granted_at DESC LIMIT 1) support_granted_at
               FROM users u
              WHERE u.deleted_at IS NULL
           ORDER BY FIELD(u.status, 'active', 'invited', 'locked', 'disabled'), u.created_at DESC"
        );
        foreach ($users as &$userRow) {
            $roleCodesForRow = array_filter(explode(',', (string) ($userRow['role_codes'] ?? '')));
            $isConfigAdminForRow = in_array(strtolower((string) $userRow['email']), $adminEmails, true);
            $lastSeenTs = strtotime((string)($userRow['last_seen_at'] ?? '')) ?: 0;
            $sessionLastSeenTs = strtotime((string)($userRow['session_last_seen_at'] ?? '')) ?: 0;
            $lastActivityTs = max($lastSeenTs, $sessionLastSeenTs);
            $userRow['last_activity_at'] = $sessionLastSeenTs > 0 ? $userRow['session_last_seen_at'] : $userRow['last_seen_at'];
            $userRow['online_label'] = ((int)($userRow['active_session_count'] ?? 0) > 0 || $lastActivityTs >= time() - 600) ? tr('admin_users.online') : tr('admin_users.offline');
            $userRow['full_name'] = trim((string)$userRow['first_name'].' '.(string)$userRow['last_name']);
            $userRow['usage_label'] = tr('admin_users.usage_summary', null, ['jobs' => (string)(int)$userRow['job_count'], 'applications' => (string)(int)$userRow['application_count'], 'documents' => (string)(int)$userRow['document_count']]);
            $userRow['access_label'] = (($isConfigAdminForRow || in_array('admin', $roleCodesForRow, true)) ? tr('admin_users.role_admin') : tr('admin_users.role_user')) . ' · ' . $userRow['online_label'];
            if (!empty($userRow['support_granted_at'])) {
                $userRow['access_label'] .= ' · ' . tr('admin_users.support_granted_short');
            }
        }
        unset($userRow);
        $allUsers = $users;
        $adminUserSfFields = [
            'full_name'=>['label'=>tr('admin_users.user')],
            'status'=>['label'=>tr('common.status')],
            'usage_label'=>['label'=>tr('admin_users.usage')],
            'access_label'=>['label'=>tr('admin_users.access')],
        ];
        $adminUserSf = sfState('admin_users', $adminUserSfFields, ['sort'=>'status','dir'=>'asc']);
        $adminUserPreserve = ['page'=>'admin_users', 'manage_user'=>(int) ($_GET['manage_user'] ?? 0) ?: ''];
        $users = sfApplyRows($users, $adminUserSf, $adminUserSfFields);
        $managedUserId = (int) ($_GET['manage_user'] ?? 0);
        $managedUser = null;
        foreach ($allUsers as $candidate) {
            if ((int) $candidate['id'] === $managedUserId) {
                $managedUser = $candidate;
                break;
            }
        }
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('admin_users.section')) ?></p><h1><?= e(tr('admin_users.title')) ?></h1></div><span><?= e(tr('admin_users.accounts_count', null, ['count' => (string) count($users)])) ?></span></div>
        <section class="panel">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('admin_users.management')) ?></p><h2><?= e(tr('admin_users.edit_user')) ?></h2></div></div>
            <?php if(!$managedUser): ?>
                <p class="meta-line"><?= e(tr('admin_users.select_manage_hint')) ?></p>
            <?php else: $managedRoleCodes = array_filter(explode(',', (string) ($managedUser['role_codes'] ?? ''))); $managedIsConfigAdmin = in_array(strtolower((string) $managedUser['email']), $adminEmails, true); $managedIsAdmin = $managedIsConfigAdmin || in_array('admin', $managedRoleCodes, true); $managedIsSelf = (int) $managedUser['id'] === realUserId(); ?>
                <h3><?= e(trim((string)$managedUser['first_name'].' '.(string)$managedUser['last_name'])) ?></h3>
                <?php if($managedIsSelf): ?>
                    <p class="alert warning"><?= e(tr('admin_users.own_account_protected_hint')) ?></p>
                <?php else: ?>
                    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>">
                        <div class="three"><label><?= e(tr('auth.first_name')) ?><input name="first_name" value="<?= e($managedUser['first_name']) ?>" required></label><label><?= e(tr('auth.last_name')) ?><input name="last_name" value="<?= e($managedUser['last_name']) ?>" required></label><label><?= e(tr('auth.email')) ?><input type="email" name="email" value="<?= e($managedUser['email']) ?>" required></label></div>
                        <div class="two"><label><?= e(tr('common.status')) ?><select name="status"><?php foreach(['active'=>tr('admin_users.status.active'),'invited'=>tr('admin_users.status.invited'),'locked'=>tr('admin_users.status.locked'),'disabled'=>tr('admin_users.status.disabled')] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $managedUser['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label class="check"><input type="checkbox" name="is_admin" value="1" <?= $managedIsAdmin?'checked':'' ?> <?= $managedIsConfigAdmin?'disabled':'' ?>> <?= e(tr('admin_users.admin_rights')) ?></label></div>
                        <?php if($managedIsConfigAdmin): ?><input type="hidden" name="is_admin" value="1"><?php endif; ?>
                        <button class="primary" name="action" value="admin_update_user"><?= e(tr('admin_users.save_user_data')) ?></button>
                    </form>
                    <form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>">
                        <div class="two"><label><?= e(tr('admin_users.new_password')) ?><input type="password" name="new_password" minlength="10" required></label><label><?= e(tr('admin_users.repeat_password')) ?><input type="password" name="new_password_confirm" minlength="10" required></label></div>
                        <button name="action" value="admin_reset_user_password"><?= e(tr('admin_users.set_password')) ?></button>
                    </form>
                    <?php if((int)($managedUser['two_factor_count'] ?? 0) > 0): ?>
                        <form method="post" onsubmit="return confirm('<?= e(tr('admin_users.reset_2fa_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button name="action" value="admin_reset_user_2fa"><?= e(tr('admin_users.reset_2fa')) ?></button></form>
                    <?php endif; ?>
                    <?php if(!$managedIsConfigAdmin): ?>
                        <form method="post" onsubmit="return confirm('<?= e(tr('admin_users.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button name="action" value="admin_delete_user"><?= e(tr('admin_users.delete_user')) ?></button></form>
                    <?php endif; ?>
                    <?php if(!empty($managedUser['support_granted_at'])): ?>
                        <form method="post" onsubmit="return confirm('<?= e(tr('admin_users.start_support_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$managedUser['id'] ?>"><button class="primary" name="action" value="admin_start_support"><?= e(tr('admin_users.start_support')) ?></button></form>
                    <?php else: ?>
                        <p class="meta-line"><?= e(tr('admin_users.support_not_granted')) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="panel table-wrap"><div class="actions export-actions"><?= sfToolbar('admin_users', $adminUserSf, $adminUserPreserve, $adminUserSfFields) ?></div><table><thead><tr><?= sfHeader('admin_users','full_name',tr('admin_users.user'),$adminUserSf,$adminUserPreserve) ?><?= sfHeader('admin_users','status',tr('common.status'),$adminUserSf,$adminUserPreserve) ?><?= sfHeader('admin_users','usage_label',tr('admin_users.usage'),$adminUserSf,$adminUserPreserve) ?><?= sfHeader('admin_users','access_label',tr('admin_users.access'),$adminUserSf,$adminUserPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody>
            <?php foreach($users as $user): $roleCodes = array_filter(explode(',', (string) ($user['role_codes'] ?? ''))); $isConfigAdmin = in_array(strtolower((string) $user['email']), $adminEmails, true); $isUserAdmin = $isConfigAdmin || in_array('admin', $roleCodes, true); $isSelf = (int) $user['id'] === realUserId(); ?>
                <tr class="<?= $isSelf ? 'is-selected' : '' ?>">
                    <td><strong><?= e(trim((string)$user['first_name'].' '.(string)$user['last_name'])) ?></strong><span class="badge <?= ($user['online_label'] ?? '') === tr('admin_users.online') ? 'role-badge' : '' ?>"><?= e($user['online_label'] ?? tr('admin_users.offline')) ?></span><small><?= e($user['email']) ?></small><small><?= e(tr('admin_users.registered_at', null, ['date' => displayDateTime($user['created_at'], $currentUser)])) ?></small><small><a href="/?page=admin_users&manage_user=<?= (int)$user['id'] ?>"><?= e(tr('admin_users.manage')) ?></a></small></td>
                    <td><span class="badge"><?= e($user['status']) ?></span><?php if($user['email_verified_at']): ?><small><?= e(tr('admin_users.verified_at', null, ['date' => displayDateTime($user['email_verified_at'], $currentUser)])) ?></small><?php else: ?><small><?= e(tr('admin_users.not_verified')) ?></small><?php endif; ?><?php if((int)$user['two_factor_count'] > 0): ?><small><?= e(tr('admin_users.2fa_active')) ?></small><?php else: ?><small><?= e(tr('admin_users.2fa_inactive')) ?></small><?php endif; ?><?php if($user['last_login_at']): ?><small><?= e(tr('admin_users.last_login', null, ['date' => displayDateTime($user['last_login_at'], $currentUser)])) ?></small><?php endif; ?><?php if($user['last_activity_at']): ?><small><?= e(tr('admin_users.last_activity', null, ['date' => displayDateTime($user['last_activity_at'], $currentUser)])) ?></small><?php endif; ?></td>
                    <td><small><?= e(tr('admin_users.jobs_count', null, ['count' => (string)(int)$user['job_count']])) ?></small><small><?= e(tr('admin_users.applications_count', null, ['count' => (string)(int)$user['application_count']])) ?></small><small><?= e(tr('admin_users.documents_count', null, ['count' => (string)(int)$user['document_count']])) ?></small></td>
                    <td><small><?= e($isUserAdmin ? tr('admin_users.role_admin') : tr('admin_users.role_user')) ?></small><?php if($isConfigAdmin): ?><small><?= e(tr('admin_users.config_admin')) ?></small><?php endif; ?><?php if(!empty($user['support_granted_at'])): ?><small><?= e(tr('admin_users.support_granted_since', null, ['date' => displayDateTime($user['support_granted_at'], $currentUser)])) ?></small><?php endif; ?></td>
                    <td>
                        <?php if($isSelf): ?>
                            <span class="meta-line"><?= e(tr('admin_users.own_account_protected')) ?></span>
                        <?php else: ?>
                            <form method="post" class="actions"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input name="first_name" value="<?= e($user['first_name']) ?>" placeholder="<?= e(tr('auth.first_name')) ?>" required>
                                <input name="last_name" value="<?= e($user['last_name']) ?>" placeholder="<?= e(tr('auth.last_name')) ?>" required>
                                <input type="email" name="email" value="<?= e($user['email']) ?>" placeholder="E-Mail" required>
                                <select name="status"><?php foreach(['active'=>tr('admin_users.status.active'),'invited'=>tr('admin_users.status.invited'),'locked'=>tr('admin_users.status.locked'),'disabled'=>tr('admin_users.status.disabled')] as $value=>$label): ?><option value="<?= e($value) ?>" <?= $user['status']===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select>
                                <label class="check"><input type="checkbox" name="is_admin" value="1" <?= $isUserAdmin?'checked':'' ?> <?= $isConfigAdmin?'disabled':'' ?>> Admin</label>
                                <?php if($isConfigAdmin): ?><input type="hidden" name="is_admin" value="1"><?php endif; ?>
                                <button class="primary" name="action" value="admin_update_user"><?= e(tr('common.save')) ?></button>
                            </form>
                            <form method="post" class="actions"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <input type="password" name="new_password" minlength="10" placeholder="<?= e(tr('admin_users.new_password')) ?>" required>
                                <input type="password" name="new_password_confirm" minlength="10" placeholder="<?= e(tr('admin_users.repeat_password')) ?>" required>
                                <button name="action" value="admin_reset_user_password"><?= e(tr('admin_users.set_password')) ?></button>
                            </form>
                            <?php if((int)$user['two_factor_count'] > 0): ?>
                                <form method="post" class="actions" onsubmit="return confirm('<?= e(tr('admin_users.reset_2fa_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button name="action" value="admin_reset_user_2fa"><?= e(tr('admin_users.reset_2fa')) ?></button></form>
                            <?php endif; ?>
                            <?php if(!$isConfigAdmin): ?>
                                <form method="post" class="actions" onsubmit="return confirm('<?= e(tr('admin_users.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button name="action" value="admin_delete_user"><?= e(tr('common.delete')) ?></button></form>
                            <?php endif; ?>
                            <?php if(!empty($user['support_granted_at'])): ?>
                                <form method="post" class="actions" onsubmit="return confirm('<?= e(tr('admin_users.start_support_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><button class="primary" name="action" value="admin_start_support"><?= e(tr('admin_users.link_into_account')) ?></button></form>
                            <?php else: ?>
                                <span class="meta-line"><?= e(tr('admin_users.no_support')) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></section>
    <?php elseif ($page === 'profile'): ?>
        <?php
        $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [userId()]) ?: [];
        $selectedEmploymentTypes = array_filter(explode(',', (string)($preference['employment_types'] ?? '')));
        $languageSkills = [];
        foreach (dbAll($db, 'SELECT language_code, cefr_level FROM user_language_skills WHERE user_id=? ORDER BY language_name', 'i', [userId()]) as $skill) {
            $languageSkills[$skill['language_code']] = $skill['cefr_level'];
        }
        $documentTypes = dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id');
        $profileDocumentTypes = documentTypesForScope($documentTypes, 'profile');
        $profileDocuments = dbAll($db, "SELECT d.*, dt.code type_code, dt.name_key type_name FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL ORDER BY d.is_current DESC, d.title, d.version DESC", 'i', [userId()]);
        $profileCurrency = currencyForCountry($currentUser['country_code'] ?? 'CH');
        $userLanguage = normalizeLocale((string) ($currentUser['preferred_language'] ?? 'de-CH'));
        $languageChoices = europeanLanguageChoices();
        $totpMethod = activeTotpMethod($db, userId());
        if (!$totpMethod && empty($_SESSION['totp_setup_secret'])) {
            $_SESSION['totp_setup_secret'] = generateTotpSecret();
        }
        $totpSetupSecret = (string) ($_SESSION['totp_setup_secret'] ?? '');
        $totpSetupUri = $totpSetupSecret ? totpUri($config, $currentUser, $totpSetupSecret) : '';
        $smtpSettings = dbOne($db, 'SELECT smtp_host, smtp_port, smtp_encryption, smtp_username, from_email, from_name, is_active, updated_at FROM user_smtp_settings WHERE user_id=? LIMIT 1', 'i', [userId()]) ?: [];
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('nav.account')) ?></p><h1><?= e(tr('profile.title')) ?></h1></div><span><?= e($currentUser['email']) ?></span></div>
        <section class="panel" id="security">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('profile.security_section')) ?></p><h2><?= e(tr('profile.totp_title')) ?></h2></div><span><?= e($totpMethod ? tr('profile.totp_status_active') : tr('profile.totp_status_inactive')) ?></span></div>
            <?php if($totpMethod): ?>
                <p class="meta-line"><?= e(tr('profile.totp_active_hint')) ?></p>
                <form method="post" onsubmit="return confirm('<?= e(tr('profile.totp_disable_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="disable_totp"><?= e(tr('profile.totp_disable')) ?></button></form>
            <?php else: ?>
                <p class="meta-line"><?= e(tr('profile.totp_setup_hint')) ?></p>
                <div class="totp-setup">
                    <div class="totp-qr" data-qr-text="<?= e($totpSetupUri) ?>" data-qr-error="<?= e(tr('profile.qr_error')) ?>" aria-label="<?= e(tr('profile.qr_label')) ?>"></div>
                    <div class="stack">
                        <label><?= e(tr('profile.totp_setup_secret')) ?><input value="<?= e($totpSetupSecret) ?>" readonly onclick="this.select()"></label>
                        <details class="totp-manual">
                            <summary><?= e(tr('profile.totp_manual_show')) ?></summary>
                            <label><?= e(tr('profile.totp_uri')) ?><input value="<?= e($totpSetupUri) ?>" readonly onclick="this.select()"></label>
                        </details>
                    </div>
                </div>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <label><?= e(tr('profile.totp_code')) ?><input name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></label>
                    <button class="primary" name="action" value="enable_totp"><?= e(tr('profile.totp_enable')) ?></button>
                </form>
            <?php endif; ?>
        </section>
        <section class="panel" id="support-access">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('support.admin')) ?></p><h2><?= e(tr('support.admin')) ?></h2></div><span><?= e($supportGrant ? tr('profile.support_status_granted') : tr('profile.support_status_not_granted')) ?></span></div>
            <?php if($supportImpersonating): ?>
                <p class="alert warning"><?= e(tr('profile.support_blocked_during_session')) ?></p>
            <?php elseif($supportGrant): ?>
                <p class="meta-line"><?= e(tr('profile.support_granted_since', null, ['date' => displayDateTime((string)$supportGrant['granted_at'], $currentUser)])) ?></p>
                <form method="post" onsubmit="return confirm('<?= e(tr('profile.support_revoke_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button name="action" value="revoke_admin_support"><?= e(tr('profile.support_revoke')) ?></button></form>
            <?php else: ?>
                <p class="meta-line"><?= e(tr('profile.support_intro')) ?></p>
                <form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button class="primary" name="action" value="grant_admin_support"><?= e(tr('profile.support_allow')) ?></button></form>
            <?php endif; ?>
        </section>
        <section class="panel" id="smtp">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('profile.email_section')) ?></p><h2><?= e(tr('profile.smtp_title')) ?></h2></div><span><?= e(!empty($smtpSettings['is_active']) ? tr('profile.smtp_active') : tr('profile.smtp_inactive')) ?></span></div>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <div class="three">
                    <label><?= e(tr('profile.smtp_host')) ?><input name="smtp_host" value="<?= e($smtpSettings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com" required></label>
                    <label><?= e(tr('profile.smtp_port')) ?><input type="number" min="1" max="65535" name="smtp_port" value="<?= e((string)($smtpSettings['smtp_port'] ?? 587)) ?>" required></label>
                    <label><?= e(tr('profile.smtp_encryption')) ?><select name="smtp_encryption"><?php foreach(['tls'=>'STARTTLS','ssl'=>'SSL/TLS','none'=>tr('profile.smtp_encryption_none')] as $value=>$label): ?><option value="<?= e($value) ?>" <?= ($smtpSettings['smtp_encryption'] ?? 'tls')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                </div>
                <div class="two">
                    <label><?= e(tr('profile.smtp_username')) ?><input name="smtp_username" value="<?= e($smtpSettings['smtp_username'] ?? '') ?>" autocomplete="username"></label>
                    <label><?= e(tr('profile.smtp_password')) ?><input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= empty($smtpSettings) ? '' : e(tr('profile.smtp_password_keep')) ?>"></label>
                </div>
                <div class="two">
                    <label><?= e(tr('profile.smtp_from_email')) ?><input type="email" name="from_email" value="<?= e($smtpSettings['from_email'] ?? $currentUser['email']) ?>" required></label>
                    <label><?= e(tr('profile.smtp_from_name')) ?><input name="from_name" value="<?= e($smtpSettings['from_name'] ?? trim((string)$currentUser['first_name'] . ' ' . (string)$currentUser['last_name'])) ?>"></label>
                </div>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= !empty($smtpSettings['is_active']) ? 'checked' : '' ?>> <?= e(tr('profile.smtp_enable')) ?></label>
                <div class="actions"><button class="primary" name="action" value="save_smtp_settings"><?= e(tr('profile.smtp_save')) ?></button><button name="action" value="test_smtp_settings"><?= e(tr('profile.smtp_save_test')) ?></button></div>
                <p class="meta-line"><?= e(tr('profile.smtp_hint')) ?></p>
            </form>
        </section>
        <section class="panel"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>">
            <div class="two"><label><?= e(tr('auth.first_name')) ?><input name="first_name" value="<?= e($currentUser['first_name']) ?>" required></label><label><?= e(tr('auth.last_name')) ?><input name="last_name" value="<?= e($currentUser['last_name']) ?>" required></label></div>
            <label><?= e(tr('auth.email')) ?><input value="<?= e($currentUser['email']) ?>" disabled><small><?= e(tr('profile.login_email_hint')) ?></small></label>
            <label><?= e(tr('profile.app_language')) ?><select name="preferred_language"><?php foreach(documentLanguageChoices() as $code=>$label): ?><option value="<?= e($code) ?>" <?= $userLanguage===$code?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small><?= e(tr('profile.app_language_hint')) ?></small></label>
            <label><?= e(tr('profile.timezone')) ?><select name="timezone"><?php foreach(timezoneChoices() as $continent=>$zones): ?><optgroup label="<?= e($continent) ?>"><?php foreach($zones as $zone=>$label): ?><option value="<?= e($zone) ?>" <?= $currentUser['timezone']===$zone?'selected':'' ?>><?= e($label) ?> (<?= e($zone) ?>)</option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label>
            <div class="two"><label><?= e(tr('profile.phone')) ?><input name="phone" value="<?= e($currentUser['phone']) ?>"></label><label><?= e(tr('profile.mobile')) ?><input name="mobile" value="<?= e($currentUser['mobile']) ?>"></label></div>
            <div class="two"><label><?= e(tr('profile.linkedin')) ?><input type="url" name="linkedin_url" value="<?= e($currentUser['linkedin_url'] ?? '') ?>" placeholder="https://www.linkedin.com/in/..."></label><label><?= e(tr('profile.facebook')) ?><input type="url" name="facebook_url" value="<?= e($currentUser['facebook_url'] ?? '') ?>" placeholder="https://www.facebook.com/..."></label></div>
            <div class="two"><label>X<input type="url" name="x_url" value="<?= e($currentUser['x_url'] ?? '') ?>" placeholder="https://x.com/..."></label><label><?= e(tr('profile.other_profile')) ?><input type="url" name="other_profile_url" value="<?= e($currentUser['other_profile_url'] ?? '') ?>" placeholder="https://..."></label></div>
            <div class="three"><label><?= e(tr('profile.city')) ?><input name="city" value="<?= e($currentUser['city']) ?>"></label><label><?= e(tr('profile.region')) ?><select name="region_key" id="profile-region"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(regionChoices() as $countryCode=>$regions): ?><optgroup label="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>"><?php foreach($regions as $region): $selectedRegion = $currentUser['region']===$region && $currentUser['country_code']===$countryCode; ?><option value="<?= e($countryCode . '|' . $region) ?>" data-country="<?= e($countryCode) ?>" data-country-name="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>" data-currency="<?= e(currencyForCountry($countryCode)) ?>" <?= $selectedRegion?'selected':'' ?>><?= e($region) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label><?= e(tr('profile.country')) ?><output id="profile-country-display" class="readonly-value"><?= e(countryChoices()[$currentUser['country_code']] ?? '') ?></output></label></div>
            <?php $cefrLevelLabels = ['A1'=>tr('profile.cefr.a1'), 'A2'=>tr('profile.cefr.a2'), 'B1'=>tr('profile.cefr.b1'), 'B2'=>tr('profile.cefr.b2'), 'C1'=>tr('profile.cefr.c1'), 'C2'=>tr('profile.cefr.c2')]; ?>
            <div class="history"><h3><?= e(tr('profile.language_skills_title')) ?></h3><p class="meta-line"><?= e(tr('profile.language_skills_hint')) ?></p></div>
            <div class="language-records">
                <?php $languageRecordIndex = 0; foreach($languageSkills as $code=>$level): ?>
                    <div class="language-record">
                        <label><?= e(tr('profile.language_label')) ?><select name="language_codes[]"><option value=""><?= e(tr('profile.language_choose')) ?></option><?php foreach($languageChoices as $choiceCode=>$label): ?><option value="<?= e($choiceCode) ?>" <?= $code===$choiceCode?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                        <label><?= e(tr('profile.language_level')) ?><select name="language_levels[]"><?php foreach($cefrLevelLabels as $levelValue=>$levelLabel): ?><option value="<?= e($levelValue) ?>" <?= $level===$levelValue?'selected':'' ?>><?= e($levelLabel) ?></option><?php endforeach; ?></select></label>
                        <label class="check"><input type="checkbox" name="remove_language_indexes[]" value="<?= $languageRecordIndex ?>"> <?= e(tr('profile.language_delete')) ?></label>
                    </div>
                <?php $languageRecordIndex++; ?>
                <?php endforeach; ?>
                <?php if(!$languageSkills): ?><p class="empty compact-empty"><?= e(tr('profile.language_empty')) ?></p><?php endif; ?>
                <div class="language-add">
                    <label><?= e(tr('profile.language_add')) ?><select name="language_codes[]"><option value=""><?= e(tr('profile.language_choose')) ?></option><?php foreach($languageChoices as $code=>$label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                    <label><?= e(tr('profile.language_level')) ?><select name="language_levels[]"><option value=""><?= e(tr('profile.language_level_choose')) ?></option><?php foreach($cefrLevelLabels as $level=>$levelLabel): ?><option value="<?= e($level) ?>"><?= e($levelLabel) ?></option><?php endforeach; ?></select></label>
                    <button class="primary" name="action" value="save_profile"><?= e(tr('profile.language_add_save')) ?></button>
                </div>
            </div>
            <?php
            $remotePreferenceLabels = ['any'=>tr('profile.remote.any'), 'onsite'=>tr('profile.remote.onsite'), 'hybrid'=>tr('profile.remote.hybrid'), 'remote'=>tr('profile.remote.remote')];
            $employmentTypeLabels = ['full_time'=>tr('profile.employment.full_time'), 'part_time'=>tr('profile.employment.part_time'), 'temporary'=>tr('profile.employment.temporary'), 'contract'=>tr('profile.employment.contract'), 'internship'=>tr('profile.employment.internship'), 'freelance'=>tr('profile.employment.freelance')];
            $salaryPeriodLabels = ['hour'=>tr('profile.salary.hour'), 'month'=>tr('profile.salary.month'), 'year'=>tr('profile.salary.year')];
            ?>
            <div class="history"><h3><?= e(tr('profile.job_preferences_title')) ?></h3><p class="meta-line"><?= e(tr('profile.job_preferences_hint')) ?></p></div>
            <label><?= e(tr('profile.desired_roles')) ?><textarea name="desired_roles" rows="3" placeholder="<?= e(tr('profile.desired_roles_placeholder')) ?>"><?= e($preference['desired_roles'] ?? '') ?></textarea></label>
            <label><?= e(tr('profile.desired_locations')) ?><textarea name="desired_locations" rows="2" placeholder="<?= e(tr('profile.desired_locations_placeholder')) ?>"><?= e($preference['desired_locations'] ?? '') ?></textarea></label>
            <div class="two"><label><?= e(tr('profile.remote_preference')) ?><select name="remote_preference"><?php foreach($remotePreferenceLabels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($preference['remote_preference'] ?? 'any')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><label><?= e(tr('profile.desired_level')) ?><input name="desired_level" value="<?= e($preference['desired_level'] ?? '') ?>" placeholder="<?= e(tr('profile.desired_level_placeholder')) ?>"></label></div>
            <fieldset class="check"><legend><?= e(tr('profile.employment_types')) ?></legend><?php foreach($employmentTypeLabels as $v=>$l): ?><label><input type="checkbox" name="employment_types[]" value="<?= e($v) ?>" <?= in_array($v, $selectedEmploymentTypes, true)?'checked':'' ?>> <?= e($l) ?></label><?php endforeach; ?></fieldset>
            <div class="two"><label><?= e(tr('profile.workload_min')) ?><input type="number" min="0" max="100" name="workload_min" value="<?= e((string)($preference['workload_min'] ?? '')) ?>"></label><label><?= e(tr('profile.workload_max')) ?><input type="number" min="0" max="100" name="workload_max" value="<?= e((string)($preference['workload_max'] ?? '')) ?>"></label></div>
            <div class="salary-row"><label><?= e(tr('profile.salary')) ?> <span class="salary-currency-display"><?= e($profileCurrency) ?></span><input type="number" min="0" step="0.01" name="salary_min" value="<?= e((string)($preference['salary_min'] ?? '')) ?>"></label><label><?= e(tr('profile.salary_format')) ?><select name="salary_period"><?php foreach($salaryPeriodLabels as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($preference['salary_period'] ?? 'year')===$v?'selected':'' ?>><?= e($l) ?> · <?= e($profileCurrency) ?></option><?php endforeach; ?></select></label></div>
            <label><?= e(tr('profile.available_from')) ?><input type="date" name="available_from" value="<?= e($preference['available_from'] ?? '') ?>"></label>
            <label><?= e(tr('profile.desired_benefits')) ?><textarea name="desired_benefits" rows="2" placeholder="<?= e(tr('profile.desired_benefits_placeholder')) ?>"><?= e($preference['desired_benefits'] ?? '') ?></textarea></label>
            <label><?= e(tr('profile.exclusions')) ?><textarea name="excluded_industries" rows="2" placeholder="<?= e(tr('profile.exclusions_placeholder')) ?>"><?= e($preference['excluded_industries'] ?? '') ?></textarea></label>
            <div class="two"><label class="check"><input type="checkbox" name="willing_to_relocate" value="1" <?= !empty($preference['willing_to_relocate'])?'checked':'' ?>> <?= e(tr('profile.willing_to_relocate')) ?></label><label><?= e(tr('profile.travel_percentage')) ?><input type="number" min="0" max="100" name="travel_percentage" value="<?= e((string)($preference['travel_percentage'] ?? '')) ?>"></label></div>
            <label><?= e(tr('profile.preference_notes')) ?><textarea name="preference_notes" rows="3"><?= e($preference['notes'] ?? '') ?></textarea></label>
            <button class="primary" name="action" value="save_profile"><?= e(tr('profile.save_profile')) ?></button>
        </form></section>
        <section class="panel" id="documents"><div class="section-head"><div><p class="eyebrow"><?= e(tr('profile.master_data')) ?></p><h2><?= e(tr('profile.document_management')) ?></h2></div><span><?= count($profileDocuments) ?> <?= e(tr('common.versions')) ?></span></div><div class="split inner-split"><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_scope" value="profile"><label><?= e(tr('documents.new_version_of')) ?><select name="replace_document_id"><option value="0"><?= e(tr('documents.new_profile_document')) ?></option><?php foreach($profileDocuments as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><label><?= e(tr('documents.document_type')) ?><select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label><?= e(tr('common.title')) ?><input name="document_title" required placeholder="<?= e(tr('documents.title_placeholder_profile')) ?>"></label><label><?= e(tr('profile.language_label')) ?><select name="document_language"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>" <?= $v===$userLanguage?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><div class="two"><label><?= e(tr('documents.valid_from')) ?><input type="date" name="valid_from"></label><label><?= e(tr('documents.valid_until')) ?><input type="date" name="valid_until"></label></div><label><?= e(tr('common.description')) ?><textarea name="document_description" rows="3"></textarea></label><?= filePickerHtml('user_document') ?><button class="primary" name="action" value="upload_document"><?= e(tr('documents.save_profile_document')) ?></button></form><div class="table-wrap"><table><thead><tr><th><?= e(tr('documents.document')) ?></th><th><?= e(tr('documents.type')) ?></th><th><?= e(tr('documents.version')) ?></th><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($profileDocuments as $doc): ?><tr class="<?= (int)$doc['is_current'] ? 'is-selected' : '' ?>"><td><strong><a class="record-link" href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · ' . e(tr('common.current')) : '' ?></td><td class="actions"><a href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor"><?= e(tr('common.edit')) ?></a><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e(tr('common.download')) ?></a><form method="post" onsubmit="return confirm('<?= e(tr('documents.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document"><?= e(tr('common.delete')) ?></button></form></td></tr><?php endforeach; ?><?php if(!$profileDocuments): ?><tr><td colspan="4" class="empty"><?= e(tr('documents.empty_profile')) ?></td></tr><?php endif; ?></tbody></table></div></div></section>
        <script>
        (() => {
            const region = document.getElementById('profile-region');
            const country = document.getElementById('profile-country-display');
            const currencies = document.querySelectorAll('.salary-currency-display');
            if (!region || !country) return;
            const syncCountry = () => {
                const selected = region.options[region.selectedIndex];
                country.textContent = selected ? (selected.dataset.countryName || '') : '';
                currencies.forEach((item) => { item.textContent = selected ? (selected.dataset.currency || 'CHF') : 'CHF'; });
            };
            region.addEventListener('change', syncCountry);
            syncCountry();
        })();
        </script>
    <?php elseif ($page === 'companies'): ?>
        <?php
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT * FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $companySfFields = ['name'=>['label'=>tr('companies.company'),'expr'=>'c.name'], 'address'=>['label'=>tr('companies.address_phone'),'expr'=>'CONCAT_WS(" ", c.address_line1, c.address_line2, c.city, c.phone)'], 'role'=>['label'=>tr('companies.role_intermediary'),'expr'=>'IF(c.is_intermediary=1, "Vermittler", "Direkt")', 'choices'=>['Direkt'=>tr('companies.direct_company'),'Vermittler'=>tr('companies.intermediary')]], 'links'=>['label'=>tr('companies.links'),'expr'=>'CAST(c.updated_at AS CHAR)']];
        $companySf = sfState('companies', $companySfFields, ['sort'=>'name','dir'=>'asc']);
        $companyPreserve = ['page'=>'companies', 'edit'=>$_GET['edit'] ?? ''];
        $companySql='SELECT c.*, (SELECT COUNT(*) FROM jobs j WHERE j.company_id=c.id AND j.owner_user_id=c.owner_user_id AND j.deleted_at IS NULL) job_count, (SELECT COUNT(*) FROM contacts ct WHERE ct.company_id=c.id AND ct.owner_user_id=c.owner_user_id AND ct.deleted_at IS NULL) contact_count, (SELECT COUNT(*) FROM applications a JOIN jobs j2 ON j2.id=a.job_id WHERE a.user_id=c.owner_user_id AND a.deleted_at IS NULL AND (j2.company_id=c.id OR a.intermediary_company_id=c.id)) application_count, (SELECT GROUP_CONCAT(DISTINCT CONCAT(client.id, "::", client.name) ORDER BY client.name SEPARATOR "||") FROM company_relationships cr JOIN companies client ON client.id=cr.client_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.intermediary_company_id=c.id AND cr.deleted_at IS NULL AND client.deleted_at IS NULL) mediated_clients, (SELECT GROUP_CONCAT(DISTINCT CONCAT(intermediary.id, "::", intermediary.name) ORDER BY intermediary.name SEPARATOR "||") FROM company_relationships cr JOIN companies intermediary ON intermediary.id=cr.intermediary_company_id WHERE cr.owner_user_id=c.owner_user_id AND cr.client_company_id=c.id AND cr.deleted_at IS NULL AND intermediary.deleted_at IS NULL) mediated_by FROM companies c WHERE c.owner_user_id=? AND c.deleted_at IS NULL'; $companyTypes='i'; $companyVals=[userId()];
        $companySql .= sfApplySql($companySf, $companySfFields, $companyTypes, $companyVals);
        $companySql .= sfOrderSql($companySf, $companySfFields, 'name');
        $companyRows = dbAll($db, $companySql, $companyTypes, $companyVals);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('nav.crm')) ?></p><h1><?= e(tr('companies.title')) ?></h1></div><span><?= count($companyRows) ?> <?= e(tr('common.entries')) ?></span></div>
        <div class="actions export-actions"><?= sfToolbar('companies', $companySf, ['page'=>'companies'], $companySfFields) ?><a class="button" href="/?page=export_pdf&type=companies">PDF</a></div>
        <div class="split"><section class="panel"><h2><?= e($edit ? tr('companies.edit') : tr('companies.new')) ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label><?= e(tr('common.name')) ?><input name="name" value="<?= e($edit['name'] ?? '') ?>" required></label><label class="check"><input type="checkbox" name="is_intermediary" value="1" <?= !empty($edit['is_intermediary'])?'checked':'' ?>> <?= e(tr('companies.possible_intermediary')) ?></label><label><?= e(tr('companies.main_phone')) ?><input name="company_phone" value="<?= e($edit['phone'] ?? '') ?>"></label><label><?= e(tr('companies.address')) ?><textarea name="address" rows="3" placeholder="<?= e(tr('companies.address_placeholder')) ?>"><?= e(trim((string)($edit['address_line1'] ?? '') . "\n" . (string)($edit['address_line2'] ?? ''))) ?></textarea></label><div class="two"><label><?= e(tr('companies.postal_code')) ?><input name="postal_code" value="<?= e($edit['postal_code'] ?? '') ?>"></label><label><?= e(tr('companies.city')) ?><input name="city" value="<?= e($edit['city'] ?? '') ?>"></label></div><div class="two"><label><?= e(tr('companies.region')) ?><select name="company_region_key" id="company-region"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(regionChoices() as $countryCode=>$regions): ?><optgroup label="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>"><?php foreach($regions as $region): $selectedRegion = ($edit['region'] ?? '')===$region && ($edit['country_code'] ?? '')===$countryCode; ?><option value="<?= e($countryCode . '|' . $region) ?>" data-country="<?= e($countryCode) ?>" data-country-name="<?= e(countryChoices()[$countryCode] ?? $countryCode) ?>" <?= $selectedRegion?'selected':'' ?>><?= e($region) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></label><label><?= e(tr('companies.country')) ?><output id="company-country-display" class="readonly-value"><?= e(countryChoices()[$edit['country_code'] ?? ''] ?? tr('companies.country_from_region')) ?></output></label></div><label><?= e(tr('companies.website')) ?><input type="url" name="website" value="<?= e($edit['website'] ?? '') ?>"></label><label><?= e(tr('common.comment')) ?><textarea name="company_notes" rows="4"><?= e($edit['notes'] ?? '') ?></textarea></label><button class="primary" name="action" value="save_company"><?= e(tr('common.save')) ?></button></form></section>
        <section class="panel table-wrap"><table><thead><tr><?= sfHeader('companies','name',tr('companies.company'),$companySf,$companyPreserve) ?><?= sfHeader('companies','address',tr('companies.address_phone'),$companySf,$companyPreserve) ?><?= sfHeader('companies','role',tr('companies.role_intermediary'),$companySf,$companyPreserve) ?><?= sfHeader('companies','links',tr('companies.links'),$companySf,$companyPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($companyRows as $company): ?><tr class="<?= $edit && (int)$edit['id']===(int)$company['id']?'is-selected':'' ?>"><td><strong><a class="record-link" href="/?page=companies&edit=<?= (int)$company['id'] ?>"><?= e($company['name']) ?></a></strong><small><?= e($company['website']) ?></small></td><td><?php if($company['address_line1']): ?><small><?= nl2br(e(trim((string)$company['address_line1'] . "\n" . (string)$company['address_line2']))) ?></small><?php endif; ?><?php if($company['city']): ?><small><?= e($company['city']) ?></small><?php endif; ?><?php if($company['phone']): ?><small><?= e($company['phone']) ?></small><?php endif; ?></td><td class="relationship-cell"><?php if(!empty($company['is_intermediary']) || $company['mediated_clients']): ?><span class="badge role-badge"><?= e(tr('companies.intermediary')) ?></span><?php endif; ?><?php if($company['mediated_clients']): ?><small><?= e(tr('companies.mediates')) ?>: <?php foreach(explode('||', $company['mediated_clients']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if($company['mediated_by']): ?><span class="badge"><?= e(tr('companies.mediated')) ?></span><small><?= e(tr('companies.by')) ?>: <?php foreach(explode('||', $company['mediated_by']) as $entry): [$id,$name]=array_pad(explode('::',$entry,2),2,''); ?><a href="/?page=companies&edit=<?= (int)$id ?>"><?= e($name) ?></a><?php endforeach; ?></small><?php endif; ?><?php if(empty($company['is_intermediary']) && !$company['mediated_clients'] && !$company['mediated_by']): ?><small><?= e(tr('companies.direct_none')) ?></small><?php endif; ?></td><td class="link-list"><a href="/?page=jobs&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['job_count'] ?> <?= e(tr('nav.jobs')) ?></a><a href="/?page=applications&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['application_count'] ?> <?= e(tr('nav.applications')) ?></a><a href="/?page=contacts&company_id=<?= (int)$company['id'] ?>"><?= (int)$company['contact_count'] ?> <?= e(tr('nav.contacts')) ?></a></td><td class="actions"><form method="post" onsubmit="return confirm('<?= e(tr('companies.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$company['id'] ?>"><button name="action" value="delete_company"><?= e(tr('common.delete')) ?></button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
        <script>
        (() => {
            const region = document.getElementById('company-region');
            const country = document.getElementById('company-country-display');
            if (!region || !country) return;
            const syncCountry = () => {
                const selected = region.options[region.selectedIndex];
                country.textContent = selected ? (selected.dataset.countryName || <?= json_encode(tr('companies.country_from_region'), JSON_UNESCAPED_UNICODE) ?>) : <?= json_encode(tr('companies.country_from_region'), JSON_UNESCAPED_UNICODE) ?>;
            };
            region.addEventListener('change', syncCountry);
            syncCountry();
        })();
        </script>
    <?php elseif ($page === 'job_platform_search'): ?>
        <?php
        seedJobPlatforms($db);
        $preference = dbOne($db, 'SELECT * FROM user_preferences WHERE user_id=? AND is_active=1 ORDER BY id LIMIT 1', 'i', [userId()]) ?: [];
        $query = jobPreferenceQuery($preference);
        $location = jobPreferenceLocation($preference, $currentUser);
        $platformRows = dbAll($db, 'SELECT * FROM job_platforms WHERE is_active=1 AND deleted_at IS NULL ORDER BY sort_order, name');
        $searchResults = is_array($_SESSION['platform_search_results'] ?? null) ? $_SESSION['platform_search_results'] : [];
        $employmentLabels = ['full_time'=>tr('profile.employment.full_time'),'part_time'=>tr('profile.employment.part_time'),'temporary'=>tr('profile.employment.temporary'),'contract'=>tr('profile.employment.contract'),'internship'=>tr('profile.employment.internship'),'freelance'=>tr('profile.employment.freelance')];
        $selectedEmployment = array_filter(explode(',', (string)($preference['employment_types'] ?? '')));
        $promptFacts = array_values(array_filter([
            trim((string)($preference['desired_roles'] ?? '')) !== '' ? tr('job_search.fact.roles') . ': ' . trim((string)$preference['desired_roles']) : '',
            trim((string)($preference['desired_locations'] ?? '')) !== '' ? tr('job_search.fact.locations') . ': ' . trim((string)$preference['desired_locations']) : '',
            ($preference['remote_preference'] ?? 'any') !== 'any' ? tr('jobs.workplace_type') . ': ' . (workplaceTypeOptions()[(string)$preference['remote_preference']] ?? (string)$preference['remote_preference']) : '',
            $selectedEmployment ? tr('profile.employment_types') . ': ' . implode(', ', array_map(static fn(string $type): string => $employmentLabels[$type] ?? $type, $selectedEmployment)) : '',
            ($preference['workload_min'] ?? '') !== '' || ($preference['workload_max'] ?? '') !== '' ? tr('profile.workload') . ': ' . trim((string)($preference['workload_min'] ?? '')) . '-' . trim((string)($preference['workload_max'] ?? '')) . '%' : '',
            ($preference['salary_min'] ?? '') !== '' ? tr('profile.salary') . ': ' . number_format((float)$preference['salary_min'], 0, '.', "'") . ' ' . (string)($preference['salary_currency'] ?? 'CHF') . ' ' . (salaryPeriodOptions()[(string)($preference['salary_period'] ?? 'year')] ?? '') : '',
            trim((string)($preference['desired_level'] ?? '')) !== '' ? tr('profile.desired_level') . ': ' . trim((string)$preference['desired_level']) : '',
            trim((string)($preference['desired_benefits'] ?? '')) !== '' ? tr('profile.desired_benefits') . ': ' . trim((string)$preference['desired_benefits']) : '',
            trim((string)($preference['excluded_industries'] ?? '')) !== '' ? tr('profile.exclusions') . ': ' . trim((string)$preference['excluded_industries']) : '',
            !empty($preference['willing_to_relocate']) ? tr('profile.willing_to_relocate') : '',
            ($preference['travel_percentage'] ?? '') !== '' ? tr('profile.travel_percentage') . ': ' . (int)$preference['travel_percentage'] . '%' : '',
            trim((string)($preference['available_from'] ?? '')) !== '' ? tr('profile.available_from') . ': ' . trim((string)$preference['available_from']) : '',
            trim((string)($preference['notes'] ?? '')) !== '' ? tr('profile.preference_notes') . ': ' . trim((string)$preference['notes']) : '',
        ]));
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('job_search.section')) ?></p><h1><?= e(tr('job_search.title')) ?></h1></div><span><?= e(tr('job_search.active_portals', null, ['count' => (string) count($platformRows)])) ?></span></div>
        <section class="panel"><div class="section-head"><div><p class="eyebrow"><?= e(tr('job_search.profile_based')) ?></p><h2><?= e(tr('job_search.find_matching')) ?></h2></div><a href="/?page=profile"><?= e(tr('job_search.edit_profile_preferences')) ?></a></div>
            <?php if($query === ''): ?><p class="alert warning"><?= e(tr('job_search.missing_query')) ?></p><?php else: ?><p class="meta-line"><?= e(tr('job_search.query_from_profile')) ?>: <strong><?= e($query) ?></strong><?= $location !== '' ? ' · ' . e(tr('jobs.location')) . ': <strong>' . e($location) . '</strong>' : '' ?></p><?php endif; ?>
            <form method="post" class="stack" data-progress-form data-progress-button-text="<?= e(tr('job_search.progress_button')) ?>" data-progress-steps="<?= e(implode('|', [tr('job_search.progress.prepare_portals'), tr('job_search.progress.build_links'), tr('job_search.progress.prepare_import')])) ?>"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="generate_platform_search"><label><?= e(tr('job_search.query')) ?><input name="search_query" value="<?= e($query) ?>" placeholder="<?= e(tr('job_search.query_placeholder')) ?>" required></label><label><?= e(tr('job_search.total_prepare')) ?><input type="number" min="1" max="100" name="total_count" value="15"></label><fieldset class="check platform-choice-grid"><legend><?= e(tr('job_search.select_portals')) ?></legend><?php foreach($platformRows as $platform): ?><label><input type="checkbox" name="platform_ids[]" value="<?= (int)$platform['id'] ?>" checked> <span><strong><?= e($platform['name']) ?></strong><small><?= e($platform['base_url']) ?></small></span></label><?php endforeach; ?></fieldset><div class="progress-box" data-progress-box hidden><div class="progress-title"><?= e(tr('job_search.progress_title')) ?></div><div class="progress-track"><span data-progress-bar></span></div><p data-progress-text><?= e(tr('job_search.progress.prepare_portals')) ?></p></div><button class="primary" type="submit" <?= !$platformRows ? 'disabled' : '' ?> data-progress-button><?= e(tr('job_search.create_package')) ?></button></form>
        </section>
        <section class="panel prompt-panel"><div class="section-head"><div><p class="eyebrow"><?= e(tr('job_search.chatgpt_section')) ?></p><h2><?= e(tr('job_search.prompt_title')) ?></h2></div><div class="actions copy-actions"><button type="button" data-copy-target="chatgpt-job-prompt"><?= e(tr('job_search.copy_prompt')) ?></button><a class="button" href="/?page=jobs#quick-import"><?= e(tr('job_search.to_quick_import')) ?></a></div></div><label><?= e(tr('job_search.prompt')) ?><textarea id="chatgpt-job-prompt" rows="15" readonly></textarea></label><p class="meta-line"><?= e(tr('job_search.copy_instruction')) ?></p></section>
        <section class="panel" id="results"><div class="section-head"><div><p class="eyebrow"><?= e(tr('job_search.ready_for_import')) ?></p><h2><?= e(tr('job_search.package')) ?></h2></div><?php if($searchResults): ?><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><button class="primary" name="action" value="prepare_platform_import"><?= e(tr('job_search.import_package')) ?></button></form><?php endif; ?></div>
            <div class="dossier-list"><?php foreach($searchResults as $result): ?><article><strong><?= e((string)$result['name']) ?></strong><span><?= e(tr('job_search.result_meta', null, ['count' => (string) (int)$result['limit'], 'query' => (string)$result['query'], 'location' => (string)$result['location']])) ?></span><a href="<?= e((string)$result['url']) ?>" target="_blank" rel="noopener"><?= e((string)$result['url']) ?></a><p class="meta-line"><?= e(tr('job_search.open_portal_hint')) ?></p></article><?php endforeach; ?><?php if(!$searchResults): ?><p class="empty"><?= e(tr('job_search.empty_package')) ?></p><?php endif; ?></div>
        </section>
        <script>
        (() => {
            const facts = <?= json_encode($promptFacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const text = <?= json_encode([
                'defaultQuery' => tr('job_search.prompt.default_query'),
                'researchInstruction' => tr('job_search.prompt.research_instruction'),
                'strictImport' => tr('job_search.prompt.strict_import'),
                'searchTerm' => tr('job_search.prompt.search_term'),
                'desiredCount' => tr('job_search.prompt.desired_count'),
                'profileParameters' => tr('job_search.prompt.profile_parameters'),
                'preferredPortals' => tr('job_search.prompt.preferred_portals'),
                'rules' => tr('job_search.prompt.rules'),
                'ruleOpenJobs' => tr('job_search.prompt.rule_open_jobs'),
                'ruleDirectLinks' => tr('job_search.prompt.rule_direct_links'),
                'rulePreferredPortals' => tr('job_search.prompt.rule_preferred_portals'),
                'ruleDedupe' => tr('job_search.prompt.rule_dedupe'),
                'ruleRegion' => tr('job_search.prompt.rule_region'),
                'ruleNoInvented' => tr('job_search.prompt.rule_no_invented'),
                'ruleLessResults' => tr('job_search.prompt.rule_less_results'),
                'ruleNoReason' => tr('job_search.prompt.rule_no_reason'),
                'ruleNoLimits' => tr('job_search.prompt.rule_no_limits'),
                'ruleNoCannot' => tr('job_search.prompt.rule_no_cannot'),
                'outputFormat' => tr('job_search.prompt.output_format'),
                'onlyUrls' => tr('job_search.prompt.only_urls'),
                'oneUrlPerLine' => tr('job_search.prompt.one_url_per_line'),
                'noNumbering' => tr('job_search.prompt.no_numbering'),
                'noBullets' => tr('job_search.prompt.no_bullets'),
                'noMarkdown' => tr('job_search.prompt.no_markdown'),
                'noTitles' => tr('job_search.prompt.no_titles'),
                'noCompanies' => tr('job_search.prompt.no_companies'),
                'noExplanations' => tr('job_search.prompt.no_explanations'),
                'noIntro' => tr('job_search.prompt.no_intro'),
                'noClosing' => tr('job_search.prompt.no_closing'),
                'emptyIfNone' => tr('job_search.prompt.empty_if_none'),
                'finalCheck' => tr('job_search.prompt.final_check'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const form = document.querySelector('[data-progress-form]');
            const prompt = document.getElementById('chatgpt-job-prompt');
            if (!form || !prompt) return;
            const buildPrompt = () => {
                const query = form.querySelector('[name="search_query"]')?.value.trim() || text.defaultQuery;
                const total = form.querySelector('[name="total_count"]')?.value || '15';
                const platforms = Array.from(form.querySelectorAll('input[name="platform_ids[]"]:checked')).map((input) => {
                    const label = input.closest('label');
                    return label?.querySelector('strong')?.textContent?.trim() || '';
                }).filter(Boolean);
                const lines = [
                    text.researchInstruction,
                    text.strictImport,
                    '',
                    text.searchTerm + ': ' + query,
                    text.desiredCount + ': ' + total,
                    facts.length ? text.profileParameters + ':' : '',
                    ...facts.map((fact) => '- ' + fact),
                    platforms.length ? '' : '',
                    platforms.length ? text.preferredPortals + ':' : '',
                    ...platforms.map((platform) => '- ' + platform),
                    '',
                    text.rules + ':',
                    '- ' + text.ruleOpenJobs,
                    '- ' + text.ruleDirectLinks,
                    '- ' + text.rulePreferredPortals,
                    '- ' + text.ruleDedupe,
                    '- ' + text.ruleRegion,
                    '- ' + text.ruleNoInvented,
                    '- ' + text.ruleLessResults,
                    '- ' + text.ruleNoReason,
                    '- ' + text.ruleNoLimits,
                    '- ' + text.ruleNoCannot,
                    '',
                    text.outputFormat + ':',
                    text.onlyUrls,
                    text.oneUrlPerLine,
                    text.noNumbering,
                    text.noBullets,
                    text.noMarkdown,
                    text.noTitles,
                    text.noCompanies,
                    text.noExplanations,
                    text.noIntro,
                    text.noClosing,
                    text.emptyIfNone,
                    text.finalCheck
                ].filter((line, index, all) => line !== '' || all[index - 1] !== '');
                prompt.value = lines.join('\n');
            };
            form.addEventListener('input', buildPrompt);
            form.addEventListener('change', buildPrompt);
            document.querySelectorAll('[data-copy-target]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const target = document.getElementById(button.dataset.copyTarget || '');
                    if (!target) return;
                    const value = 'value' in target ? target.value : target.textContent;
                    if (!value) return;
                    try {
                        await navigator.clipboard.writeText(value);
                        button.textContent = <?= json_encode(tr('common.copied'), JSON_UNESCAPED_UNICODE) ?>;
                        setTimeout(() => { button.textContent = button.dataset.copyLabel || <?= json_encode(tr('common.copy'), JSON_UNESCAPED_UNICODE) ?>; }, 1200);
                    } catch {
                        target.focus();
                        target.select?.();
                    }
                });
                button.dataset.copyLabel = button.textContent || <?= json_encode(tr('common.copy'), JSON_UNESCAPED_UNICODE) ?>;
            });
            buildPrompt();
        })();
        </script>
    <?php elseif ($page === 'jobs'): ?>
        <?php
        $companyFilter = (int)($_GET['company_id'] ?? 0); $jobView = ($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards';
        $jobSfFields = ['title'=>['label'=>tr('common.title'),'expr'=>'j.title'], 'company'=>['label'=>tr('companies.company'),'expr'=>'c.name'], 'location'=>['label'=>tr('jobs.location'),'expr'=>'j.location_text'], 'status'=>['label'=>tr('common.status'),'expr'=>'j.status', 'choices'=>jobStatusOptions()], 'match'=>['label'=>tr('jobs.match'),'expr'=>'j.updated_at']];
        $jobSf = sfState('jobs', $jobSfFields, ['sort'=>'title','dir'=>'asc']);
        $jobPreserve = ['page'=>'jobs', 'view'=>$jobView, 'company_id'=>$companyFilter ?: '', 'edit'=>$_GET['edit'] ?? ''];
        $sql = 'SELECT j.id, j.company_id, j.title, j.location_text, j.status, j.workplace_type, j.engagement_type, j.contract_term, j.fixed_term_start, j.fixed_term_end, j.source_url, j.original_pdf_status, j.original_pdf_requested_at, j.original_pdf_rendered_at, j.original_pdf_error, j.salary_min, j.salary_max, j.salary_currency, j.salary_period, SUBSTRING(j.description,1,65535) description, SUBSTRING(j.notes,1,65535) notes, j.updated_at, c.name company_name, (SELECT d.id FROM user_documents d WHERE d.user_id=j.owner_user_id AND d.job_id=j.id AND d.title="Originale Stellenausschreibung" AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 1) original_document_id FROM jobs j JOIN companies c ON c.id=j.company_id WHERE j.owner_user_id=? AND j.deleted_at IS NULL'; $types='i'; $vals=[userId()];
        if ($companyFilter > 0) { $sql .= ' AND j.company_id=?'; $types.='i'; $vals[]=$companyFilter; }
        $sql .= sfApplySql($jobSf, $jobSfFields, $types, $vals);
        $sql .= sfOrderSql($jobSf, $jobSfFields, 'title');
        $jobs=dbAll($db,$sql,$types,$vals);
        $edit = isset($_GET['edit']) ? dbOne($db, 'SELECT id, company_id, title, location_text, status, workplace_type, engagement_type, contract_term, fixed_term_start, fixed_term_end, salary_min, salary_max, salary_currency, salary_period, source_url, original_pdf_status, SUBSTRING(description,1,65535) description, SUBSTRING(notes,1,65535) notes FROM jobs WHERE id=? AND owner_user_id=? AND deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $draft = is_array($_SESSION['import_draft'] ?? null) ? $_SESSION['import_draft'] : [];
        $form = $edit ?: $draft;
        $draftCompany = trim((string) ($draft['company'] ?? ''));
        $matchedCompanyId = 0;
        foreach ($companies as $candidate) {
            if ($draftCompany !== '' && mb_strtolower($candidate['name']) === mb_strtolower($draftCompany)) {
                $matchedCompanyId = (int) $candidate['id'];
                break;
            }
        }
        $jobCurrency = currencyForCountry($currentUser['country_code'] ?? 'CH');
        $jobContacts = $edit ? dbAll($db, 'SELECT c.id, c.job_id, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, co.name company_name, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=c.id AND l.owner_user_id=c.owner_user_id) log_count FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND c.deleted_at IS NULL AND (c.job_id=? OR c.company_id=?) ORDER BY CASE WHEN c.job_id=? THEN 0 ELSE 1 END, c.last_name, c.first_name', 'iiii', [userId(), (int)$edit['id'], (int)$edit['company_id'], (int)$edit['id']]) : [];
        $jobQuestions = $edit ? dbAll($db, 'SELECT id, question_text, answer_text, sort_order, created_at FROM job_questions WHERE owner_user_id=? AND job_id=? AND deleted_at IS NULL ORDER BY sort_order, id', 'ii', [userId(), (int)$edit['id']]) : [];
        $platformImportPayload = (string)($_SESSION['platform_import_payload'] ?? '');
        unset($_SESSION['platform_import_payload']);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('jobs.section')) ?></p><h1><?= e(tr('nav.jobs')) ?></h1></div><span><?= e(tr('jobs.results_count', null, ['count' => (string) count($jobs)])) ?></span></div>
        <section class="panel import-panel" id="quick-import"><h2><?= e(tr('jobs.quick_import')) ?></h2><p><?= e(tr('jobs.quick_import_hint')) ?></p><form method="post" class="import-form" data-progress-form data-progress-button-text="<?= e(tr('jobs.progress_button')) ?>" data-progress-steps="<?= e(implode('|', [tr('jobs.progress.read_import'), tr('jobs.progress.check_links'), tr('jobs.progress.prepare_suggestion'), tr('jobs.progress.check_duplicates')])) ?>"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="preview_import"><textarea name="import_payload" rows="4" placeholder="<?= e(tr('jobs.quick_import_placeholder')) ?>" required><?= e($platformImportPayload) ?></textarea><div class="progress-box" data-progress-box hidden><div class="progress-title"><?= e(tr('jobs.progress_title')) ?></div><div class="progress-track"><span data-progress-bar></span></div><p data-progress-text><?= e(tr('jobs.progress.read_import')) ?></p></div><button class="primary" type="submit" data-progress-button><?= e(tr('jobs.create_suggestion')) ?></button></form><?php if($platformImportPayload !== ''): ?><p class="meta-line"><?= e(tr('jobs.search_links_imported')) ?></p><?php endif; ?></section>
        <div class="actions export-actions"><?= sfToolbar('jobs', $jobSf, ['page'=>'jobs', 'view'=>$jobView, 'company_id'=>$companyFilter ?: ''], $jobSfFields) ?><a class="button" href="/?page=jobs&view=cards<?= $companyFilter ? '&company_id=' . (int)$companyFilter : '' ?>"><?= e(tr('common.cards')) ?></a><a class="button" href="/?page=jobs&view=table<?= $companyFilter ? '&company_id=' . (int)$companyFilter : '' ?>"><?= e(tr('common.table')) ?></a><a class="button" href="/?page=export_pdf&type=jobs">PDF</a></div>
        <div class="split"><section class="panel" id="new"><h2><?= e($edit ? tr('jobs.edit') : ($draft ? tr('jobs.check_import') : tr('jobs.create'))) ?></h2><form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <label><?= e(tr('companies.company')) ?><select name="company_id"><option value="0"><?= e(tr('jobs.new_company_from_import')) ?></option><?php foreach($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($form['company_id']??$matchedCompanyId)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
            <label><?= e(tr('jobs.new_company')) ?><input name="new_company_name" value="<?= e($matchedCompanyId ? '' : $draftCompany) ?>" placeholder="<?= e(tr('jobs.new_company_placeholder')) ?>"></label>
            <label><?= e(tr('jobs.job_title')) ?><input name="title" value="<?= e($form['title'] ?? '') ?>" required></label>
            <div class="two"><label><?= e(tr('jobs.location')) ?><input name="location_text" value="<?= e($form['location_text'] ?? $form['location'] ?? '') ?>"></label><label><?= e(tr('jobs.workplace_type')) ?><select name="workplace_type"><?php foreach(workplaceTypeOptions() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['workplace_type']??'unknown')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
            <div class="two"><label><?= e(tr('jobs.engagement_type')) ?><select name="engagement_type"><?php foreach(engagementTypeOptions() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['engagement_type']??'permanent')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><label><?= e(tr('jobs.contract_term')) ?><select name="contract_term"><?php foreach(contractTermOptions() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['contract_term']??'unknown')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
            <div class="two"><label><?= e(tr('jobs.fixed_from')) ?><input type="date" name="fixed_term_start" value="<?= e($form['fixed_term_start'] ?? '') ?>"></label><label><?= e(tr('jobs.fixed_until')) ?><input type="date" name="fixed_term_end" value="<?= e($form['fixed_term_end'] ?? '') ?>"></label></div>
            <div class="salary-row"><label><?= e(tr('profile.salary')) ?> <span><?= e($jobCurrency) ?></span><input type="number" min="0" step="0.01" name="salary_min" value="<?= e((string)($form['salary_min'] ?? '')) ?>"></label><label><?= e(tr('profile.salary_format')) ?><select name="salary_period"><?php foreach(salaryPeriodOptions() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['salary_period'] ?? 'year')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label></div>
            <label><?= e(tr('common.status')) ?><select name="status"><?php foreach(jobStatusOptions() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($form['status']??'open')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label><?= e(tr('jobs.source_url')) ?><input type="url" name="source_url" value="<?= e($form['source_url'] ?? '') ?>"><?php if(!empty($form['source_url'])): ?><small><a href="<?= e($form['source_url']) ?>" target="_blank" rel="noopener"><?= e(tr('jobs.open_source_url')) ?></a></small><?php endif; ?></label><label><?= e(tr('common.description')) ?><textarea name="description" rows="6"><?= e($form['description'] ?? '') ?></textarea></label><label><?= e(tr('common.comment')) ?><textarea name="job_notes" rows="4"><?= e($form['notes'] ?? '') ?></textarea></label>
            <?php if(!empty($_GET['duplicate'])): ?><label class="check"><input type="checkbox" name="confirm_duplicate" value="1" required> <?= e(tr('jobs.save_as_separate')) ?></label><?php endif; ?><button class="primary" name="action" value="save_job"><?= e(tr('common.save')) ?></button>
        </form><?php if($edit): ?><form method="post" class="actions editor-actions"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$edit['id'] ?>"><button class="primary" name="action" value="start_application"><?= e(tr('applications.prepare')) ?></button><a class="button" href="/?page=applications&job_id=<?= (int)$edit['id'] ?>"><?= e(tr('applications.show')) ?></a></form><?php endif; ?></section>
        <?php if($edit): ?><section class="panel" id="job-contacts"><div class="section-head"><div><p class="eyebrow"><?= e(tr('nav.contacts')) ?></p><h2><?= e(tr('jobs.contacts_for_job')) ?></h2></div><a href="/?page=contacts&company_id=<?= (int)$edit['company_id'] ?>"><?= e(tr('jobs.all_company_contacts')) ?></a></div><div class="split inner-split"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$edit['id'] ?>"><div class="two"><label><?= e(tr('auth.first_name')) ?><input name="first_name" required></label><label><?= e(tr('auth.last_name')) ?><input name="last_name" required></label></div><div class="two"><label><?= e(tr('contacts.position')) ?><input name="position"></label><label><?= e(tr('contacts.department')) ?><input name="department"></label></div><label><?= e(tr('auth.email')) ?><input type="email" name="contact_email"></label><div class="two"><label><?= e(tr('profile.phone')) ?><input name="phone"></label><label><?= e(tr('profile.mobile')) ?><input name="mobile"></label></div><label>LinkedIn<input type="url" name="linkedin_url"></label><label><?= e(tr('profile.language_label')) ?><select name="preferred_language"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label><label><?= e(tr('common.comment')) ?><textarea name="contact_notes" rows="3"></textarea></label><button class="primary" name="action" value="save_job_contact"><?= e(tr('contacts.save_contact')) ?></button></form><div class="contact-list"><?php foreach($jobContacts as $contact): ?><article class="<?= (int)$contact['job_id']===(int)$edit['id']?'is-primary':'' ?>"><small><?= e($contact['company_name']) ?><?= (int)$contact['job_id']===(int)$edit['id'] ? ' · ' . e(tr('reports.field.job')) : ' · ' . e(tr('companies.company')) ?></small><strong><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>#contact-log"><?= e($contact['first_name'].' '.$contact['last_name']) ?></a></strong><span><?= e($contact['position'] ?: $contact['department']) ?></span><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><small><?= e($contact['phone'] ?: $contact['mobile']) ?></small><div class="actions"><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>"><?= e(tr('common.edit')) ?></a><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>#contact-log"><?= e(tr('contact_log.title')) ?></a></div></article><?php endforeach; ?><?php if(!$jobContacts): ?><p class="empty"><?= e(tr('jobs.no_contacts')) ?></p><?php endif; ?></div></div></section><?php endif; ?>
        <?php if($edit): ?><section class="panel" id="job-questions"><div class="section-head"><div><p class="eyebrow"><?= e(tr('jobs.preparation')) ?></p><h2><?= e(tr('jobs.application_questions')) ?></h2></div><span><?= e(tr('jobs.questions_count', null, ['count' => (string) count($jobQuestions)])) ?></span></div><div class="split inner-split"><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$edit['id'] ?>"><label><?= e(tr('jobs.question')) ?><textarea name="question_text" rows="3" required placeholder="<?= e(tr('jobs.question_placeholder')) ?>"></textarea></label><label><?= e(tr('jobs.answer_preparation')) ?><textarea name="answer_text" rows="4" placeholder="<?= e(tr('jobs.answer_placeholder')) ?>"></textarea></label><label><?= e(tr('jobs.sort_order')) ?><input type="number" min="0" name="sort_order" value="<?= count($jobQuestions) + 1 ?>"></label><button class="primary" name="action" value="save_job_question"><?= e(tr('jobs.save_question')) ?></button></form><div class="dossier-list"><?php foreach($jobQuestions as $question): ?><article><strong><?= nl2br(e((string)$question['question_text'])) ?></strong><p><?= nl2br(e((string)$question['answer_text'])) ?></p><form method="post" class="actions" onsubmit="return confirm('<?= e(tr('jobs.delete_question_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><button name="action" value="delete_job_question"><?= e(tr('common.delete')) ?></button></form></article><?php endforeach; ?><?php if(!$jobQuestions): ?><p class="empty"><?= e(tr('jobs.no_questions')) ?></p><?php endif; ?></div></div></section><?php endif; ?>
        <?php if($jobView === 'table'): ?><section class="panel table-wrap"><table><thead><tr><?= sfHeader('jobs','title',tr('common.title'),$jobSf,$jobPreserve) ?><?= sfHeader('jobs','company',tr('companies.company'),$jobSf,$jobPreserve) ?><?= sfHeader('jobs','location',tr('jobs.location'),$jobSf,$jobPreserve) ?><?= sfHeader('jobs','status',tr('common.status'),$jobSf,$jobPreserve) ?><?= sfHeader('jobs','match',tr('jobs.match'),$jobSf,$jobPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); $jobSalaryLabel=salaryLabel($job,$jobCurrency); ?><tr><td><strong><a href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></strong><small><?= e(mb_strimwidth((string)$job['description'],0,120,'...')) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a></td><td><?= e($job['location_text']) ?></td><td><?= e(jobStatusOptions()[(string)$job['status']] ?? (string)$job['status']) ?><small><?= e(engagementTypeOptions()[(string)$job['engagement_type']] ?? (string)$job['engagement_type']) ?> · <?= e(contractTermOptions()[(string)$job['contract_term']] ?? (string)$job['contract_term']) ?></small><?php if($jobSalaryLabel !== ''): ?><small><?= e(tr('profile.salary')) ?>: <?= e($jobSalaryLabel) ?></small><?php endif; ?></td><td><?= $score ?>%</td><td class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button name="action" value="start_application"><?= e(tr('applications.prepare')) ?></button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>"><?= e(tr('nav.applications')) ?></a></td></tr><?php endforeach; ?><?php if(!$jobs): ?><tr><td colspan="6" class="empty"><?= e(tr('common.no_results')) ?></td></tr><?php endif; ?></tbody></table></section><?php else: ?><section class="cards"><?php foreach($jobs as $job): [$score,$reasons]=matchJob($job); $jobSalaryLabel=salaryLabel($job,$jobCurrency); ?><article class="job-card <?= $edit && (int)$edit['id']===(int)$job['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e(jobStatusOptions()[(string)$job['status']] ?? (string)$job['status']) ?></span><span class="score"><?= $score ?>%</span></div><h3><a class="record-link" href="/?page=jobs&edit=<?= (int)$job['id'] ?>#new"><?= e($job['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$job['company_id'] ?>"><?= e($job['company_name']) ?></a> · <?= e($job['location_text']) ?></p><p class="meta-line"><?= e(engagementTypeOptions()[(string)$job['engagement_type']] ?? (string)$job['engagement_type']) ?> · <?= e(contractTermOptions()[(string)$job['contract_term']] ?? (string)$job['contract_term']) ?></p><?php if($jobSalaryLabel !== ''): ?><p class="meta-line"><?= e(tr('profile.salary')) ?>: <?= e($jobSalaryLabel) ?></p><?php endif; ?><p><?= e(mb_strimwidth((string)$job['description'],0,180,'...')) ?></p><details><summary><?= e(tr('jobs.why_match', null, ['score' => (string) $score])) ?></summary><ul><?php foreach($reasons as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul></details><div class="actions"><form method="post"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="job_id" value="<?= (int)$job['id'] ?>"><button class="primary-link" name="action" value="start_application"><?= e(tr('applications.prepare')) ?></button></form><a href="/?page=applications&job_id=<?= (int)$job['id'] ?>"><?= e(tr('nav.applications')) ?></a><?php if(!empty($job['original_document_id'])): ?><a href="/?page=document_download&id=<?= (int)$job['original_document_id'] ?>"><?= e(tr('jobs.original_pdf')) ?></a><?php endif; ?><form method="post" onsubmit="return confirm('<?= e(tr('jobs.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$job['id'] ?>"><button name="action" value="delete_job"><?= e(tr('common.delete')) ?></button></form></div></article><?php endforeach; ?><?php if(!$jobs): ?><div class="empty"><?= e(tr('jobs.empty')) ?></div><?php endif; ?></section><?php endif; ?></div>
    <?php elseif ($page === 'applications'): ?>
        <?php
        $appCompanyFilter=(int)($_GET['company_id'] ?? 0); $appJobFilter=(int)($_GET['job_id'] ?? 0); $todoOnly=!empty($_GET['todo']); $appView=($_GET['view'] ?? 'cards') === 'table' ? 'table' : 'cards';
        $appSfFields = ['title'=>['label'=>tr('reports.field.job'),'expr'=>'j.title'], 'company'=>['label'=>tr('companies.company'),'expr'=>'c.name'], 'status'=>['label'=>tr('common.status'),'expr'=>'a.status', 'choices'=>applicationStatusOptions()], 'channel'=>['label'=>tr('applications.channel'),'expr'=>'a.channel', 'choices'=>applicationChannelOptions()], 'next_action'=>['label'=>tr('applications.next_action'),'expr'=>'CONCAT_WS(" ", a.next_action, a.next_action_at)']];
        $appSf = sfState('applications', $appSfFields, ['sort'=>'title','dir'=>'asc']);
        $appPreserve = ['page'=>'applications', 'view'=>$appView, 'company_id'=>$appCompanyFilter ?: '', 'job_id'=>$appJobFilter ?: '', 'todo'=>$todoOnly ? '1' : '', 'edit'=>$_GET['edit'] ?? ''];
        $appSql='SELECT a.id, a.job_id, a.intermediary_company_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.updated_at, j.title, j.company_id, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.user_id=? AND a.deleted_at IS NULL'; $appTypes='i'; $appVals=[userId()];
        if($appCompanyFilter>0){ $appSql.=' AND (j.company_id=? OR a.intermediary_company_id=?)'; $appTypes.='ii'; array_push($appVals,$appCompanyFilter,$appCompanyFilter); }
        if($appJobFilter>0){ $appSql.=' AND a.job_id=?'; $appTypes.='i'; $appVals[]=$appJobFilter; }
        if($todoOnly){ $appSql.=' AND a.next_action_at IS NOT NULL'; }
        $appSql .= sfApplySql($appSf, $appSfFields, $appTypes, $appVals);
        $appSql .= sfOrderSql($appSf, $appSfFields, 'title');
        $apps=dbAll($db,$appSql,$appTypes,$appVals);
        $applicationEdit = isset($_GET['edit']) ? dbOne($db, 'SELECT a.id, a.job_id, a.intermediary_company_id, a.primary_contact_id, a.status, a.applied_at, a.channel, a.next_action, a.next_action_at, a.application_url, a.portal_account, a.reference_number, SUBSTRING(a.online_notes,1,65535) online_notes, a.email_subject, SUBSTRING(a.email_body,1,65535) email_body, SUBSTRING(a.cover_letter_text,1,65535) cover_letter_text, SUBSTRING(a.notes,1,65535) notes, j.company_id, j.title, j.source_url job_source_url, c.name company_name, i.name intermediary_company_name FROM applications a JOIN jobs j ON j.id=a.job_id JOIN companies c ON c.id=j.company_id LEFT JOIN companies i ON i.id=a.intermediary_company_id WHERE a.id=? AND a.user_id=? AND a.deleted_at IS NULL', 'ii', [(int)$_GET['edit'], userId()]) : null;
        $history = $applicationEdit ? dbAll($db, 'SELECT old_status, new_status, comment, changed_at FROM application_status_history WHERE application_id=? ORDER BY changed_at DESC', 'i', [(int)$applicationEdit['id']]) : [];
        $contacts = $applicationEdit ? dbAll($db, 'SELECT c.id, c.company_id, c.application_id, c.job_id, c.first_name, c.last_name, c.position, c.department, c.email, c.phone, c.mobile, c.linkedin_url, c.preferred_language, c.notes, co.name contact_company_name FROM contacts c JOIN companies co ON co.id=c.company_id WHERE c.owner_user_id=? AND (c.company_id=? OR c.company_id=? OR c.application_id=? OR c.job_id=?) AND c.deleted_at IS NULL ORDER BY co.name, c.last_name, c.first_name', 'iiiii', [userId(), (int)$applicationEdit['company_id'], (int)($applicationEdit['intermediary_company_id'] ?? 0), (int)$applicationEdit['id'], (int)$applicationEdit['job_id']]) : [];
        $selectedContactId = (int) ($_GET['contact'] ?? ($applicationEdit['primary_contact_id'] ?? 0));
        $contactEdit = $selectedContactId > 0 && $applicationEdit ? dbOne($db, 'SELECT id, company_id, application_id, job_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes FROM contacts WHERE id=? AND owner_user_id=? AND (company_id=? OR company_id=? OR application_id=? OR job_id=?) AND deleted_at IS NULL', 'iiiiii', [$selectedContactId, userId(), (int)$applicationEdit['company_id'], (int)($applicationEdit['intermediary_company_id'] ?? 0), (int)$applicationEdit['id'], (int)$applicationEdit['job_id']]) : null;
        $contactLogs = $contactEdit ? dbAll($db, 'SELECT id, contact_id, application_id, job_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND contact_id=? ORDER BY CASE status WHEN "open" THEN 1 WHEN "planned" THEN 2 WHEN "done" THEN 3 ELSE 4 END, COALESCE(follow_up_at, occurred_at) ASC', 'ii', [userId(), (int)$contactEdit['id']]) : [];
        $contactAttachments = $contactEdit ? contactLogAttachments($db, userId(), (int)$contactEdit['id']) : [];
        $editLogId = (int) ($_GET['edit_log'] ?? 0);
        $editLog = $editLogId > 0 ? dbOne($db, 'SELECT id, contact_id, application_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE id=? AND owner_user_id=? AND contact_id=?', 'iii', [$editLogId, userId(), (int)($contactEdit['id'] ?? 0)]) : null;
        $documentTypes = $applicationEdit ? dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id') : [];
        $applicationDocumentTypes = $applicationEdit ? documentTypesForScope($documentTypes, 'application') : [];
        $applicationDocuments = $applicationEdit ? dbAll($db, "SELECT ad.purpose, d.id, d.scope, d.title, d.version, d.original_filename, d.created_at, d.file_size, dt.code type_code, dt.name_key type_name FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id JOIN document_types dt ON dt.id=d.document_type_id WHERE ad.application_id=? AND d.user_id=? AND ((d.scope='application' AND d.application_id=?) OR d.scope='profile') AND d.deleted_at IS NULL ORDER BY ad.sort_order, d.scope DESC, d.is_current DESC, d.title, d.version DESC", 'iii', [(int)$applicationEdit['id'], userId(), (int)$applicationEdit['id']]) : [];
        $attachedDocumentIds = array_flip(array_map('intval', array_column($applicationDocuments, 'id')));
        $applicationProfileDocuments = $applicationEdit ? dbAll($db, "SELECT d.id, d.title, d.version, d.original_filename, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.is_current=1 AND d.deleted_at IS NULL ORDER BY d.title, d.version DESC", 'i', [userId()]) : [];
        $intermediaryCompanies = $applicationEdit ? array_values(array_filter($companies, static fn (array $company): bool => !empty($company['is_intermediary']) && (int)$company['id'] !== (int)$applicationEdit['company_id'])) : [];
        $userLanguage = normalizeLocale((string) ($currentUser['preferred_language'] ?? 'de-CH'));
        $nextActionChoices = applicationNextActionChoices();
        $nextActionOptions = applicationNextActionOptions();
        $coverLetterPrompt = '';
        if ($applicationEdit && trim((string)($applicationEdit['cover_letter_text'] ?? '')) === '') {
            try {
                $coverLetterPrompt = applicationPrompt($db, userId(), (int)$applicationEdit['id'], $currentUser);
            } catch (Throwable $exception) {
                error_log('Application prompt failed for application ' . (int)$applicationEdit['id'] . ': ' . $exception->getMessage());
            }
        }
        $applicationStatuses=applicationStatusOptions();
        $contactLogStatuses=contactLogStatusOptions();
        $contactLogChannels=contactLogChannelOptions();
        $channels=applicationChannelOptions();
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('applications.section')) ?></p><h1><?= e(tr('nav.applications')) ?></h1></div><span><?= e(tr('common.entries_count', null, ['count' => (string) count($apps)])) ?></span></div>
        <div class="actions export-actions"><?= sfToolbar('applications', $appSf, ['page'=>'applications', 'view'=>$appView, 'company_id'=>$appCompanyFilter ?: '', 'job_id'=>$appJobFilter ?: '', 'todo'=>$todoOnly ? '1' : ''], $appSfFields) ?><a class="button" href="/?page=applications&view=cards<?= $appCompanyFilter ? '&company_id=' . (int)$appCompanyFilter : '' ?><?= $appJobFilter ? '&job_id=' . (int)$appJobFilter : '' ?><?= $todoOnly ? '&todo=1' : '' ?>"><?= e(tr('common.cards')) ?></a><a class="button" href="/?page=applications&view=table<?= $appCompanyFilter ? '&company_id=' . (int)$appCompanyFilter : '' ?><?= $appJobFilter ? '&job_id=' . (int)$appJobFilter : '' ?><?= $todoOnly ? '&todo=1' : '' ?>"><?= e(tr('common.table')) ?></a><a class="button" href="/?page=export_pdf&type=applications">PDF</a><?php if($applicationEdit): ?><a class="button primary" href="/?page=application_dossier&id=<?= (int)$applicationEdit['id'] ?>"><?= e(tr('applications.dossier')) ?></a><?php endif; ?></div>
        <?php if($appCompanyFilter || $appJobFilter || $todoOnly): ?><p class="filter-note"><?= e(tr('applications.filtered_view')) ?> · <a href="/?page=applications"><?= e(tr('applications.show_all')) ?></a></p><?php endif; ?>
        <?php if ($applicationEdit): ?><section class="panel company-path" id="companies"><div><p class="eyebrow"><?= e(tr('applications.company_relation')) ?></p><h2><?= e($applicationEdit['company_name']) ?><?php if($applicationEdit['intermediary_company_name']): ?> <span><?= e(tr('companies.by')) ?> <?= e($applicationEdit['intermediary_company_name']) ?></span><?php endif; ?></h2><p><?= e(tr('applications.company_relation_hint')) ?></p></div><form method="post" class="company-path-form"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><label><?= e(tr('applications.intermediary_company')) ?><select name="intermediary_company_id"><option value="0"><?= e(tr('applications.direct_without_intermediary')) ?></option><?php foreach($intermediaryCompanies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)$applicationEdit['intermediary_company_id']===(int)$company['id']?'selected':'' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select><small><?= e(tr('applications.intermediary_select_hint')) ?></small></label><button class="primary" name="action" value="set_intermediary"><?= e(tr('applications.save_assignment')) ?></button></form></section><?php endif; ?>
        <?php if ($applicationEdit): ?>
        <section class="panel application-editor" id="application-form">
            <div class="section-head">
                <div><p class="eyebrow"><?= e($applicationEdit['company_name']) ?></p><h2><?= e($applicationEdit['title']) ?></h2></div>
                <a href="/?page=applications"><?= e(tr('common.close')) ?></a>
            </div>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int)$applicationEdit['id'] ?>">
                <div class="three">
                    <label><?= e(tr('common.status')) ?><select name="status"><?php foreach($applicationStatuses as $v=>$l): ?><option value="<?= e($v) ?>" <?= $applicationEdit['status']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    <label><?= e(tr('applications.channel')) ?><select name="channel"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach($channels as $v=>$l): ?><option value="<?= e($v) ?>" <?= $applicationEdit['channel']===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    <label><?= e(tr('applications.sent_at')) ?><input type="datetime-local" name="applied_at" value="<?= e($applicationEdit['applied_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['applied_at'])) : '') ?>"></label>
                </div>
                <div class="actions"><button class="primary" name="action" value="save_application"><?= e(tr('applications.save')) ?></button></div>
                <div class="history"><h3><?= e(tr('applications.online_form_title')) ?></h3><p class="meta-line"><?= e(tr('applications.online_form_hint')) ?></p></div>
                <?php $onlineApplicationUrl = (string)($applicationEdit['application_url'] ?: ($applicationEdit['job_source_url'] ?? '')); ?>
                <div class="online-assistant">
                    <div class="actions">
                        <?php if($onlineApplicationUrl !== ''): ?><a class="button primary" href="<?= e($onlineApplicationUrl) ?>" target="_blank" rel="noopener"><?= e(tr('applications.open_webform')) ?></a><?php endif; ?>
                        <?php if($applicationDocuments): ?><a class="button" href="/?page=application_documents_temp&id=<?= (int)$applicationEdit['id'] ?>" target="_blank" rel="noopener"><?= e(tr('applications.temp_folder')) ?></a><a class="button" href="/?page=application_documents_zip&id=<?= (int)$applicationEdit['id'] ?>"><?= e(tr('applications.portal_zip')) ?></a><?php endif; ?>
                    </div>
                    <p class="meta-line"><?= e(tr('applications.online_submit_hint')) ?></p>
                </div>
                <label><?= e(tr('applications.online_url')) ?><input id="application-url" type="url" name="application_url" value="<?= e($onlineApplicationUrl) ?>" placeholder="https://..."></label>
                <div class="two"><label><?= e(tr('applications.portal_hint')) ?><input id="portal-account" name="portal_account" value="<?= e($applicationEdit['portal_account'] ?? '') ?>" placeholder="<?= e(tr('applications.portal_hint_placeholder')) ?>"></label><label><?= e(tr('applications.reference_number')) ?><input id="reference-number" name="reference_number" value="<?= e($applicationEdit['reference_number'] ?? '') ?>" placeholder="<?= e(tr('applications.reference_placeholder')) ?>"></label></div>
                <label><?= e(tr('applications.online_notes')) ?><textarea id="online-notes" name="online_notes" rows="3" placeholder="<?= e(tr('applications.online_notes_placeholder')) ?>"><?= e($applicationEdit['online_notes'] ?? '') ?></textarea></label>
                <div class="actions copy-actions"><button type="button" data-copy-target="application-url"><?= e(tr('applications.copy_url')) ?></button><button type="button" data-copy-target="portal-account"><?= e(tr('applications.copy_portal_hint')) ?></button><button type="button" data-copy-target="reference-number"><?= e(tr('applications.copy_reference')) ?></button><button type="button" data-copy-target="online-notes"><?= e(tr('applications.copy_online_notes')) ?></button></div>
                <label><?= e(tr('applications.contact_optional')) ?><select name="primary_contact_id"><option value="0"><?= e(tr('applications.no_contact_needed')) ?></option><?php foreach($contacts as $contact): ?><option value="<?= (int)$contact['id'] ?>" <?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'selected':'' ?>><?= e($contact['first_name'].' '.$contact['last_name'].($contact['position'] ? ' · '.$contact['position'] : '')) ?></option><?php endforeach; ?></select></label>
                <div class="two">
                    <label><?= e(tr('applications.next_action')) ?><select name="next_action"><option value=""><?= e(tr('applications.no_next_action')) ?></option><?php foreach($nextActionOptions as $choice=>$label): ?><option value="<?= e($choice) ?>" <?= ($applicationEdit['next_action'] ?? '')===$choice?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><?php if(($applicationEdit['next_action'] ?? '') && !in_array((string)$applicationEdit['next_action'], $nextActionChoices, true)): ?><small><?= e(tr('applications.previous_free_text')) ?>: <?= e($applicationEdit['next_action']) ?></small><?php endif; ?></label>
                    <label><?= e(tr('common.due_at')) ?><input type="datetime-local" name="next_action_at" value="<?= e($applicationEdit['next_action_at'] ? date('Y-m-d\TH:i', strtotime($applicationEdit['next_action_at'])) : '') ?>"></label>
                </div>
                <label><?= e(tr('applications.status_comment')) ?><input name="status_comment" placeholder="<?= e(tr('applications.status_comment_placeholder')) ?>"></label>
                <?php if(!mailEnabledForUser($db, $config, userId())): ?><p class="app-note"><?= e(tr('applications.smtp_missing_note')) ?></p><?php endif; ?>
                <label><?= e(tr('applications.email_recipient')) ?><input type="email" name="recipient_email" value="<?= e($contactEdit['email'] ?? '') ?>" placeholder="<?= e(tr('applications.email_recipient_placeholder')) ?>"></label>
                <label><?= e(tr('applications.email_subject')) ?><input id="email-subject" name="email_subject" value="<?= e($applicationEdit['email_subject'] ?? '') ?>"></label>
                <label><?= e(tr('applications.email_body')) ?><textarea id="email-body" name="email_body" rows="4"><?= e($applicationEdit['email_body'] ?? '') ?></textarea></label>
                <label><?= e(tr('applications.cover_letter')) ?><textarea id="cover-letter-text" name="cover_letter_text" rows="<?= $coverLetterPrompt ? 16 : 7 ?>"><?= e($applicationEdit['cover_letter_text'] ?: $coverLetterPrompt) ?></textarea><?php if($coverLetterPrompt): ?><small><?= e(tr('applications.cover_prompt_hint')) ?></small><?php endif; ?></label>
                <div class="actions copy-actions"><button type="button" data-copy-target="email-subject"><?= e(tr('applications.copy_subject')) ?></button><button type="button" data-copy-target="email-body"><?= e(tr('applications.copy_body')) ?></button><button type="button" data-copy-target="cover-letter-text"><?= e(tr('applications.copy_cover')) ?></button></div>
                <label><?= e(tr('applications.internal_notes')) ?><textarea name="notes" rows="4"><?= e($applicationEdit['notes'] ?? '') ?></textarea></label>
                <div class="actions"><button class="primary" name="action" value="save_application"><?= e(tr('applications.save')) ?></button><button class="primary" name="action" value="submit_online_application"><?= e(tr('applications.submitted_online')) ?></button><button name="action" value="send_application_email"><?= e(tr('applications.send_email')) ?></button></div>
            </form>
            <?php if($history): ?><div class="history"><h3><?= e(tr('applications.status_history')) ?></h3><?php foreach($history as $entry): ?><article><strong><?= e($applicationStatuses[$entry['new_status']] ?? $entry['new_status']) ?></strong><span><?= e(displayDateTime($entry['changed_at'], $currentUser)) ?></span><?php if($entry['comment']): ?><p><?= e($entry['comment']) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="panel contact-log" id="documents">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('applications.application_data')) ?></p><h2><?= e(tr('applications.documents')) ?></h2></div><div class="actions"><a href="/?page=profile#documents"><?= e(tr('applications.profile_documents')) ?></a><?php if($applicationDocuments): ?><a class="button" href="/?page=application_documents_temp&id=<?= (int)$applicationEdit['id'] ?>"><?= e(tr('applications.open_temp_folder')) ?></a><a class="button" href="/?page=application_documents_zip&id=<?= (int)$applicationEdit['id'] ?>"><?= e(tr('applications.download_portal_package')) ?></a><?php endif; ?></div></div>
            <div class="split inner-split">
                <form method="post" class="stack doc-picker">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>">
                    <div class="actions"><button type="button" data-doc-select="all"><?= e(tr('applications.select_all')) ?></button><button type="button" data-doc-select="none"><?= e(tr('applications.clear_selection')) ?></button></div>
                    <div class="doc-choice-list">
                        <?php foreach($applicationProfileDocuments as $doc): $alreadyAttached = isset($attachedDocumentIds[(int)$doc['id']]); ?><label class="doc-choice <?= $alreadyAttached ? 'is-attached' : '' ?>"><input type="checkbox" name="user_document_ids[]" value="<?= (int)$doc['id'] ?>" <?= $alreadyAttached ? 'checked' : '' ?>><span><strong><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?> · <?= e($doc['title']) ?></strong><small><?= e($doc['original_filename']) ?> · v<?= (int)$doc['version'] ?><?= $alreadyAttached ? ' · ' . e(tr('applications.assigned')) : '' ?></small></span></label><?php endforeach; ?>
                    </div>
                    <button class="primary" name="action" value="attach_application_document" <?= !$applicationProfileDocuments ? 'disabled' : '' ?>><?= e(tr('applications.assign_profile_documents')) ?></button>
                    <?php if(!$applicationProfileDocuments): ?><p class="empty"><?= e(tr('applications.no_profile_documents')) ?></p><?php endif; ?>
                </form>
                <form method="post" enctype="multipart/form-data" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="document_scope" value="application">
                    <input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>">
                    <label><?= e(tr('documents.new_version_of')) ?><select name="replace_document_id"><option value="0"><?= e(tr('documents.new_application_document')) ?></option><?php foreach($applicationDocuments as $doc): if($doc['scope'] !== 'application') continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label>
                    <label><?= e(tr('documents.document_type')) ?><select name="document_type_id"><?php foreach($applicationDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>"><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label>
                    <input type="hidden" name="purpose" value="cover_letter">
                    <label><?= e(tr('common.title')) ?><input name="document_title" required placeholder="<?= e(tr('documents.title_placeholder_application', null, ['company' => (string)$applicationEdit['company_name']])) ?>"></label>
                    <label><?= e(tr('profile.language_label')) ?><select name="document_language"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>" <?= $v===$userLanguage?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    <label><?= e(tr('common.description')) ?><textarea name="document_description" rows="3"></textarea></label>
                    <?= filePickerHtml('user_document') ?>
                    <button class="primary" name="action" value="upload_document"><?= e(tr('applications.save_document')) ?></button>
                </form>
            </div>
            <div class="log-timeline application-documents">
                <?php foreach($applicationDocuments as $doc): ?><article draggable="true" data-download-url="/?page=document_download&id=<?= (int)$doc['id'] ?>">
                    <div><strong><a class="record-link" href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></a></strong><span><?= e(documentPurposeLabel((string)$doc['purpose'], $userLanguage)) ?> · <?= e(displayDateTime($doc['created_at'], $currentUser)) ?> · <?= number_format(((int)$doc['file_size']) / 1024, 1) ?> KB</span></div>
                    <small><?= e($doc['scope'] === 'profile' ? tr('profile.master_data') . ' · ' . $doc['original_filename'] : $doc['original_filename']) ?></small>
                    <?php if($doc['scope'] === 'profile'): ?><form method="post" class="actions" onsubmit="return confirm('<?= e(tr('applications.detach_document_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>"><input type="hidden" name="user_document_id" value="<?= (int)$doc['id'] ?>"><button name="action" value="detach_application_document"><?= e(tr('common.remove')) ?></button></form><?php else: ?><form method="post" class="actions" onsubmit="return confirm('<?= e(tr('applications.delete_document_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document"><?= e(tr('common.delete')) ?></button></form><?php endif; ?>
                </article><?php endforeach; ?>
                <?php if(!$applicationDocuments): ?><p class="empty"><?= e(tr('applications.no_documents')) ?></p><?php endif; ?>
            </div>
        </section>
        <script>
        (() => {
            document.querySelectorAll('[data-doc-select]').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = button.closest('form');
                    if (!form) return;
                    const checked = button.dataset.docSelect === 'all';
                    form.querySelectorAll('input[name="user_document_ids[]"]').forEach((input) => { input.checked = checked; });
                });
            });
            document.querySelectorAll('.application-documents [draggable="true"]').forEach((card) => {
                card.addEventListener('dragstart', (event) => {
                    const url = card.dataset.downloadUrl || card.querySelector('a')?.href || '';
                    const title = card.querySelector('strong')?.innerText || url;
                    event.dataTransfer?.setData('text/uri-list', new URL(url, location.origin).href);
                    event.dataTransfer?.setData('text/plain', title + '\n' + new URL(url, location.origin).href);
                });
            });
            document.querySelectorAll('[data-copy-target]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const target = document.getElementById(button.dataset.copyTarget || '');
                    if (!target) return;
                    const value = 'value' in target ? target.value : target.textContent;
                    if (!value) return;
                    try {
                        await navigator.clipboard.writeText(value);
                        button.textContent = <?= json_encode(tr('common.copied'), JSON_UNESCAPED_UNICODE) ?>;
                        setTimeout(() => { button.textContent = button.dataset.copyLabel || <?= json_encode(tr('common.copy'), JSON_UNESCAPED_UNICODE) ?>; }, 1200);
                    } catch {
                        target.focus();
                        target.select?.();
                    }
                });
                button.dataset.copyLabel = button.textContent || <?= json_encode(tr('common.copy'), JSON_UNESCAPED_UNICODE) ?>;
            });
        })();
        </script>
        <section class="contact-workspace" id="contacts">
            <section class="panel">
                <div class="section-head"><div><p class="eyebrow"><?= e(tr('applications.company_job_contact')) ?></p><h2><?= e($contactEdit ? tr('contacts.edit') : tr('contacts.create')) ?></h2></div><?php if($contactEdit): ?><a href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>#contacts"><?= e(tr('common.new')) ?></a><?php endif; ?></div>
                <form method="post" class="stack">
                    <input type="hidden" name="csrf" value="<?= csrfToken() ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$applicationEdit['id'] ?>">
                    <input type="hidden" name="contact_id" value="<?= (int)($contactEdit['id'] ?? 0) ?>">
                    <label><?= e(tr('contacts.belongs_to')) ?><select name="contact_company_id"><option value="<?= (int)$applicationEdit['company_id'] ?>" <?= (int)($contactEdit['company_id']??0)===(int)$applicationEdit['company_id']?'selected':'' ?>><?= e($applicationEdit['company_name']) ?> (<?= e(tr('applications.employer_client')) ?>)</option><?php if($applicationEdit['intermediary_company_id']): ?><option value="<?= (int)$applicationEdit['intermediary_company_id'] ?>" <?= (int)($contactEdit['company_id']??0)===(int)$applicationEdit['intermediary_company_id']?'selected':'' ?>><?= e($applicationEdit['intermediary_company_name']) ?> (<?= e(tr('companies.intermediary')) ?>)</option><?php endif; ?></select></label>
                    <div class="two"><label><?= e(tr('auth.first_name')) ?><input name="first_name" value="<?= e($contactEdit['first_name'] ?? '') ?>" required></label><label><?= e(tr('auth.last_name')) ?><input name="last_name" value="<?= e($contactEdit['last_name'] ?? '') ?>" required></label></div>
                    <div class="two"><label><?= e(tr('contacts.position')) ?><input name="position" value="<?= e($contactEdit['position'] ?? '') ?>"></label><label><?= e(tr('contacts.department')) ?><input name="department" value="<?= e($contactEdit['department'] ?? '') ?>"></label></div>
                    <label><?= e(tr('auth.email')) ?><input type="email" name="contact_email" value="<?= e($contactEdit['email'] ?? '') ?>"></label>
                    <div class="two"><label><?= e(tr('profile.phone')) ?><input name="phone" value="<?= e($contactEdit['phone'] ?? '') ?>"></label><label><?= e(tr('profile.mobile')) ?><input name="mobile" value="<?= e($contactEdit['mobile'] ?? '') ?>"></label></div>
                    <label>LinkedIn<input type="url" name="linkedin_url" value="<?= e($contactEdit['linkedin_url'] ?? '') ?>"></label>
                    <label><?= e(tr('profile.language_label')) ?><select name="preferred_language"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($contactEdit['preferred_language']??'')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                    <label><?= e(tr('common.comment')) ?><textarea name="contact_notes" rows="3"><?= e($contactEdit['notes'] ?? '') ?></textarea></label>
                    <label class="check"><input type="checkbox" name="set_primary" value="1" <?= !$applicationEdit['primary_contact_id'] || (int)$applicationEdit['primary_contact_id']===(int)($contactEdit['id']??0)?'checked':'' ?>> <?= e(tr('applications.use_as_primary_contact')) ?></label>
                    <button class="primary" name="action" value="save_contact"><?= e(tr('contacts.save_contact')) ?></button>
                </form>
            </section>
            <section class="panel"><h2><?= e(tr('applications.assigned_contacts')) ?></h2><div class="contact-list"><?php foreach($contacts as $contact): ?><article class="<?= (int)$applicationEdit['primary_contact_id']===(int)$contact['id']?'is-primary':'' ?> <?= $contactEdit && (int)$contactEdit['id']===(int)$contact['id']?'is-selected':'' ?>"><small><?= e($contact['contact_company_name']) ?> · <?= e(tr('companies.company')) ?><?php if((int)$contact['job_id']===(int)$applicationEdit['job_id']): ?> · <?= e(tr('reports.field.job')) ?><?php endif; ?><?php if((int)($contact['application_id'] ?? 0)===(int)$applicationEdit['id']): ?> · <?= e(tr('nav.applications')) ?><?php endif; ?></small><strong><a class="record-link" href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log"><?= e($contact['first_name'].' '.$contact['last_name']) ?></a></strong><span><?= e($contact['position'] ?: $contact['department']) ?></span><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><a class="button" href="/?page=applications&edit=<?= (int)$applicationEdit['id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log"><?= e(tr('contact_log.title')) ?></a></article><?php endforeach; ?><?php if(!$contacts): ?><p class="empty"><?= e(tr('applications.no_contacts')) ?></p><?php endif; ?></div></section>
            <?php if($contactEdit): ?><section class="panel contact-log contact-log-inline" id="contact-log"><div class="section-head"><div><p class="eyebrow"><?= e(tr('contact_log.title')) ?></p><h2><?= e($contactEdit['first_name'].' '.$contactEdit['last_name']) ?></h2></div></div><?= contactLogFormHtml($editLog, (int)$applicationEdit['id'], (int)$contactEdit['id'], $contactLogChannels, $contactLogStatuses) ?><?= contactLogTimelineHtml($contactLogs, $contactAttachments, $contactLogChannels, $contactLogStatuses, $currentUser, (int)$applicationEdit['id']) ?></section><?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if($appView === 'table'): ?><section class="panel table-wrap"><table><thead><tr><?= sfHeader('applications','title',tr('reports.field.job'),$appSf,$appPreserve) ?><?= sfHeader('applications','company',tr('companies.company'),$appSf,$appPreserve) ?><?= sfHeader('applications','status',tr('common.status'),$appSf,$appPreserve) ?><?= sfHeader('applications','channel',tr('applications.channel'),$appSf,$appPreserve) ?><?= sfHeader('applications','next_action',tr('applications.next_action'),$appSf,$appPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($apps as $app): $nextActionLabel = $nextActionOptions[(string)$app['next_action']] ?? (string)$app['next_action']; ?><tr class="<?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><td><strong><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form"><?= e($app['title']) ?></a></strong><small><?= e($app['applied_at'] ? displayDateTime($app['applied_at'], $currentUser) : '') ?></small></td><td><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?><small><?= e(tr('companies.by')) ?> <?= e($app['intermediary_company_name']) ?></small><?php endif; ?></td><td><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></td><td><?= e(applicationChannelOptions()[(string)$app['channel']] ?? (string)$app['channel']) ?></td><td><?= e($nextActionLabel) ?><?php if($app['next_action_at']): ?><small><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></small><?php endif; ?></td><td class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form"><?= e(tr('common.edit')) ?></a></td></tr><?php endforeach; ?><?php if(!$apps): ?><tr><td colspan="6" class="empty"><?= e(tr('common.no_results')) ?></td></tr><?php endif; ?></tbody></table></section><?php else: ?><section class="application-list"><?php foreach($apps as $app): $nextActionLabel = $nextActionOptions[(string)$app['next_action']] ?? (string)$app['next_action']; ?><article class="application-card <?= $applicationEdit && (int)$applicationEdit['id']===(int)$app['id']?'is-selected':'' ?>"><div class="job-top"><span class="badge"><?= e($applicationStatuses[$app['status']] ?? $app['status']) ?></span><?php if($app['next_action_at']): ?><span class="due"><?= e(displayDateTime($app['next_action_at'], $currentUser)) ?></span><?php endif; ?></div><h3><a href="/?page=jobs&edit=<?= (int)$app['job_id'] ?>#new"><?= e($app['title']) ?></a></h3><p class="company"><a href="/?page=companies&edit=<?= (int)$app['company_id'] ?>"><?= e($app['company_name']) ?></a><?php if($app['intermediary_company_name']): ?> · <?= e(tr('companies.by')) ?> <a href="/?page=companies&edit=<?= (int)$app['intermediary_company_id'] ?>"><?= e($app['intermediary_company_name']) ?></a><?php endif; ?></p><?php if($app['next_action']): ?><p><strong><?= e(tr('applications.next_action')) ?>:</strong> <?= e($nextActionLabel) ?></p><?php endif; ?><div class="actions"><a href="/?page=applications&edit=<?= (int)$app['id'] ?>#application-form"><?= e(tr('common.edit')) ?></a><form method="post" onsubmit="return confirm('<?= e(tr('applications.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="id" value="<?= (int)$app['id'] ?>"><button name="action" value="delete_application"><?= e(tr('common.delete')) ?></button></form></div></article><?php endforeach; ?><?php if(!$apps): ?><div class="panel empty"><h2><?= e(tr('applications.empty_title')) ?></h2><p><?= e(tr('applications.empty_hint')) ?></p><a class="button primary" href="/?page=jobs"><?= e(tr('applications.to_jobs')) ?></a></div><?php endif; ?></section><?php endif; ?>
    <?php elseif ($page === 'contacts'): ?>
        <?php
        $contactCompanyFilter=(int)($_GET['company_id'] ?? 0);
        $contactSfFields = ['name'=>['label'=>tr('contacts.contact'),'expr'=>'CONCAT(ct.last_name, " ", ct.first_name)'], 'company'=>['label'=>tr('companies.company'),'expr'=>'c.name'], 'reachable'=>['label'=>tr('contacts.reachable'),'expr'=>'CONCAT_WS(" ", ct.email, ct.phone, ct.mobile)'], 'crm'=>['label'=>tr('contacts.crm_reference'),'expr'=>'CONCAT_WS(" ", j.title, a.status)']];
        $contactSf = sfState('contacts', $contactSfFields, ['sort'=>'name','dir'=>'asc']);
        $contactPreserve = ['page'=>'contacts', 'company_id'=>$contactCompanyFilter ?: '', 'edit_contact'=>$_GET['edit_contact'] ?? ''];
        $contactCompany=$contactCompanyFilter ? dbOne($db,'SELECT id,name FROM companies WHERE id=? AND owner_user_id=? AND deleted_at IS NULL','ii',[$contactCompanyFilter,userId()]) : null;
        $contactEditId=(int)($_GET['edit_contact'] ?? 0);
        $contactEdit=$contactEditId>0 ? dbOne($db,'SELECT id, company_id, first_name, last_name, position, department, email, phone, mobile, linkedin_url, preferred_language, notes FROM contacts WHERE id=? AND owner_user_id=? AND deleted_at IS NULL','ii',[$contactEditId,userId()]) : null;
        $contactLogStatuses=contactLogStatusOptions();
        $contactLogChannels=contactLogChannelOptions();
        $contactLogs=$contactEdit ? dbAll($db,'SELECT id, contact_id, application_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE owner_user_id=? AND contact_id=? ORDER BY CASE status WHEN "open" THEN 1 WHEN "planned" THEN 2 WHEN "done" THEN 3 ELSE 4 END, COALESCE(follow_up_at, occurred_at) ASC','ii',[userId(),(int)$contactEdit['id']]) : [];
        $contactAttachments=$contactEdit ? contactLogAttachments($db, userId(), (int)$contactEdit['id']) : [];
        $editLogId=(int)($_GET['edit_log'] ?? 0);
        $editLog=$editLogId > 0 ? dbOne($db,'SELECT id, contact_id, application_id, channel, direction, status, subject, SUBSTRING(body,1,65535) body, occurred_at, follow_up_at, outcome FROM contact_logs WHERE id=? AND owner_user_id=? AND contact_id=?','iii',[$editLogId,userId(),(int)($contactEdit['id'] ?? 0)]) : null;
        $contactSql='SELECT ct.*, c.name company_name, j.title job_title, a.status application_status, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id) log_count, (SELECT COUNT(*) FROM contact_logs l WHERE l.contact_id=ct.id AND l.owner_user_id=ct.owner_user_id AND l.status IN ("planned","open")) open_log_count FROM contacts ct JOIN companies c ON c.id=ct.company_id LEFT JOIN jobs j ON j.id=ct.job_id LEFT JOIN applications a ON a.id=ct.application_id WHERE ct.owner_user_id=? AND ct.deleted_at IS NULL'; $contactTypes='i'; $contactVals=[userId()];
        if($contactCompanyFilter>0){ $contactSql.=' AND ct.company_id=?'; $contactTypes.='i'; $contactVals[]=$contactCompanyFilter; }
        $contactSql .= sfApplySql($contactSf, $contactSfFields, $contactTypes, $contactVals);
        $contactOrder = sfOrderSql($contactSf, $contactSfFields, 'name');
        $contactSql .= $contactOrder !== '' ? $contactOrder . ', ct.first_name' : ' ORDER BY ct.last_name, ct.first_name';
        $contactRows=dbAll($db,$contactSql,$contactTypes,$contactVals);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('nav.crm')) ?></p><h1><?= e(tr('contacts.title')) ?></h1></div><span><?= e(tr('common.entries_count', null, ['count' => (string) count($contactRows)])) ?></span></div>
        <div class="actions export-actions"><?= sfToolbar('contacts', $contactSf, ['page'=>'contacts', 'company_id'=>$contactCompanyFilter ?: ''], $contactSfFields) ?><a class="button" href="/?page=export_pdf&type=contacts">PDF</a></div>
        <?php if($contactCompany): ?><p class="filter-note"><?= e(tr('contacts.at_company')) ?> <a href="/?page=companies&edit=<?= (int)$contactCompany['id'] ?>"><?= e($contactCompany['name']) ?></a> · <a href="/?page=contacts"><?= e(tr('contacts.show_all')) ?></a></p><?php endif; ?>
        <div class="<?= $contactEdit ? 'split' : 'full-width' ?>"><?php if($contactEdit): ?><section class="panel"><h2><?= e(tr('contacts.edit')) ?></h2><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="contact_id" value="<?= (int)$contactEdit['id'] ?>"><label><?= e(tr('companies.company')) ?><select name="contact_company_id"><?php foreach($companies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)$contactEdit['company_id']===(int)$company['id']?'selected':'' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></label><div class="two"><label><?= e(tr('auth.first_name')) ?><input name="first_name" value="<?= e($contactEdit['first_name']) ?>" required></label><label><?= e(tr('auth.last_name')) ?><input name="last_name" value="<?= e($contactEdit['last_name']) ?>" required></label></div><div class="two"><label><?= e(tr('contacts.position')) ?><input name="position" value="<?= e($contactEdit['position']) ?>"></label><label><?= e(tr('contacts.department')) ?><input name="department" value="<?= e($contactEdit['department']) ?>"></label></div><label><?= e(tr('auth.email')) ?><input type="email" name="contact_email" value="<?= e($contactEdit['email']) ?>"></label><div class="two"><label><?= e(tr('profile.phone')) ?><input name="phone" value="<?= e($contactEdit['phone']) ?>"></label><label><?= e(tr('profile.mobile')) ?><input name="mobile" value="<?= e($contactEdit['mobile']) ?>"></label></div><label>LinkedIn<input type="url" name="linkedin_url" value="<?= e($contactEdit['linkedin_url']) ?>"></label><label><?= e(tr('profile.language_label')) ?><select name="preferred_language"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>" <?= ($contactEdit['preferred_language']??'')===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><label><?= e(tr('common.comment')) ?><textarea name="contact_notes" rows="4"><?= e($contactEdit['notes']) ?></textarea></label><div class="actions"><button class="primary" name="action" value="update_contact_global"><?= e(tr('contacts.save_contact')) ?></button><a class="button" href="/?page=contacts"><?= e(tr('common.close')) ?></a></div></form><hr><h2 id="contact-log"><?= e(tr('contact_log.title')) ?></h2><?= contactLogFormHtml($editLog, 0, (int)$contactEdit['id'], $contactLogChannels, $contactLogStatuses) ?><?= contactLogTimelineHtml($contactLogs, $contactAttachments, $contactLogChannels, $contactLogStatuses, $currentUser) ?></section><?php endif; ?><section class="panel table-wrap"><table><thead><tr><?= sfHeader('contacts','name',tr('contacts.contact'),$contactSf,$contactPreserve) ?><?= sfHeader('contacts','company',tr('companies.company'),$contactSf,$contactPreserve) ?><?= sfHeader('contacts','reachable',tr('contacts.reachable'),$contactSf,$contactPreserve) ?><?= sfHeader('contacts','crm',tr('contacts.crm_reference'),$contactSf,$contactPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($contactRows as $contact): ?><tr class="<?= $contactEdit && (int)$contactEdit['id']===(int)$contact['id']?'is-selected':'' ?>"><td><strong><?= e($contact['first_name'].' '.$contact['last_name']) ?></strong><small><?= e($contact['position'] ?: $contact['department']) ?></small></td><td><a href="/?page=companies&edit=<?= (int)$contact['company_id'] ?>"><?= e($contact['company_name']) ?></a></td><td><?php if($contact['email']): ?><a href="mailto:<?= e($contact['email']) ?>"><?= e($contact['email']) ?></a><?php endif; ?><small><?= e($contact['phone'] ?: $contact['mobile']) ?></small></td><td class="link-list"><span><?= e(tr('contacts.log_count_summary', null, ['open' => (string)(int)$contact['open_log_count'], 'total' => (string)(int)$contact['log_count']])) ?></span><?php if($contact['job_id']): ?><a href="/?page=jobs&edit=<?= (int)$contact['job_id'] ?>#new"><?= e($contact['job_title'] ?: tr('contacts.open_job')) ?></a><?php endif; ?><?php if($contact['application_id']): ?><a href="/?page=applications&edit=<?= (int)$contact['application_id'] ?>&contact=<?= (int)$contact['id'] ?>#contact-log"><?= e(tr('contacts.application_activities')) ?></a><?php endif; ?></td><td class="actions"><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>"><?= e(tr('common.edit')) ?></a><a href="/?page=contacts&edit_contact=<?= (int)$contact['id'] ?>#contact-log"><?= e(tr('contacts.new_entry')) ?></a></td></tr><?php endforeach; ?><?php if(!$contactRows): ?><tr><td colspan="5" class="empty"><?= e(tr('contacts.empty')) ?></td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'documents'): ?>
        <?php
        $documentTypes = dbAll($db, 'SELECT id, code, name_key FROM document_types ORDER BY id');
        $profileDocumentTypes = documentTypesForScope($documentTypes, 'profile');
        $userLanguage = normalizeLocale((string) ($currentUser['preferred_language'] ?? 'de-CH'));
        $documentTypeChoices = [];
        foreach ($profileDocumentTypes as $type) {
            $documentTypeChoices[(string)$type['code']] = documentTypeLabel((string)$type['code'], $userLanguage);
        }
        $docSfFields = [
            'title'=>['label'=>tr('documents.document'),'expr'=>'d.title'],
            'type'=>['label'=>tr('documents.type'),'expr'=>'dt.code', 'choices'=>$documentTypeChoices],
            'version'=>['label'=>tr('documents.version'),'expr'=>'CAST(d.version AS CHAR)'],
            'created_at'=>['label'=>tr('common.date'),'expr'=>'d.created_at'],
        ];
        $docSf = sfState('documents', $docSfFields, ['sort'=>'created_at','dir'=>'desc']);
        $docPreserve = ['page'=>'documents'];
        $docSql="SELECT d.*, dt.code type_code, dt.name_key type_name FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL"; $docTypes='i'; $docVals=[userId()];
        $docSql .= sfApplySql($docSf, $docSfFields, $docTypes, $docVals);
        $docOrder = sfOrderSql($docSf, $docSfFields, 'created_at');
        $docSql .= $docOrder !== '' ? $docOrder . ', d.title' : ' ORDER BY d.created_at DESC, d.title';
        $documents = dbAll($db, $docSql, $docTypes, $docVals);
        $editDocumentId = (int) ($_GET['edit_document'] ?? 0);
        $editDocument = $editDocumentId > 0 ? dbOne($db, "SELECT d.*, dt.code type_code FROM user_documents d JOIN document_types dt ON dt.id=d.document_type_id WHERE d.id=? AND d.user_id=? AND d.scope='profile' AND d.deleted_at IS NULL", 'ii', [$editDocumentId, userId()]) : null;
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('profile.master_data')) ?></p><h1><?= e(tr('documents.title')) ?></h1></div><span><?= count($documents) ?> <?= e(tr('common.versions')) ?></span></div>
        <div class="actions export-actions"><?= sfToolbar('documents', $docSf, $docPreserve, $docSfFields) ?><a class="button" href="/?page=export_pdf&type=documents">PDF</a></div>
        <div class="split"><section class="panel" id="document-editor"><h2><?= e($editDocument ? tr('documents.edit_document') : tr('documents.upload_document')) ?></h2><form method="post" enctype="multipart/form-data" class="stack"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="document_scope" value="profile"><?php if($editDocument): ?><input type="hidden" name="document_id" value="<?= (int)$editDocument['id'] ?>"><?php else: ?><label><?= e(tr('documents.new_version_of')) ?><select name="replace_document_id"><option value="0"><?= e(tr('documents.new_document')) ?></option><?php foreach($documents as $doc): if(!(int)$doc['is_current']) continue; ?><option value="<?= (int)$doc['id'] ?>"><?= e($doc['title']) ?> · v<?= (int)$doc['version'] ?></option><?php endforeach; ?></select></label><?php endif; ?><label><?= e(tr('documents.document_type')) ?><select name="document_type_id"><?php foreach($profileDocumentTypes as $type): ?><option value="<?= (int)$type['id'] ?>" <?= (int)($editDocument['document_type_id'] ?? 0)===(int)$type['id']?'selected':'' ?>><?= e(documentTypeLabel((string)$type['code'], $userLanguage)) ?></option><?php endforeach; ?></select></label><label><?= e(tr('common.title')) ?><input name="document_title" required placeholder="<?= e(tr('documents.title_placeholder_profile')) ?>" value="<?= e($editDocument['title'] ?? '') ?>"></label><label><?= e(tr('profile.language_label')) ?><select name="document_language"><option value=""><?= e(tr('common.not_selected')) ?></option><?php foreach(documentLanguageChoices() as $v=>$l): ?><option value="<?= e($v) ?>" <?= (string)($editDocument['language_code'] ?? $userLanguage)===$v?'selected':'' ?>><?= e($l) ?></option><?php endforeach; ?></select></label><div class="two"><label><?= e(tr('documents.valid_from')) ?><input type="date" name="valid_from" value="<?= e($editDocument['valid_from'] ?? '') ?>"></label><label><?= e(tr('documents.valid_until')) ?><input type="date" name="valid_until" value="<?= e($editDocument['valid_until'] ?? '') ?>"></label></div><label><?= e(tr('common.description')) ?><textarea name="document_description" rows="3"><?= e($editDocument['description'] ?? '') ?></textarea></label><?php if($editDocument): ?><div class="actions"><button class="primary" name="action" value="update_document"><?= e(tr('common.save_changes')) ?></button><a class="button" href="/?page=documents"><?= e(tr('documents.upload_new')) ?></a><a class="button" href="/?page=document_download&id=<?= (int)$editDocument['id'] ?>"><?= e(tr('common.download')) ?></a></div><p class="meta-line"><?= e(tr('documents.replace_file_hint')) ?></p><?php else: ?><?= filePickerHtml('user_document') ?><button class="primary" name="action" value="upload_document"><?= e(tr('common.save')) ?></button><?php endif; ?></form></section>
        <section class="panel table-wrap"><table><thead><tr><?= sfHeader('documents','title',tr('documents.document'),$docSf,$docPreserve) ?><?= sfHeader('documents','type',tr('documents.type'),$docSf,$docPreserve) ?><?= sfHeader('documents','version',tr('documents.version'),$docSf,$docPreserve) ?><?= sfHeader('documents','created_at',tr('common.date'),$docSf,$docPreserve) ?><th><?= e(tr('common.actions')) ?></th></tr></thead><tbody><?php foreach($documents as $doc): ?><tr class="<?= ((int)$doc['is_current'] ? 'is-selected ' : '') . ($editDocument && (int)$editDocument['id']===(int)$doc['id'] ? 'is-selected' : '') ?>"><td><strong><a class="record-link" href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor"><?= e($doc['title']) ?></a></strong><small><?= e($doc['original_filename']) ?></small></td><td><?= e(documentTypeLabel((string)$doc['type_code'], $userLanguage)) ?><small><?= e($doc['language_code']) ?></small></td><td>v<?= (int)$doc['version'] ?><?= (int)$doc['is_current'] ? ' · ' . e(tr('common.current')) : '' ?></td><td><?= e(displayDateTime($doc['created_at'], $currentUser)) ?><small><?= number_format(((int)$doc['file_size']) / 1024, 1) ?> KB</small></td><td class="actions"><a href="/?page=documents&edit_document=<?= (int)$doc['id'] ?>#document-editor"><?= e(tr('common.edit')) ?></a><a href="/?page=document_download&id=<?= (int)$doc['id'] ?>"><?= e(tr('common.download')) ?></a><form method="post" onsubmit="return confirm('<?= e(tr('documents.delete_confirm')) ?>')"><input type="hidden" name="csrf" value="<?= csrfToken() ?>"><input type="hidden" name="document_return" value="documents"><input type="hidden" name="id" value="<?= (int)$doc['id'] ?>"><button name="action" value="delete_document"><?= e(tr('common.delete')) ?></button></form></td></tr><?php endforeach; ?><?php if(!$documents): ?><tr><td colspan="5" class="empty"><?= e(tr('documents.empty')) ?></td></tr><?php endif; ?></tbody></table></section></div>
    <?php elseif ($page === 'help'): ?>
        <?php
        $helpTopics = localizedHelpTopics($appLocale);
        $helpCategories = array_values(array_unique(array_map(static fn(array $topic): string => $topic['category'], $helpTopics)));
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('support.title')) ?></p><h1><?= e(tr('nav.help')) ?></h1></div><span><?= e(tr('help.topic_count', null, ['count' => count($helpTopics)])) ?></span></div>
        <section class="panel help-hero">
            <div>
                <p class="eyebrow"><?= e(tr('help.hero_eyebrow')) ?></p>
                <h2><?= e(tr('help.hero_title')) ?></h2>
                <p><?= e(tr('help.hero_intro')) ?></p>
            </div>
            <div class="help-search-box">
                <label><?= e(tr('help.search_label')) ?><input id="help-search" placeholder="<?= e(tr('help.search_placeholder')) ?>"></label>
                <div class="help-search-actions">
                    <button type="button" data-help-search-submit><?= e(tr('help.search_button')) ?></button>
                    <button type="button" data-help-reset><?= e(tr('help.reset_button')) ?></button>
                </div>
                <p class="help-search-status" id="help-search-status" aria-live="polite"><?= e(tr('help.search_status_initial')) ?></p>
                <div class="help-filter-chips" aria-label="<?= e(tr('help.categories_label')) ?>">
                    <?php foreach($helpCategories as $category): ?><button type="button" data-help-chip="<?= e($category) ?>"><?= e($category) ?></button><?php endforeach; ?>
                </div>
            </div>
        </section>
        <section class="help-flow" aria-label="<?= e(tr('help.flow_label')) ?>">
            <a href="/?page=profile"><span>1</span><strong><?= e(tr('help.flow.profile.title')) ?></strong><small><?= e(tr('help.flow.profile.text')) ?></small></a>
            <a href="/?page=job_platform_search"><span>2</span><strong><?= e(tr('help.flow.search.title')) ?></strong><small><?= e(tr('help.flow.search.text')) ?></small></a>
            <a href="/?page=jobs#new"><span>3</span><strong><?= e(tr('help.flow.import.title')) ?></strong><small><?= e(tr('help.flow.import.text')) ?></small></a>
            <a href="/?page=applications"><span>4</span><strong><?= e(tr('help.flow.apply.title')) ?></strong><small><?= e(tr('help.flow.apply.text')) ?></small></a>
            <a href="/?page=pendents"><span>5</span><strong><?= e(tr('help.flow.follow.title')) ?></strong><small><?= e(tr('help.flow.follow.text')) ?></small></a>
            <a href="/?page=reports"><span>6</span><strong><?= e(tr('help.flow.dossier.title')) ?></strong><small><?= e(tr('help.flow.dossier.text')) ?></small></a>
        </section>
        <section class="help-quickstart panel">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('help.quick_eyebrow')) ?></p><h2><?= e(tr('help.quick_title')) ?></h2></div></div>
            <div class="three">
                <article><h3><?= e(tr('help.quick.search.title')) ?></h3><p><?= e(tr('help.quick.search.body')) ?></p><a href="/?page=job_platform_search"><?= e(tr('help.quick.search.link')) ?></a></article>
                <article><h3><?= e(tr('help.quick.apply.title')) ?></h3><p><?= e(tr('help.quick.apply.body')) ?></p><a href="/?page=applications"><?= e(tr('help.quick.apply.link')) ?></a></article>
                <article><h3><?= e(tr('help.quick.track.title')) ?></h3><p><?= e(tr('help.quick.track.body')) ?></p><a href="/?page=reminders"><?= e(tr('help.quick.track.link')) ?></a></article>
            </div>
        </section>
        <section class="panel license-panel">
            <div class="section-head"><div><p class="eyebrow"><?= e(tr('help.license_eyebrow')) ?></p><h2><?= e(tr('help.license_title')) ?></h2></div><span><?= e(tr('help.license_badge')) ?></span></div>
            <p><?= e(tr('help.license_body1')) ?></p>
            <p><?= e(tr('help.license_body2')) ?></p>
        </section>
        <section class="help-grid" id="help-topics">
            <?php foreach($helpTopics as $index => $topic): ?>
                <article class="panel help-topic" data-help-category="<?= e($topic['category']) ?>" data-help-search="<?= e(mb_strtolower($topic['category'].' '.$topic['audience'].' '.$topic['title'].' '.$topic['summary'].' '.$topic['keywords'])) ?>">
                    <div class="help-topic-head"><span><?= (int)$index + 1 ?></span><div><small><?= e($topic['category']) ?> · <?= e($topic['audience']) ?></small><h2><?= e($topic['title']) ?></h2></div></div>
                    <p><?= e($topic['summary']) ?></p>
                    <h3><?= e(tr('help.steps_title')) ?></h3>
                    <ol><?php foreach($topic['steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol>
                    <h3><?= e(tr('help.tips_title')) ?></h3>
                    <ul><?php foreach($topic['tips'] as $tip): ?><li><?= e($tip) ?></li><?php endforeach; ?></ul>
                    <div class="actions"><?php foreach($topic['links'] as $link): ?><a class="button" href="<?= e($link[1]) ?>"><?= e($link[0]) ?></a><?php endforeach; ?></div>
                </article>
            <?php endforeach; ?>
        </section>
        <section class="panel help-empty" id="help-empty" hidden><h2><?= e(tr('help.empty_title')) ?></h2><p><?= e(tr('help.empty_hint')) ?></p></section>
        <script>
        (() => {
            const labels = {
                topic: <?= json_encode(tr('help.status.topic'), JSON_UNESCAPED_UNICODE) ?>,
                topics: <?= json_encode(tr('help.status.topics'), JSON_UNESCAPED_UNICODE) ?>,
                visible: <?= json_encode(tr('help.status.visible'), JSON_UNESCAPED_UNICODE) ?>,
                search: <?= json_encode(tr('help.status.search'), JSON_UNESCAPED_UNICODE) ?>,
                jump: <?= json_encode(tr('help.status.jump'), JSON_UNESCAPED_UNICODE) ?>
            };
            const search = document.getElementById('help-search');
            const grid = document.getElementById('help-topics');
            const topics = Array.from(document.querySelectorAll('.help-topic'));
            const empty = document.getElementById('help-empty');
            const status = document.getElementById('help-search-status');
            const searchSubmit = document.querySelector('[data-help-search-submit]');
            const resetButton = document.querySelector('[data-help-reset]');
            const chips = Array.from(document.querySelectorAll('[data-help-chip]'));
            let highlightedCategory = '';
            const normalize = (value) => (value || '').trim().toLocaleLowerCase('de-CH').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            const visibleTopics = () => topics.filter((topic) => !topic.hidden);
            const scrollToResults = () => {
                const target = visibleTopics()[0] || empty || grid;
                target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };
            const apply = () => {
                const term = normalize(search?.value || '');
                let visible = 0;
                topics.forEach((topic) => {
                    const textMatch = term === '' || normalize(topic.dataset.helpSearch || '').includes(term);
                    const show = textMatch;
                    topic.hidden = !show;
                    topic.classList.toggle('is-highlighted', show && highlightedCategory !== '' && topic.dataset.helpCategory === highlightedCategory);
                    if (show) visible++;
                });
                if (empty) empty.hidden = visible !== 0;
                if (status) {
                    const bits = [];
                    const rawTerm = (search?.value || '').trim();
                    if (rawTerm !== '') bits.push(`${labels.search}: ${rawTerm}`);
                    if (highlightedCategory !== '') bits.push(`${labels.jump}: ${highlightedCategory}`);
                    status.textContent = `${visible} ${visible === 1 ? labels.topic : labels.topics} ${labels.visible}${bits.length ? ' · ' + bits.join(' · ') : ''}.`;
                }
            };
            search?.addEventListener('input', apply);
            search?.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                apply();
                scrollToResults();
            });
            searchSubmit?.addEventListener('click', () => {
                apply();
                scrollToResults();
            });
            resetButton?.addEventListener('click', () => {
                highlightedCategory = '';
                if (search) search.value = '';
                chips.forEach((item) => item.classList.remove('is-active'));
                apply();
                search?.focus();
            });
            chips.forEach((chip) => chip.addEventListener('click', () => {
                const nextCategory = chip.dataset.helpChip || '';
                highlightedCategory = highlightedCategory === nextCategory ? '' : nextCategory;
                chips.forEach((item) => item.classList.toggle('is-active', highlightedCategory !== '' && item.dataset.helpChip === highlightedCategory));
                apply();
                const target = highlightedCategory === '' ? grid : topics.find((topic) => !topic.hidden && topic.dataset.helpCategory === highlightedCategory);
                (target || grid)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }));
            apply();
        })();
        </script>
    <?php elseif ($page === 'about'): ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('nav.about')) ?></p><h1>JeMa Jobs</h1></div><span><?= e($config['app_name'] ?? 'JeMa Jobs') ?></span></div>
        <section class="panel about-panel">
            <p class="version-number"><?= e(tr('common.version')) ?> <?= e($appDisplayVersion) ?></p>
            <h2><?= e(tr('about.product_title')) ?></h2>
            <p><?= e(tr('about.summary')) ?></p>
            <div class="two">
                <article><h3><?= e(tr('about.creator')) ?></h3><p>Markus Lauber<br><a href="mailto:Markus@Lauber.online">Markus@Lauber.online</a></p></article>
                <article><h3><?= e(tr('about.status')) ?></h3><p><?= e(tr('about.production_status')) ?></p></article>
            </div>
        </section>
    <?php elseif ($page === 'audit'): ?>
        <?php
        $logs=dbAll($db,'SELECT id, action, entity_type, entity_id, created_at FROM audit_log WHERE user_id=? ORDER BY created_at DESC LIMIT 100','i',[userId()]);
        $auditSfFields = [
            'created_at'=>['label'=>tr('common.time')],
            'action'=>['label'=>tr('audit.action')],
            'entity_type'=>['label'=>tr('common.area')],
        ];
        $auditSf = sfState('audit', $auditSfFields, ['sort'=>'created_at','dir'=>'desc']);
        $auditPreserve = ['page'=>'audit'];
        $logs = sfApplyRows($logs, $auditSf, $auditSfFields);
        ?>
        <div class="page-head"><div><p class="eyebrow"><?= e(tr('audit.section')) ?></p><h1><?= e(tr('audit.title')) ?></h1></div><span><?= e(tr('audit.last_count', null, ['count' => (string) count($logs)])) ?></span></div><section class="panel table-wrap"><div class="actions export-actions"><?= sfToolbar('audit', $auditSf, $auditPreserve, $auditSfFields) ?></div><table><thead><tr><?= sfHeader('audit','created_at',tr('common.time'),$auditSf,$auditPreserve) ?><?= sfHeader('audit','action',tr('audit.action'),$auditSf,$auditPreserve) ?><?= sfHeader('audit','entity_type',tr('common.area'),$auditSf,$auditPreserve) ?></tr></thead><tbody><?php foreach($logs as $log): ?><tr><td><?= e(displayDateTime($log['created_at'], $currentUser)) ?></td><td><?= e($log['action']) ?></td><td><?= e($log['entity_type']) ?></td></tr><?php endforeach; ?></tbody></table></section>
    <?php endif; ?>
<?php endif; ?>
</main>
<footer>JeMa Jobs · Version <?= e($appDisplayVersion) ?> · <?= e(tr('footer.private')) ?></footer>
<script src="/assets/qrcode.min.js" defer></script>
<script src="/assets/totp-qr.js" defer></script>
<script>
(() => {
    document.querySelectorAll('[data-file-picker]').forEach((input) => {
        const wrapper = input.closest('.file-picker');
        const name = wrapper?.querySelector('[data-file-picker-name]');
        const button = wrapper?.querySelector('.file-picker-button');
        if (!wrapper || !name || !button) return;
        button.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            const files = Array.from(input.files || []).map((file) => file.name);
            name.textContent = files.length ? files.join(', ') : (name.dataset.empty || '');
        });
    });
})();
(() => {
    const placeMenu = (menu) => {
        if (!menu.open) return;
        const button = menu.querySelector('.sf-button');
        const form = menu.querySelector('.sf-form');
        if (!button || !form) return;
        const rect = button.getBoundingClientRect();
        const width = Math.min(290, window.innerWidth - 32);
        const left = Math.max(16, Math.min(rect.right - width, window.innerWidth - width - 16));
        const top = Math.min(rect.bottom + 8, window.innerHeight - 80);
        form.style.setProperty('--sf-menu-left', `${left}px`);
        form.style.setProperty('--sf-menu-top', `${top}px`);
    };
    const closeOtherMenus = (current) => {
        document.querySelectorAll('.sf-menu[open]').forEach((menu) => {
            if (menu !== current) menu.removeAttribute('open');
        });
    };
    document.addEventListener('toggle', (event) => {
        const menu = event.target;
        if (!(menu instanceof HTMLElement) || !menu.classList.contains('sf-menu')) return;
        if (menu.open) {
            closeOtherMenus(menu);
            placeMenu(menu);
        }
    }, true);
    window.addEventListener('resize', () => document.querySelectorAll('.sf-menu[open]').forEach(placeMenu));
    window.addEventListener('scroll', () => document.querySelectorAll('.sf-menu[open]').forEach(placeMenu), true);
})();
(() => {
    const modal = document.querySelector('[data-context-help-modal]');
    const openButton = document.querySelector('[data-context-help-open]');
    if (!modal || !openButton) return;
    const helpBar = document.querySelector('[data-context-help-container]');
    const host = document.querySelector('.container > .hero, .container > .page-head, .container > .auth-card');
    if (helpBar && host) {
        host.classList.add('context-help-host');
        host.appendChild(helpBar);
    }
    const dialog = modal.querySelector('.context-help-dialog');
    const open = () => {
        modal.hidden = false;
        document.body.classList.add('modal-open');
        dialog?.focus();
    };
    const close = () => {
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        openButton.focus();
    };
    openButton.addEventListener('click', open);
    modal.querySelectorAll('[data-context-help-close]').forEach((button) => button.addEventListener('click', close));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) close();
    });
})();
(() => {
    document.querySelectorAll('form[data-progress-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            if (!form.checkValidity()) {
                return;
            }
            const box = form.querySelector('[data-progress-box]');
            const bar = form.querySelector('[data-progress-bar]');
            const text = form.querySelector('[data-progress-text]');
            const button = form.querySelector('[data-progress-button]');
            const steps = (form.dataset.progressSteps || '').split('|').filter(Boolean);
            const fallbackSteps = <?= json_encode([tr('progress.prepare'), tr('progress.distribute'), tr('progress.create_links'), tr('progress.save')], JSON_UNESCAPED_UNICODE) ?>;
            const messages = steps.length ? steps : fallbackSteps;
            let progress = 18;
            let step = 0;
            if (box) box.hidden = false;
            if (button) {
                button.disabled = true;
                button.textContent = form.dataset.progressButtonText || <?= json_encode(tr('progress.working'), JSON_UNESCAPED_UNICODE) ?>;
            }
            if (bar) bar.style.width = `${progress}%`;
            if (text) text.textContent = messages[0];
            window.setInterval(() => {
                progress = Math.min(progress + 18, 92);
                step = Math.min(step + 1, messages.length - 1);
                if (bar) bar.style.width = `${progress}%`;
                if (text) text.textContent = messages[step];
            }, 450);
        });
    });
})();
</script>
</body></html>
