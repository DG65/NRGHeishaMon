# Eigenes Wärmepumpen-Modul an den NRG-Stack anbinden

*[English version](EIGENES_WAERMEPUMPEN_MODUL.en.md)*

Dieses Dokument beschreibt, was ein Symcon-Modul für eine Wärmepumpe liefern muss, damit der NRG-Stack (Energiemanagement EMS, Dashboard, Anlagenschema) es automatisch findet und nutzt, auch wenn es von einem anderen Autor stammt. Die Referenzimplementierung ist das Modul **HeishaMon** (`HEISHA_GetFunctions()`, siehe [README](README.md)); weitere Module mit demselben Vertrag sind WPHub, WPModbusHub, SamsungEhs und WPEEBUSHub.

## 1. Prinzip

Das Modul stellt eine öffentliche Funktion `<PRÄFIX>_GetFunctions(int $InstanzID): array` bereit. Sie liefert eine Liste mit **einem Eintrag je Wärmepumpe**. Die Konsumenten suchen über die Modulliste nach Gerätemodulen, prüfen mit `function_exists('<PRÄFIX>_GetFunctions')` und lesen die Einträge. Sie rufen die Funktion nur in einem langsamen Takt (Minuten) auf und kapseln den Aufruf: Die Funktion muss schnell antworten (keine Netzwerkzugriffe) und darf nie eine Ausnahme werfen.

Ein Eintrag besteht aus **Variablen-IDs** (nicht aus Werten): Der Konsument liest die Variable selbst. Eine ID `0` bedeutet immer „liefert dieses Modul nicht“ und ist kein Fehler.

## 2. Pflichtfelder

| Feld | Typ | Bedeutung |
| --- | --- | --- |
| `Type` | string | `'heatpump'` |
| `contractType` | string | `'heatpump'` (Selbstbeschreibung, ab Vertrag 1.19) |
| `contractVersion` | string | `'Major.Minor'`, zurzeit `'1.19'`. Neue Felder erhöhen nur die Minor-Nummer, Major nur bei einem Bruch. Nie auf eine Mindest-Minor-Nummer prüfen, sondern Felder mit `isset` |
| `Caption` | string | Name der Instanz |
| `PowerID` | int | Variable mit der aktuellen elektrischen Leistung der Wärmepumpe in **W** (kein Gesamthaus-Zähler); 0, wenn nicht vorhanden |
| `EnergyID` | int | Variable mit dem **kumulativen** Zählerstand in **kWh** (nie ein Tageswert, siehe 5.); 0, wenn nicht vorhanden |
| `Measured` | bool | `true` = echte Messung, `false` = Schätzwert der Anlage. Bei `false` keine Nachkommastellen anzeigen |
| `unit` | string | physikalische Einheit von `PowerID`, `'W'` |
| `reachable` | bool | `false`, wenn die Anlage nicht erreichbar ist. `PowerID` bleibt dann beim letzten Wert stehen (potenziell veraltet) |

## 3. Optionale Felder (alle als Variablen-ID, 0 = nicht geliefert)

Liefern Sie nur, was Ihr Modul wirklich kennt. Eine Auswahl der wichtigsten (das vollständige, ständig wachsende Verzeichnis steht in der [README von HeishaMon](README.md)):

| Feld | Bedeutung |
| --- | --- |
| `outsideTempID` | Außentemperatur der Wärmepumpe (°C) |
| `mainInletTempID`, `mainOutletTempID` | Rücklauf-/Vorlauftemperatur Hauptkreis (°C) |
| `dhwTempID` | Warmwassertemperatur (°C) |
| `z1WaterTempID`, `z2WaterTempID` | Wassertemperatur Heizzone 1/2 (°C) |
| `compressorFreqID` | Verdichterfrequenz (Hz) |
| `defrostingStateID` | Abtaubetrieb aktiv (bool) |
| `operatingModeNormID` | Betriebsart im **Verbund-Enum**: 0 Bereitschaft, 1 Heizen, 2 Kühlen, 3 Warmwasser, 4 Heizen + Warmwasser, 5 Kühlen + Warmwasser, -1 unbekannt. Herstellerspezifische Werte gehören nur in `operatingModeID` (Diagnose) |
| `copEstimateID`, `copMeasuredID` | Arbeitszahl aus Anlagenwerten bzw. aus Wärmeleistung durch gemessene Stromleistung |
| `heatOutputPowerID` | thermische Gesamtleistung (W) |
| `compressorStartsID`, `operationsHoursID` | kumulierte Starts/Betriebsstunden |
| `energyMeasured` | bool: `false`, wenn `EnergyID` aus der Leistung hochgerechnet ist (siehe 5.) |
| `pollInterval` | tatsächliche Abfragerate der Datenquelle in Sekunden |
| `managedBy` | wer die Anlage steuert (`''`/`'none'`/`'ems'` = frei für das EMS) |

