<?php
declare(strict_types=1);

/**
 * Envoie une réponse JSON standardisée.
 * Format : { "data": ..., "error": null|"message" }
 */
function api_response(mixed $data = null, ?string $error = null, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
}
