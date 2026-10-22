<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

$sessionKey = filter_input(INPUT_GET, 'session_key', FILTER_VALIDATE_INT);
if (!$sessionKey) {
    api_response(null, 'session_key manquant ou invalide.', 400);
    exit;
}

try {
    api_response(f1_get_session_status($sessionKey));
} catch (Throwable $e) {
    error_log('[F1Tracker] api/livetiming_status.php : ' . $e->getMessage());
    api_response(null, 'Erreur interne du serveur.', 500);
}
