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

Lokale Tests, Quell-Commit, Backup, Approval-ID, Live-Hash und anonyme
HTTPS-Prüfung werden nach der Durchführung eingetragen. Ein
authentifizierter End-to-End-KI-Lauf ist gesondert zu protokollieren.
