# Neuaufbau und Wiederherstellung

Stand: 2026-09-03, Spezifikation 1.18.1. Dieses Dokument ist eine implementierbare Aufbaufolge, kein Beleg eines bereits getesteten Neuaufbaus.
Fachliche Vollstaendigkeit: [REQUIREMENTS.md](REQUIREMENTS.md), technische Details: [PROGRAMMDOKUMENTATION.md](PROGRAMMDOKUMENTATION.md).

## Zwei verschiedene Aufgaben

**Neuimplementierung:** Verhalten, Masken, Felder, Rechte, Workflow und Tests aus dieser Dokumentation nachbauen. Es werden keine produktiven Datensaetze benoetigt.

**Wiederherstellung der laufenden Installation:** Zusaetzlich benoetigt werden der gepruefte Git-Stand, konsistente DB-/Dateisicherungen, UI-Textdaten, Konfiguration und externe Zugangsberechtigungen. Markdown ersetzt weder diese Sicherungen noch Geheimnisse.

## Benoetigte Artefakte

| Bestandteil | Quelle / Bedeutung |
| --- | --- |
| Anforderungen und Entscheidungen | REQUIREMENTS.md, PRODUCT_DECISIONS.md, WORKFLOW.md |
| Tabellen, Felder, Indizes, Migrationen | DATA_MODEL.md, originale sql/jobsearch-Dateien und Runtime-Erweiterungen |
| Masken, Aktionen und Fachfunktionen | PROGRAMMDOKUMENTATION.md, INTERFACES.md, public/index.php |
| Oberflaeche und Browserverhalten | public/assets, gepinnte Produktions-Assetrevisionen |
| Hilfe in fuenf Sprachen | help/source.json, build_help.php, generierte help/*.md |
| Uebrige genehmigte UI-Texte | Bereinigter Export aus ui_text_keys/ui_text_translations; keine Benutzerdaten mitexportieren |
| Konfigurationsstruktur | public/config.example.php; echte Werte nur im Geheimnisspeicher/Zielsystem |
| Private Inhalte | Verschluesselte, zugriffsgeschuetzte DB- und storage-Sicherung |
| Pruefungen | TESTING.md und tests/ |

## Aufbaufolge fuer Entwickler

1. Datenmodell mit Benutzerbesitz und stabilen Fremdschluesseln implementieren. Nicht mit HTML-Masken ohne Eigentumsmodell beginnen.
2. Anmeldung, Sitzungen, CSRF, Passwort/TOTP, Rechte, Supportfreigaben und Dateizugriff implementieren.
3. DB-Uebersetzungen und alle fuenf Locales bereitstellen; stabile Codes von Anzeigetexten trennen.
4. Profil, Firmen, Kontakte, Dokumente und Jobs samt Validierung/Versionierung aufbauen.
5. Bewerbung als eigenen Vorgang, Statushistorie und gemeinsamen Workflowdatum-Selektor implementieren.
6. Explizite Termine, mehrere Gespraeche, kurze Nachweise, Kalenderansichten und Heute-Markierung implementieren.
7. Onlineeinreichung, bewussten SMTP-Versand, Kontaktprotokollierung, Dossier und Empfaengerprompt anbinden.
8. Job-Room, Reports, Gastfreigaben, Audit und Verwaltungsfunktionen ergaenzen.
9. ICS und Google erst nach gesonderten Integrationstests mit Testkalendern aktivieren.
10. Tabellen/Karten/PDF aus denselben fachlichen Selektoren erzeugen; Layout- und Sprachmatrix aus TESTING.md abnehmen.

## Leere Umgebung mit vorhandenem PHP-Code

1. Isolierten Webroot mit PHP 8.1+ und MariaDB-Zielumgebung bereitstellen. Lokales PHP braucht mysqli, mbstring, openssl und die im jeweiligen Integrationspfad benoetigten Erweiterungen. ZIP/HTTP/PDF-Werkzeuge gesondert pruefen.
2. Leere DB mit utf8mb4 und dediziertem Benutzer anlegen. Keine Produktions-DB fuer Installationsversuche verwenden.
3. `01_schema.sql` als Basis importieren. Historische ALTER-Dateien koennen bereits in der Basis enthalten sein; nicht blind alle Dateien durchnummeriert ausfuehren.
4. `DATA_MODEL.md` gegen Runtime-CREATE/ensureColumn/ensureIndex und die Zusatzschema-Dateien abgleichen. Alle erforderlichen Tabellen und Enums in der Test-DB pruefen.
5. `02_views.sql` nur als Legacy-Kompatibilitaet behandeln. Insbesondere `v_calendar_items` nicht fuer den neuen Kalender verwenden.
6. Beispielkonfiguration als serverlokale `config.php` mit neuen Secrets einrichten. SMTP/Google bleiben aus, bis kontrolliert getestet.
7. `public/` bereitstellen; storage mit Schreibrechten fuer PHP und direkter Webzugriffssperre versehen. Das wirksame `app_key` dauerhaft sicher sichern.
8. Einen bereinigten genehmigten UI-Katalog einspielen. Bootstrap ergaenzt vorhandene Versions-/Hilfeseeds, ersetzt aber nicht garantiert den gesamten historischen UI-Bestand.
9. Einen echten Betreiber ueber Registrierung bzw. geprueften Installer anlegen und die vorgesehene Adminrolle zuordnen. Es gibt keinen allgemeinen `seed-admin.php`-Schritt in diesem Stand.
10. Ersten Request/Logs kontrollieren, Schema-Diff gegen die Referenz erstellen. Datenmigration und Datenverlust sind keine akzeptablen stillen Nebenwirkungen.
11. Mit synthetischen Konten die komplette Testmatrix durchgehen. Erst danach personenbezogene Daten uebernehmen.
12. Externe Integrationen einzeln aktivieren und deren Aussenwirkungen bestaetigen.

Die Runtime fuehrt additive DDL aus; dafuer braucht der DB-Benutzer entsprechende Rechte. Fuer eine neue Architektur sollten Schemaaenderungen in explizite, versionierte Migrationen vor dem Webbetrieb ueberfuehrt werden.

## Wiederherstellung aus Sicherung

- Datenbank und Dateispeicher aus demselben konsistenten Sicherungsfenster wiederherstellen.
- Passenden Code-/Assetstand und UI-Textkatalog verwenden.
- Originalen `app_key` beibehalten; sonst sind verschluesselte Werte nicht mehr lesbar.
- Vor Freigabe Dateizuordnungen, Versionen, Besitzgrenzen und Zeitstempel pruefen.
- Systemmail, SMTP und Google waehrend Restore deaktiviert halten, um keine Nachrichten doppelt zu senden oder Kalender unbeabsichtigt abzugleichen.
- Externe OAuth-/Feed-/Freigabelinks auf Gueltigkeit und gewollte Reichweite pruefen.
- Bestand nicht durch erneute Startmigrationen oder eine vollstaendige DB-Ruecksetzung nach einem Teilfehler ueberschreiben.

## Abnahme

Pflichtfaelle stehen in TESTING.md. Mindestziel: privater Besitznachweis mit zwei Benutzern, Admin ohne/mit Supportfreigabe, alle sechs Workflowcodes, mehrere Termine, Draft ohne Versanddatum, konsistente Listen/Karten/PDF, alle Hilfethemen und Sprachewechsel, Upload/Download-Autorisierung und gezielte Ruecknahme.

Offen bleibt ein realer Neuinstallationslauf mit MariaDB und ein vollstaendiger Restore-Test. Diese muessen vor dem Anspruch einer reproduzierbar identischen Produktionsinstallation nachgewiesen werden.
