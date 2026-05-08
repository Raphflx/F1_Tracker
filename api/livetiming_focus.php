<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../lib/openf1.php';
require __DIR__ . '/../lib/datetime.php';
require __DIR__ . '/../lib/text.php';
require __DIR__ . '/../lib/api.php';

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?? (int)date('Y');

try {
    $sessions = openf1_get("sessions?year={$year}");
    usort($sessions, static fn($a, $b) =>
        strtotime((string)($a['date_start'] ?? '')) <=> strtotime((string)($b['date_start'] ?? ''))
    );

    $nowParis = new DateTime('now', new DateTimeZone('Europe/Paris'));
    $focus    = pick_focus_session($sessions, $nowParis);

    if (!$focus) {
        api_response(null, 'Aucune session trouvée pour cette année.');
        exit;
    }

    // Meeting
    $meetingList = openf1_get('meetings?meeting_key=' . urlencode((string)$focus['meeting_key']));
    $meeting     = $meetingList[0] ?? null;

    // Météo (mesure la plus récente)
    $weatherList = openf1_get('weather?session_key=' . urlencode((string)$focus['session_key']));
    $weather = null;
    if (!empty($weatherList)) {
        usort($weatherList, static fn($a, $b) =>
            strtotime((string)$a['date']) <=> strtotime((string)$b['date'])
        );
        $weather = $weatherList[count($weatherList) - 1];
    }

    // Race control (dernières 2h)
    $sinceUtc = new DateTime('now', new DateTimeZone('UTC'));
    $sinceUtc->modify('-2 hours');
    $raceControl = openf1_get(
        'race_control?session_key=' . urlencode((string)$focus['session_key']) .
        '&date>=' . urlencode($sinceUtc->format('Y-m-d\TH:i:s'))
    );

    [$trackStatusLabel, $trackStatusClass] = track_status_from_race_control($raceControl);
    $currentLap = current_lap_from_race_control($raceControl);
    $totalLaps  = (int)($focus['total_laps'] ?? $focus['lap_count'] ?? 0) ?: null;

    // Phase (upcoming / live / finished)
    $startUtc = $focus['date_start'] ?? null;
    $endUtc   = $focus['date_end']   ?? null;
    $startParis = $startUtc ? (new DateTime((string)$startUtc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Paris')) : null;
    $endParis   = $endUtc   ? (new DateTime((string)$endUtc,   new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Paris')) : null;

    $phase = 'finished';
    if ($startParis && $endParis) {
        if ($nowParis < $startParis)    $phase = 'upcoming';
        elseif ($nowParis <= $endParis) $phase = 'live';
    }

    api_response([
        'session' => [
            'session_key'  => $focus['session_key']  ?? null,
            'session_name' => $focus['session_name'] ?? null,
            'session_type' => $focus['session_type'] ?? null,
            'date_start'   => $startParis ? $startParis->format(DateTime::ATOM) : null,
            'date_end'     => $endParis   ? $endParis->format(DateTime::ATOM)   : null,
            'phase'        => $phase,
            'current_lap'  => $currentLap,
            'total_laps'   => $totalLaps,
        ],
        'meeting' => $meeting ? [
            'meeting_key'  => $meeting['meeting_key']  ?? null,
            'meeting_name' => $meeting['meeting_name'] ?? null,
            'country_name' => $meeting['country_name'] ?? null,
        ] : null,
        'weather' => $weather ? [
            'track_temp' => isset($weather['track_temp']) ? (int)round((float)$weather['track_temp']) : null,
            'air_temp'   => isset($weather['air_temp'])   ? (int)round((float)$weather['air_temp'])   : null,
            'humidity'   => isset($weather['humidity'])   ? (int)round((float)$weather['humidity'])   : null,
            'wind_speed' => isset($weather['wind_speed']) ? (int)round((float)$weather['wind_speed']) : null,
        ] : null,
        'track_status'       => $trackStatusLabel,
        'track_status_class' => $trackStatusClass,
    ]);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/livetiming_focus.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger la session live.', 500);
}
