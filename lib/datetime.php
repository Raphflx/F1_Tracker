<?php
declare(strict_types=1);

/**
 * Helpers date/heure
 */

function parse_gmt_offset_to_minutes(?string $offset): int
{
    // Formats possibles : "+02:00", "-05:00", "+02:00:00", "-05:00:00"
    if ($offset === null || $offset === '') {
        return 0;
    }

    if (!preg_match('/^([+-])(\d{2}):(\d{2})(?::(\d{2}))?$/', $offset, $m)) {
        return 0;
    }

    $sign = ($m[1] === '-') ? -1 : 1;
    $h = (int)$m[2];
    $min = (int)$m[3];
    $sec = isset($m[4]) ? (int)$m[4] : 0;

    return $sign * ($h * 60 + $min + intdiv($sec, 60));
}

function utc_to_local_with_offset(DateTime $utc, int $offsetMinutes): DateTime
{
    // On clone pour éviter de modifier l'objet initial
    $local = clone $utc;

    $prefix = $offsetMinutes >= 0 ? '+' : '';
    $local->modify($prefix . $offsetMinutes . ' minutes');

    return $local;
}

function format_weekend_range(DateTime $start, DateTime $end): string
{
    $mois = [
        1 => 'janv.', 2 => 'févr.', 3 => 'mars', 4 => 'avr.',
        5 => 'mai', 6 => 'juin', 7 => 'juil.', 8 => 'août',
        9 => 'sept.', 10 => 'oct.', 11 => 'nov.', 12 => 'déc.'
    ];

    $startDay   = (int)$start->format('j');
    $startMonth = (int)$start->format('n');
    $startYear  = $start->format('Y');

    $endDay   = (int)$end->format('j');
    $endMonth = (int)$end->format('n');
    $endYear  = $end->format('Y');

    // Même mois et même année → "12 avr. - 14 avr. 2025"
    if ($startMonth === $endMonth && $startYear === $endYear) {
        return sprintf(
            '%d %s - %d %s %s',
            $startDay,
            $mois[$startMonth] ?? '',
            $endDay,
            $mois[$endMonth] ?? '',
            $endYear
        );
    }

    // Sinon → "28 mars 2025 - 1 avr. 2025"
    return sprintf(
        '%d %s %s - %d %s %s',
        $startDay,
        $mois[$startMonth] ?? '',
        $startYear,
        $endDay,
        $mois[$endMonth] ?? '',
        $endYear
    );
}
