# Programmdokumentation

Stand: 2026-09-21. Aktueller Code 2.4.48; Verifikation und Deployment siehe jeweiligen Release-Nachweis.

## Absatzabstand 2.4.48

Die auf Rich-Text-Editor und gespeicherte HTML-Ansichten begrenzten
CSS-Regeln setzen den unteren Rand von `p`, älteren `div`-Absätzen und
H1–H3 auf 8 pt. Der vorherige Wert betrug 6 pt. Schriftgrössen,
obere Ränder, weiche Umbrüche und Abstände innerhalb von Listen bleiben
unverändert. Es werden keine gespeicherten HTML- oder Datenbankwerte
migriert.

## Rich-Text-Absatzsemantik 2.4.47

Das gemeinsame Editor-Skript in `public/index.php` initialisiert für jedes
mehrzeilige Rich-Text-Feld eine `contenteditable`-Fläche. Da die Fläche in
einem `<label>` für das versteckte Textarea steht, unterdrückt der Click-
Handler die Label-Standardaktion. Andernfalls aktivierte ein Klick in den
Inhalt das erste Toolbar-Control und änderte beim anschliessenden Tippen
unbeabsichtigt Format und HTML. Das Formatmenü bewahrt die zuletzt im Editor
gesetzte Auswahl und wendet `formatBlock` auf Absatz/H1/H2/H3 an.
Ein fokussierter Editor setzt den Standard-Absatztrenner auf `<p>`;
`keydown` trennt Enter (`insertParagraph`) von Shift+Enter
(`insertLineBreak`). Die bestehende Synchronisierung mit dem Textarea
bleibt erhalten. `Tx` entfernt Inlineformat und Link. Selektierte
Listenpunkte werden als Absätze aus der Liste gelöst, während andere
Listeneinträge in ihrer Liste bleiben. Nur bei einer Überschrift wird
zusätzlich das Blockformat auf Absatz zurückgesetzt, um `<p>`-
Verschachtelung in normalen Absätzen zu vermeiden.

Die auf Editor und HTML-Ansichten begrenzten CSS-Regeln stehen in
`public/assets/app.css`. Normaltext ist 12 pt, H3/H2/H1 sind 14/16/18 pt;
Abstände sind in pt definiert. `li` und darin verschachtelte Absätze haben
keinen zusätzlichen Absatzabstand. Der Server-Sanitizer lässt H1 und
vorhandene `<div>`-Absatzgrenzen zu, entfernt weiterhin Attribute und
unsichere Inhalte. Eine bestehende gespeicherte Formatierung wird nicht
automatisch neu geschrieben. Keine Datenbankmigration.

## Job-Room-Detailimport 2.4.43

`importJobRoomId()` akzeptiert nur HTTPS-Detailpfade des Job-Rooms mit
gültiger UUID. `importFetchHtml()` ruft für diese Adressen die öffentliche
Job-Room-Detail-API über die bestehende SSRF-geschützte HTTP-Funktion ab.
`importJobRoomHtml()` prüft ID, öffentlichen Status, Titel, Arbeitgeber und
Text und konvertiert die Angaben in ein JobPosting-Dokument. Die übrige
Prüfung auf Laufzeit, Quellenbelege, KI-Match und Deduplikation bleibt
unverändert. Keine Schema- oder Bestandsdatenänderung. Die drei gemeldeten
öffentlichen URLs wurden lesend gegen die API geprüft; eine erfolgreiche
produktive Speicherung ist davon zu unterscheiden.
`extractJobRoomListingRows()` erkennt daneben kopierte Ergebnislisten ohne
Linkziele, mit Firma und Arbeitsort auf derselben oder getrennten Zeilen.
`importResolveJobRoomListing()` sucht je Karte über die öffentliche
Job-Room-Suche, verwirft unveröffentlichte bzw. in Titel, Firma oder
Postleitzahl abweichende Treffer und wählt bei mehreren Kandidaten nur
einen eindeutig durch Publikationsdatum und Beschreibung gestützten Treffer.
Die Verarbeitung erfolgt im bestehenden modalen Schnellimport pro Karte;
kein Sammel-HTTP-Request mit allen Stellen, keine stillen Auslassungen.

## Persistenz des Absagegrundes 2.4.42

Der Save-Handler schrieb `applications.rejection_reason` bereits. Die
Detailabfrage für `$applicationEdit` enthielt das Feld jedoch nicht, sodass
das Textfeld beim erneuten Rendern leer blieb und ein späteres Speichern
gefährdet war. Die Detailabfrage selektiert nun `a.rejection_reason`.
Ein Regressionstest prüft die Edit-Abfrage und die Vorbelegung des Felds.
Keine Schema- oder Bestandsdatenänderung.

## Sichtbare Online-Einreichung 2.4.41

Das Formular zeigt die Aktion `submit_online_application` direkt im
Onlinebewerbungsbereich neben dem Webformular-Link an, solange der Datensatz
`draft` oder `ready` und ohne `applied_at` ist. Die alte, weit entfernte
Schaltfläche am Formularende wurde entfernt. Der Hilfetext wird in anderen
Status nicht mehr angezeigt, damit er keine unauffindbare Aktion behauptet.
Der bestehende Handler prüft den Status und den tatsächlichen
Einreichungszeitpunkt erneut unter Transaktionssperre; er setzt Status,
Datum und Kalender erst nach bewusstem Klick, nicht beim Öffnen des Portals.
Keine Datenbankmigration.

## Abbruchkriterien und Empfängerdaten 2.4.40

`applicationAiTexts()` unterscheidet zwischen verbindlicher Validierung
und modellabhängiger redaktioneller Kritik. `applicationRecipientForApplication()`
liest bei jedem Aufruf die eigenen, fachlich zugeordneten Kontakte. Bei
einem bekannten vierzeiligen CRM-Empfängerblock kann
`applicationEditRecipientBlock()` keinen veralteten dreizeiligen Block
aus dem aktuellen Schreiben mehr bevorzugen. Der Schreibkontext lädt
ausserdem die aktuellen Jobanforderungen und eigenen Firmennotizen neu;
unbelegte Notizen werden nicht zu erfundenen Behauptungen.
`applicationEmailWithSignoff()`
ergänzt Grussformel und Namen, lässt vollständige HTML-E-Mails aber
unverändert. Ein redaktioneller oder simulierter Empfängerhinweis löst
höchstens einen zusätzlichen Versuch aus; der letzte verbindlich gültige
Entwurf wird bei einem misslungenen Folgeversuch behalten. Details und
verbleibende harte Grenzen stehen im [Audit](AI_ABORT_GATE_AUDIT.md).

## KI-Textreparatur 2.4.39

Die Fehlerreferenz `65C070A70D1C` zeigte, dass drei KI-Rückläufe wegen
einzelner ungeeigneter Sätze verworfen wurden, obwohl eine Bereinigung
vorhanden war. Zieltexte werden jetzt unmittelbar nach dem API-Rücklauf
bereinigt. Nur wenn danach ungeeignete Sprache verbleibt oder ein Text
zu kurz wird, fordert die App einen neuen Entwurf an. Bestehende, nicht
angewiesene Texte bleiben unverändert. Briefstruktur, expliziter Auftrag,
Quellenbezüge und Empfängerprüfung werden anschliessend wie bisher geprüft.

## Textschutz beim Öffnen und bei leerer KI-Instruktion 2.4.38

Die Bewerbungs-GET-Seite ruft `initializeApplicationTexts()` nicht mehr auf.
Sie liest Betreff, Begleit-E-Mail und Motivationsschreiben ohne Datenbank-
Schreiboperation. `initializeApplicationTexts()` prüft beim Vorbereiten
zuerst, welche Felder leer sind; bei vollständig vorhandenen Texten kehrt
die Funktion vor jeder Empfängerrecherche oder Bereinigung zurück. Bei
teilweise fehlenden Texten werden ausschliesslich diese Felder aus einem
neuen KI-Entwurf übernommen. Bestehende Felder bleiben exakt erhalten.

Beim manuellen KI-Button verhindert eine leere Instruktion jede Änderung,
wenn alle drei Textfelder gefüllt sind. Andernfalls führt derselbe Fill-only-
Abgleich nur neue Inhalte in leere Felder ein. Der Server erhält die aktuell
sichtbaren Editorwerte; ein nicht gespeicherter Entwurf bleibt bei einem
wirkungslosen KI-Klick im Formular sichtbar. Eine ausdrückliche Instruktion
nutzt weiterhin die gezielte Überarbeitung. Die früher dokumentierte
Neuerstellung aller Texte bei leerer Instruktion und die stille Reparatur
gespeicherter Texte beim Öffnen sind durch diesen Not-Update ersetzt.
Keine Schemaänderung, keine automatische E-Mail.

## Anrede-Reparatur 2.4.37

Die Briefprüfung vergleicht den Empfängerblock zeilenweise statt anhand
fragiler HTML-Zeilenabstände und erkennt Schweizer Anreden wie «Grüezi».
Steht die Anrede einige Kopfzeilen hinter Empfänger und Betreff, wird sie
ebenfalls erkannt. Fehlt sie tatsächlich in einem KI-Entwurf, ergänzt
`applicationLetterWithSalutation()` vor dem Briefhauptteil eine neutrale
Anrede in der gewählten Sprache. Der vorhandene Inhalt, Betreff und
Empfängerblock bleiben erhalten. Dies verhindert die gemeldeten drei
erfolglosen KI-Wiederholungen allein wegen einer fehlenden Anrede. Andere
Struktur-, Quellen- und Empfängerprüfungen bleiben aktiv. Keine
DB-Schemaänderung und kein Versand.

## Schweizer Schreibleitfaden und Empfängerprüfung 2.4.36

Der [Best-Practice-Leitfaden](SWISS_APPLICATION_WRITING_GUIDE.md) dokumentiert
Primärquellen von SECO/arbeit.swiss, UZH und ETH. Seine kompakten Regeln
werden bei jedem KI-Aufruf durch `applicationSwissWritingGuide()` übergeben.
`applicationWritingRelationship()` trennt die Empfängerrolle von der
künftigen Arbeitgeberrolle. Bei einem Vermittler ohne belegten Endkunden
wird der Auftraggeber beschrieben, nicht der Vermittler als Arbeitgeber.
Nach Quellen- und Strukturprüfung bekommt ein eigener API-Aufruf nur die
aktuellen Quellen, den fertigen Text und die angeforderten Zielfelder. Die
simulierte Empfängerprüfung liefert konkrete Mängel an den Schreibaufruf
zurück; höchstens drei Schreibversuche. Dies ist keine Rückmeldung eines
tatsächlichen Empfängers. Alte Versionen und andere Dokumenttypen werden
auch dem Prüflauf nicht übergeben. Kein DB-Schemaeingriff oder Versand.

## Neu aufgebauter KI-Schreibkontext 2.4.35

`applicationWritingContext()` beschränkt die Anfrage auf aktuellen
Stelleninhalt, Firma, sicher ermittelten Empfänger und gegebenenfalls
belegten Endkundenkontext. `applicationCvSourceRows()` lädt bei jedem
Aufruf genau den nach `updated_at` neuesten aktuellen Stammdaten-Lebenslauf
pro Metadatensprache; `applicationCvInputParts()` weist andere Dokumenttypen
zurück. Früher gespeicherte Bewerbungstexte und Dokumentlisten gelangen
nicht in diese Anfrage. Bei einer Überarbeitung liefert allein das gerade
abgesendete Formular `current_texts`; neue Entwürfe erhalten dort `null`.

