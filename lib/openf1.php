<?php
declare(strict_types=1);

/**
 * Petit client OpenF1 (API non officielle).
 * Centralise la récupération JSON avec timeout + gestion d’erreurs,
 * ainsi que les services métier utilisés par les pages et par api/.
 */

require_once __DIR__ . '/datetime.php';
require_once __DIR__ . '/text.php';

const OPENF1_BASE_URL = 'https://api.openf1.org/v1';

/**
 * Récupère une URL OpenF1 et renvoie un tableau PHP (JSON décodé).
 *
 * @throws RuntimeException si erreur réseau/HTTP/JSON
 */
function openf1_get(string $pathAndQuery, int $timeoutSeconds = 8): array
{
    $url = rtrim(OPENF1_BASE_URL, '/') . '/' . ltrim($pathAndQuery, '/');

    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => $timeoutSeconds,
            'header'  => "Accept: application/json\r\nUser-Agent: F1Tracker\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    // Statut HTTP de la dernière réponse (après redirections éventuelles).
    // http_get_last_response_headers() remplace $http_response_header (déprécié en PHP 8.5).
    $headers = function_exists('http_get_last_response_headers')
        ? (http_get_last_response_headers() ?? [])
        : ($http_response_header ?? []);
    $statusLines = array_values(array_filter($headers, static fn($h) => str_starts_with((string)$h, 'HTTP/')));
    $statusLine  = (string)($statusLines[count($statusLines) - 1] ?? '');
    $isHttpOk    = (bool)preg_match('#^HTTP/\S+\s+200\b#', $statusLine);

    if ($raw === false || !$isHttpOk) {
        throw new RuntimeException("Erreur lors de l'appel OpenF1: {$url} ({$statusLine})");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException("Réponse JSON invalide OpenF1: {$url}");
    }

    return $data;
}

// --- Helpers de traitement des données OpenF1 ---

function track_status_from_race_control(array $raceControl): array
{
    if (empty($raceControl)) return ['Clair', 'track-status--green'];

    usort($raceControl, static fn($a, $b) =>
        strtotime((string)($a['date'] ?? '')) <=> strtotime((string)($b['date'] ?? ''))
    );
    $last = $raceControl[count($raceControl) - 1];

    $category = strtoupper((string)($last['category'] ?? ''));
    $flag     = strtoupper((string)($last['flag'] ?? ''));
    $message  = strtoupper((string)($last['message'] ?? ''));

    if ($category === 'SAFETYCAR' || str_contains($message, 'SAFETY CAR')) return ['Safety Car', 'track-status--yellow'];
    if (str_contains($flag, 'RED')    || str_contains($message, 'RED FLAG'))  return ['Drapeau rouge', 'track-status--red'];
    if (str_contains($flag, 'YELLOW'))                                         return ['Drapeau jaune', 'track-status--yellow'];
    if (str_contains($flag, 'GREEN'))                                          return ['Clair', 'track-status--green'];

    return ['Clair', 'track-status--green'];
}

function current_lap_from_race_control(array $raceControl): ?int
{
    $max = null;
    foreach ($raceControl as $e) {
        $n = (int)($e['lap_number'] ?? 0);
        if ($n > 0 && ($max === null || $n > $max)) $max = $n;
    }
    return $max;
}

/**
 * Détermine la session "focus" parmi une liste triée chronologiquement.
 * Priorité : live > prochaine à venir > dernière passée.
 */
function pick_focus_session(array $sessions, DateTime $nowParis): ?array
{
    $nowUtc = clone $nowParis;
    $nowUtc->setTimezone(new DateTimeZone('UTC'));

    $live = null;
    $next = null;
    $last = null;

    foreach ($sessions as $s) {
        if (empty($s['date_start']) || empty($s['date_end'])) continue;

        $start = new DateTime((string)$s['date_start'], new DateTimeZone('UTC'));
        $end   = new DateTime((string)$s['date_end'],   new DateTimeZone('UTC'));

        if ($start <= $nowUtc && $nowUtc <= $end) {
            $live = $s;
            break;
        }
        if ($start > $nowUtc) {
            if ($next === null || new DateTime((string)$next['date_start'], new DateTimeZone('UTC')) > $start) {
                $next = $s;
            }
        }
        if ($end < $nowUtc) {
            $last = $s;
        }
    }

    return $live ?? $next ?? $last;
}

