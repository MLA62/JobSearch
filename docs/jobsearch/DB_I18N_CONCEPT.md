# DB-basiertes Uebersetzungskonzept

Stand: 2026-06-19

## Ziel

JeMa Jobs verwendet kuenftig keine statischen UI-Textkataloge mehr in PHP,
Markdown, JavaScript oder anderen Ressourcendateien. Sprachrelevante
Oberflaechentexte werden als Daten in der Datenbank gepflegt und zur Laufzeit
ueber stabile Text-Keys geladen.

Das bisherige HTML-Nachuebersetzen und wachsende Fallback-Arrays sind nur noch
Altbestand und werden schrittweise entfernt.

## Grundregeln

- Sichtbarer UI-Text wird ueber einen stabilen Key referenziert.
- Der Code kennt den Key, aber nicht den eigentlichen Text.
- Texte und Uebersetzungen liegen in `ui_text_keys` und
  `ui_text_translations`.
- Die Standardsprache ist weiter `de-CH`.
- Fehlende Uebersetzungen fallen kontrolliert auf `de-CH` zurueck.
- Fehlende Keys werden als technischer Fehler protokolliert und im Adminbereich
  sichtbar gemacht.
- Neue UI-Texte duerfen nicht mehr hartcodiert werden.
- Kommentare im Code bleiben Deutsch.
- Markdown-Dokumentation bleibt Deutsch und ist nicht Teil der UI-Uebersetzung.

## Datenmodell

`languages`

Bestehende Tabelle fuer aktive Sprachen und Sortierung.

`ui_text_keys`

Enthaelt den stabilen Pointer:

- `text_key`: eindeutiger technischer Key, z. B. `nav.dashboard`
- `namespace`: Bereich, z. B. `nav`, `auth`, `profile`, `jobs`
- `description`: fachlicher Hinweis fuer Admins
- `default_locale`: normalerweise `de-CH`
- `is_active`: erlaubt Ausphasung ohne Datenverlust

`ui_text_translations`

Enthaelt den eigentlichen Text pro Sprache:

- `text_key_id`
- `locale`
- `text_value`
- `status`: `draft`, `review`, `approved`, `archived`
- Audit-Felder fuer Bearbeitung und Freigabe

`ui_text_cache_versions`

Erlaubt schnelles Invalidieren pro Sprache, sobald ein Admin Texte aendert.

## Laufzeit

1. Beim ersten `t('key')` pro Request wird die aktive Sprache bestimmt.
2. Alle genehmigten Texte dieser Sprache werden einmal aus der DB geladen.
3. Die genehmigten `de-CH`-Texte werden als Fallback ebenfalls geladen.
4. Der Request nutzt danach nur noch den Speicher-Cache.
5. Platzhalter werden erst nach der Key-Aufloesung ersetzt.

Beispiel:

```php
t('dashboard.greeting', ['name' => $displayName])
```

Der Code enthaelt dabei nur `dashboard.greeting`, nicht den Text.

## Performance

- Pro Request maximal zwei DB-Ladevorgaenge fuer UI-Texte:
  - aktive Sprache
  - `de-CH` als Fallback
- Keine Einzelabfrage pro Text.
- Optionaler Datei-/APCu-Cache kann spaeter ergaenzt werden, bleibt aber
  abgeleitet aus der DB und nicht Quelle der Wahrheit.
- `ui_text_cache_versions` dient zur schnellen Cache-Invalidierung.

## Admin-Pflege

Der Adminbereich braucht eine Uebersetzungsverwaltung mit:

- Liste aller Keys nach Namespace
- Filter nach Sprache, Status, fehlender Uebersetzung
- Bearbeiten des Textwerts je Sprache
- Statuswechsel `draft` -> `review` -> `approved`
- Anzeige, ob ein Key in einer Sprache fehlt
- Export/Import fuer Uebersetzer
- Audit, wer wann welchen Text geaendert oder freigegeben hat

## Migration

Phase 1: Tabellen anlegen

- Migration `sql/jobsearch/14_ui_translation_store.sql` importieren.

Phase 2: zentrale Runtime einfuehren

- Neue Funktion `t($key, $replace = [], $locale = null)` liest aus der DB.
- Bestehendes `tr()` wird intern auf `t()` umgeleitet.
- Statische Kataloge sind kein erlaubter Runtime-Fallback.

Phase 3: Keys uebernehmen

- Bestehende Keys muessen in der Datenbank vorhanden sein.
- Der Code wird auf reine Key-Nutzung umgestellt.
- Keine neuen Texte werden mehr in PHP aufgenommen.

Phase 4: Legacy entfernen

- Stand Version `1.15.21`: PHP- und Resource-Dateien enthalten keine
  Uebersetzungswoerterbuecher mehr. Leere Kompatibilitaetsfunktionen duerfen
  nur bestehen bleiben, solange alte Aufrufstellen noch aufgeraeumt werden.
- `public/i18n/ui_legacy.php` ist entfernt.
- `translateUiHtml()` darf nur DB-Inhalte aus `ui_text_keys` und
  `ui_text_translations` verwenden.

## Qualitaetskriterien

- Kein sichtbarer UI-Text darf neu hartcodiert werden.
- Ein Test scannt PHP/JS/Views auf neue sichtbare Literaltexte.
- Jede unterstuetzte Sprache muss denselben Key-Bestand haben.
- Fehlende Uebersetzungen sind im Adminbereich sichtbar.
- Der Login muss ohne Datenbankfehler weiterhin robust bleiben; bei DB-Ausfall
  wird ein minimaler deutscher Fehlertext aus einer technischen Notfallkonstante
  angezeigt, nicht als normaler UI-Katalog.

## Nicht-Ziele

- Bewerbungsinhalte, Firmen, Kontakte, Jobs und Dokumente sind Benutzerdaten und
  bleiben nicht Teil der UI-Sprachtabelle.
- Markdown-Dokumentation bleibt Deutsch.
- Code-Kommentare bleiben Deutsch.
