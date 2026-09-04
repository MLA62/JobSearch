# Programmdokumentation

Stand: 2026-09-04. Version 2.0.7 lokal vorbereitet; Deployment und angemeldete Importabnahme ausstehend. Letzter bestätigter Live-Stand ist 2.0.6. Schnellimport und profilbasierte Suche bleiben getrennte Einstiege mit gemeinsamem Prüf-/Importweg. Historische Release-Nachweise bleiben getrennt von diesem Stand.
Verbindliche Produktregeln: [REQUIREMENTS.md](REQUIREMENTS.md), [WORKFLOW.md](WORKFLOW.md).
Exakte Tabellen, Felder und Funktionssignaturen: [DATA_MODEL.md](DATA_MODEL.md), [INTERFACES.md](INTERFACES.md).

## Verifizierte Suche und Import 2.0.7 (lokaler Stand)

- Discovery liefert nur Kandidaten-URLs. Vor der Anzeige liest die App jede Originalanzeige einschließlich erkannter Drill-down-Links. HTTP 404/410, erkannte Soft-404, Ablaufhinweise und vergangenes validThrough schließen die Anzeige aus. Ein zukünftiges validThrough oder ein aktives Bewerbungsbedienelement gilt als Verfügbarkeitsindiz; alte Anzeigen ohne aktuelle Frist und Anzeigen ohne positives Indiz bleiben unbekannt und werden nicht vorgeschlagen. Das ist keine Garantie einer tatsächlich noch unbesetzten Stelle.
- Die KI erhält vollständigen Originaltext und strukturierte Originalmetadaten, keine bloßen Such-Snippets. Jede bewertete Dimension verlangt ein überprüfbares wörtliches Zitat aus der Originalquelle. Firmenmarketing beweist keine Stelleneigenschaft. Die App berechnet den gewichteten Score: Rolle 35, Ort 20, Pensum 15, Ebene/Arbeitsmodell/Stellenart/Lohn je 10, Benefits 3, Ausschlüsse 15, Reise/Verfügbarkeit/weitere Wünsche je 5; nur aktive Kriterien zählen. Erfüllt = 1, teilweise = 0,5, unbekannt/nicht erfüllt = 0. Beliebiges Arbeitsmodell ist kein Kriterium. Fehlende Rolle/Ort-Belege und belegte harte Konflikte schließen aus. Vorläufiger Vorschlagsschwellenwert 70 %. Semantische Interpretation und Übersetzungsqualität bleiben modellabhängig.
- Neue Session-Suche: CSRF- und benutzergebundene ID, maximal 30 Minuten, ein Discovery-/Prüfschritt je Request. Abgelehnte Kandidaten zählen nicht zur Zielzahl. Weiter mit weiteren Kandidaten/Quellen; Schutzgrenzen 60 geprüfte Kandidaten oder drei Discovery-Runden pro Quelle. Eine erreichte Grenze wird ausdrücklich als unvollständige Suche angezeigt, nicht als erschöpftes Internet. KI-Dienstausfälle sind Fehler, kein Beleg für unpassende Jobs.
- Tabelle enthält nur verifizierte Treffer desselben Kriterienstands und maximal 15 Minuten alte Prüfungen, nach Match absteigend. Alte ungeprüfte Session-Ergebnisse werden ausgeblendet. Gelöschte Treffer sperren bekannte Portal- und Original-URL; Trackingparameter werden beim Vergleich entfernt. Suchfenster zeigt tatsächliche Zähler und Laufzeit, bleibt bei Abschluss/Fehler offen und bietet Abbrechen beziehungsweise Ergebnisse anzeigen. JavaScript ist für diesen mehrstufigen Suchweg erforderlich. Autosave-Requests werden vor Suchbeginn abgeschlossen.
- Originalimport und Schnellimport einzelner/mehrerer URLs verwenden verifiedJobImport. Zusätzlich zur verlinkten Firmenseite werden bis zu drei explizite Kontakt-/Impressum-/Teamlinks desselben Hosts gelesen. Firmennamenbezug, Belegzitate, Feld-Allowlist und Typprüfung begrenzen die Übernahme; keine erfundenen Kontakt-/Adresswerte. Unlesbare Firmenunterseiten bleiben ohne Ergänzung; keine universelle Firmenwebsite-Erkennung oder Browserausführung behauptet.
- Gemeinsamer transaktionaler Writer für eigene Firmen, Kontakte und Jobs. Firmenabgleich über Namen; Kontaktabgleich innerhalb der Firma per E-Mail oder kompatiblem Namen ohne widersprüchliche E-Mail, unabhängig vom einzelnen Job. Kontakt behält seine bestehende optionale Job-Zuordnung und ist über die Firma sichtbar. Jobabgleich über Quell-/Original-URL. Nur leere Felder ergänzen; Arbeitgeberkonflikt führt zum Rollback. Keine automatische Zusammenlegung von Namensvarianten oder mehrdeutigen Firmen. Gleichzeitige Imports unterschiedlicher Sitzungen sind nicht durch eine neue DB-Unique-Regel abgesichert.
- Jobs erhalten belegte Anforderungen, Benefits, Pensum, Vertrags-/Arbeitsmodell, Lohn und Datumsangaben in vorhandenen Spalten. match_score und raw_import_data speichern neu berechneten Score, Kriterienhash, Belegstellen, Prüfzeit, Originaltext und Quellen-Hashes. Gefüllte Jobfelder, Notizen, Status, Bewerbungen und Dokumente bleiben erhalten. Alte heuristische Job-Scores werden nicht mehr als verifizierter Match angezeigt. Übernehmen prüft neu; der Commit verweigert zwischenzeitlich geänderte Kriterien oder abgelaufene Fristen. Bewusst schnellimportierte verfügbare Jobs dürfen niedrigen Match haben, gelangen aber nicht dadurch in die Vorschlagstabelle.
- Keine Schema-/Bestandsmigration und kein automatisches Original-PDF/PNG. Die Hostinggrenzen aus 2.0.5 bleiben bestehen. Kein kostenpflichtiger Render-Dienst.

