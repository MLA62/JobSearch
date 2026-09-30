# Release 2.4.54 – Verbindliche Statussynchronisation

Stand: 30.09.2026

## Ursache

Beim Speichern einer Bewerbung wurde der zugehörige Jobstatus bereits
nachgeführt. Der Job konnte danach jedoch separat oder über die
Admin-KI erneut mit einem widersprüchlichen Status gespeichert werden.
Es fehlte die Gegenprüfung auf der Job-Seite. Deshalb konnte etwa eine
abgesagte Bewerbung einem nicht als Absage markierten Job gegenüberstehen.

## Änderung

- Ab dem Bewerbungsstatus `Gesendet` ist der Bewerbungsstatus die
  verbindliche Quelle für den Jobstatus.
- Das separate Speichern eines Jobs übernimmt danach erneut den Status
  seiner aktiven Bewerbung. Ein widersprüchlicher Jobstatus kann nicht
  mehr bestehen bleiben.
- Dasselbe gilt für Jobänderungen durch die Admin-KI.
- Eine einmalige, transaktionale Konsistenzprüfung korrigiert bereits
  vorhandene Abweichungen. Vor jedem korrigierten Job wird der bisherige
  Status in `workflow_data_backups` gesichert.
- Entwurf und Bereit bleiben ausgenommen, weil zu diesem Zeitpunkt noch
  keine eingereichte Bewerbung vorliegt.

## Erwartetes Ergebnis für Faigle

Die Bewerbung mit Status `Absage` setzt den zugehörigen Job automatisch
auf `Absage`. Dieser Zustand bleibt auch nach späterem Speichern des Jobs
erhalten.

## Prüfung

- Statusabbildung aller Bewerbungszustände auf Jobzustände
- Synchronisation beim Speichern der Bewerbung
- Gegenprüfung beim separaten Speichern eines Jobs
- Konsistenzmigration mit Sperre, Transaktion und Datensicherung
- vollständige PHP-Regressionssuite

## Deploymentnachweis

- Quell-Commit: `5124ca037e57fa349ef4cf85c2ec118b0d6fa52d`.
- Produktiv ausgerollt wurde ausschliesslich `index.php`; die Änderung
  benötigt keine Schemaänderung.
- Die aktive Freigabestunde wurde verwendet. Der erste Vorgang
  `f61884b838c367997a5ae5b15180f5f2` konnte den lokalen Windows-Pfad
  beim Ausführen nicht auflösen und änderte nichts. Der inhaltsgleiche,
  direkt übertragene Vorgang `61cce3049759c5a021d2c3341567a7ba`
  wurde erfolgreich ausgeführt.
- Die produktive Vorgängerdatei wurde unter
  `approval.lauber.online/storage/file_backups/20260930_063653_beb67b4a_public_html_jobs.jema.business_index.php`
  gesichert.
- Produktive und lokale `index.php` sind bytegleich: 1'439'221 Bytes,
  SHA-256
  `3ed3884af5f8010883470250fdf1a1b8ba486d33a96f56bf2bcad0b89613b35c`.
- Die öffentliche Seite liefert HTTP 200 und Version 2.4.54 ohne
  sichtbaren PHP-Fehler. Der erste Aufruf löste die einmalige
  Statuskonsistenzprüfung aus; das produktive Fehlerprotokoll blieb
  unverändert. Die Browsersitzung war abgemeldet, weshalb die
  datensatzbezogene Sichtprüfung von Faigle nicht möglich war.
