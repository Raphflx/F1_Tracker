<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API interne – Statut live timing
|--------------------------------------------------------------------------
| Paramètre GET : session_key (int)
| Retourne : JSON { weather, trackStatus, trackStatusClass, lapsText }
| Utilisé par script/livetiming.js pour le polling toutes les 30s
*/

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../lib/openf1.php';

$sessionKey = filter_input(INPUT_GET, 'session_key', FILTER_VALIDATE_INT);

if (!$sessionKey) {
    http_response_code(400);
    echo json_encode(['error' => 'session_key manquant ou invalide']);
    exit;
}

try {
    // Météo (dernière mesure)
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

    // Statut piste
    [$trackStatusLabel, $trackStatusClass] = track_status_from_race_control($raceControl);

    // Tour actuel
    $currentLap = current_lap_from_race_control($raceControl);

    // Total tours (depuis sessions si dispo)
    $sessionList = openf1_get('sessions?session_key=' . $sessionKey);
    $session = $sessionList[0] ?? [];
    $totalLaps = (int)($session['total_laps'] ?? $session['lap_count'] ?? 0) ?: null;

    echo json_encode([
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
    http_response_code(500);
    echo json_encode(['error' => 'Erreur interne du serveur.']);
}

