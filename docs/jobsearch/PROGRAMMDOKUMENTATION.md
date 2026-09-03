# Programmdokumentation

Stand: 2026-09-03. Fachlicher Ausgangsstand: 1.18.0, Commit 541e02d.
Die Hilfe-Erweiterung 1.18.1, Quellstand ab2bf9c, ist deployed; Server-Hash und angemeldete Hilfepruefung in fuenf Sprachen sind bestaetigt.
Verbindliche Produktregeln: [REQUIREMENTS.md](REQUIREMENTS.md), [WORKFLOW.md](WORKFLOW.md).
Exakte Tabellen, Felder und Funktionssignaturen: [DATA_MODEL.md](DATA_MODEL.md), [INTERFACES.md](INTERFACES.md).

## Architektur und Request

JeMa Jobs ist eine serverseitige PHP-Anwendung, keine SPA. `public/index.php` enthaelt Bootstrap, Schema-Erweiterungen, Sprachauflösung, Fachfunktionen, GET/POST-Handler und HTML-Ansichten. `public/assets/` enthaelt CSS und kleine JavaScript-Erweiterungen; keine Composer-Abhaengigkeit ist erforderlich.

1. Session starten, HTTPS-Cookie-Einstellungen anwenden; `config.php` laden.
2. mysqli mit strengem Fehlermodus und utf8mb4 verbinden. Fehlende Konfiguration/DB liefert 503.
3. Rueckwaertskompatible Schema- und Referenzdaten-Erweiterungen ausfuehren. Das ist nicht die Workflow-Datenbereinigung.
4. Sprache und Authentisierung ermitteln; freigegebene UI-Texte laden.
5. Zustandsaendernde POST-Aktionen mit CSRF und den zustaendigen Rollen-/Eigentuemerpruefungen abarbeiten, danach Redirect.
6. GET-Seite, Download oder Export rendern. Private Dateien werden nicht direkt per Webpfad ausgeliefert.

Der Neuaufbau darf diese monolithische Struktur aufteilen, muss aber die fachlichen Vertraege erhalten. Keine neuen Frameworks sind durch diese Dokumentation vorgeschrieben.

## Identitaet und Zugriff

- `users` ist die Kontobasis; Rollen, Authentisierungstoken, TOTP, Sitzungen und Supportfreigaben sind getrennte Daten.
- `userId()` bezeichnet den effektiv verwendeten Benutzer; `realUserId()` bleibt bei Support der angemeldete Admin.
- `isAdmin()` beruecksichtigt sowohl die konfigurierte Admin-E-Mail-Liste als auch die DB-Rolle `admin`.
- Benutzerbezogene Abfragen muessen die effektive Benutzer-ID und gegebenenfalls `deleted_at` beruecksichtigen; fremde IDs aus Formularen sind nie eine Berechtigung.
- Support benoetigt eine aktive, nicht widerrufene Freigabe. Die Adminrolle allein gibt keinen Zugriff auf private Bewerbungsdaten. Die Supportumgebung muss erkennbar und beendbar sein.
- Das eigene Adminkonto und der letzte erforderliche Admin sind gegen unbeabsichtigte Sperrung/Rollenentzug zu schuetzen.
- Registrierung validiert Vor-/Nachnamen, E-Mail und ein Passwort mit mindestens zehn Bytes; Passwoerter werden gehasht, nicht reversibel gespeichert.
- TOTP-Einrichtung erfordert Bestaetigung. SMTP- und OAuth-Secrets werden mit dem persistenten `app_key` verschluesselt.
- Freigabelinks, private Kalenderfeeds und Ruecksetzlinks sind Zugangsmittel. Nicht loggen, in Screenshots offen zeigen oder in Git speichern.

## Module und Masken

