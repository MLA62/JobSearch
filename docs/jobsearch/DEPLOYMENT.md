# Deployment und Betrieb

Stand: 2026-09-09. Release 2.3.6 ist produktiv verifiziert.

Quell-Commit: `5ce6b4cbbffbbc4efecce2e385468114be84327`.
Produktiver `index.php`-SHA-256 (2.3.6): `e969ef8e146468e1efffd179de7ae572feb2e9e41f84179a0a91cc9ccf47b1af`, 1'160'890 Bytes, Modus 0644.
Produktiver `app.css`-SHA-256 (2.3.6): `95cac6915638ec4c63259fb343cd82862320481b68bf664570d081855484acf7`, 50'965 Bytes, Modus 0644.
Weitere produktive Dateien entsprechen den geprüften lokalen Bytes: `.htaccess` `e23b763acf3ca402a5e48f1ce566a516480f13f72569e8f7fdcf6888048f62e0`, `layout.css` `fc8e78d3fce0e8fe1ef557c4a4b60aee50ebd435a5901b5b21bca4530f9b5d20`, `layout.js` `2a153bfea6d9b63a522ccbdc9172cd7d39e9bb0cbce8224dc2356c02842975f1`.
TOTP-Approval: `cfdd1b4cdb4c81c65b23123b2ef9e633` (ausgeführt 2026-09-09). Öffentliche Seite liefert HTTP 200, Version 2.3.6 sowie HSTS, CSP, nosniff, DENY und no-referrer.

## Vorheriges produktives Release 2.3.5

Quell-Commit: `1c1a0ae1414ab74bc280b9d3dae52dff40d2f34d`.
Produktiver `index.php`-SHA-256 (2.3.5): `20a7271c7de9425e684b16268689656137e6949fc9d5edc06b315d60a0a62549`, 1'160'201 Bytes, Modus 0644.
Produktiver `app.css`-SHA-256 (2.3.5): `95cac6915638ec4c63259fb343cd82862320481b68bf664570d081855484acf7`, 50'965 Bytes, Modus 0644.
Weitere produktive Dateien entsprechen den geprüften lokalen Bytes: `.htaccess` `e23b763acf3ca402a5e48f1ce566a516480f13f72569e8f7fdcf6888048f62e0`, `layout.css` `fc8e78d3fce0e8fe1ef557c4a4b60aee50ebd435a5901b5b21bca4530f9b5d20`, `layout.js` `2a153bfea6d9b63a522ccbdc9172cd7d39e9bb0cbce8224dc2356c02842975f1`.
TOTP-Approval: `9c93cb3e9058094f03e3f3baeb3c8f58` (ausgeführt 2026-09-09). Öffentliche Seite liefert HTTP 200, Version 2.3.5 sowie HSTS, CSP, nosniff, DENY und no-referrer. Der erste Seitenaufruf führte die idempotente Anlage von `admin_ai_memory` ohne protokollierten Migrationsfehler aus.

## Vorheriges produktives Release 2.3.4

Quell-Commit: `19a2e414be5d4aa504943148c9a05eb528577c53`.
Produktiver `index.php`-SHA-256 (2.3.4): `5ba5f649dbea8ca203d992bf8ff7dc9261597f1e9ed66cf0c7cca0f1ed9007c5`, 1'153'775 Bytes, Modus 0644.
Produktiver `app.css`-SHA-256 (2.3.4): `4dbefea60d2976453cd05de869d69952aa25b83ffd07b0b647e1596943d4204d`, 51'068 Bytes, Modus 0644.
TOTP-Approval: `f60329e108af2ead3d48f52b5d3f8406` (ausgeführt 2026-09-09).

## Vorheriges produktives Release 2.3.3