// ---------------------------------------------------------
// Services métier
// ---------------------------------------------------------
// Utilisés à la fois par les pages PHP (rendu serveur) et par les
// endpoints api/*.php (JSON pour le JS). Les dates sont renvoyées
// en ISO 8601 pour rester sérialisables.

/**
 * Trie une liste de sessions par date de début (ordre chronologique).
 */
function sort_sessions_by_start(array &$sessions): void
{
    usort($sessions, static fn($a, $b) =>
        strtotime((string)($a['date_start'] ?? '')) <=> strtotime((string)($b['date_start'] ?? ''))
    );
}

/**
 * Liste des saisons disponibles, de la plus récente à la plus ancienne.
 */
function f1_get_years(): array
{
    $set = [];
    foreach (openf1_get('meetings') as $m) {
        // 1) Priorité au champ year si présent
        if (isset($m['year']) && is_numeric($m['year'])) {
            $y = (int)$m['year'];
            if ($y > 1950 && $y < 2100) {
                $set[$y] = true;
                continue;
            }
        }
        // 2) Fallback : année de date_start
        if (!empty($m['date_start'])) {
            try {
                $y = (int)(new DateTime((string)$m['date_start'], new DateTimeZone('UTC')))->format('Y');
                if ($y > 1950 && $y < 2100) $set[$y] = true;
            } catch (Throwable) {
                // date illisible : ignorée
            }
        }
    }

    $years = array_keys($set);
    rsort($years);
    return $years;
}

/**
 * Meetings (GP) d'une saison, avec la plage du week-end en heure locale circuit.
 */
function f1_get_meetings(int $year): array
{
    $meetings = openf1_get("meetings?year={$year}");
    $sessions = openf1_get("sessions?year={$year}");

    // Plage week-end par meeting à partir des sessions
    $weekendRanges = [];
    foreach ($sessions as $s) {
        if (!isset($s['meeting_key'], $s['date_start'], $s['date_end'])) continue;

        $mk     = (string)$s['meeting_key'];
        $offset = parse_gmt_offset_to_minutes($s['gmt_offset'] ?? null);

        $localStart = utc_to_local_with_offset(new DateTime((string)$s['date_start'], new DateTimeZone('UTC')), $offset);
        $localEnd   = utc_to_local_with_offset(new DateTime((string)$s['date_end'],   new DateTimeZone('UTC')), $offset);

        if (!isset($weekendRanges[$mk])) {
            $weekendRanges[$mk] = ['start' => $localStart, 'end' => $localEnd];
            continue;
        }
        if ($localStart < $weekendRanges[$mk]['start']) $weekendRanges[$mk]['start'] = $localStart;
        if ($localEnd   > $weekendRanges[$mk]['end'])   $weekendRanges[$mk]['end']   = $localEnd;
    }

    $result = [];
    foreach ($meetings as $m) {
        $mk = (string)($m['meeting_key'] ?? '');
        if ($mk === '') continue;

        $circuit = (string)($m['circuit_short_name'] ?? ($m['location'] ?? ''));

        // Plage week-end : sessions en priorité, fallback meeting.date_start (+2 jours)
        if (isset($weekendRanges[$mk])) {
            $start = $weekendRanges[$mk]['start'];
            $end   = $weekendRanges[$mk]['end'];
        } elseif (!empty($m['date_start'])) {
            $offset = parse_gmt_offset_to_minutes($m['gmt_offset'] ?? null);
            $start  = utc_to_local_with_offset(new DateTime((string)$m['date_start'], new DateTimeZone('UTC')), $offset);
            $end    = (clone $start)->modify('+2 days');
        } else {
            $start = $end = null;
        }

        $result[] = [
            'meeting_key'   => (int)$mk,
            'meeting_name'  => $m['meeting_name'] ?? null,
            'country_name'  => (string)($m['country_name'] ?? ''),
            'location'      => $m['location'] ?? null,
            'circuit'       => $circuit,
            'circuit_slug'  => slugify($circuit),
            'weekend_start' => $start?->format(DateTime::ATOM),
            'weekend_end'   => $end?->format(DateTime::ATOM),
        ];
    }

    return $result;
}

