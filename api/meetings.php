<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
if (!$year || $year < 1950 || $year > 2100) {
    api_response(null, 'Paramètre year invalide.', 400);
    exit;
}

try {
    api_response(f1_get_meetings($year));
} catch (Throwable $e) {
    error_log('[F1Tracker] api/meetings.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger les meetings.', 500);
}
