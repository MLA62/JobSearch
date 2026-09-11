# Dokumentationsaudit

Stand: 2026-09-11. Version 2.4.4 ist implementiert und dokumentiert; produktive Nachweise folgen nach der TOTP-Bereitstellung.

## Prüfstand 2.4.4

- Ein unveränderter Link wird erst nach erfolgreichem Abruf und geprüftem JeMa-Eigentumsmarker als
  bestätigt gezählt.
- Kalenderwechsel und extern gelöschte Google-Termine führen zu einer sicheren Neuerstellung statt
  zu einem dauerhaften Scheinerfolg.
- Vor jedem Vollabgleich werden die Kalendernachweise aller aktiven Bewerbungsverläufe neu projiziert.
- Erwartete und bestätigte Exporte sowie konkrete Einzelfehler sind im Ergebnis nachvollziehbar.
- Jede angemeldete Sitzung stößt genau eine vollständige Bestandsprüfung an; unvollständige Läufe
  erhalten keinen erfolgreichen Sitzungsmarker.

## Prüfstand 2.4.3

- Das produktiv protokollierte Blockieren am fehlenden Marker `workflow_calendar_v6` ist im
  Synchronisationspfad entfernt; der bestehende geprüfte Exportfilter bleibt aktiv.
- Änderungen und Löschungen an Kalender, Bewerbungen, Jobs, Firmen und primären Kontakten lösen den
  Google-Vollabgleich aus. Automatische und manuelle Fehler werden im Profil gespeichert.
- Der abonnierbare private ICS-Feed wird mit No-Cache-Headern ausgeliefert.
- Die Workflow-v6-Bestandsmigration ist unverändert ein eigener, explizit freizugebender Datenvorgang
  und wird durch dieses Release nicht ausgeführt.
- Alle 35 PHP-Testdateien, 3'840 Hilfeprüfungen, 1'269 Hilfe-Seeds, 72 Markdown-Dateien/61 lokale
  Links, beide Generatoren sowie alle sieben Chromium-Testdateien sind erfolgreich.
- Nach TOTP-Freigabe sind lokale und produktive `index.php` bytegleich. Die öffentliche Seite liefert
  HTTP 200, Version 2.4.3 sowie HSTS, CSP, `nosniff`, `DENY` und `no-referrer`; für den
  Deployment-Tag wurde kein neuer produktiver Serverfehler protokolliert.

## Prüfstand 2.4.2

- Firmenlinks zu Jobs, Bewerbungen und Kontakten stehen vollständig und jeweils auf einer eigenen Zeile.
- Der Job-Room-Helper und seine Monatsauswahl berücksichtigen ausschließlich Bewerbungen mit
  tatsächlichem `applied_at`; es gibt keinen Fallback auf Erfassungs- oder Änderungszeit.
- Datenbankstruktur und Bestandsdaten werden nicht verändert.
- Alle 34 PHP-Testdateien, 3'829 Hilfeprüfungen, 1'264 Hilfe-Seeds, 71 Markdown-Dateien/61 lokale
  Links, beide Generatoren sowie alle sieben Chromium-Testdateien sind erfolgreich.
- Nach TOTP-Freigabe `9f28909be84b0a93ded073cd50f647c9` wurden ausschließlich `index.php` und
  `assets/layout.css` bereitgestellt. Die Produktivdateien stimmen bytegenau mit Quell-Commit
  `809283348cf88d017332a3ebf9c35f5ef692b5c9` überein: `index.php` hat SHA-256
  `8b0a8be5fdc2195084972face49b33c793dd563bdd88dd5529e56d0712a43f93` und 1'199'140 Bytes,
  `layout.css` SHA-256 `0a57f248f19f3afd3d95760ab5cdc61670c6f571c949b7e3e73652c7fd087e76`
  und 15'389 Bytes.
