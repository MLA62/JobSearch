# Bewerbungsworkflow und Kalender

Stand: 18.09.2026. Aktuelles Verhalten für Release 2.4.32.

Ergänzung 2.4.32: Der KI-Auftrag verlangt einen vollständigen einseitigen
Motivationsbrief mit konkreter Verbindung zwischen Stellenanforderungen und
belegten CV-Erfahrungen sowie einem eigenen Schluss. Die Serverprüfung
kontrolliert Empfängerblock, Anrede, Hauptteillänge, Schlusssatz, Grussformel
und Namen in allen fünf Dokumentensprachen. Bis zu drei KI-Versuche erhalten
konkretes Feedback; bleibt der Brief unvollständig, wird der KI-Vorgang nicht
als Erfolg gespeichert. Bei einer manuellen Überarbeitung bleiben die alten
Texte erhalten. Die bestehende Grundentwurf-Regel bei der erstmaligen
Bewerbungsvorbereitung bleibt davon getrennt.

Bei ausdrücklich zugeordnetem Vermittler ist die Jobfirma der identifizierte
Endkunde, sofern sie nicht selbst als Vermittler markiert ist. Firmenprofil,
Branche, Grössenangabe, Notizen und Anforderungen werden der KI getrennt vom
Vermittler übergeben. Eine hinterlegte HTTPS-Website des Endkunden wird über
den SSRF-geschützten Importabruf mit kurzer Zeitgrenze gelesen; Weiterleitungen
auf fremde Domains werden nicht als offizielle Quelle übernommen. Fehlende
oder unlesbare Webdaten blockieren die Textvorbereitung nicht.

Ergänzung 2.4.31: Unmittelbar vor jedem KI-Aufruf werden die aktuellen,
eigenen Lebensläufe vom Metadatentyp `cv` neu geladen. Pro eingetragener
Dokumentsprache wird nur der nach `updated_at`, bei Gleichstand nach ID
neueste berücksichtigt. Mehrere unterschiedlich betitelte CV-Reihen derselben
Sprache werden somit nicht gemeinsam an die KI übergeben. Die vollständige
Datei und gegebenenfalls der korrigierte Text des ausgewählten CV werden
frisch gelesen. Vorhandene Bewerbungstexte ändern sich nicht allein durch
eine CV-Aktualisierung, sondern erst bei einem ausgelösten KI-Lauf.

Ergänzung 2.4.30: Sowohl die erste KI-Textvorbereitung als auch der spätere
Button «Texte mit KI erstellen/anpassen» übergeben alle aktuellen eigenen
Stammdaten-Lebensläufe direkt als Datei an die Responses API. Die Dokumente
müssen nicht manuell an die Bewerbung angehängt werden. Ein vorhandener
korrigierter Text wird zusätzlich als vorrangige Quelle übergeben. Ohne
Lebenslauf läuft die bisherige kontextbasierte Erstellung weiter; bei einer
unlesbaren vorhandenen CV-Datei wird der KI-Pfad als fehlgeschlagen behandelt.

Ergänzung 2.4.29: Der Job-Room-Statusfilter enthält alle atomaren Optionen
unabhängig von den gerade vorhandenen Reportzeilen. Nur «Noch nicht im
Job-Room erfasst» wählen, um alle unregistrierten Bewerbungen innerhalb der
gespeicherten Report-Grundauswahl zu sehen. Ohne Treffer bleibt der Filter
sichtbar und die Ergebnismenge leer. Die übrigen Auswahlfelder verwenden
weiterhin die Werte der Reportzeilen.

Ergänzung 2.4.28: Die sichtbare Job-Room-Statusspalte darf mehrere Eigenschaften
in einem Text darstellen; der Filter verwendet dagegen eigene Werte für
«Im Job-Room erfasst», «Noch offen», «Anstellung», «Absage» und
«Vorstellungsgespräch» sowie «Noch nicht im Job-Room erfasst».
Eine Zeile mit «Noch offen · Vorstellungsgespräch» wird durch jeden dieser
beiden Werte einzeln getroffen. Bereits geteilter Einzelauswahl-Link mit
vollständigem Anzeigetext wird weiterhin exakt ausgewertet.

