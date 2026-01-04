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

require __DIR__ . '/lib/openf1.php';
require __DIR__ . '/lib/datetime.php';

date_default_timezone_set('Europe/Paris');

$pageTitle = 'Live timing – F1 Tracker';
require __DIR__ . '/includes/header.php';

$YEAR = 2025;

// ---------------------------------------------------------
// Helpers simples (affichage)
// ---------------------------------------------------------
function format_date_fr(DateTime $dt): string
{
    static $jours = [
        'Monday' => 'lundi', 'Tuesday' => 'mardi', 'Wednesday' => 'mercredi',
        'Thursday' => 'jeudi', 'Friday' => 'vendredi', 'Saturday' => 'samedi', 'Sunday' => 'dimanche',
    ];

    $dayEn = $dt->format('l');
    $dayFr = $jours[$dayEn] ?? $dayEn;
    return $dayFr . ' ' . $dt->format('d/m/Y H:i');
}

function pick_focus_session(array $sessions, DateTime $nowParis): ?array
{
    // Priorité :
    // 1) session en cours
    // 2) prochaine session à venir
    // 3) dernière session passée

    $nowUtc = clone $nowParis;
    $nowUtc->setTimezone(new DateTimeZone('UTC'));

    $live = null;
    $next = null;
    $last = null;

    foreach ($sessions as $s) {
        if (empty($s['date_start']) || empty($s['date_end'])) {
            continue;
        }
        $start = new DateTime((string)$s['date_start'], new DateTimeZone('UTC'));
        $end   = new DateTime((string)$s['date_end'],   new DateTimeZone('UTC'));

        if ($start <= $nowUtc && $nowUtc <= $end) {
            $live = $s;
            break;
        }
        if ($start > $nowUtc) {
            if ($next === null || new DateTime((string)$next['date_start'], new DateTimeZone('UTC')) > $start) {
                $next = $s;
            }
        }
        if ($end < $nowUtc) {
            $last = $s;
        }
    }

    return $live ?? $next ?? $last;
}

function track_status_from_race_control(array $raceControl): array
{
    // Retourne : [label, cssClass]
    // Par défaut
    $label = 'Clair';
    $css = 'track-status--green';

    if (empty($raceControl)) {
        return [$label, $css];
    }

    // On prend l'event le plus récent (date max)
    usort($raceControl, static function (array $a, array $b): int {
        return strtotime((string)($a['date'] ?? '1970-01-01')) <=> strtotime((string)($b['date'] ?? '1970-01-01'));
    });
    $last = $raceControl[count($raceControl) - 1];

    $category = strtoupper((string)($last['category'] ?? ''));
    $flag     = strtoupper((string)($last['flag'] ?? ''));
    $message  = strtoupper((string)($last['message'] ?? ''));

    // Safety Car
    if ($category === 'SAFETYCAR' || str_contains($message, 'SAFETY CAR')) {
        return ['Safety Car', 'track-status--yellow'];
    }

    // Rouge
    if (str_contains($flag, 'RED') || str_contains($message, 'RED FLAG')) {
        return ['Drapeau rouge', 'track-status--red'];
    }

    // Jaune
    if (str_contains($flag, 'YELLOW')) {
        return ['Drapeau jaune', 'track-status--yellow'];
    }

    // Vert
    if (str_contains($flag, 'GREEN')) {
        return ['Clair', 'track-status--green'];
    }

    return [$label, $css];
}

function current_lap_from_race_control(array $raceControl): ?int
{
    $max = null;
    foreach ($raceControl as $e) {
        if (!isset($e['lap_number'])) continue;
        $n = (int)$e['lap_number'];
        if ($n > 0 && ($max === null || $n > $max)) {
            $max = $n;
        }
    }
    return $max;
}

// ---------------------------------------------------------
// 1) Chargement sessions (année) + session focus
// ---------------------------------------------------------
$errorMessage = null;
$sessions = [];
$focusSession = null;

try {
    $sessions = openf1_get("sessions?year={$YEAR}");
    // Tri chronologique
    usort($sessions, static function (array $a, array $b): int {
        return strtotime((string)($a['date_start'] ?? '1970-01-01')) <=> strtotime((string)($b['date_start'] ?? '1970-01-01'));
    });

    $focusSession = pick_focus_session($sessions, new DateTime('now', new DateTimeZone('Europe/Paris')));
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
}

// ---------------------------------------------------------
// 2) Chargement meeting + weather + race_control (si session)
// ---------------------------------------------------------
$meeting = null;
$weather = null;
$raceControl = [];

if ($focusSession && isset($focusSession['meeting_key'], $focusSession['session_key'])) {
    try {
        $meetingList = openf1_get('meetings?meeting_key=' . urlencode((string)$focusSession['meeting_key']));
        $meeting = $meetingList[0] ?? null;

        // Weather : on prend la dernière mesure (la plus récente)
        $weatherList = openf1_get('weather?session_key=' . urlencode((string)$focusSession['session_key']));
        if (!empty($weatherList)) {
            usort($weatherList, static fn($a, $b) => strtotime((string)$a['date']) <=> strtotime((string)$b['date']));
            $weather = $weatherList[count($weatherList) - 1];
        }

        // Race control (pour tours + statut piste)
        // On limite aux dernières 2h pour éviter de récupérer trop de données
        $sinceUtc = new DateTime('now', new DateTimeZone('UTC'));
        $sinceUtc->modify('-2 hours');
        $sinceIso = $sinceUtc->format('Y-m-d\TH:i:s');

        $raceControl = openf1_get(
            'race_control?session_key=' . urlencode((string)$focusSession['session_key']) .
            '&date>=' . urlencode($sinceIso)
        );
    } catch (Throwable $e) {
        // On ne bloque pas toute la page si une partie rate
        $errorMessage = $errorMessage ?: $e->getMessage();
    }
}

