<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$php = file_get_contents($root . '/public/index.php');
$schema = file_get_contents($root . '/sql/jobsearch/01_schema.sql');
$migration = file_get_contents($root . '/sql/jobsearch/15_soft_delete_uniqueness.sql');

$softDeleteIndexes = [
    'uq_users_email' => '(email, active_unique)',
    'uq_company_relationship' => '(owner_user_id, intermediary_company_id, client_company_id, relationship_type, active_unique)',
    'uq_job_platform_name' => '(name, active_unique)',
    'uq_job_source_external' => '(source_id, external_id, active_unique)',
    'uq_application_user_job' => '(user_id, job_id, active_unique)',
];

foreach ($softDeleteIndexes as $index => $columns) {
    if (!str_contains($schema, 'UNIQUE KEY ' . $index . ' ' . $columns)) {
        throw new RuntimeException('Base schema missing active-only uniqueness for ' . $index);
    }
    if (!str_contains($migration, 'DROP INDEX ' . $index) || !str_contains($migration, 'ADD UNIQUE KEY ' . $index . ' ' . $columns)) {
        throw new RuntimeException('Migration missing active-only uniqueness for ' . $index);
    }
}

$runtimeTables = [
    'users' => ['email'],
    'company_relationships' => ['owner_user_id', 'intermediary_company_id', 'client_company_id', 'relationship_type'],
    'job_platforms' => ['name'],
    'jobs' => ['source_id', 'external_id'],
    'applications' => ['user_id', 'job_id'],
];
foreach ($runtimeTables as $table => $columns) {
    $call = "ensureSoftDeleteUniqueIndex(\$db, '" . $table . "'";
    if (!str_contains($php, $call)) throw new RuntimeException('Runtime migration missing table ' . $table);
}

$checks = [
    'generated active marker' => 'CASE WHEN `deleted_at` IS NULL THEN 1 ELSE NULL END',
    'migration lock' => "GET_LOCK('jema_soft_delete_unique_v1', 10)",
    'atomic active duplicate handling' => 'ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
    'no deleted application reactivation' => "UPDATE applications SET deleted_at=NULL",
    'storage error reference' => "applications.prepare_storage_failed",
    'text preparation error reference' => "applications.prepare_texts_failed",
    'application still opens after text failure' => "redirectAiFetch('/?page=applications&edit=' . \$applicationId . '#application-form')",
];

foreach ($checks as $label => $needle) {
    $present = str_contains($php, $needle);
    if ($label === 'no deleted application reactivation') {
        if ($present) throw new RuntimeException('Deleted applications must never be reactivated');
    } elseif (!$present) {
        throw new RuntimeException('Missing ' . $label);
    }
    echo 'PASS ' . $label . "\n";
}

echo "Soft-delete uniqueness contract passed\n";
