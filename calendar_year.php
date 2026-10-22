<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Choix de l’année – Calendrier (auto)
|--------------------------------------------------------------------------
| - Récupère automatiquement les années disponibles via OpenF1
| - Affiche les saisons triées (récentes d'abord)
| - Redirige vers calendar.php?year=XXXX
*/

require_once __DIR__ . '/lib/openf1.php';

$pageTitle = 'Choisir une saison – F1 Tracker';
require __DIR__ . '/includes/header.php';

$errorMessage = null;
$years = [];

try {
    $years = f1_get_years();

    // Si l'API renvoie vide, on met un fallback propre
    if (empty($years)) {
        $errorMessage = "Aucune saison détectée via OpenF1.";
        $years = [2025];
    }
} catch (Throwable $e) {
    error_log('[F1Tracker] calendar_year.php : ' . $e->getMessage());
    $errorMessage = 'Impossible de charger la liste des saisons. Veuillez réessayer plus tard.';
    // Fallback minimal (site reste utilisable)
    $years = [2025];
}
?>

<button class="back-btn" onclick="window.location.href='index.php'">
  ← Retour à l’accueil
</button>

<h2>Calendrier – Choisir une saison</h2>
<p class="section-subtitle">
  Sélectionne une année pour afficher la liste des Grands Prix.
</p>

<?php if ($errorMessage): ?>
  <p class="error"><?= htmlspecialchars($errorMessage, ENT_QUOTES) ?></p>
<?php endif; ?>

<section class="calendar-grid">
  <?php foreach ($years as $year): ?>
    <a class="race-card" href="calendar.php?year=<?= (int)$year ?>">
      <h3>Saison <?= (int)$year ?></h3>
      <p class="race-circuit">Championnat du monde de Formule 1</p>
      <p class="race-date">Voir le calendrier →</p>
    </a>
  <?php endforeach; ?>
</section>

<?php
require __DIR__ . '/includes/footer.php';
