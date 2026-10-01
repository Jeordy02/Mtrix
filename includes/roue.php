<?php
/* M'trix — le cadran de la prévente.
   La roue tourne pour amener le tarif en cours sous le repère fixe, en haut.
   Quand un palier se remplit, elle pivote toute seule vers le tarif suivant. */

function mtx_roue(): string {
    $paliers = mtx_paliers();
    $total   = mtx_total_places();
    $actif   = mtx_palier_actif();
    $courant = mtx_palier_courant();      // le palier en tête par inventaire, lancé ou non

    $cx = 200; $cy = 200; $rOut = 168; $rIn = 104;
    $jeu = 1.6;

    /* Cinq crans d'une seule teinte, du plus clair au plus sombre.
       Avant, c'étaient cinq couleurs sans rapport (jaune, orange, rouge,
       grenat, violet) : ça faisait cinq accents sur un dessin qui n'en
       veut qu'un. La rampe garde l'information — ça se durcit à mesure
       que ça coûte cher — sans sortir de l'orange de marque.
       Chaque encre tient au moins 3,5:1 sur son fond. */
    $teintes = [
        1 => ['#F7C58A', '#4A2A05'],
        2 => ['#F0A054', '#3D2103'],
        3 => ['#E8822B', '#3D2103'],
        4 => ['#C96510', '#FFF2E6'],
        5 => ['#8F4206', '#FFE9D6'],
    ];

    /* Angle à ramener en haut : le milieu de la part en cours. On pointe
       vers le palier en tête par inventaire même s'il est encore
       verrouillé par l'admin — sinon la roue resterait figée sur le
       palier 1 alors que tout a déjà avancé. */
    $angle = 0; $rot = 0;
    foreach ($paliers as $p) {
        $part = 360 * ((int) $p['places'] / $total);
        if ($courant && $p['n'] === $courant['n']) $rot = -($angle + $part / 2);
        $angle += $part;
    }

    $label = $actif ? 'Tarif en cours ' . fcfa($actif['prix'])
           : ($courant ? 'Bientôt — palier suivant à ' . fcfa($courant['prix']) : 'Prévente complète');
    $svg = '<svg class="cadran__svg" viewBox="0 0 400 400" role="img" aria-label="' . h($label) . '">';

    /* La roue, pivotée */
    $svg .= '<g class="cadran__roue" style="--rot:' . round($rot, 2) . 'deg">';
    $angle = 0;
    foreach ($paliers as $p) {
        $part = 360 * ((int) $p['places'] / $total);
        $a0 = $angle + $jeu / 2;
        $a1 = $angle + $part - $jeu / 2;
        $epuise  = $p['statut'] === 'epuise';
        $encours = $p['statut'] === 'encours';

        [$fond, $encre] = $teintes[$p['n']] ?? ['#E8822B', '#fff'];
        if ($epuise) { $fond = '#2A2A2C'; $encre = '#8A8B90'; }   /* 4,2:1 — lisible malgré l'opacité réduite */

        $cls = 'cadran__part' . ($epuise ? ' est-epuise' : '') . ($encours ? ' est-encours' : '');
        $svg .= '<path class="' . $cls . '" d="' . mtx_secteur($cx, $cy, $rOut, $rIn, $a0, $a1) . '" fill="' . $fond . '">'
              . '<title>' . h(fcfa($p['prix']) . ' — ' . $p['restant'] . ' sur ' . $p['places']) . '</title></path>';

        /* Le nombre restant, remis droit malgré la rotation de la roue */
        $mid = $angle + $part / 2;
        $rad = deg2rad($mid - 90);
        $tx = $cx + (($rOut + $rIn) / 2) * cos($rad);
        $ty = $cy + (($rOut + $rIn) / 2) * sin($rad);
        $svg .= '<g class="cadran__chiffre" style="--rot:' . round($rot, 2) . 'deg" transform="translate(' . round($tx, 1) . ' ' . round($ty, 1) . ')">'
              . '<text y="8" fill="' . $encre . '">' . $p['restant'] . '</text></g>';

        $angle += $part;
    }
    $svg .= '</g>';

    /* Repère fixe en haut */
    $svg .= '<path class="cadran__repere" d="M 200 8 L 212 30 L 188 30 Z"/>'
          . '<line class="cadran__trait" x1="200" y1="32" x2="200" y2="' . ($cy - $rOut - 2) . '"/>';

    /* Moyeu */
    $svg .= '<circle class="cadran__moyeu" cx="' . $cx . '" cy="' . $cy . '" r="' . ($rIn - 8) . '"/>';
    if ($actif) {
        $svg .= '<text class="cadran__etiq" x="' . $cx . '" y="' . ($cy - 30) . '">maintenant</text>'
              . '<text class="cadran__prix" x="' . $cx . '" y="' . ($cy + 10) . '">' . number_format($actif['prix'], 0, ',', ' ') . '</text>'
              . '<text class="cadran__dev"  x="' . $cx . '" y="' . ($cy + 32) . '">FCFA</text>'
              . '<text class="cadran__reste" x="' . $cx . '" y="' . ($cy + 60) . '">'
              . h($actif['restant'] . ' à ce prix') . '</text>';
    } else {
        $svg .= '<text class="cadran__prix" x="' . $cx . '" y="' . ($cy + 6) . '" style="font-size:34px">Complet</text>';
    }

    return $svg . '</svg>';
}

/* La bande des 5 tarifs, sous le cadran */
function mtx_bande(): string {
    $out = '<ol class="bande">';
    foreach (mtx_paliers() as $p) {
        $out .= '<li class="bande__pas bande__pas--' . h($p['statut']) . '">'
              . '<span class="bande__prix">' . number_format($p['prix'], 0, ',', ' ') . '</span>'
              . '<span class="bande__info">'
              . ($p['statut'] === 'epuise' ? 'parti' : ($p['statut'] === 'encours' ? $p['restant'] . ' / ' . $p['places'] : $p['places'] . ' pièces'))
              . '</span></li>';
    }
    /* Dernier cran : le prix normal, sans dire combien il en reste */
    $out .= '<li class="bande__pas bande__pas--normal">'
          . '<span class="bande__prix">Prix normal</span>'
          . '<span class="bande__info">apr&egrave;s la pr&eacute;vente</span></li>';
    return $out . '</ol>';
}
