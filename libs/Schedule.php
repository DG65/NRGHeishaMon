<?php

declare(strict_types=1);

/**
 * Wochen-Zeitplan fuer Verschiebung (ECO/Normal/Komfort) und Fluesterstufe - reine Funktionen ohne IPS-Aufrufe,
 * damit sie einzeln testbar sind. Das Modul fuehrt den Plan aus (HeishaMon::scheduleTick()), die Anlage selbst kann
 * keinen solchen Plan aufnehmen.
 *
 * Plandaten: ['channels' => [kanal => ['enabled' => bool, 'default' => int, 'segments' => [['day','from','to','value'], ...]]]]
 * day = ISO-Wochentag 1..7 (Montag = 1), from/to = Minuten seit Mitternacht (0..1440, Vielfache von 15, from < to),
 * Luecken zwischen den Abschnitten gelten als 'default' des Kanals. Zeiten sind die lokale Zeit des IPS-Servers.
 */
final class HeishaSchedule
{
    public const GRANULARITY_MIN = 15;

    /** Kanal => Anzeigename, Art, Wertebereich, Praefix/Modus der Anlage (shift) */
    public const CHANNELS = [
        'z1Heat' => ['label' => 'Zone 1 heating', 'kind' => 'shift', 'min' => -5, 'max' => 5, 'zone' => 'z1', 'mode' => 'heat'],
        'z1Cool' => ['label' => 'Zone 1 cooling', 'kind' => 'shift', 'min' => -5, 'max' => 5, 'zone' => 'z1', 'mode' => 'cool'],
        'z2Heat' => ['label' => 'Zone 2 heating', 'kind' => 'shift', 'min' => -5, 'max' => 5, 'zone' => 'z2', 'mode' => 'heat'],
        'z2Cool' => ['label' => 'Zone 2 cooling', 'kind' => 'shift', 'min' => -5, 'max' => 5, 'zone' => 'z2', 'mode' => 'cool'],
        'quiet'  => ['label' => 'Quiet mode', 'kind' => 'level', 'min' => 0, 'max' => 3]
    ];

    /** Leerer Plan: kein Kanal beteiligt, Vorgabewert 0. */
    public static function emptyPlan(): array
    {
        $channels = [];
        foreach (array_keys(self::CHANNELS) as $key) {
            $channels[$key] = ['enabled' => false, 'default' => 0, 'segments' => []];
        }
        return ['channels' => $channels];
    }

    /**
     * Ein Kanal-Dokument pruefen und normalisieren. Liefert [normalisierterKanal|null, fehler[]], Fehler als
     * [['field' => 'kanal.feld', 'message' => Schluessel], ...] (Schluessel werden vom Modul uebersetzt).
     *
     * @return array{0: ?array, 1: array}
     */
    public static function validateChannel(string $key, array $data): array
    {
        $definition = self::CHANNELS[$key] ?? null;
        $errors = [];
        if ($definition === null) {
            return [null, [['field' => $key, 'message' => 'Unknown channel']]];
        }
        $min = $definition['min'];
        $max = $definition['max'];
        $result = ['enabled' => false, 'default' => 0, 'segments' => []];
        if (array_key_exists('enabled', $data)) {
            if (!is_bool($data['enabled'])) {
                $errors[] = ['field' => $key . '.enabled', 'message' => 'Value must be true or false'];
            } else {
                $result['enabled'] = $data['enabled'];
            }
        }
        if (array_key_exists('default', $data)) {
            if (!is_int($data['default']) || $data['default'] < $min || $data['default'] > $max) {
                $errors[] = ['field' => $key . '.default', 'message' => 'Value is outside the allowed range'];
            } else {
                $result['default'] = $data['default'];
            }
        }
        if (array_key_exists('segments', $data)) {
            if (!is_array($data['segments'])) {
                $errors[] = ['field' => $key . '.segments', 'message' => 'Value must be a list of segments'];
            } else {
                $perDay = [];
                foreach (array_values($data['segments']) as $index => $segment) {
                    $field = $key . '.segments[' . $index . ']';
                    if (!is_array($segment) || !isset($segment['day'], $segment['from'], $segment['to'], $segment['value'])
                        || !is_int($segment['day']) || !is_int($segment['from']) || !is_int($segment['to']) || !is_int($segment['value'])) {
                        $errors[] = ['field' => $field, 'message' => 'A segment needs whole numbers day, from, to and value'];
                        continue;
                    }
                    if ($segment['day'] < 1 || $segment['day'] > 7) {
                        $errors[] = ['field' => $field . '.day', 'message' => 'The weekday must be 1 (Monday) to 7 (Sunday)'];
                        continue;
                    }
                    if ($segment['from'] < 0 || $segment['to'] > 1440 || $segment['from'] >= $segment['to']
                        || $segment['from'] % self::GRANULARITY_MIN !== 0 || $segment['to'] % self::GRANULARITY_MIN !== 0) {
                        $errors[] = ['field' => $field, 'message' => 'Times must lie between 0 and 1440 minutes in steps of 15, start before end'];
                        continue;
                    }
                    if ($segment['value'] < $min || $segment['value'] > $max) {
                        $errors[] = ['field' => $field . '.value', 'message' => 'Value is outside the allowed range'];
                        continue;
                    }
                    $perDay[$segment['day']][] = ['day' => $segment['day'], 'from' => $segment['from'], 'to' => $segment['to'], 'value' => $segment['value']];
                }
                $normalized = [];
                foreach ($perDay as $day => $list) {
                    usort($list, static fn ($a, $b) => $a['from'] <=> $b['from']);
                    $previousEnd = 0;
                    foreach ($list as $segment) {
                        if ($segment['from'] < $previousEnd) {
                            $errors[] = ['field' => $key . '.segments', 'message' => 'Segments of the same weekday must not overlap'];
                            break;
                        }
                        $previousEnd = $segment['to'];
                    }
                    $normalized = array_merge($normalized, $list);
                }
                usort($normalized, static fn ($a, $b) => [$a['day'], $a['from']] <=> [$b['day'], $b['from']]);
                $result['segments'] = $normalized;
            }
        }
        return [$errors === [] ? $result : null, $errors];
    }

    /** Sollwert eines Kanals zum Zeitpunkt $ts (lokale Zeit): Wert des passenden Abschnitts, sonst der Vorgabewert. */
    public static function plannedValue(array $channel, int $ts): int
    {
        $day = (int) date('N', $ts);
        $minute = (int) date('G', $ts) * 60 + (int) date('i', $ts);
        foreach ($channel['segments'] as $segment) {
            if ((int) $segment['day'] === $day && $minute >= (int) $segment['from'] && $minute < (int) $segment['to']) {
                return (int) $segment['value'];
            }
        }
        return (int) $channel['default'];
    }

    /** Naechster Zeitpunkt (Unix, 15er-Raster), an dem sich der Sollwert aendert; 0 = innerhalb von 8 Tagen keiner. */
    public static function nextChange(array $channel, int $ts): int
    {
        $step = self::GRANULARITY_MIN * 60;
        $start = intdiv($ts, $step) * $step;
        $current = self::plannedValue($channel, $ts);
        for ($t = $start + $step; $t <= $ts + 8 * 86400; $t += $step) {
            if (self::plannedValue($channel, $t) !== $current) {
                return $t;
            }
        }
        return 0;
    }

    /** Pruefsumme der Plandaten fuer die optimistische Sperre. */
    public static function revision(array $plan): int
    {
        return crc32(json_encode($plan));
    }
}
