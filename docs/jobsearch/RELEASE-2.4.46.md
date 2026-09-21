# Release 2.4.46 – Begrenzte KI-Bewerbungsvorbereitung

Stand: 21.09.2026. Beim betroffenen Job dauerte «Bewerbung vorbereiten»
bereits mehr als elf Minuten. Der bisherige Button schloss bei Abbruch nur
die Browsernavigation; ob die serverseitige KI-Kette beendet war, blieb
unbestätigt. Der Lauf konnte aus Inseratanalyse, öffentlicher Recherche,
Textentwurf, Korrekturversuchen und simuliertem Empfängerreview bestehen.

Die Vorbereitung erhält ein Gesamtbudget von 180 Sekunden und fünf
KI-API-Aufrufen. cURL prüft Abbruch und Frist auch während eines laufenden
Abrufs. Ein benutzerbezogenes Lock verhindert parallele Vorbereitungen.
Nach dem Klick auf Abbrechen bleibt das Fenster bis zur Serverantwort offen.
Ohne Abschlussantwort wird kein Erfolg oder bestätigter Abbruch behauptet.

Der Bewerbungsentwurf wird vor der optionalen erneuten Inseratanalyse
gespeichert. Bei Abbruch, Budgetende oder technischem Textfehler bleibt er
bearbeitbar und die Meldung unterscheidet die Ursachen. Ein bestehender Text
wird dadurch nicht überschrieben. Die tatsächliche externe API-Abrechnung
ist nicht Teil der App und wird hier nicht behauptet.

Lokale Prüfung: PHP-Syntax, Hilfe- und Referenzgenerator, 57 PHP-Tests
sowie der KI-Arbeitsdialog im Browser mit simuliertem Server einschliesslich
bestätigtem Abbruch. Es wurde keine Datenbankschemaänderung vorgenommen.

Deployment am 21.09.2026: Der bisherige Live-Stand wurde als
`index.php.bak-20260921-2.4.45-pre-2.4.46` gesichert. Die produktive
`index.php` hat SHA-256
`06283bc678db452350cd4781554ddcb4ab085d02827c8b3c85d0663433206148`;
dies stimmt mit dem lokalen Build überein. Die authentifizierte Live-Seite
zeigt Version 2.4.46. Ein realer KI-Lauf wurde zur Vermeidung unnötiger
API-Kosten und neuer Produktionsdatensätze nicht gestartet; Abbruch und
Fehlermeldungen sind durch den Browser-Test mit simuliertem Server geprüft.