Das neue Schreibbriefing priorisiert den konkreten Nutzen für den
Arbeitgeber, verbindet belegte Erfahrungen mit Stellenanforderungen und
vermeidet standardmässig Erfolgszahlen. Eine nachgelagerte Prüfung weist
Zahlen in neuen Entwürfen zurück. Bei manueller Überarbeitung haben die
benannten Felder und die aktuelle Instruktion Vorrang; nicht benannte Felder
werden unverändert übernommen. `applicationLetterStructureIssues()` lässt
eine Betreffzeile vor der Anrede zu. Die Empfängerfunktion ersetzt einen
unvollständigen Altblock, statt Adressen zu stapeln. Scheitert die
Überarbeitung, bleiben die gesendeten aktuellen Texte und die Anweisung im
Editor erhalten. Keine Datenbankmigration, kein Versand.

## Gezielte KI-Überarbeitung 2.4.34

`applicationEditTargets()` ordnet eine ausdrücklich benannte Betreff-, Mail-
oder Briefanweisung nur diesen Feldern zu; ohne Benennung sind die beiden
Langtexte betroffen. Der Server erhält die aktuellen Formularwerte, nicht
einen älteren Datenbank-Snapshot. Beim manuellen Editieren überschreibt der
explizite Auftrag die übliche Zielspanne von ungefähr 170–260 Briefwörtern,
nicht aber Faktenbindung, Empfänger oder Briefabschluss. Die Originalanweisung
steht in jedem Responses-Request am Schluss des User-Kontexts. Für einen
Retry wird die Eingabe aus dem Basis-Kontext neu aufgebaut und nur der
neueste Fehlhinweis ergänzt; alte Fehlerrunden sammeln sich nicht an.

`applicationEditRequestIssues()` prüft im gemeldeten deutschen Beispiel die
geforderte prozentuale Erweiterung gegenüber dem vorhandenen Brieftext und
verbliebene quantitative Erfolgsangaben. Der Empfängerblock mit Hausnummer
und Postleitzahl wird dafür ausgeklammert. Eine nicht erfüllte Anweisung
führt zu einer weiteren KI-Korrektur oder zu einer Fehlermeldung, niemals zu
einem stillen Speichern. Bei einem Fehler bleibt die Formularanweisung in
der Sitzung erhalten. Die Prüfung erkennt nicht jede mögliche freie
sprachliche Anweisung semantisch; für weitere Fälle bleibt eine fachliche
Abnahme nötig. Keine Datenbankmigration.

## KI-Laufzeitanzeige und belegte Bewerbungstexte 2.4.33

Der gemeinsame `ai-work-dialog` zeigt eine aktionsbezogene Statuszeile und
eine mit `setInterval` jede Sekunde aktualisierte Laufzeit. Er erhält vom
Server keinen Phasen-Stream; die Bezeichnung behauptet daher nicht, dass
ein bestimmter interner Schritt bereits abgeschlossen sei. Timer und Dialog
werden bei Abschluss, Abbruch und Navigation bereinigt.

Seit 2.4.35 ersetzt `applicationWritingContext()` den damaligen breiten
Prompt. Neue Generierung schliesst alte Bewerbungstexte aus; eine
ausdrückliche Überarbeitung liefert ausschliesslich die aktuell abgesendeten
Formulartexte. `applicationAiTexts()` übergibt bei jedem Aufruf
erneut den neuesten aktuellen Lebenslauf pro Sprache. Das strikte JSON-Schema
enthält neben den drei sichtbaren Texten interne `evidence_links` mit
aktueller CV-ID, CV- und Inseratzitat und einem tatsächlich verwendeten
Briefauszug. `applicationTextQualityIssues()` prüft Quellenzuordnung und
Substanz. Wörtliche CV-Fakten sind nur dann technisch mit dem Dokumentinhalt
abgleichbar, wenn korrigierter Text vorliegt; bei Binärdateien kann der Server
die Aussage nicht vollständig unabhängig verifizieren. Darum bleibt die
fachliche Sichtung vor Versand erforderlich.

Bei Beanstandungen bekommt der nächste KI-Versuch den konkreten abgelehnten
Entwurf und Fehlergründe. Der Lauf nutzt mittlere statt niedrige
Reasoning-Einstellung und ein grösseres Ausgabelimit. Aus einem weiterhin
ungenügenden KI-Rücklauf entsteht kein allgemeiner Fallback-Text. Bei
erstmaliger Vorbereitung bleibt der Bewerbungsdatensatz bestehen, während
Textfelder leer bleiben und eine Warnung erscheint; vorhandene Texte werden
nicht ersetzt. Der Seitenaufruf regeneriert leere Texte nicht wiederholt.

Die folgende Beschreibung zu 2.4.32 dokumentiert den historischen Stand;
die dortige Grundentwurf-Regel wurde in 2.4.33 ersetzt.

## Vollständiger Motivationsbrief und Endkundenkontext 2.4.32

Der aktuelle `applicationWritingContext()` übergibt Stellenanforderungen und
ein begrenztes Firmenprofil zusätzlich zur Ausschreibung. Bei
ausdrücklich zugeordnetem Vermittler trennt der Kontext dessen Rolle von
der Jobfirma als Endkunde. Ist die Endkundenfirma nicht selbst Vermittler,
liest `applicationEndClientOfficialContext()` einen auf 5000 Zeichen
begrenzten Auszug ihrer hinterlegten HTTPS-Website. Der Abruf nutzt die
bestehende SSRF-/DNS-/Redirect-Prüfung mit acht Sekunden pro Hop; fremde
Redirect-Domains und Abruffehler liefern keinen Website-Kontext. Diese
Erweiterung schreibt keine Firmendaten und behauptet bei unbekanntem
Endkunden keine Identität.

`applicationLetterStructureIssues()` prüft den KI-Brief nach Einsetzen des
Empfängerblocks auf Anrede, 100 bis 450 Wörter Hauptteil, vollständigen
eigenständigen Schlusssatz, lokalisierte Grussformel und Bewerbername als
letzte Zeile. Ein mangelhafter Rücklauf wird bis zu zweimal mit den konkreten
Prüfgründen neu angefordert. Auch nach Rich-Text-Bereinigung und finalem
Empfängerblock erfolgt eine Schlussprüfung; bleibt sie negativ, wird kein
erfolgreicher KI-Text zurückgegeben. Bestehende Bewerbungstexte werden bei
einem fehlgeschlagenen manuellen KI-Aufruf nicht überschrieben. Die
Grundentwürfe bei einer neuen Bewerbung bleiben ein ausdrücklich nicht als
KI-Erfolg gekennzeichneter Fallback.

## Jüngster Lebenslauf je Sprache bei jedem KI-Aufruf 2.4.31

`applicationCvSourceRows()` lädt bei jeder Erstellung oder Überarbeitung die
aktuellen, nicht gelöschten Profil-Dokumente des eigenen Benutzers vom
Metadatentyp `cv` samt `updated_at`. `applicationLatestCvRowsByLanguage()`
sortiert nach dem Änderungszeitpunkt absteigend, bei Gleichstand nach der
Dokument-ID, und behält je normalisiertem `language_code` genau einen CV.
Erst danach werden die ausgewählten Originaldateien und korrigierten Texte
gelesen und an die KI übergeben. Titel, Dateiname und Versionsnummer
bestimmen die Sprachgruppe nicht. Eine unzutreffend gepflegte Dokumentsprache
führt folglich zur fachlich falschen Auswahl; die Anwendung korrigiert
Metadaten nicht anhand des Dateinamens. Keine Migration, Bestandsänderung
oder automatische Neugenerierung gespeicherter Bewerbungen.

## Automatischer Zugriff auf Stammdaten-Lebensläufe 2.4.30

`applicationCvSourceRows()` lädt mandantenbegrenzt alle aktuellen, nicht
gelöschten Profil-Dokumente vom Typ `cv`; die Beschränkung auf die erste
Version entfällt. `applicationCvInputParts()` löst die Originaldateien nur
innerhalb des Dokumentverzeichnisses des effektiven Benutzers auf und baut
für PDF, DOC, DOCX und TXT `input_file`-Teile, für Bilder `input_image`-Teile.
Korrigierter Text wird zusätzlich vollständig und mit Vorrang übermittelt.
Die ursprüngliche 24.000-Zeichen-Kürzung ist entfernt. Überschreiten alle
Dateien zusammen 50 MiB oder ist eine vorhandene Datei nicht lesbar, wird
der KI-Aufruf abgebrochen statt einen vermeintlich CV-gestützten Entwurf
als erfolgreich auszugeben. Die erste Vorbereitung kann weiterhin einen
als solchen gekennzeichneten lokalen Grundentwurf erzeugen. Keine Migration,
keine automatische Änderung bestehender Bewerbungen und kein Versand.

## Dauerhaft verfügbare Job-Room-Filterwerte 2.4.29

`reportViewFilterDefinitions()` erzeugt für `job_room_result` zunächst die
sechs unabhängigen Optionen `not_recorded`, `recorded`, `result:open`,
`result:hired`, `result:rejected` und `interview`, unabhängig davon, welche
Werte in den aktuell geladenen Reportzeilen vorkommen. Die dynamische
Ergänzung der Werte anderer Auswahlfelder und der bestehende Filtervergleich
bleiben unverändert. Ein nicht vorhandener Zustand ist wählbar und ergibt
null Treffer; die Grundauswahl des gespeicherten Reports wird nicht erweitert.
Keine Migration oder Datensatzänderung.

## Atomare Job-Room-Filterwerte und Übersetzungsfallback 2.4.28

`reportJobRoomFilterOptions()` leitet aus Erfassungszustand, Bewerbungsdatum,
Resultat und Gesprächskennzeichen unabhängige, sprachneutrale Filterwerte ab.
`reportDataset()` liefert diese Werte zusätzlich zum unverändert kombinierten
Anzeigetext und ihren übersetzten Labels. `reportViewFilterDefinitions()` sammelt
die einzelnen Werte; `reportViewApplyFilters()` trifft eine Zeile bei mindestens
einem gewählten Wert im Feld. Ein alter URL-Wert mit komplettem Anzeigetext wird
weiterhin exakt verglichen. Die übrigen Auswahlfelder bleiben unverändert.

Wenn `dbUiText()` eine neue Beschriftung noch nicht enthält, verwendet `tr()`
den generierten fünfsprachigen Hilfekatalog vor dem letzten Rückfall auf den
Schlüssel. Ein bereits freigegebener Datenbanktext hat weiterhin Vorrang.
Keine Datenbankmigration oder Bestandsdatenänderung.

## Mehrfachauswahl in Reportfiltern 2.4.27

`reportViewFiltersHtml()` rendert für alle Auswahlfelder anstelle eines
Einfachauswahlmenüs eine kompakte, scrollbar begrenzte Ankreuzliste. Der
GET-Parameter `report_filter[Feld][values][]` enthält die gewählten Werte;
keine Auswahl bedeutet alle Werte. `reportViewFilterState()` normalisiert und
dedupliziert höchstens 100 skalare Werte pro Feld, nimmt bisherige
`[value]`-Links weiter an und beschränkt sie auf je 500 Zeichen.
`reportViewApplyFilters()` verknüpft Werte desselben Feldes mit ODER;
verschiedene Felder bleiben mit UND kombiniert. Leere Werte werden über
`__empty__` ausgewählt. Die normalisierte Auswahl bleibt beim Wechsel
zwischen Tabelle und Karten in der URL erhalten. Kein Datenbankeffekt.

