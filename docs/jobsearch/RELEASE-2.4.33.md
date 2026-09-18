# Release 2.4.33 – KI-Anzeige und Bewerbungsqualität

Stand: 18.09.2026. Produktive Freigabe und fachliche Abnahme sind noch offen.

## Umfang

- KI-Dialog: aktionsbezogene Information und sekündlicher Zeitmesser; keine
  erfundenen Server-Phasen.
- Neue Texte: alte Entwürfe aus dem Generierungskontext entfernt; aktuelle
  Lebensläufe je Sprache weiterhin frisch geladen. Quellenbezüge zum Inserat
  und CV sind interne Pflichtfelder und werden serverseitig geprüft.
- Begleit-E-Mail und Brief: strengere Inhalts- und Strukturprüfung, konkrete
  Korrekturrunden; keine generischen Ersatztexte nach gescheiterter KI.
- Kein Datenbankschemawechsel. Bestehende Bewerbungstexte werden nicht
  automatisch neu geschrieben. Bei gescheiterter erstmaliger Erzeugung bleibt
  der Datensatz als Entwurf mit leeren Textfeldern bestehen.

## Prüf- und Freigabenachweis

Lokal: 49 PHP-Tests ohne Fehler (`php -n`), PHP-Syntax, beide
Dokumentationsgeneratoren mit `--check`, Browser-Tests für den tatsächlichen
KI-Dialog und Rich-Text-Editor bestanden. Das Artefakt `public/index.php`
hat 1’352’736 Bytes und SHA-256
`1aae35cd4b458841b10692b888b302a7992d4056dcddebace9fab55fd0e9e7d7`.

Read-only cPanel-Vergleich: Die vorherige produktive `index.php` hat
1’347’741 Bytes, Berechtigung `0644` und SHA-256
`4bd386e99e968726d1ed39af6fd5ce1b9d7663f4773efe438969d4a51bd59bff`.
Vor dem Ersatz ist eine Sicherung mit genau diesem Hash erforderlich.

Commit: ausstehend. cPanel-Vorschlag/Freigabe: ausstehend. Remote-Hash,
öffentliche HTTPS-Prüfung und angemeldeter KI-Abnahmelauf: ausstehend.