Ergänzung 2.4.27: Im geöffneten Report sind Auswahlfilter Ankreuzlisten. Mehrere
Werte in einem Feld liefern die Vereinigung der Treffer; weitere Feldfilter
schränken diese Menge ein. Die gewählten Werte stehen in der URL und überleben
den Ansichtswechsel. Alte Einzelauswahl-Links werden weiter angenommen.

Ergänzung 2.4.26: Der Reportstatus kombiniert bestätigte Job-Room-Erfassung,
Job-Room-Resultat und gegebenenfalls das eigenständige Kennzeichen
«Vorstellungsgespräch». Ein offenes Resultat wird durch das Gespräch nicht ersetzt.
Die Reportfilter verwenden den vollständig angezeigten Wert; der lokale Status
«Bewerbungsgespräche» bleibt davon getrennt.

Ergänzung 2.4.25: Der Helper schliesst bereits im Job-Room erfasste Bewerbungen aus,
auch in der Monatsauswahl. Adresse und Absagegrund sind einzeln kopierbar. Ein
Statuswechsel zu `rejected` verlangt einen Absagegrund mit höchstens 249 Zeichen;
Formular, Kontakt-/E-Mail-Protokoll und Admin-KI-Schreibweg prüfen dies serverseitig.

Ergänzung 2.4.24: Das Dokumentformular speichert `is_application_relevant` als ausdrücklichen
Boolean mit Vorgabe 0. Bei einer neuen Version transportiert der Versionswähler das Kennzeichen
der aktuellen Version in das Ankreuzfeld. Die Bewerbungsmaske liest für den Vorschlagsbereich nur
eigene Profildokumente mit `is_current=1`, `is_application_relevant=1` und ohne Löschzeitpunkt.
Die tatsächlich zugeordneten Unterlagen werden weiterhin separat aus `application_documents`
gelesen, sodass das Ausschalten des Vorschlagskennzeichens keine bestehende Zuordnung entfernt.

Ergänzung 2.4.23: Der PDF-Button in der oberen Kalendernavigation exportiert den Zeitraum der
gewählten Ansicht (Agenda, Tag, Arbeitswoche, Woche oder Monat). Bei der Agenda werden die aktuelle
Filterung und Sortierung aus derselben Sitzung auf die PDF-Zeilen angewandt. Der ICS-Export bleibt
unverändert separat verfügbar.

Ergänzung 2.4.22: Der fachliche Workflow bleibt unverändert. Beim ersten Request eines Releases
werden notwendige Schema- und Inhaltsprüfungen einmalig unter Datenbanksperren ausgeführt. Alle
weiteren Aufrufe verwenden die gespeicherten Releasemarker. Das Speichern und Verwenden
mehrzeiliger Rollen und Orte arbeitet ohne PHP-Warnungen; manuell gespeicherte Suchkriterien laden
die zugehörigen Profileinstellungen vor der Ableitung.

Ergänzung 2.4.21: Der Bewerbungstext-Prompt enthält keine negativen Hinweise auf fehlende
Profil-, CV-, Kontakt-, Dokument- oder Verlaufsdaten mehr. `applicationTextHasDisqualifyingLanguage()`
prüft Begleit-E-Mail und Motivationsschreiben sprachübergreifend auf solche Meta-Aussagen und auf
Floskeln, die Inhalt auf ein späteres Gespräch verschieben. Der erste Treffer löst einen
verbindlichen KI-Wiederholungsversuch aus. Danach entfernt
`applicationTextWithoutDisqualifyingLanguage()` nur die beanstandeten Sätze; würde ein Feld dadurch
leer, wird ein positiver lokaler Entwurf eingesetzt. Dieselbe Bereinigung repariert bereits
gespeicherte Texte beim Öffnen einer Bewerbung.

