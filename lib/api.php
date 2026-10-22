<?php
declare(strict_types=1);

/**
 * Envoie une réponse JSON standardisée (en-têtes compris).
 * Format : { "data": ..., "error": null|"message" }
 */
function api_response(mixed $data = null, ?string $error = null, int $status = 200): void
{
    $json = json_encode(['data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        error_log('[F1Tracker] api_response : ' . json_last_error_msg());
        $status = 500;
        $json   = '{"data":null,"error":"Erreur interne du serveur."}';
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo $json;
}

/**
 * Lit un paramètre GET entier strictement positif (clé OpenF1), ou null si absent/invalide.
 */
function api_get_positive_int(string $name): ?int
{
    $value = filter_input(INPUT_GET, $name, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return is_int($value) ? $value : null;
}

/**
 * Lit le paramètre GET "year" (1950–2100). Absent → $default ; invalide → null.
 */
function api_get_year(?int $default = null): ?int
{
    if (!isset($_GET['year'])) {
        return $default;
    }
    $value = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1950, 'max_range' => 2100]]);
    return is_int($value) ? $value : null;
}
