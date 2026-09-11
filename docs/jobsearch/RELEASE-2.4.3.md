# Version 2.4.3 – Zuverlässiger externer Kalenderabgleich

Datum: 11.09.2026

## Korrekturen

- Der Google-Abgleich läuft wieder, auch wenn die getrennte Workflow-v6-Bestandsbereinigung noch nicht ausgeführt wurde.
- Der bestehende geprüfte Exportfilter verhindert weiterhin, dass ungeprüfte Legacy-Projektionen im externen Kalender erscheinen.
- Änderungen und Löschungen an Kalender, Bewerbungen, Jobs, Firmen, primären Kontakten und vorhandenen Kontaktterminen werden automatisch nachgeführt.
- Fehler aus automatischem und manuellem Abgleich bleiben im Profil sichtbar; ein erfolgreicher Lauf setzt sie zurück.
- Der private abonnierbare ICS-Feed wird von der App mit No-Cache-Headern ausgeliefert.

## Prüfung

- Ein eigener Regressionstest prüft Marker-Unabhängigkeit, Exportfilter, Änderungs- und Löschpfade,
  Fehlerpersistenz sowie den No-Cache-Vertrag.
- Die bestehenden Kalender- und Workflowtests prüfen weiterhin Zeitpunkte, Dublettenfilter,
  Eigentumsmarker und den Schutz fremder Einträge.
- Alle 35 PHP-Testdateien, Hilfe-/Dokumentationsprüfungen und alle sieben Chromium-Testdateien sind erfolgreich.
- Die Workflow-v6-Bestandsmigration wird durch dieses Release weder ausgeführt noch als ausgeführt dokumentiert.

## Produktive Bereitstellung

- Nach TOTP-Freigabe wurde die geprüfte `index.php` mit Sicherung der Vorgängerversion bereitgestellt.
- Produktive und lokale Datei sind bytegleich; die öffentliche Seite liefert HTTP 200 und Version 2.4.3.
- HSTS, CSP, `nosniff`, `DENY` und `no-referrer` wurden produktiv bestätigt.
- Für den Deployment-Tag enthält das produktive Fehlerprotokoll keinen neuen Eintrag.
