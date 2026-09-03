# Produktentscheidungen

Stand: 03.09.2026, Zielrelease 1.18.1. Verbindliche Entscheidungen vor historischen Release-Notizen.

| Bereich | Entscheidung | Grund |
|---|---|---|
| Workflow | Sechs Status, Bewerbungsgespraeche im Plural | Mehrere Gespraeche sind normale Wiederholungen |
| Termine | Ein Kalender, ausdrueckliches Datum/Uhrzeit | Kein paralleler Pendenzenprozess |
| Vorbereitung | Naechste Aufgabe darf angezeigt werden, erzeugt keinen Termin | Vorbereitung ist kein Versand |
| Datum | Workflowdatum = juengstes relevantes Workflowdatum; Anzeige nur Datum | Einheitliche Listen/Karten/Exporte |
| Versand | Nur echter Versand-/Einreichungsnachweis, Zeitpunkt erhalten | Keine irrefuehrende Behauptung bei Bereit |
| Verlauf | Statuswechsel protokollieren, Autosave nicht als Wechsel | Nachvollziehbarkeit ohne Dubletten |
| Job-Room | Erfassung bewusst bestaetigen, Ergebnis separat | Ergebnis beweist keine Portal-Erfassung |
| Legacy | Unklare Werte erhalten; Vorschau und Sicherung vor Bereinigung | Schutz echter Benutzerdaten |
| Kontakte | Aktivitaetszaehler und verknuepfte Stellen trennen | 2 Eintraege bedeutet nicht 2 Bewerbungen |
| Tabelle | Echte Zeilen/Spalten, Umbruch statt Abschneiden | Lesbarkeit bei wenig Platz |
| Auswahl | Keine automatisch eingefuegten Auswahlkaestchen | Keine ungenutzte Spalte |
| Darstellung | Orange fuer Aktionen, Gruen fuer heutigen Kalendertag | Orientierung ohne Statusverwechslung |
| Sprachdaten | Stabile Keys, DB-Aufloesung, versionierte Hilfeseeds | Reproduzierbare Inhalte in fuenf Sprachen |
| Hilfe | Themen anhand stabiler IDs statt Stichwort-Zufall | Passende Kontext-Hilfe pro Seite |
| Deployment | cPanel-Proposal und externe Freigabe | Keine Umgehung produktiver Kontrollen |
| Assets | Unveraenderliche Commit-URLs | Kein Mix aus alten und neuen Layouts |
| Dokumentation | Historie bewahren und als historisch markieren | Alte Testergebnisse nicht als neue Nachweise ausgeben |

## Nicht genehmigte Automatismen

Kein Versand aus Speichern, kein Nachfassdatum aus Versandzeitpunkt, kein Interviewdatum nur aus Status.
Kein automatisches Zusammenlegen verschiedener Bewerbungen derselben Firma.
Keine Supportfreigabe allein durch Adminrolle. Keine oeffentlichen Dateipfade fuer private Dokumente.
Kein Wiederherstellen ganzer Datenbankstaende ueber neuere Benutzerdaten.

## Aenderungsdisziplin

Ein Request kann Quellcode und Dokumentation betreffen, aber eine Freigabe gilt fuer den
exakten Deployment-Inhalt. Ausstehende Datenbereinigung nicht in ein Layout-Deployment einschleusen.
Alle Angaben zur Umsetzung muessen zwischen lokal, freigegeben, deployed und live geprueft unterscheiden.