## Kombinierter Job-Room-Status in Reports 2.4.26

`reportDataset()` lädt `job_room_interview` bereits zusammen mit `job_room_result`,
`job_room_registration` und `applied_at`. Die Formatierung des Reportfelds
`job_room_result` übergibt das Gesprächskennzeichen an `jobRoomApplicationStatus()`.
Bei bestätigter Erfassung und vorhandenem Bewerbungsdatum wird
«Vorstellungsgespräch» zusätzlich zum Resultat ausgegeben, etwa
«Im Job-Room erfasst – Noch offen · Vorstellungsgespräch». Der lokale
Bewerbungsstatus wird weiterhin als eigenes Feld formatiert. Da die Reportfilter
den sichtbaren Wert verwenden, bleiben Kombinationen mit und ohne Gespräch
getrennt auswählbar. Keine Datenbankmigration oder Bestandsdatenänderung.

## Job-Room-Helper und Absagegrund 2.4.25

`jobRoomHelperRows()` filtert auf datierte, nicht gelöschte Bewerbungen mit
`job_room_registration <> 'recorded'`; die Monatsauswahl verwendet denselben
Erfassungsfilter. `jobRoomStreetParts()` trennt eine erkennbare Hausnummer vom
Strassenfeld. Nicht eindeutig trennbare Adressen bleiben unverändert im Strassenfeld,
statt eine Nummer zu erfinden. Der Helper zeigt Strasse, Hausnummer, Postleitzahl und
Ort separat und in dieser Reihenfolge. Für abgesagte Bewerbungen erscheint zusätzlich
der kopierbare Absagegrund.

`applications.rejection_reason` ist `VARCHAR(249) NULL`; bei Status `rejected`
erzwingen die aktiven Status-Schreibpfade einen nichtleeren Wert mit höchstens 249
Zeichen. Bei anderem Status wird das Feld im Bewerbungsformular ausgeblendet. Die
manuelle Statusänderung, das Kontakt-/E-Mail-Protokoll und der Admin-KI-Upsert sind
abgesichert. Bestehende Absagen ohne Grund werden nicht automatisch mit einem
erfundenen Grund nachgefüllt.

## Bewerbungsrelevante Dokumente 2.4.24

`user_documents.is_application_relevant` ist ein nicht-nullbares Boolean-Feld mit Vorgabe 0.
Das Profildokumentformular zeigt es als Ankreuzfeld. Upload und Metadatenänderung speichern den
Wert mandantenbegrenzt; der Versionswähler übernimmt ihn zusammen mit den übrigen Metadaten in
eine neue Dokumentversion.

Die Dokumentauswahl einer Bewerbung zeigt nur eigene, aktuelle, nicht gelöschte Profildokumente
mit aktivem Kennzeichen. Das Kennzeichen ist eine Vorschlagsregel und keine Zuordnung: Bereits in
`application_documents` verknüpfte Unterlagen bleiben in der Bewerbung sichtbar, bis sie dort
ausdrücklich entfernt werden. Reports und der freigegebene Admin-KI-Feldvertrag kennen das neue
Feld ebenfalls.

## Kalender-PDF 2.4.23

`export_pdf&type=calendar` validiert `view`, übernimmt das mitgegebene Ankerdatum und berechnet den
Zeitraum über dieselbe `calendarRange()`-Funktion wie die Bildschirmansicht. `calendarEventRows()`
liefert damit denselben Terminbestand. Für die Agenda verwendet der Export zusätzlich
`calendar_agenda` aus dem bestehenden Sortier-/Filterzustand. `calendarPdfRows()` bildet die
Resultate auf Zeit, Ereignis, Typ, Status und Bezug ab; die vorhandene PDF-Ausgabe bereinigt
Steuerzeichen und erzeugt keine aktiven HTML-Inhalte.

Die Schaltfläche steht in der oberen Kalendernavigation unmittelbar rechts neben `ICS`. Ihr Link
enthält die aktuelle Ansicht und das aktuelle Datum. Die bestehenden Kalender- und
ICS-Funktionen bleiben unverändert.

## Schneller Laufzeitstart 2.4.22

Schemaabgleich und statische Übersetzungsdaten werden einmal pro Release unter dem Marker
`runtime_schema_2_4_22` ausgeführt. `GET_LOCK()` serialisiert den einmaligen Lauf; erst ein
vollständiger Lauf setzt den Marker. Normale Requests prüfen danach nur noch diesen Primärschlüssel,
statt 18 Tabellen-, 48 Spalten- und weitere Index-/Seed-Prüfungen auszuführen.

Hilfe-, Sicherheits- und KI-Speichermigrationen besitzen mit `runtime_maintenance_2_4_22` einen
zweiten gemeinsamen Marker und laufen ebenfalls nur einmal pro Release. Seiten für Stellenportale
schreiben die statischen Portal-Seeds nicht mehr bei jedem Öffnen. Die beiden ungültigen
Zeilenumbruch-Regulärausdrücke wurden korrigiert; das Speichern manueller Suchkriterien lädt sein
Profil vor der Verwendung und kann dadurch keinen `TypeError` mehr auslösen.

Eine pauschale Tabellenreorganisation gehört nicht zum Requestpfad. Bei der derzeit etwa 10,6 MiB
grossen Produktionsdatenbank wird sie nur nach separater Fragmentierungs- und `EXPLAIN`-Prüfung
mit Datenbanksicherung vorgenommen.

## Schutz vor selbstschädigenden Bewerbungstexten 2.4.21

Der aktuelle Schreibkontext übermittelt keine Formulierungen wie «kein lesbarer
Lebenslauf». Der Systemauftrag verbietet Hinweise auf fehlende oder unlesbare Quellen ebenso wie
das Verschieben inhaltlicher Aussagen auf ein Interview. Nach jeder strukturierten KI-Antwort
prüft `applicationTextHasDisqualifyingLanguage()` Begleit-E-Mail und Motivationsschreiben anhand
sprachübergreifender Muster. Beim ersten Treffer wird die Generierung mit verschärfter Anweisung
wiederholt. Ein verbleibender Treffer wird satzweise entfernt; positive Sätze bleiben erhalten.
Inhaltsleere Resttexte, etwa ein reiner Adressblock, werden durch ebenfalls floskelfreie lokale
Entwürfe ersetzt. Beim Öffnen einer
Bewerbung durchläuft auch vorhandener Inhalt dieselbe Sperre und wird bei einer Änderung gespeichert.

## Linktreuer Schnellimport aus der Zwischenablage 2.4.20

Das Schnellimport-Textarea trägt `data-import-payload`. Der zugehörige Paste-Handler liest den
HTML-, URI-Listen- und Klartextkanal der Zwischenablage. `clipboardImportText()` ersetzt sichere verlinkte
Beschriftungen durch Beschriftung plus vollständiges `href`, entfernt unsichere Linkprotokolle,
dedupliziert gleiche Ziele und normalisiert Blockgrenzen zu Zeilenumbrüchen. Der resultierende
Klartext wird mit `setRangeText()` an der aktuellen Auswahl eingefügt und löst ein normales
`input`-Ereignis aus. Dadurch bleiben URL, vorhandener Inhalt und der bestehende serverseitige
Import-/Drill-down-Pfad erhalten, ohne HTML im Datenfeld zu speichern.

## Absatzkonvertierung im Mini-Editor 2.4.19

`convertSelectedBlocks(false)` erweitert die aktuelle Auswahl auf die davon berührten obersten
Editorblöcke, zerlegt vorhandene direkte `<br>`-Grenzen und setzt jeden Teil als echtes `<p>` ein.
`convertSelectedBlocks(true)` führt die umgekehrte Operation aus und verbindet die ausgewählten
Absätze durch `<br>`. Verschachtelte Inline-Elemente werden geklont und dadurch erhalten; Listen
werden zeilenweise übernommen, während Tabellen, Bilder und Trennlinien bei der Absatzoperation
eigenständige Blöcke bleiben. Nach dem DOM-Austausch setzt `selectNodes()` die Auswahl neu und der
bestehende `sync()`-Pfad bereinigt und speichert den HTML-Stand im zugehörigen Textarea-Feld.

## Unbedingte Bewerbungsvorbereitung 2.4.18

Die erneute Inseratanalyse vor `start_application` bleibt aktiv, ist aber kein Abbruchkriterium mehr.
Der Handler übergibt `target_job_id`, sodass `importStoreDraft()` die Analyse immer auf den vom
Benutzer gewählten eigenen Job anwendet. Eine abweichende, verifizierte Arbeitgeberfirma ändert
ausschliesslich `jobs.company_id`; der bisherige Firmendatensatz wird weder überschrieben noch
gelöscht. Auditdaten halten alte und neue Firmen-ID sowie die Neuzuordnung fest.

Scheitert der Abruf, die strukturierte KI-Analyse, die Webrecherche oder eine andere optionale
Anreicherung, wird der konkrete Fehler nur serverseitig referenzierbar protokolliert. Die Anwendung
legt die Bewerbung trotzdem atomar an beziehungsweise öffnet die bestehende und erstellt danach mit
den verfügbaren Angaben KI-Texte oder lokale bearbeitbare Grundentwürfe. Auch eine fehlgeschlagene
Empfängerrecherche blockiert `initializeApplicationTexts()` nicht. Nach einer Firmenkorrektur werden
alte Kontakte ausserhalb der aktuellen Arbeitgeber- und Vermittlerfirma aus der Empfängerwahl
ausgeschlossen.

## Feldbezogene Reportlinks 2.4.17

`reportFieldRecordUrl()` löst jedes angezeigte Feld auf seinen fachlich zugehörigen Datensatz auf.
In Bewerbungsreports verlinken beispielsweise Firma und Vermittler auf unterschiedliche
Firmendatensätze, Jobtitel auf die Stelle und Kontaktfelder auf die Kontaktperson; Status-, Kanal-
und Datumsfelder bleiben mit der Bewerbung verbunden. Entsprechende Beziehungen gelten auch für
Kontakt-, Dokument-, Kalender- und Stellenreports.

`reportDataset()` speichert die Linkziele positionsgleich als `cell_urls`. `reportFieldValueHtml()`
escaped den Wert weiterhin vollständig und umschliesst nur nicht leere Werte mit einem geprüften
internen Link. Tabelle, Liste, Karten, Vorschau und Kalendergruppen verwenden denselben Renderer.
Die frühere Aktionsspalte und der separate «Datensatz öffnen»-Button entfallen.

## KI-Webrecherche für fehlende Empfängerdaten 2.4.16

Die ursprüngliche strukturierte Inseratanalyse bleibt der erste Schritt. Sind danach
`address_line1`, `postal_code`, `city` oder ein Recruiting-Kontakt leer, ruft
`jobWebResearchResponse()` die Responses API mit dem Werkzeug `web_search` auf. Das Schema trennt
Firmen- und Kontaktfelder, verlangt pro Wert eine exakte Textstelle und die dazugehörige öffentliche
HTTPS-Quelle und beschränkt Kontakte auf belegte Recruiting-/HR-Personen beziehungsweise die
ausdrücklich im Inserat genannte Kontaktperson.

