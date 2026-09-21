# Release 2.4.47 – Absatzverhalten im Rich-Text-Editor

Stand: 21.09.2026. Ein Textklick innerhalb des Editorlabels konnte bisher
das erste Toolbar-Control als Label-Standardaktion aktivieren. Das erklärt
unerwartete Absatzänderungen beim Klick auf Absätze oder Listenpunkte.

Der Editor hält den Fokus bei Textklicks und bietet statt der schwer
verständlichen Umwandlungsbuttons ein Formatmenü mit Absatz, H1, H2, H3.
Enter erzeugt einen neuen Absatz, Shift+Enter einen weichen Zeilenumbruch;
automatischer Umbruch verändert das HTML nicht. Das Menü formatiert auch
mehrere markierte Absätze einzeln und erhält bei einem Cursor die Position.
Tx entfernt Inlineformat und Links; markierte Überschriften und
Listeneinträge werden Absätze, andere Listenpunkte bleiben bestehen.
Gespeicherte Alttexte und `<br>` werden beim Öffnen nicht geändert.

Die frühere Basis kam aus `label { font-size: .9rem; }`, also bei der
Standardbasis von 16 px auf 10,8 pt und nicht auf genau 10 pt. Entsprechend
der Benutzerregel verwendet der Editor nun die 12-pt-Stufe: Absatz 12 pt
(0/6 pt Abstand davor/danach), H3 14 pt (12/6), H2 16 pt (12/6), H1 18 pt
(24/6). Listenpunkte erhalten keine Absatzlücke. Dieselbe Typografie gilt
für gespeicherte HTML-Ansichten. H1 und ältere DIV-Blöcke bleiben in der
Serverbereinigung erhalten; es gibt keine DB-Migration.

Lokale Nachweise: `rich_text_editor_visual_test.cjs` für Fokus, Klick,
Absatzwahl, mehrere markierte Blöcke, Cursor, Enter, Shift+Enter, Liste,
Tx und berechnete CSS-Abstände; `rich_text_chronology_test.php` für
Serverbereinigung und Textauszug; PHP-Syntax und 57 PHP-Tests.

Deployment am 21.09.2026 um 15:03 UTC aus Quell-Commit `4844f2d` nach
`public_html/jobs.jema.business`, ohne Datenbankschemaänderung. Die
freigegebene Archivaktion `b23940f0d4ad23c86fa0c1256815871b` ersetzte
nur `index.php` und `assets/app.css`. Zuvor wurden die Version-2.4.46-Dateien
mit den Aktionen `19b0daf1152b7366a36bfabf2d821585` und
`bb0fa306d6c13fb4d3e042ffce056d54` als
`index.php.bak-20260921-2.4.46-pre-2.4.47` und
`assets/app.css.bak-20260921-2.4.46-pre-2.4.47` gesichert. Das
Extraktionswerkzeug legte zusätzliche eigene Backups an.

Remote-Prüfsummen nach Deployment stimmen bytegenau mit den lokalen Dateien
überein:

- `index.php`: SHA-256 `18cdfd91c39a2d1d316ade563e18d85e007e6afa70a1c72ae68885ad941c8b30`
- `assets/app.css`: SHA-256 `22e5d29cbb3329ac4696865e421bc659c815b4645d3784c551665a41b6df74bc`

Die öffentliche Startseite zeigt Version 2.4.47. Die zuvor geöffnete
Browser-Sitzung war bei der Live-Prüfung abgelaufen; eine authentifizierte
Editorprüfung auf dem Produktivserver wurde deshalb nicht durchgeführt.
Der lokale Interaktionstest deckt die oben genannten Bedienfälle ab.