## Speicherkorrekturen 2.0.6 (historisch deployed)

Deployment vom 04.09.2026: Serverdatei und öffentliche Versionsanzeige bestätigt;
angemeldete fachliche Abnahme bleibt offen. Der nachfolgende 2.0.5-Abschnitt ist historisch.

Die Diagnose fand drei konkrete Lücken im Stand 2.0.5: Eine einzelne Schnellimport-URL
landete im Formularentwurf; save_job übernahm daraus weder company_details noch contacts.
Vorhandene Kontakte wurden ohne Ergänzung sofort übersprungen. Ein alter Session-Entwurf
konnte zudem die Ansicht des bereits importierten Jobs überlagern.

Übernehmen, einzelne Schnellimport-URL und mehrere URLs verwenden jetzt importStoreDraft:
Firma, fehlende Adressfelder, Job und Kontakte werden gemeinsam transaktional gespeichert.
Wiederimport anhand eigener identischer Quell-URL korrigiert die Arbeitgeberzuordnung und
Originalfelder, behält Notizen/Status/Dokumente. Vorhandene Kontaktpersonen erhalten nur
fehlende Felder; gefüllte Kontaktangaben bleiben unverändert. Veraltete Vorschauen werden
beim URL-Import verworfen. Freitext verwendet weiterhin die manuelle Formularvorschau.
Keine automatische Bestandsbereinigung beim Deployment, keine Erweiterung der Parserabdeckung.
Die konkrete vom Benutzer zuletzt betroffene Anzeige ist noch nicht bestätigt.

Zusatz: Treffer werden numerisch nach Match-Prozent absteigend sortiert; gleiche Werte
behalten ihre Reihenfolge. Übernehmen zeigt einen nativen modalen Dialog in fünf Sprachen
mit zwei tatsächlichen Phasen, unbestimmtem Fortschrittsbalken und verstrichener Zeit.
prepare_job_import liest externe Seiten und hält den Entwurf fünf Minuten benutzergebunden
in der Session (maximal fünf). Erst commit_job_import mit CSRF und einmaligem Vorbereitungstoken
ruft den transaktionalen Writer auf. Ein Abbruch in der Lesephase sendet keinen Commit;
der serverseitige Leseabruf kann noch auslaufen, legt aber keine CRM-Datensätze an.
Während der abschließenden DB-Transaktion ist der Abbruchbutton gesperrt. Escape/Backdrop
schließen nicht; Fehler bleiben sichtbar. Bei unklarer Commit-Antwort wird ausdrücklich
zum Prüfen der Jobliste aufgefordert. Nach bestätigtem Erfolg öffnet sich der gespeicherte Job.
Der bestehende Suchdialog wird durch diese Erweiterung nicht geändert.

## Originalimport 2.0.5 (historischer Release-Stand)

