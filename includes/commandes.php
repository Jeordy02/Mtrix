<?php
/* M'trix — les commandes de la prévente.

   Le parcours va à l'envers de l'habitude, et c'est voulu : on encaisse
   d'abord, on demande les coordonnées ensuite. Sur une prévente à paliers,
   chaque champ à remplir avant de payer est une place qui part à quelqu'un
   d'autre. Une fois l'argent encaissé, les questions ne coûtent plus rien.

   Règle qui ne bouge jamais : le prix est recalculé ici, côté serveur.
   Jamais celui qu'envoie le navigateur. */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/paiement.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/file.php';

const MTX_STATUTS = [
    'attente'     => 'en attente de paiement',
    'a_confirmer' => 'paiement à vérifier',
    'payee'       => 'payée',
    'livree'      => 'livrée',
    'annulee'     => 'annulée',
    'expiree'     => 'expirée — place relâchée',
];

/* Les états qui occupent une place sur la roue. */
function mtx_statut_compte(string $statut): bool {
    return in_array($statut, ['attente', 'a_confirmer', 'payee', 'livree'], true);
}

/* Une réservation non payée n'a plus de sens au-delà de 5 minutes : c'est
   large pour ouvrir FedaPay et payer, mais court pour ne pas faire
   patienter les autres pour rien si la personne a renoncé. */
const MTX_RESERVATION_MINUTES = 5;

function mtx_commandes(): array { return mtx_read('commandes', []); }
function mtx_commandes_write(array $rows): void { mtx_write('commandes', $rows); }

function mtx_ref(): string {
    do {
        $ref = 'MTX-' . strtoupper(bin2hex(random_bytes(8)));
    } while (mtx_commande_par_ref($ref) !== null);
    return $ref;
}

function mtx_commande_par_ref(string $ref): ?array {
    foreach (mtx_commandes() as $c) if (($c['ref'] ?? '') === $ref) return $c;
    return null;
}

/* Écrit une commande déjà existante. */
function mtx_commande_maj(string $ref, array $champs): ?array {
    $rows = mtx_commandes();
    $out = null;
    foreach ($rows as &$c) {
        if (($c['ref'] ?? '') !== $ref) continue;
        $c = array_merge($c, $champs);
        $out = $c;
        break;
    }
    unset($c);
    if ($out === null) return null;
    mtx_commandes_write($rows);
    return $out;
}

/* ---------------------------------------------------------------------
   1. La réservation — créée au clic sur « payer ». C'est CE clic qui
      gagne la place, pas le retour de paiement qui vient après :
      tant qu'il reste une place libre, on réserve tout de suite — deux
      personnes qui cliquent en même temps sur les 2 dernières places
      d'un palier l'obtiennent TOUTES LES DEUX, chacune la sienne.

      Ce n'est que quand TOUTES les places restantes sont déjà tenues
      par d'autres, en train de payer, qu'un nouveau clic doit patienter :
      ces réservations tenues expirent au bout de 5 minutes si la
      personne ne finalise pas, et la place repart aussitôt en jeu.

      Renvoie toujours ['ok'=>bool, 'commande'=>?array, 'raison'=>?string,
      'jusque'=>?string, 'jeton'=>?string, 'position'=>?int]. raison vaut :
        'patiente'   des places sont tenues par d'autres, ça peut se libérer
        'verrouille' ce palier a de la place mais l'admin ne l'a pas encore lancé
        'complet'    vraiment tout est vendu
        'sanspaiement' FedaPay n'est pas configuré
      Pour 'patiente' et 'verrouille', un ticket de file d'attente est pris
      dans le même mouvement — pas besoin d'un second aller-retour. */
