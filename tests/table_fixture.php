<?php
declare(strict_types=1);
$source = file_get_contents(getenv('JEMA_TEST_SOURCE') ?: __DIR__.'/../public/index.php');
foreach (['sfHeader', 'sfHiddenInputs', 'applicationWorkflowView'] as $name) {
    preg_match('/^function '.$name.'\\(.*?(?=^function )/ms', $source, $match);
    eval(trim($match[0]));
}
function tr($key, $locale = null, $replace = []) {
    $labels = ['companies.company'=>'Firma', 'companies.address_phone'=>'Adresse / Telefon',
        'companies.role_intermediary'=>'Rolle', 'companies.links'=>'Links', 'companies.intermediary'=>'Vermittler',
        'companies.direct_none'=>'Direkt', 'companies.by'=>'Via', 'companies.mediates'=>'Vermittelt',
        'nav.jobs'=>'Jobs', 'nav.applications'=>'Bewerbungen', 'nav.contacts'=>'Kontakte', 'nav.calendar'=>'Kalender',
        'common.actions'=>'Aktionen', 'common.edit'=>'Bearbeiten', 'common.delete'=>'Loeschen', 'common.status'=>'Status',
        'applications.sent_at'=>'Gesendet am', 'reports.field.job'=>'Job', 'applications.channel'=>'Kanal',
        'applications.next_action'=>'Naechster Kalendereintrag', 'applications.workflow_date'=>'Workflowdatum', 'applications.job_room_status'=>'Job-Room',
        'applications.job_room_recorded'=>'Im Job-Room erfasst', 'applications.job_room_interview'=>'Vorstellungsgespraech',
        'job_room_helper.result.open'=>'Noch offen', 'job_room_helper.result.hired'=>'Anstellung', 'job_room_helper.result.rejected'=>'Absage',
        'sf.title'=>'Sortieren / Filtern', 'sf.filter'=>'Filter', 'sf.sorting'=>'Sortierung',
        'sf.none'=>'Keine', 'sf.asc'=>'Aufsteigend', 'sf.desc'=>'Absteigend', 'sf.apply'=>'Anwenden', 'sf.clear_filter'=>'Zuruecksetzen'];
    return $labels[$key] ?? $key;
}
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrfToken() { return 'fixture-only'; }
function displayDateTime($s, $user, $time=true) { return $s ? date($time ? 'd.m.Y, H:i' : 'd.m.Y', strtotime($s)) : ''; }
function applicationStatusOptions() { global $applicationStatuses; return $applicationStatuses; }
function applicationNextActionLabel($value) { return $value; }
function applicationChannelOptions() { return ['email'=>'E-Mail','website'=>'Onlineformular']; }
$currentUser = []; $edit = null; $applicationEdit = ['id'=>0,'job_room_interview'=>1,'job_room_result'=>'open'];
$companySf = $companyPreserve = $appSf = $appPreserve = [];
$applicationStatuses = ['sent'=>'Gesendet']; $nextActionOptions = ['follow_up'=>'Nachfassen'];
$companyRows = [];
foreach (['Beispiel Vermittlung AG', 'Muster Direkt AG', 'Test Personal AG'] as $i=>$name) {
    $companyRows[] = ['id'=>$i+1,'name'=>$name,'website'=>'https://example.test',
        'address_line1'=>'Teststrasse 23','address_line2'=>'','postal_code'=>'4500','city'=>'Solothurn','phone'=>'+41 32 000 00 00',
        'is_intermediary'=>$i!==1,'mediated_clients'=>'','mediated_by'=>'','job_count'=>1,'application_count'=>2,'contact_count'=>3];
}
$apps = [];
foreach (['Account Manager:in Region Bern 100%', 'Leiter/in Vertrieb / Head of Sales', 'Sales Engineer - Maschinen (m/w) 100%'] as $i=>$title) {
    $apps[] = ['id'=>$i+1,'title'=>$title,'applied_at'=>'2026-09-03 09:16:00','company_id'=>$i+1,
        'company_name'=>$companyRows[$i]['name'],'intermediary_company_name'=>'','status'=>'sent','channel'=>'website',
        'next_action'=>'follow_up','next_action_at'=>'2026-09-10 14:30:00','latest_workflow_at'=>'2026-09-10 14:30:00'];
}
function markup($source, $start, $end) {
    $a = strpos($source, $start);
    $b = strpos($source, $end, $a);
    if ($a === false || $b === false) { throw new RuntimeException('Fixture markup not found'); }
    return substr($source, $a, $b-$a);
}
$companyTable = markup($source, '<section class="panel table-wrap" data-bulk-action="bulk_delete_companies"', '</section>') . '</section>';
$applicationStart = strpos($source, "<?php if(\$appView === 'table'): ?>");
$applicationTable = markup(substr($source, $applicationStart), '<section class="panel table-wrap">', '</section>') . '</section>';
$room = markup($source, '<div class="job-room-compact"', '                <div class="actions">');
$registration = 'not_recorded';
?><!doctype html><html lang="de-CH"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Table regression fixture</title><link rel="stylesheet" href="/public/assets/app.css"><link rel="stylesheet" href="/public/assets/layout.css">
<script defer src="/public/assets/layout.js"></script></head><body>
<header class="topbar"><nav class="menubar"><a class="menu-trigger" href="/?page=calendar&view=agenda">Kalender</a></nav></header>
<main class="container"><h2>Firmen</h2><?php eval('?>'.$companyTable); ?>
<h2>Bewerbungen</h2><?php eval('?>'.$applicationTable); ?>
<form><?php eval('?>'.$room); ?></form></main></body></html>
