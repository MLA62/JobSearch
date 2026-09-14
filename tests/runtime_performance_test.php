<?php
declare(strict_types=1);

$source = (string)file_get_contents(__DIR__ . '/../public/index.php');

function performanceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

performanceCheck(str_contains($source, "runtime_schema_2_4_24"), 'Runtime schema work has a release marker');
performanceCheck(str_contains($source, "GET_LOCK('jema-runtime-schema', 5)"), 'Runtime schema work is concurrency protected');
performanceCheck(str_contains($source, 'if ($bootstrapSchemaRequired) {'), 'Schema checks are conditional');
performanceCheck(str_contains($source, 'INSERT IGNORE INTO app_migrations (migration_key)'), 'Successful schema work stores its marker');
performanceCheck(str_contains($source, "runtime_maintenance_2_4_24"), 'Seed and data migrations have a combined release marker');
performanceCheck(str_contains($source, "GET_LOCK('jema-runtime-maintenance', 5)"), 'Release maintenance is concurrency protected');
performanceCheck(substr_count($source, 'seedJobPlatforms($db);') === 1, 'Job platform seeds run only inside release schema work');
performanceCheck(!str_contains($source, "preg_split('/[\\R,;]+/u'"), 'Invalid newline character-class expressions are absent');
performanceCheck(substr_count($source, "preg_split('/(?:\\R|[,;])+/u'") === 2, 'Role and location splitting use valid newline alternatives');

$saveAction = strpos($source, "if (\$action === 'save_platform_search_criteria')");
$saveCall = strpos($source, 'savedJobSearchCriteria($db, userId(), $preference', $saveAction ?: 0);
$preferenceLoad = strpos($source, "\$preference = dbOne(\$db, 'SELECT * FROM user_preferences", $saveAction ?: 0);
performanceCheck($saveAction !== false && $preferenceLoad !== false && $saveCall !== false && $preferenceLoad < $saveCall, 'Manual criteria save loads its preference before use');

$parts = preg_split('/(?:\R|[,;])+/u', "Sales\r\nIT,CRM;Leitung") ?: [];
performanceCheck($parts === ['Sales', 'IT', 'CRM', 'Leitung'], 'Multiline criteria splitting works without warnings');

echo "Runtime performance contract passed.\n";
