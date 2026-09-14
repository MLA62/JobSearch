# Release 2.4.24

Stand: 15.09.2026. Implementiert, lokal geprüft und produktiv bereitgestellt. Die angemeldete
fachliche Abnahme des Schalters und der Dokumentauswahl bleibt sitzungsabhängig.

## Änderung

- Profildokumente besitzen das Ankreuzfeld `Bewerbungsrelevant`.
- Neue Dokumente sind standardmässig nicht gekennzeichnet.
- Eine neue Dokumentversion übernimmt das Kennzeichen der ausgewählten aktuellen Version und
  lässt es vor dem Upload ändern.
- Bewerbungen schlagen nur eigene, aktuelle, nicht gelöschte und gekennzeichnete Profildokumente
  zum Hinzufügen vor.
- Bereits zugeordnete Dokumente bleiben unabhängig vom Vorschlagskennzeichen erhalten.
- Dokumentreports und der Admin-KI-Datenvertrag enthalten das neue Feld.

## Datenbankwirkung

Die idempotente Laufzeitmigration ergänzt `user_documents.is_application_relevant` als
`TINYINT(1) NOT NULL DEFAULT 0`. Bestehende Dokumente werden dadurch nicht automatisch als
bewerbungsrelevant markiert. Es werden keine Dateien, Dokumentversionen oder Zuordnungen geändert.

## Rücknahme

Die geprüfte `index.php` kann aus dem Deployment-Backup wiederhergestellt werden. Die zusätzliche
Spalte darf bei einer Rücknahme bestehen bleiben; ältere Anwendungsversionen ignorieren sie. Ein
Entfernen der Spalte ist für die Rücknahme nicht erforderlich und soll nur nach separater
Datenbanksicherung erfolgen.

## Lokale Prüfung

- PHP-Syntaxprüfung von Anwendung und neuem Regressionstest.
- `document_application_relevance_test.php` und `document_version_flow_test.php`.
- Alle 44 ausführbaren PHP-Testdateien, 4'043 Hilfeprüfungen, 1'384 Hilfeseeds,
  93 Markdown-Dateien/61 lokale Links, beide Generatorprüfungen und alle elf Chromium-Testdateien
  sind erfolgreich.

## Produktive Bereitstellung

- Quell-Commit: `44d8dfee0ef3535434804052faac5b6c61980f19`.
- TOTP-Freigabe: `9f82e2f3d97d8cfacad0f2d59eac75fe`.
- `index.php`: SHA-256 `027423c2e8939de7c1483de448aac3827b32700e396fc8f22136fdabfe5b41d5`.
- `assets/app.css`: SHA-256 `4dbb17e913bac9862e8de9e5212dca36053058122b31820a68bf8598893e24be`.
- Beide produktiven Dateien sind bytegleich mit dem geprüften Quellstand. Die öffentliche Seite
  liefert HTTP 200, Version 2.4.24, HSTS, CSP, `nosniff`, `DENY` und `no-referrer`.
- Der erste öffentliche Aufruf protokollierte keinen Fehler der Schema-Migration. Der
  cPanel-Connector stellt derzeit jedoch kein Abfrage- oder Sicherungsprofil für die von JeMa Jobs
  verwendete Datenbank bereit; eine direkte, unabhängige SQL-Abnahme war deshalb nicht möglich.
