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

require_once __DIR__ . '/lib/openf1.php';

$pageTitle = 'Calendrier – F1 Tracker';
require __DIR__ . '/includes/header.php';

// Année : paramètre GET, année courante par défaut ou si invalide
$year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT);
if (!$year || $year < 1950 || $year > 2100) {
    $year = (int)date('Y');
}

// Chargement des meetings (plage week-end calculée par le service)
try {
    $meetings = f1_get_meetings($year);
} catch (Throwable $e) {
    $meetings = [];
    error_log('[F1Tracker] calendar.php : ' . $e->getMessage());
    $errorMessage = 'Impossible de charger les données. Veuillez réessayer plus tard.';
}
?>

<a class="back-btn" href="index.php">← Retour à l’accueil</a>

<h2>Calendrier des Grands Prix <?= (int)$year ?></h2>
<p class="section-subtitle">
  Clique sur un circuit pour voir le détail des essais, qualifications, sprint et course.
</p>

<?php if (!empty($errorMessage ?? null)): ?>
  <p class="error">
    <?= htmlspecialchars($errorMessage, ENT_QUOTES) ?>
  </p>
<?php endif; ?>

<section class="calendar-grid">
  <?php if (empty($meetings)): ?>
    <p>Aucun meeting trouvé pour <?= (int)$year ?>.</p>
  <?php else: ?>
    <?php foreach ($meetings as $meeting): ?>
      <?php
        $mk      = (string)$meeting['meeting_key'];
        $circuit = $meeting['circuit'] !== '' ? $meeting['circuit'] : 'Circuit inconnu';
        $country = $meeting['country_name'] !== '' ? $meeting['country_name'] : 'Pays inconnu';

        // Plage du week-end (heure locale circuit)
        $weekendText = ($meeting['weekend_start'] && $meeting['weekend_end'])
            ? format_weekend_range(new DateTime($meeting['weekend_start']), new DateTime($meeting['weekend_end']))
            : 'Dates à venir';

        // Lien propre (slug) + meeting_key
        $slug = slugify($country . '-' . $circuit);
        $href = "grandprix.php?meeting_key=" . urlencode($mk) . "&slug=" . urlencode($slug);
      ?>

      <a class="race-card" href="<?= htmlspecialchars($href, ENT_QUOTES) ?>">
        <h3><?= htmlspecialchars($country, ENT_QUOTES) ?></h3>
        <p class="race-circuit"><?= htmlspecialchars($circuit, ENT_QUOTES) ?></p>
        <p class="race-date"><?= htmlspecialchars($weekendText, ENT_QUOTES) ?></p>
      </a>

    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php
require __DIR__ . '/includes/footer.php';
