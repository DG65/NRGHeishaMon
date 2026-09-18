# HeishaMon

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Modul Version](https://img.shields.io/badge/Modul_Version-1.21.0-blue)
![Symcon Version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
[![Check Style](https://github.com/DG65/NRGHeishaMon/actions/workflows/check-style.yml/badge.svg)](https://github.com/DG65/NRGHeishaMon/actions/workflows/check-style.yml)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

IP-Symcon Modul zur Anbindung eines [HeishaMon](https://github.com/heishamon/HeishaMon) an eine Panasonic Aquarea Wärmepumpe über MQTT.

Teil des **NRG-Stack** — dem Energie-Modulverbund von DG65.

## Haftungsausschluss

Die Nutzung dieses Moduls erfolgt **auf eigenes Risiko**. Das Modul sendet Befehle an den HeishaMon und damit direkt an die Wärmepumpe (z. B. Solltemperaturen, Betriebsarten, Servicemenü-Werte wie die maximale Pumpenleistung). Der Autor übernimmt **keine Verantwortung oder Haftung** für Schäden am HeishaMon, an der Wärmepumpe oder an sonstigen Anlagenteilen sowie für Folgeschäden, die durch die Verwendung dieses Moduls entstehen. Es besteht kein Anspruch auf Support oder Fehlerfreiheit.

## Funktionsumfang

- Empfängt alle HeishaMon-Datenpunkte (`main/TOP0` … `main/TOP143` sowie die Optional-PCB-Topics) über den IP-Symcon MQTT Server oder MQTT Client.
- Legt Statusvariablen **automatisch** an, sobald der HeishaMon das jeweilige Topic sendet — es erscheinen also nur die Datenpunkte, die die eigene Wärmepumpe tatsächlich liefert.
- Passende Darstellungen: Temperaturen mit °C, Leistungen mit W, Betriebsarten als Auswahlliste, Zustände als Schalter.
- Schreibbare Werte (z. B. Warmwasser-Solltemperatur, Betriebsart, Flüstermodus, Powerful-Modus) sind direkt schaltbar und werden über `<Basistopic>/commands/SetXxx` an den HeishaMon gesendet.
- Verfügbarkeitsanzeige über das LWT-Topic (Variable „Erreichbar").
- Vollständige deutsche Übersetzung über `locale.json`.

## Voraussetzungen

- IP-Symcon ab Version 9.0
- HeishaMon mit aktivierter MQTT-Anbindung
- Eingerichteter MQTT Server (oder MQTT Client) in IP-Symcon, mit dem der HeishaMon verbunden ist

## Installation

### Über den Module Store (empfohlen)

Das Modul ist im **IP-Symcon Module Store** verfügbar und lässt sich direkt aus IP-Symcon heraus installieren: Module Store öffnen, nach **HeishaMon** suchen und installieren. Aktualisierungen kommen dann automatisch über den Store.

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

Die Zeilen lassen sich durch **Ziehen mit der Maus sortieren** — die Variablen unter der Instanz und die Verknüpfungen in der Verknüpfungsstruktur folgen dieser Reihenfolge, sobald die Änderungen übernommen werden. Die Schaltfläche **Reihenfolge und Auswahl zurücksetzen** stellt den Standard wieder her.

## 1-Wire-Sensoren

Temperatursensoren (z. B. DS18B20) am 1-Wire-Bus des HeishaMon senden auf einem eigenen, adressabhängigen Topic statt der festen Themenliste der Wärmepumpe. Das Modul erkennt sie automatisch, sobald ein erster Messwert eintrifft, und trägt sie im Bereich **1-Wire-Sensoren** mit ihrer Bus-Adresse ein. Dort lässt sich pro Sensor ein sprechender **Name** vergeben (z. B. "Vorlauf Pufferspeicher") und die Aktivierung wie bei den übrigen Datenpunkten steuern. Ohne vergebenen Namen erscheint zunächst ein generischer Platzhalter mit den letzten vier Stellen der Adresse.

## S0-Zähler

Die HeishaMon-Platine kann bis zu zwei S0-Stromzähler direkt auswerten (GPIO12 und GPIO14, siehe HeishaMon-Doku). Das Modul legt dafür je Port **Leistung** (W) und **Gesamtenergie** (kWh, aus den Wh-Rohwerten umgerechnet) als Datenpunkte an. Wer den Stromverbrauch der Wärmepumpe über einen solchen S0-Zähler misst, kann die Gesamtenergie-Variable direkt als **Energiezähler im Bereich "Externer Stromzähler"** auswählen — echte COP-/Arbeitszahl-Messung ganz ohne zusätzlichen Messaktor.

## Platinen-Diagnose

Aus dem `stats`-Topic der Firmware pflegt das Modul die Gruppe **Platinen-Diagnose**: WLAN-Qualität (%), Laufzeit seit Platinen-Neustart, MQTT-Neuverbindungen, Bus-Lesequalität (Anteil fehlerfreier Datagramme vom CN-CNT-Bus), aktive Regeln (zeigt u. a., ob das aufgespielte Taktschutz-Regelwerk läuft) und Firmware-Version. WLAN-Qualität und MQTT-Neuverbindungen werden automatisch archiviert — bei Verbindungsabbrüchen zeigt der Verlauf, ob schwaches WLAN, MQTT-Hänger, heimliche Neustarts (Laufzeit-Sprünge) oder Busfehler (Lesequalität) die Ursache sind.

## Verknüpfungsstruktur (gruppierte Ansicht)

Statusvariablen müssen in IP-Symcon flach unter der Instanz liegen — im WebFront wird daraus schnell eine lange, unübersichtliche Liste. Für eine übersichtliche, gruppierte WebFront-Navigation kann das Modul optional eine **Verknüpfungsstruktur** pflegen: Im Bereich **Verknüpfungsstruktur** die Option **Verknüpfungsstruktur erzeugen** aktivieren und eine **Zielkategorie** wählen (am besten eine, die selbst im WebFront sichtbar ist). Das Modul legt dort einen Kategoriebaum an:

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

- **COP (HeishaMon-Schätzung)** — automatisch aus den HeishaMon-eigenen Werten (thermische Leistung / elektrische Aufnahme über alle Betriebsarten). Keine Konfiguration nötig, aber grob, da Panasonic die Aufnahme nur in ~200-W-Stufen schätzt.
- **COP (gemessen)** — über einen externen Stromzähler (z. B. Shelly 3EM auf der Wärmepumpen-Phase). Dazu im Konfigurationspanel **Externer Stromzähler (COP / Arbeitszahl)** die Variable **Stromzähler: Wirkleistung der Wärmepumpe (W)** auswählen; der COP wird bei jeder Wertänderung neu berechnet. Unterhalb der **Mindestleistung** (Standard 100 W, gegen Standby-Rauschen) wird 0 ausgegeben.

Wird zusätzlich die Variable **Stromzähler: Gesamtwirkenergie der Wärmepumpe (kWh)** ausgewählt, berechnet das Modul Tageswerte:

- **Stromverbrauch heute** — exakt aus dem Zählerstand (Basis wird um Mitternacht neu gesetzt, ein Zähler-Reset wird abgefangen)
- **Wärmemenge heute** — Integration der thermischen Leistung im 60-Sekunden-Takt (Zwischenstände überleben einen IPS-Neustart)
- **Arbeitszahl heute** — Verhältnis der beiden; mit Aufzeichnung im Archiv entsteht daraus die Langzeit-Historie

Hinweis: Läuft der Heizstab, steckt seine Wärme in der gemessenen thermischen Leistung. Da nur die Wärmepumpen-Phase im Nenner steht, fällt der COP in diesen Phasen optisch zu gut aus — für die reine Verdichter-Bewertung ist das aber genau richtig.

## Energiespar-Prüfung

Der Bereich **💡 Energiespar-Prüfung** in der Instanz-Konfiguration bewertet die von der Anlage empfangenen Einstellwerte anhand von Richtwerten aus den dokumentierten [HeishaMon-Beispielregelwerken](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules) und dem Panasonic-Servicehandbuch: Taktverhalten (mittlere Laufzeit je Verdichterstart), Warmwasser-Sollwert und -Nachheizdelta, Heizstab-Freigabetemperatur und -Verzögerung, Heizgrenze, Heizkurven-Sollwert und Pumpenansteuerung. Die Prüfung ist **reine Anzeige** — es wird nichts verändert. Die bewerteten Werte sind Service-Einstellungen der Wärmepumpe: Änderungen bewusst vornehmen, im Zweifel mit dem Fachbetrieb.

## Energiespar-Regelwerke (auf der Platine)

Der Bereich **⚙️ Energiespar-Regelwerke** kann kuratierte Regelwerke direkt auf die HeishaMon-Platine spielen — sie laufen dort autonom weiter, auch wenn WLAN oder IP-Symcon ausfallen. Erste Vorlage ist der **Taktschutz** (nach dem dokumentierten [`Compressor-Short-Cycle-Guard`](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules/Compressor-Short-Cycle-Guard)): Nach jedem Verdichterstopp wird der Wiederanlauf für eine einstellbare Zeit (Standard 45 min) unterdrückt, sofern es draußen mild ist (Standard ≥ 2 °C) — weniger Starts bedeuten bessere Effizienz und längere Verdichter-Lebensdauer; bei Kälte endet die Sperre sofort.

**Wichtig:** Das Aufspielen **ersetzt das komplette** auf der Platine gespeicherte Regelwerk. Wer dort eigene Regeln pflegt (z. B. das umfangreiche [Referenz-Regelwerk](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules/Jeisha-DHW-Radiators-Rowbuffer)), sollte diese Funktion nicht nutzen. Die HeishaMon-Firmware validiert jeden Upload selbst — ein ungültiges Regelwerk wird verworfen, das bisherige bleibt aktiv.

Abgrenzung im NRG-Stack (mit EMS abgestimmt): Platinen-Regelwerke bilden die autonome **Schutz-Schicht** (Taktschutz, Rampen); die energetische **Planung** (wann Warmwasser, SmartGrid-Modus, Pausen) bleibt bei EMS/IP-Symcon — die Vorlagen enthalten deshalb bewusst keine Zeitplan-Blöcke.

## Elektrische Leistung & Integration in andere Module

Das Modul führt eine Variable **Elektrische Leistung (gesamt)** (W). Sie enthält den gemessenen Wert des externen Stromzählers, sofern konfiguriert — andernfalls die Summe der HeishaMon-Schätzwerte (Heizen + Kühlen + Warmwasser). Damit gibt es unabhängig vom Messaufbau immer eine einzelne, verlässliche Leistungsvariable, z. B. für Visualisierungen, ein Energiemanagement oder eigene Automationen.

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
//     'contractVersion' => '1.10'  // Vertragsversion (NRG-Stack-Konvention, siehe SUITE.md)
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

Im Bereich **Zusätzliche Befehle** lassen sich zwei reine Schreibfunktionen als schaltbare Variablen aktivieren — die Wärmepumpe meldet ihren Zustand dabei **nicht zurück**, die Anzeige spiegelt nur den zuletzt gesendeten Befehl:

- **SmartGrid-Modus**: bildet digital dieselben vier Betriebsarten nach, die sonst nur über physische Trockenkontakte am Außengerät geschaltet werden (natives "SG ready", Servicepunkt 16 in der Wärmepumpen-Bedienung). Wirkt nur, wenn an der Wärmepumpe selbst die Service-Einstellung **Optional PCB** auf **Ja** steht (ein anderer Menüpunkt als "SG ready" selbst) — sonst passiert nichts, ohne Fehlermeldung.
- **Relais 1/2**: die beiden fest verbauten Relais der *großen* HeishaMon-Platine (`gpio/relay/one`/`two`), bis 230V/5A schaltbar — z. B. für einen externen Thermostatkontakt oder eine Pumpe. Nur vorhanden, wenn tatsächlich die große Platine verbaut ist; bei der kleinen Platine schalten diese Variablen ins Leere.

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

`HEISHA_SetHeatingCurve` liefert `true`, sobald der Befehl abgeschickt wurde (Zone vorhanden, Werte plausibel, MQTT-Gateway aktiv) — die Wärmepumpe quittiert das nicht, ob die Kurve tatsächlich übernommen wurde, zeigt erst das spätere Zurücklesen mit `HEISHA_GetHeatingCurve`. Ungültige Eingaben oder eine nicht aktive Zone 2 ergeben `false`, keine Exception.

## Hinweise zur Konfigurationsmaske

- **📖 Dokumentation & Hilfe** (eingeklappt): allgemeine Erläuterungen zur Instanz, trägt die aktuelle Modulversion in der Überschrift.
- **🆕 Neu in Version …** (aufgeklappt): erscheint nach einem Update mit den wichtigsten Neuerungen, bis „Verstanden" geklickt wird — dann erst bei der nächsten Version wieder.
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