- Die öffentliche Seite und das Stylesheet liefern HTTP 200. Die Seite weist Version 2.4.2 sowie
  HSTS, CSP, `nosniff`, `DENY` und `no-referrer` aus. Das produktive Fehlerprotokoll erhielt zwischen
  Deployment und Abnahme keinen neuen Eintrag.
- `.htaccess`, `config.php`, Speicherdateien und Datenbank wurden nicht verändert. Der bereits vor
  diesem Release abweichende produktive `.htaccess`-Stand blieb bewusst unangetastet.

## Prüfstand 2.4.1

- Firmen zählen nur aktive Bewerbungen zu aktiven eigenen Jobs; eine Vermittlerrolle zählt nicht als
  Bewerbung bei der Vermittlerfirma.
- Firmenzahl und verlinkte Bewerbungsansicht verwenden dieselbe direkte Arbeitgeberrelation.
- Bewerbungslisten blenden Datensätze zu gelöschten Jobs oder Firmen aus.
- Alle 34 PHP-Testdateien, 3'807 Hilfeprüfungen, 1'254 Hilfe-Seeds, 70 Markdown-Dateien/61 lokale
  Links, beide Generatoren sowie alle sieben Chromium-Testdateien sind erfolgreich.
- Nach TOTP-Freigabe `c932bad9a92095ff16f94d5b5a76840e` stimmen alle fünf Produktivdateien
  bytegenau mit dem geprüften Release überein. `index.php` hat SHA-256
  `231386df59571d838ebb5245b679699c03c6577f0051b86280fc2366a1c78134` und 1'197'806 Bytes.
- Die öffentliche Seite liefert HTTP 200, Version 2.4.1 sowie HSTS, CSP, `nosniff`, `DENY` und
  `no-referrer`; nach dem Deployment wurde kein neuer Serverfehler protokolliert.

## Prüfstand 2.4.0

- Der gemeldete generative Jobprofil-Auftrag wird als Schreibauftrag erkannt und durch den neuen
  mehrstufigen Agentenlauf verarbeitet. Konkrete Datenbankfehler führen zu einer selbstständigen
  Korrekturrunde statt zum vorzeitigen Abbruch.
- Profil, aktive Präferenzen, Sprachkenntnisse, aktueller CV und benutzerisolierte Tabelleninventare
  stehen als aktualisierter Kontext bereit. Nach dem ersten Schreibvorgang folgt zwingend eine
  Verifikationsrunde.
- «Vorschlag erstellen» im Schnellimport öffnet einen echten modalen Fortschrittsdialog; Chromium
  bestätigt Quellenermittlung, Laufzeit, Zähler, Verlauf je Anzeige, Abbrechen und Erfolgsnavigation.
- Alle 34 PHP-Testdateien, 3'807 Hilfeprüfungen, 1'254 Hilfe-Seeds, 69 Markdown-Dateien/61 lokale
  Links, beide Generatoren sowie alle sieben Chromium-Testdateien sind erfolgreich.
- Nach TOTP-Freigabe `a1848c8c51dc460a8ae19a61e1933580` stimmen alle fünf Produktivdateien
  bytegenau mit dem geprüften Release überein. `index.php` hat SHA-256
  `8cfd6d5ecaecb1a0b2f64c3c448fef3df4100e49b0bfd0e41822c596f235a2ce` und 1'197'397 Bytes.
- Die öffentliche Seite liefert HTTP 200, Version 2.4.0 sowie HSTS, CSP, `nosniff`, `DENY` und
  `no-referrer`; nach dem Deployment wurde kein neuer Serverfehler protokolliert.

## Prüfstand 2.3.7

- Der produktiv beobachtete Cleeven-Plan mit `table_upsert`, Firma und `uid` ist als ausführbarer
  Regressionstest hinterlegt und wird an den spezialisierten Firmenschreiber normalisiert.
- Derselbe Test prüft die Feld-Allowlist aller 20 freigegebenen Tabellen, Tabellen-/Feldaliasse,
  Zusatzangaben in Notizen sowie alle unterstützten Fremdschlüsselarten.
