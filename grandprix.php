<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Détail d’un Grand Prix (grandprix.php)
|--------------------------------------------------------------------------
| - Lit meeting_key depuis l’URL
| - Charge les infos du meeting + les sessions via OpenF1
| - Affiche : infos circuit + image + tableau des sessions (heure circuit & heure France)
*/

require __DIR__ . '/lib/openf1.php';
require __DIR__ . '/lib/text.php';
require __DIR__ . '/lib/datetime.php';

/**
 * Convertit un jour anglais ("Monday") en français ("lundi").
 */
function day_en_to_fr(string $dayEn): string
{
    static $map = [
        'Monday' => 'lundi',
        'Tuesday' => 'mardi',
        'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi',
        'Friday' => 'vendredi',
        'Saturday' => 'samedi',
        'Sunday' => 'dimanche',
    ];

    return $map[$dayEn] ?? $dayEn;
}

// ----------------------
// 1) Paramètre d’entrée
// ----------------------
$meetingKey = filter_input(INPUT_GET, 'meeting_key', FILTER_VALIDATE_INT);

if (!$meetingKey) {
    http_response_code(400);
    $pageTitle = 'Erreur – F1 Tracker';
    require __DIR__ . '/includes/header.php';
    echo '<p class="error">meeting_key manquant ou invalide.</p>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// ----------------------
// 2) Chargement OpenF1
// ----------------------
$errorMessage = null;
$meeting = null;
$sessions = [];

try {
    $meetingList = openf1_get('meetings?meeting_key=' . urlencode((string)$meetingKey));
    $meeting = $meetingList[0] ?? null;

    $sessions = openf1_get('sessions?meeting_key=' . urlencode((string)$meetingKey));
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

// Tri des sessions par date de début (UTC)
usort($sessions, static function (array $a, array $b): int {
    $da = $a['date_start'] ?? '1970-01-01T00:00:00Z';
    $db = $b['date_start'] ?? '1970-01-01T00:00:00Z';
    return strtotime($da) <=> strtotime($db);
});

// ----------------------
// 3) Préparation affichage
// ----------------------
$pageTitle = ($meeting['meeting_name'] ?? 'Détail GP') . ' – F1 Tracker';
require __DIR__ . '/includes/header.php';

if (!$meeting && !$errorMessage) {
    $errorMessage = 'Impossible de trouver les informations pour ce meeting.';
}

// Image circuit (si dispo)
$circuit = (string)($meeting['circuit_short_name'] ?? ($meeting['location'] ?? 'Circuit inconnu'));
$country = (string)($meeting['country_name'] ?? '');
$location = (string)($meeting['location'] ?? '');

$slug = slugify($circuit);
$relativeImagePath = "img/circuits/{$slug}.png";
$circuitImage = file_exists($relativeImagePath) ? $relativeImagePath : null;

// Plage week-end (basée sur sessions) en heure locale circuit
$weekendText = '';
if (!empty($sessions)) {
    $first = $sessions[0];
    $last  = $sessions[count($sessions) - 1];

    $utcStart = new DateTime((string)$first['date_start'], new DateTimeZone('UTC'));
    $utcEnd   = new DateTime((string)$last['date_end'],   new DateTimeZone('UTC'));

    $offsetStart = parse_gmt_offset_to_minutes($first['gmt_offset'] ?? null);
    $offsetEnd   = parse_gmt_offset_to_minutes($last['gmt_offset'] ?? null);

    $localStart = utc_to_local_with_offset($utcStart, $offsetStart);
    $localEnd   = utc_to_local_with_offset($utcEnd,   $offsetEnd);

    $weekendText = format_weekend_range($localStart, $localEnd);
}
?>

<button class="back-btn" onclick="window.location.href='calendar_year.php'">← Retour au calendrier</button>

<?php if ($errorMessage): ?>
  <p class="error"><?= htmlspecialchars($errorMessage, ENT_QUOTES) ?></p>
<?php endif; ?>

<?php if (!$meeting): ?>
  <h2>Grand Prix inconnu</h2>
  <p>Impossible de charger les informations de ce GP.</p>

<?php else: ?>

  <h2><?= htmlspecialchars((string)($meeting['meeting_name'] ?? 'Grand Prix'), ENT_QUOTES) ?></h2>

  <div class="gp-detail-layout">

    <!-- Colonne gauche : infos GP -->
    <div class="gp-detail-left">
      <div class="gp-card">

        <?php if ($circuitImage): ?>
          <img
            src="<?= htmlspecialchars($circuitImage, ENT_QUOTES) ?>"
            alt="Circuit <?= htmlspecialchars($circuit, ENT_QUOTES) ?>"
            class="circuit-img"
          >
        <?php endif; ?>

        <div class="gp-info">
          <p><strong>Pays :</strong> <?= htmlspecialchars($country ?: '—', ENT_QUOTES) ?></p>
          <p><strong>Lieu :</strong> <?= htmlspecialchars($location ?: '—', ENT_QUOTES) ?></p>
          <p><strong>Circuit :</strong> <?= htmlspecialchars($circuit ?: '—', ENT_QUOTES) ?></p>

          <?php if ($weekendText): ?>
            <p><strong>Week-end :</strong> <?= htmlspecialchars($weekendText, ENT_QUOTES) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Colonne droite : tableau sessions -->
    <div class="gp-detail-right">
      <h3>Programme du week-end</h3>

      <table id="sessions-table">
        <thead>
          <tr>
            <th>Session</th>
            <th>Type</th>
            <th>Jour (heure locale)</th>
            <th>Heure locale</th>
            <th>Jour (France)</th>
            <th>Heure (France)</th>
          </tr>
        </thead>

        <tbody>
          <?php if (empty($sessions)): ?>
            <tr>
              <td colspan="6">Aucune session trouvée pour ce meeting.</td>
            </tr>
          <?php else: ?>

            <?php foreach ($sessions as $session): ?>
              <?php
                $utcStart = new DateTime((string)($session['date_start'] ?? '1970-01-01T00:00:00Z'), new DateTimeZone('UTC'));

                // Heure locale circuit via gmt_offset
                $offsetMinutes = parse_gmt_offset_to_minutes($session['gmt_offset'] ?? null);
                $localStart = utc_to_local_with_offset($utcStart, $offsetMinutes);

                $localDayStr = day_en_to_fr($localStart->format('l')) . ' ' . $localStart->format('d/m');
                $localTimeStr = $localStart->format('H:i');

                // Heure France (Europe/Paris)
                $frStart = clone $utcStart;
                $frStart->setTimezone(new DateTimeZone('Europe/Paris'));

                $frDayStr = day_en_to_fr($frStart->format('l')) . ' ' . $frStart->format('d/m');
                $frTimeStr = $frStart->format('H:i');
              ?>

              <tr>
                <td><?= htmlspecialchars((string)($session['session_name'] ?? ''), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars((string)($session['session_type'] ?? ''), ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($localDayStr, ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($localTimeStr, ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($frDayStr, ENT_QUOTES) ?></td>
                <td><?= htmlspecialchars($frTimeStr, ENT_QUOTES) ?></td>
              </tr>

            <?php endforeach; ?>

          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php endif; ?>

<?php
require __DIR__ . '/includes/footer.php';
