# Release 2.4.25 – Job-Room-Helper und Absagegrund

Stand: 18.09.2026.

## Umfang

- Der Helper und seine Monatsauswahl enthalten nur datierte Bewerbungen, die noch nicht als im Job-Room erfasst markiert wurden.
- Strasse, Hausnummer, Postleitzahl und Ort erscheinen in dieser Reihenfolge als einzeln kopierbare Felder. Ein nicht eindeutig trennbarer Hausnummernteil wird nicht erfunden.
- Eine Absage verlangt einen mehrzeiligen Absagegrund mit 1–249 Zeichen. Er wird im Bewerbungsformular nur bei Absage oberhalb der übrigen Karten gezeigt und ist im Helper kopierbar.
- Direkte Formularänderung, Kontakt-/E-Mail-Protokoll und Admin-KI-Schreibweg prüfen die Pflicht serverseitig.

## Datenbank

Neue nullable Spalte `applications.rejection_reason VARCHAR(249)`, implementiert über die idempotente Runtime-Erweiterung `runtime_schema_2_4_25`; `sql/jobsearch/19_application_rejection_reason.sql` dokumentiert die entsprechende Einzelmigration für kontrollierte Neuaufbauten. Bestehende Absagen werden nicht automatisch mit unbelegten Gründen ergänzt. Keine vorhandenen Bewerbungen werden gelöscht.

## Nachweise

- Lokal: PHP-Syntax, beide Dokumentationsgeneratoren im Prüfmodus und 44 PHP-Tests erfolgreich.
- Release-Commit: `b1bc5e212cd11399870cb39ee9223987f8c65375`.
- Deployment am 18.09.2026 um 11:44–11:53 UTC über cPanel Mail Control:
  `public_html/jobs.jema.business/index.php` mit Freigabe `56d5dd973dd65bc566bf2def3ea65005`, SHA-256 `64b97e43cdc45a0b5d68dd39851715ff3c622ec03f156100c50962e653ea97b3`;
  `public_html/jobs.jema.business/assets/layout.css` mit Freigabe `4880a9feae6d319b8ee282b9d8974bbb`, SHA-256 `bb0267473d92722d59e05abffb8609c564ea9f327763a75189897cfa94fff188`.
  Beide Remote-Hashes stimmen mit dem lokalen Release überein.
- Öffentlicher Live-Aufruf: HTTP 200 und Versionsanzeige 2.4.25; Login-Seite im Browser erreichbar. Das `error_log` war nach diesen Aufrufen unverändert (letzte Änderung 11:07 UTC, vor dem Deployment).
- Datenbankeffekt auf Produktion: Der öffentliche Aufruf hat den Runtime-Bootstrap-Codepfad erreicht; ob die neue Spalte und der Migrationsmarker erfolgreich angelegt wurden, konnte mangels JeMa-Datenbankprofil im Connector nicht direkt abgefragt werden. Keine Bestandsdatenmigration beauftragt.
- Authentifizierte Live-Abnahme: noch offen; im verfügbaren Browser war keine JeMa-Sitzung vorhanden. Helper-Filter, Kopierfelder und Absage-Pflichtprüfung sind daher auf Produktion nicht als End-to-End-Test bestätigt.
