# Programmdokumentation

Stand: 2026-09-04. Version 2.0.15 ist für das Deployment vorbereitet; bestätigter Live-Stand ist 2.0.14. Schnellimport und profilbasierte Suche bleiben getrennte Einstiege mit gemeinsamem Prüf-/Importweg. Historische Release-Nachweise bleiben getrennt von diesem Stand.
Verbindliche Produktregeln: [REQUIREMENTS.md](REQUIREMENTS.md), [WORKFLOW.md](WORKFLOW.md).
Exakte Tabellen, Felder und Funktionssignaturen: [DATA_MODEL.md](DATA_MODEL.md), [INTERFACES.md](INTERFACES.md).

## Frische Kandidaten und Restbudget 2.0.15 (Deployment ausstehend)

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
