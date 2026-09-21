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
Generatoren und produktiver Hash werden vor Bereitstellung geprüft.
Ein authentifizierter KI-Lauf mit einer bewusst gewählten Bewerbung ist
fachlich separat abzunehmen; statische Tests beweisen keine Textqualität.

## Deployment

Ausgangsstand, Sicherung, Freigabe, Server-Hash, Commit und Live-Abruf
werden nach dem Deployment ergänzt. Bis dahin ist 2.4.37 nicht als
produktiv verifiziert zu betrachten.