Die Modellantwort allein ist kein Speicherbeleg. Die App lädt bis zu zwölf zitierte Seiten mit der
bestehenden SSRF-geschützten Abruffunktion und übernimmt nur Fakten, deren Kurzbeleg im abgerufenen
Seitentext vorkommt. `applyJobWebResearch()` verwirft nicht erlaubte Felder, unsichere Quellen und
ungültige Werte. Die anschliessenden Upserts verändern ausschliesslich leere Felder.
`applicationEnsureRecipientData()` aktiviert denselben Ablauf vor der Texterstellung bestehender
Bewerbungen. Der endgültige Empfängerblock verwendet vorhandene Kontakte und gibt niemals technische
Ergänzungsplatzhalter aus.

## KI-Neuanalyse und effektiver Bewerbungskontakt 2.4.15

Vor `start_application` liest `verifiedJobImport()` die Originalausschreibung und zugehörige
Firmenquellen erneut und verlangt eine vollständig abgeschlossene strukturierte KI-Analyse.
`importStoreDraft()` ergänzt daraus Firma, Kontakte und Job ausschliesslich in bisher leeren Feldern.
Ohne gültiges KI-Ergebnis wird keine Bewerbung angelegt oder verändert; die Fehlermeldung nennt die
konkrete Ursache und eine Protokollreferenz.

`applicationRecipientForApplication()` bestimmt den vorhandenen Empfänger zentral. Vorrang haben
Primärkontakt, direkter Bewerbungsbezug, Jobbezug, HR-/Recruiting-Kontakt und erst danach ein anderer
Kontakt der Arbeitgeber- oder Vermittlerfirma. Derselbe Empfänger wird im KI-Kontext, im lokalen
Ausfallentwurf und in der abschliessenden Serverprüfung verwendet. Auch ein bereits vollständig
gefülltes Motivationsschreiben wird beim Öffnen geprüft; ein alter unvollständiger Adressblock wird
ersetzt statt davor dupliziert.

## Eindeutige Reportfilter und Empfängerblock 2.4.14

`reportDataset()` erzeugt zunächst die formatierte Ergebniszeile. Für Auswahlfelder übernimmt es
anschliessend genau diesen sichtbaren Fachwert als Filterwert; Datums- und Zahlenfelder behalten
ihren maschinenlesbaren Rohwert. Damit können fachlich verschiedene Anzeigen wie «Noch nicht im
Job-Room erfasst» und «Im Job-Room erfasst – Noch offen» nicht mehr über einen gemeinsamen internen
Code zusammenfallen. Die gemeinsame Filterengine bleibt für alle sechs Report-Datenbasen identisch.

`applicationRecipientBlockForApplication()` liest Arbeitgeber beziehungsweise die Firma des
zugeordneten Primärkontakts mandantengeprüft. `applicationCoverLetterWithRecipientBlock()` verlangt
den Empfängerblock am Anfang des Motivationsschreibens, ergänzt ihn nach jeder KI-Antwort und
verhindert eine Dublette, wenn der korrekte Block bereits am Anfang steht. Dieselbe Absicherung gilt
für lokale Ausfallentwürfe. Ohne bekannte Kontaktperson wird kein Name oder Platzhalter erfunden.

## Umschaltbare und verlinkte Reports 2.4.13

Jeder geöffnete Report bietet unabhängig von der gespeicherten Voreinstellung die Schalter
«Tabelle» und «Karten». Die Auswahl wirkt nur auf die aktuelle Anzeige und verändert den
gespeicherten Report nicht. Tabelle, Karten, Liste, Vorschau sowie Kalendergruppen erhalten für
jeden Treffer einen sicheren internen Link zum Editor des ursprünglichen Jobs, der Bewerbung, Firma,
Kontaktperson, des Dokuments oder Kalendereintrags. `reportRecordUrl()` erzeugt diese Links aus der
serverseitig gelesenen ID. Zusätzlich ersetzt `tr()` sowohl aktuelle `{result}`- als auch ältere
`:result`-Platzhalter, sodass keine technische Schablone in den Job-Room-Status gelangt.
Die visuelle Reihenfolge innerhalb des Reportbereichs setzt «Gespeicherte Reports» vor den Editor,
damit vorhandene Auswertungen ohne vorgängiges Scrollen erreichbar sind.

Jeder geöffnete Report erzeugt seine Filter ausschließlich aus den tatsächlich gewählten Spalten.
Datumsfelder erhalten Von/Bis, numerische Felder Minimum/Maximum, Status- und andere Auswahlfelder
eine Liste der vorhandenen Werte und Textfelder eine Enthält-Suche. `reportViewFilterState()` verwirft
unbekannte Felder und ungültige Werte. `reportViewApplyFilters()` verknüpft alle aktiven Kriterien mit
UND und filtert Ergebnisdaten und deren Metadaten gemeinsam. Dadurch bleiben die Links zum
Originaldatensatz auch nach der Filterung korrekt zugeordnet. `reportViewUrl()` trägt aktive Filter
beim Wechsel zwischen Tabelle und Karten weiter.

## Beziehungsfilter der Firmenspalte Links 2.4.12

Die Spalte «Links» besitzt sechs fachliche Filteroptionen: Jobs, Bewerbungen und Kontakte jeweils
«mit Einträgen» oder «ohne Einträge». Mehrere ausgewählte Kriterien werden mit UND verknüpft und
prüfen dieselben mandantenbegrenzten, nicht gelöschten Beziehungen, deren Zahlen in der Tabelle
erscheinen. `sfApplySql()` akzeptiert dafür ausschließlich fest im Feldkatalog definierte
`choice_clauses`; Benutzereingaben werden nicht als SQL übernommen. Die bisherige freie Textsuche
auf einem fachfremden Aktualisierungsdatum entfällt.
Das Deployment ersetzte `index.php` und `assets/app.css`; beide produktiven Dateien sind bytegleich
mit dem geprüften Quellstand. Die öffentliche Seite liefert HTTP 200 und Version 2.4.12.

## Wirksame Report-Anzeigearten 2.4.11

`display_type` wird beim Speichern gegen die gewählte Datenbasis validiert und beim Öffnen an
`reportRowsHtml()` übergeben. Tabelle, Liste, Karten und Vorschau besitzen eigenständige Renderer.
Kalenderreports können zusätzlich nach Tag, Kalenderwoche oder Monat gruppiert werden; diese drei
Anzeigearten werden bei anderen Datenbasen nicht angeboten. Beim Wechsel der Datenbasis aktualisiert
der Browser neben Feldern, Sortierung und Status jetzt auch die gültigen Anzeigearten. Alle sichtbaren
Werte bleiben HTML-escaped und mehrzeilig lesbar.

## Eindeutiger Job-Room-Status 2.4.10

Reports beschriften `job_room_result` fachlich als «Job-Room Status» und berechnen den sichtbaren
Wert mit `jobRoomApplicationStatus()`. Ohne bestätigte Erfassung oder ohne Bewerbungsdatum erscheint
«Noch nicht im Job-Room erfasst». Andernfalls zeigt der Report vollständig «Im Job-Room erfasst –
Noch offen/Anstellung/Absage». Die getrennt auswählbaren Erfassungs- und Gesprächsfelder besitzen
ebenfalls fachliche, lokalisierte Bezeichnungen statt technischer DB-Feldnamen.
Das Deployment ersetzte ausschließlich `index.php`; lokale und produktive Datei wurden bytegleich
bestätigt. Die öffentliche Seite liefert HTTP 200 und Version 2.4.10.

## Vollständige Reportbeziehungen 2.4.9

`reportNormalizeRelations()` schließt leere Ausgaben, wenn dieselbe Beziehung über einen zweiten,
eindeutigen Datenpfad belegt ist. Bei Bewerbungen wird die Stellenfirma als Vermittler ausgegeben,
wenn sie `is_intermediary=1` trägt und keine separate Vermittlerfirma hinterlegt ist. Ein zugewiesener
Kontakt ohne Vor- und Nachnamen wird über seine E-Mail sichtbar. Dokumente übernehmen die Job-ID aus
ihrer Bewerbung. Kalendereinträge übernehmen Firma und ersatzweise den Kontaktnamen aus dem verknüpften
Kontakt. Kontakte mit Bewerbungsbezug übernehmen den Job aus dieser Bewerbung. Die Normalisierung
ordnet keine lediglich vorhandenen, aber unverbundenen Firmen oder Kontakte zu.
Das Deployment ersetzte ausschließlich `index.php`; lokale und produktive Datei wurden bytegleich
bestätigt. Die öffentliche Seite liefert HTTP 200 und Version 2.4.9.

## Job-Room-Resultat und Spaltenreihenfolge 2.4.8

`jobRoomApplicationResult()` prüft vor der Resultatausgabe den Wert von `job_room_registration`.
Nur bei `recorded` wird `job_room_result` als «Noch offen», «Anstellung» oder «Absage» dargestellt.
Bei `unknown`, `not_recorded` oder einem fehlenden Wert lautet die Ausgabe «Noch nicht im Job-Room
erfasst». Die Regel gilt gezielt für das Job-Room-Resultat in Reports und in der Job-Room-Hilfe;
andere Felder werden nicht umgedeutet.

`reportEditorFieldOptions()` stellt beim Bearbeiten zuerst die bereits ausgewählten Felder in ihrer
gespeicherten Reihenfolge bereit. Der Griff jedes Feldes erlaubt eine Neuordnung per Drag-and-drop.
Da erfolgreiche HTML-Formulare gleichnamige Checkboxen in DOM-Reihenfolge übertragen, speichert
`saveReportSettings()` diese Reihenfolge unverändert als `sort_order`.
Die Redirects nach `save_report` und `update_report` enthalten sowohl `edit_report` als auch
`view_report` mit derselben ID. Damit entsteht die Ansicht nach jeder Änderung neu aus den gerade
gespeicherten Einstellungen.
Das Deployment ersetzte ausschließlich `index.php` und `assets/app.css`; beide Dateien wurden
bytegleich verifiziert. Die öffentliche Seite und das Stylesheet liefern HTTP 200, die Seite weist
Version 2.4.8 aus.

## Gespeicherte Reportansicht 2.4.7

`reportOpenUrl()` verweist nicht mehr auf eine allgemeine Modulansicht, sondern auf den ausgewählten
Report innerhalb der Auswertungsseite. Der Report wird anhand von `id` und `owner_user_id` geladen.
`loadReportSettings()` liefert die gespeicherten Felder, Filter und Sortierung; `reportDataset()` erzeugt
daraus Kopfzeile und Daten in derselben Feldreihenfolge. Die Ausgabe escaped jeden Wert und setzt nur
Zeilenumbrüche um. Bearbeitung und PDF-Export bleiben unmittelbar bei der Reportansicht erreichbar.
Das produktive Deployment ersetzte ausschließlich `index.php`; lokale und produktive Datei wurden
bytegleich bestätigt. Die öffentliche Seite liefert HTTP 200 und Version 2.4.7.

## Vollständige Reportfelder 2.4.6

