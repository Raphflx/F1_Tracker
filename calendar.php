<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Calendrier 2025
|--------------------------------------------------------------------------
| - Récupère la liste des meetings (GP)
| - Calcule la plage de dates du week-end à partir des sessions
| - Affiche une grille de cartes cliquables vers grandprix.php
*/

require __DIR__ . '/lib/openf1.php';
require __DIR__ . '/lib/text.php';
require __DIR__ . '/lib/datetime.php';

$pageTitle = 'Calendrier – F1 Tracker';
require __DIR__ . '/includes/header.php';

$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?? 2026;
if ($year < 1950 || $year > 2100) {
    $year = 2025;
}


$allowedYears = [2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030];
if (!in_array($year, $allowedYears, true)) {
    $year = 2026;
}

// Chargement OpenF1 (meetings + sessions)
try {
    $meetings = openf1_get("meetings?year={$year}");
    $sessions = openf1_get("sessions?year={$year}");
} catch (Throwable $e) {
    $meetings = [];
    $sessions = [];
    $errorMessage = $e->getMessage();
}

// Index sessions -> plage week-end par meeting_key
$weekendRanges = [];

foreach ($sessions as $session) {
    if (!isset($session['meeting_key'], $session['date_start'], $session['date_end'])) {
        continue;
    }

    $meetingKey = (string)$session['meeting_key'];

    $utcStart = new DateTime((string)$session['date_start'], new DateTimeZone('UTC'));
    $utcEnd   = new DateTime((string)$session['date_end'],   new DateTimeZone('UTC'));

    $offsetMinutes = parse_gmt_offset_to_minutes($session['gmt_offset'] ?? null);

    $localStart = utc_to_local_with_offset($utcStart, $offsetMinutes);
    $localEnd   = utc_to_local_with_offset($utcEnd,   $offsetMinutes);

    if (!isset($weekendRanges[$meetingKey])) {
        $weekendRanges[$meetingKey] = ['start' => $localStart, 'end' => $localEnd];
        continue;
    }

    if ($localStart < $weekendRanges[$meetingKey]['start']) {
        $weekendRanges[$meetingKey]['start'] = $localStart;
    }
    if ($localEnd > $weekendRanges[$meetingKey]['end']) {
        $weekendRanges[$meetingKey]['end'] = $localEnd;
    }
}
?>

<button class="back-btn" onclick="window.location.href='index.php'">← Retour à l’accueil</button>

<h2>Calendrier des Grands Prix <?= (int)$year ?></h2>
<p class="section-subtitle">
  Clique sur un circuit pour voir le détail des essais, qualifications, sprint et course.
</p>

<?php if (!empty($errorMessage ?? null)): ?>
  <p class="error">
    Impossible de charger les données OpenF1. Détail : <?= htmlspecialchars($errorMessage, ENT_QUOTES) ?>
  </p>
<?php endif; ?>

<section class="calendar-grid">
  <?php if (empty($meetings)): ?>
    <p>Aucun meeting trouvé pour <?= (int)$year ?>.</p>
  <?php else: ?>
    <?php foreach ($meetings as $meeting): ?>
      <?php
        $mk = (string)($meeting['meeting_key'] ?? '');
        if ($mk === '') {
            continue;
        }

        $circuit = (string)($meeting['circuit_short_name'] ?? ($meeting['location'] ?? 'Circuit inconnu'));
        $country = (string)($meeting['country_name'] ?? 'Pays inconnu');

        // Date du week-end à partir des sessions (meilleur) + fallback
        if (isset($weekendRanges[$mk])) {
            $start = $weekendRanges[$mk]['start'];
            $end   = $weekendRanges[$mk]['end'];
        } else {
            $utcStart = new DateTime((string)($meeting['date_start'] ?? '1970-01-01'), new DateTimeZone('UTC'));
            $offsetMinutes = parse_gmt_offset_to_minutes($meeting['gmt_offset'] ?? null);

            $start = utc_to_local_with_offset($utcStart, $offsetMinutes);
            $end = clone $start;
            $end->modify('+2 days');
        }

        $weekendText = format_weekend_range($start, $end);

        // Lien propre (slug) + meeting_key
        $slug = slugify($country . '-' . $circuit);
        $href = "grandprix.php?meeting_key=" . urlencode($mk) . "&slug=" . urlencode($slug);
      ?>

      <a class="race-card" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>">
        <div class="race-card-content">
          <h3><?= htmlspecialchars($country, ENT_QUOTES) ?></h3>
          <p class="race-circuit"><?= htmlspecialchars($circuit, ENT_QUOTES) ?></p>
          <p class="race-date"><?= htmlspecialchars($weekendText, ENT_QUOTES) ?></p>
        </div>
      </a>

    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php
require __DIR__ . '/includes/footer.php';
