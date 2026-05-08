<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require __DIR__ . '/../lib/openf1.php';
require __DIR__ . '/../lib/datetime.php';
require __DIR__ . '/../lib/text.php';
require __DIR__ . '/../lib/api.php';

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
if (!$year || $year < 1950 || $year > 2100) {
    api_response(null, 'Paramètre year invalide.', 400);
    exit;
}

try {
    $meetings = openf1_get("meetings?year={$year}");
    $sessions = openf1_get("sessions?year={$year}");

    // Calcul de la plage week-end par meeting à partir des sessions
    $weekendRanges = [];
    foreach ($sessions as $s) {
        if (!isset($s['meeting_key'], $s['date_start'], $s['date_end'])) continue;

        $mk     = (string)$s['meeting_key'];
        $offset = parse_gmt_offset_to_minutes($s['gmt_offset'] ?? null);

        $utcStart = new DateTime((string)$s['date_start'], new DateTimeZone('UTC'));
        $utcEnd   = new DateTime((string)$s['date_end'],   new DateTimeZone('UTC'));

        $localStart = utc_to_local_with_offset($utcStart, $offset);
        $localEnd   = utc_to_local_with_offset($utcEnd,   $offset);

        if (!isset($weekendRanges[$mk])) {
            $weekendRanges[$mk] = ['start' => $localStart, 'end' => $localEnd];
        } else {
            if ($localStart < $weekendRanges[$mk]['start']) $weekendRanges[$mk]['start'] = $localStart;
            if ($localEnd   > $weekendRanges[$mk]['end'])   $weekendRanges[$mk]['end']   = $localEnd;
        }
    }

    $result = [];
    foreach ($meetings as $m) {
        $mk = (string)($m['meeting_key'] ?? '');
        if ($mk === '') continue;

        $circuit = (string)($m['circuit_short_name'] ?? ($m['location'] ?? ''));
        $country = (string)($m['country_name'] ?? '');

        // Plage week-end : sessions en priorité, fallback meeting.date_start
        if (isset($weekendRanges[$mk])) {
            $weekendStart = $weekendRanges[$mk]['start']->format(DateTime::ATOM);
            $weekendEnd   = $weekendRanges[$mk]['end']->format(DateTime::ATOM);
        } elseif (!empty($m['date_start'])) {
            $utcFallback = new DateTime((string)$m['date_start'], new DateTimeZone('UTC'));
            $offset      = parse_gmt_offset_to_minutes($m['gmt_offset'] ?? null);
            $localFallback = utc_to_local_with_offset($utcFallback, $offset);
            $weekendStart  = $localFallback->format(DateTime::ATOM);
            $weekendEnd    = (clone $localFallback)->modify('+2 days')->format(DateTime::ATOM);
        } else {
            $weekendStart = null;
            $weekendEnd   = null;
        }

        $result[] = [
            'meeting_key'   => (int)$mk,
            'meeting_name'  => $m['meeting_name']  ?? null,
            'country_name'  => $country,
            'location'      => $m['location']      ?? null,
            'circuit'       => $circuit,
            'circuit_slug'  => slugify($circuit),
            'weekend_start' => $weekendStart,
            'weekend_end'   => $weekendEnd,
        ];
    }

    api_response($result);
} catch (Throwable $e) {
    error_log('[F1Tracker] api/meetings.php : ' . $e->getMessage());
    api_response(null, 'Impossible de charger les meetings.', 500);
}
