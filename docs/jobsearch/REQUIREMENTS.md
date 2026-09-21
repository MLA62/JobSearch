# Anforderungen

Stand: 21.09.2026. Zielbeschreibung mit Ergänzungen bis 2.4.48.

Release 2.4.48: Der Abstand nach einem normalen Absatz oder einer
Überschrift H1–H3 beträgt 8 pt statt 6 pt (ein Drittel mehr). Die
Abstände davor, Schriftgrössen und weichen Zeilenumbrüche bleiben
unverändert; einzelne Listenpunkte haben weiterhin keinen Zusatzabstand.

Release 2.4.47: Alle Rich-Text-Felder verwenden dieselbe Absatzsemantik.
Ein Klick in Text oder Aufzählungen verändert weder Inhalt noch Format.
Automatischer Umbruch bleibt innerhalb einer Zeile, Shift+Enter erzeugt
einen weichen Umbruch ohne Abstand und Enter einen neuen Absatz mit 6 pt
Abstand danach. Die vier wählbaren Absatzformate sind Absatz, H1, H2 und
H3. Weil die bisherige Editorbasis durch die Formularlabel-Regel etwa
10,8 pt und nicht 10 pt betrug, gilt nun die vom Benutzer für diesen Fall
vorgegebene 12-pt-Stufe: Absatz 12 pt/0 pt davor/6 pt danach, H3 14 pt/
12 pt davor/6 pt danach, H2 16 pt/12 pt davor/6 pt danach und H1 18 pt/
24 pt davor/6 pt danach, Überschriften fett. Listenpunkte haben keinen
zusätzlichen Abstand. Tx entfernt die markierte Inline-Formatierung und
setzt markierte Überschriften oder Listeneinträge auf Absätze zurück,
ohne benachbarte Listeneinträge zu verändern. Die bisherigen
Konvertierungsbuttons ¶ und ↵ werden durch das eindeutige Formatmenü
ersetzt. Bestehende HTML-Inhalte und manuelle `<br>` bleiben erhalten.

Release 2.4.46: «Bewerbung vorbereiten» erhält ein serverseitiges Budget
von höchstens drei Minuten und fünf KI-API-Aufrufen. Das Budget gilt über
Inseratprüfung, optionale Empfängerrecherche, Textentwurf und KI-Review.
Ein Abbruch wird mit einem zufälligen, benutzergebundenen Laufschlüssel an
den Server übermittelt und während HTTP-/KI-Abrufen geprüft. Die PHP-Sitzung
bleibt während des Laufs für den Abbruch-Request frei; pro Benutzer läuft
höchstens eine solche Vorbereitung gleichzeitig. Das Arbeitsfenster wartet
nach «Abbrechen» auf die Serverantwort statt den Abbruch lediglich optisch
zu behaupten. Eine bereits angelegte Bewerbung bleibt bei Abbruch oder
Budgetende bearbeitbar; die Abschlussmeldung unterscheidet Erfolg, Abbruch,
Budgetende und technischen Fehler. Der tatsächliche API-Verbrauch wird
damit begrenzt, aber die externe Abrechnung nicht in der App gemessen.

Release 2.4.43: Öffentliche Job-Room-Detailadressen `/job-search/<UUID>`
müssen im Schnellimport als einzelne Inserate erkannt werden. Der Import
liest veröffentlichte Detaildaten samt Originaltext, Arbeitgeber und
Gültigkeit; Suchseiten, fremde Hosts sowie unveröffentlichte oder
unvollständige Datensätze werden nicht als Anzeige ausgegeben.
Zusätzlich muss der Schnellimport kopierte Job-Room-Ergebnislisten ohne
Linkziele als mehrere Stellenkarten erkennen. Für jede Karte wird die
veröffentlichte Detailadresse anhand Titel, Firma und Arbeitsort gesucht;
Datum und Beschreibung unterscheiden gleichnamige Anzeigen. Jede Karte
wird einzeln verarbeitet und im Verlauf ausgewiesen. Unvollständige oder
mehrdeutige Karten werden ausdrücklich als Fehler gezählt, nicht still
weggelassen oder einer fremden Stelle zugeordnet. Anderer Freitext bleibt
ein einzelner Formularentwurf.

Release 2.4.42: Ein gespeicherter Absagegrund muss nach manuellem Speichern,
Autosave und erneutem Öffnen der abgelehnten Bewerbung im Formular stehen
bleiben. Ein blosses erneutes Speichern darf ihn nicht unbeabsichtigt leeren.

Release 2.4.41: Der Button «Online eingereicht» steht im Bereich
«Onlinebewerbung» unmittelbar bei «Webformular öffnen» statt am Ende des
langen Bewerbungsformulars. Der Hinweis auf diesen Button erscheint nur,
solange die Bewerbung noch als Entwurf/Bereit ohne Bewerbungsdatum geführt
wird. Nach der externen Einreichung protokolliert der bewusste Klick wie
bisher Status, Zeitpunkt und Kalender; das Öffnen des Portals allein nicht.

Release 2.4.40: Fehlende Empfängerangaben werden bei jeder KI-Überarbeitung
zuerst aus den eigenen CRM-Datensätzen erneut ermittelt. Ein vollständiger
aktueller Kontaktblock darf nicht durch einen alten dreizeiligen Briefkopf
verdrängt werden. Redaktionelle Qualitäts- und simulierte Empfängerurteile
sind höchstens Anlass für einen Verbesserungsversuch, aber kein Veto über
einen bereits strukturell und gemäss der ausdrücklichen Benutzeranweisung
gültigen Entwurf. Ein späterer Retry darf diesen nicht vernichten.
Technische Defekte, leere Texte und tatsächlich nicht umgesetzte
Benutzeranweisungen bleiben verbindliche Fehler. Siehe
[Abbruchkriterien-Audit](AI_ABORT_GATE_AUDIT.md).

Hotfix 2.4.39: Ein einzelner technisch entfernbarer, bewerberschädlicher
Satz darf einen ansonsten substanziellen KI-Entwurf nicht vollständig
blockieren. Die Bereinigung erfolgt vor Brief-, Auftrags-, Quellen- und
Empfängerprüfung; unvollständige Reste werden erneut erzeugt.

Not-Update 2.4.38: Das blosse Öffnen einer bestehenden Bewerbung ist für
Betreff, Begleit-E-Mail und Motivationsschreiben vollständig lesend. Es darf
keine Bereinigung und keine Korrektur des Empfängerblocks speichern. Auch die
Initialisierung einer bereits bestehenden Bewerbung ergänzt ausschliesslich
leere Textfelder; jedes nicht leere Feld bleibt bytegenau erhalten. Der
KI-Button mit leerer Instruktion ergänzt ebenfalls nur leere Textfelder.
Sind alle drei Felder gefüllt, bleibt die Datenbank unverändert und die App
fordert eine ausdrückliche Änderungsanweisung an. Nur eine solche Anweisung
oder manuelles Speichern darf vorhandene Texte ändern. Diese Regel ersetzt
die ältere Neuerstellung-bei-leerer-Instruktion-Regel aus 2.1.4 und die
automatische Reparatur gespeicherter Texte beim Öffnen aus 2.4.21.

