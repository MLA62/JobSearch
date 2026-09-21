# Abbruchkriterien der Bewerbungs-KI – Prüfung 2.4.40

Stand: 21.09.2026. Auslöser war Fehlerreferenz `C6970452D5F0`.
Das produktive Fehlerprotokoll ordnet sie der simulierten Empfängerprüfung
zu. Diese verlangte unter anderem eine postalische Adresse in der
Begleit-E-Mail und bewertete eine verständliche Anrede als Ablehnungsgrund.
Beides ist kein belastbarer technischer oder fachlicher Abbruchgrund.

| Prüfung | Behandlung ab 2.4.40 | Grund |
| --- | --- | --- |
| Bewerbung und Job gehören dem aktuellen Benutzer; ausgewählte aktuelle CV-Dateien sind lesbar | Verbindlich | Fremde Daten oder defekte ausgewählte Quellen dürfen nicht als Bewerberfakten dienen. |
| API nicht erreichbar, Antwort unvollständig oder kein gültiges JSON | Verbindlich; nach einem rein redaktionellen Retry bleibt der letzte bereits verbindlich geprüfte Entwurf erhalten | Ein defekter API-Rücklauf ist kein Bewerbungstext. |
| Angeforderte Textfelder leer | Verbindlich | Leere Felder dürfen nicht als Erfolg gespeichert werden. |
| Aussagen über fehlende Unterlagen oder Aufschub auf ein Gespräch | Vor der Prüfung gezielt entfernen; nur bei danach fehlender Substanz erneut generieren | Ein einzelner entfernbarer Satz darf keinen ganzen Entwurf vernichten. |
| Ausdrückliche Änderung nicht sichtbar, Längen-/Weglassvorgabe nicht erfüllt | Verbindlich, mit bis zu drei Versuchen | Die Benutzeranweisung hat Vorrang; ein unveränderter Text ist kein Erfolg. |
| Ungefragte Erfolgszahlen bei Neuerstellung | Verbindlich, mit Korrekturversuch | Entspricht der ausdrücklich festgelegten Standardvorgabe. |
| Empfängerblock, Anrede und vollständige Briefstruktur | Verbindlich, mit deterministischer Empfängerblock-/Anrede-Reparatur und Korrekturversuch | Ein unfertiger Brief darf nicht als fertiges Schreiben ausgegeben werden. |
| Fehlende Grussformel oder voller Name in der Begleit-E-Mail | Vor der Prüfung ergänzen | Technisch eindeutig reparierbar; vorhandene HTML-Formatierung bleibt bei vollständigem Text erhalten. |
| Wortzahlpräferenz, Standardfloskel und interne Zitat-/Quellenmetadaten | Ein redaktioneller Verbesserungsversuch, danach Hinweis im Serverlog statt Veto | Die Metadaten sind nicht Teil des Bewerbungstextes; exakte Zitate und Wortgrenzen können trotz brauchbarem Text falsch bewertet werden. |
| Zweites Modell als simulierter Empfänger | Ein Verbesserungsversuch, danach nur beratend; Ausfall ebenfalls nicht blockierend | Das Modell ist kein realer Empfänger und kann Kontext oder Stil falsch beurteilen. |
| Endkontrolle auf Mindestsubstanz, Empfängerblock, Briefstruktur und konkrete Änderungsanweisung | Verbindlich | Verhindert eine falsche Erfolgsmeldung nach der Nachbearbeitung. |

Die Kontaktperson wird bei jedem KI-Aufruf aus den eigenen Primär-,
Bewerbungs-, Job- und Firmenkontakten erneut ermittelt. Zusätzlich werden
aktuelle Jobanforderungen, Firmenname, Branche und vorhandene
CRM-Firmennotizen für den Schreibkontext gelesen; Notizen sind dabei
keine ungeprüfte Tatsachenfreigabe. Ein alter
dreizeiliger Briefkopf wird durch den aktuellen vierzeiligen CRM-Block
ersetzt, wenn darin eine inzwischen bekannte Kontaktperson steht.
Vollständig manuell geänderte vierzeilige Adressen bleiben erhalten.
Andere Firmenkontakte werden nicht ohne eindeutige Zuordnung geraten.

Ein redaktioneller Neuversuch hält den letzten bereits strukturell und
bezüglich des Benutzerauftrags gültigen Entwurf bereit. Ein späterer
API-Fehler oder schlechterer Entwurf kann dieses Ergebnis nicht mehr
vernichten. Es wird nichts automatisch versendet. Eine echte sprachliche
Abnahme mit dem konkreten Benutzerfall ist nach dem Deployment gesondert
von Syntax, Tests und anonymem HTTPS-Smoke-Test auszuweisen.
