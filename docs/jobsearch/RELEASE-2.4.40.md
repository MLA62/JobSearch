# Release 2.4.40 – KI-Abbruchkriterien und CRM-Kontakt

Stand: 21.09.2026. Anlass: Fehlerreferenz `C6970452D5F0`.
Die simulierte Empfängerprüfung blockierte einen ansonsten technisch
gültigen Bewerbungstext mit unbelegten Stilforderungen. Die Entscheidung
über harte und beratende Prüfungen ist im
[Abbruchkriterien-Audit](AI_ABORT_GATE_AUDIT.md) dokumentiert.

## Änderung

- Der aktuelle Empfänger wird bei jedem KI-Aufruf aus den eigenen CRM-Daten
  gelesen. Ein dreizeiliger alter Briefkopf verdrängt eine inzwischen
  bekannte Kontaktperson nicht mehr; ein vollständig manuell geänderter
  vierzeiliger Block bleibt erhalten.
- Fehlende Grussformel und voller Name in der Begleit-E-Mail werden vor
  der Qualitätsprüfung ergänzt. Ein bereits vollständiger HTML-Text wird
  bytegenau belassen.
- Interne Zitatmetadaten, Wortzahlpräferenzen und die simulierte
  Empfängerprüfung führen höchstens zu einem Verbesserungsversuch.
  Ein Modellurteil oder Ausfall ist kein Abbruchgrund.
- Ein bereits strukturell und gemäss Benutzeranweisung gültiger Entwurf
  bleibt bei einem schlechteren redaktionellen Folgeversuch erhalten.
- Technische API-Fehler ohne gültigen Entwurf, fehlende Zieltexte,
  nicht umgesetzte ausdrückliche Änderungen und unvollständige Briefe
  bleiben verbindliche Fehler. Bestehende Texte werden dann nicht
  überschrieben. Es wird nichts automatisch versendet.

## Verifikation und Deployment

- Quell-Commit: `57064dc` auf `feature/jema-jobs-ki-2.1.0`, nach GitHub gepusht.
- Lokal: `php -n -l public/index.php`, Hilfe- und Referenzgeneratorprüfung,
  `git diff --check` und 55 PHP-Tests erfolgreich.
- Unmittelbar vor dem Austausch hatte die Live-Datei den SHA-256-Hash
  `0f22cfdde3e9b6679e849281c4e7a07a556808f7b3ffec8441438af11aa232a8`.
  Sie wurde unter
  `public_html/jobs.jema.business/index.php.bak-20260921-2.4.39-pre-2.4.40`
  gesichert (Approval-ID `40f39e9d5dd6cf3ab934f79ba692313b`).
- Der Upload ersetzte ausschliesslich `public_html/jobs.jema.business/index.php`
  (Approval-ID `1e34debd2042d8ff3d83a97a3679d50b`); zusätzlich erzeugte
  der Connector ein eigenes Datei-Backup.
- Die Live-Datei hat danach den mit der lokal getesteten Datei identischen
  SHA-256-Hash `180aa5c9bef497e953ae83c1b1c0ecd67931fb602e509230703bdff0a4b8d3de`
  und weiterhin Berechtigung `0644`. Der anonyme HTTPS-Aufruf von
  `https://jobs.jema.business/` antwortete mit HTTP 200 und zeigte 2.4.40.
- Ein authentifizierter End-to-End-KI-Lauf mit einer realen Bewerbung wurde
  bei diesem technischen Smoke-Test nicht durchgeführt; dies ist keine
  Behauptung einer fachlichen Live-Abnahme.
