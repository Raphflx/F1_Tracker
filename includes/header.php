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

// Si la page définit un titre, on l’utilise, sinon titre par défaut
$title = $pageTitle ?? 'F1 Tracker';
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
    <a href="index.php">Accueil</a>
    <a href="calendar.php">Calendrier 2025</a>
    <a href="livetiming.php">Live timing</a>
    <a href="credits.php">Crédits</a>
  </nav>
</header>

<!-- Début du contenu spécifique à chaque page -->
<main>