`reportFieldOptions()` führt für jede der sechs Datenbasen alle fachlich auswertbaren Felder, IDs und
lesbaren Relationen. Die zugehörigen Abfragen verwenden weiterhin feste SQL-Listen und gebundene
Mandantenparameter; vom Browser gelieferte Feldnamen werden nie in SQL eingesetzt. Interne Eigentümer-,
Lösch-, Speicherpfad- und Eindeutigkeitsfelder bleiben ausgeschlossen.

`limitReportColumns()` dedupliziert und validiert die Auswahl, `reportSelectedColumns()` wendet die
Obergrenze zwölf beim Speichern, Laden und Export an. Der Reporteditor hält für jede Datenbasis den
vollständigen Feld- und Statuskatalog bereit und aktualisiert Auswahl, Sortierung, Statusfilter und
Zähler ohne Seitenneuladung. Rich-Text-Inhalte werden im Export als lesbarer Text ausgegeben; Datums-,
Enum-, Sprach-, Länder-, Boolean- und Dateigrößenwerte werden verständlich formatiert.

## Eindeutiger TOTP-Zustandswechsel 2.4.5

`twoFactorChallengeState()` unterscheidet drei Zustände: eine bereits authentifizierte Sitzung, eine
offene Challenge und eine fehlende Challenge. Der POST-Handler prüft diesen Zustand vor Rate-Limit,
Datenbankzugriff und Codeprüfung. Ein altes TOTP-Formular kann deshalb keine Fehlermeldung mehr in einer
bereits angemeldeten Sitzung erzeugen. Es entfernt nur überholte Challenge-Daten und führt zum Dashboard.

Eine Anfrage ohne authentifizierte Sitzung und ohne offene Challenge führt kommentarlos zur Anmeldung;
sie wird nicht fälschlich als ungültiger Sicherheitscode gewertet. Ausschließlich eine offene Challenge
darf die TOTP-Rate-Begrenzung und den Authenticator prüfen. Der normale Login ohne aktiviertes TOTP entfernt
vorsorglich alte Challenge-Werte, bevor die authentifizierte Sitzung gesetzt wird.

## Vollständiger Google-Kalenderabgleich 2.4.4

Der bisherige Schnellpfad betrachtete eine gespeicherte Prüfsumme als ausreichend und fragte den
zugehörigen Google-Termin nicht nochmals ab. Nach einer externen Löschung oder dem Wechsel des
Zielkalenders blieb die lokale Verknüpfung deshalb scheinbar aktuell, obwohl der Termin im gewählten
Kalender fehlte.

Der Vollabgleich prüft nun jeden unveränderten Link zuerst gegen Google und akzeptiert ihn nur mit
passendem privaten JeMa-Eigentumsmarker. Fehlende Links werden im aktuellen Kalender unter der
kalenderabhängigen stabilen ID erstellt. Bereits von Google gelöschte und deshalb gesperrte IDs
erhalten reproduzierbare Ersatzkandidaten; ein abgebrochener Versuch kann dadurch ohne Dublette
wiederholt werden. Gefundene fremde Termine werden nicht verändert.

Vor dem Vergleich projiziert die App die Statusverläufe aller aktiven Bewerbungen erneut. Das
Ergebnis enthält die Anzahl erwarteter und bestätigter Exporte. Der automatische Abgleich gilt nur
dann als vollständig, wenn beide Zahlen übereinstimmen und kein Einzelfehler vorliegt. Bis zu acht
konkrete Fehler mit Quelle und Datensatz-ID bleiben im Profil erhalten. Jede angemeldete Sitzung
startet diese Vollständigkeitsprüfung einmal; bei einem unvollständigen Lauf wird sie nicht als
erledigt markiert und bei der nächsten angemeldeten Anfrage wiederholt.

## Zuverlässiger externer Kalenderabgleich 2.4.3

`syncGoogleCalendarEventsLocked()` hängt nicht mehr vom Marker `workflow_calendar_v6` ab. Die
Sicherheit bleibt durch `calendarEventRows()` und `calendarExportRows()` erhalten: Vorbereitungs- und
Legacy-Projektionen werden nicht exportiert, fremde Google-Einträge bleiben geschützt und veraltete
JeMa-Einträge werden nur nach einem fehlerfreien Exportlauf stillgelegt.

Speichern und Löschen von Kalenderdaten sowie Änderungen an verknüpften Bewerbungen, Jobs, Firmen und
primären Kontakten starten den Vollabgleich. Auch Löschkaskaden werden dadurch extern nachgeführt.
Automatische und manuelle Fehler schreiben `user_google_calendar_settings.last_error`; ein erfolgreicher
Lauf setzt den Fehler zurück und aktualisiert `last_sync_at`. Der private ICS-Feed setzt explizite
No-Cache-Header. Das Abrufintervall eines abonnierten Feeds bleibt Sache des externen Kalenderanbieters.

## Lesbare Firmenlinks und datierter Job-Room 2.4.2

Die drei Verknüpfungen einer Firma werden als getrennte Blocklinks gerendert. `white-space: nowrap`
hält Zähler und Bezeichnung zusammen; das Tabellenlayout kann die Spalte dadurch anhand des
vollständigen Inhalts bemessen, während die Tabellenzelle selbst eine echte Zelle bleibt.

`jobRoomHelperRows()` verlangt jetzt `applications.applied_at IS NOT NULL`. Monatsauswahl,
Monatsgrenze und Sortierung verwenden ausschließlich diesen fachlichen Zeitpunkt. Der frühere
Fallback auf `updated_at` oder `created_at` konnte noch nicht versandte Bewerbungen als leere
Job-Room-Karten anzeigen und ist entfernt. Es wird kein Datum erfunden und kein Ersatzhinweis in
der Liste ausgegeben. Datenbankstruktur und vorhandene Datensätze bleiben unverändert.

## Eindeutige Firmen-Bewerbungszahl 2.4.1

Die korrelierte Firmenabfrage zählt nur `applications.deleted_at IS NULL` über einen ebenfalls aktiven
Job mit `jobs.company_id = companies.id`. `applications.intermediary_company_id` ist ausdrücklich
keine Bewerbung bei der Vermittlerfirma. Die Firmenfilterung der Bewerbungsseite verwendet exakt
dieselbe direkte Arbeitgeberbeziehung und blendet Bewerbungen zu gelöschten Jobs oder Firmen aus.

## Mehrstufiger Admin-KI-Agent 2.4.0

`adminAiRunTask()` kapselt den vollständigen Admin-Auftrag in höchstens vier Runden. Jede Runde erhält
den ursprünglichen Auftrag, den dauerhaften Gesprächskontext, den frisch aus der Datenbank gelesenen
Benutzerbestand und das konkrete Ergebnis beziehungsweise den Fehler der vorherigen Runde. Schreibende
Aufträge benötigen mindestens eine ausgeführte Schreiboperation und danach eine weitere Verifikationsrunde.

`adminAiPlatformContext()` stellt benutzerisoliert Firmen, Kontakte, Jobs, Bewerbungen und Dokumentmetadaten
bereit. Bei Profil-/CV-Bezug kommen Benutzerstamm, aktive Präferenzen, Sprachkenntnisse und der Text des
aktuellen Lebenslaufs hinzu. `record_lookup`-Abgleichwerte werden, wenn möglich, aus erlaubten Feldern
abgeleitet; fehlerhafte Hilfsabfragen werden als Rückmeldung an die nächste Agentenrunde gegeben.

Der Schnellimport verwendet für JavaScript-Clients einen dreiteiligen JSON-Lebenszyklus:
`prepare_quick_import` ermittelt und tokenisiert die Quellen, `process_quick_import` verarbeitet genau
eine Anzeige und liefert Zähler sowie Verlaufseintrag, `cancel_quick_import` verwirft den noch offenen
Stapel. Der bisherige direkte `preview_import`-POST bleibt als Fallback ohne JavaScript erhalten.

## Admin-KI-Datenvertrag 2.3.7

`adminAiPlatformContext()` liefert der KI nicht mehr nur Bestandszahlen, sondern auch die exakten
beschreibbaren, erforderlichen und zum Abgleich geeigneten Felder jeder freigegebenen Tabelle.
`adminAiNormalizeOperation()` bildet Tabellen- und Feldaliasse auf dieses Schema ab, bevor dynamisches
SQL entsteht. Nicht zuordenbare Zusatzangaben werden in einem vorhandenen Notizfeld erhalten und
gesondert markiert; der Spaltenname selbst gelangt niemals in SQL.

Ein generisches Firmen-`table_upsert` wird serverseitig in `company_upsert` umgewandelt. Damit werden
Name, Anschrift, Web-/Kontaktdaten und weitere Firmenfelder regulär geschrieben, während UID,
Handelsregister und Aliasse als recherchierte Identitätsinformationen in den Notizen landen. Die
Firmen-Dublettenprüfung verwendet Name oder UID. Statusabfragen können Firmen zusätzlich anhand UID
oder Handelsregister finden. Fremdschlüssel für Firmen, Vermittler, Kunden, Quellen, Jobs, Kontakte,
Bewerbungen und Dokumenttypen werden zentral aufgelöst. Pflichtfelder werden erst vor einer echten
Neuanlage geprüft, sodass eine gezielte Ergänzung vorhandener Zeilen möglich bleibt.

## Admin-KI-Konsole 2.3.6

Der Browser hat `form.action` als Request-Ziel verwendet. In der realen Maske konnte das enthaltene
Bedienelement `name="action"` diese DOM-Eigenschaft überlagern; der Fetch lief dadurch auf eine
nicht vorhandene Adresse und erhielt HTTP 404. Die Zieladresse wird jetzt ausschließlich aus dem
HTML-Attribut und andernfalls aus `window.location.href` gebildet. Der Chromium-Test nutzt exakt die
Produktionsstruktur und verweigert alle anderen Zielpfade. Nicht strukturierte HTTP-Antworten nennen
zusätzlich den tatsächlich erreichten Endpunkt und bestätigen den Erhalt der Eingabe.

## Admin-KI-Konsole 2.3.5

Der Browser verarbeitet auch HTTP-422-Antworten als strukturiertes JSON und zeigt die konkrete
Fehlerursache im Ausgabefeld. Er lädt die Seite bei einem Admin-KI-Fehler nicht mehr neu. Die
laufende Eingabe bleibt serverseitig und zusätzlich als Browser-Entwurf erhalten.

`admin_ai_memory` speichert pro Benutzer die Kontexte, das vollständige chronologische
Ausgabeprotokoll, die letzte Anweisung und das Modell. Erfolg wie Fehlschlag wird
gespeichert; nur «Gedächtnis löschen» entfernt diesen Zustand. Die Tabelle wird idempotent beim
Start angelegt und ist zusätzlich als SQL-Migration dokumentiert. Der einleitende Erklärungstext
ist entfernt; Ausgabe und Eingabe nutzen dadurch den verfügbaren Bildschirm direkt.

## Admin-KI-Konsole 2.3.4 (historisch)

