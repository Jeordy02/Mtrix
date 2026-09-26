<?php
/* M'trix — réglages de la prévente.
   Le nombre de pièces vendues se change depuis admin.php, pas ici. */

require_once __DIR__ . '/store.php';

const MTX_WHATSAPP  = '22958779933';
const MTX_INSTAGRAM = 'mtrix229';
const MTX_TIKTOK    = 'mtrix2290';
const MTX_LANCEMENT = '01/11/26';

/* Les 5 coloris. 'fichier' = photo dans assets/ */
function mtx_couleurs(): array {
    return [
        ['id' => 'noir',       'nom' => 'Noir',       'fichier' => 'mtrix-noir.jpg',       'puce' => '#121214'],
        ['id' => 'noir-fume',  'nom' => 'Noir fumé',  'fichier' => 'mtrix-noir-fume.jpg',  'puce' => 'linear-gradient(135deg,#3AA0C8,#E8C63A 55%,#2F8F6B)'],
        ['id' => 'blanc',      'nom' => 'Blanc',      'fichier' => 'mtrix-blanc.jpg',      'puce' => '#F1F1EF'],
        ['id' => 'marron',     'nom' => 'Marron',     'fichier' => 'mtrix-marron.jpg',     'puce' => '#B9AFA4'],
        ['id' => 'rouge',      'nom' => 'Rouge',      'fichier' => 'mtrix-rouge.jpg',      'puce' => '#C8202A'],
    ];
}

/* Les paliers de la prévente : le prix monte à mesure que les places partent. */
function mtx_paliers_def(): array {
    return [
        ['n' => 1, 'prix' => 3500, 'places' => 1],
        ['n' => 2, 'prix' => 4500, 'places' => 2],
        ['n' => 3, 'prix' => 5000, 'places' => 2],
        ['n' => 4, 'prix' => 6000, 'places' => 5],
        ['n' => 5, 'prix' => 7000, 'places' => 5],
    ];
}

function mtx_total_places(): int {
    $t = 0;
    foreach (mtx_paliers_def() as $p) $t += (int) $p['places'];
    return $t;   // 15
}

function mtx_ca_total(): int {
    $t = 0;
    foreach (mtx_paliers_def() as $p) $t += (int) $p['places'] * (int) $p['prix'];
    return $t;   // 87 500
}

/* Nombre de pièces vendues (0 à 15). */
function mtx_vendues(): int {
    $e = mtx_read('etat', []);
    return max(0, min(mtx_total_places(), (int) ($e['vendues'] ?? 0)));
}

function mtx_set_vendues(int $n): void {
    $e = mtx_read('etat', []);
    $e['vendues'] = max(0, min(mtx_total_places(), $n));
    mtx_write('etat', $e);
}

/* Combien de paliers l'admin a lancés (0 = rien encore, prévente pas
   ouverte ; 1 = seul le palier 1 se vend ; etc.). C'est la seule chose
   qui décide si un palier est réellement achetable — même s'il reste
   des places dedans, personne ne peut payer tant qu'il n'est pas lancé. */
function mtx_palier_debloque(): int {
    $e = mtx_read('etat', []);
    return max(0, min(count(mtx_paliers_def()), (int) ($e['debloque'] ?? 0)));
}

function mtx_set_debloque(int $n): void {
    $e = mtx_read('etat', []);
    $e['debloque'] = max(0, min(count(mtx_paliers_def()), $n));
    mtx_write('etat', $e);
}

/* Lance le palier suivant depuis admin.php : à partir de maintenant il
   devient achetable. Donne aussitôt ses places à qui patiente déjà dans
   la file plutôt que de les laisser filer au premier clic venu. */
function mtx_palier_debloquer_suivant(): void {
    mtx_verrou(function () {
        $actuel = mtx_palier_debloque();
        $total  = count(mtx_paliers_def());
        if ($actuel >= $total) return;                   // déjà tout lancé

        $suivant = $actuel + 1;
        mtx_set_debloque($suivant);

        $places = 0;
        foreach (mtx_paliers_def() as $p) if ((int) $p['n'] === $suivant) $places = (int) $p['places'];
        mtx_file_promouvoir($places);
    });
}

