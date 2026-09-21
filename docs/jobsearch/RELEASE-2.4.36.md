# Release 2.4.36 – Schweizer Bewerbungstexte

Stand: 21.09.2026. Produktive Bereitstellung und fachliche Abnahme werden
getrennt dokumentiert.

## Umsetzung

Der recherchierte [Schweizer Best-Practice-Leitfaden](SWISS_APPLICATION_WRITING_GUIDE.md)
ist Teil jeder Schreibanfrage. Die KI verbindet Stellenanforderungen mit
aktuellen CV-Belegen und beschreibt den Nutzen für den künftigen Arbeitgeber.
Bei Vermittlern wird der Empfänger korrekt adressiert und der (gegebenenfalls
unbekannte) Auftraggeber als Nutzniesser benannt. Ein unabhängiger,
simulierter Empfänger-Prüflauf bewertet fertige angeforderte Texte gegen
Inserat, aktuelle CVs, Rollen und Benutzeranweisung; höchstens zwei
gezielte Überarbeitungen folgen auf konkrete Mängel.

Der Prüflauf ist keine reale Empfängerreaktion und keine Erfolgsgarantie.
Die CV-Auswahl erfolgt bei jedem Aufruf neu nach Änderungsdatum je Sprache.
Kein Datenbankschema, keine Datenmigration, kein automatischer Versand.

## Prüfungen

53 lokale PHP-Tests einschließlich neuem Rollen-/Leitfadentest bestanden;
PHP-Syntax geprüft. Hilfe in fünf Sprachen mit `--check` geprüft;
Referenz nach Codeänderung generiert und mit `--check` geprüft. Ein
authentifizierter produktiver KI-Lauf ist nicht nachgewiesen und wird nicht
aus Quelltests abgeleitet. Die Browser-Automation wurde vor dem Zugriff vom
Computer-Use-Dienst gestoppt, weil die URL des Edge-Fensters nicht sicher
bestimmt werden konnte.

## Deployment-Nachweis

Geprüfter Quell-Commit `8ac16b90171f06fb3739a1d5d6658625fe8546c6`
auf `origin/feature/jema-jobs-ki-2.1.0` veröffentlicht. Dieses
Dokumentations-Nachtragscommit ändert das bereitgestellte PHP-Artefakt nicht.

Ausgangsstand 2.4.35: `public_html/jobs.jema.business/index.php`, 1’355’665
Bytes, SHA-256 `3bf6b8dbd86710b53257e07cb1c6ce6f36246686b22a56095ff6904960d92338`.
Vor dem Austausch als `index.php.bak-20260921-2.4.35-pre-2.4.36`
gesichert; die Sicherung hat denselben Hash, 1’355’665 Bytes und Rechte
`0644` (Copy-Approval `475cb9848f78f2e18be32f11e93c1f89`).

Um 08:15 UTC per freigegebenem Upload/Extract
(`de0920c0077c5f073b9f86978e1ff779`) genau eine Datei überschrieben.
Neues Artefakt lokal und auf Server: 1’364’012 Bytes, SHA-256
`4c643c3314f9475f7528d71353848331de2c8fcf6a3bb152610de0dc1d6ace44`,
Rechte `0644`. Öffentlicher HTTPS-Abruf: HTTP 200, Titel «JeMa Jobs» und
Version 2.4.36. Keine Schemaänderung, keine Bewerbungstexte durch das
Deployment geändert. Ein echter KI-Aufruf ist weiter fachlich zu prüfen.