| Bereich / Seite | Inhalt und wesentliche Regeln |
| --- | --- |
| `dashboard` | Einstieg und Kennzahlen des effektiven Kontos; kein anderer Datenbestand als in den Fachlisten. |
| `profile`, `profile_links` | Person, Adresse, Land/Region, Zeitzone, App-Sprache, Sprachen, gewuenschte Rollen/Orte, Arbeitsmodell, Stellenarten, Pensum von/bis, Lohn, Verfuegbarkeit, Benefits, Ausschluesse; SMTP, Signatur, Kalender und Supportfreigabe. Links separat pflegen. |
| `documents` | Titel, Typ, tatsaechliche Dokumentsprache, Version und Datei. Upload, Metadaten, Download, Zuordnung und Loeschung sind eigene Aktionen. |
| `job_platform_search` | Suchwuensche und Plattformen in einen Rechercheprompt uebernehmen. Externe Recherche, danach kontrollierter Import, kein autonomer Bewerbungsversand. |
| `jobs` | Firma oder neue Firma, mehrzeiliger Titel, Ort, Arbeitsmodell, Pensum von/bis, Stellenart, Vertragsdauer/Datumsbereich, Lohn mit Periode, Status, Quell-URL, Inseratdatei, Beschreibung/Notizen und Kontakte. |
| `companies` | Firma, Vermittlerrolle, Adresse, Region, Telefon, E-Mail, Website, zugehoerige Kontakte/Jobs/Bewerbungen. Adressen umbrechen. |
| `contacts` | Person/Funktion, Firma, Kontaktwege/Sprache, Stellen-/Bewerbungszuordnung; Kontakt-Log und E-Mail-Protokollierung. Zaehlung bedeutet Aktivitaeten, nicht Bewerbungen. |
| `applications` | Bezug auf Job/Firma, Inhalte/Dokumente, Versandkanal, Statusverlauf, Onlineeinreichung oder SMTP-Versand, mehrere Gespraeche, Ergebnis; Job-Room separat. |
| `application_dossier` | Zusammenfassung genau einer Bewerbung mit passenden Unterlagen, Kontakten und Aktivitaeten, auch als PDF. |
| `calendar` | Agenda, Tag, Arbeitswoche, Woche, Monat; explizite Nachfass-/Gespraechstermine und kurze Workflow-Nachweise. Heute gruen in Monat/Arbeitswoche/Woche. |
| `reports` | Eigene Reportdefinition: Datenbasis, Spalten, Filter, Sortierung, Vorschau und PDF. |
| `job_room_helper` | Daten fuer das externe Job-Room bereitstellen; keine automatische Uebermittlung. |
| `sharing`, `guest` | Begrenzte Freigaben und Gastansicht fuer ausgewaehlte Inhalte. |
| `privacy` | Exporte und Bereinigungsanfragen mit Vorschau und Status; kein direkter Loeschabschluss auf dieser Maske. |
| `translations` | Uebersetzung eigener Datensaetze ueber Prompt, nicht die Pflege der App-Sprache. |
| `admin_users` | Konto eroeffnen, Details verwalten, Status/Rolle pruefen, bei gueltiger Freigabe Support betreten. Tabelle mit vollstaendigen Namen; Adminspalte als Anzeige-Ankreuzfeld. |
| `admin_job_platforms` | Plattformdefinitionen, URLs, Suchvorlagen, Aktivierung und Reihenfolge. |
| `workflow_review` | Adminvorschau und gesonderte Bestaetigung der v6-Altdatenbereinigung. Kein Start beim Deployment. |
| `audit` | Protokoll ausgewählter Aktionen; kein vollstaendiges Backup. |
| `help`, `about` | 24 Hilfethemen in fuenf Sprachen; Seitenkontext aus denselben Themen; Version/Lizenz. |

Exakte GET-Exportnamen und POST-Aktionen stehen im generierten Inventar. Einige technische Endpunkte sind keine interaktiven Masken. Ein Auftreten im Inventar belegt noch keine Berechtigung.

## Relationen und Lebenszyklus

Das effektive Benutzerkonto besitzt Firmen, Kontakte, Jobs, Bewerbungen, Dokumente, Termine, Reports und Einstellungen. Beziehungen zwischen diesen Entitaeten duerfen nur innerhalb desselben erlaubten Datenbereichs entstehen.

Eine Firma hat mehrere Kontakte und Jobs. Ein Kontakt kann fuer mehrere Jobs/Bewerbungen zustaendig sein. Ein Job beschreibt das Angebot; eine Bewerbung ist ein eigener Vorgang. Zuordnungs-/Verlaufstabellen verhindern, dass Dokumentversionen, Gespräche und Kontaktaktivitaeten allein durch einen aktuellen Status ueberschrieben werden.

Kontakte einer Firma koennen in mehreren Dossiers erscheinen. Das rechtfertigt nicht, fremde Bewerbungsaktivitaeten mitzuspiegeln. Kontaktlog-Gesamtzahl zaehlt alle betreffenden Eintraege, offen/geplant nur die entsprechende Teilmenge.

Bestehende Legacywerte bleiben lesbar. Soft-Delete, Stornieren und fachliche Loeschung sind nicht austauschbar; vor kaskadierenden Eingriffen muessen Abhaengigkeiten und Sicherung geprueft werden.

## Fachregeln und Validierung

- Pensum: zwei unabhaengige, optionale Ganzzahlen zwischen 0 und 100; wenn beide vorhanden sind, von <= bis.
- Region: Landbezug erhalten; Schweizer Auswahlliste enthaelt Bern Stadt, Region Biel und Region Solothurn.
- Arbeitsmodell: unbekannt/beliebig je Kontext, vor Ort, hybrid, remote; Benutzersprache aendert Labels, nicht gespeicherte Codes.
- Lohnbetrag braucht eine Periode; keine stillschweigende Jahres-/Monatsumrechnung ohne definierte Basis.
- Job-Match ist aktuell eine einfache Heuristik: Basis 50, Remote-Stelle +15, vorhandener Mindestlohn +10, vorhandene Beschreibung +10, interessanter Status +15, maximal 100. Keine semantische KI-Bewertung versprechen.
- Jobimport liefert zu pruefende Vorschlaege. Dubletten, Kontaktzuordnung und Quell-URL vor dem Speichern kontrollieren.
- Zeitpunkte werden nach dem vorhandenen Daten-/Zeitzonenvertrag verarbeitet; Anzeige ueber `displayDateTime` (ohne Uhrzeit mit drittem Argument false), keine eigenstaendige Formatierung pro Maske.
- `applicationWorkflowDateSql()`, Statushelfer und Kalenderprojektion teilen sich den Bewerbungsvertrag. Details und Ausschlussregeln stehen in WORKFLOW.md.
- Zwei konkurrierende Sendepfade verwenden denselben Bewerbungsschluessel zur Sperre. Datenbank und externer SMTP-Server sind trotzdem keine gemeinsame atomare Transaktion.

