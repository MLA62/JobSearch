# Version 2.1.0 – KI-gestützte Bewerbungstexte

Stand: 2026-09-04. Für externe TOTP-Freigabe vorbereitet; bestätigter Live-Stand ist 2.0.15.

## Änderung

- Betreff, Begleit-E-Mail und Motivationsschreiben werden beim erstmaligen Vorbereiten aus den verfügbaren Profil-, Stellen-, Firmen- und Kontaktdaten sowie dem lesbaren Inhalt der aktuellen CV-Version vorausgefüllt.
- Die KI liefert alle drei Texte in der Benutzersprache als strikt strukturierte Antwort, ohne Fakten zu erfinden.
- Alle Texte bleiben frei bearbeitbar und werden wie die übrigen Bewerbungsfelder automatisch gespeichert.
- Ein zweizeiliges KI-Instruktionsfeld überarbeitet die drei aktuellen Texte erst nach ausdrücklichem Klick gemeinsam.
- Die KI-Aktion versendet keine E-Mail und reicht keine Bewerbung ein.
- Bei einem API-Ausfall bleiben bearbeitbare lokale Grundentwürfe verfügbar; bestehende Texte werden bei der Initialisierung nicht überschrieben.
- OpenAI-Speicherung ist deaktiviert; der API-Schlüssel bleibt serverseitig. Keine DB-Schemaänderung.

## Prüfung und Deployment

Alle 27 PHP-Testdateien liefen ohne Warnungen durch. Die neue Prüfung bestätigt den strukturierten Drei-Felder-Vertrag, die Ausfallentwürfe, das zweizeilige Instruktionsfeld, deaktivierte OpenAI-Speicherung und die benutzergebundene Sicherheitskennung. Beide PHP-Syntaxprüfungen, die Hilfeprüfung mit 3467 Checks, der Hilfeseed mit 1094 Checks, beide Dokumentationsgeneratoren und `git diff --check` bestanden. Externe KI-Antworten werden vor der Freigabe nicht mit echten Bewerbungsdaten getestet. Deploymentumfang ist `public_html/jobs.jema.business/index.php`; Konfiguration, Datenbank und Bestandsdaten bleiben unverändert.

Vorheriger bestätigter Live-Stand: Version 2.0.15. Rückweg ist ein separat freizugebender Upload der bestätigten 2.0.15-Datei. Keine Freigabe-ID in Git.