Unter Konto steht für Admins im eigenen Konto eine geschützte KI-Konsole bereit. Sie ist auf
JeMa-Jobs-Daten, Workflows sowie belegte öffentliche Adress- und Kontaktrecherchen begrenzt.
Eine ausdrücklich beauftragte Einzel- oder Mehrfachoperation wird als strukturierter Plan erzeugt,
gegen eine Allowlist der Nutzer-Datentabellen und Spalten geprüft und in einer Datenbanktransaktion
ausgeführt. Bestehende Datensätze werden anhand stabiler Felder ergänzt; gelöschte Datensätze
werden nicht reaktiviert. Sicherheits-, Geheimnis- und Audit-Tabellen, Löschungen, Identitätswechsel
und das Versenden externer E-Mails bleiben gesperrt. Ausgabe und Eingabe nutzen je ein breit
angelegtes, mehrzeiliges und vertikal scrollbares Feld; die damalige Sitzung merkt sich bis zum Befehl
`Gedächtnis löschen` die letzten Aufgaben und Ausführungsergebnisse.

## Security Hardening 2.3.0

Nur Jobs mit Status `rejected` und die ihnen zugeordneten Bewerbungen verwenden in Tabellen und
Karten eine hellere, weiterhin kontrastreiche Textfarbe; alle übrigen Einträge sowie Überschriften
und Interaktionsfarben bleiben im Standardkontrast.

Der Passwort-Reset besitzt keinen Browser-Fallback mehr. Tokens entstehen nur bei verfügbarem
Betreiber-Mailkanal; unabhängig von Adresse und Versandresultat sieht der Anfordernde dieselbe
Antwort. Eine einmalige Datenmigration entwertet ältere offene Reset-Tokens und verschlüsselt
bestehende TOTP-Secrets mit AES-256-GCM. Ein verpflichtender separater `app_key` verhindert den
früheren Rückfall auf Datenbankpasswort oder Prototyp-Schlüssel.

`auth_rate_limits` begrenzt Login, TOTP, Passwort-Reset und Registrierung gleichzeitig nach
Identität und Quell-IP. `users.session_version` entwertet Sitzungen nach Passwort- oder
Zwei-Faktor-Änderungen. Sessions verwenden Strict Mode, reine Cookies sowie 30 Minuten
Inaktivitäts- und zwölf Stunden Maximallaufzeit.

Mailverbindungen lösen ausschließlich öffentliche IP-Adressen auf, verbinden auf das geprüfte
Ziel und erlauben SMTP 465/587 sowie IMAP 143/993 nur mit TLS. Dokumentuploads prüfen Dateigröße,
Endung, tatsächlichen Uploadstatus und MIME-Typ. Dateiausgaben verwenden normalisierte Namen und
werden als Download mit `nosniff` ausgeliefert. CSP, HSTS, Frame-Sperre, Referrer- und
Permissions-Policy sind zentral gesetzt; Laufzeit-Assets werden lokal ausgeliefert.

## Verbindlicher Formulartransport für HTML-Inhalte 2.2.4

HTML- und WYSIWYG-Inhalte besitzen getrennte, ausdrückliche Commit-Funktionen. Beim Wechsel aus
der HTML-Ansicht wird der bereinigte Quelltext unmittelbar in den visuellen Editor geschrieben.
Zusätzlich synchronisiert jedes `formdata`-Ereignis den aktiven Modus und setzt den tatsächlichen
Formularwert neu. Das gilt sowohl für native Formularübermittlungen als auch für programmatisch
erzeugte `FormData`-Objekte. Der Bewerbungs-Autosave synchronisiert Rich-Text-Felder außerdem
explizit vor der Payload-Erzeugung. Damit kann kein älterer WYSIWYG-Stand eine offene
HTML-Änderung überschreiben.

## Verlässlicher Wechsel zwischen HTML und WYSIWYG 2.2.3

Der Editor entscheidet beim Synchronisieren anhand des aktuell sichtbaren Bearbeitungsmodus über
die verbindliche Quelle. In der HTML-Ansicht ist der eingegebene Quelltext maßgebend: Er wird erst
beim Zurückschalten, Speichern oder KI-Aufruf einmal bereinigt, in den WYSIWYG-Bereich übernommen
und als Feldwert weitergegeben. Unvollständiges HTML wird während des Tippens nicht fortlaufend
normalisiert. Dadurch können Zwischenstände weder verlorengehen noch vom vorherigen visuellen
Editorinhalt überschrieben werden.

## Zuverlässige KI-Instruktionsübergabe 2.2.2

Vor der KI-Aktion synchronisiert der Browser die tatsächlich sichtbaren Inhalte der Rich-Text-Editoren
explizit in die zugehörigen Formularfelder. Gleichzeitig werden ausstehende Autosaves angehalten.
Die Überarbeitung wird anschließend als normale Formularnavigation übermittelt; damit gehen weder
Instruktion noch aktuelle Begleit-E-Mail oder aktuelles Motivationsschreiben durch einen vorzeitig
erzeugten Hintergrund-Request verloren. Das Modell erhält die Benutzerinstruktion als vorrangigen
Bearbeitungsauftrag für beide Langtexte. Unveränderte Rückgaben werden weiterhin nicht als Erfolg
gespeichert.

Die Dokumentversionierung in 2.2.2 behandelt Profil- und Bewerbungsdokumente gleich: Die Auswahl
`Neue Version von` lädt Titel, Typ, Sprache, Beschreibung und Gültigkeitsdaten des aktuellen
Dokuments in das Uploadformular. Der Server bindet die neue Datei an dieselbe Titel-/Typ-Reihe und
ermittelt die nächste Nummer aus deren höchster vorhandener Version. Dateiablage und sämtliche
Datenbankänderungen bilden einen fehlersicheren Ablauf. Scheitert ein DB-Schritt, wird die
Transaktion zurückgerollt, die neue Datei entfernt und die bisherige Version bleibt aktuell.

## Priorität manueller Inserat-Adressen 2.2.1

Die automatische Jobsuche bleibt streng: Nur nachweislich verfügbare Anzeigen dürfen in der
Vorschlagstabelle erscheinen. Beim Schnellimport ist eine vom Benutzer eingegebene HTTPS-Adresse
dagegen ein ausdrücklicher Übernahmeauftrag. Eine lesbare, fachlich auswertbare Anzeige wird daher
auch dann importiert, wenn die Seite keinen technischen Aktualitätsbeleg wie `validThrough` oder
einen erkennbaren Bewerbungsbutton liefert. Die unsichere Verfügbarkeitsbewertung wird als
manueller Override im Importnachweis festgehalten; Drill-down, Firmen-/Kontaktsuche, Extraktion,
Match-Berechnung und Mindestanforderungen an Titel, Firma und Originaltext bleiben aktiv.

Bei einer instruktionsbasierten Überarbeitung prüft die App, dass Begleit-E-Mail und
Motivationsschreiben tatsächlich verändert wurden. Bleibt eines der beiden Felder unverändert,
wird der KI-Aufruf einmal mit einem verbindlichen Korrekturhinweis wiederholt. Ein weiterer
unveränderter Rücklauf wird nicht als Erfolg gemeldet; die bestehenden Texte bleiben erhalten
und die Meldung enthält eine im Serverlog auffindbare Fehlerreferenz.

## Formatierte Mehrzeilenfelder und Zeitachsen 2.2.0

Fachliche Langtexte werden als bereinigtes HTML gespeichert. Der Mini-Editor bietet Absätze,
Fett, Kursiv, Links, Aufzählungen, nummerierte Listen, Ein-/Ausrücken, Format löschen,
externe HTTPS-Bilder, Tabellen, Trennlinien und eine HTML-Ansicht. Serverseitig
gilt eine Positivliste für Elemente und Attribute; aktive Inhalte, Formulare, eingebettete Frames,
Ereignisattribute sowie unsichere Link- und Bildprotokolle werden entfernt. Historischer Klartext
wird beim Bearbeiten und Anzeigen kompatibel in Absätze und Zeilenumbrüche überführt.

HTML-E-Mails und das Bewerbungsdossier behalten die erlaubte Formatierung. Karten, Tabellen,
PDF- und Klartextexporte sowie KI-Kontexte verwenden eine lesbare Textdarstellung, sodass keine
HTML-Tags als Inhalt erscheinen. Gemischte
Bewerbungsaktivitäten, Statushistorien, Kontakt-Logs und Audit-Auszüge stehen standardmäßig
chronologisch vom ältesten zum neuesten Eintrag; begrenzte Ansichten wählen zuerst die neuesten
Einträge und ordnen diesen Ausschnitt anschließend aufsteigend.

## Zeitstempelvalidierung 2.1.9

Das Feld `applied_at` wird mit Sekunden aus der Datenbank und aus der automatischen Speicherung zurückgegeben. Das zugehörige `datetime-local`-Feld erlaubt deshalb mit `step="1"` Sekundenwerte und rendert den vollständigen Zeitstempel. Damit ist ein gespeicherter Wert wie `14:02:21` browserseitig gültig und ohne erzwungenes Runden erneut speicherbar.

## Soft-delete-sichere Neuanlage 2.1.8

Die natürliche Eindeutigkeit der Tabellen `users`, `company_relationships`, `job_platforms`,
`jobs` und `applications` enthält einen generierten Aktivmarker. Er ist bei aktiven Datensätzen
`1` und bei gelöschten Datensätzen `NULL`. Damit schützt der eindeutige Index weiterhin aktive
Dubletten, während beliebig viele historische gelöschte Datensätze eine Neuanlage nicht blockieren.
Die Laufzeitmigration wird durch ein Datenbank-Advisory-Lock serialisiert. Bevor ein bisher auch
für einen Fremdschlüssel benötigter Unique-Index ersetzt wird, legt sie einen eigenständigen
Stützindex an. So bleibt die referenzielle Integrität während der Migration erhalten.

Die Bewerbungserstellung verwendet zusätzlich `INSERT ... ON DUPLICATE KEY` mit
`LAST_INSERT_ID`, sodass parallele Klicks auf dieselbe aktive Stelle denselben Datensatz öffnen.
Gelöschte Bewerbungen werden nicht reaktiviert. Textinitialisierung und Datensatzanlage sind als
getrennte Fehlerphasen behandelt: Bei einem Textfehler bleibt die Bewerbung sichtbar und
bearbeitbar; Meldung und Serverlog teilen eine kurze Fehlerreferenz.

## Begrenzte Bewerbungsvorbereitung 2.4.46

Der Browser sendet «Bewerbung vorbereiten» mit einem zufälligen 128-Bit-
Laufschlüssel als asynchrone Formularanfrage. Die PHP-Sitzungssperre wird vor
den langen Netzwerkaufrufen freigegeben. Ein zweiter authentifizierter und
CSRF-geprüfter Request markiert denselben Lauf als abgebrochen; der aktive
Worker prüft dies auch über den cURL-Fortschrittscallback. Ein benutzerbezogenes
Dateilock verhindert parallele Vorbereitungen. Das Gesamtbudget beträgt
180 Sekunden und fünf KI-API-Aufrufe. Die App legt den Bewerbungsentwurf vor
der optionalen erneuten Inseratprüfung an, sodass Abbruch oder Budgetende
keine angelegten Daten verwerfen. Das Browserfenster bleibt beim Abbruch
bis zur bestätigenden Serverantwort offen; bei fehlender Antwort zeigt es
ausdrücklich einen unbestätigten Ausgang. Eine echte OpenAI-Kostenmessung
oder serverseitige Rücknahme bereits abgeschlossener Datenbanktransaktionen
ist nicht enthalten.

## Native Bewerbungsvorbereitung 2.1.6

