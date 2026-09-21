# Not-Update 2.4.38 – vorhandene Bewerbungstexte schützen

Stand: 21.09.2026. Anlass: Ein blosses Öffnen der Bewerbungsseite konnte
Begleit-E-Mail und Motivationsschreiben bereits bereinigen oder den
Empfängerblock ändern. Ein KI-Klick ohne Instruktion ersetzte alle Texte.

## Änderung

- Die Bewerbungsseite liest vorhandene Texte, ohne sie zu initialisieren.
- Die Vorbereitung ergänzt ausschliesslich leere Textfelder; gefüllte
  Felder behalten ihren exakten Inhalt.
- Der KI-Button ohne Instruktion ergänzt nur leere Felder. Sind alle drei
  Felder gefüllt, schreibt er nichts und fordert eine Änderungsanweisung an.
- Eine ausdrücklich eingegebene KI-Instruktion kann vorhandene Texte
  weiterhin gezielt überarbeiten. Manuelles Speichern bleibt möglich.
- Keine Datenmigration, kein automatischer E-Mail-Versand.

## Verifikation und Deployment

Lokale PHP-Syntax, 54 PHP-Tests sowie Hilfe- und Referenzgenerator wurden
geprüft. `application_text_preservation_test.php` sichert den lesenden
Aufruf und den Fill-only-Abgleich ab.

- Quell-Commit: `de891079bba333517320c160c0eda413ff774b0c` (gepusht).
- Vorheriges Live-`index.php`: SHA-256
  `82f601641116905ba2b28cd84e91da605b2a069382acbbd8bb926f9414e04edc`.
- Backup: `public_html/jobs.jema.business/index.php.bak-20260921-2.4.37-pre-2.4.38`;
  Hash identisch mit dem vorherigen Live-Stand.
- Connector-Backup: Proposal `a835740caff0848ca1e38e5987ccc428`.
- Connector-Upload/Extraktion: Proposal `2d0c87f97c7938883127e84a14573196`.
- Aktuelles Live-`index.php`: SHA-256
  `4496066a8b3ec69afad1a86297b6195a6ab2087798c1bb065a642896ae2eea34`,
  identisch mit lokalem Release-Asset; 1'367'053 Byte, Modus 0644.
- Anonymer HTTPS-Smoke-Test `/?page=login`: HTTP 200, Version 2.4.38 sichtbar.
- Authentifizierte Funktions- und Latenzabnahme: noch nicht durchgeführt;
  dafür ist eine angemeldete Sitzung nötig.

## Performance-Nachprüfung

Nach `Bewerbung gespeichert` gibt es keinen expliziten Sleep/Timer. Der
Redirect auf die Bewerbungsseite führte vor 2.4.38 erneut die automatische
Textinitialisierung aus; diese konnte bei fehlenden Empfängerdaten eine
Web-/KI-Recherche auslösen. Dieser unnötige GET-Pfad ist in 2.4.38 entfernt.
Weitere unabhängig zu messende Latenzpfade: Die vollständige Google-Kalender-
Synchronisation läuft beim ersten authentifizierten Request einer Sitzung und
bei relevanten Bewerbungsänderungen synchron; bei Fehlschlag erfolgt ein
erneuter Versuch im nächsten Request. Aus Gründen der Kalenderkonsistenz
wurde sie nicht ohne Ersatz deaktiviert. Zudem aktualisiert die App bei
jedem authentifizierten Request zwei Präsenzdatensätze. Die 900-ms-
Autosave-Entprellung liegt vor dem Speichern, nicht nach der Statusmeldung.
