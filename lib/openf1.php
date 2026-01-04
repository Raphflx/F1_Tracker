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