- Die vollständige Suite mit 33 PHP-Testdateien ist erfolgreich; Hilfe-Generator (25 Themen in
  fünf Sprachen), Referenzgenerator, 3'796 Hilfeprüfungen, 68 Markdown-Dateien/61 Links sowie der
  echte Chromium-Test für die Admin-KI-Konsole sind erfolgreich.
- Nach TOTP-Freigabe `7f855985bdf44aca69021367617fe94e` stimmen alle fünf Produktivdateien
  bytegenau mit dem geprüften Release überein. `index.php` hat SHA-256
  `24fb8fa3a4886108e31f83dbafb61bc111d8c8502d60dd8223826ac04490049d` und 1'172'894 Bytes.
  Die öffentliche Seite liefert HTTP 200, Version 2.3.7 sowie HSTS, CSP, `nosniff`, `DENY` und
  `no-referrer`.

## Prüfstand 2.3.6

- Der reale Formularaufbau ohne `action`-Attribut wird mit seinen `action`-benannten Buttons in
  Chromium ausgeführt.
- Ausschließlich die aktuelle Admin-KI-Seitenadresse wird vom Testserver akzeptiert; dadurch ist der
  zuvor übersehene HTTP-404-Pfad reproduzierbar ausgeschlossen.
- Eingabeerhalt, konkrete Fehler, Erfolg, Markdown, Modal und drei Bildschirmgrössen bleiben Teil
  desselben Browsertests.
- Die fünf Produktivdateien stimmen bytegenau mit dem geprüften Release überein. Die öffentliche
  Seite liefert HTTP 200, Version 2.3.6 und die vorgesehenen Security-Header.

## Prüfstand 2.3.5

- Eingabe und konkrete Fehlerantwort bleiben ohne Seiten-Reload sichtbar.
- Kontext und Protokoll werden benutzergebunden in `admin_ai_memory` gespeichert und erst mit
  «Gedächtnis löschen» entfernt; die Eingabe besitzt zusätzlich einen Browser-Entwurf.
- Der einleitende Erklärungstext ist entfernt.
- Chromium prüft den echten produktiven Clientcode für Fehler- und Erfolgspfad sowie drei
  Bildschirmgrössen ohne Dokument-Scroll. PHP-, Hilfe- und Dokumentationsprüfungen sind vollständig
  erfolgreich.
- Die fünf Produktivdateien stimmen bytegenau mit dem geprüften Release überein. Die öffentliche
  Seite liefert HTTP 200, Version 2.3.5 und die vorgesehenen Security-Header. Die neue
  `admin_ai_memory`-Startmigration erzeugte keinen Serverfehler.

## Prüfstand 2.3.4

- Direkte Admin-Aufträge werden nicht als unverbindlicher Vorschlag zurückgestuft; Statusfragen nutzen einen Datenbank-Lookup.
- Die Ausgabe rendert Markdown sicher, inklusive `**Fettdruck**`, ohne HTML-Ausführung.
- Die Admin-KI-Seite hält Eingabe und Ausgabe gleichzeitig sichtbar; nur das Ausgabefeld scrollt intern.

- Die Admin-KI erzeugt ein striktes JSON-Operationsschema und kann ausdrücklich beauftragte Einzel-
  und Mehrfachoperationen in allen freigegebenen Nutzer-Datentabellen transaktional ausführen.
- Allowlist, gebundene Werte, Referenzauflösung, Ergänzung nichtleerer Datensätze und Nichtreaktivierung
  gelöschter Datensätze sind im Anwendungscode und Vertragstest verankert.
