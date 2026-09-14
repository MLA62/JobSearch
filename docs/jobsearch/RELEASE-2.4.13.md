# Version 2.4.13 – Umschaltbare und verlinkte Reports

Stand: 14.09.2026.

## Änderungen

- Jeder geöffnete Report kann direkt zwischen Tabelle und Karten umgeschaltet werden.
- «Gespeicherte Reports» steht oberhalb des Report-Editors.
- Jeder geöffnete Report bietet passende Filter pro angezeigtem Feld: Text, Datum von/bis,
  Minimum/Maximum oder vorhandene Auswahlwerte. Mehrere Filter werden gemeinsam angewendet und beim
  Wechsel zwischen Tabelle und Karten beibehalten.
- Die gespeicherte Anzeigeart bleibt unverändert und dient weiterhin als Voreinstellung.
- Jeder Treffer in Tabelle, Karten, Liste, Vorschau und Kalendergruppen verlinkt auf seinen
  zugehörigen Originaldatensatz.
- Der sichtbare Job-Room-Status ersetzt den Ergebnisplatzhalter vollständig. Auch ältere
  `:result`-Übersetzungen werden korrekt verarbeitet.

## Prüfung

Alle 36 PHP-Testdateien sind erfolgreich. Darin enthalten sind 4'010 Inhaltsprüfungen des
Hilfesystems, 1'369 Prüfungen der Hilfe-Seeds sowie die Verträge für Anzeigeumschaltung, sichere
Datensatzrouten, typgerechte Feldfilter, Platzhalterersetzung und sämtliche Report-Renderer. Alle acht
Chromium-Tests sind erfolgreich. Der Reporttest prüft die Resultatlinks, eine echte Statusfilterung
und die Umschaltung mit erhaltenem Filter bei Mobil- und Desktopbreite. Zusätzlich wurden 82
Markdown-Dateien und 61 lokale Links geprüft.

## Deployment

Das Deployment ersetzt ausschließlich `public_html/jobs.jema.business/index.php` und
`public_html/jobs.jema.business/assets/app.css`. Produktive Hashes und HTTP-Prüfung werden nach der
externen TOTP-Freigabe ergänzt.
