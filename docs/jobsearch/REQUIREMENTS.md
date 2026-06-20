# JeMa Jobs - Anforderungen

Stand: 2026-06-19

Produktversion: `1.15.22`

JeMa Jobs ist kein Prototyp mehr, sondern eine produktive Webanwendung mit
realen Benutzern und vertraulichen Bewerbungsdaten.

## Grundsaetze

- Alle produktiven Benutzerdaten sind vertraulich.
- Benutzer sehen nur ihre eigenen CRM-, Bewerbungs-, Dokument- und
  Kalenderdaten.
- Administrative Eingriffe muessen minimal, nachvollziehbar und auditiert sein.
- Jede produktive Aenderung muss lokal geprueft, per FTPS deployed und nach
  Moeglichkeit nach GitHub synchronisiert werden.
- Die sichtbare Versionsnummer wird im Footer angezeigt und muss bei
  produktiven Aenderungen erhoeht werden.
- Die Anwendung ist proprietaer. Alle Rechte sind vorbehalten.

## Plattform

- Webanwendung unter `https://jobs.jema.business`.
- PHP 8.1+.
- MariaDB 10.6.
- UTF-8/`utf8mb4` fuer Anwendungstabellen.
- Responsive Layout fuer Desktop, Tablet und Mobile.
- Keine clientseitige Abhaengigkeit fuer kritische Sicherheitsentscheidungen.

## Identitaet und Sicherheit

- Benutzer koennen sich registrieren und anmelden.
- Passwoerter werden mit `password_hash()` gespeichert.
- Passwort-Reset ist vorhanden.
- Wenn SMTP aktiv ist, werden interne E-Mails wie Registrierung und Reset per
  E-Mail verschickt.
- Wenn E-Mail-Versand nicht moeglich ist, muss der Ablauf sichtbar und
  kontrolliert bleiben.
- TOTP-2FA kann im Profil aktiviert werden.
- Aktivierte TOTP-2FA muss beim Login zwingend geprueft werden.
- Admins koennen fuer Benutzer Passwort und 2FA zuruecksetzen.
- Benutzer koennen ADMIN Support explizit freigeben und jederzeit widerrufen.
- Waehrend ADMIN Support muessen Benutzer- und Admin-Umgebung farblich
  markiert sein.
- Admins sehen in der Benutzerverwaltung, wer aktuell online ist.

## Benutzerprofil

- Profil enthaelt Name, E-Mail, Telefon, Adresse, Region, Zeitzone und Sprache.
- Profil enthaelt Social Links: LinkedIn, Facebook, X und andere.
- Profil enthaelt Sprachkenntnisse.
- Profil enthaelt Suchpraeferenzen:
  - Rollen/Taetigkeiten
  - Orte/Regionen
  - Arbeitsmodell
  - Stellenarten
  - Pensum
  - Lohn
  - Level/Lage
  - Benefits
  - Ausschluesse
  - Reiseanteil
  - Verfuegbarkeit
- Benutzer koennen eigene SMTP-Einstellungen pflegen.

## Mehrsprachigkeit

- Stand Version `1.15.22`: UI-Texte muessen im Laufzeitpfad ueber DB-Keys
  kommen. PHP- und Resource-Dateien duerfen keine Uebersetzungswoerterbuecher
  mehr enthalten.
- PDF-Ausgaben muessen dieselbe DB-Uebersetzung fuer Titel, Tabellenkoepfe,
  Zeilenwerte und Standard-Metatexte verwenden.
- `tr()` muss UI-Texte primaer aus der Datenbank lesen.
- Fehlende alte `tr()`-Fallbacks duerfen waehrend der Migration automatisch in
  der Datenbank registriert werden.
- Neue sichtbare UI-Texte duerfen nicht mehr ohne DB-Key eingefuehrt werden;
  `tools/jobsearch_i18n_audit.php` dient als Kontrollwerkzeug.
- Unterstuetzte App-Sprachen sind Deutsch Schweiz (`de-CH`), Franzoesisch
  Schweiz (`fr-CH`), English UK (`en-GB`), Brasilianisch-Portugiesisch
  (`pt-BR`) und Mexikanisch-Spanisch (`es-MX`).
- Sichtbare UI-Texte werden nicht mehr als statische Textkataloge in PHP,
  JavaScript, Markdown oder anderen Ressourcen-Dateien gepflegt.
- Sichtbare UI-Texte werden ueber stabile Keys aus der Datenbank gelesen.
- Die Datenbanktabellen fuer UI-Texte sind `ui_text_keys` und
  `ui_text_translations`; `languages` bleibt die fuehrende Sprachtabelle.
- Der Code darf neue sichtbare UI-Texte nur noch per Key referenzieren.
- Vor dem Login wird die Sprache anhand der Browsersprache gewaehlt, sofern
  sie unterstuetzt ist; sonst gilt Deutsch Schweiz.
- Beim Login gibt es eine kleine Flaggenauswahl.
- Registrierte Benutzer koennen die App- und Dokumentensprache im Profil
  anpassen.
- Sprachwerte werden als Locale-Codes gespeichert, nicht als unklare
  Kurzkuerzel.

## Dokumente

- Stammdokumente werden versioniert verwaltet.
- Bewerbungsdokumente koennen einer Bewerbung zugeordnet werden.
- Dokumente haben Typ, Titel, Sprache, Beschreibung, Version und Datei.
- Benutzer koennen Dokumente bearbeiten, ersetzen, herunterladen und loeschen.
- Bei Bewerbungen koennen mehrere Stammdokumente gesammelt zugeordnet werden.
- Fuer Onlinebewerbungen muessen Drag-and-drop-Download, temporaerer Ordner und
  Portalpaket ZIP unterstuetzt werden.

