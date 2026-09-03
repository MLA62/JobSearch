<?php
$labels = ['Erfassungsdatum'=>'created_at','Titel'=>'title','Firma'=>'company','Ort'=>'location','Status'=>'status','Match'=>'match'];
$rows = [
['03.09.2026','Account Manager:in Region Bern 100%','Beispiel AG','Zuerich / Region Bern','Beworben','60%'],
['28.08.2026','Leiter/in Vertrieb / Head of Sales','Handelsgenossenschaft des Schweizerischen Baumeisterverbandes','Solothurn','Offen','70%'],
];
?><!doctype html><html lang="de-CH"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/public/assets/app.css"><link rel="stylesheet" href="/public/assets/layout.css"><script src="/public/assets/layout.js" defer></script><title>Layout verification</title></head>
<body><header class="topbar"><a class="brand"><img src="/public/assets/favicon.svg" width="30" height="30" alt="">JeMa Jobs</a><nav class="menubar"><button class="menu-trigger">Datei</button><button class="menu-trigger">CRM</button><button class="menu-trigger">Bewerbung</button><button class="menu-trigger">Planung</button></nav></header>
<main class="container"><div class="page-head"><h1>Jobs</h1><span>2 Eintraege</span></div>
<div class="actions export-actions"><span>Keine Feldfilter</span><button>Sort/Filter zuruecksetzen</button><a>Karten</a><a>Tabelle</a><a>PDF</a></div>
<div class="split"><section class="panel" id="new"><h2>Job erfassen</h2><form class="stack"><label>Firma<select><option>Beispiel AG</option></select></label><label>Jobtitel<input></label><div class="two"><label>Ort<input></label><label>Arbeitsmodell<select><option>Vor Ort</option></select></label></div><label>Beschreibung<textarea></textarea></label><button>Speichern</button></form></section>
<section class="panel table-wrap"><table><thead><tr><th class="bulk-select-column"><input aria-label="Alle auswaehlen" type="checkbox"></th>
<?php foreach($labels as $label=>$field): ?><th><div class="sf-head"><span><?= $label ?></span><details class="sf-menu"><summary class="sf-button" title="Sortieren und filtern">=</summary><form method="get" class="sf-form"><input name="sf_field" type="hidden" value="<?= $field ?>"><label>Filter<input name="sf_filter"></label><label>Sortierung<select name="sf_sort"><option value="none">Keine</option><option value="asc">Aufsteigend</option><option value="desc">Absteigend</option></select></label><div class="actions"><button>Anwenden</button></div></form></details></div></th><?php endforeach; ?><th>Aktionen</th></tr></thead>
<tbody><?php foreach($rows as $row): ?><tr><td class="bulk-select-column"><input type="checkbox" aria-label="Datensatz auswaehlen"></td><?php foreach($row as $i=>$value): ?><td><?php if($i===1): ?><strong><a href="#new"><?= $value ?></a></strong><small>Account Management in der gesamten Region. Betreuung bestehender Kunden und Entwicklung langfristiger Beziehungen.</small><?php else: ?><?= $value ?><?php endif; ?></td><?php endforeach; ?><td class="actions"><button>Bewerbung vorbereiten</button><a href="#new">Bewerbungen</a><button>Loeschen</button></td></tr><?php endforeach; ?></tbody></table></section></div>
</main><footer>Layout verification</footer></body></html>