function mtx_commande_creer(string $couleurId, ?string $jetonExistant = null): array {
    $echec = fn(string $raison, ?string $jusque = null, ?array $ticket = null) => [
        'ok' => false, 'commande' => null, 'raison' => $raison, 'jusque' => $jusque,
        'jeton' => $ticket['jeton'] ?? null, 'position' => $ticket['position'] ?? null,
    ];

    $couleur = null;
    foreach (mtx_couleurs() as $c) if ($c['id'] === $couleurId) $couleur = $c;
    if (!$couleur) return $echec('complet');

    mtx_commandes_purger();                             // libère d'abord les tentatives abandonnées, et promeut la file

    return mtx_verrou(function () use ($couleur, $couleurId, $jetonExistant, $echec) {
        $actif = mtx_palier_actif();                     // relu sous verrou : personne n'a pu changer l'état entre-temps

        if (!$actif) {
            /* D'abord : des retenues encore « en attente » occupent-elles
               une place ? Si oui, ça peut se libérer dans les minutes qui
               viennent — c'est le cas le plus favorable, on le préfère. */
            $jusque = null;
            foreach (mtx_commandes() as $c) {
                if (($c['statut'] ?? '') !== 'attente') continue;
                $fin = date('Y-m-d H:i:s', strtotime((string) $c['cree_le']) + MTX_RESERVATION_MINUTES * 60);
                if ($jusque === null || $fin < $jusque) $jusque = $fin;
            }
            if ($jusque) {
                $ticket = mtx_file_rejoindre_interne($couleurId, $jetonExistant);
                return $echec('patiente', $jusque, $ticket);
            }

            /* Sinon : reste-t-il un palier avec de la place, juste pas
               encore lancé par l'admin ? */
            if (mtx_palier_courant()) {
                $ticket = mtx_file_rejoindre_interne($couleurId, $jetonExistant);
                return $echec('verrouille', null, $ticket);
            }

            return $echec('complet');                    // vraiment tout est parti
        }

        if (!mtx_paiement_pret()) return $echec('sanspaiement');

        return ['ok' => true, 'commande' => mtx_commande_construire($couleur, $actif),
                'raison' => null, 'jusque' => null, 'jeton' => null, 'position' => null];
    });
}

/* Construit et enregistre une commande pour un coloris et un palier déjà
   choisis. Toujours à appeler depuis l'intérieur d'un mtx_verrou() — sert
   au clic direct ci-dessus comme à la promotion depuis la file d'attente. */
function mtx_commande_construire(array $couleur, array $actif): array {
    $cmd = [
        'ref'      => mtx_ref(),
        'couleur'  => $couleur['nom'],
        'couleur_id' => $couleur['id'],
        'prix'     => (int) $actif['prix'],          // prix serveur, point final
        'palier'   => (int) $actif['n'],
        'place'    => mtx_place_prendre(),           // attribuée MAINTENANT
        'statut'   => 'attente',
        'nom'      => '',
        'tel'      => '',
        'email'    => '',
        'lieu'     => '',
        'note'     => '',
        'complete' => false,
        'transac'  => null,
        'mail_envoye' => false,
        'cree_le'  => date('Y-m-d H:i:s'),
        'paye_le'  => null,
    ];

    $rows = mtx_commandes();
    $rows[] = $cmd;
    mtx_commandes_write($rows);
    return $cmd;
}

/* Libère les places retenues par des réservations jamais payées, et les
   donne directement au premier de la file d'attente plutôt que de les
   remettre en jeu pour que tout le monde retente sa chance en même temps.
   La ligne reste dans l'historique, marquée « expirée » — utile pour dire
   clairement à son propriétaire ce qui s'est passé s'il revient tard. */
function mtx_commandes_purger(): void {
    $limite = time() - MTX_RESERVATION_MINUTES * 60;
    mtx_verrou(function () use ($limite) {
        $rows = mtx_commandes();
        $liberees = 0;
        foreach ($rows as &$c) {
            if (($c['statut'] ?? '') === 'attente' && strtotime((string) ($c['cree_le'] ?? '')) < $limite) {
                $c['statut'] = 'expiree';
                $c['motif_expiration'] = 'delai';
                $liberees++;
            }
        }
        unset($c);
        if ($liberees === 0) return;

        mtx_commandes_write($rows);
        mtx_set_vendues(mtx_vendues() - $liberees);   // les places abandonnées repartent en jeu
        mtx_file_promouvoir($liberees);                // ...directement données à qui patiente depuis le plus longtemps
    });
}

/* ---------------------------------------------------------------------
   2. Le paiement — confirme ou libère une place déjà attribuée.
   --------------------------------------------------------------------- */

/* Attribue le numéro de place et avance la roue.
   Toujours à appeler depuis l'intérieur d'un mtx_verrou(). */
function mtx_place_prendre(): int {
    $place = mtx_vendues() + 1;
    mtx_set_vendues($place);
    return $place;
}

/* Confirme une commande après vérification du paiement auprès de FedaPay.

   $montant est celui que FedaPay confirme avoir encaissé : s'il ne
   correspond pas au prix de la commande, on refuse. Sinon il suffirait de
   payer 100 F pour réserver une paire à 7 000.

   Renvoie ['ok' => bool, 'commande' => ?array, 'erreur' => ?string] */
