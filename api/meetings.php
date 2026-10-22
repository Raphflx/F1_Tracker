<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

$year = api_get_year();
if ($year === null) {
    api_response(null, 'Paramètre year invalide.', 400);
    exit;
}

try {
    api_response(f1_get_meetings($year));
} catch (Throwable $e) {
    error_log('[F1Tracker] api/meetings.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger les meetings.', 500);
}
