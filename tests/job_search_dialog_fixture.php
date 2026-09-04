<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/help_test_support.php';
helpLoadFunction('verifiedSearchScript');
echo '<!doctype html><html lang="de-CH"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><form method="post"><input type="hidden" name="csrf" value="test-csrf"><input name="search_query" value="Sales"><button name="action" value="search_ai_jobs">Suchen</button></form>';
echo verifiedSearchScript('de-CH');
echo '</body></html>';
