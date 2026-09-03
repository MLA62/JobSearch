<?php
declare(strict_types=1);
$source=file_get_contents(getenv('JEMA_TEST_SOURCE') ?: __DIR__.'/../public/index.php');
$start=strpos($source,'<div class="application-workflow">');
$end=strpos($source,'        </section>', $start);
if ($start===false || $end===false) { throw new RuntimeException('Workflow markup missing'); }
$markup=substr($source,$start,$end-$start);
function tr($key) { return ['applications.status_history'=>'Statusverlauf','applications.next_action'=>'Nächster Kalendereintrag','nav.calendar'=>'Kalender','calendar.create_entry'=>'Kalendereintrag erstellen','common.no_results'=>'Keine weiteren Termine','applications.no_next_action'=>'Kein weiterer Termin','calendar.status.completed'=>'Erledigt'][$key] ?? $key; }
function e($s) { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function applicationStatusSequence() { return ['draft','ready','sent','interview','accepted','rejected']; }
function csrfToken() { return 'test-only'; }
function displayDateTime($s,$user) { return date('d.m.Y, H:i',strtotime($s)); }
$currentUser=[];
$applicationStatuses=['draft'=>'Entwurf','ready'=>'Bereit','sent'=>'Gesendet','interview'=>'Bewerbungsgespräche','accepted'=>'Zusage','rejected'=>'Absage'];
$applicationEdit=['id'=>1,'status'=>'sent','next_action'=>'follow_up','next_action_at'=>'2026-09-10 14:30:00'];
$nextActionOptions=['follow_up'=>'Nachfassen'];
$history=[['new_status'=>'sent','changed_at'=>'2026-09-03 09:16:00','comment'=>''],['new_status'=>'ready','changed_at'=>'2026-09-03 08:51:00','comment'=>'Online-Bewerbung vorbereitet']];
$workflowAppointments=[];
?><!doctype html><html lang="de-CH"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Workflow test</title><link rel="stylesheet" href="/public/assets/app.css"><link rel="stylesheet" href="/public/assets/layout.css"></head>
<body><main class="container"><h1>Bewerbung</h1><?php eval('?>'.$markup); ?></main></body></html>