Ergänzung 2.4.35: Die Text-KI erstellt Begleit-E-Mail und Motivationsschreiben
mit dem Nutzen für den konkreten Arbeitgeber als Hauptziel, nicht als
chronologische CV-Zusammenfassung und ohne Erfolgszahlen als Standard. Sie
erhält bei jedem Aufruf nur die aktuelle Stelle, benötigte Firmen- und
Empfängerangaben und dynamisch den neuesten aktuellen Stammdaten-Lebenslauf
je Metadatensprache. Andere Dokumenttypen, frühere CV-Versionen und frühere
Bewerbungstexte dürfen nicht in den KI-Kontext gelangen. Nur bei einer
ausdrücklichen Überarbeitung werden zusätzlich die aktuell im Formular
stehenden Texte übergeben; sie sind die einzige Textvorlage. Eine gültige
Betreffzeile zwischen Empfängerblock und Anrede ist zulässig. Scheitert die
KI, bleiben neben der Anweisung auch die gerade eingegebenen Texte im Formular
erhalten. Neue Entwürfe mit quantifizierten Erfolgen sind standardmässig
zurückzuweisen; ausdrücklich angeforderte Zahlen bleiben möglich.

Ergänzung 2.4.34: Eine ausgefüllte KI-Bearbeitungsanweisung hat gegenüber
Standardvorgaben zu Länge und Formulierung Vorrang, soweit belegte Fakten,
Empfängerblock und vollständiger Brief erhalten bleiben. Nennt die Anweisung
ein bestimmtes Textfeld, werden andere Felder nicht unnötig umformuliert;
ohne Feldnennung gelten beide Langtexte als Ziel. Jeder Korrekturversuch
erhält die ursprüngliche Benutzeranweisung erneut und nur das aktuelle
Fehlerfeedback, nicht eine wachsende Reihe alter Entwürfe. Der gemeldete Fall
«Motivationsschreiben 30 % ausführlicher; Erfolgszahlen weg» muss nach der
KI-Antwort zusätzlich an Länge und quantitativen Erfolgsaussagen geprüft
werden; Hausnummer und PLZ des Empfängers zählen nicht als Erfolgszahlen.
Ein nicht erfüllter Auftrag überschreibt vorhandene Texte nicht. Die
Benutzeranweisung bleibt bei einem Fehler im Formular erhalten.

Ergänzung 2.4.33: Beim KI-Vorgang zeigt ein Dialog die konkrete Aktion und
die seit Beginn verstrichene Zeit sekündlich an; ohne Streaming darf er keine
nicht beobachtbaren internen Arbeitsschritte behaupten. Beim Erstellen eines
neuen Bewerbungstextes dürfen alte Texte nicht als Vorlage in den Prompt
gelangen. Die Antwort soll die Stellenanforderungen mit mindestens zwei
konkreten, aktuellen CV-Fakten verknüpfen; ein internes Quellenprotokoll ist
gegen das Inserat und, soweit vorhanden, korrigierten CV-Text zu prüfen.
Begleit-E-Mail und Brief werden gemeinsam auf Substanz, Adressierung und
Abschluss geprüft. Die KI bekommt konkrete Fehler zur Überarbeitung. Nach
endgültigem Fehlschlag werden weder generische Ersatztexte als Erfolg
gespeichert noch vorhandene Texte überschrieben. Die tatsächliche sprachliche
Qualität benötigt eine fachliche Abnahme mit echten, selbst gewählten Daten.

Ergänzung 2.4.32: Ein KI-Motivationsschreiben ist ein vollständiger,
individueller Brief und nicht ein bloss nichtleerer Text. Nach dem exakten
Empfängerblock folgen Anrede, substanzieller, stellen- und CV-bezogener
Hauptteil, eigenständiger Schlusssatz, passende Grussformel und der vollständige
Bewerbername als letzte Zeile. Der Hauptteil umfasst mindestens 100 und
höchstens 450 Wörter. Mangelhafte KI-Rückläufe werden mit konkretem
Prüfergebnis erneut angefordert; eine weiterhin mangelhafte Antwort darf
nicht als erfolgreiches KI-Schreiben gespeichert werden. Bestehende Texte
bleiben bei manueller Überarbeitung im Fehlerfall erhalten. Bei einer
Vermittlung trennt die KI Vermittler und eindeutig identifizierten Endkunden.
Sie verwendet zusätzlich das vorhandene Endkundenprofil und, sofern sicher
erreichbar, einen begrenzten Auszug der dort hinterlegten offiziellen Website.
Ein unbekannter Endkunde oder unbelegte Eigenschaften werden nicht erfunden.

Ergänzung 2.4.31: Für jede in den Metadaten eingetragene Dokumentsprache
berücksichtigt die KI bei Erstellung und Überarbeitung der Bewerbungstexte
genau den aktuellen, nicht gelöschten Stammdaten-Lebenslauf mit dem jüngsten
`updated_at` (bei Gleichstand die höhere Dokument-ID). Die Auswahl wird bei
jedem KI-Aufruf erneut aus der Datenbank ermittelt; Titel und Versionsnummer
sind keine sprachübergreifenden Auswahlkriterien. Eine spätere Änderung oder
neue Version wird beim nächsten Aufruf berücksichtigt. Die hinterlegte
Dokumentsprache ist massgeblich, nicht die Sprache im Dateinamen.

Ergänzung 2.4.30: Die KI zur Erstellung und Überarbeitung des Motivationsschreibens
liest ohne zusätzliche Anweisung alle aktuellen, nicht gelöschten Lebensläufe aus
den eigenen Stammdaten als vollständige Dateien und berücksichtigt die belegten,
stellenrelevanten Erfahrungen. Korrigierter Lebenslauftext hat bei Widersprüchen
Vorrang. Alte Versionen und fremde Dokumente dürfen nicht in den KI-Kontext
gelangen. Kann ein vorhandener Lebenslauf nicht sicher gelesen werden, darf die
App keine erfolgreiche KI-Erstellung behaupten. Es findet kein automatischer
Bewerbungsversand statt.

Ergänzung 2.4.29: «Noch nicht im Job-Room erfasst» muss im geöffneten
Bewerbungsreport immer als unabhängiger Filterwert anwählbar sein, auch wenn
der aktuelle Report keine solche Zeile enthält. Ausgewählt ohne weiteren
Job-Room-Wert zeigt er ausschliesslich nicht erfasste Bewerbungen; bei
fehlenden Treffern eine leere Ergebnismenge. Der gespeicherte Report bleibt
unverändert.

Ergänzung 2.4.28: Der Job-Room-Filter bietet unabhängige Werte für Erfassung,
Resultat und Vorstellungsgespräch, keine zusammengesetzten Anzeigetexte. «Noch offen»
schliesst auch offene Bewerbungen mit Vorstellungsgespräch ein; «Vorstellungsgespräch»
kann unabhängig davon gewählt werden. Mehrere Werte gelten als ODER. Neue
Beschriftungen dürfen auch bei fehlendem Datenbank-Katalogeintrag nicht als
technische Übersetzungsschlüssel erscheinen.

Ergänzung 2.4.27: Auswahlfelder im geöffneten Report müssen mehrere ankreuzbare
Werte erlauben. Mehrere Werte desselben Feldes gelten als ODER, verschiedene
Feldfilter zusammen weiterhin als UND. Ohne Auswahl gilt das Feld als ungefiltert.
Im Job-Room-Status können «Noch offen» und «Noch offen · Vorstellungsgespräch»
gemeinsam gewählt werden, damit beide offenen Varianten im Ergebnis bleiben.
Die Auswahl muss beim Wechsel zwischen Tabelle und Karten erhalten bleiben.

