# Release 2.4.42 – Absagegrund bleibt sichtbar

Stand: 21.09.2026. Ein gespeicherter Absagegrund erschien nach dem Speichern
oder erneuten Öffnen der Bewerbung als leeres Textfeld. Der gemeinsame
Speicherpfad für manuelles Speichern und Autosave schrieb den Wert bereits in
`applications.rejection_reason`; die Edit-Abfrage las ihn aber nicht. Sie
lädt das Feld nun gemeinsam mit dem Bewerbungsstatus. So bleibt ein bereits
gespeicherter Grund beim erneuten Öffnen sichtbar und wird beim nächsten
Speichern nicht unbeabsichtigt durch einen leeren Formularwert ersetzt.

Keine Datenbankmigration oder Änderung bestehender Daten. Ein historisch
tatsächlich geleerter Wert kann durch diese Anzeige-Korrektur allein nicht
rekonstruiert werden.

## Verifikation und Deployment

- Quell-Commit `d07fb56` auf `feature/jema-jobs-ki-2.1.0` nach GitHub
  gepusht. PHP-Syntax, 56 PHP-Tests, Hilfe- und Referenzgenerator und
  `git diff --check` bestanden.
- Produktive Ausgangsdatei: SHA-256
  `1800c7a66747b505b80f34135fd85cd00f4474a650d2c4ca12715986361e3e0f`,
  Berechtigung `0644`. Backup unter
  `public_html/jobs.jema.business/index.php.bak-20260921-2.4.41-pre-2.4.42`
  mit Approval-ID `9edf520dd0791d3569dd002a0addd07b` erstellt.
- Upload/Extraktion nur von `public_html/jobs.jema.business/index.php` mit
  Approval-ID `8172d2af754f0afccfce56f3104d1563`; der Connector erstellte
  zusätzlich ein Datei-Backup.
- Produktiver SHA-256 nach Deployment:
  `34febf50f3500575aad620f889c0dd24f871eafc3eecce887930d10113456fbd`,
  identisch zur lokal getesteten Datei, Berechtigung `0644`. Anonymer
  HTTPS-Abruf: HTTP 200, Version 2.4.42.
- Ein angemeldeter Speicher-/Reload-Test mit echten Bewerbungsdaten wurde
  nicht durchgeführt. Der Regressionstest belegt die Codeverbindung von
  Detailabfrage zu Formular, nicht den individuellen Datenbankinhalt.
