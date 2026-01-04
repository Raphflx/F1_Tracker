<?php
declare(strict_types=1);

/**
 * Helpers texte (URLs, affichage, etc.)
 */

function slugify(string $str): string
{
    // Enlève les accents
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = strtolower($str);

    // Remplace tout ce qui n'est pas lettre ou chiffre par un tiret
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);

    // Supprime les tirets en trop au début/fin
    $str = trim($str, '-');

    return $str !== '' ? $str : 'circuit';
}