Quell-Commit: `dbde138c00e9026ddb33a25970c8e051db790103`.
Produktiver `index.php`-SHA-256 (2.3.3): `dbd9664839b775c8a940f184fda6e345e04aba3bc8311f690bb30ee9bb9c6ac1`, 1'144'604 Bytes, Modus 0644.
Produktiver `app.css`-SHA-256 (2.3.3): `8b1927163844191a9c499a504a2eb6c4aed4a754217369cbb0a238a701fe43c7`, 49'970 Bytes, Modus 0644.
Weitere produktive Dateien entsprechen den geprüften lokalen Bytes: `.htaccess` `e23b763acf3ca402a5e48f1ce566a516480f13f72569e8f7fdcf6888048f62e0`, `layout.css` `fc8e78d3fce0e8fe1ef557c4a4b60aee50ebd435a5901b5b21bca4530f9b5d20`, `layout.js` `2a153bfea6d9b63a522ccbdc9172cd7d39e9bb0cbce8224dc2356c02842975f1`.
TOTP-Approval: `28492b06ae4402bf9dba9846b33791dc` (ausgeführt 2026-09-09). Öffentliche Seite liefert HTTP 200, Version 2.3.3 sowie HSTS, CSP, nosniff, DENY und no-referrer.
Neuinstallation und Wiederherstellung: [REBUILD.md](REBUILD.md).

## Freigabegrenzen

Produktionsaenderungen erfolgen ueber cPanel Mail Control mit externer TOTP-Freigabe. Keine FTPS-Abkuerzungen, keine Zertifikatspruefung abschalten, keine alten Freigaben erneut verwenden. Die Zustimmung zu einer Codeaenderung ersetzt nicht die erforderliche externe Ausfuehrungsfreigabe.

Niemals `config.php`, private Dateien, Datenbankinhalte oder Secrets aus dem Entwicklungsstand ueber die Produktion schreiben. Eine Workflow-Datenbereinigung ist ein eigener Vorgang mit Vorschau; sie gehoert nicht automatisch zum PHP-Deployment.

## Produktionspfade und Abhaengigkeiten

- Webadresse: https://jobs.jema.business/
- PHP-Einstieg: `public_html/jobs.jema.business/index.php`
- Private Konfiguration und `storage/` bleiben auf dem Ziel unveraendert.
- App-CSS und Layout-JavaScript werden ab 2.3.0 lokal aus `/assets/` geladen; externe CDN-Laufzeitabhängigkeiten entfallen.
- Vor 2.3.0 verwendete CDN-Pins bleiben nur als historische Nachweise in älteren Release-Dokumenten erhalten.
- docs/, tests/, SQL und Entwicklerwerkzeuge gehoeren nicht in den oeffentlichen Webroot.

Baseline vor dieser Runde: SHA-256
`938656618b21a799be18779eaccc5519e9ac32c3b2a4117745229802488905d3`,
771985 Bytes, Serverzeit 2026-09-03T15:56:26+00:00.
Diese Werte sind historische Vergleichswerte; vor einer neuen Proposal-Erstellung live erneut lesen.

## Releaseablauf

1. Sauberen Releaseumfang in Git pruefen; fremde lokale Aenderungen nicht mitnehmen oder verwerfen.
2. Generatoren, PHP-Lint, Regressionen und Browserpruefungen aus TESTING.md ausfuehren.
3. Kandidat committen, Remote-Erreichbarkeit des exakten Commits pruefen; Assetrevisionen unveraenderlich pinnen.
4. Aktuelle Produktionsdatei read-only hashen, bei Bedarf fuer gezielten Rollback sichern. Bei unerwartetem Hash erst die Fremdaenderung klaeren.
5. Genau die geprueften Bytes als cPanel-Schreibvorschlag mit Zielpfad und Overwrite bereitstellen.
6. Benutzer fuehrt die externe Freigabe selbst aus. Keine TOTP-Codes anfordern, lesen oder eingeben.
7. Erst die freigegebene Proposal-ID ausfuehren; Ergebnis und neuen Dateihash mit den lokalen Bytes vergleichen.
8. Oeffentlichen HTTP-Check sowie angemeldete betroffene Seiten pruefen. Fuenf Sprachen, Desktop und schmales Fenster fuer Hilfereleases.
9. Releaseprotokoll mit Commit, Ziel, Hash, Zeiten, Pruefumfang und offenen Punkten aktualisieren.

Die Prüfung erfolgte öffentlich; eine authentisierte Prüfung bleibt weiterhin sitzungsabhängig.

## Datenwirkung von 2.1.0

