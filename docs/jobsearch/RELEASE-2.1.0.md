# Version 2.1.0 – KI-gestützte Bewerbungstexte

Stand: 2026-09-04. Nach externer TOTP-Freigabe deployed.

## Änderung

- Betreff, Begleit-E-Mail und Motivationsschreiben werden beim erstmaligen Vorbereiten aus den verfügbaren Profil-, Stellen-, Firmen- und Kontaktdaten sowie dem lesbaren Inhalt der aktuellen CV-Version vorausgefüllt.
- Die KI liefert alle drei Texte in der Benutzersprache als strikt strukturierte Antwort, ohne Fakten zu erfinden.
- Alle Texte bleiben frei bearbeitbar und werden wie die übrigen Bewerbungsfelder automatisch gespeichert.
- Ein zweizeiliges KI-Instruktionsfeld überarbeitet die drei aktuellen Texte erst nach ausdrücklichem Klick gemeinsam.
- Die KI-Aktion versendet keine E-Mail und reicht keine Bewerbung ein.
- Bei einem API-Ausfall bleiben bearbeitbare lokale Grundentwürfe verfügbar; bestehende Texte werden bei der Initialisierung nicht überschrieben.
- OpenAI-Speicherung ist deaktiviert; der API-Schlüssel bleibt serverseitig. Keine DB-Schemaänderung.

## Prüfung und Deployment

Alle 27 PHP-Testdateien liefen ohne Warnungen durch. Die neue Prüfung bestätigt den strukturierten Drei-Felder-Vertrag, die Ausfallentwürfe, das zweizeilige Instruktionsfeld, deaktivierte OpenAI-Speicherung und die benutzergebundene Sicherheitskennung. Beide PHP-Syntaxprüfungen, die Hilfeprüfung mit 3467 Checks, der Hilfeseed mit 1094 Checks, beide Dokumentationsgeneratoren und `git diff --check` bestanden. Externe KI-Antworten wurden vor der Freigabe nicht mit echten Bewerbungsdaten getestet.

Deployed wurde ausschließlich `public_html/jobs.jema.business/index.php` aus Quell-Commit `5d8f189b861df9fd171e9473386daf1cbc1e3fe1`. Die Produktionsdatei hat 1026470 Bytes, Modus 0644 und SHA-256 `5883707a5c7e1ebaca28b50f18a8c2fe2f58601fd865ee1bce88a0aef8771de6`; sie entspricht exakt den geprüften lokalen Bytes. Die öffentliche Loginseite liefert HTTP 200, zeigt Version 2.1.0 und keinen sichtbaren PHP-Laufzeitfehler. Konfiguration und DB-Schema blieben unverändert. Die angemeldete Fachabnahme mit einem vorhandenen CV und einer Bewerbung bleibt offen.

Vorheriger bestätigter Live-Stand: Version 2.0.15. Rückweg ist ein separat freizugebender Upload der bestätigten 2.0.15-Datei. Keine Freigabe-ID in Git.
