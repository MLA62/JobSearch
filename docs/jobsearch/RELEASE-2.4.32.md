# Release 2.4.32 – Briefqualität und Endkundenprofil

## Umfang

Die Motivationsschreiben-KI erhält eine verbindliche Briefstruktur und einen
nachgelagerten serverseitigen Qualitätscheck in allen fünf App-Sprachen.
Unvollständige Rückläufe werden mit konkreten Gründen erneut angefordert;
nach drei mangelhaften Antworten wird kein erfolgreicher KI-Text gespeichert.

Bei einem ausdrücklich zugeordneten Vermittler werden Vermittler und
identifizierte Endkundenfirma getrennt. Die KI erhält Stellenanforderungen,
Firmenbranche, Grössenangabe und Notizen sowie einen begrenzten Auszug der
hinterlegten offiziellen Endkunden-Website, sofern diese sicher erreichbar
ist. Ein nicht eindeutig bekannter Endkunde wird nicht geraten.

## Daten und Rückweg

Keine Datenbankmigration oder automatische Änderung bestehender Bewerbungen.
Für die produktive App ist nur `public/index.php` zu ersetzen. Die bisherige
produktive Datei muss vor dem Austausch gesichert und gehasht werden.

## Nachweise

Lokal: 48 PHP-Tests ohne Fehler (`php -n`), zusätzlicher Rich-Text-Test mit
DOM, PHP-Syntaxprüfung und Generator-Checks für Hilfe und Referenz bestanden.
Artefakt `public/index.php`: 1’347’741 Bytes, SHA-256
`4bd386e99e968726d1ed39af6fd5ce1b9d7663f4773efe438969d4a51bd59bff`.

Commit und produktive Bereitstellung werden nach der externen cPanel-Freigabe
separat protokolliert. Ein echter KI-Qualitätstest auf einer produktiven
Bewerbung ist von der blossen Versions-/Loginprüfung getrennt und darf
bestehende Texte nicht unbeabsichtigt überschreiben.
