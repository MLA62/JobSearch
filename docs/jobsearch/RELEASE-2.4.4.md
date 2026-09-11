# Version 2.4.4 – Vollständiger Google-Kalenderabgleich

Datum: 11.09.2026

## Korrekturen

- Unveränderte Verknüpfungen werden gegen den tatsächlich gewählten Google-Kalender geprüft.
- Nach Kalenderwechsel oder externer Löschung fehlende JeMa-Termine werden sicher neu erstellt.
- Bestehende Bewerbungsverläufe werden vor dem Vollabgleich erneut in Kalendernachweise projiziert.
- Der Abgleich zählt erwartete und bestätigte Exporte und speichert konkrete Einzelfehler.
- Jede angemeldete Sitzung stößt eine vollständige Reparatur an; unvollständige Läufe werden bei der nächsten Anfrage wiederholt.

## Sicherheit

- Fremde Google-Termine werden weder verändert noch gelöscht.
- Reproduzierbare Ersatz-IDs vermeiden Dubletten bei wiederholten oder unterbrochenen Reparaturen.
- Die getrennte Workflow-v6-Bestandsmigration wird nicht ausgeführt.

## Produktive Bereitstellung

Die TOTP-Bereitstellung und der anschließende Live-Nachweis werden im Deployment-Dokument ergänzt.
