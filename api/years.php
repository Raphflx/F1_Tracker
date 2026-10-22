<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

try {
    api_response(f1_get_years() ?: [2025]);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/years.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger les saisons.', 500);
}
