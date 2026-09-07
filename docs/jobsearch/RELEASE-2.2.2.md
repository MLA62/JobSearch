# Version 2.2.2 – zuverlässige KI-Instruktionen

Stand: 07.09.2026. Produktiv bereitgestellt nach externer TOTP-Freigabe.

## Änderungen

- Vor der KI-Überarbeitung werden die sichtbaren Inhalte von Begleit-E-Mail und Motivationsschreiben aus den Mini-Editoren in die Formularfelder synchronisiert.
- Ausstehende automatische Speicherungen werden vor der KI-Aktion gestoppt, damit keine älteren Texte parallel zurückgeschrieben werden.
- Die KI-Aktion verwendet eine normale Formularnavigation statt des fehleranfälligen Hintergrundabrufs.
- Die Benutzerinstruktion ist als vorrangiger Bearbeitungsauftrag für Begleit-E-Mail und Motivationsschreiben formuliert.
- Unveränderte Langtexte werden weiterhin einmal nachgefordert und danach mit konkreter Fehlerreferenz abgelehnt.
- Neue Versionen von Profil- und Bewerbungsdokumenten übernehmen Titel, Typ, Sprache, Beschreibung und Gültigkeitsdaten der gewählten aktuellen Version.
- Die neue Dokumentnummer wird aus der höchsten Nummer derselben Versionsreihe gebildet; Datei und Datenbankänderungen werden transaktional abgesichert und bei Fehlern vollständig zurückgerollt.

## Prüfung und Deployment

PHP-Syntax, alle 31 automatisierten PHP-Testdateien, generierte Hilfe in fünf Sprachen, Referenzdateien und Git-Diff wurden vor der Freigabe geprüft. Commit `1cb398c1955d8f767b65ce0ea1ae086365489b65` wurde nach `public_html/jobs.jema.business/index.php` bereitgestellt. Lokale und produktive Datei sind mit SHA-256 `1c43f2a75f5f01807ca8901bfea1dbeae1523ba025700d91e47442a4747d4999` bytegleich. Die öffentliche Seite liefert Version 2.2.2; nach dem Deployment entstand kein neuer PHP-Fehlereintrag. Konfiguration und Datenbankschema blieben unverändert.
