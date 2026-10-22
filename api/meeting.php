<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/openf1.php';
require_once __DIR__ . '/../lib/api.php';

$meetingKey = api_get_positive_int('meeting_key');
if ($meetingKey === null) {
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
