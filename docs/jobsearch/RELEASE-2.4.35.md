# Release 2.4.35 – KI-Schreibkontext

Stand: 21.09.2026. Implementierung und lokale Tests; produktive Bereitstellung und
authentifizierte fachliche Abnahme sind separat zu dokumentieren.

## Anlass und Umsetzung

Der gemeldete KI-Fehler C95960CE42D3 entstand nach drei Versuchen durch die
Briefstrukturprüfung: Sie behandelte eine gültige Betreffzeile zwischen
Empfängerblock und Anrede als fehlende Anrede. Ein alter, unvollständiger
Adressblock konnte zudem bei der Reparatur hinter einem neuen Block stehen.

`applicationWritingContext()` ersetzt den breiten bisherigen Prompt. Die
KI erhält nur den aktuellen Stellen- und Firmenkontext und die bei jedem
Aufruf frisch ausgewählten aktuellen Stammdaten-Lebensläufe des Typs `cv`,
jeweils die jüngste Modifikation pro Metadatensprache. Frühere Lebensläufe,
andere Dokumenttypen und ältere Bewerbungstexte werden nicht übergeben. Bei
einer Überarbeitung sind ausschliesslich die aktuell abgesendeten
Formulartexte die Textvorlage. Eine leere Instruktion erzeugt ohne Altentwurf
neu. Die Aufgabenregeln betonen den Nutzen für den konkreten Arbeitgeber
und vermeiden quantifizierte Erfolge als Standard. Neue Entwürfe mit solchen
Zahlen werden vor dem Speichern erneut zur Korrektur gegeben. Eine
ausdrückliche Benutzeranweisung hat Vorrang.

Die Briefprüfung akzeptiert nun einen einzelnen Betreff vor der Anrede.
Unvollständige Altadressen werden ersetzt statt gestapelt. Ein KI-Fehler
überschreibt keine Bewerbungstexte; die gesendete Instruktion und die
aktuellen Formulartexte werden für die Rückkehr zum Editor in der Sitzung
erhalten. Keine Datenbankmigration und kein automatischer Versand.

## Prüfungen und Grenzen

52 lokale PHP-Tests einschliesslich CV-Auswahl, Kontext-Isolation,
Empfängerblock/Betreff, Bearbeitungsinstruktion und Qualitätstest bestanden.
PHP-Syntax und Hilfe-Generatorprüfung bestanden; die Referenz wurde neu
generiert und mit `--check` zu prüfen. Die [offizielle OpenAI-Dokumentation
zu strukturierten Ausgaben](https://developers.openai.com/api/docs/guides/structured-outputs)
bestätigt die verwendete JSON-Schema-Antwort; die [Dateieingabe-Dokumentation](https://developers.openai.com/api/docs/guides/file-inputs)
führt PDF, DOC und DOCX als unterstützte Responses-Dateitypen auf.

Quelltests prüfen Datenfluss und deterministische Regeln, nicht die
sprachliche Qualität einer realen KI-Antwort. Ein angemeldeter Lauf mit
einem bewusst gewählten Testentwurf ist für die fachliche Abnahme weiter
erforderlich; bestehende produktive Bewerbungstexte werden nicht zu
Testzwecken überschrieben.

## Deployment-Nachweis

Ausgangsstand: Version 2.4.34, produktive Datei
`public_html/jobs.jema.business/index.php`, SHA-256
`dbc5afe90313ecd18b41dd9a01a41dbf91951eba13b0662c51e05127bf5d0248`.
Quell-Commit, neues Artefakt, Sicherung, Approval-IDs, produktiver Hash und
HTTPS-Prüfung werden nach dem Deployment ergänzt.
