# Release 2.4.19

Stand: 2026-09-14.

## Ergebnis

Der Rich-Text-Mini-Editor kann eine beliebige Auswahl mehrerer Zeilen beziehungsweise Absätze nun
gezielt zwischen echten Absätzen und weichen Zeilenumbrüchen umwandeln. Der bisherige `¶`-Button
wirkt auf die gesamte Markierung. Direkt daneben steht `↵` für Shift+Enter-Umbrüche.

Die mit 2.4.18 korrigierte unbedingte Bewerbungsvorbereitung ist vollständig enthalten: Firmen- oder
Analysekonflikte verweigern eine ausdrücklich verlangte Bewerbung nicht mehr.

## Bedienung

- Text über mehrere Zeilen oder Absätze markieren.
- `¶` anklicken, um jede markierte Zeile als eigenen Absatz zu setzen.
- `↵` anklicken, um die markierten Absätze durch weiche Zeilenumbrüche zu verbinden.
- Fett, Kursiv, Links und andere Inline-Formatierungen bleiben erhalten.

## Prüfung

Ein neuer Chromium-Test bedient beide Schaltflächen am produktiven Editorcode und vergleicht den
sichtbaren WYSIWYG-Inhalt sowie den tatsächlich übertragenen Textarea-Wert. Die bestehenden
Rich-Text-, Sicherheits-, Hilfe-, Dokumentations- und Anwendungsregressionen bleiben Teil der
vollständigen Freigabeprüfung.
