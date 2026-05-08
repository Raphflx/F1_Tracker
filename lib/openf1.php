<?php
declare(strict_types=1);

/**
 * Petit client OpenF1 (API non officielle).
 * Centralise la récupération JSON avec timeout + gestion d’erreurs.
 */

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

    // Statut HTTP (si dispo)
    $statusLine = $http_response_header[0] ?? '';
    $isHttpOk = str_contains($statusLine, '200');

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
