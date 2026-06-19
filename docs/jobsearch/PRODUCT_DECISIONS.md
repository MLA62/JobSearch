# JeMa Jobs - Produktentscheidungen

Stand: 2026-06-19

Produktversion: `1.15.16`

Dieses Dokument beschreibt verbindliche Produktentscheidungen. Es dient als
Grundlage, um JeMa Jobs aus dem Repository und der Dokumentation wieder
aufzubauen.

## Produktstatus

- JeMa Jobs ist produktiv.
- Es existieren reale produktive Benutzer.
- Aenderungen muessen rueckwaertskompatibel und datenbewusst erfolgen.
- Der Begriff `Prototyp` darf in der Benutzeroberflaeche nicht mehr verwendet
  werden.
- Sichtbare Versionen beginnen nicht mit `0.`.

## Lizenzmodell

- JeMa Jobs ist proprietaere Software.
- Alle Rechte bleiben beim Rechteinhaber.
- Keine Open-Source-Lizenz.
- Keine Weitergabe, Veroeffentlichung, Vervielfaeltigung oder abgeleitete
  Nutzung ohne ausdrueckliche schriftliche Erlaubnis.
- Die Hilfe enthaelt eine kurze Lizenzsektion.
- Das Repository enthaelt `LICENSE.md` als kanonische Lizenznotiz.

## UI- und Designentscheidungen

- Orientierung am Microsoft-Windows-11-Stil mit hoeherem Kontrast als die
  Minimalvorgaben.
- Akzentfarbe ist Orange, nicht Blau.
- Eingabefelder haben sichtbaren Hintergrund und klare Raender.
- Aktive Auswahlen und selektierte Datensaetze muessen deutlich erkennbar sein.
- Tabellenlinien, Rahmen und Zeilentrennung muessen konsequent sichtbar sein.
- Kartenansichten werden dort, wo sinnvoll, durch Tabellenansichten ergaenzt.
- Technische IDs werden nicht in fachlichen Listen oder Reports angezeigt.
- Kontext-Hilfe erscheint ueber eine leuchtende Gluebirne und ein modales
  Popup.
- Die Topnavigation verwendet Windows-nahe Menuegruppen mit Pulldowns.

## Mehrsprachigkeit

- Stand Version `1.15.16`: UI-Texte werden im Laufzeitpfad ueber DB-Keys
  gelesen. Auch alte Arbeitsseiten-Phrasen werden als `legacy.literal.*`
  in `ui_text_keys` und `ui_text_translations` gehalten.
- Stand Version `1.15.16`: PDF-Ausgaben uebersetzen Titel, Tabellenkoepfe,
  Zeilenwerte und Standard-Metatexte ueber dieselbe DB-Phrasenlogik.
- Stand Version `1.15.16`: `tr()` liest UI-Texte zuerst aus den Tabellen
  `ui_text_keys` und `ui_text_translations`. Fehlende bisherige `tr()`-Keys
  werden als Migrationshilfe automatisch in der Datenbank registriert.
- Das Werkzeug `tools/jobsearch_i18n_audit.php` liefert die Arbeitsliste der
  noch direkt im PHP vorhandenen sichtbaren Textkandidaten.
- Die App verwendet eine zentrale Sprachbibliothek in `public/index.php`.
- Produktive Locale-Codes sind `de-CH`, `fr-CH`, `en-GB`, `pt-BR` und
  `es-MX`.
- Veraltete Werte `de`, `fr`, `en`, `pt` und `es` werden per
  Runtime-Migration auf die produktiven Locale-Codes normalisiert.
- Login und Registrierung zeigen eine kompakte Flaggenauswahl; im Profil
  wird die App- und Dokumentensprache als Stammdatum gepflegt.

## Identitaet, Login und Support

- Registrierung ist erlaubt.
- Benutzer koennen sich anmelden und ihr Passwort zuruecksetzen.
- TOTP-2FA ist im Profil verfuegbar.
- Aktivierte 2FA muss beim Login erzwungen werden.
- Admins koennen Benutzer loeschen, Passwoerter zuruecksetzen und 2FA
  zuruecksetzen.
- Benutzer koennen ADMIN Support explizit freigeben.
- Admins duerfen nur mit aktiver Freigabe in eine Benutzerumgebung wechseln.
- Waehrend Support ist die Kopfzeile farblich anders.
- Der Admin sieht, in welcher Umgebung er arbeitet.
- Benutzer koennen die Freigabe jederzeit widerrufen.

## E-Mail

- Benutzer pflegen eigene SMTP-Einstellungen im Profil.
- Benutzerbezogene E-Mails werden ueber die SMTP-Konfiguration des jeweiligen
  Benutzers verschickt.
- Zentrale SMTP-Konfiguration ist nur Fallback fuer systemeigene Flows.
- Interne E-Mails wie Registrierung oder Passwort-Reset duerfen verschickt
  werden, sofern SMTP aktiv ist.
- SMTP-Passwoerter werden verschluesselt gespeichert.