Ergänzung 2.4.26: Der gespeicherte Report «Job-Room Bewerbungen» zeigt im Feld
«Job-Room Status» bei bestätigter Erfassung und Bewerbungsdatum neben dem Resultat
auch ein gesetztes «Vorstellungsgespräch». «Noch offen» und «Vorstellungsgespräch»
sind gleichzeitig sichtbar und unabhängig filterbar; der lokale Bewerbungsstatus
bleibt ein separates Feld.

Ergänzung 2.4.25: Der Job-Room-Helper zeigt nur datierte Bewerbungen, die noch nicht als
`recorded` markiert sind. Strasse, Hausnummer, Postleitzahl und Ort müssen in dieser
Reihenfolge getrennt angezeigt und kopiert werden können. Bei Status `rejected` ist ein
mehrzeiliger Absagegrund von 1 bis 249 Zeichen obligatorisch, auch bei Statuswechseln
ausserhalb des Bewerbungsformulars. Das Feld erscheint oberhalb der Karten nur bei
Absage und ist im Helper ebenfalls kopierbar.

Ergänzung 2.4.24: Profildokumente besitzen das Ankreuzfeld `Bewerbungsrelevant`. Es ist bei
neuen Dokumenten standardmässig deaktiviert. Nur aktuelle, nicht gelöschte und ausdrücklich
gekennzeichnete Profildokumente werden in Bewerbungen als hinzufügbare Dokumente vorgeschlagen.
Bereits einer Bewerbung zugeordnete Dokumente bleiben unabhängig von einer späteren Deaktivierung
sichtbar und zugeordnet. Beim Ersetzen einer Datei übernimmt das Formular das Kennzeichen der
gewählten aktuellen Version; der Benutzer kann es vor dem Speichern ändern.

Ergänzung 2.4.23: In der oberen Kalendernavigation steht direkt neben dem ICS-Export ein
PDF-Export zur Verfügung. Er übernimmt die aktuell gewählte Kalenderansicht und das Ankerdatum.
In der Agenda müssen zusätzlich die aktiven Feldfilter und die Sortierung gelten. Das PDF enthält
Zeit, Ereignis, Typ, Status und Bezug aller Resultate des sichtbaren Zeitraums.

Ergänzung 2.4.22: Normale Seitenaufrufe dürfen keine vollständigen Schema-, Seed- oder
Migrationsläufe ausführen. Solche Arbeiten laufen höchstens einmal pro Release, sind gegen parallele
Ausführung gesperrt und werden erst nach erfolgreichem Abschluss markiert. Stellenportal-Seeds
dürfen nicht beim blossen Anzeigen einer Seite geschrieben werden. Fehlerhafte Suchkriterien dürfen
weder wiederkehrende PHP-Warnungen noch Fatal Errors erzeugen.

Ergänzung 2.4.21: KI-generierte Begleit-E-Mails und Motivationsschreiben dürfen weder fehlende,
unlesbare oder unvollständige Daten, Unterlagen, Lebensläufe, Erfahrungen oder Qualifikationen
gegenüber dem Arbeitgeber offenlegen noch fehlende Substanz auf ein späteres Gespräch oder
Interview verschieben. Nicht belegte Aussagen werden still weggelassen. Die Regel gilt für alle
unterstützten Sprachen, initiale Entwürfe, KI-Überarbeitungen und lokale Rückfalltexte. Ein
unzulässiger KI-Rücklauf wird automatisch neu erzeugt; verbleibende unzulässige Sätze werden vor
dem Speichern technisch entfernt. Bereits gespeicherte betroffene Texte werden beim Öffnen der
Bewerbung bereinigt, ohne positive belegte Aussagen zu löschen.

Ergänzung 2.4.20: Werden formatierte Suchresultate, HTML-Inhalte oder sichtbare Linktexte aus einer
Webseite in den Schnellimport eingefügt, müssen die hinter den Links gespeicherten HTTP-/HTTPS-Ziele
als Klartext-URLs übernommen werden. Linkbeschriftungen allein dürfen das Ziel nicht ersetzen.
Mehrere eindeutige Links bleiben in ihrer Reihenfolge erhalten; unsichere Protokolle wie
`javascript:` werden nicht übernommen. Bereits vorhandener Feldinhalt darf beim Einfügen nicht
verloren gehen.

Ergänzung 2.4.19: Der Rich-Text-Editor muss markierte Textblöcke in beide Richtungen umwandeln.
`¶` zerlegt alle markierten Zeilen an vorhandenen weichen Umbrüchen in echte HTML-Absätze.
Der unmittelbar folgende Button `↵` verbindet alle markierten Absätze mit weichen Umbrüchen
entsprechend Shift+Enter. Inline-Formatierungen wie Fett, Kursiv und Links bleiben erhalten; der
umgewandelte Stand wird sofort in das zu speichernde Feld synchronisiert.

Ergänzung 2.4.18: Der ausdrückliche Auftrag «Bewerbung vorbereiten» darf nicht wegen einer
widersprüchlichen Firmenzuordnung oder einer vorübergehend fehlgeschlagenen Inserat-, KI- oder
Empfängerrecherche verweigert werden. Die gewählte Stelle bleibt der Zieljob. Erkennt die geprüfte
Originalausschreibung eine abweichende Arbeitgeberfirma, wird die Stelle dieser Firma zugeordnet;
die bisherige Firma und deren Daten werden nicht überschrieben. Nicht mehr zur Arbeitgeber- oder
Vermittlerfirma gehörende alte Kontakte dürfen nicht in den Empfängerblock gelangen. Scheitert eine
optionale Anreicherung, werden Bewerbung und bearbeitbare Texte mit den vorhandenen Daten erstellt.
Nur eine tatsächlich gelöschte oder nicht dem Benutzer gehörende Stelle darf den Vorgang stoppen.

Ergänzung 2.4.17: Reportresultate dürfen keine separate Aktionsspalte und keinen separaten
«Datensatz öffnen»-Button verbrauchen. Jeder nicht leere Feldinhalt muss selbst auf den fachlich
passenden individuellen Datensatz verweisen. Beziehungsfelder öffnen die jeweilige Firma,
Vermittlerfirma, Stelle, Bewerbung oder Kontaktperson; andere Felder öffnen den Basisdatensatz der
Zeile. Dieses Verhalten gilt für Tabelle, Liste, Karten, Vorschau und Kalendergruppen.

Ergänzung 2.4.16: Fehlen nach der strukturierten KI-Analyse einer Originalausschreibung
Firmenanschrift oder Recruiting-Kontakt, muss die App selbstständig eine zweite KI-Recherche im
öffentlichen Internet ausführen. Bevor ein Wert gespeichert wird, ruft die App die von der KI
zitierte HTTPS-Seite selbst ab und bestätigt den angegebenen Textbeleg. Bevorzugt werden offizielle
Unternehmens-, Karriere-, Impressums- und Registerseiten. Bestehende CRM-Werte dürfen nicht
überschrieben werden. Das Öffnen oder Vorbereiten einer bestehenden Bewerbung muss dieselbe
Ergänzung auslösen; Empfängerblöcke dürfen keine eckigen Platzhalter enthalten.

Ergänzung 2.4.15: Jede Originalausschreibung wird beim Import und unmittelbar vor der
Bewerbungsvorbereitung über die KI-API analysiert. Belegte Firmen-, Adress-, Kontakt- und Jobdaten
ergänzen leere Datenbankfelder. Ein vorhandener Kontakt muss unabhängig davon verwendet werden, ob
er als Primärkontakt, Bewerbungskontakt, Jobkontakt oder Firmenkontakt erfasst ist. Bereits vorhandene
Texte werden ebenfalls auf den vollständigen Empfängerblock geprüft und nötigenfalls korrigiert.