- Sitzungskontext bleibt bis «Gedächtnis löschen» erhalten; die Ausgabe ist kompakt und chronologisch.
- PHP-Lint, Kernregressionen, Hilfe-Generator und 3'785 Hilfe-Inhaltsprüfungen bestanden.
- Nach TOTP-Freigabe `28492b06ae4402bf9dba9846b33791dc` sind lokale und produktive öffentliche
  Dateien bytegleich; `index.php` hat SHA-256 `dbd9664839b775c8a940f184fda6e345e04aba3bc8311f690bb30ee9bb9c6ac1`.
- HTTPS liefert HTTP 200, Version 2.3.3 sowie HSTS, CSP, `nosniff`, `DENY` und `no-referrer`.


## Prüfstand 2.3.2

- Nur Jobs mit Status `rejected` sowie Bewerbungen, die einem solchen abgesagten Job zugeordnet sind, verwenden die hellere Inhaltsfarbe; übrige Karten und Tabellen bleiben im Standardkontrast.
- Die Admin-KI-Konsole ist auf das eigene Admin-Konto beschränkt, nutzt `store=false`, Websuche nur für öffentliche Plattformrecherchen und liefert ausschließlich prüfbare Vorschläge ohne automatische Mutationen.
- Ausgabe-/Eingabefelder sind auf Bildschirmbreite und vertikales Scrollen ausgelegt; PHP- und UI-Tests sowie die Live-Header-Prüfung sind bestanden.

- Produktiver Commit `a391e99b47120082955318411ea055866c2da518`; `index.php`-Hash `6c3aea1c40955eb751e5145db3b820ed2471bd2177c165a8d263d2a16b7c8499`, `app.css`-Hash `81f943fcfda5f257014a1b51c8fd4aea67d20c7ce55f57f6ecb1bc3caba13834`.

## Prüfstand 2.3.1

- Karten- und Tabellenfarben wurden heller gesetzt, ohne die Lesbarkeit zu verlieren.
- Versionsbasiertes Asset-Caching verhindert, dass alte CSS-Dateien im Browser verbleiben.
- Responsive Browser-Tests in fünf Sprachen und allen getesteten Breiten bestanden.
- Produktiver `index.php`-Hash `683fe14737453319db535d210a128abb4e72d0775bd1e247cff0a8facf16abc` und `app.css`-Hash `78efab6f67a36f00a5d71f545fa09e6cae408bbbe74766b48a01b7082d3179f5` entsprechen dem getesteten Release.
- Öffentliche Seite und CSS liefern HTTP 200; Version 2.3.1 und die neuen Inhaltsfarben sind per HTTPS bestätigt.

## Pruefstand 2.3.0

- Der kritische Passwort-Reset-Fallback wurde entfernt; offene Alttokens werden migriert und entwertet.
- Authentifizierungsbegrenzung, verschlüsselte TOTP-Secrets, Sitzungsentwertung, sichere Mailziele, MIME-Prüfung und Browser-Schutzheader sind implementiert.
- Externe Laufzeit-Assets wurden durch lokale Dateien ersetzt; der SQL-Fallback interpoliert keine Werte mehr.
- PHP-Syntax, 32 automatisierte PHP-Testdateien, 3'650 Hilfeinhalte und die generierten Hilfereferenzen sind geprüft.
- Produktiver `index.php`-Hash: `b0ccedab1b9e158c8d50b0d21ae069ba6dd2e84c254ee6e8578a50cbb4f62310`; HTTP 200 und Security-Header verifiziert.
- Ungültiger 2FA-Routenfall liefert HTTP 302 ohne neuen Fehlerlog-Eintrag.

## Pruefstand 2.2.4

- HTML und WYSIWYG besitzen getrennte Commit-Wege; der Wechsel aus HTML schreibt unmittelbar in den visuellen Editor.
- Native Übermittlung und programmatisches `FormData` setzen den Feldwert nochmals aus dem aktiven Modus.
- Der Bewerbungs-Autosave synchronisiert Rich-Text-Felder ausdrücklich vor der Payload-Erzeugung.
- PHP-Syntax, alle 31 automatisierten PHP-Testdateien sowie Hilfe- und Referenzgeneratoren wurden erfolgreich geprüft.
- Nach externer TOTP-Freigabe sind lokale und produktive Datei mit SHA-256 `a4522dccd8777c81a564a5180359306ca28b9e268311deeb6d9d1420e4a256bc` bytegleich. Die öffentliche Seite liefert HTTP 200 und Version 2.2.4; seit dem Deployment entstand kein neuer PHP-Fehlereintrag.

