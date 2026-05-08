<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../lib/openf1.php';
require __DIR__ . '/../lib/api.php';

$sessionKey = filter_input(INPUT_GET, 'session_key', FILTER_VALIDATE_INT);
if (!$sessionKey) {
    api_response(null, 'session_key manquant ou invalide.', 400);
    exit;
}

try {
    // Météo (mesure la plus récente)
    $weatherList = openf1_get('weather?session_key=' . $sessionKey);
    $weather = null;
    if (!empty($weatherList)) {
        usort($weatherList, static fn($a, $b) => strtotime((string)$a['date']) <=> strtotime((string)$b['date']));
        $weather = $weatherList[count($weatherList) - 1];
    }

    // Race control (dernières 2h)
    $sinceUtc = new DateTime('now', new DateTimeZone('UTC'));
    $sinceUtc->modify('-2 hours');
    $raceControl = openf1_get(
        'race_control?session_key=' . $sessionKey .
        '&date>=' . urlencode($sinceUtc->format('Y-m-d\TH:i:s'))
    );

    [$trackStatusLabel, $trackStatusClass] = track_status_from_race_control($raceControl);
    $currentLap = current_lap_from_race_control($raceControl);

    $sessionList = openf1_get('sessions?session_key=' . $sessionKey);
    $session   = $sessionList[0] ?? [];
    $totalLaps = (int)($session['total_laps'] ?? $session['lap_count'] ?? 0) ?: null;

    api_response([
        'trackTemp'        => isset($weather['track_temp']) ? (int)round((float)$weather['track_temp']) : null,
        'airTemp'          => isset($weather['air_temp'])   ? (int)round((float)$weather['air_temp'])   : null,
        'humidity'         => isset($weather['humidity'])   ? (int)round((float)$weather['humidity'])   : null,
        'windSpeed'        => isset($weather['wind_speed']) ? (int)round((float)$weather['wind_speed']) : null,
        'trackStatus'      => $trackStatusLabel,
        'trackStatusClass' => $trackStatusClass,
        'currentLap'       => $currentLap,
        'totalLaps'        => $totalLaps,
    ]);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/livetiming_status.php : ' . $e->getMessage());
    api_response(null, 'Erreur interne du serveur.', 500);
}
