<?php
declare(strict_types=1);

$source = (string) file_get_contents(__DIR__ . '/../public/index.php');

function bulkCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS {$message}\n";
}

bulkCheck(str_contains($source, 'data-bulk-action="bulk_delete_jobs"'), 'Jobs expose the bulk-selection scope');
bulkCheck(str_contains($source, 'data-bulk-toolbar hidden') && str_contains($source, 'data-bulk-form'), 'A single shared bulk-delete toolbar is rendered');
bulkCheck(str_contains($source, "new Set()") && str_contains($source, "toggle.addEventListener('click'"), 'Selections are kept locally in the browser');
bulkCheck(str_contains($source, "actions.insertBefore(toggle, deleteForm)"), 'The selection button is placed in Actions');
bulkCheck(str_contains($source, "toggle.type = 'button'"), 'Selecting a job never submits or reloads the list');
bulkCheck(str_contains($source, "input.name = 'job_ids[]'"), 'All selected job IDs are sent in one request');
bulkCheck(!str_contains($source, '<th class="bulk-select-column">'), 'No additional selection column is rendered');
bulkCheck(str_contains($source, "if (\$action === 'bulk_delete_jobs')") && str_contains($source, '$db->begin_transaction()'), 'Bulk deletion is processed transactionally');
bulkCheck(substr_count($source, 'owner_user_id = ? AND deleted_at IS NULL') > 5, 'Deletion remains scoped to the signed-in owner');

echo "Job bulk-selection contract passed.\n";
