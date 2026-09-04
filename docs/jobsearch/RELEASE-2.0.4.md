# Version 2.0.4 – Ergebnissprache und Originalimport

Stand: 2026-09-04. Deployment vorbereitet, noch nicht freigegeben/ausgeführt.

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
Produktions-API und angemeldete Live-Abnahme stehen bis nach Deployment aus.

## API-Vertrag

Der getrennte Übersetzungsschritt nutzt das strukturierte Responses-Ausgabeformat:
[OpenAI Responses](https://developers.openai.com/api/reference/cli/resources/responses/methods/create).
Schema-/Locale-Validierung ist kein unabhängiger sprachwissenschaftlicher Nachweis.
