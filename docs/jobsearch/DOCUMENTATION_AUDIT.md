# Dokumentationsaudit

Stand: 2026-09-04. Version 2.1.5 ist dokumentiert und zur Bereitstellung vorbereitet.
Version 2.1.4 ist der bestätigte Live-Stand; frühere Nachweise sind historische Belege.

## Umfang

Alle Markdown-Dateien dieses Release-Repositories wurden eingeordnet und gegen den aktuellen Code-/Produktvertrag geprueft. Fremde Repositories und persoenliche Notizen ausserhalb von JeMa Jobs sind nicht Teil des Auftrags.

| Datei(en) | Ergebnis dieser Runde |
| --- | --- |
| README.md | Aktueller Einstieg, Versions-/Produktionsgrenze, Aufbaupfade und Pruefbefehle |
| AGENTS.md | Dokumentationspflege, Generatoren und Freigabegrenzen ergaenzt |
| LICENSE.md | Gelesen, unveraendert; keine Rechte oder Lizenzbedingungen neu formuliert |
| REQUIREMENTS.md | Fachliche Anforderungen, Rollen, Masken, Workflow und Layout aktualisiert |
| PRODUCT_DECISIONS.md | Verbindliche aktuelle Entscheidungen und verworfene Altlogik |
| WORKFLOW.md | Status, Datum, Kalender, Kontakte, Versand und Migration konsolidiert |
| DEPLOYMENT.md | Veraltete FTPS-/unsichere TLS-Anweisungen ersetzt; externer cPanel-Freigabeweg und Datenwirkungen |
| DB_I18N_CONCEPT.md | Tatsaechliche DB-Laufzeit/Seeds, Sprachewechsel und gemeinsame Hilfequelle |
| PROGRAMMDOKUMENTATION.md | Neu: Architektur, Module, Relationen, Validierungen, Integrationen, bekannte Grenzen |
| REBUILD.md | Neu: Neuimplementierung, leere Umgebung, benoetigte Artefakte und Wiederherstellung |
| DATA_MODEL.md | Neu: Generierte SQL-/Runtime-DDL-Referenz mit ausdruecklicher Legacy-Abgrenzung |
| INTERFACES.md | Neu: Generiertes Seiten-, Aktions-, Formularfeld- und Funktionsinventar |
| TESTING.md | Neu: Reproduzierbare Tests, Browsermatrix, Fachabnahme und verbleibende Grenzen |
| help/de-CH.md, fr-CH.md, en-GB.md, pt-BR.md, es-MX.md | Neu: 24 Themen je Sprache, identisch zur Quelle fuer die Anwendungshilfe |
| I18N_SUMMARY_1.15.8.md | Als historisch markiert, neue Referenzen; alte Ergebnisbehauptungen nicht wiederverwendet |
| I18N_TEST_RESULTS_1.15.8.md | Als historisch markiert; keine neue Testabnahme vorgetaeuscht |
| I18N_USE_CASES_1.15.8.md | Historische 160 Faelle erhalten; aktueller Ersatz in TESTING.md |
| I18N_LINE_AUDIT_1.15.8.md | Historisches Zeileninventar erhalten; alte Dateipfade nicht als aktueller Code behauptet |
| RELEASE-1.18.0.md | Historischer Releasebeleg erhalten und eingeordnet; neuere Produktionsbasis siehe DEPLOYMENT.md |
| releases/1.16.2.md, 1.16.3.md, 1.17.0.md, 1.17.1.md | Historische Belege erhalten, Archivhinweis und aktuelle Referenzen ergaenzt |
| releases/1.18.1.md | Releaseumfang, ausgefuehrte Freigabe, Server-Hash und Livepruefung dokumentiert |
| DOCUMENTATION_AUDIT.md | Diese nachvollziehbare Inventur |

## Hilfeabdeckung

24 eigenstaendige Themen, 120 Sprachfassungen:
Ueberblick, Profil, Sicherheit, Dokumente, Stellensuche, Jobs, Firmen, Kontakte,
Bewerbungen, Onlinebewerbung, E-Mail/Motivationsschreiben, Kalender,
Kalenderanbindung, Job-Room, Reports, Dossier, Freigaben, Datenschutz,
Benutzerverwaltung, Plattformverwaltung, Datensatzuebersetzung, Audit,
Workflowbereinigung und Hilfe/Lizenz.

Alle 27 interaktiven Seitenzuordnungen teilen Inhalte mit dem passenden zentralen Thema. Technische Export-/Callback-Endpunkte besitzen keine eigene kuenstliche Maske. Sicherheitsthemen sind zentral verfuegbar; die Glühbirne wird im aktuellen Layout nur bei angemeldeten Benutzern gezeigt.

Die Themen wurden fachlich mit den jeweiligen Formularen und Handlern abgeglichen. Ein korrigiertes Beispiel: Datenschutz erstellt eine Bereinigungsanfrage, keine direkte Loeschbestaetigung. Hilfeverweise auf Pendenzen und automatisches Nachfassen sind entfernt.

## Pruefstand 2.1.5

- Die asynchrone Bewerbungsvorbereitung liefert ein explizites Navigationsziel auf den erzeugten
  oder vorhandenen Bewerbungsdatensatz.
