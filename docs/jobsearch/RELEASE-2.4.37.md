# Release 2.4.37 – Anrede in KI-Motivationsschreiben

Stand: 21.09.2026. Ausgangspunkt: Fehlerreferenz `567A6CBF4C2E`.

## Ursache und Änderung

Die bisherige Prüfung erwartete die Anrede exakt unmittelbar nach
Empfängerblock oder einer einzigen Betreffzeile. «Grüezi» wurde nicht
erkannt; eine wirklich fehlende Anrede führte auch nach drei
KI-Wiederholungen zum Abbruch und liess den Bearbeitungsauftrag unerledigt.

Der Empfängerblock wird nun unabhängig von HTML-Leerzeilen zeilenweise
verglichen. Die Prüfung erkennt passende Anreden in den ersten fünf
Kopfzeilen. Fehlt eine Anrede wirklich, wird sie sprachabhängig und
deterministisch nach Betreff und vor dem bestehenden Haupttext ergänzt.
Der ursprüngliche Briefinhalt bleibt erhalten. Die übrigen
Qualitätsprüfungen, Benutzeranweisungen und die Sperre gegen erfundene
Fakten bleiben bestehen. Keine Datenmigration und kein automatischer Versand.

## Verifikation

53 lokale PHP-Tests und Syntaxprüfung bestanden. Der Regressionstest
belegt fehlende Anrede, Erhalt des Briefinhalts und «Grüezi» mit Betreff.
Hilfe- und Referenzgenerator mit `--check` geprüft. Der produktive Hash
ist nach Bereitstellung mit dem lokalen Artefakt identisch.
Ein authentifizierter KI-Lauf mit einer bewusst gewählten Bewerbung ist
fachlich separat abzunehmen; statische Tests beweisen keine Textqualität.

## Deployment

Quell-Commit `4e1676e866cf245d229158187dd918fd3074cbec` auf
`origin/feature/jema-jobs-ki-2.1.0` veröffentlicht. Ausgangsstand 2.4.36:
`public_html/jobs.jema.business/index.php`, 1’364’012 Bytes, SHA-256
`4c643c3314f9475f7528d71353848331de2c8fcf6a3bb152610de0dc1d6ace44`.
Vor dem Austausch als `index.php.bak-20260921-2.4.36-pre-2.4.37`
gesichert (Copy-Approval `15c1ce7f2a7f5ada8d1bb99df7bcc987`);
Sicherungshash identisch, Rechte `0644`.

Um 08:28 UTC wurde über die freigegebene Upload/Extract-Aktion
`1d1fd20f5a0a88331ccbc7b00e978699` genau `index.php` überschrieben.
Neues Artefakt lokal und produktiv: 1’366’124 Bytes, SHA-256
`82f601641116905ba2b28cd84e91da605b2a069382acbbd8bb926f9414e04edc`,
Rechte `0644`. Öffentlicher HTTPS-Abruf: HTTP 200, Titel «JeMa Jobs» und
Version 2.4.37. Keine Schema- oder produktiven Bewerbungsdatenänderungen
durch das Deployment. Ein authentifizierter KI-Bearbeitungslauf wurde
nicht durchgeführt und bleibt als fachliche Abnahme offen.