Ergänzung 2.4.20: Das Schnellimportfeld wertet beim Einfügen neben `text/plain` auch die
`text/html`- und `text/uri-list`-Anteile der Zwischenablage aus. Sichere HTTP-/HTTPS-Ziele von `<a href>` werden direkt
unter ihrem sichtbaren Linktext als Klartext eingefügt, dedupliziert und anschliessend vom
bestehenden `extractImportUrls()`-Pfad verarbeitet. Ohne sichere Linkziele bleibt das native
Klartext-Einfügen unverändert. Vorhandener Text vor und nach der Auswahl bleibt erhalten.

Ergänzung 2.4.19: Im Rich-Text-Mini-Editor konvertiert `¶` sämtliche von der aktuellen Auswahl
geschnittenen Textblöcke in einzelne `<p>`-Absätze; vorhandene `<br>`-Grenzen werden dabei zu
Absatzgrenzen. Der direkt daneben angeordnete Button `↵` verbindet die gewählten Absätze innerhalb
eines `<p>` mit `<br>`-Umbrüchen, also dem Ergebnis von Shift+Enter. Die Auswahl bleibt nach der
Umwandlung erhalten und der bereinigte HTML-Wert wird unmittelbar in das Formularfeld geschrieben.

Ergänzung 2.4.18: `start_application` bindet die erneute Inseratanalyse mit `target_job_id` an die
ausdrücklich gewählte Stelle. `importStoreDraft()` aktualisiert dadurch genau diesen eigenen Job und
korrigiert eine verifiziert abweichende Firmenzuordnung, statt einen zweiten Job anzulegen oder den
Vorgang zurückzurollen. Die zuvor verknüpfte Firma bleibt unverändert. Inserat- und Empfängerrecherche
sind Anreicherungen: Fehler werden mit Referenz protokolliert, verhindern aber weder die atomare
Bewerbungsanlage noch die anschliessende KI- beziehungsweise lokale Textvorbereitung. Die
Empfängerauflösung ignoriert Kontakte ausserhalb der aktuellen Arbeitgeber- und Vermittlerfirma.

Ergänzung 2.4.17: `reportFieldRecordUrl()` bestimmt für jede sichtbare Reportspalte das konkrete
Linkziel. Beziehungsfelder verwenden ihre eigene Fremd-ID; alle übrigen Felder verwenden die ID des
Basisdatensatzes. `reportDataset()` liefert die positionsgleichen `cell_urls` zusammen mit den
formatierten Werten. Sämtliche Renderer verlinken den Feldinhalt direkt und geben weder zusätzliche
Aktionsspalte noch separaten Datensatzbutton aus.

Ergänzung 2.4.16: `verifiedJobImport()` führt nach der strukturierten Inseratanalyse eine zweite
Responses-API-Anfrage mit Websuche aus, sobald Strasse, PLZ, Ort oder ein Recruiting-Kontakt fehlen.
`jobWebResearchResponse()` lässt nur belegte Felder aus öffentlichen HTTPS-Quellen zu und lädt jede
zitierte Seite anschliessend selbst. Nur ein tatsächlich auf der Seite gefundener Kurzbeleg gelangt
zu `applyJobWebResearch()`. `importUpsertCompany()` und `importDraftContacts()` ergänzen die belegten
Werte fill-only. `initializeApplicationTexts()` wiederholt diese Empfängerrecherche auch für eine
bereits bestehende Bewerbung, bevor der Adressblock erzeugt oder korrigiert wird.

Ergänzung 2.4.15: Vor dem Anlegen oder Öffnen einer Bewerbung wird das Originalinserat erneut
gelesen und zwingend über die strukturierte KI-Analyse geführt. Die belegten Ergebnisse ergänzen
Firma, Kontakt und Job fill-only. Danach ermittelt die Bewerbung ihren Empfänger in der Reihenfolge
Primärkontakt, Bewerbungsbezug, Jobbezug, HR-/Recruiting-Firmenkontakt, sonstiger Firmenkontakt.
Diese Auflösung gilt identisch für KI-Prompt, Ausfalltext und bestehende Motivationsschreiben.

