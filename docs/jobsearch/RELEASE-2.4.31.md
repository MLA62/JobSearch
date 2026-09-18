# Release 2.4.31 – jüngster Lebenslauf je Sprache

## Anlass und Regel

Version 2.4.30 übergab alle als aktuell markierten CV-Dokumentreihen an die
Motivationsschreiben-KI. Mehrere eigenständig betitelte Reihen derselben
Sprache konnten so gleichzeitig im Kontext stehen. Der Benutzer hat am
18.09.2026 präzisiert: Je eingetragener Dokumentsprache zählt nur der nach
Modifikationsdatum neueste Lebenslauf.

Bei jedem KI-Aufruf werden die eigenen aktuellen, nicht gelöschten Dokumente
vom Typ `cv` erneut geladen. Pro `language_code` wird das jüngste
`updated_at` gewählt, bei Gleichstand die höhere Dokument-ID. Nur für diese
Auswahl werden Originaldatei und gegebenenfalls korrigierter Text vollständig
an die KI übergeben. Später geänderte oder neu hochgeladene CVs werden beim
nächsten Aufruf berücksichtigt. Vorhandene Bewerbungstexte werden durch die
Auswahländerung nicht automatisch überschrieben.

Die Sprachzuordnung stammt ausschliesslich aus den Dokumentmetadaten. Der
Dateiname wird nicht zur stillen Korrektur verwendet. Im am 18.09.2026
gesehenen Datenbestand trägt «Lebenslauf English 4.1» die Metadatensprache
`de-CH`; solange das so bleibt und dieses Dokument das jüngste `de-CH`-CV
ist, wird es für diese Sprachgruppe gewählt. Diese Datenauffälligkeit ist
keine automatische Datenkorrektur oder Migration.

## Technik und Betrieb

Keine Datenbankmigration und keine Änderung produktiver Datensätze. Geändert
wird nur die CV-Auswahl und ihre Dokumentation/Hilfe; für die produktive App
ist allein `public/index.php` zu ersetzen. Rückweg ist die gesicherte
vorherige produktive Datei. Ein KI-Lauf speichert wie bisher nur nach
bewusstem Auslösen Entwürfe in der Bewerbung und versendet nichts.

## Prüfung und Bereitstellung

- 2026-09-18 ab 13:55 UTC: PHP-Syntax, alle 46 PHP-Tests und beide
  Dokumentationsgeneratoren erfolgreich geprüft. Der CV-Test deckt
  Sprachgruppierung, `updated_at`-Priorität, ID-Gleichstand, erneute Auswahl
  und die vollständige Übergabe ausgewählter Dateien ab.
- Vor dem Deployment: Domain-Document-Root
  `public_html/jobs.jema.business`, produktive Version 2.4.30,
  SHA-256 `8696f609b0f554f23476e37171db1095f6bc1091ff3fd7e2bbcf7828dfa9a7fc`,
  1'335'612 Bytes, Modus `0644` read-only bestätigt.
- Git-Commit, produktive Sicherung, freigegebenes Deployment und Live-Hash
  werden nach Durchführung separat nachgetragen.
- Die fachliche Abnahme eines neu generierten Schreibens mit echten CVs ist
  getrennt vom Quelltest zu dokumentieren.
