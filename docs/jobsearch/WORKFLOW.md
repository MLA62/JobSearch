# Bewerbungsworkflow und Kalender

Stand: 10.09.2026. Aktuelles Verhalten für Release 2.4.2.

Ergänzung 2.4.2: Der Job-Room-Helper verwendet ausschließlich `applied_at` als Bewerbungsdatum.
Datensätze ohne Bewerbungsdatum werden weder für die Monatsauswahl noch als Karte berücksichtigt;
`created_at` und `updated_at` sind dafür kein Ersatz. Firmenlinks zu Jobs, Bewerbungen und Kontakten
stehen in der Firmenliste jeweils vollständig auf einer eigenen Zeile.

Ergänzung 2.4.1: Firmen zeigen Bewerbungen nur dann an, wenn ein aktiver Bewerbungsdatensatz zu
einem aktiven Job genau dieser Firma besteht. Vermittlerbeziehungen werden im Firmenpfad dargestellt,
aber nicht als Bewerbung der Vermittlerfirma gezählt.

Ergänzung 2.4.0: Ein Admin-Auftrag durchläuft bis zu vier selbstständige Planungs-, Ausführungs- und
Prüfrunden. Die App normalisiert und führt den Plan aus, gibt konkrete Datenbankfehler an die KI zurück,
lädt Tabelleninventar und – bei entsprechendem Auftrag – Profil und aktuellen CV neu und lässt die KI
Abhängigkeiten selbst korrigieren. Nach dem ersten erfolgreichen Schreibvorgang ist zwingend eine weitere
Prüfrunde erforderlich. Erst ein bestätigter Abschluss mit mindestens einem ausgeführten Schreibvorgang
beendet einen Änderungsauftrag.

«Vorschlag erstellen» im Schnellimport startet zuerst die Quellenermittlung und verarbeitet danach
jede gefundene Anzeige in einem eigenen, serverseitig gespeicherten Stapelschritt. Der modale Dialog
zeigt den aktuellen Zähler und ergänzt den Verlauf nach jedem Import, jeder Aktualisierung oder jedem
konkreten Fehler. Abbrechen stoppt die folgenden Stapelschritte.

## Admin-KI und Plattformdaten

Ergänzung 2.3.7: Der Server übergibt der KI die exakten beschreibbaren Felder, Pflichtfelder und
Abgleichfelder aller freigegebenen Tabellen. Vor der Transaktion werden Singular-/Aliasnamen,
Feldaliasse und Fremdschlüssel normalisiert. Firmenoperationen laufen stets über den spezialisierten
Firmenschreiber; UID, Handelsregister und Aliasse bleiben dadurch für Dublettenprüfung und spätere
Statusabfragen erhalten. Teilupdates benötigen nur bei einer tatsächlichen Neuanlage sämtliche
Pflichtfelder. Bei Mehrfachoperationen werden Firmen und Stammdaten vor abhängigen Jobs, Kontakten,
Bewerbungen, Dokumenten, Logs und Ausgangsdaten verarbeitet.

Ergänzung 2.3.6: Der asynchrone Formularaufruf verwendet die deklarierte Formularzieladresse oder
die vollständige aktuelle Seitenadresse. Ein Button namens `action` kann das Request-Ziel nicht mehr
überschreiben; der Auftrag gelangt dadurch zuverlässig zum geschützten Admin-KI-Handler.

Ergänzung 2.3.5: Die Anweisung wird vor dem API-Aufruf gespeichert. Erfolg und Fehler ergänzen den
benutzergebundenen Datenbankkontext chronologisch; ein HTTP-Fehler wird als strukturierte Antwort
im bestehenden Ausgabefeld verarbeitet und lädt die Seite nicht neu. Der Kontext endet erst durch
«Gedächtnis löschen». Der bisherige Erklärungstext oberhalb der Ausgabe ist entfernt.

Ergänzung 2.3.4: Jeder direkte Admin-Auftrag innerhalb der JeMa-Jobs-Plattform wird vollständig ausgeführt, auch als Einzel-, Mehrfach- oder Rechercheauftrag. Die Frage, ob ein Datensatz bereits erfasst wurde, wird als konkreter Datenbank-Lookup beantwortet. Die Ausgabe unterstützt Markdown, insbesondere **Fettdruck**, und wird sicher im Ausgabefeld gerendert.

