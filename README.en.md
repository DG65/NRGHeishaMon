# HeishaMon

![Symcon](https://img.shields.io/badge/Symcon-PHPModul-blue)
![Module version](https://img.shields.io/badge/Module_Version-1.41.0-blue)
![Symcon version](https://img.shields.io/badge/Symcon_Version-9.0%2B-blue)
![License](https://img.shields.io/badge/License-PolyForm_Noncommercial_1.0.0-lightgrey)
[![Check Style](https://github.com/DG65/NRGHeishaMon/actions/workflows/check-style.yml/badge.svg)](https://github.com/DG65/NRGHeishaMon/actions/workflows/check-style.yml)
[![PayPal](https://img.shields.io/badge/PayPal-Me-blue?logo=paypal)](https://paypal.me/DietmarGureth)

[Deutsche Fassung](README.md)

Symcon module that connects a [HeishaMon](https://github.com/heishamon/HeishaMon) board on a Panasonic Aquarea heat pump to Symcon via MQTT.

Part of the **NRG-Stack**, the energy module family by DG65.

## Disclaimer

You use this module **at your own risk**. It sends commands to the HeishaMon and therefore directly to the heat pump (for example target temperatures, operating modes and service menu values such as the maximum pump duty). The author accepts **no responsibility or liability** for damage to the HeishaMon, the heat pump or any other part of the installation, nor for consequential damage caused by using this module. There is no entitlement to support or freedom from errors.

## Features

- Receives all HeishaMon datapoints (`main/TOP0` … `main/TOP143` and the optional PCB topics) through the Symcon MQTT Server or MQTT Client.
- Creates status variables **automatically** as soon as the HeishaMon publishes the corresponding topic, so only the datapoints your own heat pump actually delivers appear.
- Suitable presentations: temperatures in °C, power in W, operating modes as selection lists, states as switches.
- Writable values (for example DHW target temperature, operating mode, quiet mode, powerful mode) can be switched directly and are sent to the HeishaMon via `<base topic>/commands/SetXxx`.
- Availability through the LWT topic (variable "Reachable").
- Available in German and English (`locale.json`).

## Requirements

- Symcon 9.0 or later
- HeishaMon with MQTT enabled
- An MQTT Server (or MQTT Client) set up in Symcon that the HeishaMon is connected to

## Installation

### Through the Module Store (recommended)

The module is available in the **Symcon Module Store** and can be installed directly from Symcon: open the Module Store, search for **HeishaMon** and install it. Updates then arrive automatically through the store.

### Through the Module Control (URL)

Alternatively, add the URL of this repository in the Module Control (Core Instances → Modules):

```
https://github.com/DG65/NRGHeishaMon
```

Note: a library installed by URL is not switched to the store version automatically. To change to the Module Store later, remove the URL installation in the Module Control and install the module from the store again. The GUIDs are identical, so existing instances keep their variables and archive data.

## Setup

1. Create an instance of **HeishaMon**.
2. Select the MQTT Server or MQTT Client that the HeishaMon is connected to as the parent instance.
3. Enter the **MQTT base topic** (default: `panasonic_heat_pump`; it must match the base topic configured in the HeishaMon).
4. Apply. The variables are created automatically as soon as the HeishaMon sends data (at the latest with the next publish interval of the HeishaMon).

## Choosing datapoints

In the instance configuration the **Datapoints** table lists all known HeishaMon topics. The **Received** column shows which topics your installation actually sends. Use the **Active** column to deselect individual datapoints. Their variables are **hidden**; object ID, value updates and archive data are kept, and reactivating a datapoint simply shows the variable again. Datapoints whose variable does not exist yet are not created while they are deselected.

Rows can be **reordered by dragging**. The variables below the instance and the links in the link structure follow this order once you apply the changes. The **Reset order and selection** button restores the default.

## 1-Wire sensors

Temperature sensors (for example DS18B20) on the 1-Wire bus of the HeishaMon publish on their own, address-dependent topic instead of the fixed topic list of the heat pump. The module detects them automatically as soon as a first reading arrives and lists them in the **1-Wire sensors** area with their bus address. For each sensor you can give a meaningful **name** (for example "Buffer tank flow") and control activation just like for the other datapoints. Without a name a generic placeholder with the last four characters of the address is shown at first.

## S0 meters

The HeishaMon board can read up to two S0 electricity meters directly (GPIO12 and GPIO14, see the HeishaMon documentation). The module creates **power** (W) and **total energy** (kWh, converted from the raw Wh values) datapoints for each port. If you measure the electricity consumption of the heat pump with such an S0 meter, you can select the total energy variable directly as the **energy counter in the "External energy meter" area**. That gives you a real COP and performance factor measurement without any additional measuring actuator.

## Board diagnostics

From the firmware's `stats` topic the module maintains the **Board diagnostics** group: WiFi quality (%), uptime since board restart, MQTT reconnects, bus read quality (share of error-free datagrams from the CN-CNT bus), active rules (among other things it shows whether the uploaded short-cycle guard ruleset is running) and firmware version. If you enable "Archive monitoring datapoints automatically" in the form (opt-in since 1.33.0, no longer the default), WiFi quality and MQTT reconnects are logged as well. When connections drop, the history then shows whether weak WiFi, MQTT hangs, hidden restarts (uptime jumps) or bus errors (read quality) are the cause.

## Link structure (grouped view)

In Symcon, status variables have to sit directly below the instance, which quickly turns into a long, hard-to-read list in the WebFront. For a clear, grouped WebFront navigation the module can optionally maintain a **link structure**: in the **Link structure** area enable **Create link structure** and select a **target category** (ideally one that is itself visible in the WebFront). The module creates a category tree there:

```
<target category>
└── <instance name>
    ├── Operation
    ├── Heating
    ├── Cooling
    ├── DHW
    ├── Power & COP
    ├── Device values
    ├── System configuration
    ├── Optional PCB
    ├── S0 meters
    ├── 1-Wire sensors
    └── Board diagnostics
```

It holds links to all **active** datapoints, including switchability (a link to the DHW target temperature stays a slider, for example). When a datapoint is deselected in the list, its link disappears automatically; newly received datapoints are sorted in at once. Empty groups are removed. The categories are created below the target category you select, never below the instance.

## COP / performance factor

The module calculates the COP in two ways:

- **COP (HeishaMon estimate)**: automatically from the HeishaMon's own values (thermal output divided by electrical input over all operating modes). No configuration needed, but rough, because Panasonic estimates the input only in steps of about 200 W. For heat pumps of the K/L series and newer, the old power topics deliver invalid values according to the HeishaMon firmware (for example -200). There the board reports the power values in the **extra data block** (`extra/…` topics, from firmware 4.1.7). The module creates them as datapoints and, since version 1.31.0, uses them automatically for electrical power, total thermal output and this COP estimate as soon as they arrive.
- **COP (measured)**: through an external electricity meter (for example a Shelly 3EM on the heat pump phase). If MeterHub already has a meter assigned to the function "heat pump", the module takes it over automatically since version 1.34.0 (status line in the **External energy meter** area; it follows later changes to the assignment). There is nothing to set up, and nothing is copied into a property of its own. Only if you want to use a different meter, or if MeterHub is not installed, enter a variable yourself in the collapsed section "Use your own meter instead". Your own choice always takes priority over MeterHub. The COP is recalculated on every value change; below the **minimum power** (default 100 W, against standby noise) it reports 0.

If the meter (automatic or your own) also provides an energy counter, the module calculates daily values:

- **Energy consumption today**: exactly from the counter reading (the base is reset at midnight, and a counter reset is handled).
- **Heat energy today**: integration of the thermal output in 60-second steps (intermediate values survive a Symcon restart).
- **Performance factor today**: the ratio of the two. With archiving enabled, this builds up the long-term history.

Note: when the backup heater runs, its heat is part of the measured thermal output. Because only the heat pump phase is in the denominator, the COP looks too good during these phases. For rating the compressor alone, that is exactly right.

## Heating automation (optional)

Switches **only the room heating** on and off, never the whole heat pump. The module sends `SetOperationMode` and changes between *heat + DHW* (4, or *auto + DHW* 6), *cool + DHW* (5) and *DHW only* (3). Hot water keeps running in every state. The function is **off** by default; enable it in the form under "Heating automation", and it only acts once the switch variable `Heating automation` (`HeatAuto_Active`) is turned on.

Every 15 minutes the module decides in this order:

1. **Frost protection**: outside mean below 5 °C or any room below 10 °C: heating on, the lock time is ignored. If outside data is missing, the heating also stays on.
2. **Cold**: short outside mean (default 24 h) below 12 °C: heating on.
3. **Warm**: short AND long mean (default 48 h) above 16 °C, and the long mean covered by at least 90 % of its window: heating off. The long mean makes switching off slow on purpose.
4. **In between**: optionally the rooms decide (mean of the selected wall sensors): longer than the delay (2 h) below the minimum (19.5 °C) → heating on; above minimum plus hysteresis (1.5 K) → heating off; otherwise the state is kept.
5. **Lock time**: after a change the state is kept for 12 h (switching on again already after a quarter of it).

Order of priority: **frost protection > user > automation.** If someone changes the operating mode from outside (for example in the dashboard), the automation accepts that, does not send a counter command and starts the lock time again. Only frost protection acts immediately. Auto + DHW counts as the same variant as heat + DHW.

**Sources are found automatically**, so nothing has to be set by hand (your own selection always overrides; the status lines in the form show 🔗 automatic / ✏️ own / ℹ️ nothing found):

- **Outside temperature:** the heat pump's sensor, or a variable of your own.
- **Humidity:** all fresh, plausible variables with a humidity profile (`~Humidity*`) or "humidity" in the name, without outdoor, soil and weather values (an explicit "indoor" value of a weather station is kept). The **highest** value counts, and the dew point is calculated with the highest room temperature (26 °C if no room temperature is known). A wrong pick only errs on the safe side: cooling is more likely blocked than released.
- **Rooms:** one temperature sensor per room of the **Raumstruktur** module (wall sensors before radiator thermostats), without outdoor and unheated rooms (carport, terrace, cellar …; the exclusion list can be edited in the form, for example to add a conservatory or workshop, and also applies to the humidity search), only from three rooms with a valid value, using the **median** instead of the mean (one sensor next to a heat source does not decide alone). Without Raumstruktur and without a selection of your own, only the outside temperature decides.

**Price block** (optional, for dynamic electricity tariffs): in the most expensive hours of the day the automation pauses the room heating (hot water is not touched), so the heat pump does not run exactly then. The price curve is found automatically (Tibber Grid Rewards, Börsenpreis) or comes from a price variable of your own (ct/kWh, needs one day of history). A price counts as "expensive" from the larger of two limits: the 85th percentile of the next 24 hours and the median plus 5 ct/kWh; with a flat price curve nothing is ever blocked. A block only starts while the heating is running, when the rooms have a reserve (without room sensors: when it is clearly above the frost limit outside), at most twice a day and for at most two hours at a time. Frost protection and room comfort end any block. If a battery carries the heat pump through the expensive hours anyway (enter the capacity; the state of charge is read from InverterHub), nothing is blocked.

**Cooling** (optional) needs a dew point or humidity source for the room air (found automatically or your own). It only starts if the lowest cooling target of the cooling curve is at least the safety margin (2 K) above the dew point, and it stops at once when the measured flow temperature falls below it. The module does not change the cooling curve itself.

Each command is checked after about 45 s against the reported operating mode and repeated up to three times (the unit never acknowledges commands). The variable `Heating automation: reason` states in plain text why a decision was made. When you switch the automation off, a heating that was switched off is restored. State and settings can be read with `HEISHA_GetHeatingAutomation()` (including limits, step, unit and default value per setting as well as a `configRevision`) and changed as a partial update with `HEISHA_SetHeatingAutomation()`: the return value holds `ok`, field errors in `errors`, the current `config` and the new `configRevision`; if an outdated revision is sent, nothing changes. Intended for the heat pump monitor of the NRGDashboard.

## Schedule for ECO / comfort and quiet mode (optional)

A weekly plan that sets the **shift of the heating and cooling request** (ECO −5 K, normal 0, comfort +5 K; zone 1 and zone 2, heating and cooling) and the **quiet mode level** (off, level 1…3) by weekday and time of day. The heat pump cannot store such a plan itself (the HeishaMon only writes values), so the module runs it. You edit it graphically in the heat pump monitor of the NRGDashboard; in the form you only switch the function on and see the current state.

- **Structure:** for each channel (zone 1/2 × heating/cooling, quiet mode) segments with weekday, start, end (15 minute grid) and value; in between, the channel's default value applies. Times are the local time of the Symcon server.
- **Off by default,** and a channel only takes part if it is enabled in the plan. That way no second controller ever writes the same value (for example an old weekly program for the quiet mode).
- **Gentle on the unit:** a command is only sent when the planned value changes; after about 45 s the reported value is checked and the command is repeated at most three times, after which a fault is shown in the status.
- **Changed by hand** (for example in the dashboard): stays until the next switching time. The status shows since when (`stateSince`), and the log gets a line when a channel changes to "changed by hand" or "fault". With `HEISHA_SetSchedule(…, '{"applyNow": true}')` you enforce the plan again immediately.
- **Limitations:** the shift only applies while the unit reports a shift (not in direct mode), and the optional short-cycle guard ruleset on the board can overwrite it. Do not use the unit's own quiet mode timer (on the remote control) for the same purpose in parallel.
- For scripts and other modules: `HEISHA_GetSchedule()` / `HEISHA_SetSchedule()` (partial update per channel, validation in the module, optimistic locking through `revision`).

## Hot water boost for the EMS (optional)

The EMS can request a **hot water run** when there is surplus PV power: the heat pump then heats the hot water tank at once, like the hot water key on the unit (operating command `SetForceDHW`). Setpoints, heating curves and the shift are **never** changed for this; whether the unit stores such settings in its EEPROM is not documented, so the module only uses this operating command.

- **Off by default.** Enable it in the "EMS hot water boost" area; only then does the switchable variable `Hot water boost (EMS)` (`Optional_Run`) exist and `HEISHA_GetFunctions()` report `optionalRunID` and `controllable`.
- **Starts only under conditions:** operating mode with hot water, no defrosting, no legionella protection, no forced backup heater, hot water at least 3 K below its target, at least 60 minutes since the last start, at most 4 starts in 24 hours (interval, number and difference are adjustable). Otherwise nothing is sent; the reason appears in the status line and in the log.
- **Check:** the unit never acknowledges commands; after about 45 s the state is checked through `Force_DHW_State`, and the command is repeated at most three times.
- **Cancel:** `false` only cancels a run started by the module; a run started by hand is left alone. A run the module started itself ends after 2 hours (adjustable).
- **Read value:** `Optional_Run` shows the real state of the unit, not the request. The EMS remembers its own request.
- The module does not deliver power fields (`optionalRunPowerID`, `optionalRunPowerMaxID`) or a power limit (`powerLimitID`, `powerLimitActiveID`); they are 0. `contractVersion` '1.18'.

## Energy saving check

The **💡 Energy saving check** area in the instance configuration rates the settings received from the unit against reference values from the documented [HeishaMon example rulesets](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules) and the Panasonic service manual: cycling behavior (average runtime per compressor start), DHW target temperature and reheat delta, backup heater enable temperature and delay, heating-off threshold, heating curve target and pump duty. The check is **display only**; nothing is changed. The rated values are service settings of the heat pump: change them deliberately, and when in doubt with your installer.

## Energy saving rulesets (on the board)

The **⚙️ Energy saving rulesets** area can deploy curated rulesets directly to the HeishaMon board. They keep running there autonomously, even if WiFi or Symcon fail. The first template is the **short-cycle guard** (based on the documented [`Compressor-Short-Cycle-Guard`](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules/Compressor-Short-Cycle-Guard)): after every compressor stop, a restart is suppressed for an adjustable time (default 45 min) as long as it is mild outside (default ≥ 2 °C). Fewer starts mean better efficiency and longer compressor life; in cold weather the block ends immediately.

**Important:** deploying **replaces the entire** ruleset stored on the board. If you maintain your own rules there (for example the extensive [reference ruleset](https://github.com/heishamon/HeishaMon/tree/main/Examples/Rules/Jeisha-DHW-Radiators-Rowbuffer)), do not use this function. The HeishaMon firmware validates every upload itself; an invalid ruleset is discarded and the previous one stays active.

Delimitation within the NRG-Stack (agreed with EMS): board rulesets form the autonomous **protection layer** (short-cycle guard, ramps); the energy **planning** (when to heat DHW, SmartGrid mode, pauses) stays with EMS/Symcon. The templates deliberately contain no time schedule blocks.

## Electrical power & integration into other modules

The module maintains a variable **Electrical power (total)** (W). It holds the measured value of the external electricity meter if one is configured, otherwise the sum of the HeishaMon estimates (heating + cooling + DHW). That way there is always a single, reliable power variable regardless of the measuring setup, for example for visualizations, an energy management system or your own automations.

Other modules can integrate the heat pump automatically, without the user having to assign variables by hand. `HEISHA_GetFunctions()` implements the shared, manufacturer-neutral `heatpump` contract type of the NRG-Stack (also used by WPHub for the Panasonic Comfort Cloud, for example). Consumers such as the NRGDashboard render generically based on the fields that are actually present, regardless of which heat pump module delivers them. The listing shows the main fields with the contract version in which each appeared; the complete current contract (version 1.16) is kept in the NRG-Stack manifest:

```php
$functions = HEISHA_GetFunctions(12345);
// [
//   [
//     'Type'      => 'heatpump',
//     'Caption'   => 'HeishaMon',   // instance name
//     'PowerID'   => 34567,         // variable "Electrical power (total)" in W
//     'EnergyID'  => 45678,         // kWh counter reading of the external meter, 0 if not configured
//     'Measured'  => true,          // true = real measurement, false = HeishaMon estimate
//     'unit'      => 'W',           // physical unit of PowerID, informative
//     'reachable' => true,          // false = heat pump offline, PowerID may be stale
//     // from contractVersion 1.3: heating circuit datapoints for a system schematic
//     // visualization - 0 each if the datapoint has not been received (yet) or is deselected:
//     'pumpFlowID' => 34580, 'pumpSpeedID' => 34581, 'pumpDutyID' => 34582,
//     'threeWayValveStateID' => 34583, 'twoWayValveStateID' => 34584,
//     'mainInletTempID' => 34585, 'mainOutletTempID' => 34586,
//     'z1WaterTempID' => 34587, 'z2WaterTempID' => 34588, 'dhwTempID' => 34589,
//     'bufferTempID' => 34590, 'compressorFreqID' => 34591,
//     'dischargeTempID' => 34592, 'defrostingStateID' => 34593,
//     // from 1.4: external heating circuit pump / mixing valve on the optional 2nd control board
//     // (separate from the internal pump). The mixing valve reports a direction (0=off, 1=closing,
//     // 2=opening), not an absolute position:
//     'z1PumpID' => 34594, 'z1MixingValveID' => 34595,
//     'z2PumpID' => 0, 'z2MixingValveID' => 0,   // 0 = no zone 2 present
//     // from 1.5: outdoor unit fan speed in rpm:
//     'fan1SpeedID' => 34596, 'fan2SpeedID' => 0,   // 0 = only one fan installed
//     // from 1.6: suction gas temperature (counterpart of dischargeTempID);
//     // on Panasonic the best available measuring point Eva_Outlet_Temp (evaporator outlet):
//     'suctionTempID' => 34597,
//     // from 1.7: operating mode, mixing valve position and indoor pipe temperature:
//     'operatingModeID' => 34598,           // RAW Panasonic enum 0-8, for diagnostics only -
//                                           // read operatingModeNormID instead!
//     'z1MixingValvePositionID' => 34599,   // mixing valve position 0-100 % (absolute position,
//     'z2MixingValvePositionID' => 0,       // unlike the direction in z1/z2MixingValveID)
//     'indoorPipeTempID' => 34600,          // in cooling mode the refrigerant side that is actually cold
//     // from 1.8: standardized operating mode, manufacturer-neutral NRG-Stack enum:
//     // 0=standby, 1=heating, 2=cooling, 3=dhw, 4=heating+dhw, 5=cooling+dhw, -1=unknown.
//     // Derived by the module from the Panasonic enum (auto modes -> active direction):
//     'operatingModeNormID' => 34601,
//     // from 1.9: COP / performance factor (0 each if the data source is missing).
//     // Monthly/yearly values are deliberately not part of the contract - aggregation over
//     // a period is up to the consumer (archive / moving average):
//     'copEstimateID' => 34602,            // heat pump's own estimate (about 200 W steps, rough)
//     'copMeasuredID' => 34603,            // real measurement through an external meter
//     'dailyPerformanceFactorID' => 34604, // daily performance factor since midnight
//     // from 1.10: fields for monitoring pages (power / temperature time series, cycling analysis):
//     'heatOutputPowerID' => 34605,        // total thermal output in W (sum of generation)
//     'outsideTempID' => 34606,            // outdoor sensor of the heat pump in °C
//     'compressorStartsID' => 34607,       // cumulative compressor starts
//     'operationsHoursID' => 34608,        // cumulative operating hours
//     'contractVersion' => '1.16'          // contract version (NRG-Stack convention, see SUITE.md)
//   ]
// ]
```

Notes for consumers:

- `EnergyID` deliberately points to the **cumulative** counter reading of the external electricity meter and not to "energy consumption today". The latter is reset at midnight and is therefore unsuitable as an energy counter for evaluations such as a Sankey diagram. If `EnergyID` is 0, do **not** extrapolate the energy from the power.
- `Measured` separates a real measurement from the HeishaMon estimate (rough, in steps of about 200 W). If `false`, the value should not be shown with decimals, as that would be false precision. The flag **cannot** be derived from `EnergyID`, because the power and energy variables can be configured independently of each other.
- `reachable` = `false` when the heat pump is currently not reachable (MQTT connection lost). In that case `PowerID` is **not** reset but freezes at the last known value. Consumers that react to the value (for example load management) should treat it as potentially stale when `reachable = false`.
- All `*ID` fields are 0 as long as the corresponding datapoint does not exist (the unit never sent it, or it was deselected in the datapoint list). Always check for `0` before reading the variable.
- `pumpFlowID`/`pumpSpeedID`/`pumpDutyID`/`twoWayValveStateID` refer to the **internal** pump/valve in the indoor unit. `z1PumpID`/`z1MixingValveID`/`z2PumpID`/`z2MixingValveID` refer to the separate **external** heating circuit pump / mixing valve on the optional 2nd control board. These are physically different components; do not mix them up.
- Since contractVersion 1.7, `z1PumpID`/`z2PumpID` are preferably fed from the **core protocol** (`main/Z1/Z2_Pump_State`). It also reports the state of a **real, physically installed** CZ-NS4P, not only of the HeishaMon's own board emulation. The `optional/...` emulation variables remain as a fallback.
- `z1MixingValveID`/`z2MixingValveID` deliver a **direction** (0=off, 1=closing, 2=opening), **not an absolute position**. The value 0 is therefore ambiguous between "no mixing valve present" and "valve currently stationary". If you have to tell these apart, additionally check `IPS_VariableExists()` on the ID.

## §14a control levers (load management / control box connection)

Heat pumps are typical consumers that can be controlled under §14a of the German Energy Industry Act. The compressor of an Aquarea is usually already below the permitted minimum draw limit, so the real load lever is mostly the **backup heater** (typically 3–9 kW additional). The HeishaMon itself runs no §14a logic (capturing the signal is the job of a separate module, for example SteuerboxHub). The following commands are, however, the toolbox a controlling module can use to react to a curtailment request, all reachable through `HEISHA_SendSetCommand($id, $Command, $Value)`:

| Command | Effect | Role |
| --- | --- | --- |
| `SetDHWHeaterState` | Block/release the backup heater for DHW (0/1) | Primary load lever |
| `SetRoomHeaterState` | Block/release the backup heater for space heating (0/1) | Primary load lever |
| `SetQuietMode` | Quiet mode, reduces compressor output noticeably | Fine step |
| `SetZ1HeatRequestTemperature` / `SetZ2HeatRequestTemperature` | Shift the heat request | Additional lever |
| `SetCurves` | Adjust heating/cooling curves (also through `HEISHA_SetCurves`) | Additional lever |
| `SetForceDHW` / `SetDHWTemp` | Postpone DHW production or lower its target temperature | Additional lever |
| `SetSmartGridMode` | Digital SG ready replacement: normal / increased capacity 1+2 / heat pump and heater off (0-3), see below | Primary load lever (alternative) |

**Protective conditions a controlling module should respect** (both readable live as status variables):

- While `Defrosting_State` is active, **do not intervene**. Defrosting is short and technically necessary; interfering risks icing.
- `Sterilization_State` (legionella protection) may be **postponed** but never suppressed permanently. Hygiene takes priority over optimization.

Note: the HeishaMon does not report who changed a setpoint last (MQTT has no originator). A controlling module should therefore verify its own settings by comparing setpoint and actual value instead of assuming they stay set.

## Extra commands (optional)

In the **Extra commands** area you can enable two write-only functions as switchable variables. The heat pump does **not report their state back**; the display only reflects the last command sent:

- **SmartGrid mode**: digitally replicates the four operating modes that are otherwise only switched through physical dry contacts at the outdoor unit (native "SG ready", service item 16 in the heat pump's operation menu). It only takes effect if the heat pump's own service setting **Optional PCB** is set to **Yes** (a different menu item than "SG ready" itself). Otherwise nothing happens, without an error message.
- **Relay 1/2**: the two relays permanently installed on the *large* HeishaMon board (`gpio/relay/one`/`two`), switching up to 230 V/5 A, for example for an external thermostat contact or a pump. Only present if the large board is actually installed; on the small board these variables switch into the void.

## Commands by script

All HeishaMon commands (see [MQTT topics](https://github.com/heishamon/HeishaMon/blob/master/MQTT-Topics.md)) can also be sent by script:

```php
// Any set command
HEISHA_SendSetCommand(12345, 'SetQuietMode', '2');

// Set heating/cooling curves (SET16, JSON as in the HeishaMon documentation)
HEISHA_SetCurves(12345, '{"zone1":{"heat":{"target":{"high":35,"low":25},"outside":{"high":15,"low":-15}}}}');

// Read/set heating curves in a structured way (two-point model, since version 1.29.0)
$curve = HEISHA_GetHeatingCurve(12345);   // ['curveModel'=>'twoPoint','curveWritable'=>true,'zones'=>['z1'=>['heat'=>[...]|null,'cool'=>...],'z2'=>...|null]]
HEISHA_SetHeatingCurve(12345, 'z1', 'heat', 35, 25, 15, -15);   // zone, mode, flow high/low, outside high/low (°C)
```

Since version 1.30.0, `HEISHA_GetHeatingCurve` additionally returns `shift` per zone (heating/cooling: `mode` `'shift'` = shift -5..+5 or `'direct'` = direct flow target temperature, plus `valueC`) and `boardRulesActive` at the top. To shift: `HEISHA_SetHeatingCurveShift(12345, 'z1', 'heat', -2)`, only if the unit reports shift mode, otherwise `false`. Rules on the board (for example the short-cycle guard) can overwrite the value. Operating functions: `HEISHA_GetOperations(12345)` (`quiet`, `powerful`, `holiday`, `emergencyHeater`, `dhwTargetC`, each with `value`/`min`/`max`) and `HEISHA_SetOperation(12345, 'quiet', 2)`. The HeishaMon knows no holiday period (only on/off).

`HEISHA_SetHeatingCurve` returns `true` as soon as the command has been sent (zone present, values plausible, MQTT gateway active). The heat pump does not acknowledge whether the curve was actually taken over; only reading it back later with `HEISHA_GetHeatingCurve` shows that. Invalid input or an inactive zone 2 give `false`, never an exception.

## Notes on the configuration form

- **📖 Documentation & help** (collapsed): general explanations about the instance; the heading carries the current module version.
- **🆕 New in version …** (expanded): appears after an update with the most important news until you click "Understood", and then only again with the next version.
- **🪟 Form layout** (collapsed, after the functional areas, since version 1.40.0): for each area (1-Wire, link structure, energy meter, heating automation, schedule, energy saving check and rulesets, reboot watchdog, archiving, extra commands) you choose whether it is **expanded**, **collapsed** or **hidden** when the form opens, and at which **position** it stands (1–99). No tick means default; with several ticks, hidden wins over collapsed, which wins over expanded. Hidden areas keep their settings and keep working, only the display is missing. The setting takes effect after "Apply" the next time you open the form; "Reset to default" resets everything (by script: `HEISHA_ResetFormLayout($id)`, which does not save by itself). The areas above (purpose, news, documentation) and below (forum, about this module), the connection fields and the datapoint list stay where they are.
- **Forum hint** at the end of the form: shown once, can be dismissed permanently.

## Notes

- According to the HeishaMon documentation, state topics can in exceptional cases deliver the value `-1` (unknown); for switch states the module treats this as "off".
- Unknown topics (for example `stats`, `1wire`, `s0`) are ignored; the **Debug: unknown topics** option shows them in the debug window.

## Changelog

All changes are documented in the [CHANGELOG](CHANGELOG.md) (German).

## License

This module is part of the **NRG-Stack** and is licensed under the [PolyForm Noncommercial License 1.0.0](LICENSE):

- Private and non-commercial use is free.
- **Commercial use** requires a separate license from the rights holder (contact: DG65).
- The change only applies going forward: older versions already published under MIT (up to version 1.2.0) remain MIT.
- Donations are explicitly welcome and entirely voluntary: [paypal.me/DietmarGureth](https://paypal.me/DietmarGureth)