Ergänzung 2.4.14: Auswahlfilter verwenden den fertig formatierten, sichtbaren Fachwert. Dadurch
bleiben insbesondere «Noch nicht im Job-Room erfasst» und «Im Job-Room erfasst – Noch offen» trotz
eines möglichen gemeinsamen Rohwerts getrennt. Datums- und Zahlenfilter verwenden weiterhin die
unformatierten Werte; Textfilter vergleichen den sichtbaren Text. Der KI-Entwurf und der lokale
Ausfallentwurf erhalten den Empfängerblock aus der Bewerbung. Ein bekannter Primärkontakt wird
zwischen Firmenname und Firmenanschrift gesetzt; eine abschliessende Serverprüfung ergänzt einen
vom Modell ausgelassenen Block, ohne einen vorhandenen Block zu duplizieren.

Ergänzung 2.4.13: Die gespeicherte Report-Anzeigeart bleibt die Voreinstellung. `report_as=table`
oder `report_as=cards` schaltet die geöffnete Ansicht ohne Datenbankänderung um; ungültige Werte
fallen auf die gespeicherte Anzeigeart zurück. `reportRecordUrl()` bildet jede Datenbasis anhand der
internen ID auf ihren eigenen Editor ab. Der sichtbare Link wird in allen Report-Renderern ergänzt.
`reportViewFilterType()` bestimmt je angezeigtem Feld Text-, Datums-, Zahlen- oder Auswahlfilter.
Der Server akzeptiert ausschließlich Filter der gespeicherten Reportspalten, kombiniert sie mit UND
und hält die zugehörigen Zeilenmetadaten und Datensatzlinks positionsgleich. Aktive Filter werden beim
Umschalten zwischen Tabelle und Karten in der URL mitgeführt.
Die Übersetzung ersetzt sowohl `{name}`- als auch ältere `:name`-Platzhalter.

Ergänzung 2.4.12: Der Spaltenfilter «Links» verwendet geprüfte Auswahlwerte statt einer Texteingabe.
Für Jobs, Bewerbungen und Kontakte kann jeweils Vorhandensein oder Nichtvorhandensein verlangt werden.
`sfState()` speichert die Auswahl wie andere Feldfilter; `sfApplySql()` bildet jeden Wert ausschließlich
auf eine fest definierte SQL-Bedingung ab und kombiniert mehrere Kriterien mit UND.

Ergänzung 2.4.11: Ein Report speichert die Anzeigeart zusammen mit Datenbasis, Feldern, Filtern und
Sortierung. Beim Anzeigen normalisiert `reportDisplayType()` die Kombination und `reportRowsHtml()`
rendert Tabelle, Liste, Karten, Vorschau oder bei Kalenderdaten gruppierte Tages-, Wochen- und
Monatsansichten. Ein Basiswechsel ersetzt im Editor auch die Liste gültiger Anzeigearten.

Ergänzung 2.4.10: `jobRoomApplicationStatus()` behandelt einen Datensatz nur dann als im Job-Room
erfasst, wenn `job_room_registration=recorded` und ein tatsächliches `applied_at` vorhanden sind.
Der sichtbare Status enthält anschließend sowohl die bestätigte Erfassung als auch das Resultat.
Damit kann der technische Standardwert `job_room_result=open` keinen offenen Job-Room-Vorgang
vortäuschen.

Ergänzung 2.4.9: Vor der Reportformatierung normalisiert `reportNormalizeRelations()` ausschließlich
eindeutig belegte Alternativbeziehungen. Das betrifft Vermittler über die als Vermittler markierte
Stellenfirma, zugewiesene Kontakte ohne Namen über deren E-Mail, den Dokument-Job über die verknüpfte
Bewerbung, den Kalender-Firmenbezug über den Kontakt und den Kontakt-Job über dessen Bewerbung.

Ergänzung 2.4.8: Bei der Ausgabe des Feldes Job-Room-Resultat wird zuerst
`job_room_registration` ausgewertet. Nur `recorded` erlaubt die Werte «Noch offen», «Anstellung»
oder «Absage»; alle nicht bestätigten Zustände werden als «Noch nicht im Job-Room erfasst» ausgegeben.
Im Report-Editor lassen sich Felder am Griff per Drag-and-drop ordnen. Die Formularreihenfolge wird
als Spaltenreihenfolge gespeichert und beim erneuten Bearbeiten wiederhergestellt.
Nach dem Anlegen oder Aktualisieren lädt die Zielroute Editor und Report anhand derselben Report-ID
neu und springt direkt zur aktualisierten Reporttabelle.

