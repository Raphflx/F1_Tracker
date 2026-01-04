<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Accueil – F1 Tracker (Landing page)
|--------------------------------------------------------------------------
| - Mise en avant des fonctionnalités principales
| - Accès rapide calendrier / live timing
| - Présentation claire et pro du projet
*/

$pageTitle = 'Accueil – F1 Tracker';
require __DIR__ . '/includes/header.php';
?>

<section class="home-hero">
  <div class="home-hero-content">
    <div class="home-badge">Projet non officiel</div>

    <h2>F1 Tracker</h2>
    <p class="home-lead">
      Un tableau de bord simple pour suivre le calendrier 2025, les sessions du week-end
      et un live timing lisible (météo, compte à rebours, état de piste).
    </p>

    <div class="home-cta">
      <a href="calendar_year.php" class="primary-btn button-link">Calendriers</a>
      <a href="livetiming.php" class="secondary-btn button-link">Ouvrir le live timing</a>
    </div>

    <div class="home-meta">
      <span>🔌 Données : OpenF1</span>
      <span>🕒 Fuseau : Europe/Paris</span>
    </div>
  </div>
</section>

<section class="home-grid">
  <a class="home-card" href="calendar_year.php">
    <div class="home-card-icon">📅</div>
    <div class="home-card-body">
      <h3>Calendriers</h3>
      <p>Liste complète des Grands Prix a partir de 2025.</p>
    </div>
  </a>

  <a class="home-card" href="livetiming.php">
    <div class="home-card-icon">⏱️</div>
    <div class="home-card-body">
      <h3>Live timing</h3>
      <p>Compte à rebours en direct, météo, tours et état de piste selon les infos disponibles.</p>
      <span class="home-card-link">Accéder →</span>
    </div>
  </a>

  <a class="home-card" href="credits.php">
    <div class="home-card-icon">ℹ️</div>
    <div class="home-card-body">
      <h3>Crédits & mentions</h3>
      <p>Sources de données, propriété intellectuelle, licence et informations légales.</p>
      <span class="home-card-link">Lire →</span>
    </div>
  </a>
</section>

<section class="home-about">
  <h3>À propos</h3>
  <p>
    Ce site est un projet personnel, sans affiliation avec Formula 1, la FIA, les équipes ou les pilotes.
    Les informations affichées sont issues de l’API non officielle OpenF1 et peuvent varier selon la disponibilité des données.
  </p>
</section>

<?php
require __DIR__ . '/includes/footer.php';
