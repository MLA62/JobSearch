# Release 2.4.35 – KI-Schreibkontext

Stand: 21.09.2026. Implementierung, lokale Tests und produktive Bereitstellung
nachgewiesen; authentifizierte fachliche Abnahme steht aus.

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

52 lokale PHP-Tests (`php -n`) einschliesslich CV-Auswahl, Kontext-Isolation,
Empfängerblock/Betreff, Bearbeitungsinstruktion und Qualitätstest bestanden.
PHP-Syntax und Hilfe-Generatorprüfung bestanden; die Referenz wurde neu
generiert und mit `--check` geprüft. Die [offizielle OpenAI-Dokumentation
zu strukturierten Ausgaben](https://developers.openai.com/api/docs/guides/structured-outputs)
bestätigt die verwendete JSON-Schema-Antwort; die [Dateieingabe-Dokumentation](https://developers.openai.com/api/docs/guides/file-inputs)
führt PDF, DOC und DOCX als unterstützte Responses-Dateitypen auf.

Quelltests prüfen Datenfluss und deterministische Regeln, nicht die
sprachliche Qualität einer realen KI-Antwort. Ein angemeldeter Lauf mit
einem bewusst gewählten Testentwurf ist für die fachliche Abnahme weiter
erforderlich; bestehende produktive Bewerbungstexte werden nicht zu
Testzwecken überschrieben.

## Deployment-Nachweis

Quell-Commit `479036148a45c66b457664ab6effdbee6c6c44cd`, auf
`origin/feature/jema-jobs-ki-2.1.0` veröffentlicht. Ausgangsstand:
Version 2.4.34, produktive Datei
`public_html/jobs.jema.business/index.php`, SHA-256
`dbc5afe90313ecd18b41dd9a01a41dbf91951eba13b0662c51e05127bf5d0248`.
Vor dem Austausch um 07:48 UTC als
`index.php.bak-20260921-2.4.34-pre-2.4.35` gesichert (Copy-Approval
`1501c17afdbfe4a25ec88ebbb5445d55`). Sicherung: 1’363’220 Bytes,
Berechtigung `0644`, identischer SHA-256-Hash.

Neues lokales Artefakt: 1’355’665 Bytes, SHA-256
`3bf6b8dbd86710b53257e07cb1c6ce6f36246686b22a56095ff6904960d92338`.
Um 07:49 UTC mit Write-Approval `6be55477161496347c700d8ca5579200`
auf die produktive Datei geschrieben. Der anschliessend gelesene Server-Hash
ist mit dem lokalen identisch; Berechtigung weiterhin `0644`. Ein
öffentlicher HTTPS-Abruf lieferte HTTP 200, den Titel «JeMa Jobs» und die
Versionskennung 2.4.35. Die Browser-Sitzung war bei der Live-Prüfung nicht
mehr angemeldet; die Anmeldeseite zeigte ebenfalls 2.4.35. Ein
authentifizierter KI-Schreibversuch wurde deshalb nicht durchgeführt und
darf nicht als erfolgreich behauptet werden. Keine Schema- oder
produktiven Bewerbungsdatenänderungen durch das Deployment.
