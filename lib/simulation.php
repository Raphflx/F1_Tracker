<?php
declare(strict_types=1);

/**
 * Simulateur OpenF1 — mode démo du live timing.
 *
 * Imite les réponses brutes d'OpenF1 (mêmes champs, mêmes formats) à partir
 * de l'heure courante, sans aucun appel réseau. Activé par openf1_get() quand
 * f1_set_demo_phase() a été appelé (paramètre ?demo= de l'URL).
 *
 * Les données sont déterministes : deux requêtes au même instant renvoient
 * la même chose, et la session "avance" toute seule avec le temps.
 * Toutes les données (GP, circuit, messages) sont fictives.
 */

const DEMO_SESSION_KEY = 1;
const DEMO_MEETING_KEY = 1;
const DEMO_TOTAL_LAPS  = 57;

/** Phases de démo acceptées dans l'URL (?demo=1 équivaut à "live"). */
const DEMO_PHASES = ['live', 'upcoming', 'finished'];

/**
 * Lit le paramètre GET "demo" : "1" ou "live" → live, "upcoming", "finished".
 * Absent ou valeur inconnue → null (données réelles).
 */
function demo_phase_from_query(): ?string
{
    $value = filter_input(INPUT_GET, 'demo');
    if (!is_string($value)) {
        return null;
    }
    $value = strtolower($value);
    if ($value === '1') {
        return 'live';
    }
    return in_array($value, DEMO_PHASES, true) ? $value : null;
}

/**
 * Valeurs forçables depuis l'encart « Réglages de la démo » :
 * nom du paramètre GET => [min, max] (entiers). Vide ou hors bornes → automatique.
 */
const DEMO_NUMERIC_OVERRIDES = [
    'temp_piste' => [0, 70],     // °C
    'temp_air'   => [-10, 50],   // °C
    'humidite'   => [0, 100],    // %
    'vent'       => [0, 100],    // km/h
    'tour'       => [1, DEMO_TOTAL_LAPS],
];

/** États de piste forçables : valeur GET => [catégorie, drapeau, message] OpenF1. */
const DEMO_TRACK_OVERRIDES = [
    'vert'  => ['Flag',      'GREEN',  'TRACK CLEAR'],
    'jaune' => ['Flag',      'YELLOW', 'YELLOW IN TRACK SECTOR 2'],
    'sc'    => ['SafetyCar', null,     'SAFETY CAR DEPLOYED'],
    'rouge' => ['Flag',      'RED',    'RED FLAG'],
];

/**
 * Lit les valeurs forcées de l'URL (déjà validées). Ne renvoie que celles
 * qui sont renseignées et valides ; le reste reste automatique.
 */