- Eine ausdrücklich beauftragte Recherche mit «erfassen», «speichern», «importieren», «aktualisieren»
  oder einer Mehrfachoperation erzeugt strukturierte Tabellenoperationen und führt sie vollständig in
  einer Transaktion aus.
- Das gilt für die freigegebenen Nutzer-Datentabellen der Plattform, nicht nur für Firmen und Kontakte.
  Bestehende Zeilen werden über Abgleichfelder ergänzt; Soft-Deleted-Zeilen bleiben gelöscht.
- Authentisierung, Geheimnisse, Audit, Löschungen und der Versand externer E-Mails sind keine erlaubten
  KI-Schreibziele. Das Ergebnis nennt kompakt die betroffenen Tabellen und IDs.
- Der Kontext der letzten Aufgaben bleibt benutzergebunden in der Datenbank und wird nur über «Gedächtnis löschen» entfernt.

## Manueller Schnellimport

- Eine bewusst eingegebene HTTPS-Inserat-Adresse wird übernommen, sobald die Seite als Stellenanzeige lesbar und vollständig genug für Firma, Titel und Beschreibung ist.
- Fehlende automatische Aktualitätsbelege blockieren diesen manuellen Import nicht.
- Original-Drill-down, Firmen- und Kontaktdatenrecherche, Match-Neuberechnung und Dublettenbehandlung laufen weiterhin vollständig.
- Die automatische profilbasierte Suche zeigt weiterhin nur nachweislich verfügbare Anzeigen.

## Formatierte Langtexte und Verlauf

- Mehrzeilige fachliche Texte besitzen einen HTML-Mini-Editor für Absätze, Fett, Kursiv, Links, Aufzählungen, nummerierte Listen, Ein-/Ausrücken, Format löschen, externe HTTPS-Bilder, Tabellen und Trennlinien.
- Die HTML-Ansicht erlaubt gezielte Quelltextkorrekturen. Beim Zurückschalten auf WYSIWYG übernimmt eine eigene Commit-Funktion den bereinigten Quelltext sichtbar. Vor jedem nativen oder programmierten Formulartransport wird der aktive Modus erneut verbindlich synchronisiert; dies umfasst manuelles Speichern, Autosave und KI-Aufrufe. Während des Tippens wird unvollständiges HTML nicht vorzeitig normalisiert.
- E-Mails und Dossiers behalten die sichere Formatierung. Karten, Tabellen, PDF-/Textausgaben und KI-Kontexte erhalten daraus lesbaren Klartext mit Absätzen.
- Aktivitäten, Statushistorien, Kontakt-Logs und Audit-Auszüge laufen chronologisch von oben nach unten.

Das Feld «Gesendet am» übernimmt den vollständigen gespeicherten Zeitstempel einschließlich Sekunden und erlaubt diese Auflösung bei der Eingabe.

## KI-Arbeitsanzeige und Kennzeichnung

- Manuell gestartete KI-Vorschläge und KI-Bewerbungstexte öffnen `In Arbeit` bereits vor dem Request; die Seite wechselt erst nach Abschluss. Die direkte Klickverarbeitung funktioniert auch in mobilen Browsern.
- Abbrechen beendet die Browser-Anfrage und lässt die aktuelle Seite geöffnet. Eine auf dem Server bereits abgeschlossene Transaktion wird dadurch nicht rückgängig gemacht.
- Die Fusszeile zeigt Hersteller und Modell. Sie zeigt keine lokale Guthaben- oder Kontingentschätzung.

## Textvorbereitung mit KI

