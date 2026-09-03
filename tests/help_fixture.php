<?php
declare(strict_types=1);
require __DIR__.'/help_test_support.php';
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $value): string { return strtolower($value); }
}
$_SESSION['locale']=$_GET['lang'] ?? 'de-CH';
$appLocale=currentLocale();
$contexts=localizedContextHelpTopics($appLocale);
$contextHelp=$contexts[$_GET['context'] ?? 'help'] ?? $contexts['help'];
$start=strpos($helpSource,'<?php elseif ($page === \'help\'): ?>');
$end=strpos($helpSource,'<?php elseif ($page === \'about\'): ?>',$start);
$template=substr($helpSource,$start+strlen('<?php elseif ($page === \'help\'): ?>'),$end-$start-strlen('<?php elseif ($page === \'help\'): ?>'));
$contextStart=strpos($helpSource,'<?php if ($contextHelp): ?>');
$contextEnd=strpos($helpSource,'<?php if ($page === \'login\'',$contextStart);
$contextTemplate=substr($helpSource,$contextStart,$contextEnd-$contextStart);
$scriptStart=strrpos(substr($helpSource,0,strpos($helpSource,"const modal = document.querySelector('[data-context-help-modal]')")),'<script>');
$scriptEnd=strpos($helpSource,'</script>',$scriptStart);
$contextScript=substr($helpSource,$scriptStart,$scriptEnd-$scriptStart+9);
?><!doctype html><html lang="<?= e($appLocale) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Help fixture</title><link rel="stylesheet" href="../public/assets/app.css"><link rel="stylesheet" href="../public/assets/layout.css"></head><body><main class="container"><?php
eval('?>'.$contextTemplate);
eval('?>'.$template);
?></main><?php eval('?>'.$contextScript); ?></body></html>