## Datenisolation und Sicherheit

- Jeder Benutzer besitzt seine eigenen Firmen, Jobs, Kontakte, Bewerbungen,
  Dokumente, Reports und Kalenderdaten.
- Abfragen muessen immer auf den authentifizierten Benutzer scoped sein, ausser
  es gibt eine explizite Freigabe.
- Admin Support ist eine kontrollierte Ausnahme mit Auditpflicht.
- Audit-Log ist unveraenderbar.
- Produktionsdaten duerfen nicht durch Test- oder Migrationslogik geloescht
  oder ueberschrieben werden.

## Jobsuche und Import

- Vollautomatisches Scraping externer Jobportale ist nicht der Standardweg.
- Der robuste Standardweg ist:
  1. Profilpraeferenzen pflegen.
  2. In der App einen ChatGPT-Rechercheprompt erzeugen.
  3. ChatGPT mit Web-Recherche direkte Stellenlinks liefern lassen.
  4. Eine unformatierte Linkliste in den Schnellimport einfuegen.
  5. Jobvorschlaege pruefen und speichern.
- Der Prompt verlangt ausschliesslich Direkt-URLs, eine URL pro Zeile, ohne
  Markdown, Nummerierung, Titel oder Erklaerungen.
- Admins pflegen die Liste der Jobplattformen.
- Jobplattformen sind Prioritaeten fuer die Recherche, keine Garantie fuer
  direkte Inserat-URLs.
- Schnellimport zeigt beim Erstellen eines Vorschlags einen Fortschrittsbalken.

## Bewerbungen

- Onlinebewerbung ist der wichtigste Zielprozess, weil viele Firmen eigene
  Formulare nutzen.
- Eine Bewerbung kann ohne konkreten Kontakt existieren.
- Unterlagen muessen fuer Webformulare leicht bereitgestellt werden:
  Drag-and-drop, temporaerer Ordner, ZIP-Paket, Download.
- Nach Einreichung einer Bewerbung wird automatisch protokolliert:
  - Kontaktlog-Aktivitaet
  - Pendent `Antwort auf Bewerbung pendent`
  - Zeitpunkt der Einreichung
  - Sichtbarkeit in Agenda/Kalender
- Bei E-Mail-Bewerbungen werden Unterlagen als Attachments behandelt, sofern
  sie zugeordnet sind.

## Firmen, Kontakte und Kontaktlog

- Firmen koennen automatisch aus Importen entstehen.
- Firmen, Personen und Jobs haben Kommentarfelder.
- Kontakte werden immer nach Nachname sortiert.
- Das Kontaktlog gehoert direkt zu Kontakten und ist auch aus Bewerbungen
  sichtbar.
- Kontaktlog-Eintraege koennen Kanaele, Zeitpunkte, Wiedervorlagen, Status,
  Mitteilung und Anhaenge enthalten.

## Kalender und Pendent

- Der Begriff lautet `Pendent`, nicht `Offene Schritte`.
- Agenda ist tabellarisch.
- Tages-, Wochen- und Monatsansichten sind Matrixansichten, keine einfachen
  Listen.
- Eintraege ohne Uhrzeit werden als Tageseintraege oben angezeigt.
- ICS-Export ist pro Ansicht verfuegbar.

## Dokumente und Dossier

- Dokumente sind versioniert.
- Stammdokumente und bewerbungsspezifische Dokumente werden unterschieden.
- Bewerbungsdossier fasst Firma, Kontakte, Job, Bewerbung, Fragen, Dokumente
  und Aktivitaeten zusammen.
- Dossier kann als Webseite angezeigt und als PDF genutzt werden.

## Reports, Tabellen, Sortierung und Filter

- Tabellen haben Sort- und Filtersteuerung pro Feld.
- Dropdown-basierte Felder bieten Mehrfachauswahlfilter.
- Filter und Sortierung bleiben innerhalb der Session und ueber Ansichten
  hinweg erhalten.
- PDF-Reports muessen optisch lesbar sein: Rahmen, Spaltenlinien,
  gebaenderte Zeilen.

## Hilfe

- Zentrale Hilfe ist eine produktive Wissensbasis.
- Die Hilfe hat Suche, Kategorien, Prozessgrafik, Kurzablaeufe und ausfuehrliche
  Themen.
- Kontext-Hilfe wird dort angezeigt, wo sie den Arbeitsfluss direkt verbessert.
- Kontext-Hilfe nutzt eine leuchtende Gluebirne und ein modales Popup.
- Die Hilfe enthaelt eine Lizenzsektion.

## Deployment-Entscheidungen

- Produktive Deployments erfolgen aktuell per explizitem FTPS.
- Secrets werden nicht in Git gespeichert.
- Vor jedem Deployment:
  - `php -l public/index.php`
  - `git diff --check`
  - gezielte Live-HTTP-Pruefung
- Nach Deployment werden GitHub und lokale Arbeitskopie synchron gehalten.
