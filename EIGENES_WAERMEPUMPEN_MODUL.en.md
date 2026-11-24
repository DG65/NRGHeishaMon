# Connect your own heat pump module to the NRG-Stack

*[Deutsche Fassung](EIGENES_WAERMEPUMPEN_MODUL.md)*

This document describes what a Symcon module for a heat pump has to deliver so that the NRG-Stack (the EMS energy management, the dashboard, the system schematic) finds and uses it automatically, even if it comes from another author. The reference implementation is the module **HeishaMon** (`HEISHA_GetFunctions()`, see the [README](README.en.md)); other modules with the same contract are WPHub, WPModbusHub, SamsungEhs and WPEEBUSHub.

## 1. Principle

The module provides a public function `<PREFIX>_GetFunctions(int $InstanceID): array`. It returns a list with **one entry per heat pump**. Consumers search the module list for device modules, check `function_exists('<PREFIX>_GetFunctions')` and read the entries. They call the function only at a slow pace (minutes) and wrap the call: the function has to answer quickly (no network access) and must never throw an exception.

An entry consists of **variable IDs** (not values): the consumer reads the variable itself. An ID of `0` always means "this module does not deliver it" and is not an error.

## 2. Mandatory fields

| Field | Type | Meaning |
| --- | --- | --- |
| `Type` | string | `'heatpump'` |
| `contractType` | string | `'heatpump'` (self description, from contract 1.19) |
| `contractVersion` | string | `'Major.Minor'`, currently `'1.19'`. New fields only raise the minor number, the major number only changes on a break. Never test for a minimum minor number, test fields with `isset` |
| `Caption` | string | name of the instance |
| `PowerID` | int | variable with the current electrical power of the heat pump in **W** (not a whole-house meter); 0 if not available |
| `EnergyID` | int | variable with the **cumulative** meter reading in **kWh** (never a daily value, see 5.); 0 if not available |
| `Measured` | bool | `true` = real measurement, `false` = estimate of the unit. With `false` do not show decimals |
| `unit` | string | physical unit of `PowerID`, `'W'` |
| `reachable` | bool | `false` when the unit is not reachable. `PowerID` then keeps its last value (potentially stale) |

## 3. Optional fields (all variable IDs, 0 = not delivered)

Deliver only what your module really knows. A selection of the most important ones (the complete, growing list is in the [HeishaMon README](README.en.md)):

| Field | Meaning |
| --- | --- |
| `outsideTempID` | outside temperature of the heat pump (°C) |
| `mainInletTempID`, `mainOutletTempID` | return/flow temperature of the main circuit (°C) |
| `dhwTempID` | hot water temperature (°C) |
| `z1WaterTempID`, `z2WaterTempID` | water temperature of heating zone 1/2 (°C) |
| `compressorFreqID` | compressor frequency (Hz) |
| `defrostingStateID` | defrosting active (bool) |
| `operatingModeNormID` | operating mode in the **NRG-Stack enum**: 0 standby, 1 heating, 2 cooling, 3 hot water, 4 heating + hot water, 5 cooling + hot water, -1 unknown. Manufacturer-specific values belong only in `operatingModeID` (diagnostics) |
| `copEstimateID`, `copMeasuredID` | performance factor from unit values or from heat output divided by measured electrical power |
| `heatOutputPowerID` | total thermal output (W) |
| `compressorStartsID`, `operationsHoursID` | cumulative starts/operating hours |
| `energyMeasured` | bool: `false` if `EnergyID` is extrapolated from the power (see 5.) |
| `pollInterval` | actual polling rate of the data source in seconds |
| `managedBy` | who controls the unit (`''`/`'none'`/`'ems'` = free for the EMS) |

## 4. Freshness: `lastSeenAt`

`lastSeenAt` (int, Unix time) is the time of the **last successful answer of the unit**. It **stays** at that value during an outage and does not fall to 0 (0 only means "never"). Consumers treat values whose `lastSeenAt` is older than 10 minutes as unknown. Without `lastSeenAt` the EMS cannot check freshness and will only read your module, not control it.

## 5. Never derive energy from daily values

`EnergyID` has to be a **cumulative** meter reading that never jumps back (except when a meter is replaced). A daily value that goes to 0 at midnight is unsuitable as `EnergyID`, because evaluations (balance, Sankey diagram) take the difference of two readings. If you only have daily values, build a cumulative counter yourself or deliver `EnergyID = 0`. If you extrapolate the counter from the power, set `energyMeasured = false`. If `EnergyID` is 0, the consumer does **not** extrapolate the energy from `PowerID`.

## 6. Control (optional, only with release)

