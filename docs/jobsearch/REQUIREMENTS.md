# Anforderungen

Stand: 03.09.2026. Zielbeschreibung fuer 1.18.1 auf Basis des produktiven Standes 1.18.0.
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
