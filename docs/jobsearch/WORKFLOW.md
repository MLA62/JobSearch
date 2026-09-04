# Bewerbungsworkflow und Kalender

Stand: 04.09.2026. Aktuelles Verhalten für Release 2.1.5.

## KI-Arbeitsanzeige und Kennzeichnung

- Manuell gestartete KI-Vorschläge und KI-Bewerbungstexte öffnen `In Arbeit` bereits vor dem Request; die Seite wechselt erst nach Abschluss. Die direkte Klickverarbeitung funktioniert auch in mobilen Browsern.
- Abbrechen beendet die Browser-Anfrage und lässt die aktuelle Seite geöffnet. Eine auf dem Server bereits abgeschlossene Transaktion wird dadurch nicht rückgängig gemacht.
- Die Fusszeile zeigt Hersteller und Modell. Sie zeigt keine lokale Guthaben- oder Kontingentschätzung.

## Textvorbereitung mit KI

- Beim Erstellen eines Bewerbungsentwurfs werden Betreff, Begleit-E-Mail und Motivationsschreiben aus Profil, aktuellem lesbarem CV, Stelle, Firma und Kontakten vorbereitet.
- Der asynchrone Aufruf erhält sein Navigationsziel als explizite Serverantwort und öffnet danach den erstellten oder vorhandenen Bewerbungsdatensatz.
- Die Initialisierung ergänzt nur leere Felder; vorhandene Benutzertexte bleiben bestehen.
- Die drei Felder sind normale bearbeitbare Bewerbungsdaten und unterliegen dem Autosave.
- Eine zweizeilige, nicht gespeicherte KI-Instruktion überarbeitet die vorhandenen Texte gemäß Auftrag. Bleibt sie leer, erstellt die KI alle drei Texte vollständig neu aus den verfügbaren Bewerbungsdaten; bisherige Texte werden dann nicht als Vorlage übermittelt.
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
