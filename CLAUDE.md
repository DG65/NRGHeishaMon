# Konventionen für dieses Modul

## Sprache: Deutsch für alles Nutzersichtbare

Anweisung von Dietmar (22.07.2026), gilt für alle Module des DG65-Verbunds.

**Deutsch** sind: Formularbeschriftungen, Hinweis- und Warntexte, Bestätigungsdialoge, Fehler- und Statusmeldungen, Log-Ausgaben, Variablen- und Profilnamen, README und Changelog. Vermeidbare Anglizismen ersetzen — insbesondere: Link → Verknüpfung, Event → Ereignis, Button → Schaltfläche, Dry-Run → Probelauf, Scan → Suche, Drag & Drop → Ziehen mit der Maus.

**Ausgenommen** (bleibt englisch, sonst brechen Verträge oder das Verständnis leidet):

- Bezeichner im Code: Klassen-, Methoden-, Eigenschafts- und vor allem **Ident-Namen**
- Formularelementtypen (`"type": "Button"`, `SelectVariable`, `SelectCategory`) — das ist Code, kein Anzeigetext
- die MQTT-Topic-Namen der Wärmepumpe (`main/...`) und die Feldnamen von `HEISHA_GetFunctions`
- feststehende Fachbegriffe — verbindliche Liste für dieses Modul:
  **COP**, **SG-Ready**, **MQTT**, **Topic**, **Modbus TCP**, **WebFront**, **Debug**, **PID**, **IPM**, **Delta-T**
