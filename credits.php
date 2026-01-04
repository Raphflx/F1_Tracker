<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Crédits & Mentions
|--------------------------------------------------------------------------
| - Présente les sources de données
| - Précise les droits, licences et avertissements légaux
| - Centralisé et structuré pour une lecture claire
*/

$pageTitle = 'Crédits & mentions – F1 Tracker';
require __DIR__ . '/includes/header.php';
?>

<button class="back-btn" onclick="window.location.href='index.php'">
  ← Retour à l’accueil
</button>

<h2>Crédits & mentions</h2>

<section class="credits-section">
  <h3>Projet</h3>
  <p>
    <strong>F1 Tracker</strong> est un projet personnel développé à des fins pédagogiques et
    expérimentales. Il a pour objectif de proposer une visualisation claire et accessible
    des informations liées au championnat du monde de Formule 1.
  </p>
</section>

<section class="credits-section">
  <h3>Sources de données</h3>
  <p>
    Les données utilisées sur ce site proviennent de l’API non officielle
    <a href="https://openf1.org" target="_blank" rel="noopener">OpenF1</a>.
  </p>

  <ul>
    <li>Calendrier des Grands Prix</li>
    <li>Sessions (essais, qualifications, sprint, course)</li>
    <li>Données météo</li>
    <li>Événements de course (race control)</li>
  </ul>

  <p>
    Bien que ces données soient généralement fiables, elles peuvent présenter
    des retards ou des incohérences selon les événements.
  </p>
</section>

<section class="credits-section">
  <h3>Droits et propriété intellectuelle</h3>
  <p>
    F1, Formula 1, les noms des Grands Prix, les circuits, les équipes, les pilotes
    ainsi que leurs logos respectifs sont des marques déposées et des éléments
    protégés appartenant à leurs propriétaires respectifs.
  </p>

  <p>
    Ce site n’est ni affilié, ni sponsorisé, ni approuvé par la Formula 1,
    la FIA, les équipes ou les pilotes.
  </p>
</section>

<section class="credits-section">
  <h3>Licence du contenu</h3>
  <p>
    Le contenu original de ce site (structure, textes, code) est mis à disposition
    sous licence
    <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/deed.fr" target="_blank" rel="noopener">
      Creative Commons Attribution – Pas d’Utilisation Commerciale – Partage dans les Mêmes Conditions 4.0
    </a>.
  </p>

  <p>
    © <?= date('Y') ?> Chappe Raphaël
  </p>
</section>

<?php
require __DIR__ . '/includes/footer.php';
