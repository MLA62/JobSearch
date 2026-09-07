<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');

$checks = [
    'replacement must reference current owned document' => 'scope=? AND is_current=1 AND deleted_at IS NULL',
    'version is derived from complete series' => 'SELECT COALESCE(MAX(version),0) max_version FROM user_documents',
    'next version increments maximum' => '$version = ((int)($existing[\'max_version\'] ?? 0)) + 1',
    'database transaction starts after file upload' => '$db->begin_transaction();',
    'database transaction commits' => '$db->commit();',
    'database transaction rolls back' => '$db->rollback();',
    'failed database write removes uploaded file' => '@unlink($candidate)',
    'profile replacement selector carries metadata' => 'data-document-version-select',
    'valid-from metadata is carried' => 'data-valid-from=',
    'valid-until metadata is carried' => 'data-valid-until=',
    'application upload exposes validity dates' => '<input type="date" name="valid_from">',
    'replacement metadata hydrator exists' => 'const hydrate = () =>',
    'rich description editor receives inherited value' => "dispatchEvent(new Event('jema:richtext-load'))",
    'new document still requires a title' => 'fields.document_title.required = !replacing',
    'replacement title remains in same version series' => 'fields.document_title.readOnly = Boolean(replacing)',
    'replacement type remains in same version series' => 'fields.document_type_id.disabled = Boolean(replacing)',
];

foreach ($checks as $label => $needle) {
    if (!str_contains($source, $needle)) throw new RuntimeException('Missing: ' . $label);
    echo 'PASS ' . $label . "\n";
}

if (str_contains($source, '$version = (int) $oldDoc[\'version\'] + 1')) {
    throw new RuntimeException('Branching from a stale version must not create a duplicate version number.');
}

echo "Document version flow checks passed.\n";