Ergänzung 2.4.14: Auswahlfilter geöffneter Reports müssen jeden sichtbaren fachlichen Wert
eindeutig behandeln. Unterschiedliche Anzeigen dürfen auch dann nicht gemeinsam gefiltert werden,
wenn sie intern denselben Rohwert verwenden. Datum, Zahl, Text und jede vorhandene Auswahloption
müssen einzeln und kombiniert funktionieren. Ist einer Bewerbung eine Kontaktperson zugeordnet,
muss das KI-generierte Motivationsschreiben mit Firma, Kontaktperson, Strasse und PLZ/Ort beginnen.
Die App stellt diesen Empfängerblock nach jeder KI-Antwort serverseitig sicher.

Ergänzung 2.4.13: Jeder geöffnete Report muss unabhängig von seiner gespeicherten Voreinstellung
direkt zwischen Tabellen- und Kartenansicht umschaltbar sein. Jeder Datensatz muss in Tabelle,
Karten, Liste, Vorschau und Kalendergruppen einen sicheren internen Link zum zugehörigen Original-
datensatz besitzen. Die Liste «Gespeicherte Reports» steht oberhalb des Report-Editors.
Geöffnete Reports müssen jedes angezeigte Feld passend zu dessen Datentyp filtern können: Textsuche,
Datumsbereich, Zahlenbereich oder Auswahl aus vorhandenen Werten. Mehrere Filter wirken gemeinsam und
bleiben beim Wechsel zwischen Tabelle und Karten erhalten.
Übersetzungsplatzhalter dürfen nie unverarbeitet ausgegeben werden.

Ergänzung 2.4.12: Der Filter der Firmenspalte «Links» muss Jobs, Bewerbungen und Kontakte getrennt
nach «mit Einträgen» oder «ohne Einträge» filtern können. Mehrere gewählte Kriterien gelten
gleichzeitig. Gezählt werden ausschließlich aktive, dem Benutzer gehörende Beziehungen; freie
Textsuche auf technisch nicht zugehörigen Werten ist an dieser Stelle unzulässig.

Ergänzung 2.4.11: Die gespeicherte Anzeigeart eines Reports muss die Bildschirmdarstellung tatsächlich
steuern. Tabelle, Liste, Karten und Vorschau sind für alle Datenbasen verfügbar. Tages-, Wochen- und
Monatsgruppen stehen ausschließlich für Kalenderdaten zur Wahl. Beim Wechsel der Datenbasis muss die
Auswahlliste sofort aktualisiert werden; eine ungültige Kombination fällt serverseitig auf Tabelle zurück.

Ergänzung 2.4.10: Der Job-Room-Status in Reports muss Erfassung und Resultat eindeutig gemeinsam
ausgeben. «Noch offen» allein ist unzulässig. Ohne Bewerbungsdatum oder ohne bestätigte Erfassung
muss «Noch nicht im Job-Room erfasst» erscheinen; sonst «Im Job-Room erfasst – Resultat». Die
Spaltenbezeichnungen müssen fachlich und lokalisiert sein, nicht als technische DB-Namen erscheinen.

Ergänzung 2.4.9: Ausgewählte Beziehungsfelder eines Reports müssen ihren Inhalt über jeden
eindeutig belegten Datenpfad ermitteln. Eine Stellenfirma mit Vermittlerkennzeichen erscheint als
Vermittler, sofern keine separate Vermittlerzuordnung besteht. Entsprechend werden Jobbezüge von
Dokumenten über ihre Bewerbung, Firmenbezüge von Kalendereinträgen über ihren Kontakt und fehlende
Kontaktnamen über die zugewiesene E-Mail ergänzt. Es dürfen keine unverbundenen Datensätze geraten
oder willkürlich zugeordnet werden.

Ergänzung 2.4.8: Das Feld Job-Room-Resultat darf «Noch offen» nur anzeigen, wenn die Bewerbung
nachweislich im Job-Room erfasst wurde. Bei `unknown` oder `not_recorded` muss stattdessen
«Noch nicht im Job-Room erfasst» erscheinen. Andere Bewerbungs- und Reportfelder bleiben unverändert.
Die Reihenfolge gewählter Reportspalten ist im Editor per Drag-and-drop änderbar und wird gespeichert.
Nach dem Speichern muss unmittelbar die neu geladene Reportansicht erscheinen; eine alte Ansicht darf
nicht stehen bleiben.

Ergänzung 2.4.7: «Anzeigen» eines gespeicherten Reports muss den Report selbst öffnen. Die Tabelle
muss exakt die gespeicherte Spaltenauswahl in derselben Reihenfolge und mit den dazugehörigen Werten
zeigen. Sie darf nicht auf die allgemeine Stellen-, Bewerbungs-, Firmen- oder sonstige Modultabelle
umleiten, weil deren feste Spalten nicht der Reportdefinition entsprechen.

Ergänzung 2.4.6: In Auswertungen müssen für Stellen, Bewerbungen, Firmen, Kontakte, Dokumente und
Kalender sämtliche fachlich auswertbaren Datenbankfelder als Spalten wählbar sein. Pro Report sind
höchstens zwölf Felder gleichzeitig zulässig; Browser und Server setzen diese Grenze durch. IDs und
lesbare Beziehungen dürfen ausgewertet werden. Mandantenkennungen, Soft-Delete-Felder, generierte
Eindeutigkeitsmarker und interne Dokumentpfade werden aus Sicherheitsgründen nicht angeboten. Beim
Wechsel der Datenbasis müssen Felder, Sortierung und Statusfilter unmittelbar passend aktualisiert werden.

Ergänzung 2.4.5: Die TOTP-Eingabe darf nur eine durch eine erfolgreiche Passwortprüfung ausdrücklich
eröffnete Zwei-Faktor-Challenge verifizieren. Eine bereits authentifizierte Sitzung darf weder erneut
geprüft noch wegen eines veralteten Formulars mit einer widersprüchlichen Fehlermeldung versehen werden;
veraltete Challenge-Daten werden verworfen und die Sitzung bleibt auf dem Dashboard. Fehlt ohne aktive
Sitzung die Challenge, führt der Ablauf ohne falsche Code-Fehlermeldung zur Anmeldung zurück. Ein wirklich
abgelehnter Code lässt den Benutzer unauthentifiziert und erlaubt nur innerhalb derselben offenen Challenge
einen neuen Versuch.

Ergänzung 2.4.4: Ein unveränderter lokaler Hash darf nur dann als erfolgreich synchronisiert gelten,
wenn der zugehörige JeMa-Termin im aktuell gewählten Google-Kalender tatsächlich vorhanden ist und
den passenden JeMa-Eigentumsmarker trägt. Bei Kalenderwechsel, externer Löschung oder veralteter
Zuordnung muss der Termin mit einer kollisionssicheren, reproduzierbaren ID neu erstellt werden.
Vor jedem Vollabgleich werden alle vorhandenen Bewerbungsverläufe erneut in die erlaubten
Kalendernachweise projiziert. Der Abgleich meldet erwartete und bestätigte Termine; nur Gleichheit
ohne Einzelfehler gilt als vollständig. Jede angemeldete Sitzung führt diese Vollständigkeitsprüfung
mindestens einmal aus; unvollständige Läufe werden bei der nächsten Anfrage erneut ausgeführt.

