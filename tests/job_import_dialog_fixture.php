<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/help_test_support.php';
helpLoadFunction('jobImportDialogHtml');
echo '<!doctype html><html lang="de-CH"><head><meta charset="UTF-8"></head><body><form method="post"><input type="hidden" name="csrf" value="test-csrf"><input type="hidden" name="job_url" value="https://example.test/job"><button name="action" value="prepare_ai_job_import">Übernehmen</button></form>';
echo jobImportDialogHtml('de-CH');
echo '</body></html>';
