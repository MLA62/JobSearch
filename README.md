# JeMa Jobs

JeMa Jobs ist ein produktives, privates Job-CRM fuer `https://jobs.jema.business`.
Die Anwendung verwaltet den kompletten Bewerbungsprozess von der Profilpflege
ueber Jobsuche, Schnellimport, Firmen, Kontakte, Bewerbungen, Dokumente,
Kontaktlog, Pendent, Kalender, Reports, Dossier und Admin-Support bis zur
Nachverfolgung.

Aktueller dokumentierter Stand: Version `1.15.16`.

## Lizenz

JeMa Jobs ist proprietaere Software. Alle Rechte sind vorbehalten.
Nutzung, Betrieb, Weitergabe, Vervielfaeltigung oder Ableitung sind nur mit
ausdruecklicher Berechtigung erlaubt.

Siehe [LICENSE.md](LICENSE.md).

## Zielplattform

- PHP 8.1+; die produktive Domain wurde mit PHP 8.1.x beobachtet.
- MariaDB 10.6.
- Klassisches Shared Hosting mit `public/index.php` als Front Controller.
- Keine Composer-Abhaengigkeiten im produktiven Kern.
- Responsive Browser-Oberflaeche fuer Desktop, Tablet und Mobile.
- Produktive UI-Sprache in Version `1.15.16`: UI-Texte laufen primaer ueber
  `ui_text_keys` und `ui_text_translations`; auch alte Arbeitsseiten-Phrasen
  sowie PDF-Ausgaben werden fuer die Uebersetzung ueber DB-Keys gelesen.
- UI-Texte werden schrittweise in die Datenbanktabellen `ui_text_keys` und
  `ui_text_translations` migriert. Das Audit-Werkzeug
  `tools/jobsearch_i18n_audit.php` zeigt verbliebene Hardcode-Kandidaten.

## Repository-Struktur

```text
public/
  index.php              Zentrale produktive Anwendung
  assets/app.css         Globales Windows-11-nahes Design
  assets/favicon.svg     App-Icon
  assets/qrcode.min.js   QR-Code-Bibliothek fuer TOTP
  assets/totp-qr.js      TOTP-QR-Initialisierung
  config.example.php     Beispiel fuer serverseitige Konfiguration

sql/jobsearch/
  00_create_database.sql Optionale Datenbankanlage
  01_schema.sql          Basis-Schema
  02_views.sql           Reporting-Views

deploy/
  installer/             Einmaliger Web-Installer fuer Hosts ohne SSH
  render-pending-job-pdfs.php
  extract-document-texts.php

docs/
  jobsearch/REQUIREMENTS.md
  jobsearch/PRODUCT_DECISIONS.md
  jobsearch/DEPLOYMENT.md
  rendered-job-pdf-migration-2026-06-16.md
```

## Produktumfang

Der aktuelle produktive Kern umfasst:

- Registrierung, Login, Passwort-Reset, TOTP-2FA und Admin-Reset fuer Passwort
  und 2FA.
- Benutzerverwaltung mit Online-Status und Rollen.
- Explizit freigegebener ADMIN Support mit farblich markierter Umgebung.
- Benutzerprofile mit Kontaktdaten, Social Links, Sprachkenntnissen,
  Suchpraeferenzen, SMTP-Einstellungen und Sicherheitsbereich.
- Versionierte Stammdokumente und bewerbungsspezifische Dokumente.
- Firmen mit Kommentaren, Beziehungen, Vermittlungsbezug und Verknuepfungen.
- Kontakte mit Nachname-Sortierung, Kontaktlog, Wiedervorlage und Anhaengen.
- Jobs mit Schnellimport aus URLs oder Text, Lohn, Quelle, Kommentar,
  Original-PDF-Status, Fragen und Dublettenhinweis.
- Admin-gepflegte Jobplattformen und benutzerseitige ChatGPT-Rechercheprompts
  fuer direkte Stellenlinks.
- Bewerbungen mit Onlinebewerbungsfluss, E-Mail-Fluss, Dokumentzuordnung,
  Portalpaket, temporaerem Dokumentordner, Einreichungsprotokoll und Pendent.
- Pendent-Zentrale und Kalender mit Agenda, Tages-, Wochen- und Monatsmatrix
  sowie ICS-Export.
- Reports mit Tabellenansicht, Filtern, Sortierung, Speicherung und PDF-Export.
- Bewerbungsdossier als Webseite mit PDF-Moeglichkeit.
- Zentrale Hilfe mit Suche, Prozessgrafik, Lizenzsektion und kontextuellen
  Gluebirnen-Hilfen als modale Popups.
- Audit-Log, Cleanup-Vorschau und private Datenisolation pro Benutzer.

## Wiederaufbau aus diesem Repository

1. Eine leere MariaDB-Datenbank `kerubina_JeMaJobs` bereitstellen.
2. `sql/jobsearch/01_schema.sql` importieren.
3. `sql/jobsearch/02_views.sql` importieren.
4. `public/config.example.php` auf dem Zielserver als `public/config.php`
   kopieren und echte Werte eintragen.
5. `public/` als Webroot oder in den bestehenden Webroot deployen.
6. Schreibrechte fuer `public/storage/` sicherstellen, wenn Uploads und
   temporare Bewerbungsunterlagen im Webroot-Layout genutzt werden.
7. Die Domain per HTTPS ausliefern.
8. Login, Registrierung, Passwort-Reset, Profil, Dokumentupload, Schnellimport
   und Hilfe testen.
9. Optional Worker fuer Original-PDFs und Dokumenttextextraktion per Cron
   aktivieren.

Die Anwendung fuehrt mehrere rueckwaertskompatible Runtime-Migrationen in
`public/index.php` aus. Fuer einen reproduzierbaren Neuaufbau sollte trotzdem
das SQL-Schema aktuell gehalten und importiert werden.

## Produktiver Betrieb

- Keine Secrets in Git speichern.
- FTP-/FTPS-, Datenbank-, SMTP- und App-Schluessel nur serverseitig pflegen.
- Produktive Benutzerdaten sind real und vertraulich.
- Aenderungen an Login, 2FA, Passwort-Reset, Dokumenten, Bewerbungen,
  Supportzugriff und Adminfunktionen vor Deployment immer linten und live
  pruefen.
- Sichtbare Version im Footer muss bei Aenderungen erhoeht werden.

## Dokumentation

- [Anforderungen](docs/jobsearch/REQUIREMENTS.md)
- [Produktentscheidungen](docs/jobsearch/PRODUCT_DECISIONS.md)
- [Deployment](docs/jobsearch/DEPLOYMENT.md)
- [Historische PDF-Migration](docs/rendered-job-pdf-migration-2026-06-16.md)
- [Lizenz](LICENSE.md)
