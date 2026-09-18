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

Quell-Commit `817552e` wurde auf
`origin/feature/jema-jobs-ki-2.1.0` übertragen.

Produktiv am 18.09.2026: Die bisherige Datei (`2.4.31`, 1’336’548 Bytes,
SHA-256 `8cdda21ff582c1b8522b9ac685252ec4abc638a8ce06f84540ab1f0b30dead95`)
wurde als `index.php.bak-20260918-2.4.31-pre-2.4.32` gesichert. Der Hash der
Sicherung stimmt mit dem Ausgangszustand überein. Die neue Datei wurde über
den freigegebenen cPanel-Connector eingespielt; produktive Grösse, SHA-256
und Berechtigung `0644` stimmen mit dem lokalen Artefakt überein. Die
öffentliche HTTPS-Anmeldeseite antwortet mit HTTP 200 und zeigt `2.4.32`.

Ein authentifizierter KI-Qualitätstest auf einer produktiven Bewerbung ist
noch offen: Der separat gestartete Prüf-Tab besitzt keine angemeldete Sitzung.
Bestehende Bewerbungstexte wurden für die Prüfung nicht verändert. Ein
unauthentifizierter Direktaufruf einer geschützten Bewerbungs-URL gibt nur den
Seitenkopf aus; dieser vorbestehende Auth-Redirect-Mangel ist nicht Teil
dieses Releases.
