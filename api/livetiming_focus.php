<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

// Mode démo (?demo=1|live|upcoming|finished) : données simulées
f1_set_demo_phase(demo_phase_from_query());

// Année : paramètre optionnel, année courante par défaut
$year = api_get_year((int)date('Y'));
if ($year === null) {
    api_response(null, 'Paramètre year invalide.', 400);
    exit;
}

try {
    $focus = f1_get_livetiming_focus($year);
    if (!$focus) {
        api_response(null, 'Aucune session trouvée pour cette année.', 404);
        exit;
    }
    api_response($focus);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/livetiming_focus.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger la session live.', 500);
}
