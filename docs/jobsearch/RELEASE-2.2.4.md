# Version 2.2.4 – verbindliche HTML-Übernahme

Stand: 07.09.2026

## Änderungen

- Eine eigene HTML-Commit-Funktion schreibt Quelltextänderungen beim Umschalten sichtbar in WYSIWYG.
- Jedes native oder programmatisch erzeugte Formular-Payload übernimmt nochmals den aktuellen Editormodus.
- Der Bewerbungs-Autosave synchronisiert Rich-Text-Felder vor dem Erzeugen seiner Nutzdaten.
- Manuelles Speichern, Autosave und KI-Aufrufe können offene HTML-Änderungen nicht mehr durch ältere WYSIWYG-Inhalte ersetzen.
- Version, technische Dokumentation und In-App-Hilfe sind auf 2.2.4 nachgeführt.

## Prüfung und Bereitstellung

PHP-Syntax, alle 31 automatisierten PHP-Testdateien sowie die generierte Hilfe und Referenz wurden
geprüft. Quell-Commit `f7c0b040fb74eaf4fd373ab33bea60214e6fdad5` wurde nach externer
TOTP-Freigabe nach `public_html/jobs.jema.business/index.php` bereitgestellt. Lokale und produktive
Datei sind mit SHA-256 `a4522dccd8777c81a564a5180359306ca28b9e268311deeb6d9d1420e4a256bc`
bytegleich. Die öffentliche Seite liefert HTTP 200 und Version 2.2.4; seit dem Deployment entstand
kein neuer PHP-Fehlereintrag. Konfiguration und Datenbankschema blieben unverändert.