- Der Import folgt erkannten Original-Links bis zu drei Inseratseiten. Jobs.ch/Jobup: externalUrl aus serialisiertem Seitenzustand; sonst explizit beschriftete Original-Links. Der erste Kandidat wird verwendet, nicht beliebig weitere Links durchsucht. Ein Titelvergleich verhindert offensichtliche Fehlzuordnungen, ist aber kein semantischer Identitätsbeweis.
- Bei ohws.prospective.ch wird der sichtbare main-Inhalt mit Absätzen übernommen, statt nur die verkürzte Schema-Beschreibung. Kontakte aus contactInfo-Absätzen bzw. applicationContact werden zusätzlich zu strukturierten Kontakten gespeichert. Das ist keine universelle Erkennung jedes Portal-Layouts.
- Ein namentlich passendes Firmenlogo kann zur Arbeitgeberwebsite führen. Fehlende Adressfelder werden nur aus einem explizit firmennamengebundenen Postadressblock im Footer/address ergänzt. Arbeitsort ist weiterhin keine Firmenadresse. Unlesbare Firmenwebseiten verhindern den ansonsten lesbaren Inseratimport nicht; fehlende Angaben bleiben leer.
- Jeder HTTP-Abruf begrenzt Größe und Laufzeit, validiert öffentliche DNS-Adressen und pinnt die ausgewählte IP. Weiterleitungen werden vor dem Folgeabruf neu geprüft. Cookies, JavaScript, Bot-Sperren und Login-Barrieren werden nicht durch einen Browser bearbeitet.
- Übernehmen speichert Firma, Job, Kontakte und Audit gemeinsam in einer DB-Transaktion. Ein Reimport kann auch die Firmenzuordnung vom Portal-Konzern zur tatsächlichen Arbeitgeberfirma korrigieren. Notizen, Status, Bewerbungen und bestehende Dokumente bleiben erhalten. Portal-URL bleibt Dublettenschlüssel, Original-URL und Quellenkette werden im Audit gespeichert. Schnellimport nutzt denselben Parser, aber weiterhin seinen bisherigen Speicherablauf.
- Die Erzeugung einer nachgebauten Tabelle als angebliches Original-PDF entfällt. Bestehende Tabellen-PDFs werden weder gelöscht noch ersetzt. Echte manuelle PDF-/Bild-Uploads bleiben möglich. Kein automatisches Original-PDF/PNG: Auf dem geprüften PHP-Hosting sind Browser-/Node-Laufzeit nicht vorhanden und Programmstarts gesperrt; ein separater Host wurde vom Benutzer verneint. Keine kostenpflichtigen Render-Dienste angebunden.
- Keine Schemaänderung oder Bestandsmigration. Produktive Funktionsabnahme bleibt nach dem Deployment gesondert erforderlich.

## Korrekturen 2.0.4 (historischer Release-Stand)

- Ergebnissprache folgt der aktiven App-Sprache, nicht einem abweichenden Profil-Standard. Ein separater Responses-Aufruf übersetzt Titel, Kurzbeschreibung und Match-Begründung; IDs, vollständige Feldbelegung und Locale werden validiert. Der Match-Wert und Quellen bleiben unverändert. Die semantische Übersetzungsqualität bleibt modellabhängig.
- Session-Treffer erhalten Locale und Übersetzungsrevision. Alte oder anderssprachige Treffer werden beim Aufruf neu übersetzt. Bei Fehlern werden sie vorübergehend ausgeblendet und eine Fehlermeldung angezeigt; erneuter Versuch nach 60 Sekunden.
- Der Import verwendet den vollständigen Originaltext aus JobPosting.description oder einem expliziten Inserat-Textbereich, mit Absätzen. SEO-Metabeschreibungen sind kein Ersatz. Ohne lesbaren Originaltext bricht der Import vor der Datenspeicherung ab.
- Arbeitgeberadresse, Website, E-Mail und Telefon stammen aus hiringOrganization; Arbeitsort wird nicht als Firmenadresse ausgegeben. Nur leere Firmenfelder werden ergänzt. Ein benannter Bewerbungskontakt wird der Firma und Stelle zugeordnet; Wiederholung derselben Zuordnung erzeugt kein Duplikat. Freitext-Kontaktextraktion und zusätzliche Firmenrecherche sind damit nicht behauptet.
- Bewusstes erneutes Übernehmen aktualisiert Titel, Arbeitsort und Originalbeschreibung eines eigenen Jobs mit identischer Quell-URL sowie fehlende Firmen-/Kontaktdaten. Notizen, Bewerbungen und Dokumente bleiben erhalten; vorhandene PDFs werden bei Wiederholung nicht ersetzt. Keine pauschale Änderung bestehender Datensätze beim Deployment.
- Der JavaScript-Suchrequest übermittelt die zuvor fehlende Aktion search_ai_jobs.

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
| `job_platform_search` | Suchbegriff, Suchort, Trefferzahl und Plattformen sind direkt editierbar und pro Benutzer speicherbar. Beim ersten Aufruf stammen sie aus dem Profil; nur „Standardwerte aus dem Profil“ überschreibt sie wieder. Die KI kann auf bewussten Klick einen begrenzten Suchvorschlag erstellen; sie erhält nur Suchkriterien. Externe Recherche und Import bleiben kontrolliert, kein autonomer Bewerbungsversand. |
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
- Ab 2.0.7 verwendet Job-Match die belegbasierte Bewertung oben. Ungeprüfte Bestandsjobs zeigen keinen erfundenen Prozentsatz; die frühere Basis-50-Heuristik entfällt.
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
