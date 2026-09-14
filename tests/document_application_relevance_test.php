<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../public/index.php');
$schema = file_get_contents(__DIR__ . '/../sql/jobsearch/01_schema.sql');
$migration = file_get_contents(__DIR__ . '/../sql/jobsearch/18_document_application_relevance.sql');

$checks = [
    'base schema defaults new documents to not relevant' => str_contains($schema, 'is_application_relevant TINYINT(1) NOT NULL DEFAULT 0'),
    'runtime migration is idempotently ensured' => str_contains($source, "ensureColumn(\$db, 'user_documents', 'is_application_relevant', '`is_application_relevant` TINYINT(1) NOT NULL DEFAULT 0', 'is_current')"),
    'document form exposes the checkbox' => str_contains($source, 'type="checkbox" name="is_application_relevant" value="1"'),
    'new form is unchecked by default' => str_contains($source, "!empty(\$editDocument['is_application_relevant']) ? 'checked' : ''"),
    'profile upload reads checkbox as boolean' => str_contains($source, "\$isApplicationRelevant = \$scope === 'profile' && isset(\$_POST['is_application_relevant']) ? 1 : 0;"),
    'upload persists relevance' => str_contains($source, 'is_current, is_application_relevant) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)'),
    'metadata update persists relevance' => str_contains($source, 'valid_until=?, is_application_relevant=? WHERE id=? AND user_id=?'),
    'replacement option carries relevance' => str_contains($source, 'data-application-relevant="<?= (int)$doc[\'is_application_relevant\'] ?>"'),
    'replacement hydrator restores checkbox' => str_contains($source, "is_application_relevant: option.dataset.applicationRelevant || '0'"),
    'application suggestions require relevance' => str_contains($source, "d.scope='profile' AND d.is_current=1 AND d.is_application_relevant=1 AND d.deleted_at IS NULL"),
    'suggestions remain owner scoped' => str_contains($source, 'WHERE d.user_id=? AND d.scope=\'profile\''),
    'existing attachments remain sourced from join table' => str_contains($source, 'FROM application_documents ad JOIN user_documents d ON d.id=ad.user_document_id'),
    'reports expose the new field' => str_contains($source, "'is_application_relevant'=>tr('documents.application_relevant')"),
    'admin AI field contract includes relevance' => str_contains($source, "'version','is_current','is_application_relevant'"),
    'explicit SQL migration exists' => str_contains($migration, 'ADD COLUMN is_application_relevant TINYINT(1) NOT NULL DEFAULT 0 AFTER is_current'),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        throw new RuntimeException('Missing: ' . $label);
    }
    echo 'PASS ' . $label . PHP_EOL;
}

if (preg_match('/applicationProfileDocuments.*?WHERE d\.user_id=\?[^\n]+d\.deleted_at IS NULL/s', $source, $match) !== 1
    || !str_contains($match[0], 'd.is_current=1')
    || !str_contains($match[0], 'd.is_application_relevant=1')) {
    throw new RuntimeException('Application suggestion query must combine owner, current, relevant and non-deleted constraints.');
}

echo "Document application relevance checks passed.\n";