`Bewerbung vorbereiten` wird nach zwei Renderzyklen für das sichtbare Arbeitsmodal als normale
Browser-Formularnavigation abgeschickt. Der Submit-Buttonwert wird dafür als verborgenes
Aktionsfeld mitgesendet. Damit folgt Safari der serverseitigen Weiterleitung selbst; clientseitige
Fetch-Auswertung und JSON-Parsing liegen nicht mehr im kritischen Navigationspfad. `Abbrechen`
stoppt eine noch laufende Navigation mit `window.stop()`.

## Zielnavigation nach Bewerbungsvorbereitung 2.1.5

Asynchrone KI-Aktionen kennzeichnen ihren Request. `Bewerbung vorbereiten` antwortet darauf mit
einem JSON-Navigationsvertrag, der die ID des erzeugten oder bereits vorhandenen
Bewerbungsdatensatzes enthält. Der Browser öffnet dieses Ziel nach Abschluss ausdrücklich und ist
nicht mehr auf die vom Fetch-Client interpretierte Weiterleitung angewiesen.

## Neuerstellung oder instruktionsbasierte Überarbeitung 2.1.4

Das leere KI-Instruktionsfeld löst eine vollständige Neuerstellung von Betreff, Begleit-E-Mail und
Motivationsschreiben aus den verfügbaren Bewerbungsdaten aus. Die bisherigen Texte werden in diesem
Fall nicht an das Modell übermittelt. Bei ausgefülltem Feld erhält das Modell die aktuellen Texte
und muss sie gemäß der Benutzerinstruktion überarbeiten.

## Mobile KI-Textaktion 2.1.3

Die KI-Aktionen werden direkt am geklickten Aktionsbutton erkannt und nicht mehr von der in
mobilen Browsern unzuverlässigen Übergabe des Submitters im Formularereignis abhängig gemacht.
Der Dialog `In Arbeit` wird vor dem Netzwerkaufruf gezeichnet. Danach sendet die App die aktuellen
Formulardaten samt Aktion per `fetch`. Eine vorhandene Instruktion muss in den relevanten Texten
substanziell sichtbar werden; ohne Instruktion verbessert die KI alle drei Texte selbständig. Eine
erfolgreiche Antwort wird mit einem Cache-Buster geladen. Abbrechen stoppt die Browser-Anfrage und
lässt die Seite offen.

## KI-Kennzeichnung 2.1.2

Die Fusszeile nennt weiterhin OpenAI und das konfigurierte Modell. Eine Prozentangabe zum
verfügbaren Kontingent wird nicht mehr ausgegeben, weil eine aus lokalen Requests abgeleitete
Schätzung weder das tatsächliche OpenAI-Guthaben noch andere Verbräuche zuverlässig abbildet.
Die in 2.1.1 eingeführte Tabelle und Kostenschätzung werden nicht weiter verwendet oder angelegt.

## KI-Arbeitsdialog und App-Kontingent 2.1.1

Manuell gestartete KI-Vorschläge, das Vorbereiten einer Bewerbung mit KI-Texten und die gemeinsame
Textüberarbeitung werden per `fetch` mit `AbortController` ausgeführt. Ein modales Fenster zeigt
`In Arbeit`, eine Aktivitätsanzeige und ausschließlich den bewussten Abbruch. Bestehende spezielle
Such- und Importdialoge bleiben unverändert. Ein Abbruch beendet die Browser-Anfrage und lädt die
Seite neu; eine auf dem Server bereits abgeschlossene Transaktion wird nicht rückgängig gemacht. Ab 2.1.3 bleibt die Seite beim Abbruch offen.

Verbindliche Produktregeln: [REQUIREMENTS.md](REQUIREMENTS.md), [WORKFLOW.md](WORKFLOW.md).
Exakte Tabellen, Felder und Funktionssignaturen: [DATA_MODEL.md](DATA_MODEL.md), [INTERFACES.md](INTERFACES.md).

## KI-gestützte Bewerbungstexte 2.1.0 (deployed)

Beim erstmaligen Vorbereiten einer Bewerbung erzeugt die App in der gewählten Benutzersprache
einen Betreff, eine kurze Begleit-E-Mail und ein Motivationsschreiben. Als Kontext dienen das
Bewerberprofil, der lesbare Inhalt der aktuellen CV-Version sowie die verfügbaren Stellen-, Firmen- und Kontaktdaten. Die Responses API liefert
ein striktes JSON-Objekt mit genau diesen drei Feldern; serverseitige Speicherung bei OpenAI ist
deaktiviert und der API-Schlüssel bleibt in der geschützten Konfiguration.

Die drei Entwürfe sind normale, automatisch gespeicherte Bewerbungsfelder. Darunter steht ein
zweizeiliges, nicht persistiertes Instruktionsfeld. Nur der Button `Texte mit KI erstellen/anpassen`
ersetzt die drei Felder gemeinsam. Diese Aktion versendet nichts. Quelldaten werden als nicht
vertrauenswürdige Daten behandelt; erfundene Fakten sind untersagt. Korrigierter CV-Text hat Vorrang
vor extrahiertem Text und OCR; fehlt ein lesbarer aktueller CV, wird ohne ihn fortgefahren. Bei einem API-Ausfall werden
lokale, bearbeitbare Grundentwürfe gespeichert, damit kein leeres Formular entsteht. Bestehende
Benutzertexte werden bei der Initialisierung nicht überschrieben. Keine DB-Schemaänderung.

## Frische Kandidaten und Restbudget 2.0.15 (deployed)

Der produktive Diagnosebericht aus 2.0.14 schloss 16 von 16 Quellen ab und erreichte sieben
brauchbare Treffer bei 52 geprüften Kandidaten. Von neun vollständig bewertbaren Anzeigen waren
sieben passend; der Mindestmatch war daher nicht der Engpass. Vor der Bewertung fielen 26 Anzeigen
als abgelaufen oder nicht belegbar verfügbar und 15 als technisch nicht lesbar aus. Zwei weitere
wurden mit 68 beziehungsweise 64 Prozent fachlich abgelehnt. Die Optimierung senkt den
Matchschwellenwert nicht.

Discovery erhält nun `current_date_utc` und verlangt bevorzugt kürzlich veröffentlichte einzelne
Anzeigen mit künftigem Gültigkeitsdatum oder sichtbarer Bewerbungsaktion. Erkennbar abgelaufene,
geschlossene, entfernte, generische, archivierte oder zugangsgesperrte Ergebnisse sollen bereits
dort entfallen; jede zurückgegebene URL durchläuft weiterhin die unabhängige Original-,
Verfügbarkeits-, Beleg-, Match-, Ausschluss- und Dublettenprüfung.

Die gleichmäßige Erstprüfung und das Gesamtlimit von 60 bleiben bestehen. Kann eine produktive
Quelle ihren Vertiefungsanteil nicht mit neuen eindeutigen URLs füllen, wird das verbleibende
Prüfbudget in bis zu zwei weiteren Durchgängen an diejenigen produktiven Quellen verteilt, die im
vorherigen Durchgang noch neue eindeutige Kandidaten lieferten. Damit bleiben im beobachteten Fall
nicht bis zu acht Prüfplätze ungenutzt. Leere oder nur doppelte Antworten werden nicht nochmals
bevorzugt; spätestens nach dem dritten Vertiefungsdurchgang endet die Suche.

Zusammenfassungen dürfen bis zu 2000 Zeichen enthalten und nutzen maximal zwölf sichtbare
Tabellenzeilen. Übersetzung und Primärbewertung verwenden dasselbe Limit. Keine DB-,
Konfigurations- oder Bestandsdatenänderung.

Das Deployment vom 04.09.2026 bestätigte die exakte Serverdatei, Version 2.0.15 auf der
öffentlichen Loginseite sowie den Login-Schutz des Debug-Downloads. Ein neuer angemeldeter
Suchlauf bleibt als produktiver Ausbeute- und Laufzeitnachweis offen.

## Frühe Resultate und Lohnbelege 2.0.14 (deployed)

Der Diagnosebericht aus dem produktiven Stand 2.0.13 bestätigt vier akzeptierte Treffer in
einem noch laufenden, begrenzten Snapshot. Sobald der erste Treffer gespeichert ist, ersetzt
Resultate im Suchdialog den Abbrechen-Button. Zähler, Quelle, Fortschritt und Laufzeit bleiben
sichtbar und die Suche läuft weiter. Resultate bricht nur die weiteren clientseitigen Schritte ab,
lädt die bereits in der eigenen Sitzung gespeicherten Treffer mit einem neuen GET und öffnet die Tabelle.

Die Ergebnis-Kurzbeschreibung darf nun bis zu 1000 Zeichen enthalten; die Tabellenzelle nutzt
den verfügbaren Platz und bleibt auf höchstens vier sichtbare Zeilen begrenzt. Übersetzung in die
aktive App-Sprache verwendet dasselbe Limit. Der vollständige Originaltext und gespeicherte Jobtext
werden dadurch nicht gekürzt oder übersetzt.

Eine extrahierte Lohnperiode wird zusätzlich zum KI-Vertrag deterministisch gegen ihr exaktes
Originalzitat geprüft. Eindeutige Formulierungen wie `/Monat`, `pro Jahr` oder `pro Stunde` bestimmen
month, year beziehungsweise hour. Ein widersprechender Modellwert wird korrigiert; ohne eindeutigen
Periodenbeleg wird die Periode verworfen. Es findet keine Umrechnung der Beträge statt. Keine DB-,
Konfigurations- oder Bestandsdatenänderung.

Das Deployment vom 04.09.2026 bestätigte die exakte Serverdatei, Version 2.0.14 auf der
öffentlichen Loginseite sowie den Login-Schutz des Debug-Downloads. Ein neuer angemeldeter
Suchlauf und der konkrete Monatslohnimport bleiben als fachliche Produktionsabnahme offen.

## Adaptive Quellenverteilung 2.0.13 (deployed)

Der Diagnosebericht aus dem produktiven Lauf 2.0.12 enthält 53 geprüfte Kandidaten bei Ziel 20: sechs akzeptiert, acht nach vollständiger Bewertung profilbezogen abgelehnt, 14 abgelaufen oder nicht ausreichend belegbar verfügbar und 25 technisch nicht lesbar. Nur 14 Kandidaten erreichten damit überhaupt die fachliche Bewertung. Wiederholte 401-/403-Sperren und nicht als einzelne Anzeige lesbare Aggregatorseiten verbrauchten einen großen Teil des starren Quellenanteils. Drei Jobs.ch-, zwei Xing- und ein ICTjobs-Treffer wurden akzeptiert. Diese Zahlen erklären den Lauf; sie erlauben keine Aussage über künftig andere Anzeigen derselben Quelle.

Bei mehreren Quellen reserviert die Suche nun einen gleichmäßigen ersten Durchgang und den Rest des unveränderten Gesamtlimits von 60 Prüfungen für einen zweiten, adaptiven Durchgang. Das Explorationsbudget beträgt mindestens 30 und mindestens zwei Kandidaten je Quelle; bei 16 Quellen exakt 32 beziehungsweise zwei je Quelle. Liefert eine Quelle zweimal denselben Zugriffs-/Lesefehlertyp, ohne dass eine Anzeige bewertet werden konnte, wird ihre restliche Warteschlange für diesen Lauf verworfen. Nach Abschluss aller ausgewählten Quellen wird das Restbudget gleichmäßig auf Quellen mit mindestens einem akzeptierten Treffer verteilt. Ohne akzeptierten Treffer werden stattdessen Quellen mit mindestens einer vollständig bewerteten Anzeige verwendet. Reihenfolge innerhalb dieser Gruppe: akzeptierte, danach bewertete Treffer; die Restquote bleibt gleichmäßig.