// ---------------------------------------------------------
// 3) Préparation affichage bandeau
// ---------------------------------------------------------
$nowParis = new DateTime('now', new DateTimeZone('Europe/Paris'));

$bannerTitle = $meeting['meeting_name'] ?? null;
$bannerCountry = $meeting['country_name'] ?? null;

$sessionName = $focusSession['session_name'] ?? null;
$sessionType = $focusSession['session_type'] ?? null;

$startUtc = $focusSession['date_start'] ?? null;
$endUtc   = $focusSession['date_end'] ?? null;

$startParis = $startUtc ? (new DateTime((string)$startUtc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Paris')) : null;
$endParis   = $endUtc   ? (new DateTime((string)$endUtc,   new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Paris')) : null;

$phase = 'finished';
if ($startParis && $endParis) {
    if ($nowParis < $startParis) $phase = 'upcoming';
    elseif ($nowParis <= $endParis) $phase = 'live';
    else $phase = 'finished';
}

// Météo
$trackTempText = isset($weather['track_temp']) ? ((int)round((float)$weather['track_temp']) . '°C') : 'N/A';
$airTempText   = isset($weather['air_temp'])   ? ((int)round((float)$weather['air_temp']) . '°C')   : 'N/A';
$humidityText  = isset($weather['humidity'])   ? ((int)round((float)$weather['humidity']) . '%')    : 'N/A';

$windText = 'N/A';
if (isset($weather['wind_speed'])) {
    $windText = (int)round((float)$weather['wind_speed']) . ' km/h';
}

// Tours + statut piste
[$trackStatusLabel, $trackStatusClass] = track_status_from_race_control($raceControl);
$currentLap = current_lap_from_race_control($raceControl);

// Total tours : si OpenF1 ne le fournit pas dans session/meeting, on met "—"
$totalLaps = null;
if (isset($focusSession['total_laps'])) {
    $totalLaps = (int)$focusSession['total_laps'];
} elseif (isset($focusSession['lap_count'])) {
    $totalLaps = (int)$focusSession['lap_count'];
}
$lapsText = ($currentLap ? (string)$currentLap : '—') . '/' . ($totalLaps ? (string)$totalLaps : '—');

?>

<button class="back-btn" onclick="window.location.href='index.php'">← Retour à l’accueil</button>

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
      <span class="value"><?= htmlspecialchars($lapsText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Piste</span>
      <span class="value">
        <span class="track-status-badge <?= htmlspecialchars($trackStatusClass, ENT_QUOTES) ?>">
          <?= htmlspecialchars($trackStatusLabel, ENT_QUOTES) ?>
        </span>
      </span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Track</span>
      <span class="value"><?= htmlspecialchars($trackTempText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Air</span>
      <span class="value"><?= htmlspecialchars($airTempText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Humidité</span>
      <span class="value"><?= htmlspecialchars($humidityText, ENT_QUOTES) ?></span>
    </div>

    <div class="livetiming-metric">
      <span class="label">Vent</span>
      <span class="value"><?= htmlspecialchars($windText, ENT_QUOTES) ?></span>
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
  >
    Chargement…
  </div>
</div>

<script>
(function () {
  const el = document.getElementById('livetiming-countdown');
  if (!el) return;

  const startTs = parseInt(el.dataset.start, 10) * 1000;
  const endTs   = parseInt(el.dataset.end, 10) * 1000;
  let phase     = el.dataset.phase || 'finished';

  function formatDuration(ms) {
    if (ms <= 0) return '0s';

    const totalSeconds = Math.floor(ms / 1000);

    const days = Math.floor(totalSeconds / (24 * 3600));
    const hours = Math.floor((totalSeconds % (24 * 3600)) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    const parts = [];
    if (days > 0) parts.push(days + 'j');
    if (hours > 0 || days > 0) parts.push(hours + 'h');
    if (minutes > 0 || hours > 0 || days > 0) parts.push(minutes + 'min');
    parts.push(seconds + 's');

    return parts.join(' ');
  }

  function tick() {
    const now = Date.now();

    if (startTs === 0 || endTs === 0) {
      el.textContent = 'Temps indisponible';
      return;
    }

    if (now < startTs) {
      phase = 'upcoming';
      el.textContent = 'Début dans ' + formatDuration(startTs - now);
    } else if (now <= endTs) {
      phase = 'live';
      el.textContent = 'En cours – fin dans ' + formatDuration(endTs - now);
    } else {
      phase = 'finished';
      el.textContent = 'Session terminée';
    }
  }

  tick();
  setInterval(tick, 1000);
})();
</script>

<?php
require __DIR__ . '/includes/footer.php';