The EMS may switch a heat pump in one gentle point only: an **additional run at PV surplus** (for example hot water heating or EEBUS OHPCF). All fields are optional; deliver only what your heat pump can do gently, otherwise 0.

| Field | Meaning |
| --- | --- |
| `optionalRunID` | bool variable, switchable by `RequestAction`. **Writing `true` = one-time request** (the module plans by itself and remembers the request, the consumer does not rewrite cyclically), `false` = withdraw/cancel. The **read value is the actual state** (running/scheduled), not the request |
| `optionalRunPowerID`, `optionalRunPowerMaxID` | expected and highest additional power in W |
| `powerLimitID`, `powerLimitActiveID` | power limit (W) and whether it is active, only if the unit can really do it |
| `controllable` | bool: `true` only if the user has released the control |

Rules:

- **Off by default, released by the user** in the heat pump module. Without release: `optionalRunID = 0`, `controllable = false`.
- **Nothing that writes the memory of the unit (EEPROM) frequently.** Do not write setpoints, heating curves or service values cyclically. Prefer operating-key commands (for example "hot water now").
- The module **checks protective conditions itself** (for example not during defrosting, a minimum interval, a maximum number of starts per day) and checks whether the unit took over the command. A rejected request stays stored for a limited time.
- A run started by hand is **never** cancelled by the EMS.

## 7. Fallback: `failsafeSec` and `failsafeMode`

Every controllable unit needs a defined safe state when commands stop. Without `failsafeSec > 0` the EMS does not release writing for any **third-party module**.

| Field | Meaning |
| --- | --- |
| `failsafeSec` | int, seconds |
| `failsafeMode` | `'deadman'`: the consumer has to write again within `failsafeSec/2`, otherwise the module goes into the safe state by itself. `'maxDuration'`: it is a **one-time request**; the module ends the run by itself after at most `failsafeSec`, the consumer does **not** rewrite. If the field is missing, `'deadman'` applies |

For an additional run as a one-time request `'maxDuration'` fits (HeishaMon does it this way: maximum duration of the run, greater than 0 only with release).

## 8. Minimal example

```php
public function GetFunctions(): array
{
    $power = @$this->GetIDForIdent('Power');
    $energy = @$this->GetIDForIdent('EnergyTotal');   // cumulative, kWh
    $outside = @$this->GetIDForIdent('OutsideTemp');
    $mode = @$this->GetIDForIdent('ModeNorm');         // NRG-Stack enum 0-5, -1
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

## 9. Checklist

- [ ] `<PREFIX>_GetFunctions()` answers quickly, never throws an exception and needs no network access.
- [ ] Mandatory fields present, `contractVersion` as `'Major.Minor'`.
- [ ] Fields that are not delivered are `0` (or missing), never `null` or text.
- [ ] `EnergyID` is cumulative in kWh, never a daily value.
- [ ] `operatingModeNormID` uses the NRG-Stack enum.
- [ ] `lastSeenAt` stays at its value during an outage.
- [ ] Control only with user release, without frequent writes to the unit's memory, with `failsafeSec` and `failsafeMode`.
- [ ] Fields are never renamed or removed, only added.

## 10. Reference modules: two modules, two kinds of fallback

| | HeishaMon (Panasonic via MQTT) | WPEEBUSHub (EEBUS, store beta from 0.6.0) |
| --- | --- | --- |
| Control | `optionalRunID` = hot water boost, only with release | `optionalRunID` = additional run (OHPCF), `powerLimitID` = power limit (LPC), each only if the heat pump offers it |
| `failsafeMode` | `maxDuration`: one start consumes the request, the module ends the run itself after `failsafeSec`; the consumer does **not** rewrite | `deadman`: the consumer rewrites at the latest after `failsafeSec`/2; without rewriting the heat pump falls back to the safe state by itself |
| `failsafeSec` | maximum duration of the boost (default 7200), 0 without release | default 900, adjustable; 0 = unlimited and therefore no write release for third-party consumers |
| Freshness | `lastSeenAt` from the latest data point of the board | `lastSeenAt` = last data packet, `pollInterval` 0 = event driven |
| Read value | actual state (`Force_DHW_State`), not the request | actual state (running/scheduled), not the request |

The consumer reads `failsafeMode` and acts accordingly: `deadman` means rewrite, `maxDuration` means request once and never rewrite. Heat pumps over EEBUS are e.g. Vaillant, Viessmann, Bosch/Buderus, NIBE and Wolf; the module hangs below the NRG-Stack EEBUS gateway. The statutory failure limit of the heat pump on connection loss (LPC failsafe) is not affected by the fallback, and the grid operator's §14a limit comes through the SteuerboxHub, not through the EMS.
