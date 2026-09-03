# Deployment und Betrieb

Stand: 2026-09-03. Zuletzt verifizierte Produktion: 1.18.0 / 541e02d.
Kandidat dieser Dokumentationsrunde: 1.18.1, noch nicht produktiv bestaetigt.
Neuinstallation und Wiederherstellung: [REBUILD.md](REBUILD.md).

## Freigabegrenzen

Produktionsaenderungen erfolgen ueber cPanel Mail Control mit externer TOTP-Freigabe. Keine FTPS-Abkuerzungen, keine Zertifikatspruefung abschalten, keine alten Freigaben erneut verwenden. Die Zustimmung zu einer Codeaenderung ersetzt nicht die erforderliche externe Ausfuehrungsfreigabe.

Niemals `config.php`, private Dateien, Datenbankinhalte oder Secrets aus dem Entwicklungsstand ueber die Produktion schreiben. Eine Workflow-Datenbereinigung ist ein eigener Vorgang mit Vorschau; sie gehoert nicht automatisch zum PHP-Deployment.

## Produktionspfade und Abhaengigkeiten

- Webadresse: https://jobs.jema.business/
- PHP-Einstieg: `public_html/jobs.jema.business/index.php`
- Private Konfiguration und `storage/` bleiben auf dem Ziel unveraendert.
- App-CSS und Layout-JavaScript sind auf Commit `a7ab08cd447c48223a4b586ae2fa92d133fd5ca6` gepinnt.
- Layout-CSS ist auf `e729866c75285d61b2ac5f908a63a631a9c8b686` gepinnt.
- Asset-URLs und Versionen vor jedem Release im tatsaechlichen PHP pruefen. Lokale Assetaenderung allein aendert keine gepinnte Produktionsdatei.
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

Nicht freigegeben, abgelaufen oder nicht angemeldet bedeutet **ausstehend**, nicht erfolgreich deployt.

## Datenwirkung von 1.18.1

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
