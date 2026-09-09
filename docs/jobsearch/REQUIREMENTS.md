# Anforderungen

Stand: 09.09.2026. Zielbeschreibung mit Ergänzungen bis 2.3.7.

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