function demo_overrides_from_query(): array
{
    $overrides = [];

    $piste = filter_input(INPUT_GET, 'piste');
    if (is_string($piste) && isset(DEMO_TRACK_OVERRIDES[$piste])) {
        $overrides['piste'] = $piste;
    }

    foreach (DEMO_NUMERIC_OVERRIDES as $name => [$min, $max]) {
        $value = filter_input(INPUT_GET, $name, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if (is_int($value)) {
            $overrides[$name] = $value;
        }
    }

    return $overrides;
}

/**
 * Paramètres d'URL du mode démo (phase + valeurs forcées), pour les liens
 * et le polling JS. Construits uniquement à partir de valeurs validées.
 */
function demo_query_string(string $phase, array $overrides): string
{
    return http_build_query(['demo' => $phase] + $overrides);
}

/**
 * Bornes UTC (timestamps) de la session fictive selon la phase demandée.
 * - live     : en cours depuis 10 à 130 min (cycle de 2 h), jamais terminée
 * - upcoming : démarre à la prochaine demi-heure
 * - finished : terminée depuis environ 1 h
 */
function demo_session_bounds(string $phase, int $now): array
{
    return match ($phase) {
        'upcoming' => [$start = (intdiv($now, 1800) + 1) * 1800, $start + 7200],
        'finished' => [($end = intdiv($now, 3600) * 3600 - 3600) - 7200, $end],
        default    => [$start = intdiv($now, 7200) * 7200 - 600, $start + 8400],
    };
}

/**
 * Tour en cours à un instant donné (1 tour ≈ 2 min 10 s).
 */
function demo_lap_at(int $start, int $t): int
{
    return max(1, min(DEMO_TOTAL_LAPS, intdiv(max(0, $t - $start), 130) + 1));
}

/**
 * Point d'entrée : renvoie la réponse simulée d'un chemin OpenF1
 * ("sessions?year=2026", "weather?session_key=1", …).
 */
function demo_openf1_get(string $pathAndQuery, string $phase, array $overrides = []): array
{
    $endpoint = (string)parse_url('/' . ltrim($pathAndQuery, '/'), PHP_URL_PATH);
    parse_str((string)parse_url('/' . ltrim($pathAndQuery, '/'), PHP_URL_QUERY), $query);

    $now = time();
    [$start, $end] = demo_session_bounds($phase, $now);

    // Les mesures et messages n'existent qu'à partir du début de la session
    $until = min($now, $end);

    return match (trim($endpoint, '/')) {
        'sessions'     => [demo_session($start, $end)],
        'meetings'     => [demo_meeting($start)],
        'weather'      => demo_apply_weather_overrides(
            $now >= $start ? demo_weather($start, $until) : [], $overrides, $until
        ),
        'race_control' => demo_apply_race_control_overrides(
            $now >= $start ? demo_race_control($start, $until, $query) : [], $overrides, $start, $until
        ),
        default        => [],
    };
}

/**
 * Remplace les valeurs de la dernière mesure météo par les valeurs forcées
 * (crée une mesure si la session n'a pas encore commencé).
 */
function demo_apply_weather_overrides(array $list, array $overrides, int $until): array
{
    $map = ['temp_piste' => 'track_temp', 'temp_air' => 'air_temp', 'humidite' => 'humidity', 'vent' => 'wind_speed'];
    if (!array_intersect_key($overrides, $map)) {
        return $list;
    }

    $last = $list ? array_pop($list) : ['session_key' => DEMO_SESSION_KEY, 'date' => demo_iso($until)];
    foreach ($map as $param => $field) {
        if (isset($overrides[$param])) {
            // Le vent est saisi en km/h ; OpenF1 le fournit en m/s
            $last[$field] = $param === 'vent' ? round($overrides[$param] / 3.6, 2) : $overrides[$param];
        }
    }
    $list[] = $last;
    return $list;
}

/**
 * Applique l'état de piste et le tour forcés : plafonne les tours au tour
 * choisi et ajoute un dernier message portant l'état de piste voulu.
 */
function demo_apply_race_control_overrides(array $events, array $overrides, int $start, int $until): array
{
    if (!isset($overrides['piste']) && !isset($overrides['tour'])) {
        return $events;
    }

    $lap = $overrides['tour'] ?? null;
    if ($lap !== null) {
        foreach ($events as &$e) {
            $e['lap_number'] = min((int)$e['lap_number'], $lap);
        }
        unset($e);
    }

    // Dernier message (le plus récent) : état de piste forcé, ou état actuel conservé
    $last = $events ? $events[count($events) - 1] : null;
    [$category, $flag, $message] = isset($overrides['piste'])
        ? DEMO_TRACK_OVERRIDES[$overrides['piste']]
        : [$last['category'] ?? 'Flag', $last['flag'] ?? 'GREEN', $last['message'] ?? 'TRACK CLEAR'];

    $events[] = [
        'session_key' => DEMO_SESSION_KEY,
        'date'        => demo_iso(max($until, $start) + 1),
        'category'    => $category,
        'flag'        => $flag,
        'message'     => $message,
        'lap_number'  => $lap ?? ($last['lap_number'] ?? demo_lap_at($start, $until)),
    ];
    return $events;
}

function demo_iso(int $timestamp): string
{
    return gmdate('Y-m-d\TH:i:s+00:00', $timestamp);
}

function demo_session(int $start, int $end): array
{
    return [
        'session_key'  => DEMO_SESSION_KEY,
        'meeting_key'  => DEMO_MEETING_KEY,
        'year'         => (int)gmdate('Y', $start),
        'session_name' => 'Course',
        'session_type' => 'Race',
        'date_start'   => demo_iso($start),
        'date_end'     => demo_iso($end),
        'gmt_offset'   => '02:00:00',
        'total_laps'   => DEMO_TOTAL_LAPS,
    ];
}

function demo_meeting(int $start): array
{
    return [
        'meeting_key'        => DEMO_MEETING_KEY,
        'year'               => (int)gmdate('Y', $start),
        'meeting_name'       => 'Grand Prix de démonstration',
        'country_name'       => 'Pays fictif',
        'location'           => 'Ville fictive',
        'circuit_short_name' => 'Circuit de démo',
        'date_start'         => demo_iso($start),
        'gmt_offset'         => '02:00:00',
    ];
}

/**
 * Une mesure météo par minute, avec des variations lentes et réalistes.
 * wind_speed est en m/s, comme dans OpenF1.
 */
function demo_weather(int $start, int $until): array
{
    $list = [];
    for ($t = $start; $t <= $until; $t += 60) {
        $e = $t - $start;
        $list[] = [
            'session_key' => DEMO_SESSION_KEY,
            'date'        => demo_iso($t),
            'track_temp'  => round(42 + 4 * sin($e / 1800), 1),
            'air_temp'    => round(27 + 1.5 * sin($e / 2400), 1),
            'humidity'    => round(45 + 6 * cos($e / 2000), 1),
            'wind_speed'  => round(2.5 + 1.5 * sin($e / 600), 1),
        ];
    }
    return $list;
}

/**
 * Messages de la direction de course, rejoués en boucle toutes les 12 min :
 * drapeau vert → jaune → piste dégagée → safety car (ou drapeau rouge
 * un cycle sur trois) → piste dégagée.
 */
function demo_race_control(int $start, int $until, array $query): array
{
    // [décalage en secondes dans le cycle, catégorie, drapeau, message]
    $cycle = [
        [0,   'Flag',      'GREEN',  'GREEN LIGHT - PIT EXIT OPEN'],
        [300, 'Flag',      'YELLOW', 'YELLOW IN TRACK SECTOR 2'],
        [360, 'Flag',      'CLEAR',  'CLEAR IN TRACK SECTOR 2'],
        [480, 'SafetyCar', null,     'SAFETY CAR DEPLOYED'],
        [600, 'SafetyCar', null,     'SAFETY CAR IN THIS LAP'],
        [660, 'Flag',      'GREEN',  'TRACK CLEAR'],
    ];
    $cycleLength = 720;

    // Filtre "date>=" d'OpenF1 (parse_str transforme "date>=X" en ["date>" => "X"])
    $since = isset($query['date>']) ? (int)strtotime((string)$query['date>'] . ' UTC') : 0;

    $events = [];
    for ($cycleStart = $start, $n = 0; $cycleStart <= $until; $cycleStart += $cycleLength, $n++) {
        foreach ($cycle as [$offset, $category, $flag, $message]) {
            $t = $cycleStart + $offset;
            if ($t > $until || $t < $since) continue;

            // Un cycle sur trois : drapeau rouge à la place du safety car
            if ($category === 'SafetyCar' && $n % 3 === 2) {
                [$category, $flag, $message] = $offset === 480
                    ? ['Flag', 'RED', 'RED FLAG']
                    : ['Flag', 'RED', 'SESSION WILL RESUME SHORTLY'];
            }

            $events[] = [
                'session_key' => DEMO_SESSION_KEY,
                'date'        => demo_iso($t),
                'category'    => $category,
                'flag'        => $flag,
                'message'     => $message,
                'lap_number'  => demo_lap_at($start, $t),
            ];
        }
    }
    return $events;
}