Ergänzung 2.4.3: Der direkte Google-Kalenderabgleich darf nicht von der getrennt freizugebenden
Workflow-v6-Bestandsbereinigung blockiert werden. Er verwendet unabhängig davon ausschließlich die
geprüfte Kalenderprojektion. Änderungen und Löschungen an Kalenderdaten sowie an verknüpften
Bewerbungen, Jobs, Firmen, primären Kontakten und vorhandenen Kontaktterminen müssen automatisch nachgeführt werden. Fehler eines
automatischen oder manuellen Abgleichs bleiben im Profil sichtbar, bis ein erfolgreicher Lauf sie
löscht. Der private abonnierbare ICS-Feed darf von der App nicht zwischengespeichert ausgeliefert werden.

Ergänzung 2.4.2: In der Firmenliste müssen die Verknüpfungen zu Jobs, Bewerbungen und Kontakten
jeweils vollständig und auf einer eigenen Zeile erscheinen. Zähler und Bezeichnung dürfen nicht
zwischen Zeilen auseinanderfallen. Der Job-Room-Helper zeigt ausschließlich Bewerbungen mit einem
tatsächlich gespeicherten Bewerbungsdatum (`applied_at`). Bewerbungen ohne dieses Datum erscheinen
dort vollständig nicht; weder als leere Karte noch mit einem Ersatzdatum oder Hinweis.

Ergänzung 2.4.1: Die bei einer Firma ausgewiesene Bewerbungszahl muss genau den aktiven,
nicht gelöschten Bewerbungen zu aktiven Jobs dieser Firma entsprechen. Die Rolle einer Firma als
Vermittler erzeugt keine Bewerbung bei dieser Firma. Der Firmenlink und seine Zielliste verwenden
dieselbe Definition.

Ergänzung 2.4.0: Die Admin-KI arbeitet nicht als einmaliger Textgenerator, sondern als begrenzter,
mehrstufiger Operationsagent. Nach jedem Ausführungsfehler erhält sie die konkrete Ursache, korrigiert
den Operationsplan selbst und setzt denselben Auftrag fort. Schreibende Aufträge werden erst nach einer
zusätzlichen Prüfung des neu geladenen Datenbestands als abgeschlossen gemeldet. Bezieht sich ein Auftrag
auf Profil oder Lebenslauf, müssen das aktuelle Profil, Suchpräferenzen, Sprachkenntnisse und der aktuelle
CV in die Bearbeitung einfliessen. Unvollständige Hilfsabfragen dürfen gültige Schreiboperationen nicht
mehr abbrechen.

Beim Schnellimport muss «Vorschlag erstellen» unmittelbar einen modalen Arbeitsdialog öffnen. Dieser
zeigt Quellenaufbereitung, verstrichene Zeit, den aktuellen Zähler und einen chronologischen Verlauf
pro Anzeige. Abbrechen beendet den Browserlauf und verhindert weitere Anzeigen im Stapel.

Ergänzung 2.3.7: Vor jeder Admin-KI-Operation müssen Tabellenname, Feldnamen und Referenzfelder
gegen die reale, serverseitige Allowlist normalisiert werden. Das vollständige beschreibbare Schema
wird der KI als Kontext übergeben. Recherchierte Firmenangaben wie UID, Handelsregisternummer und
Aliasse sind Identitätsmetadaten und dürfen nie als vermeintliche SQL-Spalten abgewiesen werden.
Dasselbe Normalisierungs- und Referenzverfahren gilt für alle freigegebenen Tabellen. Unbekannte
Zusatzangaben werden, sofern vorhanden, nachvollziehbar in Notizen erhalten; sie dürfen die übrige
Operation nicht abbrechen. Pflichtfelder sind bei Neuanlagen, nicht bei Teilergänzungen bestehender
Datensätze zu verlangen. Abhängige Mehrfachoperationen müssen in referenziell sinnvoller Reihenfolge
ausgeführt werden.

Ergänzung 2.3.6: Der Admin-KI-Aufruf muss die Formularzieladresse über das HTML-Attribut
ermitteln und darf sie nicht aus einer durch `name="action"` überschatteten DOM-Eigenschaft lesen.
Ohne explizites `action`-Attribut wird an die vollständige aktuelle Seitenadresse einschließlich
`?page=admin_ai` gesendet. Ein Browser-Vertragstest muss exakt diese Produktionsstruktur ohne
künstlich gesetzte Zieladresse ausführen und jeden abweichenden Request-Pfad mit HTTP 404 ablehnen.

Ergänzung 2.3.5: Die Admin-KI darf eine eingegebene Anweisung weder bei Erfolg noch bei einem
fachlichen oder technischen Fehler verlieren. Strukturierte Fehlerantworten werden im Ausgabefeld
mit ihrer konkreten Ursache angezeigt und lösen keinen Seiten-Reload aus. Eingabe, chronologisches
Ausführungsprotokoll und die letzten Kontexte werden benutzergebunden in der Datenbank gespeichert
und bleiben über PHP-/Browser-Sitzungen hinweg erhalten, bis der Admin «Gedächtnis löschen» wählt.
Ein Browser-Entwurf schützt die laufende Eingabe zusätzlich. Der einleitende Erklärungstext entfällt.

Ergänzung 2.3.4: Ein direkter Admin-Auftrag innerhalb der Plattform wird vollständig ausgeführt, auch als Recherche, Einzel-, Mehrfach- oder Massenoperation. Eine Statusfrage wie „Hast Du die Firma erfasst?“ prüft den eigenen Datenbestand und meldet konkret gefunden/nicht gefunden. Die KI-Ausgabe ist Markdown-fähig; `**Text**` wird als Fettdruck dargestellt. Eingabe und Ausgabe bleiben ohne Seitenscroll gleichzeitig sichtbar.

Ergänzung 2.3.3: Die Admin-KI führt ausdrücklich beauftragte Einzel- und Mehrfachoperationen
transaktional in allen freigegebenen Nutzer-Datentabellen aus, darunter Profil-/Suchdaten, Firmen,
Kontakte, Jobs, Bewerbungen, Dokumentmetadaten, Kalender, Logs, Tags und Berichte. Bestehende
Datensätze werden über geprüfte Abgleichfelder ergänzt; gelöschte Datensätze werden nicht reaktiviert.
Tabellen für Authentisierung, Geheimnisse und Audit sowie Löschungen und das Versenden von E-Mails
bleiben gesperrt. Kontext und Ausführungsprotokoll bleiben bis «Gedächtnis löschen» in der Admin-Sitzung.

Ergänzung 2.3.2: Nur Karten und Tabellenzeilen, die abgesagten Jobs zugeordnet sind,
erhalten die hellere Inhaltsfarbe. Alle anderen Einträge bleiben in der bisherigen
Standarddarstellung. Admins erhalten unter Konto eine eigene, plattformgebundene KI-Konsole mit
80%-Ausgabefeld und 10%-Eingabefeld; öffentliche Adress-/Kontaktrecherche ist erlaubt.