## Pruefstand 2.2.3

- Änderungen in der HTML-Ansicht werden beim Wechsel zu WYSIWYG, beim Speichern und vor KI-Aktionen als verbindlicher Feldinhalt übernommen.
- Die Bereinigung wird nicht mehr bei jedem Tastendruck auf möglicherweise unvollständiges HTML angewendet.
- Der Vertragstest deckt Modusquelle, Übernahme in WYSIWYG und den entfernten verlustanfälligen Live-Abgleich ab.
- PHP-Syntax, alle 31 automatisierten PHP-Testdateien, 3'639 Hilfeinhalte, 1'204 Hilfe-Seeds und die generierten Referenzen wurden erfolgreich geprüft.
- Nach externer TOTP-Freigabe sind lokale und produktive Datei mit SHA-256 `c23ee604d4948ca4ce1df92e0895a8d5ece75f53484dd5eb063eb2f161fa482e` bytegleich. Die öffentliche Seite liefert HTTP 200 und Version 2.2.3; seit dem Deployment entstand kein neuer PHP-Fehlereintrag.

## Pruefstand 2.2.2

- Sichtbare Rich-Text-Inhalte werden vor der KI-Aktion ausdrücklich in die Formularfelder synchronisiert.
- Ausstehende Autosaves werden vor der KI-Übermittlung gestoppt; die Aktion nutzt die robuste native Formularnavigation.
- Die Modellinstruktion verlangt die erkennbare Umsetzung in Begleit-E-Mail und Motivationsschreiben; unveränderte Rückgaben bleiben ein Fehler.
- Neue Dokumentversionen übernehmen Metadaten und Gültigkeitsdaten, zählen aus der höchsten Serienversion weiter und werden mit Datei-Cleanup transaktional gespeichert.
- PHP-Syntax, alle 31 automatisierten PHP-Testdateien sowie Hilfe- und Referenzgeneratoren wurden vor der TOTP-Freigabe geprüft.
- Nach externer TOTP-Freigabe sind lokale und produktive Datei mit SHA-256 `1c43f2a75f5f01807ca8901bfea1dbeae1523ba025700d91e47442a4747d4999` bytegleich. Die öffentliche Seite liefert Version 2.2.2; seit dem Deployment entstand kein neuer PHP-Fehler.

## Pruefstand 2.2.1

- Manuell eingegebene Inserat-Adressen überschreiben ausschließlich einen fehlenden automatischen Verfügbarkeitsbeleg; die automatische Suche bleibt streng.
- Einzel- und Mehrfach-Schnellimport verwenden denselben manuellen Prioritätsmodus mit vollständiger Extraktion und Match-Berechnung.
- Der Mini-Editor enthält Listen, Ein-/Ausrücken und Format löschen. Karten und Tabellen zeigen Langtexte als Klartext; das Dossier rendert bereinigtes HTML.
- Bewerbungsfilter, Leerzustand und Arbeitgeberbezug sind in fünf Sprachen vollständig beschriftet.
- KI-Anweisungen werden gegen tatsächliche Änderungen an Begleit-E-Mail und Motivationsschreiben geprüft; ein unveränderter erster Rücklauf wird einmal wiederholt.
- Alle 30 PHP-Testdateien bestanden. Das Hilfe-System umfasst 3'628 geprüfte Inhalte und 1'199 Seed-Einträge.
- Nach externer TOTP-Freigabe wurden identische lokale und produktive Dateibytes (SHA-256 `c40ecd95dc908b1d66449a6a972c4628cf1f4c942836a350faf466121c6805d3`), HTTP 200, Version 2.2.1 und kein neuer PHP-Fehlereintrag bestätigt.