## Dokumente, Versand und Prompts

Dokumentdateien liegen unter `storage/documents/<userId>/` mit zufaelligem Dateinamen; fachliche Namen bleiben in der DB. Allgemeiner Upload erlaubt PDF, DOC, DOCX, JPG/JPEG, PNG und TXT bis 25 MiB. Inseratdateien sind PDF/Bild. Erweiterungspruefung ist kein vollstaendiger Schadsoftware- oder MIME-Nachweis.

Der Download prueft Konto, Datensatz, Loeschstatus und realen Pfad innerhalb der Ablage. Apache-Zugriffsschutz der Ablage wird per `.htaccess` gesetzt; andere Webserver brauchen eine gleichwertige explizite Sperre.

Versionierung und Zuordnung sind getrennt von Versand. ZIP/temporaere Unterlagen erleichtern externen Upload, bestaetigen aber keine Einreichung. Vor Versand Empfaenger, Inhalt und komplette Anhaenge anzeigen/pruefen.

Der Motivationsschreiben-Prompt enthaelt einen herauskopierbaren Empfaengerblock in der Reihenfolge Firma, Kontakt, Strasse Nr., PLZ Ort. Fehlende Adressbestandteile nicht erfinden. Eigene Inhalte gehen erst durch bewusstes Kopieren/Verwenden an ein externes Werkzeug.

SMTP-Bewerbungsversand ist eine Aussenwirkung. `E-Mail / Antwort erfassen` ist dagegen Protokollierung und erzeugt keinen Versand und keinen automatischen Nachfasstermin.

## Integrationen und Betrieb

- SMTP: personenbezogene Einstellungen plus konfigurierte Systemmail-Funktion. Vor Aktivierung in einer neuen Umgebung nur kontrollierte Testpostfaecher verwenden.
- ICS: Download ist ein Standbild; privater Feed kann abonniert werden. Feed-Token ist vertraulich.
- Google: Betreiber-OAuth-Client plus Benutzerautorisierung und Zielkalender. Synchronisation nach Workflowmigration freigeben, Fremdeintraege schuetzen, Synchronisationsfehler sichtbar lassen.
- Job-Room: externe manuelle Erfassung mit unabhaengiger Bestaetigung.
- Textextraktion: `deploy/extract-document-texts.php`, PDF benoetigt `pdftotext`. Kein automatischer Browser-Screenshot-Worker fuer Inserate.
- UI-Texte: DB-basierte Laufzeit plus bewusst versionierte Seeds. Hilfequelle und Generator siehe DB_I18N_CONCEPT.md.

## Layoutvertrag

Alle Befehle als erkennbare Buttons, Navigation und Datenverknuepfungen als Links. Eine Datenzeile pro Datensatz; keine Karte in Tabellenzellen. Lange Namen, Titel, Adressen und Aktionen duerfen nicht abgeschnitten werden. Status/Usage duerfen umbrechen. Ueberfluessige automatische Auswahlspalten entfallen; funktionale Checkboxen wie Adminanzeige und Job-Room bleiben.

Kopfbereich: Titel, Anzahl und Aktion zusammenhaengend ausrichten. Keine leeren Bearbeitungscontainer, Platzhaltertexte oder ueberbreiten Eingaben ohne fachlichen Grund. Kalender und Tabellen muessen auch bei schmalem Fenster bedienbar bleiben. Hilfesuche durchsucht Titel, Zusammenfassung, Schritte und Hinweise.

## Bekannte Grenzen und Neuaufbau-Risiken

- Ein vollstaendiger Neuaufbau ist noch nicht gegen eine leere MariaDB durchgetestet. Basis-SQL allein bildet den aktuellen Stand nicht ab.
- Historische SQL-Views projizieren alte next_action-Felder; sie sind nicht das aktuelle Kalenderdatenmodell.
- Die UI-Datenbank enthaelt ausserhalb der hier versionierten Seeds weitere Texte. Ein bereinigter Textkatalogexport ist fuer eine identische Oberflaeche erforderlich.
- Einige Legacy-Beschriftungen ausserhalb der Hilfe sind weiter sprachlich unvollstaendig. Diese Dokumentationsrunde behauptet keine komplette UI-Neuuebersetzung.
- Karten zeigen teilweise noch technische Zuordnungszusätze wie Firma/Job. Das ist keine fachliche Statusdefinition.
- Geschuetzte GET-Masken pruefen an manchen Stellen erst nach begonnenem HTML-Output; anonyme Redirects muessen im Neuaufbau frueher erfolgen.
- v6-Workflowbereinigung produktiver Bestandsdaten ist separat freizugeben und noch nicht als ausgefuehrt belegt.
