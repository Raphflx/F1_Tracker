<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../lib/openf1.php';
require __DIR__ . '/../lib/datetime.php';
require __DIR__ . '/../lib/text.php';
require __DIR__ . '/../lib/api.php';

$meetingKey = filter_input(INPUT_GET, 'meeting_key', FILTER_VALIDATE_INT);
if (!$meetingKey) {
    api_response(null, 'Paramètre meeting_key invalide.', 400);
    exit;
}

try {
    $meetingList = openf1_get('meetings?meeting_key=' . urlencode((string)$meetingKey));
    $meeting     = $meetingList[0] ?? null;

    if (!$meeting) {
        api_response(null, 'Meeting introuvable.', 404);
        exit;
    }

    $sessions = openf1_get('sessions?meeting_key=' . urlencode((string)$meetingKey));
    usort($sessions, static fn($a, $b) =>
        strtotime((string)($a['date_start'] ?? '')) <=> strtotime((string)($b['date_start'] ?? ''))
    );

    $formattedSessions = [];
    foreach ($sessions as $s) {
        $utcStart = new DateTime((string)($s['date_start'] ?? '1970-01-01T00:00:00Z'), new DateTimeZone('UTC'));
        $offset   = parse_gmt_offset_to_minutes($s['gmt_offset'] ?? null);

        $localStart = utc_to_local_with_offset($utcStart, $offset);
        $parisStart = (clone $utcStart)->setTimezone(new DateTimeZone('Europe/Paris'));

        $formattedSessions[] = [
            'session_key'  => $s['session_key']  ?? null,
            'session_name' => $s['session_name'] ?? null,
            'session_type' => $s['session_type'] ?? null,
            'date_start'   => $s['date_start']   ?? null,
            'date_end'     => $s['date_end']     ?? null,
            'gmt_offset'   => $s['gmt_offset']   ?? null,
            'local_start'  => $localStart->format(DateTime::ATOM),
            'paris_start'  => $parisStart->format(DateTime::ATOM),
        ];
    }

    $circuit = (string)($meeting['circuit_short_name'] ?? ($meeting['location'] ?? ''));

    api_response([
        'meeting' => [
            'meeting_key'  => $meeting['meeting_key']  ?? null,
            'meeting_name' => $meeting['meeting_name'] ?? null,
            'country_name' => $meeting['country_name'] ?? null,
            'location'     => $meeting['location']     ?? null,
            'circuit'      => $circuit,
            'circuit_slug' => slugify($circuit),
        ],
        'sessions' => $formattedSessions,
    ]);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/meeting.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger le meeting.', 500);
}
