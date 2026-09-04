# Version 2.0.4 – Ergebnissprache und Originalimport

Stand: 2026-09-04. Deployment nach externer TOTP-Freigabe ausgeführt.

Getrennte Tabellenübersetzung in die aktive App-Sprache; sprachgebundene Session-Treffer.
Originalbeschreibung bleibt unübersetzt und vollständig; kein SEO-Zusammenfassungsfallback.
Firmenadresse, Website, Telefon, E-Mail und Bewerbungskontakt werden aus strukturierten Inseratdaten übernommen.
Bestehende Firmenangaben bleiben erhalten; Wiederübernahme kann die Originalbeschreibung korrigieren.
Die Suchaktion wird im JavaScript-Request ausdrücklich mitgesendet.

Keine Schemaänderung, keine pauschale Datenmigration, keine Änderungen an Server-Secrets.
Eine Wiederübernahme ändert bewusst Originaltitel/-beschreibung/-ort, nicht private Notizen.
Vorhandene PDF-Dateien werden bei Wiederübernahme nicht automatisch ersetzt.

## Prüfung

Lokal bestanden: alle 20 PHP-Testdateien, beide Dokumentationsgeneratoren mit --check,
PHP-Syntaxprüfung, JavaScript-Syntaxprüfung des Suchhandlers und git diff --check.
Der Hilfe-Test berücksichtigt jetzt die bereits vorhandenen vier Suchschritte und prüft
weiterhin eine identische Schritt-/Tippstruktur in allen fünf Sprachen.

tests/job_content_language_test.php prüft die echten Produktionshelfer mit synthetischen französischen Inseraten, fünf Übersetzungssprachen, fehlendem Originaltext, UTF-8 und Firmen-/Kontaktdaten.
Diese Fixtures prüfen Datenwege, nicht die tatsächliche sprachliche Qualität einer Live-Modellantwort.
Produktions-API und angemeldete fachliche Live-Abnahme stehen aus: im verfügbaren In-App-Browser besteht keine nutzbare angemeldete Sitzung.

## Deployment-Nachweis

- Quell-Commit: a55053f (Branch feature/jema-jobs-ki-2.0.0).
- Ziel: public_html/jobs.jema.business/index.php; Version 2.0.4.
- Externe Freigabe: 93de54fcf84292d0c2481597eaa59895; Ausführung bestätigt.
- Remote-Zeitpunkt: 2026-09-04T10:49:24+00:00.
- SHA-256 lokal und remote: 42fa72191b5e1a237fa8225e8d050c300d9f1b8b2c1fabc171a2028ec100343b.
- Dateigröße 913975 Bytes, Berechtigung 0644.
- Öffentliche Login-Seite: HTTP 200, Versionsanzeige 2.0.4, Loginformular vorhanden, keine sichtbare PHP-Fatal-/Parse-Fehlermeldung.
- Kein produktiver Testimport, keine Bestandskorrektur und keine Profiländerung als Deploymentprobe ausgeführt. Angemeldete Abnahme von Übersetzung, Firmen-/Kontaktübernahme und Originaltext bleibt separat offen.

## API-Vertrag

Der getrennte Übersetzungsschritt nutzt das strukturierte Responses-Ausgabeformat:
[OpenAI Responses](https://developers.openai.com/api/reference/cli/resources/responses/methods/create).
Schema-/Locale-Validierung ist kein unabhängiger sprachwissenschaftlicher Nachweis.
