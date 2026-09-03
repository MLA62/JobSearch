# Sprachen, UI-Texte und Hilfe

Stand: 2026-09-03, produktive Hilfe 1.18.1.
Dieses Dokument ersetzt das fruehere reine Zielbild durch die tatsaechliche Architektur.

## Sprachen und Daten

Unterstuetzt: `de-CH`, `fr-CH`, `en-GB`, `pt-BR`, `es-MX`.
`normalizeLocale()` normalisiert bekannte Varianten. `currentLocale()` bevorzugt die Sitzung, danach die Benutzereinstellung, danach Browser/Standard. Sprachewechsel per `lang` setzt die Sitzung; Profilpflege speichert die bevorzugte Sprache.

App-Sprache, Dokumentsprache, Kontakt-/Bewerbungssprache und Uebersetzung eigener Inhalte sind getrennt. Eigene Namen, Nachrichten, Dateien und Stellenbeschreibungen werden bei einem Sprachewechsel nicht umgeschrieben.

## DB-Laufzeit

- `ui_text_keys`: stabiler Key, Namespace, Standardsprache, Aktivierung.
- `ui_text_translations`: Text je Locale mit Freigabestatus.
- `ui_text_cache_versions`: vorhandene Infrastruktur; nicht mit einem voll implementierten globalen Cache gleichsetzen.
- `tr(string $key, ?string $locale = null, array $replace = [])`: freigegebene DB-Texte der Sprache, dann de-CH, sonst Key selbst; Platzhalter wie `{count}` ersetzen.
- Pro Request werden Sprachkataloge zwischengespeichert. Kein PHP-Woerterbuch als stiller Runtime-Fallback fuer fehlende Keys.
- `translateUiHtml()` ist Legacy-Nachuebersetzung auf DB-Basis, nicht die Zielarchitektur fuer neue Masken.
- Die Seite `translations` bearbeitet eigene Datensatzuebersetzungen; sie ist keine vollstaendige UI-Key-Verwaltung.

Die alte Behauptung, im PHP existierten keinerlei Textkataloge mehr, war falsch. Der Einstiegspunkt enthaelt versionierte Initial-/Reparaturseeds, die in die DB geschrieben werden. Neue Hilfe verwendet denselben DB-Laufzeitweg mit nachvollziehbarer, einmaliger Seedmigration.

## Eine Quelle fuer alle Hilfen

`docs/jobsearch/help/source.json` ist die redaktionelle Quelle mit 24 stabilen Themen-IDs, Seitenzuordnungen, Kategorien, Links und Texten in allen fuenf Sprachen.

Pro Thema: Titel, Zusammenfassung, konkrete Schritte, Hinweise. Hilfe-Chrome, Kategorien und Kurzuebersichten sind ebenfalls vollstaendig lokalisiert.

`php -n tools/build_help.php` erzeugt:
- den markierten `helpTranslationSeeds()`-/`helpTopicDefinitions()`-Block in public/index.php;
- fuenf lesbare Markdown-Handbuecher unter help/.

`php -n tools/build_help.php --check` meldet veraltete generierte Dateien als Fehler. Nicht in generierten Texten von Hand korrigieren.

`seedReviewedHelp()` nimmt nur den Hilfe-/Kontextkatalog auf. Ein SHA-256-Migrationsmarker, Advisory Lock und Transaktion verhindern Teilupdates und staendiges Ueberschreiben. Alte Initialseeds ueberschreiben verwaltete Hilfekeys nicht wieder. Aenderungen an diesen Keys werden bei einem neuen Inhaltshash bewusst erneut freigegeben.

`localizedHelpTopics()` baut die zentrale Hilfe aus den DB-Texten. `localizedContextHelpTopics()` verwendet dieselben Themen nach exakten Seiten-IDs. Keine erratene Zuordnung ueber englische Titel. Links in Kontextfenstern fuehren zum passenden Themenanker.

## Inhaltlicher Vertrag

Die Hilfe erklaert den aktuellen Arbeitsablauf: Stellen und Bewerbungen getrennt, sechs neue Statuswerte, kein Versanddatum fuer Entwurf/Bereit, explizite Termine statt Pendenzen, Kontaktlog-Zaehlungen, Job-Room ohne automatische Uebermittlung, Benutzer-Supportfreigabe und echte Dokumentsprache.

Die Suche umfasst Titel, Zusammenfassung, Schritte und Hinweise. Seitenthemen und Kategorien muessen auch nach einem Sprachwechsel funktionieren. Technische GET-Endpunkte ohne eigene Maske brauchen keine kuenstliche Hilfeseite.

## Pflege und Pruefung

1. Sachverhalt in Handler, Formular und Anzeige abgleichen.
2. Alle fuenf Sprachfassungen und benoetigte UI-Keys gemeinsam in source.json aendern.
3. Generatoren ausfuehren und Diff pruefen.
4. Vollstaendigkeit, Platzhalter, Verweise, Kontextzuordnung und Browserdarstellung testen.
5. Freigegebenen Katalog zusammen mit Code deployen; anschliessend alle Sprachen mit DB-Laufzeit pruefen.

Tests mit Mock-Katalogen belegen keine korrekten Produktions-DB-Werte. Fallback auf Deutsch kann fehlende Uebersetzungen kaschieren; deshalb im Test jede Locale vor dem Fallback auf Vollstaendigkeit pruefen.

## Grenzen

Der gesamte historische UI-Katalog ist nicht als vollstaendiger sauberer Seed im Repo enthalten. Fuer einen identischen Neuaufbau ist ein bereinigter Export der genehmigten UI-Texte erforderlich. Keine privaten Datensatztexte/Secrets dabei exportieren.

Diese Runde aktualisiert saemtliche Hilfethemen und Kontexttexte. Sie behauptet nicht, alle uebrigen Legacy-Beschriftungen der Anwendung oder alle gespeicherten Benutzeruebersetzungen korrigiert zu haben.
