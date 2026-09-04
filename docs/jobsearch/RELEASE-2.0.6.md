# Version 2.0.6 – Vollständige Speicherung beim URL-Import

Stand 2026-09-04. In Vorbereitung, noch nicht deployed.

## Ursache und Änderungen

- Einzelner URL-Schnellimport verlor beim manuellen save_job die gelesenen Firmen-/Kontaktdetails. Er nutzt jetzt denselben vollständigen Writer wie Mehrfachimport und Übernehmen und öffnet direkt den gespeicherten Job.
- Wiederimport ergänzt leere Kontaktfelder statt vorhandene Personen unverändert zu überspringen. Bestehende Werte bleiben geschützt.
- Gleiche eigene Quell-URL aktualisiert den vorhandenen Job einschließlich der tatsächlichen Arbeitgeberzuordnung; Notizen, Status und Dokumente bleiben erhalten.
- Veraltete Importvorschauen werden vor Schnellimport bzw. nach erfolgreichem Übernehmen entfernt, damit der gespeicherte Job sichtbar ist.
- Gemeinsame Transaktion für Firma, Job, Kontakte und Audit. Jede URL einer Liste bleibt eine einzelne Transaktion.
- Freitext bleibt ein manuell zu prüfender Entwurf; keine neue universelle Adress-/Kontakterkennung behauptet.
- Keine Schemaänderung, keine automatische Bestandsmigration, keine Änderungen an Server-Secrets.
- Modales Übernehmen-Fenster mit Status für Lesen und Speichern, Laufzeit und Abbruch. Vorbereitung liest nur; erst ein separater CSRF-/Session-gebundener Einmal-Commit speichert. Abbrechen verhindert diesen Commit, während der abschließenden DB-Transaktion ist der Button gesperrt. Fehler bleiben sichtbar, Erfolg öffnet den Job.
- Ergebnisliste nach numerischem Match-Prozent absteigend sortiert; stabile Reihenfolge bei gleichem Match.

## Prüfung

254 Prüfungen des echten Speicherhelfers gegen simulierte DB bestanden, inklusive Fehlersimulation/Rollback.
Die Verdrahtung von Einzel-/Mehrfach-Schnellimport und Übernehmen ist als Quellvertrag geprüft.
Alle 22 PHP-Testdateien, beide Syntaxprüfungen, beide Dokumentationsgeneratoren mit --check und git diff --check bestanden.
Chromium-Dialogtests mit simulierten HTTP-Antworten: Abbruch ohne Commit, Erfolg, Lesefehler und Commit-Fehler bestanden; mobile Darstellung visuell geprüft. Keine angemeldete Produktionsprüfung dadurch behauptet.
Die genaue zuletzt betroffene Anzeige und der vom Benutzer verwendete Einstieg sind noch nicht bestätigt.
Angemeldete Funktionsabnahme sowie ein echter MariaDB-Rollbacktest bleiben separat offen.

## Deployment und Rückweg

Ziel ausschließlich public_html/jobs.jema.business/index.php inklusive generierter Hilfe in fünf Sprachen.
Vorheriger Stand 2.0.5: Quell-Commit c34d46994107d704f4674cc04d20a96a361c17d5,
926119 Bytes, Modus 0644, SHA-256 9690811bb33e3a9ea034db68c361d5e49464201b1a8b54b89fef782799746f5e.
Code-Rollback durch erneute TOTP-Freigabe dieses vorherigen Inhalts. Benutzerseitige Imports
werden dadurch nicht zurückgesetzt. Freigabe-ID und Link bleiben außerhalb des Repositorys.