Die Discovery-Anweisung bevorzugt ausdrücklich verlinkte öffentliche Originalanzeigen des Arbeitgebers und verwirft Suchseiten, Aggregator-Redirects sowie Login-/Zugriffswände, wenn ein Original verfügbar ist. Jede zurückgegebene URL durchläuft unverändert Originalabruf, Verfügbarkeitsprüfung, belegbasierten Profilvergleich, Mindestscore 70, Ausschlüsse und Dublettenprüfung. Es gibt keinen Match aus Such-Snippets. Einzelquellensuche bleibt bei 45 Kandidaten; Zielzahl und Sicherheitslimit bleiben unverändert. Der Quellenzähler zeigt abgeschlossene Erstprüfungen und springt während der adaptiven Runde nicht rückwärts.

Das Deployment vom 04.09.2026 bestätigte die exakte Serverdatei, Version 2.0.13 auf der öffentlichen Loginseite sowie den Login-Schutz des Debug-Downloads. Ein neuer angemeldeter produktiver Suchlauf samt Diagnosebericht bleibt als fachlicher Ausbeutenachweis erforderlich.

## Ergebnisnavigation 2.0.12 (deployed)

Ergebnisse anzeigen erzwingt über einen nicht vertraulichen Zeitstempel-Queryparameter einen neuen GET der Jobsuche-Seite mit Ergebnisanker. Der bisherige reine Fragmentwechsel hat auf derselben Seite keinen PHP-Neuaufbau ausgelöst: Modal und alte leere Tabelle blieben stehen, obwohl Treffer bereits in der Session waren. Die neue Navigation lädt die aktuellen serverseitig gefilterten Treffer, ohne eine neue KI-Suche anzustoßen. Keine Änderung an Match, TTL, Profilbindung, Session- oder DB-Daten. Der neue Klicktest reproduzierte den alten Fehler und besteht mit der Korrektur; externe Daten/Serverantworten werden dabei simuliert.

Das Deployment vom 04.09.2026 bestätigte die exakte Serverdatei, Version 2.0.12 auf der öffentlichen Loginseite und den Login-Schutz des Debug-Downloads. Die angemeldete Produktionsabnahme mit echten Sitzungstreffern bleibt gesondert erforderlich.

## Gleichmäßige Quellenverteilung und Erfolg 2.0.11 (deployed)

Mindestens ein akzeptierter verifizierter Treffer bedeutet Sucherfolg, unabhängig von Zielzahl oder späteren Abruffehlern. Das Modal zeigt den Erfolg bereits während der weiteren Suche, bei begrenztem Abschluss und nach späterem Abbruch des Dienstes. Ohne Treffer wird zwischen abgeschlossener Suche ohne Ergebnis und technischem Fehler unterschieden. Die Suche endet nicht schon beim ersten Treffer; das Ziel bleibt unverändert. Der Debugbericht führt successful getrennt vom technischen status (laufend/abgeschlossen/fehlgeschlagen). Fehler werden nicht unterdrückt und unprüfbare Jobs bleiben ausgeschlossen.

jobSearchSourceQuota verteilt 60 Prüfplätze auf N ausgewählte Quellen: floor(60/N), die ersten 60 mod N Quellen erhalten einen zusätzlichen Platz. Bei 16 Quellen zwölfmal vier und viermal drei rohe Kandidaten. Die Restanforderung wird wie bisher vor Ausschlüssen/Dubletten gezählt und serverseitig begrenzt. Ein einzelner Discovery-Aufruf verlangt höchstens 15; drei Runden pro Quelle und frühere Beendigung ohne neue Kandidaten bleiben bestehen. Einzelquelle unverändert. Ungenutzte Quoten werden nicht neu verteilt. Bei mehr als 60 Quellen mindestens ein Platz je Quelle, jedoch bleibt die Gesamtgrenze 60: vollständige Abdeckung ist dann nicht möglich. Trefferziel, Abbruch, Sitzungsablauf oder Dienstausfall können weiterhin früher beenden.

Alte Zehner-Warteschlangen werden anhand des bereits verbrauchten Rohanteils auf die neue Quote gekürzt; akzeptierte Treffer bleiben erhalten. Match-Gewichte, Mindestscore und Datenbank-Schreibregeln unverändert. Keine Migration.

## Quellenwechsel 2.0.10 (historisch deployed)

Bei mehreren Quellen zählt source_raw die gelieferten URL-Kandidaten vor Dubletten-/Benutzerausschluss und Original-/Match-Prüfung. Nach insgesamt zehn rohen Treffern der Quelle wird deren Prüfwarteschlange abgearbeitet und anschließend zur nächsten ausgewählten Quelle gewechselt. Discovery fragt nur die verbleibende Anzahl an; serverseitiges Abschneiden begrenzt übergroße Antworten. Kleine Antworten können bis zur bisherigen Drei-Runden-Grenze ergänzt werden. Keine neuen Kandidaten bedeutet weiterhin früherer Wechsel. Einzelsuche bleibt bei bisherigen 15 Kandidaten je Discovery-Runde.

Die gewünschte Zahl passender Jobs und Gesamtgrenze 60 haben weiterhin Vorrang; das Quellenlimit wird als begrenzte Suche gekennzeichnet, nicht als vollständige Ausschöpfung. Alte laufende Mehrquellensuchen ohne Rohzähler wechseln von einer bereits begonnenen Quelle weiter, statt die alte große Warteschlange zu leeren. Bereits akzeptierte Treffer bleiben erhalten. Neue Suchen verwenden den exakten Zähler. Keine DB- oder Match-Änderungen.

Der neue vom Benutzer bereitgestellte Teilbericht aus 2.0.9 belegt fünf akzeptierte Treffer, zwei Profilablehnungen, 24 unverfügbare/unprüfbare Kandidaten und null technische Fehler bei 31 Prüfungen; weiterhin ausschließlich erste Quelle. Das bestätigt erfolgreiche Match-Verarbeitung dieses Laufs, nicht die vollständige fachliche Abnahme aller Bewertungen.

## Match-Vertrag 2.0.9 (deployed)

Der bereitgestellte Diagnosebericht aus 2.0.8 zeigt 30 Versuche, davon 23 ohne belegbare Verfügbarkeit, sechs technische Kriterienfehler und einen HTTP-404. Keine abgeschlossene Match-Bewertung; das ist kein Nachweis für 30 unpassende Jobs. Die sechs Fehler entsprechen exakt der Meldung über ungültige oder doppelte Match-Kriterien. Der Bericht enthält keine vollständigen URLs; die 23 konkreten Anzeigen können daraus nicht erneut abgerufen werden.

verifiedJobImport baut checks jetzt als strikt geschlossenes JSON-Objekt mit genau den aktiven Kriteriennamen als Pflichtschlüsseln. Die KI benennt diese Schlüssel nicht selbst; jobVerificationChecks validiert Schlüssel, Anzahl, Typen und Urteile nochmals und überführt sie in das bisherige interne Bewertungsformat. Ein leeres Profil erhält keine erfundene Prozentzahl. Belegprüfung, Gewichte, Schwelle, Datenbank-Schreibweg und Quellensuchreihenfolge bleiben unverändert. Fehler im Antwortvertrag werden als match_contract_error protokolliert und sichtbar gemeldet; die Suche verarbeitet dann nicht still weitere Anzeigen als Ablehnungen. Keine automatischen kostenpflichtigen Wiederholungsaufrufe.

Verfügbarkeit: Zusätzliche lokalisierte Hinweise auf geschlossene Bewerbungen haben Vorrang vor Frist/CTA. LinkedIn-Job-Bewerbungsbuttons außerhalb von main werden bei vorhandenen JobPosting-Metadaten erkannt. Explizit versteckte, inaktive oder deaktivierte Bedienelemente zählen nicht. Das ist eine HTML-basierte Prüfung, keine Browserausführung und keine Garantie, dass eine Stelle noch unbesetzt ist. Unprüfbare Anzeigen bleiben ausgeschlossen. Diagnose führt die gelesene Quellenkette und den tatsächlichen letzten Prüfschritt auch beim Abbruch mit.

## Suchdiagnose 2.0.8 (deployed)

job_search_debug_download exportiert die letzte protokollierte Suche der eigenen Sitzung als JSON-Anhang. requireLogin und effektive Benutzer-ID begrenzen den Zugriff; kein Report für eine fremde oder alte unprotokollierte Suche. Cache-Control private/no-store und nosniff; keine Datei im öffentlichen Dateisystem. Maximal 250 bereinigte Ereignisse in verified_job_search, entfernte Ereignisse werden gezählt. Neue Suche ersetzt den Bericht; Sitzungsende beendet den Zugriff. Ein noch laufender Request kann wegen des PHP-Sessionlocks den Download verzögern. Abbruch liefert einen Teilstand, keinen erfundenen erfolgreichen Abschluss.

Discovery protokolliert Quelle, gefundene/eingereihte Anzahl und Laufzeit. Kandidaten protokollieren Originalabruf, Verfügbarkeitsprüfung, Parsing oder Match-Auswertung; Erfolg, Profilablehnung, Dublette, Benutzerausschluss, unprüfbare Verfügbarkeit und technische Fehler bleiben unterscheidbar. Match-Diagnose enthält nur Kriteriennamen und met/partial/unmet/unknown sowie Score und Gewichtung, keine Profilwerte oder Belegtexte. Der Fehlerschritt bleibt auch bei abgefangenem Suchabbruch erhalten. Laufzeitinformationen PHP/cURL/DOM/mbstring helfen, fehlende Serverfunktionen zu erkennen.

Strikte Feldliste statt Export der gesamten Session: Domains und SHA-256-URL-Fingerprints statt Pfad/Query/Fragment/URL-Zugangsdaten. Fehlercodes, HTTP-/cURL-Nummern, Quellzeile und gegebenenfalls Name einer fehlenden Funktion/Klasse statt vollständiger Exception-/API-Antworten oder Stacktraces. Keine API-Schlüssel, Cookies, Session-IDs, Kontakt-/Inserattexte oder Konfiguration. Domains und abgeleitete Bewertungen bleiben potenziell vertraulich; gezielte manuelle Weitergabe, kein automatischer Versand. Bericht dient der Ursachenanalyse, behebt aber nicht automatisch die Ursache der 13 früheren Ausschlüsse.

Download-Link auf der Seite und im Modal in fünf Sprachen. Status nennt Verarbeitet, technischen Fehleranteil, abgeschlossene Quellen und aktuelle Domain. Eine lokale hidden-CSS-Regel verhindert, dass globale Button-/Progress-Styles den Ergebnisbutton während der Suche oder den Balken nach Abschluss sichtbar halten. Suche, Match-Schwelle und Importregeln bleiben unverändert. Keine DB-Migration.

## Verifizierte Suche und Import 2.0.7 (historisch deployed)

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
- Google: Betreiber-OAuth-Client plus Benutzerautorisierung und Zielkalender. Die geprüfte Exportprojektion läuft unabhängig von der getrennten Workflow-Bestandsmigration; Fremdeintraege schuetzen und Synchronisationsfehler sichtbar lassen.
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