## Pruefstand 2.2.0

- Sichere HTML-Langtexte, Mini-Editor, formatierte E-Mail-Ausgabe sowie Klartextkonvertierung für PDF, Export und KI-Kontext sind in Code und Test beschrieben.
- Statushistorien, Kontakt-Logs, gemischte Bewerbungsvorgänge und Audit-Auszüge sind chronologisch aufsteigend spezifiziert.
- Hilfequelle, fünf generierte Sprachfassungen, Anforderungen, Workflow, Programmdokumentation, Prüfplan und Release-Nachweis werden gemeinsam geprüft.
- Nach externer TOTP-Freigabe wurden identische lokale und produktive Dateibytes, HTTP 200, Version 2.2.0 und das Fehlen neuer sichtbarer PHP-Fehler bestätigt.
Frühere Nachweise sind historische Belege.

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

## Pruefstand 2.1.9

- «Gesendet am» rendert und akzeptiert Sekundenwerte konsistent; der Browser weist gespeicherte Zeitstempel nicht mehr als ungültig zurück.
- Der Workflow-Vertragstest deckt Eingabeauflösung und Sekundenformat ab.
- Nach TOTP-Freigabe wurden identische lokale und produktive Dateibytes, HTTP 200, Version 2.1.9 und das Fehlen neuer sichtbarer PHP-Fehler bestätigt.

## Pruefstand 2.1.8

- Alle 29 PHP-Testdateien bestanden; das Help-System umfasst 3'525 geprüfte Inhalte und 1'134 Seed-Einträge.
- Der neue Vertragstest deckt alle soft-gelöschten Tabellen mit fachlicher Eindeutigkeit ab.
- Gelöschte Datensätze blockieren keine Neuanlage; aktive Dubletten bleiben verhindert.
- Fremdschlüssel erhalten vor dem Austausch eines bisher stützenden Unique-Indexes einen eigenen Index.
- Bewerbungsspeicherung und Textvorbereitung besitzen getrennte, handlungsorientierte Meldungen
  mit korrelierbarer Fehlerreferenz.
- Nach TOTP-Freigabe wurden identische lokale und produktive Dateibytes, HTTP 200, Version 2.1.8
  und das Fehlen neuer Schema- sowie sichtbarer PHP-Fehler bestätigt.

## Pruefstand 2.1.6

- `Bewerbung vorbereiten` nutzt nach dem Modal eine native Browser-Formularnavigation und folgt
  damit der serverseitigen Weiterleitung ohne clientseitiges Fetch-Parsing.
- Der Vertragstest prüft Aktionsfeld, native Übermittlung und Abbruch der Navigation.
- Alle 28 PHP-Testdateien, PHP-Syntax, Hilfe- und Referenzgeneratoren sowie `git diff --check`
  bestanden. Nach TOTP-Freigabe wurden der identische lokale und produktive Release-Stand,
  HTTP 200, Version 2.1.6, KI-Modal und das Fehlen sichtbarer PHP-Fehler bestätigt.

## Pruefstand 2.1.5

- Die asynchrone Bewerbungsvorbereitung liefert ein explizites Navigationsziel auf den erzeugten
  oder vorhandenen Bewerbungsdatensatz.
- Der Browser wertet dieses Ziel aus, ergänzt den Cache-Buster und öffnet die Bewerbung.
- Alle 28 PHP-Testdateien, PHP-Syntax, Hilfe- und Referenzgeneratoren sowie `git diff --check`
  bestanden. Nach TOTP-Freigabe wurden identische lokale und produktive Dateibytes, HTTP 200,
  Version 2.1.5, KI-Modal und das Fehlen sichtbarer PHP-Fehler bestätigt.

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