- Beim Erstellen eines Bewerbungsentwurfs werden Betreff, Begleit-E-Mail und Motivationsschreiben aus Profil, aktuellem lesbarem CV, Stelle, Firma und Kontakten vorbereitet.
- Nach dem sichtbaren Arbeitsdialog sendet der Browser `Bewerbung vorbereiten` als normale Formularnavigation. Dadurch folgt er der serverseitigen Weiterleitung direkt zum erstellten oder vorhandenen Bewerbungsdatensatz.
- Eine gelöschte Bewerbung wird niemals reaktiviert. Sie blockiert keine neue Bewerbung für denselben Job. Parallele Klicks öffnen atomar denselben aktiven Datensatz.
- Scheitert nur die Textvorbereitung, bleibt die Bewerbung angelegt und wird zur manuellen Bearbeitung geöffnet. Speicher- und Textfehler werden getrennt mit Fehlerreferenz gemeldet.
- Die Initialisierung ergänzt nur leere Felder; vorhandene Benutzertexte bleiben bestehen.
- Die drei Felder sind normale bearbeitbare Bewerbungsdaten und unterliegen dem Autosave.
- Eine zweizeilige, nicht gespeicherte KI-Instruktion überarbeitet die vorhandenen Texte gemäß Auftrag. Vor dem Aufruf synchronisiert der Browser die sichtbaren Mini-Editoren und stoppt ausstehende Autosaves; die Aktion wird als normale Formularnavigation gesendet. Bleibt die Instruktion leer, erstellt die KI alle drei Texte vollständig neu aus den verfügbaren Bewerbungsdaten; bisherige Texte werden dann nicht als Vorlage übermittelt.
- Die KI-Aktion selbst ändert weder Versandstatus noch Versandzeit und versendet keine Nachricht.
- Bei einem API-Ausfall werden bearbeitbare Grundentwürfe eingesetzt; fehlende Fakten werden nicht erfunden.

## Statusvertrag

| Statuscode | Anzeige | Aktion und Nachweis |
|---|---|---|
| draft | Entwurf | Vorbereitung, kein Versandnachweis, keine automatische Terminierung |
| ready | Bereit | Unterlagen bereit; angezeigte Aufgabe Bewerbung senden ist kein Termin |
| sent | Gesendet | Tatsaechlicher Versand/extern bestaetigte Einreichung, applied_at |
| interview | Bewerbungsgespraeche | Phase; mehrere unabhaengige datierte Gespraeche |
| accepted | Zusage | Ergebnis im Verlauf und kurzer Kalendernachweis |
| rejected | Absage | Ergebnis im Verlauf und kurzer Kalendernachweis |

Dies ist die fachliche Reihenfolge, keine starre Pflicht, jede Phase zu durchlaufen:
Absage oder Zusage kann direkt nach Versand eintreffen.
confirmed, assessment, offer, withdrawn und closed sind lesbare Altdaten, keine neuen Standardoptionen.
Ein vorhandener Sonderstatus darf erhalten bleiben, bis er bewusst fachlich geklaert wird.

## Schreibwege und Zeiten

- Start aus einer Stelle legt einen Entwurf an.
- Normales Speichern/Autosave schreibt nur bei geaendertem Status einen Verlaufseintrag.
- E-Mail-Versand und externe Einreichung benutzen je Bewerbung denselben Advisory-Lock.
- Externe Einreichung ist eine bewusste Bestaetigung des Benutzers, keine Browserautomatisierung.
- Draft/ready haben keine sichtbare applied_at-Behauptung. Wechsel zur Vorbereitung setzt sie im aktuellen Code auf NULL.
- Bei sent ohne vorhandenen Zeitpunkt setzt die Erfassung die aktuelle Benutzerzeit.
- Korrektur eines Zeitpunktes ist moeglich; unveraenderte Minuten behalten vorhandene Sekunden.
- Interview/Ergebnis ohne angewiesenen Versandzeitpunkt erfinden keinen Versand.
- SMTP plus Datenbank ist keine gemeinsame verteilte Transaktion: bei unklarem Versandfehler
  zuerst Ausgang/protokollierte Nachricht pruefen, nicht blind erneut senden.

## Workflowdatum

GREATEST aus Erfassungsdatum, juengstem Statuswechsel, tatsaechlichem Versanddatum (ausser Vorbereitung)
und groesstem Startdatum beruecksichtigter, nicht stornierter, verknuepfter Nicht-Meilenstein-Termine.
Die Terminquellen sind manuell, workflow_appointment, contact_log sowie ausdrueckliches follow_up
aus application_next_action. updated_at/Autosave zaehlt nicht.
Das kann ein zukuenftiges Datum sein. Es ist nicht MIN(naechster offener Termin).

