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

require_once __DIR__ . '/lib/openf1.php';

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
    // Meeting + sessions triées (heures locale et France calculées par le service)
    $detail = f1_get_meeting($meetingKey);
    if ($detail) {
        $meeting  = $detail['meeting'];
        $sessions = $detail['sessions'];
    }
} catch (Throwable $e) {
    error_log('[F1Tracker] grandprix.php : ' . $e->getMessage());
    $errorMessage = 'Impossible de charger les données du Grand Prix. Veuillez réessayer plus tard.';
}

// ----------------------
// 3) Préparation affichage
// ----------------------
$pageTitle = ($meeting['meeting_name'] ?? 'Détail GP') . ' – F1 Tracker';
require __DIR__ . '/includes/header.php';

if (!$meeting && !$errorMessage) {
    $errorMessage = 'Impossible de trouver les informations pour ce meeting.';
}

// Image circuit (si dispo)
$circuit = (string)($meeting['circuit'] ?? '') ?: 'Circuit inconnu';
$country = (string)($meeting['country_name'] ?? '');
$location = (string)($meeting['location'] ?? '');

$slug = (string)($meeting['circuit_slug'] ?? slugify($circuit));
$relativeImagePath = "img/circuits/{$slug}.png";
$circuitImage = file_exists($relativeImagePath) ? $relativeImagePath : null;

// Plage week-end (heure locale circuit)
$weekendText = '';
if (!empty($meeting['weekend_start']) && !empty($meeting['weekend_end'])) {
    $weekendText = format_weekend_range(new DateTime($meeting['weekend_start']), new DateTime($meeting['weekend_end']));
}
?>

<a class="back-btn" href="calendar_year.php">← Retour au calendrier</a>

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

      <div class="table-scroll">
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
                // Heure locale circuit
                $localStart = new DateTime($session['local_start']);
                $localDayStr = day_en_to_fr($localStart->format('l')) . ' ' . $localStart->format('d/m');
                $localTimeStr = $localStart->format('H:i');

                // Heure France (Europe/Paris)
                $frStart = new DateTime($session['paris_start']);

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
  </div>

<?php endif; ?>

<?php
require __DIR__ . '/includes/footer.php';