## 4. Frische: `lastSeenAt`

`lastSeenAt` (int, Unix-Zeit) ist der Zeitpunkt der **letzten erfolgreichen Antwort der Anlage**. Es bleibt bei einem Ausfall **stehen** und fällt nicht auf 0 (0 heißt nur „nie“). Konsumenten behandeln Werte, deren `lastSeenAt` älter als 10 Minuten ist, als unbekannt. Ohne `lastSeenAt` kann das EMS die Frische nicht prüfen und wird Ihr Modul nur lesen, nicht steuern.

## 5. Energie nie aus Tageswerten

`EnergyID` muss ein **kumulativer** Zählerstand sein, der nie zurückspringt (außer bei einem Zählertausch). Ein Tageswert, der um Mitternacht auf 0 geht, ist als `EnergyID` ungeeignet, weil Auswertungen (Bilanz, Sankey-Diagramm) die Differenz zweier Stände bilden. Haben Sie nur Tageswerte, bilden Sie selbst einen kumulativen Zähler oder liefern `EnergyID = 0`. Rechnen Sie den Zähler aus der Leistung hoch, setzen Sie `energyMeasured = false`. Ist `EnergyID` 0, rechnet der Konsument die Energie **nicht** aus `PowerID` hoch.

## 6. Steuern (optional, nur mit Freigabe)

Das EMS darf eine Wärmepumpe nur in einem schonenden Punkt schalten: einem **Zusatzlauf bei PV-Überschuss** (zum Beispiel Warmwasserbereitung oder EEBUS OHPCF). Alle Felder sind optional; liefern Sie nur, was Ihre Wärmepumpe schonend kann, sonst 0.

| Feld | Bedeutung |
| --- | --- |
| `optionalRunID` | bool-Variable, per `RequestAction` schaltbar. **Schreiben `true` = Ein-Wunsch** (das Modul plant selbst und merkt sich den Wunsch, der Konsument schreibt nicht zyklisch nach), `false` = zurücknehmen/abbrechen. Der **gelesene Wert ist der Ist-Zustand** (läuft/eingeplant), nicht der Wunsch |
| `optionalRunPowerID`, `optionalRunPowerMaxID` | erwartete bzw. höchste Zusatzleistung in W |
| `powerLimitID`, `powerLimitActiveID` | Leistungsgrenze (W) und ob sie aktiv ist, nur wenn die Anlage sie wirklich kann |
| `controllable` | bool: `true` nur, wenn der Nutzer die Steuerung freigegeben hat |

Regeln:

- **Ab Werk aus, Freigabe durch den Nutzer** im Wärmepumpen-Modul. Ohne Freigabe: `optionalRunID = 0`, `controllable = false`.
- **Nichts, was den Speicher der Anlage (EEPROM) häufig beschreibt.** Keine Sollwerte, Heizkurven oder Servicewerte zyklisch schreiben. Bevorzugen Sie Bedientasten-Befehle (zum Beispiel „Warmwasser jetzt“).
- Das Modul **prüft selbst Schutzbedingungen** (zum Beispiel nicht beim Abtauen, Mindestabstand, höchste Zahl der Starts pro Tag) und kontrolliert, ob die Anlage den Befehl übernommen hat. Ein abgelehnter Wunsch bleibt eine begrenzte Zeit gespeichert.
- Ein von Hand gestarteter Lauf wird **nie** durch das EMS abgebrochen.

## 7. Rückfall: `failsafeSec` und `failsafeMode`

Jede steuerbare Anlage braucht einen definierten sicheren Zustand, wenn Befehle ausbleiben. Ohne `failsafeSec > 0` gibt das EMS keinem **Fremdmodul** das Schreiben frei.

| Feld | Bedeutung |
| --- | --- |
| `failsafeSec` | int, Sekunden |
| `failsafeMode` | `'deadman'`: Der Konsument muss innerhalb von `failsafeSec/2` erneut schreiben, sonst geht das Modul selbst in den sicheren Zustand. `'maxDuration'`: Es ist ein **Ein-Wunsch**; das Modul beendet den Lauf nach höchstens `failsafeSec` selbst, der Konsument schreibt **nicht** nach. Fehlt das Feld, gilt `'deadman'` |

