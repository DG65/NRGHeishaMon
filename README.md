# HeishaMon

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Modul Version](https://img.shields.io/badge/Modul_Version-1.47.1-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
[![Check Style](https://github.com/DG65/NRGHeishaMon/actions/workflows/check-style.yml/badge.svg)](https://github.com/DG65/NRGHeishaMon/actions/workflows/check-style.yml)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

[English version](README.en.md)

Symcon Modul zur Anbindung eines [HeishaMon](https://github.com/heishamon/HeishaMon) an eine Panasonic Aquarea Wärmepumpe über MQTT.

Teil des **NRG-Stack** — dem Energie-Modulverbund von DG65.

## Haftungsausschluss

Die Nutzung dieses Moduls erfolgt **auf eigenes Risiko**. Das Modul sendet Befehle an den HeishaMon und damit direkt an die Wärmepumpe (z. B. Solltemperaturen, Betriebsarten, Servicemenü-Werte wie die maximale Pumpenleistung). Der Autor übernimmt **keine Verantwortung oder Haftung** für Schäden am HeishaMon, an der Wärmepumpe oder an sonstigen Anlagenteilen sowie für Folgeschäden, die durch die Verwendung dieses Moduls entstehen. Es besteht kein Anspruch auf Support oder Fehlerfreiheit.

## Funktionsumfang

- Empfängt alle HeishaMon-Datenpunkte (`main/TOP0` … `main/TOP143` sowie die Optional-PCB-Topics) über den Symcon MQTT Server oder MQTT Client.
- Legt Statusvariablen **automatisch** an, sobald der HeishaMon das jeweilige Topic sendet — es erscheinen also nur die Datenpunkte, die die eigene Wärmepumpe tatsächlich liefert.
- Passende Darstellungen: Temperaturen mit °C, Leistungen mit W, Betriebsarten als Auswahlliste, Zustände als Schalter.
- Schreibbare Werte (z. B. Warmwasser-Solltemperatur, Betriebsart, Flüstermodus, Powerful-Modus) sind direkt schaltbar und werden über `<Basistopic>/commands/SetXxx` an den HeishaMon gesendet.
- Verfügbarkeitsanzeige über das LWT-Topic (Variable „Erreichbar").
- Zweisprachig Deutsch/Englisch (`locale.json`); englische Anleitung in [README.en.md](README.en.md).

## Voraussetzungen

- Symcon ab Version 9.0
- HeishaMon mit aktivierter MQTT-Anbindung. Empfohlen ist die aktuelle HeishaMon-Firmware ([Download und Update](https://github.com/heishamon/HeishaMon/releases/latest), mindestens 4.4.1 für alle Funktionen dieser Version); ältere Firmware läuft mit weniger Funktionen weiter
- Eingerichteter MQTT Server (oder MQTT Client) in Symcon, mit dem der HeishaMon verbunden ist

## Installation

### Über den Module Store (empfohlen)

Das Modul ist im **Symcon Module Store** verfügbar und lässt sich direkt aus Symcon heraus installieren: Module Store öffnen, nach **HeishaMon** suchen und installieren. Aktualisierungen kommen dann automatisch über den Store.

### Über die Modulverwaltung (URL)

Alternativ über die Modulverwaltung (Kern Instanzen → Modules) die URL dieses Repositories hinzufügen:

```
https://github.com/DG65/NRGHeishaMon
```

Hinweis: Eine über URL installierte Bibliothek wird nicht automatisch auf die Store-Version umgestellt. Wer später auf den Module Store wechseln möchte, entfernt die URL-Installation in der Modulverwaltung und installiert das Modul aus dem Store neu — die GUIDs sind identisch, bestehende Instanzen samt Variablen und Archivdaten bleiben dabei erhalten.

## Einrichtung

1. Instanz **HeishaMon** anlegen.
2. Als übergeordnete Instanz den MQTT Server bzw. MQTT Client auswählen, mit dem der HeishaMon verbunden ist.
3. **MQTT Basistopic** eintragen (Standard: `panasonic_heat_pump`, muss dem im HeishaMon konfigurierten Basistopic entsprechen).
4. Übernehmen — die Variablen werden automatisch angelegt, sobald der HeishaMon Daten sendet (spätestens beim nächsten Sendeintervall des HeishaMon).

## Datenpunkte auswählen

In der Instanz-Konfiguration listet die Tabelle **Datenpunkte** alle bekannten HeishaMon-Topics. Die Spalte **Empfangen** zeigt, welche Topics die eigene Anlage tatsächlich sendet. Über die Spalte **Aktiv** lassen sich einzelne Datenpunkte abwählen — deren Variablen werden **ausgeblendet**. Objekt-ID, Wert-Aktualisierung und Archivdaten bleiben dabei erhalten; beim erneuten Aktivieren wird die Variable einfach wieder eingeblendet. Nur Datenpunkte, deren Variable noch gar nicht existiert, werden bei deaktivierter Auswahl auch nicht angelegt.

**Neue Datenpunkte der Firmware 4.4:** Die Firmware 4.3/4.4 meldet über 50 zusätzliche Werte (TOP144–TOP198). Nur zehn davon legt das Modul von selbst an: tatsächlicher Flüster- und Powerful-Modus, Aktivität von Zone 1 und 2, Heizwasser-Ziel inklusive Pufferdifferenz, Nennleistung und Typ der Wärmepumpe, Abtausignal sowie die Höchstzeiten für Heizbetrieb und Warmwasser-Aufheizung. Die übrigen 45 (Rückmeldungen der Installateurmenüs, SG-Ready-Werte, Fühlerabgleiche, Bivalenz, Zusatzpumpe) sind in der Liste **abgewählt**; wer sie braucht, wählt sie dort an. Ob die eigene Anlage einen Wert liefert, hängt vom Modell ab.

**Hinweis für die Platine (Firmware ab 4.4.0):** Die Topic-Gruppe **Installer settings** ist ab Werk ausgeschaltet. Dazu gehören zwölf Werte, die dieses Modul kennt (`Max_Pump_Duty`, `Pump_Flowrate_Mode`, `Liquid_Type`, `Anti_Freeze_Mode`, `Buffer_Installed`, `DHW_Installed`, `Alt_External_Sensor`, `Z1_Sensor_Settings`, `Z2_Sensor_Settings`, `Heating_Control`, `Quiet_Mode_Priority`, `DHW_Sensor_Selection`). Bleibt die Gruppe aus, stehen die Variablen auf dem letzten Wert still. In der Weboberfläche der Platine unter *Einstellungen → Advanced → Topic groups* einschalten und speichern. Das Modul zeigt dazu im Formular einen Hinweis, wenn eine Firmware ab 4.4.0 gemeldet wird und diese Variablen seit Stunden nicht aktualisiert wurden. Firmware herunterladen: [HeishaMon-Releases](https://github.com/heishamon/HeishaMon/releases/latest).

Die Zeilen lassen sich durch **Ziehen mit der Maus sortieren** — die Variablen unter der Instanz und die Verknüpfungen in der Verknüpfungsstruktur folgen dieser Reihenfolge, sobald die Änderungen übernommen werden. Die Schaltfläche **Reihenfolge und Auswahl zurücksetzen** stellt den Standard wieder her.

## 1-Wire-Sensoren

Temperatursensoren (z. B. DS18B20) am 1-Wire-Bus des HeishaMon senden auf einem eigenen, adressabhängigen Topic statt der festen Themenliste der Wärmepumpe. Das Modul erkennt sie automatisch, sobald ein erster Messwert eintrifft, und trägt sie im Bereich **1-Wire-Sensoren** mit ihrer Bus-Adresse ein. Dort lässt sich pro Sensor ein sprechender **Name** vergeben (z. B. "Vorlauf Pufferspeicher") und die Aktivierung wie bei den übrigen Datenpunkten steuern. Ohne vergebenen Namen erscheint zunächst ein generischer Platzhalter mit den letzten vier Stellen der Adresse.

## S0-Zähler

Die HeishaMon-Platine kann bis zu zwei S0-Stromzähler direkt auswerten (GPIO12 und GPIO14, siehe HeishaMon-Doku). Das Modul legt dafür je Port **Leistung** (W) und **Gesamtenergie** (kWh, aus den Wh-Rohwerten umgerechnet) als Datenpunkte an. Wer den Stromverbrauch der Wärmepumpe über einen solchen S0-Zähler misst, kann die Gesamtenergie-Variable direkt als **Energiezähler im Bereich "Externer Stromzähler"** auswählen — echte COP-/Arbeitszahl-Messung ganz ohne zusätzlichen Messaktor.

## Platinen-Diagnose

Aus dem `stats`-Topic der Firmware pflegt das Modul die Gruppe **Platinen-Diagnose**: WLAN-Qualität (%), Laufzeit seit Platinen-Neustart, MQTT-Neuverbindungen, Bus-Lesequalität (Anteil fehlerfreier Datagramme vom CN-CNT-Bus), aktive Regeln (zeigt u. a., ob das aufgespielte Taktschutz-Regelwerk läuft) und Firmware-Version. Aktiviert man im Formular die Option „Monitoring-Datenpunkte automatisch archivieren" (seit 1.33.0 ein Opt-in, keine Standardeinstellung mehr), werden WLAN-Qualität und MQTT-Neuverbindungen mitgeloggt — bei Verbindungsabbrüchen zeigt der Verlauf dann, ob schwaches WLAN, MQTT-Hänger, heimliche Neustarts (Laufzeit-Sprünge) oder Busfehler (Lesequalität) die Ursache sind.

## Verknüpfungsstruktur (gruppierte Ansicht)

Statusvariablen müssen in Symcon flach unter der Instanz liegen — im WebFront wird daraus schnell eine lange, unübersichtliche Liste. Für eine übersichtliche, gruppierte WebFront-Navigation kann das Modul optional eine **Verknüpfungsstruktur** pflegen: Im Bereich **Verknüpfungsstruktur** die Option **Verknüpfungsstruktur erzeugen** aktivieren und eine **Zielkategorie** wählen (am besten eine, die selbst im WebFront sichtbar ist). Das Modul legt dort einen Kategoriebaum an:

```
<Zielkategorie>
└── <Instanzname>
    ├── Betrieb
    ├── Heizen
    ├── Kühlen
    ├── Warmwasser
    ├── Leistung & COP
    ├── Gerätewerte
    ├── Anlagenkonfiguration
    ├── Optionale Platine
    ├── S0-Zähler
    ├── 1-Wire-Sensoren
    └── Platinen-Diagnose
```

Darin liegen Verknüpfungen auf alle **aktiven** Datenpunkte (inklusive Schaltbarkeit — eine Verknüpfung auf die Warmwasser-Solltemperatur bleibt z. B. ein Schieberegler). Wird ein Datenpunkt in der Liste abgewählt, verschwindet seine Verknüpfung automatisch; neu empfangene Datenpunkte werden sofort einsortiert. Leere Gruppen werden entfernt.

## COP / Arbeitszahl

Das Modul berechnet den COP auf zwei Wegen:

- **COP (HeishaMon-Schätzung)** — automatisch aus den HeishaMon-eigenen Werten (thermische Leistung / elektrische Aufnahme über alle Betriebsarten). Keine Konfiguration nötig, aber grob, da Panasonic die Aufnahme nur in ~200-W-Stufen schätzt. Bei Wärmepumpen der K/L-Serie und neuer liefern die alten Leistungs-Topics laut HeishaMon-Firmware ungültige Werte (z. B. -200); dort meldet die Platine die Leistungen im **Extra-Datenblock** (`extra/…`-Topics, ab Firmware 4.1.7). Das Modul legt diese als Datenpunkte an und nutzt sie ab Version 1.31.0 automatisch für elektrische Leistung, thermische Gesamtleistung und diese COP-Schätzung, sobald sie eintreffen.
- **COP (gemessen)** — über einen externen Stromzähler (z. B. Shelly 3EM auf der Wärmepumpen-Phase). Hat schon MeterHub einen Zähler mit Funktionszuordnung „Wärmepumpe", übernimmt das Modul ihn ab Version 1.34.0 automatisch (Statuszeile im Panel **Externer Stromzähler**, folgt späteren Änderungen der Zuordnung) — es ist nichts einzustellen, nichts wird dabei in eine eigene Property kopiert. Nur wer einen anderen Zähler nutzen will oder kein MeterHub installiert hat, trägt im eingeklappten Bereich „Eigenen Zähler stattdessen verwenden" selbst eine Variable ein; eine eigene Wahl hat dabei immer Vorrang vor MeterHub. Der COP wird bei jeder Wertänderung neu berechnet, unterhalb der **Mindestleistung** (Standard 100 W, gegen Standby-Rauschen) wird 0 ausgegeben.

Liefert der Zähler (automatisch oder eigen) zusätzlich einen Energiezähler, berechnet das Modul Tageswerte:

- **Stromverbrauch heute** — exakt aus dem Zählerstand (Basis wird um Mitternacht neu gesetzt, ein Zähler-Reset wird abgefangen)
- **Wärmemenge heute** — Integration der thermischen Leistung im 60-Sekunden-Takt (Zwischenstände überleben einen IPS-Neustart)
- **Arbeitszahl heute** — Verhältnis der beiden; mit Aufzeichnung im Archiv entsteht daraus die Langzeit-Historie

**Aufschlüsselung nach Heizen, Warmwasser und Bereitschaft** (ab Version 1.42.0, nur mit Energiezähler): Das Modul zeigt zusätzlich, wohin die Energie geht und wie gut jede Betriebsart arbeitet: `Heizen: Wärmemenge heute`, `Warmwasser: Wärmemenge heute`, `Heizen: Stromverbrauch heute`, `Warmwasser: Stromverbrauch heute`, `Bereitschaft: Stromverbrauch heute` sowie die Arbeitszahlen `Arbeitszahl Heizen heute` und `Arbeitszahl Warmwasser heute`. Den Strom ordnet das Modul im Minutentakt zu: Läuft der Verdichter und steht das 3-Wege-Ventil auf Warmwasser, geht er ins Warmwasser, läuft der Verdichter sonst, ins Heizen (Kühlen zählt dazu), und steht der Verdichter, in die Bereitschaft (Steuerung, Pumpen, Kurbelwannenheizung). An den Umschaltpunkten ist die Zuordnung ungenau, im Tagesmittel aber belastbar. Die Werte lassen sich wie die übrigen Monitoring-Datenpunkte archivieren.

Hinweis: Läuft der Heizstab, steckt seine Wärme in der gemessenen thermischen Leistung. Da nur die Wärmepumpen-Phase im Nenner steht, fällt der COP in diesen Phasen optisch zu gut aus — für die reine Verdichter-Bewertung ist das aber genau richtig.

## Heizungs-Automatik (optional)

Schaltet **nur den Heizbetrieb der Räume** ein und aus, nie die ganze Wärmepumpe: Das Modul sendet `SetOperationMode` und wechselt zwischen *Heizen + Warmwasser* (4, oder *Auto + Warmwasser* 6), *Kühlen + Warmwasser* (5) und *nur Warmwasser* (3). Das Warmwasser läuft in jedem Zustand weiter. Ausgeliefert ist die Funktion **aus**; im Formular unter „Heizungs-Automatik" aktivieren, dann wirkt sie erst, wenn die Schaltervariable `Heizungs-Automatik` (`HeatAuto_Active`) eingeschaltet wird.

Alle 15 Minuten wird in dieser Reihenfolge entschieden:

1. **Frostschutz** — Außenmittel unter 5 °C oder ein Raum unter 10 °C: Heizen, die Sperrzeit wird übergangen. Fehlen die Außendaten, bleibt die Heizung ebenfalls an.
2. **Kalt** — kurzes Außenmittel (Standard 24 h) unter 12 °C: Heizen.
3. **Warm** — kurzes UND langes Mittel (Standard 48 h) über 16 °C und das lange Mittel zu mindestens 90 % belegt: Heizung aus. Das lange Mittel macht das Ausschalten bewusst träge.
4. **Dazwischen** — wahlweise entscheiden die Räume (Mittel der gewählten Wandfühler): länger als die Verzögerung (2 h) unter dem Mindestwert (19,5 °C) → Heizen, über Mindestwert + Hysterese (1,5 K) → Heizung aus; sonst bleibt der Zustand.
5. **Sperrzeit** — nach einem Wechsel bleibt der Zustand 12 h (das Wiedereinschalten schon nach einem Viertel davon).

Rangfolge: **Frostschutz > Nutzer > Automatik.** Ändert jemand die Betriebsart von außen (z. B. im Dashboard), übernimmt die Automatik das, sendet keinen Gegenbefehl und beginnt die Sperrzeit neu; nur der Frostschutz greift sofort ein. Auto+Warmwasser statt Heizen+Warmwasser gilt als dieselbe Variante.

**Quellen werden automatisch gesucht**, damit nichts von Hand eingestellt werden muss (eigene Auswahl überschreibt immer; die Statuszeilen im Formular zeigen 🔗 automatisch / ✏️ eigen / ℹ️ nichts gefunden):

- **Außentemperatur:** der Fühler der Wärmepumpe, wahlweise eine eigene Variable.
- **Feuchte:** alle frischen, plausiblen Variablen mit Feuchteprofil (`~Humidity*`) oder „Luftfeuchte/Humidity" im Namen, ohne Außen-, Boden- und Wetterwerte (ein ausdrücklicher „Innen"-Wert einer Wetterstation bleibt). Es zählt der **höchste** Wert, der Taupunkt wird mit der höchsten Raumtemperatur gerechnet (ohne bekannte Raumtemperatur mit 26 °C). Fehlgriffe gehen so nur auf die sichere Seite: Das Kühlen wird eher gesperrt als freigegeben.
- **Räume:** ein Temperaturfühler je Raum der **Raumstruktur** (Wandfühler vor Heizkörper-Thermostat), ohne Außen-/unbeheizte Räume (Carport, Terrasse, Keller …; die Ausschlussliste ist im Formular editierbar, z. B. um Wintergarten oder Werkstatt ergänzbar und gilt auch für die Feuchtesuche), erst ab drei Räumen mit gültigem Wert, mit dem **Median** statt dem Mittelwert (ein Fühler neben einer Wärmequelle entscheidet nicht allein). Ohne Raumstruktur und ohne eigene Auswahl entscheidet nur die Außentemperatur.

**Preis-Sperre** (optional, für dynamische Stromtarife): In den teuersten Stunden des Tages pausiert die Automatik den Heizbetrieb (Warmwasser bleibt unberührt), damit die Wärmepumpe nicht ausgerechnet dann läuft. Die Preiskurve wird automatisch gefunden (Tibber Grid Rewards, Börsenpreis) oder kommt aus einer eigenen Preisvariable (ct/kWh, braucht einen Tag Verlauf). „Teuer" ist ab der größeren Grenze aus 85. Perzentil der nächsten 24 Stunden und Median plus 5 ct/kWh; bei flachem Preisverlauf wird nie gesperrt. Gesperrt wird nur bei laufender Heizung, wenn die Räume Reserve haben (ohne Raumfühler: wenn es draußen deutlich über der Frostgrenze liegt), höchstens zweimal pro Tag und höchstens zwei Stunden am Stück; Frostschutz und Raumkomfort beenden jede Sperre. Trägt eine Batterie die Wärmepumpe durch die teuren Stunden ohnehin (Kapazität eintragen, Ladestand wird aus InverterHub gelesen), wird nicht gesperrt.

**Kühlen** (optional) braucht eine Taupunkt- oder Feuchte-Quelle der Raumluft (automatisch gefunden oder eigene): Es startet nur, wenn das tiefste Kühlziel der Kühlkurve mindestens den Sicherheitsabstand (2 K) über dem Taupunkt liegt, und stoppt sofort, wenn die gemessene Vorlauftemperatur darunter fällt. Die Kühlkurve selbst ändert das Modul nicht.

Jeder Befehl wird nach ca. 45 s über die gemeldete Betriebsart kontrolliert und bis zu dreimal wiederholt (die Anlage quittiert Befehle nie). Die Variable `Heizungs-Automatik: Begründung` nennt im Klartext, warum gerade so entschieden wurde. Beim Ausschalten der Automatik wird ein abgeschalteter Heizbetrieb wiederhergestellt. Zustand und Einstellungen sind per `HEISHA_GetHeatingAutomation()` lesbar (inkl. Grenzen, Schrittweite, Einheit und Standardwert je Einstellung sowie einer `configRevision`), per `HEISHA_SetHeatingAutomation()` als Teil-Update änderbar: Rückgabe `ok`, Feldfehler in `errors`, die geltende `config` und die neue `configRevision`; wird eine veraltete Revision mitgeschickt, ändert sich nichts. Gedacht für den WP-Monitor des NRGDashboards.

## Zeitplan für ECO / Komfort und Flüsterbetrieb (optional)

Ein Wochenplan, der nach Wochentag und Uhrzeit die **Verschiebung der Heiz- und Kühlanforderung** (ECO −5 K, Normal 0, Komfort +5 K; Zone 1 und Zone 2, Heizen und Kühlen) und die **Stufe des Flüsterbetriebs** (Aus, Stufe 1…3) setzt. Die Wärmepumpe kann so einen Plan nicht selbst speichern (HeishaMon schreibt nur Werte), deshalb führt das Modul ihn aus. Bearbeitet wird er grafisch im Wärmepumpen-Monitor des NRGDashboards; im Formular schaltet man die Funktion nur ein und sieht den aktuellen Stand.

- **Aufbau:** je Kanal (Zone 1/2 × Heizen/Kühlen, Flüsterbetrieb) Abschnitte mit Wochentag, Beginn, Ende (15-Minuten-Raster) und Wert; dazwischen gilt der Vorgabewert des Kanals. Zeiten sind die lokale Zeit des Symcon-Servers.
- **Ab Werk aus,** und ein Kanal nimmt nur teil, wenn er im Plan eingeschaltet ist. So schreibt nie ein zweiter Regler denselben Wert (z. B. ein alter Wochenplan für den Flüsterbetrieb).
- **Schonend:** ein Befehl geht nur raus, wenn sich der Sollwert ändert; nach ca. 45 s wird der gemeldete Wert kontrolliert und der Befehl höchstens dreimal wiederholt, danach steht eine Störung im Status.
- **Von Hand geändert** (z. B. im Dashboard): bleibt bis zur nächsten Schaltzeit stehen. Der Status zeigt, seit wann (`stateSince`), und das Protokoll bekommt eine Zeile, wenn ein Kanal auf „von Hand geändert“ oder „Störung“ wechselt. Mit `HEISHA_SetSchedule(…, '{"applyNow": true}')` setzt man den Plan sofort wieder durch.
- **Einschränkungen:** Die Verschiebung gilt nur, solange die Anlage eine Verschiebung meldet (nicht im Direktmodus), und das optionale Taktschutz-Regelwerk der Platine kann sie überschreiben. Die anlageneigene Flüster-Zeitschaltung (an der Fernbedienung) nicht parallel für denselben Zweck nutzen.
- Für Skripte und andere Module: `HEISHA_GetSchedule()` / `HEISHA_SetSchedule()` (Teil-Update je Kanal, Prüfung im Modul, optimistische Sperre über `revision`).

## Warmwasser-Zusatzlauf für das EMS (optional)

Das EMS kann bei PV-Überschuss einen **Warmwasser-Lauf** anfordern: Die Wärmepumpe lädt dann sofort den Warmwasserspeicher, wie mit der Warmwasser-Taste an der Anlage (Bedienbefehl `SetForceDHW`). Sollwerte, Heizkurven und Verschiebung werden dafür **nie** geändert; ob die Anlage solche Einstellwerte im EEPROM ablegt, ist nicht dokumentiert, deshalb nutzt das Modul nur diesen Bedienbefehl.

- **Ab Werk aus.** Im Bereich „EMS-Warmwasser-Zusatzlauf“ freigeben; erst dann gibt es die schaltbare Variable `Warmwasser-Zusatzlauf (EMS)` (`Optional_Run`) und meldet `HEISHA_GetFunctions()` `optionalRunID` und `controllable`.
- **Start nur unter Bedingungen (ein abgelehnter Wunsch bleibt zwei Stunden gespeichert, einstellbar, und startet selbst, sobald die Bedingungen erfüllt sind; ein Start verbraucht ihn):** Betriebsart mit Warmwasser, keine Abtauung, kein Legionellenschutz, kein erzwungener Heizstab, Warmwasser mindestens 3 K unter dem Sollwert, mindestens 60 Minuten seit dem letzten Start, höchstens 4 Starts in 24 Stunden (Abstand, Zahl und Differenz einstellbar). Sonst wird nichts gesendet; der Grund steht in der Statuszeile und im Protokoll.
- **Kontrolle:** Die Anlage quittiert Befehle nie; nach ca. 45 s wird der Zustand über `Force_DHW_State` geprüft, der Befehl höchstens dreimal wiederholt.
- **Abbruch:** `false` bricht nur einen vom Modul gestarteten Lauf ab, ein von Hand gestarteter bleibt unberührt. Ein selbst gestarteter Lauf wird nach 2 Stunden (einstellbar) beendet.
- **Lesewert:** `Optional_Run` zeigt den tatsächlichen Zustand der Anlage, nicht den Wunsch. Das EMS merkt sich seinen Wunsch selbst.
- Leistungsfelder (`optionalRunPowerID`, `optionalRunPowerMaxID`) und Leistungsgrenze (`powerLimitID`, `powerLimitActiveID`) liefert das Modul nicht (0); `contractVersion` '1.18'.

### Raumführung der Verschiebung (optional, ab Version 1.43.0)

Ein langsamer Regler, der die geplante Heiz-Verschiebung des Zeitplans an die Räume anpasst. Alle 10 Minuten notiert das Modul die Raumtemperatur (dieselben Räume wie die Heizungs-Automatik: eigene Auswahl oder automatisch über die Raumstruktur, Median), solange geheizt wird. Nach mindestens 20 Stunden Daten vergleicht es das **24-Stunden-Mittel** mit dem Zielband (Standard 22,0 °C ± 0,5 K): Liegen die Räume darüber, sinkt ein **Versatz** um 1 K, liegen sie darunter, steigt er um 1 K. Höchstens eine Änderung je Haltezeit (Standard 24 Stunden) und höchstens ±3 K. Der Zeitplan setzt „geplanter Wert + Versatz“ mit seiner üblichen Kontrolle um, das Ergebnis bleibt zwischen −5 und +5 K. Es kommen also nur wenige Änderungen am Tag bei der Wärmepumpe an. Voraussetzung ist ein eingeschalteter Heiz-Kanal im Zeitplan. Ab Werk aus; Einstellung im Bereich „Zeitplan“. Der Versatz steht in der Variable `Raumgeführter Verschiebungs-Versatz` und in `HEISHA_GetSchedule()['roomShift']`.

### Sonnenwärme der Verschattung (optional, ab Version 1.45.0)

Zusätzliche Eingangsgröße der Raumführung (geschaltet mit der Variable „Sonnenwärme der Verschattung nutzen“, `RoomShift_SolarAssist`, ab Version 1.46.0; vorher ein Häkchen im Formular, das einmal übernommen wird): Ist das Modul **NRG-Stack Verschattung** (ab 0.11, Vertrag `shading` 1.1, `VSCH_GetState`) installiert, kann das Modul die Verschiebung der Heiz-Kanäle um **1 bis 2 K** absenken, solange die Sonne das Haus kräftig heizt. Ab Werk aus; es braucht den Zeitplan und einen eingeschalteten Heiz-Kanal. Die Absenkung läuft wie der Versatz der Raumführung über den Zeitplan (einziger Schreiber der Verschiebung, mit Kontrolle und Flatter-Schutz). Das Modul liest die Verschattung nur und schreibt nie hinein.

- **Schnelle Heizung (Heizkörper):** zählt der aktuelle Wert `gainNowW` (Sonnenwärme jetzt in W, mit den Behängen, wie sie stehen; `null` gilt als keine Sonne). Erreicht er die Schwelle (Standard 1500 W), wird abgesenkt; unter 60 Prozent der Schwelle kehrt die Verschiebung zum Plan zurück. Das braucht keine Prognose.
- **Träge Heizung (Fußboden):** zählt die Erwartung `gainNextHoursWh` (Mittel der nächsten 1–6 Stunden, Standard 3). Das gilt nur, wenn die Verschattung `basis: "forecast"` meldet, also die Wolkenprognose eingerechnet hat; bei `clearSky` bleibt der Plan unverändert.
- **Automatisch** (Standard) richtet sich nach der Einstellung „Heizung in den Räumen“ der Verschattung (`heating`: Heizkörper → aktueller Wert, Fußboden/gemischt → Erwartung, unbekannt → aktueller Wert); im Formular lässt sich die Reaktion der Heizung auch festlegen.
- **Schutz:** höchstens eine Änderung je halbe Stunde (schont den Verdichter); nie absenken, wenn die Räume unter dem Zielband der Raumführung liegen; bei fehlender, zu alter (über 30 Minuten) oder nicht auf Heizen stehender Verschattung, ohne Wolkenprognose (träge Heizung) und wenn die Heizung nicht läuft, gilt der Plan sofort unverändert. Wird die Raumführung gleichzeitig benutzt, addieren sich beide Versätze innerhalb der Kanalgrenzen (−5 bis +5 K).
- `HEISHA_GetSolarAssist($id)` (ab 1.46.0, rein lesend) meldet `available`, `enabled`, `active`, `offsetK`, `inertia`, `thresholdW`, `switchIdent`/`switchID` und `reason`; geschaltet wird per `RequestAction` auf die Variable `RoomShift_SolarAssist`, nie über eine Eigenschaft.
- `HEISHA_GetSchedule` meldet den Zustand unter `roomShift.solar` (`enabled`, `offset`, `powerW`, `thresholdW`, `changedAt`, `reason`).

### Heizkurven-Vorschlag (optional, ab Version 1.47.0)

Das Modul vergleicht das **24-Stunden-Mittel der Raumtemperatur** (dieselben Räume wie die Heizungs-Automatik, Median) mit der **Ziel-Raumtemperatur** der Raumführung (Toleranz wie dort) und schlägt vor, wie sich die Heizkurve von Zone 1 ändern ließe. Es **zeigt nur an und schreibt nie** in die Wärmepumpe, weil nicht bekannt ist, wie viele Schreibvorgänge deren Speicher verträgt (HeishaMon-README „EEPROM warning“: ein paar je Stunde und Einstellung sind vermutlich in Ordnung).

- Alle 10 Minuten notiert das Modul Raum-Mittel und Außentemperatur, solange geheizt wird; Zeiten mit Sonnenabsenkung zählen nicht. Nach mindestens 20 Stunden Daten wird gerechnet. Ändert sich, was die Vorlauftemperatur führt (Versatz der Raumführung, Zeitplan von Zone 1 Heizen oder die Heizkurve), beginnt die Messung neu (ab 1.47.1), damit eine schon umgesetzte Änderung nicht doppelt zählt.
- Liegen die Räume außerhalb der Toleranz, nimmt das Modul an, dass 1 K Raumtemperatur etwa **2,5 K Vorlauftemperatur** braucht (grobe Schätzung, höchstens 4 K je Vorschlag, Vorlauf 20–55 °C), und verteilt die Änderung nach der mittleren Außentemperatur auf Kalt-Punkt und Mild-Punkt der Kurve (kalter Tag: vor allem der Kalt-Punkt). Alternativ nennt es die gleichwertige Änderung der Verschiebung „Normal“ im Zeitplan.
- Der Vorschlag steht im Formular (Panel Zeitplan, unter der Raumführung) und in der Variable „Heizkurven-Vorschlag“; `HEISHA_GetCurveAdvice($id)` liefert ihn rein lesend (`ready`, `roomMeanC`, `targetC`, `errorK`, `outsideMeanC`, `deltaK` mit + = anheben, `curve` jetzt, `suggested`, `shiftK`, `reason`).
- Nicht abgedeckt: Zone 2 und Direktbetrieb (ohne Heizkurve).

## Energiespar-Prüfung

Der Bereich **💡 Energiespar-Prüfung** in der Instanz-Konfiguration bewertet die von der Anlage empfangenen Einstellwerte anhand von Richtwerten aus den dokumentierten [HeishaMon-Beispielregelwerken](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules) und dem Panasonic-Servicehandbuch: Taktverhalten (mittlere Laufzeit je Verdichterstart), Warmwasser-Sollwert und -Nachheizdelta, Heizstab-Freigabetemperatur und -Verzögerung, Heizgrenze, Heizkurven-Sollwert und Pumpenansteuerung. Die Prüfung ist **reine Anzeige** — es wird nichts verändert. Die bewerteten Werte sind Service-Einstellungen der Wärmepumpe: Änderungen bewusst vornehmen, im Zweifel mit dem Fachbetrieb.

## Energiespar-Regelwerke (auf der Platine)

Der Bereich **⚙️ Energiespar-Regelwerke** kann kuratierte Regelwerke direkt auf die HeishaMon-Platine spielen — sie laufen dort autonom weiter, auch wenn WLAN oder Symcon ausfallen. Erste Vorlage ist der **Taktschutz** (nach dem dokumentierten [`Compressor-Short-Cycle-Guard`](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules/Compressor-Short-Cycle-Guard)): Nach jedem Verdichterstopp wird der Wiederanlauf für eine einstellbare Zeit (Standard 45 min) unterdrückt, sofern es draußen mild ist (Standard ≥ 2 °C) — weniger Starts bedeuten bessere Effizienz und längere Verdichter-Lebensdauer; bei Kälte endet die Sperre sofort.

**Wichtig:** Das Aufspielen **ersetzt das komplette** auf der Platine gespeicherte Regelwerk. Wer dort eigene Regeln pflegt (z. B. das umfangreiche [Referenz-Regelwerk](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules/Jeisha-DHW-Radiators-Rowbuffer)), sollte diese Funktion nicht nutzen. Die HeishaMon-Firmware validiert jeden Upload selbst — ein ungültiges Regelwerk wird verworfen, das bisherige bleibt aktiv.

Abgrenzung im NRG-Stack (mit EMS abgestimmt): Platinen-Regelwerke bilden die autonome **Schutz-Schicht** (Taktschutz, Rampen); die energetische **Planung** (wann Warmwasser, SmartGrid-Modus, Pausen) bleibt bei EMS/Symcon — die Vorlagen enthalten deshalb bewusst keine Zeitplan-Blöcke.

## Elektrische Leistung & Integration in andere Module

Das Modul führt eine Variable **Elektrische Leistung (gesamt)** (W). Sie enthält den gemessenen Wert des externen Stromzählers, sofern konfiguriert — andernfalls die Summe der HeishaMon-Schätzwerte (Heizen + Kühlen + Warmwasser). Damit gibt es unabhängig vom Messaufbau immer eine einzelne, verlässliche Leistungsvariable, z. B. für Visualisierungen, ein Energiemanagement oder eigene Automationen.

Wer ein **eigenes Wärmepumpen-Modul** an den NRG-Stack anbinden will, findet die Anleitung in [EIGENES_WAERMEPUMPEN_MODUL.md](EIGENES_WAERMEPUMPEN_MODUL.md) (auch auf [Englisch](EIGENES_WAERMEPUMPEN_MODUL.en.md)).

Andere Module können die Wärmepumpe automatisch einbinden, ohne dass der Nutzer Variablen von Hand zuweisen muss. `HEISHA_GetFunctions()` implementiert dazu den gemeinsamen, herstellerneutralen `heatpump`-Vertragstyp des NRG-Stack (z. B. auch von WPHub für die Panasonic-Comfort-Cloud genutzt) — Konsumenten wie NRGDashboard rendern generisch anhand der tatsächlich vorhandenen Felder, unabhängig davon, welches Wärmepumpen-Modul sie liefert:

```php
$functions = HEISHA_GetFunctions(12345);
// [
//   [
//     'Type'     => 'heatpump',
//     'Caption'  => 'HeishaMon',   // Name der Instanz
//     'PowerID'  => 34567,         // Variable "Elektrische Leistung (gesamt)" in W
//     'EnergyID' => 45678,         // kWh-Zählerstand des externen Zählers, 0 wenn nicht konfiguriert
//     'Measured' => true,          // true = echte Messung, false = HeishaMon-Schätzung
//     'unit'     => 'W',           // physikalische Einheit von PowerID, informativ
//     'reachable' => true,         // false = Wärmepumpe offline, PowerID ggf. veraltet
//     // Ab contractVersion 1.3: Heizkreislauf-Datenpunkte fuer eine Anlagenschema-
//     // Visualisierung (z.B. NRGDashboard) - je 0, wenn der Datenpunkt (noch) nicht
//     // empfangen wurde oder abgewaehlt ist, unabhaengig vom Sichtbarkeitsstatus:
//     'pumpFlowID' => 34580, 'pumpSpeedID' => 34581, 'pumpDutyID' => 34582,
//     'threeWayValveStateID' => 34583, 'twoWayValveStateID' => 34584,
//     'mainInletTempID' => 34585, 'mainOutletTempID' => 34586,
//     'z1WaterTempID' => 34587, 'z2WaterTempID' => 34588, 'dhwTempID' => 34589,
//     'bufferTempID' => 34590, 'compressorFreqID' => 34591,
//     'dischargeTempID' => 34592, 'defrostingStateID' => 34593,
//     // Ab contractVersion 1.4: externe Heizkreispumpe/-Mischventil an der optionalen
//     // 2. Steuerplatine (getrennt von der internen Pumpe oben). Mischventil liefert eine
//     // Stellrichtung (0=Aus, 1=Zu, 2=Auf), keine absolute Position:
//     'z1PumpID' => 34594, 'z1MixingValveID' => 34595,
//     'z2PumpID' => 0, 'z2MixingValveID' => 0,   // 0 = keine Zone 2 vorhanden
//     // Ab contractVersion 1.5: Luefterdrehzahl Aussengeraet in U/min:
//     'fan1SpeedID' => 34596, 'fan2SpeedID' => 0,   // 0 = nur ein Luefter verbaut
//     // Ab contractVersion 1.6: Sauggas-/Kaltgastemperatur (Gegenstueck zu dischargeTempID);
//     // bei Panasonic die beste verfuegbare Messstelle Eva_Outlet_Temp (Verdampferaustritt):
//     'suctionTempID' => 34597,
//     // Ab contractVersion 1.7: Betriebsart, Mischventil-Position und Innenrohr-Temperatur:
//     'operatingModeID' => 34598,           // ROHER Panasonic-Enum 0-8, nur fuer Diagnose -
//                                           // zum Ablesen operatingModeNormID verwenden!
//     'z1MixingValvePositionID' => 34599,   // Mischventil-Position 0-100 % (absolute Position,
//     'z2MixingValvePositionID' => 0,       // im Gegensatz zur Stellrichtung in z1/z2MixingValveID)
//     'indoorPipeTempID' => 34600,          // im Kuehlbetrieb die tatsaechlich kalte Kaeltemittelseite
//     // Ab contractVersion 1.8: vereinheitlichte Betriebsart, herstellerneutraler Verbund-Enum
//     // (SUITE.md): 0=standby, 1=heating, 2=cooling, 3=dhw, 4=heating+dhw, 5=cooling+dhw,
//     // -1=unbekannt. Vom Modul aus dem Panasonic-Enum abgeleitet (Auto-Modi -> aktive Richtung):
//     'operatingModeNormID' => 34601,
//     // Ab contractVersion 1.9: COP/Arbeitszahl (je 0, wenn die Datenquelle fehlt).
//     // Monats-/Jahreswerte bewusst nicht im Vertrag - Zeitraum-Aggregation ist
//     // Konsumentensache (Archiv/GleitenderMittelwert):
//     'copEstimateID' => 34602,            // WP-eigene Schaetzung (~200-W-Stufen, grob)
//     'copMeasuredID' => 34603,            // echte Messung via externem Zaehler
//     'dailyPerformanceFactorID' => 34604, // Tages-Arbeitszahl ab Mitternacht
//     // Ab contractVersion 1.10: Felder fuer Monitoring-Seiten (Leistungs-/Temperatur-
//     // Zeitreihen, Takt-Analyse):
//     'heatOutputPowerID' => 34605,        // thermische Gesamtleistung in W (Summe Erzeugung)
//     'outsideTempID' => 34606,            // Aussenfuehler der WP in °C
//     'compressorStartsID' => 34607,       // kumulierte Verdichter-Starts
//     'operationsHoursID' => 34608,        // kumulierte Betriebsstunden
//     // Ab contractVersion 1.19 (Fremdanbindung): 'contractType' => 'heatpump', 'failsafeSec' (Hoechstdauer des
//     // Zusatzlaufs in s, 0 ohne Freigabe), 'failsafeMode' => 'maxDuration', 'lastSeenAt' (Unix-Zeit der letzten Nachricht)
//     'contractVersion' => '1.19'  // Vertragsversion (NRG-Stack-Konvention, siehe SUITE.md)
//   ]
// ]
```

Hinweise für Konsumenten:

- `EnergyID` verweist bewusst auf den **kumulativen** Zählerstand des externen Stromzählers und nicht auf „Stromverbrauch heute" — letzterer wird um Mitternacht zurückgesetzt und eignet sich daher nicht als Energiezähler für Auswertungen wie eine Sankey-Darstellung. Ist `EnergyID` 0, sollte die Energie **nicht** aus der Leistung hochgerechnet werden.
- `Measured` unterscheidet echte Messung von der HeishaMon-Schätzung (grob in ~200-W-Stufen). Bei `false` sollte der Wert nicht mit Nachkommastellen dargestellt werden — das wäre Scheingenauigkeit. Das Flag lässt sich **nicht** aus `EnergyID` ableiten, da Leistungs- und Energievariable unabhängig voneinander konfigurierbar sind.
- `reachable` = `false`, wenn die Wärmepumpe gerade nicht erreichbar ist (MQTT-Verbindung verloren). `PowerID` wird in diesem Fall **nicht** zurückgesetzt, sondern friert beim letzten bekannten Wert ein — Konsumenten, die auf den Wert reagieren (z. B. Lastmanagement), sollten ihn bei `reachable = false` als potenziell veraltet behandeln.
- Alle `*ID`-Felder sind 0, solange der zugehörige Datenpunkt nicht existiert (Anlage hat ihn nie gesendet oder er wurde in der Datenpunkt-Liste abgewählt) — immer auf `0` prüfen, bevor die Variable gelesen wird.
- `pumpFlowID`/`pumpSpeedID`/`pumpDutyID`/`twoWayValveStateID` betreffen die **interne** Pumpe/das interne Ventil im Innengerät. `z1PumpID`/`z1MixingValveID`/`z2PumpID`/`z2MixingValveID` betreffen die davon getrennte **externe** Heizkreispumpe/-Mischventil an der optionalen 2. Steuerplatine — physikalisch unterschiedliche Komponenten, nicht verwechseln.
- `z1PumpID`/`z2PumpID` speisen sich seit contractVersion 1.7 bevorzugt aus dem **Kernprotokoll** (`main/Z1/Z2_Pump_State`) — das meldet auch den Zustand einer **echten, physisch verbauten** CZ-NS4P, nicht nur der HeishaMon-eigenen Platinen-Emulation. Die `optional/...`-Emulations-Variablen bleiben als Fallback.
- `z1MixingValveID`/`z2MixingValveID` liefern eine **Stellrichtung** (0=Aus, 1=Zu, 2=Auf), **keine absolute Position** — der Wert 0 ist daher zweideutig zwischen „kein Mischventil vorhanden" und „Ventil steht gerade still". Wer das unterscheiden muss, sollte zusätzlich `IPS_VariableExists()` auf die ID prüfen.

## §14a-Stellhebel (Lastmanagement / Steuerbox-Anbindung)

Wärmepumpen sind klassische §14a-steuerbare Verbrauchseinrichtungen. Der Verdichter einer Aquarea liegt üblicherweise bereits unter der zulässigen Mindestbezugsgrenze — der eigentliche Lasthebel ist meist der **Heizstab** (typisch 3–9 kW zusätzlich). HeishaMon selbst führt keine §14a-Logik aus (die Signalerfassung ist Aufgabe eines eigenen Moduls, z. B. SteuerboxHub); die folgenden Befehle sind aber der Werkzeugkasten, mit dem ein steuerndes Modul auf eine Dimmierungsanforderung reagieren kann — alle erreichbar über `HEISHA_SendSetCommand($id, $Command, $Value)`:

| Befehl | Wirkung | Rolle |
| --- | --- | --- |
| `SetDHWHeaterState` | Heizstab für Warmwasser sperren/freigeben (0/1) | Primärer Lasthebel |
| `SetRoomHeaterState` | Heizstab für Heizung sperren/freigeben (0/1) | Primärer Lasthebel |
| `SetQuietMode` | Flüstermodus, reduziert die Verdichterleistung spürbar | Feinstufe |
| `SetZ1HeatRequestTemperature` / `SetZ2HeatRequestTemperature` | Heizanforderung verschieben | Zusatzhebel |
| `SetCurves` | Heiz-/Kühlkurven anpassen (auch über `HEISHA_SetCurves`) | Zusatzhebel |
| `SetForceDHW` / `SetDHWTemp` | Warmwasserbereitung verschieben bzw. deren Solltemperatur senken | Zusatzhebel |
| `SetSmartGridMode` | Digitaler SG-Ready-Ersatz — Normal/Überhöhung 1+2/WP+Heizstab aus (0-3), s. u. | Primärer Lasthebel (Alternative) |

**Schutzbedingungen, die ein steuerndes Modul respektieren sollte** (beide live als Statusvariable lesbar):

- Während `Defrosting_State` = aktiv **nicht eingreifen** — die Abtauung ist kurz und technisch notwendig, ein Eingriff riskiert Vereisung.
- `Sterilization_State` (Legionellenschutz) darf **verschoben**, aber nicht dauerhaft unterdrückt werden — Hygiene hat Vorrang vor Optimierung.

Hinweis: HeishaMon meldet nicht, wer einen Sollwert zuletzt geändert hat (MQTT kennt keinen Urheber). Ein steuerndes Modul sollte seine eigenen Vorgaben deshalb über einen Soll-/Ist-Abgleich verifizieren statt sie als dauerhaft gesetzt anzunehmen.

## Zusätzliche Befehle (optional)

Im Bereich **Zusätzliche Befehle** lassen sich zusätzliche Funktionen als Variablen aktivieren. Der SmartGrid-Modus ist ein reiner Schreibbefehl — die Wärmepumpe meldet seinen Zustand **nicht zurück**, die Anzeige spiegelt nur den zuletzt gesendeten Befehl. Der Zustand der Relais wird dagegen ab Firmware 4.3 von der Platine zurückgelesen:

- **SmartGrid-Modus**: bildet digital dieselben vier Betriebsarten nach, die sonst nur über physische Trockenkontakte am Außengerät geschaltet werden (natives "SG ready", Servicepunkt 16 in der Wärmepumpen-Bedienung). Wirkt nur, wenn an der Wärmepumpe selbst die Service-Einstellung **Optional PCB** auf **Ja** steht (ein anderer Menüpunkt als "SG ready" selbst) — sonst passiert nichts, ohne Fehlermeldung.
- **Relais 1/2**: die beiden fest verbauten Relais der *großen* HeishaMon-Platine (`gpio/relay/one`/`two`), bis 230V/5A schaltbar — z. B. für einen externen Thermostatkontakt oder eine Pumpe. Nur vorhanden, wenn tatsächlich die große Platine verbaut ist; bei der kleinen Platine schalten diese Variablen ins Leere. Seit Version 1.44.0 liest das Modul den Zustand von `gpio/relay/one/state` bzw. `two/state` zurück (Firmware 4.3 oder neuer); schaltet eine Regel auf der Platine oder ein Modbus-Client ein Relais, folgt die Variable.
- **Zusatz-GPIOs 1–5** (ab Version 1.44.0, eigene Option, nur Anzeige): Zustand der fünf frei konfigurierbaren GPIOs der großen Platine (`gpio/extra/N/state`), egal ob sie als Eingang oder Ausgang eingestellt sind.
- **Fehlercode zurücksetzen** (ab Version 1.44.0): Die Schaltfläche bzw. `HEISHA_ResetFault($id)` sendet `SetReset`, wie die Taste „Reset“ an der Fernbedienung CZ-TAW1 oder am Innengerät. Sie löst Fehler, die ein Aus- und Einschalten nicht löst. Nur mit aktivierten Extra-Befehlen.

## Befehle per Skript

Alle HeishaMon-Befehle (siehe [MQTT-Topics](https://github.com/heishamon/HeishaMon/blob/master/MQTT-Topics.md)) lassen sich auch per Skript senden:

```php
// Beliebiger Set-Befehl
HEISHA_SendSetCommand(12345, 'SetQuietMode', '2');

// Heiz-/Kühlkurven setzen (SET16, JSON laut HeishaMon-Doku)
HEISHA_SetCurves(12345, '{"zone1":{"heat":{"target":{"high":35,"low":25},"outside":{"high":15,"low":-15}}}}');

// Heizkurven strukturiert lesen/setzen (Zwei-Punkt-Modell, ab Version 1.29.0)
$curve = HEISHA_GetHeatingCurve(12345);   // ['curveModel'=>'twoPoint','curveWritable'=>true,'zones'=>['z1'=>['heat'=>[...]|null,'cool'=>...],'z2'=>...|null]]
HEISHA_SetHeatingCurve(12345, 'z1', 'heat', 35, 25, 15, -15);   // Zone, Modus, Vorlauf hoch/tief, Aussen hoch/tief (°C)
```

Ab Version 1.30.0 liefert `HEISHA_GetHeatingCurve` je Zone zusätzlich `shift` (Heizen/Kühlen: `mode` `'shift'` = Verschiebung -5..+5 oder `'direct'` = direkte Vorlauf-Solltemperatur, plus `valueC`) und oben `boardRulesActive`. Verschieben: `HEISHA_SetHeatingCurveShift(12345, 'z1', 'heat', -2)` - nur wenn die Anlage im Verschiebungsmodus meldet, sonst `false`. Regeln auf der Platine (z. B. der Taktschutz) können den Wert überschreiben. Bedienfunktionen: `HEISHA_GetOperations(12345)` (`quiet`, `powerful`, `holiday`, `emergencyHeater`, `dhwTargetC`, je `value`/`min`/`max`) und `HEISHA_SetOperation(12345, 'quiet', 2)`. Einen Urlaubszeitraum kennt HeishaMon nicht (nur an/aus).

`HEISHA_SetHeatingCurve` liefert `true`, sobald der Befehl abgeschickt wurde (Zone vorhanden, Werte plausibel, MQTT-Gateway aktiv) — die Wärmepumpe quittiert das nicht, ob die Kurve tatsächlich übernommen wurde, zeigt erst das spätere Zurücklesen mit `HEISHA_GetHeatingCurve`. Ungültige Eingaben oder eine nicht aktive Zone 2 ergeben `false`, keine Exception.

## Hinweise zur Konfigurationsmaske

- **📖 Dokumentation & Hilfe** (eingeklappt): allgemeine Erläuterungen zur Instanz, trägt die aktuelle Modulversion in der Überschrift.
- **🆕 Neu in Version …** (aufgeklappt): erscheint nach einem Update mit den wichtigsten Neuerungen, bis „Verstanden" geklickt wird — dann erst bei der nächsten Version wieder.
- **🪟 Formular: Aufbau** (eingeklappt, nach den Fachbereichen, ab Version 1.40.0): Je Bereich (1-Wire, Verknüpfungsstruktur, Energiezähler, Heizungs-Automatik, Zeitplan, Energiespar-Prüfung und -Regelwerke, Neustart-Wächter, Archivierung, Zusatzbefehle) legen Sie fest, ob er beim Öffnen **aufgeklappt**, **zugeklappt** oder **ausgeblendet** ist und an welcher **Position** er steht (1–99). Kein Haken heißt Standard; bei mehreren Haken gilt ausgeblendet vor zugeklappt vor aufgeklappt. Ausgeblendete Bereiche behalten ihre Einstellungen und arbeiten weiter, nur die Anzeige fehlt. Die Einstellung wirkt nach „Übernehmen“ beim nächsten Öffnen; „Auf Standard zurücksetzen“ stellt alles zurück (per Skript: `HEISHA_ResetFormLayout($id)`, speichert nicht von selbst). Fest bleiben die Bereiche oben (Zweck, Neuigkeiten, Dokumentation) und unten (Forum, Über dieses Modul), die Verbindungsfelder und die Datenpunkt-Liste.
- **Forum-Hinweis** am Ende der Maske: einmalig, lässt sich dauerhaft ausblenden.

## Hinweise

- Zustands-Topics können laut HeishaMon-Doku in Ausnahmefällen den Wert `-1` (unbekannt) liefern; das Modul behandelt dies bei Schaltzuständen als „Aus".
- Unbekannte Topics (z. B. `stats`, `1wire`, `s0`) werden ignoriert; mit der Option **Debug: Unbekannte Topics** lassen sie sich im Debug-Fenster anzeigen.

## Changelog

Alle Änderungen sind im [CHANGELOG](CHANGELOG.md) dokumentiert.

## Lizenz

Dieses Modul ist Teil des **NRG-Stack** und steht unter der [PolyForm Noncommercial License 1.0.0](LICENSE):

- Private und nicht-kommerzielle Nutzung ist frei.
- **Gewerbliche Nutzung** erfordert eine gesonderte Lizenz vom Rechteinhaber (Kontakt: DG65).
- Der Wechsel wirkt nur nach vorn: bereits unter MIT veröffentlichte Altversionen (bis Version 1.2.0) bleiben MIT.
- Spenden sind ausdrücklich willkommen und rein freiwillig: [paypal.me/DietmarGureth](https://paypal.me/DietmarGureth)