## Firmen und Kontakte

- Firmen koennen manuell erfasst oder beim Jobimport automatisch erzeugt werden.
- Firmen haben Kommentar- und Verknuepfungsinformationen.
- Kontakte gehoeren zu Firmen und optional zu Jobs oder Bewerbungen.
- Kontakte muessen nach Nachname sortiert sein.
- Kontakte haben ein Kontaktlog.
- Kontaktlog-Eintraege enthalten:
  - Kanal: E-Mail, extern, vor Ort, Telefon, Video Call, WhatsApp, SMS, andere
  - Richtung
  - Datum/Uhrzeit
  - Wiedervorlage Datum/Uhrzeit
  - Betreff
  - Status: geplant, offen, erledigt, abgebrochen
  - mehrzeilige Mitteilung
  - Ergebnis/naechster Schritt
  - Anhaenge ueber Dokumentablage
- Bewerbungsereignisse muessen im Kontaktlog sichtbar werden, auch wenn kein
  klassischer Ansprechpartner vorhanden ist.

## Jobs und Jobsuche

- Jobs koennen manuell erfasst, aus einer URL importiert oder aus Text
  vorgeschlagen werden.
- Der Schnellimport akzeptiert eine URL, mehrere URLs oder kopierten
  Ausschreibungstext.
- Beim Klick auf `Vorschlag erstellen` muss Fortschritt sichtbar sein.
- Jobdaten enthalten Firma, Titel, Ort, Arbeitsmodell, Stellenart,
  Vertragsdauer, Lohn, Status, Quell-URL, Beschreibung, Kommentar,
  Original-PDF-Status und Fragen.
- Quell-URL ist klickbar.
- ID-Werte duerfen in Benutzerlisten und Reports nicht als fachliche Information
  erscheinen.
- Der Admin pflegt eine Liste von Jobplattformen.
- Benutzer koennen auf Basis ihrer Profilpraeferenzen einen
  ChatGPT-Rechercheprompt erstellen.
- Der Prompt muss direkte Stellenlinks verlangen:
  - eine URL pro Zeile
  - keine Nummerierung
  - kein Markdown
  - keine Erklaerungen
  - keine erfundenen Links
- Von ChatGPT gelieferte Direktlinks werden im Schnellimport verarbeitet.

## Bewerbungen

- Aus einem Job kann eine Bewerbung vorbereitet werden.
- Bewerbungen unterstuetzen Onlinebewerbung, E-Mail-Bewerbung und andere Kanaele.
- Onlinebewerbungen benoetigen Webformular-URL, Portalhinweise,
  Referenznummer, Notizen und Kopieren-Buttons.
- Viele Bewerbungen erfolgen direkt in Formularen der Firmen; der Workflow muss
  darauf optimiert sein.
- Nach Einreichung einer Bewerbung muss automatisch entstehen:
  - Kontaktlog-Aktivitaet
  - Kalender-/Agenda-Sichtbarkeit
  - Pendent mit Text `Antwort auf Bewerbung pendent`
  - Zeitpunkt der Einreichung als Bezugszeitpunkt
- Bewerbungspakete muessen Dokumente fuer externen Upload bereitstellen.
- Bewerbungsdossier muss alle relevanten Informationen zusammenfassen:
  Firma, Kontakte, Job, Bewerbung, Dokumente, Fragen und Aktivitaeten.

## Pendent und Kalender

- `Pendent` ist die zentrale Aufgabenliste.
- Daten koennen gefiltert und sortiert werden.
- Kalender bietet:
  - Agenda als Tabelle
  - Tagesplan mit Uhrzeitachse
  - Arbeitswochen-/Wochenplan als Matrix
  - Monatsplan als Monatsmatrix
  - Kalenderwochenanzeige
  - Tageseintraege ohne Uhrzeit oben
  - ICS-Export je Ansicht
- Ereignisse muessen anklickbar sein.

## Reports und Tabellen

- Wo Daten als Karten erscheinen, muss eine Tabellenansicht moeglich sein, wenn
  es fachlich sinnvoll ist.
- Tabellen koennen sortiert und gefiltert werden.
- Sort/Filter-Zustand bleibt session- und darstellungsuebergreifend erhalten.
- Dropdown-Felder nutzen Filter mit Mehrfachauswahl.
- Tabellen koennen als PDF exportiert werden.
- Reports koennen gespeichert, angezeigt, bearbeitet, geloescht und exportiert
  werden.
- Reports muessen grafisch sauber sein: gebaenderte Zeilen, Rahmen und
  Spaltenlinien.

## Hilfe

- Zentrale Hilfe mit Suche, Kategorien und Prozessuebersicht.
- Kontextuelle Hilfe auf sinnvollen Seiten ueber leuchtende Gluebirne.
- Hilfetext erscheint modal und kann per Button, Hintergrundklick oder Escape
  geschlossen werden.
- Hilfe enthaelt eine Lizenzsektion.
- Hilfe muss produktiv formuliert sein und darf nicht auf einen Prototyp
  verweisen.

## Admin

- Admins verwalten Benutzer, Rollen, Status, Passwort, 2FA und Support.
- Admins verwalten Jobplattformen.
- Admins duerfen sich nur mit expliziter Benutzerfreigabe in eine Umgebung
  einklinken.
- Admins sehen, welche Umgebung sie gerade benutzen.
- Adminhandlungen muessen auditiert werden.

## Nicht-Ziele im aktuellen Stand

- Vollautomatisches Scraping von Jobportalen ohne erlaubte Schnittstelle.
- Speicherung von Klartext-Passwoertern.
- Open-Source-Weitergabe.
- Direkter Browserzugriff auf Dokumentdateien ohne Autorisierungspruefung.
