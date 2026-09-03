<?php
declare(strict_types=1);
$source = file_get_contents(__DIR__.'/../public/index.php');
$locale = in_array($_GET['lang'] ?? '',['de-CH','fr-CH','en-GB','pt-BR','es-MX'],true) ? $_GET['lang'] : 'de-CH';
$catalog=[];
preg_match_all("/^        '([^']+)' => \\[\\R(.*?)^        \\],/ms",substr($source,0,strpos($source,"\nfunction ")),$matches,PREG_SET_ORDER);
foreach ($matches as $match) { $catalog[$match[1]]=eval('return ['.$match[2].'];'); }
$common = [
    'de-CH'=>['Bewerbungen','Job','Firma','Status','Kanal','Aktionen','Bearbeiten','Löschen','Entwurf','Bereit','Gesendet','Absage','E-Mail','Onlineformular','Schliessen'],
    'fr-CH'=>['Candidatures','Poste','Entreprise','Statut','Canal','Actions','Modifier','Supprimer','Brouillon','Prête','Envoyée','Refus','E-mail','Formulaire en ligne','Fermer'],
    'en-GB'=>['Applications','Job','Company','Status','Channel','Actions','Edit','Delete','Draft','Ready','Sent','Rejected','Email','Online form','Close'],
    'pt-BR'=>['Candidaturas','Vaga','Empresa','Status','Canal','Ações','Editar','Excluir','Rascunho','Pronta','Enviada','Recusada','E-mail','Formulário online','Fechar'],
    'es-MX'=>['Solicitudes','Puesto','Empresa','Estado','Canal','Acciones','Editar','Eliminar','Borrador','Lista','Enviada','Rechazada','Correo','Formulario en línea','Cerrar'],
];
$keys=['nav.applications','reports.field.job','companies.company','common.status','applications.channel','common.actions','common.edit','common.delete','applications.status.draft','applications.status.ready','applications.status.sent','applications.status.rejected','contact_log.channel.email','applications.channel.website','common.close'];
$labels=array_combine($keys,$common[$locale]);
function tr($key,$language=null,$replace=[]): string { global $catalog,$locale,$labels; return $catalog[$key][$language ?? $locale] ?? $labels[$key] ?? $key; }
function e($value): string { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function csrfToken(): string { return 'fixture'; }
foreach (['applicationStatusOptions','applicationStatusSequence','applicationWorkflowView','applicationNextActionOptions','applicationNextActionChoices','applicationNextActionLabel','applicationChannelOptions','sfHeader','sfHiddenInputs','calendarEventTypeOptions','calendarStatusOptions','pdfWrapLines','pdfTableBytes','pdfTextOperand','pdfResponse','applicationExportHeaders','applicationExportData','optionLabel'] as $name) {
    preg_match('/^function '.$name.'\(.*?(?=^function |\z)/ms',$source,$match); eval(trim($match[0]));
}
function displayDateTime(?string $value,array $user=[],bool $time=true): string { return $value ? date($time?'d.m.Y, H:i':'d.m.Y',strtotime($value)) : ''; }
$apps=[];
foreach(applicationStatusSequence() as $i=>$status) {
    $apps[]=['id'=>$i+1,'job_id'=>$i+1,'status'=>$status,'applied_at'=>in_array($status,['draft','ready'],true)?null:'2026-09-03 09:16:42',
        'latest_workflow_at'=>'2026-09-10 14:30:00','title'=>'Spontanbewerbung Reinigungsmitarbeiter:in (a) 10 - 50% mit einem langen vollständigen Titel',
        'company_id'=>1,'company_name'=>'Beispiel Unternehmen AG','intermediary_company_name'=>'','channel'=>'website','next_action'=>'send_application','next_action_at'=>'2026-09-03 09:16:42'];
}
$currentUser=[]; $appSf=$appPreserve=[]; $applicationStatuses=applicationStatusOptions(); $nextActionOptions=applicationNextActionOptions();
if (($_GET['format'] ?? '')==='pdf') {
    $rows=array_map(static fn(array $row): array => $row+['company'=>$row['company_name']],$apps);
    pdfResponse('workflow-test.pdf',tr('nav.applications'),applicationExportHeaders(),applicationExportData($rows,[]));
}
$applicationEdit=null; $appView=($_GET['view'] ?? '')==='cards'?'cards':'table';
$a=strpos($source,"<?php if(\$appView === 'table'): ?>"); $b=strpos($source,"    <?php elseif (\$page === 'contacts')",$a);
$listing=substr($source,$a,$b-$a);
$formStart=strpos($source,'<form method="post" class="stack" id="application-edit-form"');
$a=strpos($source,'<div class="three">',$formStart); $b=strpos($source,'</div>',$a)+6;
$fields=substr($source,$a,$b-$a);
$a=strpos($source,'<div class="job-room-compact"',$formStart); $b=strpos($source,'                <div class="actions">',$a);
$jobRoom=substr($source,$a,$b-$a);
$applicationEdit=$apps[1]+['job_room_interview'=>0,'job_room_result'=>'open']; $channels=applicationChannelOptions(); $registration='not_recorded';
?><!doctype html><html lang="<?= e($locale) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Workflow verification</title><link rel="stylesheet" href="/public/assets/app.css"><link rel="stylesheet" href="/public/assets/layout.css"><script defer src="/public/assets/layout.js"></script></head>
<body><main class="container"><h1><?= e(tr('nav.applications')) ?></h1>
<section class="application-editor panel"><form data-application-autosave onsubmit="event.preventDefault()">
<?php eval('?>'.$fields); eval('?>'.$jobRoom); ?></form></section>
<?php $applicationEdit=null; eval('?>'.$listing); ?>
</main></body></html>
