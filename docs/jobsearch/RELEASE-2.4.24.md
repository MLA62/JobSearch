# Release 2.4.24

Stand: 14.09.2026. Implementiert und lokal geprüft; produktives Deployment und angemeldete
fachliche Abnahme sind bis zur externen Freigabe offen.

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
