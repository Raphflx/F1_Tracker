<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Footer commun du site
|--------------------------------------------------------------------------
| - Contient les mentions légales et crédits
| - Centralisé pour garantir la cohérence sur toutes les pages
*/
?>

<!-- Fin du contenu spécifique -->
</main>

<footer class="site-footer">

  <!-- Source des données -->
  <p>
    Les données de course sont fournies par l’API non officielle
    <a href="https://openf1.org" target="_blank" rel="noopener">OpenF1</a>.
    Certaines courses futures peuvent apparaître avec un léger délai après leur annonce ou leur déroulement.
  </p>

  <!-- Droits et licence -->
  <p>
    Contenu original du site © <?= date('Y') ?> Chappe Raphaël –
    licence
    <a href="https://creativecommons.org/licenses/by-nc-sa/4.0/deed.fr" target="_blank" rel="noopener">
      CC BY-NC-SA 4.0
    </a>
    – <a href="credits.php">Crédits & mentions</a>
  </p>

  <!-- Mentions liées à la F1 -->
  <p>
    F1, Formula 1, les noms des Grands Prix, ainsi que les noms et logos des écuries et des pilotes
    sont des marques et éléments protégés appartenant à leurs propriétaires respectifs.
    Ce site est un projet non officiel, sans lien avec Formula 1, la FIA, les équipes ou les pilotes.
  </p>
</footer>

</body>
</html>