- Der Browser wertet dieses Ziel aus, ergänzt den Cache-Buster und öffnet die Bewerbung.

## Pruefstand 2.1.4

- Der KI-Textvertrag unterscheidet vollständige Neuerstellung bei leerer Instruktion von der
  Überarbeitung vorhandener Texte bei ausgefüllter Instruktion.
- Bei der Neuerstellung werden bisherige Texte nicht an das Modell übermittelt.
- Alle 28 PHP-Testdateien, PHP-Syntax, Hilfe- und Referenzgeneratoren sowie `git diff --check`
  bestanden. Nach TOTP-Freigabe wurden identische lokale und produktive Dateibytes, HTTP 200,
  Version 2.1.4 und das Fehlen sichtbarer PHP-Fehler bestätigt.

## Pruefstand 2.1.3

- Die Vertragstests decken die mobile direkte Klickbehandlung, das vor dem Request gezeichnete
  KI-Modal, die zuverlässige Neuladung nach Erfolg sowie leere und ausgefüllte KI-Instruktionen ab.
- Externe OpenAI-Aufrufe und die Produktionsdatenbank werden durch diese Tests nicht verändert.
- Alle 28 PHP-Testdateien, PHP-Syntax, beide Generatoren mit `--check` und `git diff --check`
  bestanden. Nach TOTP-Freigabe wurden identische lokale und produktive Dateibytes, HTTP 200,
  Version 2.1.3, KI-Modal, mobile Klicklogik und das Fehlen sichtbarer PHP-Fehler bestätigt.

## Pruefstand 2.1.2

- Alle 28 PHP-Testdateien bestanden; darin 3513 Hilfe-Inhaltsprüfungen und 1124 Hilfeseed-Prüfungen.
- PHP-Syntax für Anwendung und Beispielkonfiguration, beide Generatoren mit `--check` sowie `git diff --check` bestanden.
- Der Vertragstest prüft KI-Modal, Abbruchsteuerung, Hersteller-/Modellkennzeichnung und das Fehlen der unzuverlässigen Kontingentanzeige.
- Externe OpenAI-Aufrufe und die Produktionsdatenbank wurden durch die Tests nicht verändert. Nach TOTP-Freigabe wurden HTTP 200, Version 2.1.2, KI-Kennzeichnung, fehlende Kontingentanzeige und fehlende sichtbare PHP-Fehler öffentlich bestätigt.

## Historische Prüfstände

- 3248 Inhalts-/Locale-/Kontextpruefungen bestanden.
- 1049 Hilfeseed-Pruefungen mit simulierter DB bestanden, einschliesslich Teilfehler-Rollback und Wiederholung.
- Alle 16 PHP-Testdateien im aktuellen Kandidaten bestanden.
- Browser: 15 Hilfeansichten (5 Sprachen x 3 Breiten) und 135 Kontextdialoge (27 Seiten x 5 Sprachen) bestanden. Suche, Leerzustand, Reset, Kategorien, vollstaendige Texte/Links, Fokus und Escape geprueft.
- Desktop-, Mobil- und Dialogbilder tatsaechlich angesehen. Ueberfluessige Einfuehrungsbloecke entfernt, Suchfeld-/Themen-IDs getrennt und Themenverweise eindeutig beschriftet.
- 29 Markdown-Dateien und 60 lokale Dokumentationslinks geprueft.
- Hilfe-/Referenzgeneratoren mit --check und git diff --check bestanden; PHP-Syntax geprueft.
- Zusaetzliche Browserregressionen: 40 Workflowansichten, 3 Firmenadressansichten und 15 Aktionsbutton-Faelle bestanden.
- Live nach Benutzeranmeldung: 24 Themen, 72 Schritte und 24 Hinweise je Sprache in de-CH/fr-CH/en-GB/pt-BR/es-MX; kein Raw-Key, PHP-Fehlertext oder Seitenueberlauf in der aktuellen Browserbreite.
- Live: SMTP-Suche, Reset, Kontextdialog der Hilfe und Bewerbungs-Kontexttexte samt Themenanker in allen fuenf Sprachen geprueft. Deutsche Hilfe am Ende wiederhergestellt und Layout visuell angesehen.
- Produktive Datei entspricht exakt dem getesteten SHA-256; Version 1.18.1 im angemeldeten Footer bestaetigt.
- Keine echten Bewerbungen, Benutzerinformationen, Dateien oder Kalenderdaten durch Tests geaendert; keine E-Mail versendet.

## Offene Nachweise

Die Bestandsdatenmigration v6 ist nicht Teil dieser Runde und weiterhin nicht als ausgefuehrt belegt. Alle 27 Kontextzuordnungen und die drei Testbreiten wurden lokal geprueft; live wurden die zentrale Hilfe und die Kontexte Hilfe/Bewerbungen in fuenf Sprachen geprueft, nicht nochmals jede produktive Maske.

Die Dokumentation benennt verbleibende technische Schulden: historische SQL-Views, nicht vollstaendig versionierter allgemeiner UI-Katalog, einzelne Legacy-Beschriftungen ausserhalb der Hilfe und fehlender realer Neuinstallations-/Restore-Test. Sie verschweigt diese Luecken nicht und behauptet keine perfekte 1:1-Reproduktion allein aus Markdown.
