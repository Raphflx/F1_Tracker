<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

// Année : paramètre optionnel, année courante par défaut
$year = isset($_GET['year'])
    ? filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT)
    : (int)date('Y');
if (!$year || $year < 1950 || $year > 2100) {
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
