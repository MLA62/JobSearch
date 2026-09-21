<?php
declare(strict_types=1);

$source=file_get_contents(__DIR__.'/../public/index.php');
if (!is_string($source)) throw new RuntimeException('Application source unavailable.');
$start=strpos($source,'<div class="online-assistant">');
$end=$start===false ? false : strpos($source,'</div>',$start);
if ($start===false || $end===false) throw new RuntimeException('Online assistant not found.');
$assistant=substr($source,$start,$end-$start);
if (substr_count($source,'value="submit_online_application"')!==1
    || !str_contains($assistant,'value="submit_online_application"')
    || strpos($assistant,"tr('applications.open_webform')")>strpos($assistant,'value="submit_online_application"')) {
    throw new RuntimeException('Online submission button is not next to the web form or appears more than once.');
}
$guard="in_array(\$applicationEdit['status'], ['draft','ready'], true) && empty(\$applicationEdit['applied_at'])";
if (substr_count($source,$guard)!==2 || !str_contains($assistant,$guard)) {
    throw new RuntimeException('Online submission button and hint must be hidden after submission.');
}
if (!str_contains($source,"if (!in_array(\$old['status'], ['draft','ready'], true) || !empty(\$old['applied_at']))")) {
    throw new RuntimeException('Server-side submission guard was removed.');
}
echo "PASS online submission action and hint are adjacent to web form and shown only before submission\n";
