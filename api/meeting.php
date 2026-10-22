<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

$meetingKey = filter_input(INPUT_GET, 'meeting_key', FILTER_VALIDATE_INT);
if (!$meetingKey) {
    api_response(null, 'Paramètre meeting_key invalide.', 400);
    exit;
}

try {
    $detail = f1_get_meeting($meetingKey);
    if (!$detail) {
        api_response(null, 'Meeting introuvable.', 404);
        exit;
    }
    api_response($detail);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/meeting.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger le meeting.', 500);
}
