<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

// Mode démo (?demo=1|live|upcoming|finished) : données simulées
f1_set_demo_phase(demo_phase_from_query());

$sessionKey = api_get_positive_int('session_key');
if ($sessionKey === null) {
    api_response(null, 'session_key manquant ou invalide.', 400);
    exit;
}

try {
    api_response(f1_get_session_status($sessionKey));
} catch (Throwable $e) {
    error_log('[F1Tracker] api/livetiming_status.php : ' . $e->getMessage());
    api_response(null, 'Erreur interne du serveur.', 500);
}