- Produktbezeichnungen von Panasonic bzw. HeishaMon, die auf dem Gerät und in dessen Oberfläche so heißen:
  **Powerful-Modus**, **Smart-Warmwasser**, **Duty** (als Klammer-Erläuterung bei „Pumpenansteuerung (Duty)")
- etabliertes Lehngut: **Online/Offline** (im Duden)

Neue Texte werden im Code als englischer Schlüssel geschrieben und in `HeishaMon/locale.json` übersetzt.

**Vorgehen bei Umstellungen:** Ganze Sätze neu formulieren, nicht einzelne Wörter tauschen. Suchen-und-Ersetzen erzeugt zuverlässig zwei Fehlerklassen — gebrochene Genus-Kongruenz (aus „einen Portcheck" wird mit dem femininen „Port-Prüfung" ein Fehler) und vertauschte Objektbezüge (englisch „scan" heißt je nach Satz „absuchen" *oder* „finden": man durchsucht nicht die Zähler, die Suche findet sie nicht).

## Emojis

Entscheidung Dietmar (23.07.2026), verbundweit — ersetzt jede frühere „keine Emojis"-Vorgabe.

Emojis sind **erwünscht**, wo sie Nutzen stiften:

1. als **Panel-Icon** — ein Zeichen am Anfang einer ExpansionPanel-Überschrift (📖🔌📊), als Ersatz für das fehlende `icon`-Feld;
2. als **Status-/Aufmerksamkeitssymbol** (✅❌⚠️💡ℹ️) dort, wo etwas beim Lesen Aufmerksamkeit erfordert oder herausgestellt werden soll (Status, Warnungen, wichtige Hinweise).

Faktenlage: Kein Symcon-Store-Review hat Emojis je beanstandet; die frühere Regel war präventiv und ist aufgehoben. **Beobachtungsklausel:** Sollte ein Stable-Review Emojis bemängeln, entscheidet der Verbund neu (Rückfall: gemeinsam emoji-frei).

## Einheitliche Formular-Optik

Konvention Dietmar (24.07.2026), verbundweit, Referenzimplementierung InverterHub, Details in `EMS/SUITE.md`.

Reihenfolge von oben: (1) **🆕 Neu in Version X.Y** — aufgeklappt, pro Version bestätigbar (Attribut `SeenNews` speichert die bestätigte Version), keine Versionsnummer im Panel-Inhalt. (2) **📖 Dokumentation & Hilfe** — ganz oben vor den Funktionsfeldern, eingeklappt, Überschrift trägt die Modulversion. (3) Fachpanels; neue/wichtige Felder mit `🆕`-Präfix im Label. (4) Symcon-Forum-Hinweis nach den Haupteinstellungen, einmalig ausblendbar.

**Pflege ist Pflicht bei jedem Fix/Update, nicht nur bei großen Releases:** Bei jeder Änderung prüfen, ob sie ins Neu-in-Version-Panel gehört — die Antwort darf „nein" sein (z. B. reine interne Umbauten, Testergänzungen), aber die Prüfung selbst darf nicht entfallen.

**Layout-Qualität:** logische Gruppierung (Doku vor Funktionsfeldern, Zusammengehöriges in einem Panel), Step-by-Step ohne Scroll-Zickzack (keine Sprünge zwischen Kernfeldern und Nebenpanels), Feldkanten auf einer Linie statt kreuz und quer.

Aktueller Forum-Link zeigt auf die allgemeine PHP-Module-Kategorie (`community.symcon.de/c/erweiterungen/php-module-entwicklung/21`), da kein bestätigter HeishaMon-eigener Thread existiert — bei Bedarf durch den konkreten Thread ersetzen.

**Feld-Tooltips:** Symcon kennt keine nativen Mouseover-Tooltips (form.json/Listenspalten haben kein `tooltip`-Attribut). Für erklärungsbedürftige Einzelfelder ein `PopupButton` direkt daneben in einem `RowLayout` — kurze, immer sichtbare Erklärungen bleiben als `Label`.

**Caption = die volle Frage MIT Gegenstand, nicht bloß `"?"`** (Dietmars finale Entscheidung, verbundweit, 13.09.2026, per EMS nachgeschärft am selben Tag — Ablösung der früheren `"?"`-Fassung nach Praxiseinsatz in MeterHub, dort bereits am 01.09.2026 in SUITE.md festgehalten): der Nutzer soll schon am Formular sehen, WELCHE Frage sich per Klick beantworten lässt, statt erst ein rätselhaftes Symbol anklicken zu müssen. Die Frage muss den konkreten Gegenstand benennen, nicht nur "das"/"hier" - z. B. `"Warum muss der Zähler kumulativ sein?"` statt nur `"Warum ist das wichtig?"`, sonst sind mehrere Knöpfe im selben Formular nicht unterscheidbar (genau dieser Fehler ist uns beim ersten Anlauf passiert und musste nachgebessert werden). Endet die Frage bereits mit einem Fragezeichen (Normalfall), kein zweites anhängen, kein zusätzliches ❓-Symbol. `width` an der tatsächlichen Textlänge der (deutschen!) Frage ausrichten, nicht mehr am alten 70px-Quadrat für ein einzelnes Zeichen — bei uns wie in MeterHub 400–460px für einzeilige Fragen; unter ~70-80px hat `width` im WebFront-Skin ohnehin keinen sichtbaren Effekt. Popup-`caption` ist identisch mit der Button-Beschriftung (dieselbe Frage taucht im geöffneten Popup als Titel wieder auf). Umgesetzt bei allen neun PopupButtons des Formulars (MQTTTopic, DebugUnknownTopics, PowerVariable, EnergyVariable, COPMinPower, DeviceIP, Taktschutz Heizen/Kühlen, SmartGridMode).

## Idents sind API

Variablen-Idents werden **nie** umbenannt — sie sind die Schnittstelle für Skripte, Archiv und andere Module. Änderungen nur additiv. Dasselbe gilt für die Rückgabestruktur von `HEISHA_GetFunctions` (Erweiterung nur durch neue Felder).

**Pflicht vor jeder neuen `HEISHA_GetFunctions`-Erweiterung (EMS-Anweisung, 17.08.2026):** Zuerst das „Kanonische Feldregister: Type=>'heatpump'-Vertrag" in `EMS/SUITE.md` prüfen (führt alle Felder mit Bedeutung, seit-Version und liefernden Modulen) und den neuen Feldnamen dort eintragen lassen (Meldung an EMS), BEVOR gebaut wird. Hintergrund: `outsideTempID` (wir) vs. `outdoorTemperatureID` (WPHub) war dieselbe Größe unter zwei Namen — die Drift entstand, weil niemand den Feld-Gesamtbestand beider Wärmepumpen-Module sah. Kanonisch ist seither `outsideTempID`; WPHub liefert übergangsweise beide.

## Zweige

- `beta` — Entwicklung und schnelle Auslieferung an Tester (Installation per GitHub-URL)
- `main` — geprüfter Stand, den Nutzer über den IP-Symcon Module Store beziehen
- `ems-integration` — verbundweiter Zweig für die laufende EMS-Integrationsphase, abgezweigt von `beta`

**Solange die EMS-Integrationsphase läuft (seit 25.07.2026, verbundweite Anweisung):** ausnahmslos alles auf `ems-integration` pushen, keine Ausnahme mehr für „sichere" Fixes direkt auf `beta`. Erst nach Bewährung und Freigabe wandert der Stand von `ems-integration` zurück nach `beta`. Diese Regel endet erst durch eine ausdrückliche neue Ansage — nicht von selbst nach einer gewissen Zeit annehmen, dass sie ausgelaufen ist.

Die Übernahme nach `main` entscheidet Dietmar von sich aus (nicht nachfragen, siehe Feedback-Notiz „beta→main-Freigabe" im Gedächtnis).

## Tests

Vor jedem Push `php test_module.php` im übergeordneten Arbeitsverzeichnis ausführen (gemockter IPS-Kern, deckt Empfang, Steuerung, COP-Berechnung, Datenpunkt-Auswahl, Verknüpfungsstruktur und den `GetFunctions`-Vertrag ab).

## IP-Symcon-Stolperfallen

- Schaltbare Variablen brauchen eine eingabefähige Darstellung (Schalter, Auswahlliste, Schieberegler mit MIN/MAX oder `VARIABLE_PRESENTATION_VALUE_INPUT`).
- Darstellungen nur bei tatsächlicher Abweichung schreiben — sonst Update-Sturm in der Konsole.
- In `onClick`-Skripten von Schaltflächen gibt es kein `$_IPS['TARGET']`, die Instanz-ID heißt `$id`.
- Schaltflächen dürfen keine Eigenschaften per `IPS_SetProperty` + `IPS_ApplyChanges` persistieren, sondern nur die offene Maske per `UpdateFormField` ändern.
- Nicht editierbare Listenspalten benötigen `"save": true`, sonst gehen ihre Werte beim Übernehmen verloren.


## Verbund-Manifest SUITE.md — Bezugsquelle (geändert 31.08.2026)

SUITE.md liegt seit 31.08.2026 NICHT mehr in einem GitHub-Repo (die
Modul-Repos sind öffentlich, SUITE.md enthält das komplette Architektur-/
Debugging-Know-how des Verbunds — Dietmars Entscheidung). Primärquelle ist
ausschließlich die lokale Datei `/Users/dietmar/Nextcloud/Claude/SUITE.md`
auf Dietmars Maschine, versioniert in einem eigenen lokalen Git-Repo ohne
Remote. Frühere Kopien dieses Dokuments wurden zusätzlich aus der Historie
aller Modul-Repos entfernt (`git filter-repo` + Force-Push). Kein
Fallback-Link mehr — ohne lokalen Zugriff auf Dietmars Maschine ist SUITE.md
nicht einsehbar.

## Verbund-Status-Kopfzeile bei künftigen Discovery-Panels (20.08.2026)

Aktuell nicht anwendbar (HeishaMon hat keine Geräte-such-/Discovery-Funktion,
nur passiven MQTT-Empfang) — aber falls das Modul irgendwann eine bekommt
(z. B. 1-Wire- oder S0-Geräte-Suche): Muster aus SUITE.md ("Einheitliche
Verbund-Status-Kopfzeile") von Anfang an übernehmen. Button zuerst, direkt
darunter EINE Zeile `<Icon> <Zahl> <Was> gefunden (zuletzt <HH:MM:SS> Uhr).`
(✅/⚠️/ℹ️), technische Details in ein eingeklapptes Unter-Panel. Referenz:
EMS' `getDiscoverySummaryLine()` (module.php, 0.21.5).