function mtx_commande_confirmer(string $ref, string $transac, ?int $montant = null): array {
    $c = mtx_commande_par_ref($ref);
    if (!$c) return ['ok' => false, 'commande' => null, 'erreur' => 'Commande introuvable.'];

    if ($montant === null || $montant !== (int) $c['prix']) {
        return ['ok' => false, 'commande' => null,
                'erreur' => 'Le montant confirmé par FedaPay ne correspond pas au prix de la commande.'];
    }

    /* Déjà confirmée : on renvoie la même, sans reprendre une place. */
    if (in_array($c['statut'], ['payee', 'livree'], true)) {
        return ['ok' => true, 'commande' => $c, 'erreur' => null];
    }
    if ($c['statut'] === 'a_confirmer') {
        $maj = mtx_commande_maj($ref, ['statut' => 'payee', 'transac' => $transac]);
        return ['ok' => true, 'commande' => $maj, 'erreur' => null];   // place déjà prise
    }
    if ($c['statut'] === 'annulee') {
        return ['ok' => false, 'commande' => null, 'erreur' => 'Cette commande a été annulée.'];
    }
    /* La place a déjà été gagnée au clic sur « payer » ($c['place'] existe
       depuis la création) : ici on ne fait que confirmer l'argent reçu. */
    $maj = mtx_commande_maj($ref, [
        'statut'  => 'payee',
        'paye_le' => date('Y-m-d H:i:s'),
        'transac' => $transac,
    ]);
    return ['ok' => true, 'commande' => $maj, 'erreur' => null];
}

function mtx_commande_reservation_liberer(string $ref, string $motif): bool {
    return mtx_verrou(function () use ($ref, $motif) {
        $commande = mtx_commande_par_ref($ref);
        if (!$commande || ($commande['statut'] ?? '') !== 'attente') return false;

        $maj = mtx_commande_maj($ref, [
            'statut' => 'expiree',
            'motif_expiration' => $motif,
        ]);
        if (!$maj) return false;

        mtx_set_vendues(mtx_vendues() - 1);
        mtx_file_promouvoir(1);
        return true;
    });
}

/* ---------------------------------------------------------------------
   3. Les coordonnées — demandées après, quand l'argent est déjà rentré.
   --------------------------------------------------------------------- */
function mtx_verifier_livraison(array $p): array {
    $err = [];
    if (mb_strlen(trim($p['nom'] ?? '')) < 2) $err['nom'] = 'Ton nom, qu\'on sache à qui remettre la paire.';

    $tel = preg_replace('/[^0-9+]/', '', $p['tel'] ?? '');
    if (mb_strlen($tel) < 8) $err['tel'] = 'Un numéro joignable sur WhatsApp.';

    $mail = trim($p['email'] ?? '');
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) $err['email'] = 'Une adresse valide, ta confirmation part dessus.';

    if (mb_strlen(trim($p['lieu'] ?? '')) < 3) $err['lieu'] = 'Ton quartier et un repère.';

    return $err;
}

/* Enregistre les coordonnées puis envoie la confirmation par mail.
   Un mail qui échoue ne fait jamais échouer la commande : elle est payée. */
function mtx_commande_completer(string $ref, array $p): ?array {
    $c = mtx_commande_par_ref($ref);
    if (!$c || !mtx_statut_compte((string) $c['statut'])) return null;

    $c = mtx_commande_maj($ref, [
        'nom'      => mb_substr(trim($p['nom'] ?? ''), 0, 60),
        'tel'      => mb_substr(trim($p['tel'] ?? ''), 0, 25),
        'email'    => mb_substr(trim($p['email'] ?? ''), 0, 120),
        'lieu'     => mb_substr(trim($p['lieu'] ?? ''), 0, 140),
        'note'     => mb_substr(trim($p['note'] ?? ''), 0, 250),
        'complete' => true,
    ]);
    if (!$c) return null;

    if (empty($c['mail_envoye'])) {
        $envoi = mtx_mail_commande($c);
        if ($envoi['ok']) $c = mtx_commande_maj($ref, ['mail_envoye' => true]) ?? $c;
    }
    return $c;
}

/* ---------------------------------------------------------------------
   4. Gestion depuis la page privée
   --------------------------------------------------------------------- */

