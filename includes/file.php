<?php
/* M'trix — la file d'attente.

   Quand toutes les places libres sont tenues par d'autres en train de
   payer, le nouveau venu ne repart pas les mains vides : il prend un
   ticket. Dès qu'une place se libère (5 minutes sans paiement déclaré),
   elle est donnée directement au premier ticket de la file — pas remise
   en jeu pour que tout le monde retente sa chance en même temps.

   Deux petits fichiers :
     attente_file.json     les tickets qui patientent, dans l'ordre d'arrivée
     attente_promues.json  les tickets qui viennent de gagner une place,
                            en attendant que leur navigateur vienne la
                            chercher au prochain sondage */

const MTX_FILE_TICKET_MINUTES = 25;   // un ticket jamais réclamé finit par s'effacer

function mtx_file_lire(): array { return mtx_read('attente_file', []); }
function mtx_file_ecrire(array $rows): void { mtx_write('attente_file', $rows); }
function mtx_promues_lire(): array { return mtx_read('attente_promues', []); }
function mtx_promues_ecrire(array $rows): void { mtx_write('attente_promues', $rows); }

/* Prend un ticket. Si ce navigateur en a déjà un valide (jeton fourni),
   on ne le duplique pas — on renvoie simplement sa position actuelle.
   Version interne : n'acquiert pas le verrou elle-même — à appeler
   uniquement depuis l'intérieur d'un mtx_verrou() déjà ouvert. */
function mtx_file_rejoindre_interne(string $couleurId, ?string $jeton): array {
    $file = mtx_file_lire();

    if ($jeton) {
        foreach ($file as $i => $t) {
            if ($t['jeton'] === $jeton) return ['jeton' => $jeton, 'position' => $i + 1];
        }
    }

    $neuf = ['jeton' => mtx_uid(), 'couleur_id' => $couleurId, 'arrivee_le' => date('Y-m-d H:i:s')];
    $file[] = $neuf;
    mtx_file_ecrire($file);
    return ['jeton' => $neuf['jeton'], 'position' => count($file)];
}

/* Même chose, pour un appel isolé (hors d'un verrou déjà ouvert). */
function mtx_file_rejoindre(string $couleurId, ?string $jeton): array {
    return mtx_verrou(fn() => mtx_file_rejoindre_interne($couleurId, $jeton));
}

/* Où en est ce ticket ? 'promue' (une place l'attend), 'attente' (position
   donnée), ou 'inconnu' (jamais pris, ou expiré — il doit recommencer). */
function mtx_file_etat(string $jeton): array {
    $promues = mtx_promues_lire();
    if (isset($promues[$jeton])) {
        $ref = $promues[$jeton];
        unset($promues[$jeton]);
        mtx_promues_ecrire($promues);                    // livré une fois, on ne le garde pas
        $cmd = mtx_commande_par_ref($ref);
        return $cmd ? ['etat' => 'promue', 'commande' => $cmd] : ['etat' => 'inconnu'];
    }

    foreach (mtx_file_lire() as $i => $t) {
        if ($t['jeton'] === $jeton) return ['etat' => 'attente', 'position' => $i + 1];
    }
    return ['etat' => 'inconnu'];
}

/* Efface les tickets jamais réclamés depuis trop longtemps — sans ça la
   file grossirait indéfiniment avec des gens partis depuis longtemps. */
function mtx_file_nettoyer_fantomes(array $file): array {
    $limite = time() - MTX_FILE_TICKET_MINUTES * 60;
    return array_values(array_filter($file, fn($t) => strtotime((string) $t['arrivee_le']) >= $limite));
}

/* Donne jusqu'à $nb places aux premiers de la file. À appeler UNE FOIS
   déjà à l'intérieur d'un mtx_verrou() — ne prend pas le verrou lui-même,
   pour ne jamais l'imbriquer. */
function mtx_file_promouvoir(int $nb): void {
    if ($nb <= 0) return;

    $file = mtx_file_nettoyer_fantomes(mtx_file_lire());
    if (!$file) { mtx_file_ecrire($file); return; }

    $promues = mtx_promues_lire();
    $donnees = 0;

    while ($donnees < $nb && $file) {
        $actif = mtx_palier_actif();
        if (!$actif) break;                               // plus rien à donner, la file attendra une vraie ouverture

        /* Ne pas attribuer de place si le paiement est indisponible. */
        if (!mtx_paiement_pret()) break;

        $ticket = array_shift($file);
        $couleur = null;
        foreach (mtx_couleurs() as $c) if ($c['id'] === $ticket['couleur_id']) $couleur = $c;
        if (!$couleur) continue;                          // couleur disparue entre-temps : on passe au suivant

        $cmd = mtx_commande_construire($couleur, $actif);
        $promues[$ticket['jeton']] = $cmd['ref'];
        $donnees++;
    }

    mtx_file_ecrire($file);
    mtx_promues_ecrire($promues);
}
