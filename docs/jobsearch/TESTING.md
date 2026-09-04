# Pruefplan und Nachweise

Stand: 2026-09-03. Aktuelle Ergebnisse: [DOCUMENTATION_AUDIT.md](DOCUMENTATION_AUDIT.md).
Ein gruenes Fixture ist kein Beleg fuer eine ausgefuehrte Produktionsmigration.

## Automatisierte Basis

Aus dem Repository-Stamm:

```powershell
php -n -l public/index.php
php -n -l public/config.example.php
php -n tools/build_help.php --check
php -n tools/build_reference.php --check
Get-ChildItem tests -Filter '*_test.php' | ForEach-Object {
    & php -n $_.FullName
    if ($LASTEXITCODE -ne 0) { throw $_.Name }
}
git diff --check
```

`-n` deaktiviert php.ini; Tests mit simuliertem mysqli brauchen diese Isolation. Der normale Webbetrieb braucht dagegen die echten Erweiterungen.

| Testfamilie | Vertrag |
| --- | --- |
| help_content | 24 Themen, fuenf Sprachfassungen, vollstaendige Keys/Platzhalter, echte tr-/DB-Aufloesung gegen synthetische DB-Zeilen, identische Kontexttexte und gueltige Themenverweise |
| help_seed | Begrenzte Schreibziele, vollstaendige Uebersetzungen, Transaktion, Wiederholung, Fehler-Rollback, Sperre, Wiederanlauf; simulierter DB-Adapter |
| documentation | Alle Markdown-Verweise lokal aufloesbar, historische Kennzeichnung, keine Platzhalter fuer noch fehlende Aufbaudokumente |
| workflow_release / workflow_v6 | Statuscodes, Versandzeit, Kalenderprojektion, neue/alte Werte, Workflowdatum und konsistente Exporte |
| workflow_migration / workflow_migration_v6 | Vorschau, Sicherungen, Transaktion, Sperren, stale-preview und Fehlerfall; keine echte MariaDB |
| calendar_today | Aktueller Tag in Monat/Arbeitswoche/Woche, keine Markierung ausserhalb des Bereichs |
| job_date / region_choices | Datumssortierung/-filter und Schweizer Regionsauswahl |
| profile_i18n / totp | Sprachkeys und Authentisierungsregressionen, keine echten Geheimnisse |
| contact_create_form / table_layout / pdf_table | Leere Kontaktliste, echte Tabelle, Textumbruch und PDF-Seitenwechsel |

## Browser-Fixtures

### Jobsuche 2.0.4

- `php -n tests/job_content_language_test.php`: echte Parser-/Locale-/Übersetzungs-Merge-Helfer; fünf Sprachen, französischer Originaltext, Absätze, UTF-8, kein SEO-Ersatz, Firmenadresse getrennt vom Arbeitsort, Kontaktzuordnung.
- `php -n tests/job_import_storage_test.php`: 40 Prüfungen mit simulierter Datenbank; Parameterbindung, Eigentümergrenzen, Firmenfelder erhalten/ergänzen und Kontakt ohne Duplikat beim wiederholten Import.
- Live zusätzlich prüfen: tatsächliche Modellantwort auf de-CH/fr-CH, Wechsel der App-Sprache mit bestehenden Treffern, vollständiger Inserattext im Jobeditor und Firmen-/Kontaktfelder nach Übernahme. Synthetische Fixtures ersetzen diese Prüfung nicht.


Lokalen Server in einem eigenen Terminal starten:

```powershell
php -S 127.0.0.1:8128 -t .
```

Node mit installiertem Playwright und Chromium verwenden. Falls das Modul nicht im normalen Suchpfad liegt, `PLAYWRIGHT_MODULE` auf den lokalen Modulpfad setzen; nicht im Repo fest verdrahten.

```powershell
$env:JEMA_TEST_URL='http://127.0.0.1:8128'
node tests/help_visual_test.cjs
node tests/workflow_v6_visual_test.cjs
node tests/company_address_visual_test.cjs
```

