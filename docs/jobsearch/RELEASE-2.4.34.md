# Release 2.4.34 – KI-Bearbeitungsanweisung

Stand: 21.09.2026. Lokal geprüft und produktiv bereitgestellt;
angemeldete fachliche Abnahme getrennt nachzuweisen.

## Anlass und Umfang

Der gemeldete Auftrag «Motivationsschreiben 30 % ausführlicher; Erfolgszahlen
komplett weglassen» konnte bisher von allgemeinen Längenvorgaben und
Korrekturrunden überlagert werden. Die bisherige Prüfung verlangte nur
irgendeine Änderung an E-Mail und Brief. Sie kontrollierte weder die
gewünschte Verlängerung noch das Weglassen quantitativer Erfolge.

Manuelle Bearbeitung erkennt nun ausdrücklich benannte Zielfelder und
erhält unbetroffene Texte. Bei jeder KI-Wiederholung steht die unveränderte
Benutzeranweisung am Ende des Requests; nur der zuletzt abgelehnte Entwurf
mit seinem Fehler kommt hinzu. Der gemeldete deutsche Fall erhält eine
zusätzliche serverseitige Längen- und Erfolgszahlenprüfung. Hausnummer und
Postleitzahl des Empfängers bleiben davon ausgenommen. Scheitert der
KI-Lauf, überschreibt er die bestehenden Texte nicht; die eingegebene
Instruktion bleibt sichtbar. Kein Datenbankschemawechsel und kein
automatischer Versand.

## Lokale und produktive Nachweise

Lokal: 50 PHP-Tests ohne Fehler (`php -n`), PHP-Syntax,
Hilfegenerator und Referenzgenerator mit `--check` bestanden. Der
Regressionstest `application_edit_instruction_test.php` prüft den
konkreten ROCKEN-Auftrag als Ablehnungs- und Erfolgsfall. Chromium-Tests
für KI-Dialog und Rich-Text-Editor mit gebündeltem Playwright bestanden.
Die Tests verwenden synthetische Texte und keine OpenAI- oder
Produktionsdatenbankverbindung.

Lokales Deploymentartefakt `public/index.php`: 1’363’220 Bytes, SHA-256
`dbc5afe90313ecd18b41dd9a01a41dbf91951eba13b0662c51e05127bf5d0248`.

Read-only-Ausgangsstand auf cPanel:
`public_html/jobs.jema.business/index.php`, Version 2.4.33,
1’352’736 Bytes, Berechtigung `0644`, SHA-256
`1aae35cd4b458841b10692b888b302a7992d4056dcddebace9fab55fd0e9e7d7`.
Quell-Commit: `4bc43b6231aba8914015a15aae3193c39eec4735`.
Am 21.09.2026 um 07:26 UTC wurde genau dieser produktive Ausgangsstand
unter `public_html/jobs.jema.business/index.php.bak-20260921-2.4.33-pre-2.4.34`
gesichert. Die Sicherung hat 1’352’736 Bytes, Berechtigung `0644` und
denselben SHA-256-Hash wie der Ausgangsstand. Copy-Approval:
`501a0cb400e0f2ad03766ad01ea7cd75`.

Um 07:27 UTC wurde `public_html/jobs.jema.business/index.php` über ein
einzeln freigegebenes Write-Proposal ersetzt. Write-Approval:
`425cc280f61626da2a67211544a0ac64`. Die produktive Datei hat danach
1’363’220 Bytes, Berechtigung `0644` und SHA-256
`dbc5afe90313ecd18b41dd9a01a41dbf91951eba13b0662c51e05127bf5d0248`;
der Hash stimmt mit dem lokalen Artefakt überein. Ein öffentlicher
HTTPS-Abruf von `https://jobs.jema.business/` lieferte HTTP 200, den Titel
«JeMa Jobs» und die Versionskennung `2.4.34`. Es gab keine
Datenbankschemaänderung und keine Änderung an produktiven Bewerbungsdaten.

Die echte sprachliche Qualität eines KI-Laufs mit Lebenslauf und
Stelleninserat ist durch die lokalen Tests nicht bewiesen. Eine
angemeldete Abnahme muss mit einem ausdrücklich gewählten Entwurf
erfolgen; bestehende Bewerbungstexte werden nicht bloss zum Test
überschrieben.