Beim ersten Request werden die neuen Beschriftungen für KI-Instruktion, Aktion und Status in
`ui_text_keys`/`ui_text_translations` ergänzt. Die Anwendung nutzt die vorhandenen Bewerbungsfelder;
es gibt keine Schemaänderung. Erst beim Öffnen einer Bewerbung mit mindestens einem leeren
Textfeld werden fehlende Entwürfe ergänzt. Vorhandene Betreff-, E-Mail- und Motivationsfelder
bleiben dabei unverändert. Eine KI-Überarbeitung erfolgt nur nach ausdrücklicher Benutzeraktion.

## Datenwirkung von 2.3.3

Die Admin-KI kann nach ausdrücklicher Anweisung in einer einzelnen Datenbanktransaktion mehrere
Datensätze in den allow-gelisteten Nutzer-Datentabellen erfassen oder leere Felder ergänzen. Die
Operationen werden vor der Ausführung auf Tabelle, Spalte, Benutzerbesitz, Referenzen und
Soft-Delete-Status geprüft. Es werden keine gelöschten Datensätze reaktiviert, keine Sicherheits-,
Geheimnis- oder Audit-Tabellen beschrieben und keine E-Mails versendet. Quellen werden, soweit das
Zielobjekt ein Notizfeld besitzt, als Nachweis ergänzt. Der Sitzungs-Kontext ist nur im Admin-
Benutzerkonto gespeichert und kann mit «Gedächtnis löschen» entfernt werden.

## Historische Datenwirkung von 1.18.1

Das Hilfe-Release fuegt beim ersten Request den geprueften Katalog in `ui_text_keys`/`ui_text_translations` ein bzw. aktualisiert die aufgefuehrten Hilfe-/Kontextkeys. Es verwendet einen Inhalts-Hash als Marker in `app_migrations`, eine DB-Sperre und eine Transaktion.

Betroffen sind ausschliesslich Hilfe- und Kontexttexte, keine Firmen, Bewerbungen, Konten oder Kalendertermine. Vorhandene Uebersetzungen dieser Keys werden durch die freigegebenen Texte ersetzt. Fuer einen exakten Text-Rollback die betroffenen UI-Zeilen vorab sichern; ein reiner PHP-Rollback stellt sie nicht zurueck. Nach erfolgreichem Seed erfolgen fuer denselben Hash keine erneuten Ueberschreibungen.

Die v6-Workflowbereinigung wird dadurch weder gestartet noch als abgeschlossen markiert.

## Betrieb und Fehler

- Bei PHP-Fehlern Serverlog und Hash pruefen; keine leeren Seiten als Erfolg akzeptieren.
- Konfigurations-/DB-Ausfall liefert 503. Eine HTTP-200-Antwort allein belegt keine korrekte Authentisierung oder Fachfunktion.
- Hilfeseed-Fehler werden geloggt; der naechste Request versucht erneut. Sichtbare Raw-Keys sind ein fehlgeschlagener Sprachcheck.
- Datei-/DB-Sicherungen zugriffsgeschuetzt halten und Wiederherstellung regelmaessig getrennt testen.
- Dokumenttextextraktion: `php deploy/extract-document-texts.php --limit=20`. PDF benoetigt `pdftotext`; Laufzeit und Cronintervall vom Betreiber konfigurieren, nicht stillschweigend installieren.
- Ein temporaerer Installer braucht ein Zufallstoken und muss nach Verwendung entfernt werden.
- Es gibt keinen erforderlichen Browser-Worker fuer Inserate; Benutzer laden PDF/Bild selbst hoch.

## Rollback

Gezielt die vorherigen PHP-/Assetbytes ueber eine neue Freigabe wiederherstellen und Hash/Seiten pruefen. Nur die Daten zuruecknehmen, die nachweislich vom fehlerhaften Vorgang geaendert wurden. Kein pauschaler DB-Restore ueber zwischenzeitliche Benutzerarbeit.

Workflowbereinigung nutzt zeilenbezogene Sicherungen. Ein entsprechender Rueckweg muss diese pruefen und Konflikte behandeln, nicht die gesamte Produktivdatenbank ersetzen.
