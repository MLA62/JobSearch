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

Wird nach dem produktiven Rollout ergänzt.
