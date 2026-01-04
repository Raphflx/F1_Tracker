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

require __DIR__ . '/lib/openf1.php';

$pageTitle = 'Choisir une saison – F1 Tracker';
require __DIR__ . '/includes/header.php';

$errorMessage = null;
$years = [];

try {
    // On récupère tous les meetings et on déduit les années.
    // (OpenF1 renvoie généralement un champ "year"; sinon on parse date_start)
    $meetings = openf1_get('meetings');

    $set = [];

    foreach ($meetings as $m) {
        // 1) Priorité au champ year si présent
        if (isset($m['year']) && is_numeric($m['year'])) {
            $y = (int)$m['year'];
            if ($y > 1950 && $y < 2100) {
                $set[$y] = true;
                continue;
            }
        }

        // 2) Fallback: parse date_start (ISO)
        if (!empty($m['date_start'])) {
            try {
                $dt = new DateTime((string)$m['date_start'], new DateTimeZone('UTC'));
                $y = (int)$dt->format('Y');
                if ($y > 1950 && $y < 2100) {
                    $set[$y] = true;
                }
            } catch (Throwable) {
                // ignore date parse errors
            }
        }
    }

    $years = array_keys($set);
    rsort($years);

    // Si l'API renvoie vide, on met un fallback propre
    if (empty($years)) {
        $errorMessage = "Aucune saison détectée via OpenF1.";
        $years = [2025];
    }
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
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
  <p class="error">
    ⚠️ Impossible de charger la liste des saisons automatiquement (OpenF1).
    Détail : <?= htmlspecialchars($errorMessage, ENT_QUOTES) ?>
  </p>
<?php endif; ?>

<section class="calendar-grid">
  <?php foreach ($years as $year): ?>
    <a class="race-card" href="calendar.php?year=<?= (int)$year ?>">
      <div class="race-card-content">
        <h3>Saison <?= (int)$year ?></h3>
        <p class="race-circuit">Championnat du monde de Formule 1</p>
        <p class="race-date">Voir le calendrier →</p>
      </div>
    </a>
  <?php endforeach; ?>
</section>

<?php
require __DIR__ . '/includes/footer.php';