Ergänzung 2.3.0: Passwort-Rücksetzlinks werden ausschließlich über die zentrale, serverseitige
Betreiber-Mailkonfiguration versendet und niemals im anfordernden Browser angezeigt. Anmeldung,
TOTP, Reset und Registrierung sind pro Identität und IP zeitlich begrenzt. TOTP-Secrets und
Mailpasswörter werden mit einem verpflichtenden separaten App-Schlüssel verschlüsselt. Änderungen
an Passwort oder Zwei-Faktor-Konfiguration entwerten bestehende Sitzungen. SMTP und IMAP erlauben
nur öffentliche Ziele, festgelegte Ports und TLS. Uploads müssen Endungs- und MIME-Allow-List
erfüllen; Downloads verwenden sichere Dateinamen, `nosniff` und eine Sandbox. Browser-Schutzheader
und lokale Assets reduzieren XSS-, Clickjacking- und Lieferkettenrisiken. Maximal zehn aktive
Benutzer können registriert werden; der Betreiber kann die Registrierung ganz abschalten.

Ergänzung 2.2.4: Der Editor muss HTML-Quelltext beim Wechsel auf WYSIWYG unmittelbar sichtbar
übernehmen. Jede native oder programmatische Formularübermittlung muss den aktuell aktiven
Editormodus unmittelbar vor dem Erzeugen der Nutzdaten synchronisieren. Dies gilt insbesondere
für manuelles Speichern, Autosave und KI-Aktionen; ein älterer visueller Inhalt darf den offenen
HTML-Stand nie überschreiben.

Ergänzung 2.2.3: Änderungen in der HTML-Ansicht eines Mehrzeilenfelds werden beim Umschalten auf
WYSIWYG sichtbar übernommen. Solange die HTML-Ansicht aktiv ist, ist ihr Inhalt auch beim
Speichern, Autosave oder KI-Aufruf die verbindliche Quelle. Die Bereinigung erfolgt einmal beim
Synchronisieren und nicht bei jedem Tastendruck, damit unvollständige Eingaben nicht verloren gehen.

Ergänzung 2.2.2: Beim Klick auf `Texte mit KI erstellen/anpassen` müssen zuerst die sichtbaren
Rich-Text-Inhalte in die Formularfelder übernommen und ausstehende Autosaves angehalten werden.
Instruktion, aktueller Begleittext und aktuelles Motivationsschreiben werden gemeinsam als normale
Formularnavigation übermittelt. Eine ausgefüllte Instruktion ist in Begleit-E-Mail und
Motivationsschreiben sichtbar umzusetzen; unveränderte Langtexte dürfen nicht als Erfolg gelten.

Beim Erstellen einer neuen Dokumentversion werden der bestehende aktuelle Datensatz und seine
Versionsreihe explizit gewählt. Titel und Typ bleiben derselben Reihe zugeordnet; Sprache,
Beschreibung sowie Gültig-von/Gültig-bis werden in das Formular übernommen und können für die neue
Version angepasst werden. Die Versionsnummer ist stets eins höher als die höchste vorhandene
Version derselben Reihe. Dateiablage, Kennzeichnung der Altversion, neuer DB-Datensatz,
Textextraktionsstatus und Bewerbungszuordnung werden atomar behandelt; bei einem Fehler bleibt die
Altversion aktuell und die bereits abgelegte neue Datei wird entfernt.

Ergänzung 2.2.1: Eine manuell im Schnellimport eingegebene Inserat-Adresse gilt als ausdrücklicher
Importauftrag. Eine lesbare und als Stellenanzeige auswertbare Seite darf nicht allein deshalb
abgewiesen werden, weil die automatische Verfügbarkeitsprüfung keinen positiven Aktualitätsbeleg
findet. Die strengere Verfügbarkeitsprüfung der automatischen Vorschlagssuche bleibt unverändert.

Ergänzung 2.2.0: Mehrzeilige fachliche Textfelder wie E-Mail, Motivationsschreiben,
Begleitschreiben, Beschreibungen, Kommentare und Online-Notizen unterstützen sichere
HTML-Formatierung. Pro Feld ist ein Mini-Editor mit Absätzen, Fett, Kursiv, Links,
Aufzählungen, nummerierten Listen, Ein-/Ausrücken, Format löschen, HTTPS-Bildern, Tabellen,
Trennlinien und HTML-Ansicht verfügbar. Aktive oder unsichere Inhalte
werden serverseitig entfernt. Aktivitäten und Logs werden vom ältesten Eintrag oben bis zum
neuesten Eintrag unten dargestellt.

Karten und Tabellen zeigen aus diesen Inhalten ausschliesslich lesbaren Klartext mit Absätzen;
HTML-Tags dürfen dort weder sichtbar sein noch ausgeführt werden. Das Dossier rendert hingegen
die serverseitig bereinigte HTML-Formatierung.

Ergänzung 2.1.9: Das Feld «Gesendet am» muss vollständige gespeicherte Zeitstempel einschließlich Sekunden browserseitig als gültig akzeptieren und verlustfrei wieder anzeigen.

Ergänzung 2.1.8: Gelöschte Datensätze bleiben gelöscht und dürfen eine fachlich gleiche
Neuanlage in keiner Tabelle blockieren. Eindeutigkeitsregeln mit `deleted_at` gelten nur für
aktive Datensätze. Dies betrifft Benutzer-E-Mail, Firmenbeziehungen, Jobportale, externe
Stellenidentitäten und Bewerbungen pro Job. Gleichzeitige Neuanlagen derselben aktiven Bewerbung
werden atomar auf einen Datensatz zusammengeführt. Fehlermeldungen nennen Verarbeitungsschritt,
Datenwirkung und eine im Serverlog auffindbare Fehlerreferenz.

Ergänzung 2.1.6: `Bewerbung vorbereiten` verwendet nach dem Anzeigen des Arbeitsdialogs eine
normale Browser-Formularnavigation. Der Browser folgt der serverseitigen Weiterleitung direkt und
öffnet den erzeugten oder vorhandenen Bewerbungsdatensatz. Der Abbruch kann die noch laufende
Seitennavigation stoppen.

Ergänzung 2.1.5: `Bewerbung vorbereiten` öffnet nach der Verarbeitung zwingend den neu erstellten
oder bereits vorhandenen Bewerbungsdatensatz. Die asynchrone KI-Arbeitsanzeige darf nicht zur
aufrufenden Jobseite zurückführen, sofern kein fachlicher Fehler gemeldet wurde.

Ergänzung 2.1.4: Ist das KI-Instruktionsfeld leer, erstellt die KI Betreff, Begleit-E-Mail und
Motivationsschreiben vollständig neu aus den verfügbaren Bewerbungsdaten und übernimmt keine
bisherigen Texte als Vorlage. Ist das Feld ausgefüllt, überarbeitet die KI die vorhandenen Texte
gemäß der Instruktion.

Ergänzung 2.1.3: Der KI-Button für Bewerbungstexte muss auch in mobilen Browsern zuverlässig
auslösen. Das modale Fenster `In Arbeit` wird vor dem Request sichtbar. Eine ausgefüllte
KI-Instruktion wird in den betroffenen Texten erkennbar umgesetzt; bei leerem Feld verbessert die
KI Betreff, Begleit-E-Mail und Motivationsschreiben selbständig. Abbrechen beendet die
Browser-Anfrage, ohne die aktuelle Seite neu zu laden.

Ergänzung 2.1.2: Die Fusszeile nennt weiterhin KI-Hersteller und Modell, zeigt aber keine
Prozent-/Kontingentangabe. Die lokale Schätzung aus 2.1.1 ist unzuverlässig und wird mitsamt ihrer
Nutzungstelemetrie entfernt.

