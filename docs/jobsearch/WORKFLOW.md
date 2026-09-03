# Bewerbungsprozess und Kalender

Beschlossen am 03.09.2026. Umsetzung: Release 1.17.0.

## Prozess

Entwurf -> Bereit -> Gesendet -> Bewerbungsgespraeche -> Zusage oder Absage.
Ein Statuswechsel schreibt automatisch einen Zeitstempel in den Verlauf.
Mehrere Bewerbungsgespraeche und Kontakte bleiben einzeln mit ihren eigenen
Terminen und Notizen erfassbar; sie erfordern keinen neuen Status.
Historische Sonderstatus werden erhalten und nicht in andere Ergebnisse umgedeutet.

## Ein Kalender

Es gibt keinen getrennten Aufgaben- oder Pendenzenbereich. Ein Kalendereintrag
hat einen konkreten Zeitpunkt. Geplante Gespraeche und Nachfassaktionen sind
bearbeitbar, verschiebbar und abschliessbar. Der Abschluss eines bestehenden
Eintrags erzeugt keinen weiteren Termin. Undatierte Altdaten bleiben bei der
Bewerbung erhalten, bis ein Datum angegeben oder der Eintrag entfernt wird.

Gesendet, Zusage und Absage erscheinen automatisch als kurze, nicht blockierende
Nachweise ohne Alarm. Der Versand verwendet applied_at, nicht den Zeitpunkt
einer spaeteren Erfassung. Entwurf und Bereit bleiben ausschliesslich im Verlauf.
Antwort pendent ist kein Termin. JeMa erzeugt keine Ganztagseintraege.
Private importierte Google-Eintraege werden nicht im Rahmen der Bereinigung geaendert.

## Job-Room

Erfassung und Ergebnis sind unabhaengig. Neue Bewerbungen starten mit
not_recorded. Bestehende Bewerbungen starten mit unknown, da ein bisheriges
Ergebnis keine bestaetigte Erfassung im Portal belegt. recorded wird bewusst
gesetzt. Ein Gespraech bedeutet weder Zusage noch Anstellung.

## Migration und Rueckweg

workflow_calendar_v5 ist durch einen Datenbank-Lock und einen einmaligen
Migrationsmarker geschuetzt. Vor fachlichen Aenderungen werden Anwendungen
und erzeugte Kalendereintraege in workflow_data_backups gesichert. Aenderungen
und Migrationsmarker erfolgen in einer Transaktion; Fehler rollen diese zurueck.

Automatische Antwort-Wartetermine am Versandzeitpunkt werden entfernt.
Abweichende ausdrueckliche Wartedaten werden als Nachfassen erhalten.
Alte automatische completed-/closed-Eintraege werden storniert, nicht geloescht.
Tatsaechliche Versandzeitpunkte und Statushistorien bleiben erhalten.

Google-Eintraege werden anhand ihrer JeMa-Eigentuemerkennung aktualisiert.
Ueberholte verknuepfte Projektionen werden erst nach erfolgreichem Export
storniert, mit vorheriger Sicherung und Versionspruefung. Private Termine und
Eintraege mit Teilnehmern werden nicht automatisch bereinigt.
Eine Wiederherstellung darf nur gezielt anhand der Sicherungen erfolgen;
kein vollstaendiger Datenbank-Rollback ueber inzwischen neue Benutzerdaten.

## Pruefung

Vor Freigabe: PHP-Syntax, Projektionstests, Formular- und Datumsfiltertests,
responsive Workflow-Darstellung. Nach Freigabe: Release-Hash, migrierte
Kalenderliste, Job-Room-Feld, Datumsfilter, Google-Abgleich und Wiederholung
ohne weitere Neuanlagen. Testdaten duerfen keine echten Bewerbungen versenden.
