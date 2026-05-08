<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../lib/openf1.php';
require __DIR__ . '/../lib/api.php';

try {
    $meetings = openf1_get('meetings');

    $set = [];
    foreach ($meetings as $m) {
        if (isset($m['year']) && is_numeric($m['year'])) {
            $y = (int)$m['year'];
            if ($y > 1950 && $y < 2100) {
                $set[$y] = true;
                continue;
            }
        }
        if (!empty($m['date_start'])) {
            try {
                $dt = new DateTime((string)$m['date_start'], new DateTimeZone('UTC'));
                $y  = (int)$dt->format('Y');
                if ($y > 1950 && $y < 2100) $set[$y] = true;
            } catch (Throwable) {}
        }
    }

    $years = array_keys($set);
    rsort($years);

    api_response($years ?: [2025]);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/years.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger les saisons.', 500);
}