/* Les paliers enrichis : places prises, restantes, statut d'inventaire
   et s'ils sont réellement ouverts à l'achat.
   Les ventes remplissent les paliers dans l'ordre : palier 1, puis 2, etc. */
function mtx_paliers(): array {
    $reste    = mtx_vendues();
    $debloque = mtx_palier_debloque();
    $out = [];
    $encoursTrouve = false;
    foreach (mtx_paliers_def() as $p) {
        $pris = min((int) $p['places'], $reste);
        $reste -= $pris;
        $p['pris']    = $pris;
        $p['restant'] = (int) $p['places'] - $pris;

        if ($p['restant'] === 0) {
            $p['statut'] = 'epuise';
        } elseif (!$encoursTrouve) {
            $p['statut'] = 'encours';
            $encoursTrouve = true;
        } else {
            $p['statut'] = 'a_venir';
        }
        /* Achetable seulement si c'est le palier en cours ET que l'admin
           l'a explicitement lancé depuis admin.php. */
        $p['ouvert'] = $p['statut'] === 'encours' && (int) $p['n'] <= $debloque;
        $out[] = $p;
    }
    return $out;
}

function mtx_palier_actif(): ?array {
    foreach (mtx_paliers() as $p) if ($p['ouvert']) return $p;
    return null;   // rien à acheter là, maintenant
}

/* Le palier en cours d'inventaire, lancé ou non — sert à l'admin pour
   savoir lequel lancer ensuite, et au public pour savoir ce qui arrive. */
function mtx_palier_courant(): ?array {
    foreach (mtx_paliers() as $p) if ($p['statut'] === 'encours') return $p;
    return null;   // tout est vendu
}

/* Une case par pièce, dans l'ordre : sert à dessiner la roue. */
function mtx_cases(): array {
    $cases = [];
    $vendues = mtx_vendues();
    $idx = 0;
    foreach (mtx_paliers_def() as $p) {
        for ($k = 0; $k < (int) $p['places']; $k++) {
            $idx++;
            $cases[] = [
                'num'    => $idx,
                'palier' => (int) $p['n'],
                'prix'   => (int) $p['prix'],
                'vendue' => $idx <= $vendues,
                'active' => $idx === $vendues + 1,
            ];
        }
    }
    return $cases;
}

/* Recette déjà encaissée d'après les pièces vendues. */
function mtx_ca_realise(): int {
    $t = 0;
    foreach (mtx_cases() as $c) if ($c['vendue']) $t += $c['prix'];
    return $t;
}

/* Secteur d'anneau en SVG : de l'angle $a0 à $a1 (degrés, 0 = midi). */
function mtx_secteur(float $cx, float $cy, float $rOut, float $rIn, float $a0, float $a1): string {
    $pt = function (float $r, float $deg) use ($cx, $cy): array {
        $rad = deg2rad($deg - 90);
        return [$cx + $r * cos($rad), $cy + $r * sin($rad)];
    };
    [$x1, $y1] = $pt($rOut, $a0);
    [$x2, $y2] = $pt($rOut, $a1);
    [$x3, $y3] = $pt($rIn,  $a1);
    [$x4, $y4] = $pt($rIn,  $a0);
    $grand = ($a1 - $a0) > 180 ? 1 : 0;
    return sprintf(
        'M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f L %.2f %.2f A %.2f %.2f 0 %d 0 %.2f %.2f Z',
        $x1, $y1, $rOut, $rOut, $grand, $x2, $y2,
        $x3, $y3, $rIn,  $rIn,  $grand, $x4, $y4
    );
}

/* Le prix du palier suivant, pour dire "après, ça passe à X". */
function mtx_prochain_prix(): ?int {
    $vu = false;
    foreach (mtx_paliers() as $p) {
        if ($vu) return (int) $p['prix'];
        if ($p['statut'] === 'encours') $vu = true;
    }
    return null;
}