Die Hilfepruefung rendert das tatsaechliche PHP-Template mit synthetischen Text-DB-Zeilen. Sie prueft alle 24 Themen in allen fuenf Sprachen bei 390, 1366 und 2560 Pixeln sowie alle 27 zugeordneten Kontexte je Sprache bei 390 Pixeln. Geprueft werden Inhalte, Links, Suchtreffer/Leerzustand/Reset, Kategorieauswahl, Dialog/Escape/Fokus, Browserfehler und Abschneiden.

Bilder liegen standardmaessig im temporaeren `jema-help`-Ordner; `JEMA_TEST_OUTPUT` kann den Pfad aendern. Bilder tatsaechlich ansehen, nicht nur erzeugen. Nach den Pruefungen den Fixture-Server beenden.

## Fachliche Abnahme mit Testkonten

1. Zwei normale Konten anlegen; Listen, direkte IDs, Downloads und Beziehungen duerfen keine fremden Daten zeigen.
2. Admin ohne Supportfreigabe darf kein Konto betreten. Nach bewusster Freigabe Support betreten, widerrufen/beenden und erneuten Zugriff verweigern.
3. Profil/App-Sprache in jeder Locale setzen, Seite wechseln und erneut anmelden; Dokument-/Kontakt-/Inhaltssprache bleibt unabhaengig.
4. Firmen, Kontakte, Jobs und Dokumente erfassen, aendern und verknuepfen. Lange Namen, Titel, Adressen und Aktionsbuttons auf Desktop/Telefon kontrollieren.
5. Pensum 50 bis 80 speichern; invalides von > bis und Werte ausserhalb 0..100 ablehnen.
6. Entwurf und Bereit ohne Versanddatum und ohne Kalenderaufgabe zeigen. Erst tatsaechlichen Versand/Einreichung bestaetigen.
7. Wiederholtes Speichern/Autosave und konkurrierende Einreichung erzeugen keine doppelten Statusnachweise. SMTP nur an ein autorisiertes Testpostfach.
8. Zwei Gespraeche und einen eigenen Nachfasstermin erfassen. Einzeltermine verschieben/abschliessen; keine anderen Termine zusammenlegen.
9. Zusage/Absage dokumentieren; Workflowdatum in Tabelle/Karte/PDF/CSV vergleichen. Kein irrefuehrender naechster Kalendereintrag.
10. Job-Room-Ankreuzfeld getrennt testen; abhaengige Felder aus-/einblenden, gespeicherte Werte erhalten.
11. Kalender heute/andere Woche/anderer Monat und Zeitzonen-/Sommerzeitgrenzen pruefen. ICS-Nachweise kurz, transparent, ohne Alarm; explizite Termine mit korrekter Dauer.
12. Neue Kontaktlog-Eintraege in zwei Bewerbungen derselben Firma pruefen; keine Aktivitaeten falsch duplizieren.
13. Privaten Dokumentdownload, Versionen und ZIP kontrollieren; direkten storage-Webzugriff verweigern.
14. Freigabe auf gueltig/abgelaufen/widerrufen und vorgesehene Inhalte testen. Keine anonymen Fremddaten.
15. Alle 24 zentralen Hilfethemen und Kontextfenster mit echter DB in fuenf Sprachen pruefen; kein deutscher Fallback oder Raw-Key darf fehlende Texte kaschieren.

## Migration und externe Systeme

Workflow-v6-Migration nur an einer gesicherten Testkopie mit expliziter Vorschau testen. Konflikt zwischen Vorschau und Ausfuehrung, Fehler nach Sicherung, Wiederholung und unbekannte Legacywerte sind Pflichtfaelle.

Google mit einem Testkalender pruefen: Marker fehlt/gesetzt, neu/erneut synchronisieren, Konflikt/ETag, Fremdeintrag und Termine mit Teilnehmern schuetzen. Keine reale Nachricht oder externe Kalenderaenderung als blosse UI-Probe ausloesen.

## Abnahmegrenzen

Noch erforderlich fuer einen vollstaendigen Neuaufbau-Nachweis: leere MariaDB, echter UI-Katalogimport, private Storage-Rechte im Zielwebserver und Restore mit passendem app_key. Lokale Fixtures ersetzen weder diesen Installationslauf noch die angemeldete Livepruefung nach Deployment.