Ergänzung 2.1.1: Länger dauernde KI-Aktionen zeigen modal `In Arbeit` mit sichtbarer
Aktivität und Abbrechen. Die Fusszeile jeder Seite nennt OpenAI und das konfigurierte Modell.
Sie zeigt außerdem den geschätzten Rest des für JeMa Jobs konfigurierten App-Kontingents in
Prozent. Die Schätzung basiert auf den von der App seit 2.1.1 erfassten Response-Tokenzahlen und
den konfigurierten Modellpreisen; sie darf nicht als OpenAI-Abrechnungssaldo bezeichnet werden.
Diese Anforderungen beschreiben das gewollte Verhalten. Abweichungen des Bestands stehen in PROGRAMMDOKUMENTATION.md.

## Produkt und Grenzen

Privates CRM fuer Stellensuche, Bewerbungsunterlagen, Firmen, Kontakte, Bewerbungen, Termine und Auswertungen.
Keine automatische Bewerbung bei fremden Portalen, keine erfundenen Stellenlinks, kein automatischer Job-Room-Versand.
Alle Benutzerinhalte sind vertraulich. Proprietaere Lizenz, kein implizites Recht zur Weitergabe.
Der aktuelle Code bleibt Referenz fuer bestehende Datenformate, nicht fuer versehentliche historische UI-Fehler.

## Identitaet und Rollen

- Registrierung, E-Mail-Verifikation, Login, Abmeldung, Passwort-Reset und optionale TOTP-2FA.
- Passwoerter gehasht; SMTP- und Google-Secrets verschluesselt; keine Geheimnisse in Protokollen.
- Benutzer besitzen ihre eigenen CRM-Daten. Jede Abfrage und jede Mutation muss die effektive Besitzer-ID pruefen.
- Admins verwalten Benutzer, Kontostatus und Rollen. Das eigene Konto darf nicht versehentlich entmachtet werden.
- Support-Impersonation verlangt eine aktive, widerrufbare Benutzerfreigabe und einen sichtbaren Supportkontext.
- Gastfreigaben gelten nur fuer expliziten Inhalt, definierte Rechte und Laufzeit.
- Server prueft Berechtigungen und CSRF unabhaengig von versteckten oder deaktivierten Buttons.

## Profil

Name, E-Mail, Kontaktwege, Adresse, Region/Land, Zeitzone, App-/Dokumentensprache, Links und Sprachkenntnisse.
Suchwuensche: Taetigkeiten, Orte, Arbeitsmodell, Stellenarten, Pensum minimum/maximum,
Lohn und Periode, Benefits, Ausschluesse, Reiseanteil und Verfuegbarkeit.
Pensum 0 bis 100, minimum <= maximum; leere Angaben bleiben unbekannt.
Vor Ort, Hybrid und Remote sind unterscheidbar. Nur vor Ort darf nicht als Remote bezeichnet werden.
Schweizer Regionsliste enthaelt Bern Stadt, Region Biel und Region Solothurn.

## Firmen, Stellen und Kontakte

Ergänzung 2.1.0: Beim Vorbereiten einer Bewerbung werden Betreff, Begleit-E-Mail und
Motivationsschreiben in der Benutzersprache aus den verfügbaren Profil-, Stellen-, Firmen- und
Kontaktdaten sowie dem lesbaren Inhalt der aktuellen Lebenslaufversion vorausgefüllt. Fehlende Fakten werden nicht erfunden. Alle drei Texte bleiben frei
bearbeitbar. Ein zweizeiliges KI-Instruktionsfeld kann eine gemeinsame Überarbeitung auslösen;
vorhandene Benutzertexte werden nur durch diesen ausdrücklichen Auftrag ersetzt. Die Aktion
versendet keine E-Mail und reicht keine Bewerbung ein. Bei vorübergehend fehlender KI stehen
bearbeitbare Grundentwürfe bereit.

Ergänzung 2.0.15: Die Ergebnis-Zusammenfassung nutzt höchstens zwölf sichtbare Zeilen und
bis zu 2000 Zeichen in der Benutzersprache. Für eine höhere Ausbeute soll bereits die Discovery
anhand des aktuellen Tages neue, einzeln lesbare Anzeigen bevorzugen und erkennbar abgelaufene,
geschlossene, generische oder zugangsgesperrte Seiten meiden. Nach gleichmäßiger Erstprüfung wird
ungenutztes Vertiefungsbudget erneut annähernd gleich an produktive Quellen verteilt, die im
vorherigen Durchgang weitere eindeutige URLs geliefert haben. Höchstens drei Vertiefungsdurchgänge,
insgesamt weiterhin maximal 60 Anzeigenprüfungen. Mindestmatch 70, Belegpflicht, Verfügbarkeit,
Ausschlüsse und Dubletten bleiben unverändert.

Ergänzung 2.0.14: Sobald die laufende Suche mindestens einen brauchbaren Treffer gespeichert
hat, wird im offenen Statusfenster Abbrechen durch Resultate ersetzt. Die Statusanzeige bleibt
sichtbar und die Suche läuft weiter, bis Resultate gewählt wird oder ein Endzustand erreicht ist.
Kurzbeschreibungen nutzen bis zu vier Tabellenzeilen und dürfen dafür bis zu 1000 Zeichen enthalten.
Die Lohnperiode muss aus ihrem exakten Originalbeleg technisch erkennbar sein; monatliche,
jährliche und stündliche Angaben werden weder geraten noch still umgerechnet. Ein abweichender
KI-Periodencode wird anhand des eindeutigen Belegs korrigiert.

Ergänzung 2.0.13: Bei mehreren Quellen zuerst alle ausgewählten Suchmaschinen mit annähernd
gleichem Explorationsanteil prüfen. Bei 16 Quellen sind dies zwei rohe Kandidaten je Quelle.
Eine Quelle ohne lesbare Anzeige nach zwei gleichartigen Zugriffs-/Lesefehlern für diesen Lauf
abbrechen. Das verbleibende Gesamtbudget bis 60 Prüfungen gleichmäßig auf Quellen mit bereits
akzeptierten Treffern verteilen; gibt es noch keinen akzeptierten Treffer, auf Quellen mit
fachlich lesbaren Anzeigen. Original-/Arbeitgeberlinks aus Suchresultaten bevorzugen, keine
Such-, Redirect- oder Loginseiten als Anzeige akzeptieren. Zielzahl, Belegpflicht, Mindestmatch,
Ausschlüsse und Einzelsuche bleiben unverändert.

Ergänzung 2.0.11 (ersetzt die Zehnerregel aus 2.0.10): Schon ein brauchbarer Treffer macht die Suche erfolgreich. Zur Zielzahl weitersuchen, technische Einschränkungen gesondert anzeigen. Das Gesamtprüfbudget bei mehreren Quellen annähernd gleich auf alle ausgewählten Suchmaschinen verteilen. Rohe Kandidaten vor Ausschlüssen zählen; eine Einzelquelle bleibt unverändert. Keine Aussage vollständiger Ausschöpfung allein durch Quellenwechsel.

Ergänzung 2.0.9: Jeder aktive Match-Kriterienname ist im technischen Antwortvertrag fest vorgegeben und genau einmal erforderlich. Fehler in diesem Vertrag sind sichtbare technische Fehler, keine fachliche Profilablehnung. Unprüfbare Anzeigen bleiben ausgeschlossen; zusätzliche Erkennungsregeln dürfen keine unbelegte Verfügbarkeit behaupten.

