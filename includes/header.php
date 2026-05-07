<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Header commun du site
|--------------------------------------------------------------------------
| - Centralise le <head>, le header visuel et la navigation
| - Permet de définir un titre par page via la variable $pageTitle
| - Évite toute duplication HTML dans les pages PHP
*/

$title = $pageTitle ?? 'F1 Tracker';

// Détecte la section active pour la nav (peut être surchargée par $navSection)
$_currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$_navSection  = $navSection ?? $_currentPage;
if (in_array($_currentPage, ['calendar.php', 'grandprix.php'], true)) {
    $_navSection = 'calendar_year.php';
}
function nav_active(string $page, string $section): string {
    return $page === $section ? ' class="active" aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">

  <!-- Adaptation mobile -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Titre dynamique -->
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>

  <!-- Feuille de style globale -->
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- En-tête principal du site -->
<header>
  <h1>F1 Tracker</h1>

  <!-- Navigation principale -->
  <nav>
    <a href="index.php"<?= nav_active('index.php', $_navSection) ?>>Accueil</a>
    <a href="calendar_year.php"<?= nav_active('calendar_year.php', $_navSection) ?>>Calendriers</a>
    <a href="livetiming.php"<?= nav_active('livetiming.php', $_navSection) ?>>Live timing</a>
    <a href="credits.php"<?= nav_active('credits.php', $_navSection) ?>>Crédits</a>
  </nav>
</header>

<!-- Début du contenu spécifique à chaque page -->
<main>