Bewerbungstabelle: Workflowdatum | Job | Firma | Status | Kanal | Aktionen.
Datum ohne Uhrzeit; CSV/PDF dieselben fuenf Datenfelder ohne Aktionsspalte.
Karten und Dossier verwenden dieselbe Sicht; tatsaechlicher Versand nur bei vorhandenem Nachweis.

## Kalender

Agenda, Tag, Arbeitswoche Montag-Freitag, Woche Montag-Sonntag und Monat.
Nachfassen und jedes Gespraech erhalten eigenes Datum/Uhrzeit; Ende liegt nach Start, Standarddauer 30 Minuten.
Keine neu erzeugten Ganztagseintraege. Historische/importierte Ganztagsdaten bleiben unterscheidbar.
Mitternacht ist nicht automatisch ganztags.
Heute wird anhand der Benutzerzeitzone bestimmt, nicht anhand des Navigationsdatums.

Kein Termin fuer Entwurf, Bereit, review_documents, send_application, await_response oder prepare_interview.
Gesendet/Zusage/Absage werden als kurze transparente Nachweise ohne Alarm projiziert.
Wiederholte Projektion aktualisiert dieselbe Quelle; sie erzeugt keine zweite Statuskopie.
Ein Interviewstatus allein erzeugt keinen Gesprächstermin. Ein bewusst angelegter Interviewtermin
kann eine gesendete/bestaetigte Bewerbung in die Gespraechsphase bringen, nicht ein Ergebnis zuruecksetzen.
Abschluss aendert denselben Termin. Loeschen im Kalender storniert; Meilensteine nicht als freie Termine editieren.
Formular-Request-IDs schuetzen neue Termine gegen Wiederholung desselben Submit.

## Kontakt-Log und Job-Room

Eine Nachricht/Antwort protokollieren ist nicht E-Mail senden.
Kontakt-Log-Follow-up-Felder sind Altbestand. Neue Formulare und Projektion erzeugen daraus keine Termine.
Keine zusaetzliche automatisch angelegte Aktivitaet allein fuer Bewerbung eingereicht.
Kontaktzaehler: Anzahl Logzeilen der Person; offen/geplant ist deren Teilmenge, keine Anzahl Bewerbungen.

Im Job-Room erfasst: Checkbox. Erst danach Vorstellungsgespraech und Noch offen/Anstellung/Absage sichtbar.
Ausblenden loescht bereits gespeicherte Ergebnisse nicht. Ankreuzen ist keine API-Uebertragung an Job-Room.
Der Job-Room-Helper listet nur tatsächlich datierte Bewerbungen; fehlende Bewerbungsdaten erzeugen
weder einen Eintrag noch ein aus Erfassungs- oder Änderungszeit abgeleitetes Datum.

## Bestandsmigration v6

Die produktive Ausfuehrung von workflow_calendar_v6 ist in dieser Dokumentation NICHT bestaetigt.
Die Adminseite workflow_review zeigt den konkreten Plan. Ein Hash bindet die Bestaetigung an diesen Plan.
Advisory-Lock, erneute gesperrte Pruefung und Transaktion; Sicherung in workflow_data_backups VOR Aenderung.
Marker und Aenderungen gemeinsam committen, Fehler rollen zurueck; erneute Ausfuehrung ist wirkungslos.
Alte erzeugte Vorbereitungs-/Dublettenereignisse stornieren, eindeutige datierte Follow-ups entkoppeln
oder wiederherstellen, bekannte next_action-Felder bereinigen. Unbekannte Werte erhalten.
Manuelle Termine und fremde Kalenderdaten nicht pauschal zusammenlegen oder loeschen.

Google-Synchronisation ist bis zum v6-Marker gesperrt. Rueckweg: betroffene gesicherte Datensaetze
gezielt pruefen/wiederherstellen; keine pauschale Ruecksicherung ueber spaetere Benutzerarbeit.

## Querverweise

[Programmlogik](PROGRAMMDOKUMENTATION.md), [Datenmodell](DATA_MODEL.md),
[Tests](TESTING.md), [Neuaufbau](REBUILD.md), [Hilfe](help/de-CH.md).
