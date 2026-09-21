# Release 2.4.41 – Online eingereicht am richtigen Ort

Stand: 21.09.2026. Der auf der Bewerbungsseite angezeigte Hinweis verwies
auf «Online eingereicht», während der zugehörige Button erst ganz unten
nach dem langen KI- und Textbereich stand. Der Screenshot zeigte den
Onlinebewerbungsbereich ohne diese Aktion.

Der Button steht nun direkt bei «Webformular öffnen» und den
Dokumentenaktionen. Er wird wie der Hinweis nur vor einer bestätigten
Einreichung angezeigt (`draft` oder `ready` und kein `applied_at`).
Der entfernte zweite Button am Formularende ist nicht mehr nötig.
Der Handler zur transaktionalen Erfassung, zum Statuswechsel, zur
Kalenderprojektion und zur erneuten serverseitigen Prüfung bleibt
unverändert. Keine Schema- oder Bestandsdatenänderung.

## Verifikation und Deployment

- Quell-Commit `de8496d` auf `feature/jema-jobs-ki-2.1.0` nach GitHub gepusht.
  56 PHP-Tests, PHP-Syntax, Hilfe- und Referenzgenerator sowie
  `git diff --check` bestanden.
- Vor dem Austausch: SHA-256 der produktiven Datei
  `180aa5c9bef497e953ae83c1b1c0ecd67931fb602e509230703bdff0a4b8d3de`,
  Berechtigung `0644`. Backup unter
  `public_html/jobs.jema.business/index.php.bak-20260921-2.4.40-pre-2.4.41`
  mit Approval-ID `e5f251cbf2c64ba2af6f77789d3a8579` ausgeführt.
- Upload/Extraktion nur der Datei `public_html/jobs.jema.business/index.php`
  mit Approval-ID `63321bf1cb15df80e1c01e8e6e28906d`; der Connector
  erstellte zusätzlich ein eigenes Datei-Backup.
- Nach dem Austausch: Live-SHA-256
  `1800c7a66747b505b80f34135fd85cd00f4474a650d2c4ca12715986361e3e0f`
  identisch mit der lokal geprüften Datei, Berechtigung weiterhin `0644`.
  Anonymer HTTPS-Abruf HTTP 200 und Version 2.4.41.
- Eine neue versteckte Browser-Registerkarte übernahm die Anmeldung der
  bereits offenen Benutzerregisterkarte nicht. Die Root-Seite zeigte
  die Anmeldemaske mit 2.4.41. Eine angemeldete Sichtprüfung des Buttons
  und ein echter Einreichungsklick wurden nicht durchgeführt; bestehende
  Benutzertexte wurden für diese Prüfung nicht berührt.