Für einen Zusatzlauf als Ein-Wunsch passt `'maxDuration'` (so macht es HeishaMon: Höchstdauer des Laufs, nur mit Freigabe größer als 0).

## 8. Minimalbeispiel

```php
public function GetFunctions(): array
{
    $power = @$this->GetIDForIdent('Power');
    $energy = @$this->GetIDForIdent('EnergyTotal');   // kumulativ, kWh
    $outside = @$this->GetIDForIdent('OutsideTemp');
    $mode = @$this->GetIDForIdent('ModeNorm');         // Verbund-Enum 0-5, -1
    return [[
        'Type'            => 'heatpump',
        'contractType'    => 'heatpump',
        'contractVersion' => '1.19',
        'Caption'         => IPS_GetName($this->InstanceID),
        'PowerID'         => $power === false ? 0 : $power,
        'EnergyID'        => $energy === false ? 0 : $energy,
        'Measured'        => true,
        'unit'            => 'W',
        'reachable'       => (bool) $this->GetValue('Reachable'),
        'outsideTempID'   => $outside === false ? 0 : $outside,
        'operatingModeNormID' => $mode === false ? 0 : $mode,
        'lastSeenAt'      => (int) $this->ReadAttributeInteger('LastSeenAt')
    ]];
}
```

## 9. Prüfliste

- [ ] `<PRÄFIX>_GetFunctions()` antwortet schnell, wirft nie eine Ausnahme und kommt ohne Netzwerkzugriff aus.
- [ ] Pflichtfelder vorhanden, `contractVersion` als `'Major.Minor'`.
- [ ] Nicht gelieferte Felder sind `0` (oder fehlen), nie `null` oder ein Text.
- [ ] `EnergyID` ist kumulativ in kWh, nie ein Tageswert.
- [ ] `operatingModeNormID` nutzt das Verbund-Enum.
- [ ] `lastSeenAt` bleibt bei einem Ausfall stehen.
- [ ] Steuern nur mit Nutzerfreigabe, ohne häufiges Schreiben in den Anlagenspeicher, mit `failsafeSec` und `failsafeMode`.
- [ ] Felder werden nie umbenannt oder entfernt, nur ergänzt.

## 10. Vorbilder: zwei Module, zwei Rückfallarten

| | HeishaMon (Panasonic über MQTT) | WPEEBUSHub (EEBUS, in Vorbereitung ab 0.6.0) |
| --- | --- | --- |
| Steuern | `optionalRunID` = Warmwasser-Zusatzlauf, nur mit Freigabe | `optionalRunID` = Zusatzlauf (OHPCF), `powerLimitID` = Leistungsgrenze (LPC), je nur wenn die Wärmepumpe es anbietet |
| `failsafeMode` | `maxDuration`: ein Start verbraucht den Wunsch, das Modul beendet den Lauf selbst nach `failsafeSec`; der Konsument schreibt **nicht** nach | `deadman`: der Konsument schreibt spätestens nach `failsafeSec`/2 nach; ohne Nachschreiben fällt die Wärmepumpe selbst in den sicheren Zustand |
| `failsafeSec` | Höchstdauer des Zusatzlaufs (Standard 7200), 0 ohne Freigabe | Standard 900, einstellbar; 0 = unbefristet und damit keine Schreibfreigabe für fremde Konsumenten |
| Frische | `lastSeenAt` aus dem jüngsten Datenpunkt der Platine | `lastSeenAt` = letztes Datenpaket, `pollInterval` 0 = ereignisgesteuert |
| Lesewert | Ist-Zustand (`Force_DHW_State`), nicht der Wunsch | Ist-Zustand (läuft/eingeplant), nicht der Wunsch |

Der Konsument liest `failsafeMode` und richtet sich danach: `deadman` heißt nachschreiben, `maxDuration` heißt einmal wünschen und nie nachschreiben. Wärmepumpen über EEBUS sind z. B. Vaillant, Viessmann, Bosch/Buderus, NIBE und Wolf; das Modul hängt als Kind am NRG-Stack EEBUS-Gateway. Die gesetzliche Ausfallgrenze der Wärmepumpe bei Verbindungsverlust (LPC failsafe) bleibt vom Rückfall unberührt, und die §14a-Grenze des Netzbetreibers kommt über den SteuerboxHub, nicht über das EMS.