/* Change le statut. Annuler une commande payée rend sa place. */
function mtx_commande_statut(string $ref, string $statut): bool {
    if (!isset(MTX_STATUTS[$statut])) return false;
    $c = mtx_commande_par_ref($ref);
    if (!$c) return false;

    $avant    = (string) $c['statut'];
    $comptait = mtx_statut_compte($avant);
    $compte   = mtx_statut_compte($statut);

    $champs = ['statut' => $statut];
    if ($compte && empty($c['paye_le'])) $champs['paye_le'] = date('Y-m-d H:i:s');

    /* Cas normal : la place existe déjà depuis le clic initial, ces
       statuts ne font que la confirmer. Le seul cas qui bouge le compteur
       est une réactivation après annulation — sous verrou, comme toute
       écriture du compteur. */
    if (!$comptait && $compte)      $champs['place'] = mtx_verrou(fn() => mtx_place_prendre());
    elseif ($comptait && !$compte) { mtx_verrou(fn() => mtx_set_vendues(mtx_vendues() - 1)); $champs['place'] = null; }

    $c = mtx_commande_maj($ref, $champs);
    if (!$c) return false;

    /* Le mail de félicitations ne part qu'une fois le paiement confirmé :
       on ne félicite personne pour un virement qu'on n'a pas encore vu. */
    if ($statut === 'payee' && !empty($c['complete']) && empty($c['mail_envoye'])) {
        if (mtx_mail_commande($c)['ok']) mtx_commande_maj($ref, ['mail_envoye' => true]);
    }
    return true;
}

function mtx_commande_supprimer(string $ref): void {
    mtx_verrou(function () use ($ref) {
        $garde = [];
        foreach (mtx_commandes() as $c) {
            if (($c['ref'] ?? '') === $ref) {
                if (mtx_statut_compte((string) ($c['statut'] ?? ''))) {
                    mtx_set_vendues(mtx_vendues() - 1);      // la place est rendue
                }
                continue;
            }
            $garde[] = $c;
        }
        mtx_commandes_write($garde);
    });
}

function mtx_compte(string $statut): int {
    $n = 0;
    foreach (mtx_commandes() as $c) if (($c['statut'] ?? '') === $statut) $n++;
    return $n;
}

/* Recette encaissée d'après les commandes réellement payées. */
function mtx_encaisse(): int {
    $t = 0;
    foreach (mtx_commandes() as $c) {
        if (in_array($c['statut'] ?? '', ['payee', 'livree'], true)) $t += (int) $c['prix'];
    }
    return $t;
}

/* Les anciennes commandes déclarées manuellement avant le passage à FedaPay. */
function mtx_a_verifier(): array {
    $out = [];
    foreach (mtx_commandes() as $c) if (($c['statut'] ?? '') === 'a_confirmer') $out[] = $c;
    return $out;
}

/* Commandes réellement payées dont on n'a toujours pas les coordonnées :
   à rappeler. Une simple réservation en attente ('attente') n'a rien
   d'anormal — le client est peut-être juste en train de payer. */
function mtx_a_relancer(): array {
    $out = [];
    foreach (mtx_commandes() as $c) {
        if (in_array($c['statut'] ?? '', ['payee', 'livree'], true) && empty($c['complete'])) $out[] = $c;
    }
    return $out;
}

/* Photo à montrer pour un coloris : la version taguée si elle existe. */
function mtx_photo_taguee(array $couleur): string {
    $tag = 'mtrix-' . $couleur['id'] . '-logo.jpg';
    return is_file(__DIR__ . '/../assets/' . $tag) ? $tag : $couleur['fichier'];
}

/* ---------------------------------------------------------------------
   5. Accès à la page privée
   --------------------------------------------------------------------- */

function mtx_admin_a_configurer(): bool {
    $d = mtx_read('admin', []);
    return empty($d['hash']);
}

function mtx_admin_definir(string $mdp): void {
    mtx_write('admin', ['hash' => password_hash($mdp, PASSWORD_DEFAULT)]);
}

function mtx_admin_connecter(string $mdp): bool {
    $d = mtx_read('admin', []);
    if (empty($d['hash']) || !password_verify($mdp, $d['hash'])) return false;
    $_SESSION['mtx_admin'] = true;
    return true;
}

function mtx_admin_connecte(): bool { return !empty($_SESSION['mtx_admin']); }
function mtx_admin_sortir(): void { unset($_SESSION['mtx_admin']); }
