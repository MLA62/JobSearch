# Version 2.2.4 – verbindliche HTML-Übernahme

Stand: 07.09.2026

## Änderungen

- Eine eigene HTML-Commit-Funktion schreibt Quelltextänderungen beim Umschalten sichtbar in WYSIWYG.
- Jedes native oder programmatisch erzeugte Formular-Payload übernimmt nochmals den aktuellen Editormodus.
- Der Bewerbungs-Autosave synchronisiert Rich-Text-Felder vor dem Erzeugen seiner Nutzdaten.
- Manuelles Speichern, Autosave und KI-Aufrufe können offene HTML-Änderungen nicht mehr durch ältere WYSIWYG-Inhalte ersetzen.
- Version, technische Dokumentation und In-App-Hilfe sind auf 2.2.4 nachgeführt.

## Prüfung und Bereitstellung

Die Bereitstellung erfolgt nach PHP-Syntaxprüfung, vollständiger Testsuite sowie Prüfung der
generierten Hilfe und Referenzdokumente über den externen TOTP-Freigabeablauf.