Ergänzung 2.0.8: Downloadbarer Diagnosebericht zur Suche, damit tatsächliche
Profilablehnungen von Abruf-/Prüffehlern unterschieden werden können. Nur die eigene
Sitzung exportieren, keine Zugangsdaten oder vollständigen Profil-/Inserat-/Kontakttexte.
Keine automatische Übermittlung. Unbrauchbare Kandidaten bleiben außerhalb der Tabelle.

Ergänzung 2.0.7: Ausschließlich brauchbare, aktuell belegbar verfügbare und profilbezogen
geprüfte Anzeigen in der Ergebnistabelle. Abgelaufene, unlesbare, unprüfbare, unpassende,
gelöschte und doppelte Kandidaten ausschließen und die Suche fortsetzen. Suchmaschinen-
Snippets und frei erfundene KI-Prozente sind keine Nachweise. Originalanzeigen mit Drill-down
lesen, Suchprofil anhand von Textbelegen vergleichen und Match neu berechnen. Beide URL-
Importwege recherchieren belegte Firmen-, Adress- und Recruiting-Kontaktdaten auf der
verlinkten Firmenwebsite. Vorhandene eigene Daten ergänzen, sonst neu anlegen; gefüllte
Felder nicht still überschreiben. Konflikte nicht automatisch zusammenlegen.

Ergänzung 2.0.6: Ein einzelner URL-Schnellimport und Mehrfachimport müssen denselben
vollständigen Speicherweg nutzen. Vorhandene Kontakte werden bei gleicher Zuordnung
um belegte leere Felder ergänzt; eine alte Vorschau darf den importierten Job nicht verdecken.
Suchtreffer absteigend nach Match-Prozent. Übernehmen braucht eine modale Statusanzeige
mit Fortschritt und Abbrechen während der langen Lesephase; keine erfundenen Prozentwerte.

Ergänzung 04.09.2026 für 2.0.5: Erkannte Links zur Originalausschreibung verfolgen,
deren belegte Arbeitgeber- und Kontaktangaben übernehmen und fehlende Firmenadresse auf
der explizit verlinkten Arbeitgeberwebsite prüfen. Keine Adressen erfinden und keine
nachgebaute Tabelle als Original-PDF ausgeben. Ohne verfügbaren Browser-Host entfällt
die automatische originalgetreue PDF-/PNG-Erstellung; kostenpflichtige Render-Dienste sind ausgeschlossen.

- Firma: Name, Adresse, Website, Telefon, Region/Land, Kommentar, optionale Vermittlerrolle und Beziehungen.
- Stelle: Firma, vollstaendiger mehrzeiliger Titel, Ort, Pensum von/bis, Arbeitsmodell,
  Stellenart, Vertragsdauer und Befristung, Lohn, Quell-URL, Beschreibung, Notizen, Fragen und Status.
- Schnellimport verarbeitet URLs oder Text zu pruefbaren Vorschlaegen. Originalinhalte und Quelle bleiben nachvollziehbar.
- Dublettenpruefung ist benutzerbezogen; kein Zusammenlegen unterschiedlicher Stellen allein aufgrund gleicher Firma.
- Ein Kontakt gehoert zur Firma, optional zur Stelle/Bewerbung; Sortierung primaer Nachname.
- Kontakt-Log: Kanal, Richtung, Zeitpunkt, Status, Betreff, Text, Ergebnis und Anhaenge.
- Kontaktaktivitaeten sind weder automatisch ein Versand noch automatisch ein Nachfasstermin.
- Eintragszahlen muessen sagen, was gezaehlt wird. Offen/geplant ist Teil der Kontakt-Log-Gesamtzahl.
- Firmenname ist als Firmenlink nutzbar; technische Zuordnungswoerter nicht als unklare Information darstellen.

## Bewerbung und Kalender

Der ausfuehrliche Vertrag steht in WORKFLOW.md.
Status: Entwurf, Bereit, Gesendet, Bewerbungsgespraeche, Zusage, Absage.
Mehrere Gespraeche erzeugen mehrere Termine, nicht mehrere Statuscodes.
Ein echter Statuswechsel schreibt einen Zeitstempel; blosses Speichern erzeugt keinen Statuswechsel.
Entwurf/Bereit behaupten keinen Versand; ein erfolgreicher Versand hat einen echten, stabilen Zeitpunkt.
Kein zusaetzlicher Kontakt-Log-Eintrag allein zur Verdoppelung des Versandnachweises.
Keine Kalendertermine aus Entwurf, Bereit, Bewerbung senden, Antwort pendent oder Vorbereitung.
Nachfassen muss explizit terminiert werden. Versand und Ergebnisse sind kurze transparente Nachweise ohne Alarm.
Heute ist in Monats-, Arbeitswochen- und Wochenansicht farblich markiert, unabhaengig vom gewaelten Datum.
Job-Room-Erfassung ist ein eigener boolescher Sachverhalt; Ergebnisfelder erscheinen nur bei aktiver Erfassung.
Historische unklare Status und Daten nicht blind umdeuten oder loeschen.

## Dokumente, Kommunikation und Integrationen

Versionierte Stammdokumente und bewerbungsspezifische Dateien, jeweils Typ, Titel und tatsaechliche Dokumentsprache.
Dokumentzuordnungen mit Zweck/Reihenfolge, Einzel-Download, ZIP und zeitlich begrenzter Dateibereitstellung.
Vor E-Mail-Versand Empfaenger, Text und ALLE Anhaenge pruefen. Fehlende zugeordnete Datei ist ein Fehler.
Online-Einreichung findet im fremden Portal statt; JeMa protokolliert erst die bestaetigte Einreichung.
Motivationsschreiben-Prompt enthaelt kopierbaren Empfaengerblock: Firma / Kontakt / Strasse Nr. / PLZ Ort.
SMTP-Einstellungen benutzerbezogen; IMAP-Ablage gesendeter Nachrichten ist gesondert konfigurierbar.
ICS-Export, privater ICS-Feed und Google-Abgleich sind verschiedene Funktionen.
Fremde Kalenderdaten nicht bei der Bereinigung automatisch erzeugter JeMa-Daten veraendern.

## Darstellung und Hilfe

Eine Tabelle hat eine Zeile pro Datensatz und echte Spalten, keine eingebauten Detailkarten.
Inhalte duerfen innerhalb der Zellen umbrechen; Name, Titel, Adresse und Befehle nicht abschneiden.
Keine zwecklosen Auswahlspalten. Fachliche Checkboxen (Admin, Job-Room, Dokumentzuordnung) bleiben.
Befehle als erkennbare Buttons; Links fuer Datensatznavigation, URL und E-Mail.
Kompakte Koepfe mit Titel, Anzahl und zugehoerigen Aktionen; keine frei schwebenden Bedienfelder.
UI in de-CH, fr-CH, en-GB, pt-BR, es-MX; Sprachwechsel darf keine Benutzerdaten uebersetzen.
Hilfe und Kontext-Popups verwenden dieselben ueberprueften Inhalte in allen fuenf Sprachen.
Suchen muss auch Informationen in Schritten und Hinweisen finden, nicht nur Ueberschriften.

## Nachweis der Fertigstellung

Lokale Tests und dokumentierter Quellstand; kontrolliertes Deployment; Server-Hash;
angemeldete Abnahme der betroffenen Masken in allen erforderlichen Sprach-/Ansichtskombinationen.
Offene Datenmigration und nicht ausgefuehrte Tests ausdruecklich als offen benennen.