/**
 * Détail d'un meeting : infos + sessions triées (heure circuit et heure de Paris).
 * Renvoie null si le meeting n'existe pas.
 */
function f1_get_meeting(int $meetingKey): ?array
{
    $meeting = openf1_get('meetings?meeting_key=' . $meetingKey)[0] ?? null;
    if (!$meeting) {
        return null;
    }

    $sessions = openf1_get('sessions?meeting_key=' . $meetingKey);
    sort_sessions_by_start($sessions);

    $formatted = [];
    foreach ($sessions as $s) {
        $utcStart = new DateTime((string)($s['date_start'] ?? '1970-01-01T00:00:00Z'), new DateTimeZone('UTC'));
        $offset   = parse_gmt_offset_to_minutes($s['gmt_offset'] ?? null);

        $formatted[] = [
            'session_key'  => $s['session_key']  ?? null,
            'session_name' => $s['session_name'] ?? null,
            'session_type' => $s['session_type'] ?? null,
            'date_start'   => $s['date_start']   ?? null,
            'date_end'     => $s['date_end']     ?? null,
            'gmt_offset'   => $s['gmt_offset']   ?? null,
            'local_start'  => utc_to_local_with_offset($utcStart, $offset)->format(DateTime::ATOM),
            'paris_start'  => (clone $utcStart)->setTimezone(new DateTimeZone('Europe/Paris'))->format(DateTime::ATOM),
        ];
    }

    // Plage week-end : début de la 1re session → fin de la dernière (heure locale circuit)
    $weekendStart = $weekendEnd = null;
    if (!empty($sessions)) {
        $first = $sessions[0];
        $last  = $sessions[count($sessions) - 1];

        $weekendStart = utc_to_local_with_offset(
            new DateTime((string)$first['date_start'], new DateTimeZone('UTC')),
            parse_gmt_offset_to_minutes($first['gmt_offset'] ?? null)
        )->format(DateTime::ATOM);
        $weekendEnd = utc_to_local_with_offset(
            new DateTime((string)$last['date_end'], new DateTimeZone('UTC')),
            parse_gmt_offset_to_minutes($last['gmt_offset'] ?? null)
        )->format(DateTime::ATOM);
    }

    $circuit = (string)($meeting['circuit_short_name'] ?? ($meeting['location'] ?? ''));

    return [
        'meeting' => [
            'meeting_key'   => $meeting['meeting_key']  ?? null,
            'meeting_name'  => $meeting['meeting_name'] ?? null,
            'country_name'  => $meeting['country_name'] ?? null,
            'location'      => $meeting['location']     ?? null,
            'circuit'       => $circuit,
            'circuit_slug'  => slugify($circuit),
            'weekend_start' => $weekendStart,
            'weekend_end'   => $weekendEnd,
        ],
        'sessions' => $formatted,
    ];
}

/**
 * Dernière mesure météo d'une session (valeurs arrondies), ou null.
 */
function f1_latest_weather(int $sessionKey): ?array
{
    $list = openf1_get('weather?session_key=' . $sessionKey);
    if (empty($list)) {
        return null;
    }

    usort($list, static fn($a, $b) => strtotime((string)$a['date']) <=> strtotime((string)$b['date']));
    $w = $list[count($list) - 1];

    $round = static fn(string $k): ?int => isset($w[$k]) ? (int)round((float)$w[$k]) : null;

    return [
        'track_temp' => $round('track_temp'),
        'air_temp'   => $round('air_temp'),
        'humidity'   => $round('humidity'),
        'wind_speed' => $round('wind_speed'),
    ];
}

