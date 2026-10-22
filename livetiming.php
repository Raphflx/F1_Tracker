<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Live timing
|--------------------------------------------------------------------------
| - Détermine une "session focus" (live > upcoming > dernière)
| - Affiche un bandeau type F1 (GP + session + compte à rebours en direct)
| - Affiche météo (track/air/humidity/wind)
| - Affiche tours (xx/yy si dispo) + état piste (clair / safety car / rouge...)
*/

require_once __DIR__ . '/lib/openf1.php';

date_default_timezone_set('Europe/Paris');

$pageTitle = 'Live timing – F1 Tracker';
require __DIR__ . '/includes/header.php';

// ---------------------------------------------------------
// 1) Session focus de la saison en cours (+ meeting + statut)
// ---------------------------------------------------------
$errorMessage = null;
$focus = null;

try {
    $focus = f1_get_livetiming_focus((int)date('Y'));
    if ($focus && $focus['status'] === null) {
        $errorMessage = 'Impossible de charger les données temps réel. Veuillez réessayer plus tard.';
    }
} catch (Throwable $e) {
    error_log('[F1Tracker] livetiming.php : ' . $e->getMessage());
    $errorMessage = 'Impossible de charger les sessions. Veuillez réessayer plus tard.';
}

$focusSession = $focus['session'] ?? null;
$meeting      = $focus['meeting'] ?? null;
$status       = $focus['status']  ?? [];

// ---------------------------------------------------------
// 2) Préparation affichage bandeau
// ---------------------------------------------------------
$bannerTitle = $meeting['meeting_name'] ?? null;

$sessionName = $focusSession['session_name'] ?? null;
$sessionType = $focusSession['session_type'] ?? null;
$phase       = $focusSession['phase'] ?? 'finished';

$startParis = !empty($focusSession['date_start']) ? new DateTime($focusSession['date_start']) : null;
$endParis   = !empty($focusSession['date_end'])   ? new DateTime($focusSession['date_end'])   : null;

// Météo
$fmt = static fn(?int $v, string $unit): string => $v !== null ? $v . $unit : 'N/A';
$trackTempText = $fmt($status['track_temp'] ?? null, '°C');
$airTempText   = $fmt($status['air_temp']   ?? null, '°C');
$humidityText  = $fmt($status['humidity']   ?? null, '%');
$windText      = $fmt($status['wind_speed'] ?? null, ' km/h');

// Tours + statut piste
$trackStatusLabel = $status['track_status']       ?? 'Clair';
$trackStatusClass = $status['track_status_class'] ?? 'track-status--green';
$currentLap = $status['current_lap'] ?? null;
$totalLaps  = $status['total_laps']  ?? null;
$lapsText = ($currentLap ? (string)$currentLap : '—') . '/' . ($totalLaps ? (string)$totalLaps : '—');

?>

<a class="back-btn" href="index.php">← Retour à l’accueil</a>

<?php if ($errorMessage): ?>
  <p class="error">⚠️ <?= htmlspecialchars($errorMessage, ENT_QUOTES) ?></p>
<?php endif; ?>

<!-- Bandeau Live Timing -->
<div class="livetiming-banner">

  <div class="livetiming-banner-left">
    <div class="livetiming-banner-text">
      <div class="livetiming-gp-line">
        <span class="livetiming-gp-name">
          <?= htmlspecialchars($bannerTitle ?: 'Aucune session détectée', ENT_QUOTES) ?>
        </span>
      </div>

      <div class="livetiming-session-line">
        <?php if ($sessionName): ?>
          <span class="livetiming-session-name">
            <?= htmlspecialchars($sessionName, ENT_QUOTES) ?>
          </span>
          <?php if ($sessionType): ?>
            <span class="livetiming-session-type">
              (<?= htmlspecialchars($sessionType, ENT_QUOTES) ?>)
            </span>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="livetiming-dates-line">
        <?php if ($startParis && $endParis): ?>
          <span><?= htmlspecialchars(format_date_fr($startParis), ENT_QUOTES) ?></span>
          <span class="sep">→</span>
          <span><?= htmlspecialchars(format_date_fr($endParis), ENT_QUOTES) ?></span>
        <?php else: ?>
          <span>Dates indisponibles</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="livetiming-banner-right">
    <div class="livetiming-metric">
      <span class="label">Tours</span>
      <span class="value" id="lt-laps"><?= htmlspecialchars($lapsText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Piste</span>
      <span class="value">
        <span id="lt-track-status" class="track-status-badge <?= htmlspecialchars($trackStatusClass, ENT_QUOTES) ?>">
          <?= htmlspecialchars($trackStatusLabel, ENT_QUOTES) ?>
        </span>
      </span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Track</span>
      <span class="value" id="lt-track-temp"><?= htmlspecialchars($trackTempText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Air</span>
      <span class="value" id="lt-air-temp"><?= htmlspecialchars($airTempText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Humidité</span>
      <span class="value" id="lt-humidity"><?= htmlspecialchars($humidityText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Vent</span>
      <span class="value" id="lt-wind"><?= htmlspecialchars($windText, ENT_QUOTES) ?></span>
    </div>
  </div>
</div>

<!-- Compte à rebours -->
<div class="livetiming-countdown-wrap">
  <div
    id="livetiming-countdown"
    class="livetiming-countdown"
    data-phase="<?= htmlspecialchars($phase, ENT_QUOTES) ?>"
    data-start="<?= $startParis ? (string)$startParis->getTimestamp() : '0' ?>"
    data-end="<?= $endParis ? (string)$endParis->getTimestamp() : '0' ?>"
    data-session-key="<?= $focusSession ? (int)$focusSession['session_key'] : '0' ?>"
  >
    Chargement…
  </div>
</div>

<script src="script/countdown.js"></script>
<?php if ($phase !== 'finished' && $focusSession): ?>
<script src="script/livetiming.js"></script>
<?php endif; ?>

<?php
require __DIR__ . '/includes/footer.php';
