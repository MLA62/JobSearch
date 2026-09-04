# JeMa Jobs

Stand: 04.09.2026. Version 2.0.14 mit früher Ergebnisnavigation, ausführlicherer Kurzbeschreibung und beleggeprüfter Lohnperiode ist für das Deployment vorbereitet. Bestätigter Live-Stand ist 2.0.13.
Aktueller Nachweis: [Release 2.0.14](docs/jobsearch/RELEASE-2.0.14.md). Vorheriger bestätigter Live-Stand: [2.0.13](docs/jobsearch/RELEASE-2.0.13.md).
Deployment, Live-Prüfung und ein neuer angemeldeter produktiver Suchlauf bleiben bis zur externen Freigabe offen; historische Abnahmen gelten nicht automatisch für diese Änderung.

Privates, mandantengetrenntes Bewerbungs-CRM fuer https://jobs.jema.business.
Der Ablauf lautet **Entwurf -> Bereit -> Gesendet -> Bewerbungsgespraeche -> Zusage oder Absage**.
Nachfassen und mehrere Gespraeche sind eigenstaendige datierte Termine.
Es gibt keinen separaten Aufgaben-/Pendenzenbereich.

## Dokumentationsweg

1. [Anforderungen](docs/jobsearch/REQUIREMENTS.md): verbindliches Produktverhalten.
2. [Programmdokumentation](docs/jobsearch/PROGRAMMDOKUMENTATION.md): Module, Felder, Berechtigungen, Integrationen.
3. [Workflow](docs/jobsearch/WORKFLOW.md): Status, Zeitstempel, Kalender und Legacy-Migration.
4. [Neuaufbau](docs/jobsearch/REBUILD.md): Umsetzungsschritte und Abnahmekriterien.
5. [Datenbankreferenz](docs/jobsearch/DATA_MODEL.md): vollstaendige SQL-Dateien und Runtime-Erweiterungen.
6. [Schnittstellen](docs/jobsearch/INTERFACES.md): Seiten, POST-Aktionen, Formulare und Funktionen.
7. [Sprachkonzept](docs/jobsearch/DB_I18N_CONCEPT.md): Laufzeitauflosung und Hilfekatalog.
8. [Deployment](docs/jobsearch/DEPLOYMENT.md), [Tests](docs/jobsearch/TESTING.md) und [Audit](docs/jobsearch/DOCUMENTATION_AUDIT.md).
9. [Produktentscheidungen](docs/jobsearch/PRODUCT_DECISIONS.md), [Release 2.0.3](docs/jobsearch/releases/2.0.3.md).

[Deutsche Hilfe](docs/jobsearch/help/de-CH.md), [fr-CH](docs/jobsearch/help/fr-CH.md),
[en-GB](docs/jobsearch/help/en-GB.md), [pt-BR](docs/jobsearch/help/pt-BR.md),
[es-MX](docs/jobsearch/help/es-MX.md).

## Technischer Aufbau

- PHP 8.1+, MariaDB-kompatible SQL-Syntax, utf8mb4, HTTPS.
- Front Controller: public/index.php; keine Composer-Abhaengigkeit im Kern.
- Styles: public/assets/app.css und layout.css; Verhalten: layout.js sowie Inline-JavaScript.
- Konfiguration: public/config.php, nicht versioniert; Muster: public/config.example.php.
- Dateien: public/storage/documents und temporaere Bewerbungspakete; Zugriff ausschliesslich autorisiert.
- UI-Texte: ui_text_keys und ui_text_translations; lokale Seeds und Hilfe werden in diese Tabellen eingespielt.
- Hilfetextquelle: docs/jobsearch/help/source.json. Generierte Hilfetexte niemals separat bearbeiten.
- SQL-Basisschema plus bedingte Runtime-Erweiterungen. Historische ALTER-Dateien sind keine lineare Neuinstallationsliste.

## Arbeiten am Projekt

Lies AGENTS.md. Bestehende Benutzerdateien und nicht zugehoerige Aenderungen nicht ueberschreiben.
Vor Auslieferung PHP-Lint, fachliche Regressionen, Sprach- und Browserpruefungen ausfuehren.
Generierte Inhalte mit php -n tools/build_help.php und php -n tools/build_reference.php aktualisieren;
mit --check auf Uebereinstimmung pruefen. Details in TESTING.md.

Produktive Aenderungen ausschliesslich ueber cPanel Mail Control:
Proposal, externe Freigabe, Ausfuehrung, Hashvergleich und angemeldete Live-Pruefung.
Ein erfolgreiches Git-Push ist kein Deployment. CSS/JS werden mit unveraenderlichem Commit referenziert.
Keine Zugangsdaten, produktiven Datensaetze oder Datenbankdumps in Git.

## Lizenz und Grenzen

Proprietaere Software; [LICENSE.md](LICENSE.md) gilt unveraendert.
Historische Versionsberichte dokumentieren damalige Aussagen, nicht den heutigen Funktionsumfang.
Offene technische Unterschiede zum Zielverhalten sind in PROGRAMMDOKUMENTATION.md aufgefuehrt.