/**
 * Messages race control des 2 dernières heures (limite le volume de données).
 */
function f1_recent_race_control(int $sessionKey): array
{
    $sinceUtc = new DateTime('now', new DateTimeZone('UTC'));
    $sinceUtc->modify('-2 hours');

    return openf1_get(
        'race_control?session_key=' . $sessionKey .
        '&date>=' . urlencode($sinceUtc->format('Y-m-d\TH:i:s'))
    );
}

/**
 * Statut temps réel d'une session : météo, état de piste, tours.
 * $session (optionnel) évite de recharger la session si on l'a déjà.
 */
function f1_get_session_status(int $sessionKey, ?array $session = null): array
{
    $weather     = f1_latest_weather($sessionKey);
    $raceControl = f1_recent_race_control($sessionKey);

    [$trackStatusLabel, $trackStatusClass] = track_status_from_race_control($raceControl);

    $session ??= openf1_get('sessions?session_key=' . $sessionKey)[0] ?? [];

    return [
        'track_temp'         => $weather['track_temp'] ?? null,
        'air_temp'           => $weather['air_temp']   ?? null,
        'humidity'           => $weather['humidity']   ?? null,
        'wind_speed'         => $weather['wind_speed'] ?? null,
        'track_status'       => $trackStatusLabel,
        'track_status_class' => $trackStatusClass,
        'current_lap'        => current_lap_from_race_control($raceControl),
        'total_laps'         => (int)($session['total_laps'] ?? $session['lap_count'] ?? 0) ?: null,
    ];
}

/**
 * Session "focus" du live timing pour une saison (live > à venir > dernière),
 * avec son meeting et son statut. Renvoie null si aucune session.
 * Si le meeting ou le statut ne peuvent pas être chargés, ils valent null
 * (la session reste affichable).
 */
function f1_get_livetiming_focus(int $year): ?array
{
    $sessions = openf1_get("sessions?year={$year}");
    sort_sessions_by_start($sessions);

    $nowParis = new DateTime('now', new DateTimeZone('Europe/Paris'));
    $focus    = pick_focus_session($sessions, $nowParis);
    if (!$focus) {
        return null;
    }

    $startParis = utc_string_to_paris($focus['date_start'] ?? null);
    $endParis   = utc_string_to_paris($focus['date_end']   ?? null);

    // Phase : upcoming / live / finished
    $phase = 'finished';
    if ($startParis && $endParis) {
        if ($nowParis < $startParis)    $phase = 'upcoming';
        elseif ($nowParis <= $endParis) $phase = 'live';
    }

    $meeting = null;
    $status  = null;
    try {
        $m = openf1_get('meetings?meeting_key=' . (int)$focus['meeting_key'])[0] ?? null;
        if ($m) {
            $meeting = [
                'meeting_key'  => $m['meeting_key']  ?? null,
                'meeting_name' => $m['meeting_name'] ?? null,
                'country_name' => $m['country_name'] ?? null,
            ];
        }
        $status = f1_get_session_status((int)$focus['session_key'], $focus);
    } catch (Throwable $e) {
        error_log('[F1Tracker] f1_get_livetiming_focus (meeting/statut) : ' . $e->getMessage());
    }

    return [
        'session' => [
            'session_key'  => $focus['session_key']  ?? null,
            'session_name' => $focus['session_name'] ?? null,
            'session_type' => $focus['session_type'] ?? null,
            'date_start'   => $startParis?->format(DateTime::ATOM),
            'date_end'     => $endParis?->format(DateTime::ATOM),
            'phase'        => $phase,
        ],
        'meeting' => $meeting,
        'status'  => $status,
    ];
}
