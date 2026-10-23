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

// Mode démo (?demo=1|live|upcoming|finished) : données simulées, pour tester sans GP réel
$demoPhase     = demo_phase_from_query();
$demoOverrides = $demoPhase ? demo_overrides_from_query() : [];
f1_set_demo_phase($demoPhase, $demoOverrides);

$pageTitle = ($demoPhase ? 'Démo – ' : '') . 'Live timing – F1 Tracker';
$pageNoIndex = $demoPhase !== null; // la démo n'a pas à apparaître sur Google
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

<?php if ($demoPhase): ?>
  <!-- Bandeau + encart de réglage du mode démo (formulaire GET, aucun JS) -->
  <section class="demo-banner" aria-labelledby="demo-title">
    <p id="demo-title">
      <strong>Mode démo — données fictives.</strong>
      Session simulée pour tester le live timing sans Grand Prix en cours.
      Les champs laissés vides restent automatiques.
    </p>

    <form class="demo-form" method="get" action="livetiming.php">
      <label>
        Phase
        <select name="demo">
          <?php foreach (['upcoming' => 'À venir', 'live' => 'En cours', 'finished' => 'Terminée'] as $value => $label): ?>
            <option value="<?= $value ?>"<?= $demoPhase === $value ? ' selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        État de piste
        <select name="piste">
          <?php foreach (['' => 'Automatique', 'vert' => 'Clair', 'jaune' => 'Drapeau jaune', 'sc' => 'Safety Car', 'rouge' => 'Drapeau rouge'] as $value => $label): ?>
            <option value="<?= $value ?>"<?= ($demoOverrides['piste'] ?? '') === $value ? ' selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <?php foreach (['temp_piste' => 'Temp. piste (°C)', 'temp_air' => 'Temp. air (°C)', 'humidite' => 'Humidité (%)', 'vent' => 'Vent (km/h)', 'tour' => 'Tour'] as $name => $label): ?>
        <?php [$min, $max] = DEMO_NUMERIC_OVERRIDES[$name]; ?>
        <label>
          <?= $label ?>
          <input type="number" name="<?= $name ?>" min="<?= $min ?>" max="<?= $max ?>" step="1"
                 placeholder="auto" value="<?= isset($demoOverrides[$name]) ? (int)$demoOverrides[$name] : '' ?>">
        </label>
      <?php endforeach; ?>

      <div class="demo-form-actions">
        <button type="submit" class="primary-btn">Appliquer</button>
        <a class="secondary-btn button-link" href="livetiming.php?demo=<?= $demoPhase ?>">Réinitialiser</a>
        <a class="demo-quit" href="livetiming.php">Quitter la démo</a>
      </div>
    </form>
  </section>
<?php endif; ?>

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
    data-demo="<?= $demoPhase ? htmlspecialchars(demo_query_string($demoPhase, $demoOverrides), ENT_QUOTES) : '' ?>"
  >
    Chargement…
  </div>
</div>

<?php if (!$demoPhase && $phase !== 'live'): ?>
  <p class="demo-link">
    Pas de session en cours ? <a href="livetiming.php?demo=1" rel="nofollow">Voir une démo avec des données fictives</a>
  </p>
<?php endif; ?>

<script src="script/countdown.js"></script>
<?php if ($phase !== 'finished' && $focusSession): ?>
<script src="script/livetiming.js"></script>
<?php endif; ?>

<?php
require __DIR__ . '/includes/footer.php';
