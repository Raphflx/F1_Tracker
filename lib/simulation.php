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
function demo_openf1_get(string $pathAndQuery, string $phase): array
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
        'weather'      => $now >= $start ? demo_weather($start, $until) : [],
        'race_control' => $now >= $start ? demo_race_control($start, $until, $query) : [],
        default        => [],
    };
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