Ergänzung 2.4.7: Der Anzeigen-Link bleibt in «Auswertungen», lädt den gewählten Report
mandantenbegrenzt über seine ID, übernimmt dessen gespeicherte Felder, Filter und Sortierung und rendert
darunter die wirkliche Reporttabelle. Tabellenköpfe und Datenzellen werden aus demselben validierten
Spaltenarray erzeugt und bleiben deshalb positionsgleich.

Ergänzung 2.4.6: Beim Erstellen oder Bearbeiten eines Reports bestimmt die Datenbasis die vollständige
Liste fachlich freigegebener Spalten. Ein Basiswechsel ersetzt Feldauswahl, Sortierfelder und Statuswerte
sofort. Die Oberfläche verhindert eine dreizehnte Auswahl; beim Speichern, Laden und Export begrenzt der
Server unabhängig davon auf zwölf eindeutige und erlaubte Felder. Nicht mehr erlaubte Legacy-Felder werden
beim Laden verworfen.

Ergänzung 2.4.5: Nach einer gültigen Passwortprüfung mit aktivem TOTP entsteht eine explizite offene
Challenge. Nur dieser Zustand rendert und verarbeitet die Code-Eingabe. Eine bereits angemeldete Sitzung
ignoriert ein veraltetes TOTP-Formular, löscht dessen Challenge-Reste und kehrt zum Dashboard zurück.
Ohne Anmeldung und ohne Challenge geht es direkt zur Login-Seite. Erst ein gültiger Code überführt die
offene Challenge in eine authentifizierte Sitzung; ein ungültiger Code erzeugt keine Anmeldung.

Ergänzung 2.4.4: Der Google-Abgleich verifiziert auch bei unverändertem Inhalt die tatsächliche
Existenz jedes JeMa-Termins im aktuell gewählten Zielkalender. Veraltete Verknüpfungen nach einem
Kalenderwechsel und extern gelöschte Google-Termine werden mit einer reproduzierbaren Ersatz-ID
wiederhergestellt. Vorher werden Statusverläufe aller aktiven Bewerbungen erneut projiziert.
Erwartete und bestätigte Exportzahlen müssen übereinstimmen; Einzelfehler werden mit Quelle und ID
gespeichert. Die erste angemeldete Anfrage jeder Sitzung stößt diese Vollständigkeitsreparatur an;
unvollständige Läufe werden bei der nächsten Anfrage erneut versucht.

Ergänzung 2.4.3: Der direkte Google-Abgleich verwendet die geprüfte Kalenderprojektion auch dann,
wenn die getrennte Workflow-v6-Bestandsbereinigung noch nicht ausgeführt wurde. Änderungen und
Löschungen an Kalender, Bewerbung, Job, Firma oder primärem Kontakt lösen den Abgleich aus. Ein
Fehler wird im Profil gespeichert; ein erfolgreicher Vollabgleich löscht ihn wieder. Der abonnierbare
ICS-Feed wird ohne App-Cache ausgeliefert, wobei der externe Kalender sein Abrufintervall selbst bestimmt.

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

Die Bestandsmigration bleibt ein eigener, explizit geprüfter Datenvorgang. Sie ist keine Voraussetzung
für den Google-Abgleich; dessen Exportfilter blendet ungeprüfte Legacy-Projektionen aus. Rueckweg:
betroffene gesicherte Datensaetze gezielt pruefen/wiederherstellen; keine pauschale Ruecksicherung ueber
spaetere Benutzerarbeit.

## Querverweise

[Programmlogik](PROGRAMMDOKUMENTATION.md), [Datenmodell](DATA_MODEL.md),
[Tests](TESTING.md), [Neuaufbau](REBUILD.md), [Hilfe](help/de-CH.md).
