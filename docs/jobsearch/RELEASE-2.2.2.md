# Version 2.2.2 – zuverlässige KI-Instruktionen

Stand: 07.09.2026. Deployment-Kandidat; produktive Bereitstellung folgt nach externer TOTP-Freigabe.

## Änderungen

- Vor der KI-Überarbeitung werden die sichtbaren Inhalte von Begleit-E-Mail und Motivationsschreiben aus den Mini-Editoren in die Formularfelder synchronisiert.
- Ausstehende automatische Speicherungen werden vor der KI-Aktion gestoppt, damit keine älteren Texte parallel zurückgeschrieben werden.
- Die KI-Aktion verwendet eine normale Formularnavigation statt des fehleranfälligen Hintergrundabrufs.
- Die Benutzerinstruktion ist als vorrangiger Bearbeitungsauftrag für Begleit-E-Mail und Motivationsschreiben formuliert.
- Unveränderte Langtexte werden weiterhin einmal nachgefordert und danach mit konkreter Fehlerreferenz abgelehnt.
- Neue Versionen von Profil- und Bewerbungsdokumenten übernehmen Titel, Typ, Sprache, Beschreibung und Gültigkeitsdaten der gewählten aktuellen Version.
- Die neue Dokumentnummer wird aus der höchsten Nummer derselben Versionsreihe gebildet; Datei und Datenbankänderungen werden transaktional abgesichert und bei Fehlern vollständig zurückgerollt.

## Prüfung und Deployment

PHP-Syntax, automatisierte PHP-Tests, generierte Hilfe in fünf Sprachen, Referenzdateien und Git-Diff werden vor der Freigabe geprüft. Nach der TOTP-Freigabe werden Git-Synchronität, produktiver Dateihash, HTTP-Antwort, Versionsanzeige und Serverfehler kontrolliert und hier ergänzt.
