<?php

declare(strict_types=1);

/**
 * Definition aller HeishaMon MQTT-Topics.
 * Quelle: https://github.com/heishamon/HeishaMon/blob/master/MQTT-Topics.md
 *
 * Aufbau eines Eintrags (Key = Topic relativ zum Basistopic):
 *   cap     => Englische Beschriftung (wird über locale.json übersetzt)
 *   kind    => bool | int | float | string | enum
 *   suffix  => Einheit für numerische Werte
 *   digits  => Nachkommastellen (nur float)
 *   options => Wert => Beschriftung (nur enum)
 *   on/off  => Beschriftungen für bool (Standard: On/Off)
 *   set     => HeishaMon-Befehl (commands/SetXxx) - Variable wird schaltbar
 *   min/max/step => Slider-Grenzen für schaltbare numerische Werte
 */
class HeishaMonTopics
{
    public static function topics(): array
    {
        return [
            // TOP0 - TOP143 (main)
            'main/Heatpump_State'                  => ['cap' => 'Heatpump state', 'kind' => 'bool', 'set' => 'SetHeatpump'],
            'main/Pump_Flow'                       => ['cap' => 'Pump flow', 'kind' => 'float', 'suffix' => ' l/min', 'digits' => 2],
            'main/Force_DHW_State'                 => ['cap' => 'Force DHW', 'kind' => 'bool', 'set' => 'SetForceDHW'],
            'main/Quiet_Mode_Schedule'             => ['cap' => 'Quiet mode schedule', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive'],
            'main/Operating_Mode_State'            => ['cap' => 'Operating mode', 'kind' => 'enum', 'set' => 'SetOperationMode', 'options' => [
                0 => 'Heat only', 1 => 'Cool only', 2 => 'Auto (Heat)', 3 => 'DHW only', 4 => 'Heat + DHW',
                5 => 'Cool + DHW', 6 => 'Auto (Heat) + DHW', 7 => 'Auto (Cool)', 8 => 'Auto (Cool) + DHW']],
            'main/Main_Inlet_Temp'                 => ['cap' => 'Main inlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 2],
            'main/Main_Outlet_Temp'                => ['cap' => 'Main outlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 2],
            'main/Main_Target_Temp'                => ['cap' => 'Main outlet target temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Compressor_Freq'                 => ['cap' => 'Compressor frequency', 'kind' => 'int', 'suffix' => ' Hz'],
            'main/DHW_Target_Temp'                 => ['cap' => 'DHW target temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1, 'set' => 'SetDHWTemp', 'min' => 40, 'max' => 75, 'step' => 1],
            'main/DHW_Temp'                        => ['cap' => 'DHW temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Operations_Hours'                => ['cap' => 'Operating hours', 'kind' => 'int', 'suffix' => ' h'],
            'main/Operations_Counter'              => ['cap' => 'Heatpump starts', 'kind' => 'int'],
            'main/Main_Schedule_State'             => ['cap' => 'Main schedule', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive', 'set' => 'SetMainSchedule'],
            'main/Outside_Temp'                    => ['cap' => 'Outside temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Heat_Power_Production'           => ['cap' => 'Heat power production', 'kind' => 'int', 'suffix' => ' W'],
            'main/Heat_Power_Consumption'          => ['cap' => 'Heat power consumption', 'kind' => 'int', 'suffix' => ' W'],
            'main/Powerful_Mode_Time'              => ['cap' => 'Powerful mode', 'kind' => 'enum', 'set' => 'SetPowerfulMode', 'options' => [
                0 => 'Off', 1 => '30 min', 2 => '60 min', 3 => '90 min']],
            'main/Quiet_Mode_Level'                => ['cap' => 'Quiet mode level', 'kind' => 'enum', 'set' => 'SetQuietMode', 'options' => [
                0 => 'Off', 1 => 'Level 1', 2 => 'Level 2', 3 => 'Level 3']],
            'main/Holiday_Mode_State'              => ['cap' => 'Holiday mode', 'kind' => 'enum', 'set' => 'SetHolidayMode', 'options' => [
                0 => 'Off', 1 => 'Scheduled', 2 => 'Active']],
            'main/ThreeWay_Valve_State'            => ['cap' => '3-way valve', 'kind' => 'enum', 'options' => [0 => 'Room', 1 => 'DHW']],
            'main/Outside_Pipe_Temp'               => ['cap' => 'Outside pipe temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/DHW_Heat_Delta'                  => ['cap' => 'DHW heating delta', 'kind' => 'int', 'suffix' => ' K', 'set' => 'SetDHWHeatDelta', 'min' => -12, 'max' => -2, 'step' => 1],
            'main/Heat_Delta'                      => ['cap' => 'Heat delta', 'kind' => 'int', 'suffix' => ' K', 'set' => 'SetFloorHeatDelta', 'min' => 1, 'max' => 15, 'step' => 1],
            'main/Cool_Delta'                      => ['cap' => 'Cool delta', 'kind' => 'int', 'suffix' => ' K', 'set' => 'SetFloorCoolDelta', 'min' => 1, 'max' => 15, 'step' => 1],
            'main/DHW_Holiday_Shift_Temp'          => ['cap' => 'DHW holiday shift temperature', 'kind' => 'int', 'suffix' => ' K'],
            'main/Defrosting_State'                => ['cap' => 'Defrosting', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive', 'set' => 'SetForceDefrost'],
            'main/Z1_Heat_Request_Temp'            => ['cap' => 'Zone 1 heat request temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetZ1HeatRequestTemperature'],
            'main/Z1_Cool_Request_Temp'            => ['cap' => 'Zone 1 cool request temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetZ1CoolRequestTemperature'],
            'main/Z1_Heat_Curve_Target_High_Temp'  => ['cap' => 'Zone 1 heat curve target high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z1_Heat_Curve_Target_Low_Temp'   => ['cap' => 'Zone 1 heat curve target low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z1_Heat_Curve_Outside_High_Temp' => ['cap' => 'Zone 1 heat curve outside high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z1_Heat_Curve_Outside_Low_Temp'  => ['cap' => 'Zone 1 heat curve outside low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Room_Thermostat_Temp'            => ['cap' => 'Room thermostat temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Z2_Heat_Request_Temp'            => ['cap' => 'Zone 2 heat request temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetZ2HeatRequestTemperature'],
            'main/Z2_Cool_Request_Temp'            => ['cap' => 'Zone 2 cool request temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetZ2CoolRequestTemperature'],
            'main/Z1_Water_Temp'                   => ['cap' => 'Zone 1 water temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Z2_Water_Temp'                   => ['cap' => 'Zone 2 water temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Cool_Power_Production'           => ['cap' => 'Cool power production', 'kind' => 'int', 'suffix' => ' W'],
            'main/Cool_Power_Consumption'          => ['cap' => 'Cool power consumption', 'kind' => 'int', 'suffix' => ' W'],
            'main/DHW_Power_Production'            => ['cap' => 'DHW power production', 'kind' => 'int', 'suffix' => ' W'],
            'main/DHW_Power_Consumption'           => ['cap' => 'DHW power consumption', 'kind' => 'int', 'suffix' => ' W'],
            'main/Z1_Water_Target_Temp'            => ['cap' => 'Zone 1 water target temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Z2_Water_Target_Temp'            => ['cap' => 'Zone 2 water target temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Error'                           => ['cap' => 'Last error', 'kind' => 'string'],
            'main/Room_Holiday_Shift_Temp'         => ['cap' => 'Room holiday shift temperature', 'kind' => 'int', 'suffix' => ' K'],
            'main/Buffer_Temp'                     => ['cap' => 'Buffer temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Solar_Temp'                      => ['cap' => 'Solar temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Pool_Temp'                       => ['cap' => 'Pool temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Main_Hex_Outlet_Temp'            => ['cap' => 'Heat exchanger outlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Discharge_Temp'                  => ['cap' => 'Discharge temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Inside_Pipe_Temp'                => ['cap' => 'Inside pipe temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Defrost_Temp'                    => ['cap' => 'Defrost temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Eva_Outlet_Temp'                 => ['cap' => 'Eva outlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Bypass_Outlet_Temp'              => ['cap' => 'Bypass outlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Ipm_Temp'                        => ['cap' => 'IPM temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Z1_Temp'                         => ['cap' => 'Zone 1 actual temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Z2_Temp'                         => ['cap' => 'Zone 2 actual temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/DHW_Heater_State'                => ['cap' => 'DHW heater allowed', 'kind' => 'bool', 'on' => 'Allowed', 'off' => 'Blocked', 'set' => 'SetDHWHeaterState'],
            'main/Room_Heater_State'               => ['cap' => 'Room heater allowed', 'kind' => 'bool', 'on' => 'Allowed', 'off' => 'Blocked', 'set' => 'SetRoomHeaterState'],
            'main/Internal_Heater_State'           => ['cap' => 'Internal heater', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive'],
            'main/External_Heater_State'           => ['cap' => 'External heater', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive'],
            'main/Fan1_Motor_Speed'                => ['cap' => 'Fan 1 motor speed', 'kind' => 'int', 'suffix' => ' rpm'],
            'main/Fan2_Motor_Speed'                => ['cap' => 'Fan 2 motor speed', 'kind' => 'int', 'suffix' => ' rpm'],
            'main/High_Pressure'                   => ['cap' => 'High pressure', 'kind' => 'float', 'suffix' => ' kgf/cm²', 'digits' => 2],
            'main/Pump_Speed'                      => ['cap' => 'Pump speed', 'kind' => 'int', 'suffix' => ' rpm'],
            'main/Low_Pressure'                    => ['cap' => 'Low pressure', 'kind' => 'float', 'suffix' => ' kgf/cm²', 'digits' => 2],
            'main/Compressor_Current'              => ['cap' => 'Compressor current', 'kind' => 'float', 'suffix' => ' A', 'digits' => 2],
            'main/Force_Heater_State'              => ['cap' => 'Force heater', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive'],
            'main/Sterilization_State'             => ['cap' => 'Sterilization', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive', 'set' => 'SetForceSterilization'],
            'main/Sterilization_Temp'              => ['cap' => 'Sterilization temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Sterilization_Max_Time'          => ['cap' => 'Sterilization maximum time', 'kind' => 'int', 'suffix' => ' min'],
            'main/Z1_Cool_Curve_Target_High_Temp'  => ['cap' => 'Zone 1 cool curve target high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z1_Cool_Curve_Target_Low_Temp'   => ['cap' => 'Zone 1 cool curve target low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z1_Cool_Curve_Outside_High_Temp' => ['cap' => 'Zone 1 cool curve outside high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z1_Cool_Curve_Outside_Low_Temp'  => ['cap' => 'Zone 1 cool curve outside low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Heating_Mode'                    => ['cap' => 'Heating mode', 'kind' => 'enum', 'options' => [0 => 'Compensation curve', 1 => 'Direct']],
            'main/Heating_Off_Outdoor_Temp'        => ['cap' => 'Heating off outdoor temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetHeatingOffOutdoorTemp', 'min' => 5, 'max' => 35, 'step' => 1],
            'main/Heater_On_Outdoor_Temp'          => ['cap' => 'Heater on outdoor temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetHeaterOnOutdoorTemp', 'min' => -15, 'max' => 20, 'step' => 1],
            'main/Heat_To_Cool_Temp'               => ['cap' => 'Heat to cool temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Cool_To_Heat_Temp'               => ['cap' => 'Cool to heat temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Cooling_Mode'                    => ['cap' => 'Cooling mode', 'kind' => 'enum', 'options' => [0 => 'Compensation curve', 1 => 'Direct']],
            'main/Z2_Heat_Curve_Target_High_Temp'  => ['cap' => 'Zone 2 heat curve target high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Heat_Curve_Target_Low_Temp'   => ['cap' => 'Zone 2 heat curve target low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Heat_Curve_Outside_High_Temp' => ['cap' => 'Zone 2 heat curve outside high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Heat_Curve_Outside_Low_Temp'  => ['cap' => 'Zone 2 heat curve outside low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Cool_Curve_Target_High_Temp'  => ['cap' => 'Zone 2 cool curve target high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Cool_Curve_Target_Low_Temp'   => ['cap' => 'Zone 2 cool curve target low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Cool_Curve_Outside_High_Temp' => ['cap' => 'Zone 2 cool curve outside high temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Z2_Cool_Curve_Outside_Low_Temp'  => ['cap' => 'Zone 2 cool curve outside low temperature', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Room_Heater_Operations_Hours'    => ['cap' => 'Room heater operating hours', 'kind' => 'int', 'suffix' => ' h'],
            'main/DHW_Heater_Operations_Hours'     => ['cap' => 'DHW heater operating hours', 'kind' => 'int', 'suffix' => ' h'],
            'main/Heat_Pump_Model'                 => ['cap' => 'Heat pump model', 'kind' => 'string'],
            'main/Pump_Duty'                       => ['cap' => 'Pump duty', 'kind' => 'int'],
            'main/Zones_State'                     => ['cap' => 'Zones', 'kind' => 'enum', 'set' => 'SetZones', 'options' => [
                0 => 'Zone 1 active', 1 => 'Zone 2 active', 2 => 'Zone 1 + 2 active']],
            'main/Max_Pump_Duty'                   => ['cap' => 'Maximum pump duty', 'kind' => 'int', 'set' => 'SetMaxPumpDuty', 'min' => 64, 'max' => 254, 'step' => 1],
            'main/Heater_Delay_Time'               => ['cap' => 'Heater delay time', 'kind' => 'int', 'suffix' => ' min', 'set' => 'SetHeaterDelayTime'],
            'main/Heater_Start_Delta'              => ['cap' => 'Heater start delta', 'kind' => 'int', 'suffix' => ' K', 'set' => 'SetHeaterStartDelta'],
            'main/Heater_Stop_Delta'               => ['cap' => 'Heater stop delta', 'kind' => 'int', 'suffix' => ' K', 'set' => 'SetHeaterStopDelta'],
            'main/Buffer_Installed'                => ['cap' => 'Buffer tank installed', 'kind' => 'bool', 'on' => 'Installed', 'off' => 'Not installed', 'set' => 'SetBuffer'],
            'main/DHW_Installed'                   => ['cap' => 'DHW tank installed', 'kind' => 'bool', 'on' => 'Installed', 'off' => 'Not installed'],
            'main/Solar_Mode'                      => ['cap' => 'Solar mode', 'kind' => 'enum', 'options' => [0 => 'Disabled', 1 => 'Buffer', 2 => 'DHW']],
            'main/Solar_On_Delta'                  => ['cap' => 'Solar on delta', 'kind' => 'int', 'suffix' => ' K'],
            'main/Solar_Off_Delta'                 => ['cap' => 'Solar off delta', 'kind' => 'int', 'suffix' => ' K'],
            'main/Solar_Frost_Protection'          => ['cap' => 'Solar frost protection', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Solar_High_Limit'                => ['cap' => 'Solar high limit', 'kind' => 'int', 'suffix' => ' °C'],
            'main/Pump_Flowrate_Mode'              => ['cap' => 'Pump flow rate mode', 'kind' => 'enum', 'set' => 'SetPumpFlowrateMode', 'options' => [
                0 => 'Delta-T', 1 => 'Maximum flow']],
            'main/Liquid_Type'                     => ['cap' => 'Liquid type', 'kind' => 'enum', 'options' => [0 => 'Water', 1 => 'Glycol']],
            'main/Alt_External_Sensor'             => ['cap' => 'Alternative external sensor', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled', 'set' => 'SetAltExternalSensor'],
            'main/Anti_Freeze_Mode'                => ['cap' => 'Anti freeze mode', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled'],
            'main/Optional_PCB'                    => ['cap' => 'Optional PCB', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled'],
            'main/Z2_Sensor_Settings'              => ['cap' => 'Zone 2 sensor setting', 'kind' => 'enum', 'options' => [
                0 => 'Water temperature', 1 => 'External thermostat', 2 => 'Internal thermostat / thermistor']],
            'main/Z1_Sensor_Settings'              => ['cap' => 'Zone 1 sensor setting', 'kind' => 'enum', 'options' => [
                0 => 'Water temperature', 1 => 'External thermostat', 2 => 'Internal thermostat / thermistor']],
            'main/Buffer_Tank_Delta'               => ['cap' => 'Buffer tank delta', 'kind' => 'int', 'suffix' => ' K', 'set' => 'SetBufferDelta', 'min' => 0, 'max' => 10, 'step' => 1],
            'main/External_Pad_Heater'             => ['cap' => 'External pad heater', 'kind' => 'enum', 'set' => 'SetExternalPadHeater', 'options' => [
                0 => 'Disabled', 1 => 'Type A', 2 => 'Type B']],
            'main/Water_Pressure'                  => ['cap' => 'Water pressure', 'kind' => 'float', 'suffix' => ' bar', 'digits' => 2],
            'main/Second_Inlet_Temp'               => ['cap' => 'Second inlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Economizer_Outlet_Temp'          => ['cap' => 'Economizer outlet temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/Second_Room_Thermostat_Temp'     => ['cap' => 'Second room thermostat temperature', 'kind' => 'float', 'suffix' => ' °C', 'digits' => 1],
            'main/External_Control'                => ['cap' => 'External control', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled', 'set' => 'SetExternalControl'],
            'main/External_Heat_Cool_Control'      => ['cap' => 'External heat/cool control', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled', 'set' => 'SetExternalHeatCoolControl'],
            'main/External_Error_Signal'           => ['cap' => 'External error signal', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled', 'set' => 'SetExternalError'],
            'main/External_Compressor_Control'     => ['cap' => 'External compressor control', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled', 'set' => 'SetExternalCompressorControl'],
            'main/Z1_Pump_State'                   => ['cap' => 'Zone 1 pump', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive'],
            'main/Z2_Pump_State'                   => ['cap' => 'Zone 2 pump', 'kind' => 'bool', 'on' => 'Active', 'off' => 'Inactive'],
            'main/TwoWay_Valve_State'              => ['cap' => '2-way valve', 'kind' => 'bool', 'on' => 'On', 'off' => 'Off'],
            'main/ThreeWay_Valve_State2'           => ['cap' => '3-way valve (2nd definition)', 'kind' => 'enum', 'options' => [0 => 'Room', 1 => 'DHW']],
            'main/Z1_Valve_PID'                    => ['cap' => 'Zone 1 mixing valve position', 'kind' => 'float', 'digits' => 1, 'suffix' => ' %'],
            'main/Z2_Valve_PID'                    => ['cap' => 'Zone 2 mixing valve position', 'kind' => 'float', 'digits' => 1, 'suffix' => ' %'],
            'main/Bivalent_Control'                => ['cap' => 'Bivalent control', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled', 'set' => 'SetBivalentControl'],
            'main/Bivalent_Mode'                   => ['cap' => 'Bivalent mode', 'kind' => 'enum', 'set' => 'SetBivalentMode', 'options' => [
                0 => 'Alternative', 1 => 'Parallel', 2 => 'Advanced parallel']],
            'main/Bivalent_Start_Temp'             => ['cap' => 'Bivalent start temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetBivalentStartTemp', 'min' => -15, 'max' => 35, 'step' => 1],
            'main/Bivalent_Advanced_Heat'          => ['cap' => 'Bivalent advanced heat control', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled'],
            'main/Bivalent_Advanced_DHW'           => ['cap' => 'Bivalent advanced DHW control', 'kind' => 'bool', 'on' => 'Enabled', 'off' => 'Disabled'],
            'main/Bivalent_Advanced_Start_Temp'    => ['cap' => 'Bivalent advanced start temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetBivalentAPStartTemp', 'min' => -15, 'max' => 35, 'step' => 1],
            'main/Bivalent_Advanced_Stop_Temp'     => ['cap' => 'Bivalent advanced stop temperature', 'kind' => 'int', 'suffix' => ' °C', 'set' => 'SetBivalentAPStopTemp', 'min' => -15, 'max' => 35, 'step' => 1],
            'main/Bivalent_Advanced_Start_Delay'   => ['cap' => 'Bivalent advanced start delay', 'kind' => 'int', 'suffix' => ' min'],
            'main/Bivalent_Advanced_Stop_Delay'    => ['cap' => 'Bivalent advanced stop delay', 'kind' => 'int', 'suffix' => ' min'],
            'main/Bivalent_Advanced_DHW_Delay'     => ['cap' => 'Bivalent advanced DHW delay', 'kind' => 'int', 'suffix' => ' min'],
            'main/Heating_Control'                 => ['cap' => 'Heating control', 'kind' => 'enum', 'set' => 'SetHeatingControl', 'options' => [
                0 => 'Comfort', 1 => 'Efficiency']],
            'main/Smart_DHW'                       => ['cap' => 'Smart DHW', 'kind' => 'enum', 'set' => 'SetSmartDHW', 'options' => [
                0 => 'Variable', 1 => 'Standard']],
            'main/Quiet_Mode_Priority'             => ['cap' => 'Quiet mode priority', 'kind' => 'enum', 'set' => 'SetQuietModePriority', 'options' => [
                0 => 'Sound', 1 => 'Capacity']],
            'main/Expansion_Valve'                 => ['cap' => 'Expansion valve', 'kind' => 'int'],
            'main/DHW_Sensor_Selection'            => ['cap' => 'DHW sensor selection', 'kind' => 'enum', 'set' => 'SetDHWSensorSelection', 'options' => [
                0 => 'Top', 1 => 'Center']],

            // OPT0 - OPT6 (Optional PCB Emulation)
            'optional/Z1_Water_Pump'               => ['cap' => 'Optional PCB: Zone 1 water pump', 'kind' => 'bool', 'on' => 'On', 'off' => 'Off'],
            'optional/Z1_Mixing_Valve'             => ['cap' => 'Optional PCB: Zone 1 mixing valve', 'kind' => 'enum', 'options' => [
                0 => 'Off', 1 => 'Decrease', 2 => 'Increase']],
            'optional/Z2_Water_Pump'               => ['cap' => 'Optional PCB: Zone 2 water pump', 'kind' => 'bool', 'on' => 'On', 'off' => 'Off'],
            'optional/Z2_Mixing_Valve'             => ['cap' => 'Optional PCB: Zone 2 mixing valve', 'kind' => 'enum', 'options' => [
                0 => 'Off', 1 => 'Decrease', 2 => 'Increase']],
            'optional/Pool_Water_Pump'             => ['cap' => 'Optional PCB: Pool water pump', 'kind' => 'bool', 'on' => 'On', 'off' => 'Off'],
            'optional/Solar_Water_Pump'            => ['cap' => 'Optional PCB: Solar water pump', 'kind' => 'bool', 'on' => 'On', 'off' => 'Off'],
            'optional/Alarm_State'                 => ['cap' => 'Optional PCB: Alarm', 'kind' => 'bool', 'on' => 'On', 'off' => 'Off'],

            // S0-Zaehler direkt an der Platine (GPIO12/GPIO14); WatthourTotal kommt in Wh
            // und wird auf kWh skaliert, damit die Variable direkt als Energiezaehler-Quelle
            // der COP-Berechnung taugt
            's0/Watt/1'                            => ['cap' => 'S0 meter 1 power', 'kind' => 'int', 'suffix' => ' W'],
            's0/Watt/2'                            => ['cap' => 'S0 meter 2 power', 'kind' => 'int', 'suffix' => ' W'],
            's0/WatthourTotal/1'                   => ['cap' => 'S0 meter 1 energy total', 'kind' => 'float', 'suffix' => ' kWh', 'digits' => 3, 'scale' => 0.001],
            's0/WatthourTotal/2'                   => ['cap' => 'S0 meter 2 energy total', 'kind' => 'float', 'suffix' => ' kWh', 'digits' => 3, 'scale' => 0.001],

            // Extra-Datenblock (K/L-Serie und neuer, XTOP0 - XTOP5): praezisere Leistungswerte je
            // Modus. Bei solchen Anlagen liefern die alten main/*_Power_*-Topics laut Firmware-Doku
            // ungueltige Werte (z.B. -200), die Summen im Modul nutzen dann diese hier.
            'extra/Heat_Power_Consumption_Extra'   => ['cap' => 'Heat power consumption (extra data block)', 'kind' => 'int', 'suffix' => ' W'],
            'extra/Cool_Power_Consumption_Extra'   => ['cap' => 'Cool power consumption (extra data block)', 'kind' => 'int', 'suffix' => ' W'],
            'extra/DHW_Power_Consumption_Extra'    => ['cap' => 'DHW power consumption (extra data block)', 'kind' => 'int', 'suffix' => ' W'],
            'extra/Heat_Power_Production_Extra'    => ['cap' => 'Heat power production (extra data block)', 'kind' => 'int', 'suffix' => ' W'],
            'extra/Cool_Power_Production_Extra'    => ['cap' => 'Cool power production (extra data block)', 'kind' => 'int', 'suffix' => ' W'],
            'extra/DHW_Power_Production_Extra'     => ['cap' => 'DHW power production (extra data block)', 'kind' => 'int', 'suffix' => ' W'],
        ];
    }

    /**
     * Gruppen fuer die optionale Linkstruktur, in Anzeige-Reihenfolge.
     * Die Namen werden ueber locale.json uebersetzt.
     */
    public static function groupOrder(): array
    {
        return ['Operation', 'Heating', 'Cooling', 'DHW', 'Power & COP', 'Device values', 'System configuration', 'Optional PCB', 'S0 meters', '1-Wire sensors', 'Board diagnostics'];
    }

    /**
     * Ordnet ein Topic einer Gruppe zu. Nicht gelistete main-Topics gelten als Geraetewerte.
     */
    public static function groupForTopic(string $topic): string
    {
        foreach (self::groupMap() as $group => $topics) {
            if (in_array($topic, $topics, true)) {
                return $group;
            }
        }
        if (strpos($topic, 'optional/') === 0) {
            return 'Optional PCB';
        }
        if (strpos($topic, 's0/') === 0) {
            return 'S0 meters';
        }
        return 'Device values';
    }

    /**
     * Sinnvolle Standard-Reihenfolge: nach Gruppen sortiert (siehe groupOrder),
     * innerhalb der Gruppe in der Reihenfolge der groupMap-Listen, Fallback-Topics
     * (Geraetewerte, Optional-PCB) in TopicMap-Reihenfolge dahinter.
     */
    public static function defaultOrder(): array
    {
        $all = array_keys(self::topics());
        $ordered = [];
        foreach (self::groupOrder() as $group) {
            foreach (self::groupMap()[$group] ?? [] as $topic) {
                if (in_array($topic, $all, true)) {
                    $ordered[] = $topic;
                }
            }
            foreach ($all as $topic) {
                if (!in_array($topic, $ordered, true) && self::groupForTopic($topic) === $group) {
                    $ordered[] = $topic;
                }
            }
        }
        return $ordered;
    }

    private static function groupMap(): array
    {
        return [
            'Operation' => [
                'main/Heatpump_State', 'main/Operating_Mode_State', 'main/Quiet_Mode_Schedule',
                'main/Quiet_Mode_Level', 'main/Quiet_Mode_Priority', 'main/Powerful_Mode_Time',
                'main/Holiday_Mode_State', 'main/Main_Schedule_State', 'main/Defrosting_State',
                'main/Error', 'main/Outside_Temp', 'main/Operations_Hours', 'main/Operations_Counter',
                'main/ThreeWay_Valve_State', 'main/ThreeWay_Valve_State2', 'main/TwoWay_Valve_State',
                'main/Zones_State'
            ],
            'Heating' => [
                'main/Main_Inlet_Temp', 'main/Main_Outlet_Temp', 'main/Main_Target_Temp', 'main/Heat_Delta',
                'main/Z1_Heat_Request_Temp', 'main/Z1_Heat_Curve_Target_High_Temp', 'main/Z1_Heat_Curve_Target_Low_Temp',
                'main/Z1_Heat_Curve_Outside_High_Temp', 'main/Z1_Heat_Curve_Outside_Low_Temp',
                'main/Z2_Heat_Request_Temp', 'main/Z2_Heat_Curve_Target_High_Temp', 'main/Z2_Heat_Curve_Target_Low_Temp',
                'main/Z2_Heat_Curve_Outside_High_Temp', 'main/Z2_Heat_Curve_Outside_Low_Temp',
                'main/Z1_Water_Temp', 'main/Z2_Water_Temp', 'main/Z1_Water_Target_Temp', 'main/Z2_Water_Target_Temp',
                'main/Z1_Temp', 'main/Z2_Temp', 'main/Room_Thermostat_Temp', 'main/Second_Room_Thermostat_Temp',
                'main/Heating_Mode', 'main/Heating_Off_Outdoor_Temp', 'main/Heat_To_Cool_Temp', 'main/Heating_Control',
                'main/Z1_Pump_State', 'main/Z2_Pump_State', 'main/Z1_Valve_PID', 'main/Z2_Valve_PID',
                'main/Room_Holiday_Shift_Temp', 'main/Room_Heater_State', 'main/Room_Heater_Operations_Hours'
            ],
            'Cooling' => [
                'main/Cool_Delta', 'main/Z1_Cool_Request_Temp', 'main/Z1_Cool_Curve_Target_High_Temp',
                'main/Z1_Cool_Curve_Target_Low_Temp', 'main/Z1_Cool_Curve_Outside_High_Temp',
                'main/Z1_Cool_Curve_Outside_Low_Temp', 'main/Z2_Cool_Request_Temp',
                'main/Z2_Cool_Curve_Target_High_Temp', 'main/Z2_Cool_Curve_Target_Low_Temp',
                'main/Z2_Cool_Curve_Outside_High_Temp', 'main/Z2_Cool_Curve_Outside_Low_Temp',
                'main/Cooling_Mode', 'main/Cool_To_Heat_Temp'
            ],
            'DHW' => [
                'main/Force_DHW_State', 'main/DHW_Target_Temp', 'main/DHW_Temp', 'main/DHW_Heat_Delta',
                'main/DHW_Holiday_Shift_Temp', 'main/DHW_Heater_State', 'main/DHW_Installed',
                'main/DHW_Heater_Operations_Hours', 'main/Sterilization_State', 'main/Sterilization_Temp',
                'main/Sterilization_Max_Time', 'main/Smart_DHW', 'main/DHW_Sensor_Selection'
            ],
            'Power & COP' => [
                'main/Heat_Power_Production', 'main/Heat_Power_Consumption', 'main/Cool_Power_Production',
                'main/Cool_Power_Consumption', 'main/DHW_Power_Production', 'main/DHW_Power_Consumption',
                'extra/Heat_Power_Production_Extra', 'extra/Heat_Power_Consumption_Extra',
                'extra/Cool_Power_Production_Extra', 'extra/Cool_Power_Consumption_Extra',
                'extra/DHW_Power_Production_Extra', 'extra/DHW_Power_Consumption_Extra'
            ],
            'System configuration' => [
                'main/Buffer_Installed', 'main/Buffer_Tank_Delta', 'main/Solar_Mode', 'main/Solar_On_Delta',
                'main/Solar_Off_Delta', 'main/Solar_Frost_Protection', 'main/Solar_High_Limit',
                'main/Pump_Flowrate_Mode', 'main/Max_Pump_Duty', 'main/Liquid_Type', 'main/Alt_External_Sensor',
                'main/Anti_Freeze_Mode', 'main/Optional_PCB', 'main/Z1_Sensor_Settings', 'main/Z2_Sensor_Settings',
                'main/External_Pad_Heater', 'main/External_Control', 'main/External_Heat_Cool_Control',
                'main/External_Error_Signal', 'main/External_Compressor_Control', 'main/Bivalent_Control',
                'main/Bivalent_Mode', 'main/Bivalent_Start_Temp', 'main/Bivalent_Advanced_Heat',
                'main/Bivalent_Advanced_DHW', 'main/Bivalent_Advanced_Start_Temp', 'main/Bivalent_Advanced_Stop_Temp',
                'main/Bivalent_Advanced_Start_Delay', 'main/Bivalent_Advanced_Stop_Delay',
                'main/Bivalent_Advanced_DHW_Delay', 'main/Heater_Delay_Time', 'main/Heater_Start_Delta',
                'main/Heater_Stop_Delta', 'main/Heater_On_Outdoor_Temp'
            ]
        ];
    }

    /**
     * Liefert den Variablen-Ident zu einem Topic (relativ zum Basistopic).
     */
    public static function identFromTopic(string $topic): string
    {
        if (strpos($topic, 'optional/') === 0) {
            return 'Optional_' . substr($topic, strlen('optional/'));
        }
        if (strpos($topic, 'main/') === 0) {
            return substr($topic, strlen('main/'));
        }
        if (strpos($topic, 'extra/') === 0) {
            return substr($topic, strlen('extra/'));
        }
        return str_replace('/', '_', $topic);
    }
}
