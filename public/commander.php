<?php
/* M'trix — la page de réservation et de paiement FedaPay.

   Quatre écrans :
     choix      un coloris, un prix, on paie.
     paiement_attente le retour est reçu, FedaPay n'a pas encore confirmé.
     livraison  le paiement a été confirmé par l'API FedaPay.
     merci      tout est fait, la carte de place à poster. */

require_once __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../includes/commandes.php';

$couleurs = mtx_couleurs();
$actif    = mtx_palier_actif();
$suivant  = mtx_prochain_prix();
$mode     = mtx_paiement_mode();
$ecran    = 'choix';
$commande = null;
$erreurs  = [];
$alerte   = '';

/* ---------------------------------------------------------------------
   Point d'entrée AJAX : le clic sur « payer » réserve une référence et
   renvoie le montant. Le navigateur ne choisit jamais le prix lui-même.
   --------------------------------------------------------------------- */
if (($_GET['reserver'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!mtx_paiement_pret()) {
        http_response_code(503);
        echo json_encode(['ok' => false, 'erreur' => 'Le paiement en ligne est momentanément indisponible. Réessaie plus tard.']);
        exit;
    }
    $jetonEnvoye = trim((string) ($_POST['jeton'] ?? '')) ?: null;
    $r = mtx_commande_creer((string) ($_POST['couleur'] ?? ''), $jetonEnvoye);
    if (!$r['ok']) {
        if ($r['raison'] === 'patiente') {
            echo json_encode(['ok' => false, 'patiente' => true, 'jusque' => $r['jusque'],
                'jeton' => $r['jeton'], 'position' => $r['position'],
                'erreur' => 'Il ne reste plus de place libre à l\'instant : d\'autres sont en train de payer. Ça peut se débloquer d\'une minute à l\'autre.']);
        } elseif ($r['raison'] === 'verrouille') {
            echo json_encode(['ok' => false, 'patiente' => true, 'verrouille' => true,
                'jeton' => $r['jeton'], 'position' => $r['position'],
                'erreur' => 'Ce palier n\'est pas encore lancé. Tu es dans la file : dès qu\'on ouvre, tu passes automatiquement.']);
        } elseif ($r['raison'] === 'sanspaiement') {
            http_response_code(503);
            echo json_encode(['ok' => false, 'erreur' => 'Le paiement en ligne est momentanément indisponible. Réessaie plus tard.']);
        } else {
            echo json_encode(['ok' => false, 'erreur' => 'La prévente vient de se remplir.']);
        }
        exit;
    }
    $cmd = $r['commande'];
    $paiement = mtx_fedapay_demarrer($cmd);
    if (!$paiement['ok']) {
        mtx_commande_reservation_liberer((string) $cmd['ref'], 'erreur_fedapay');
        http_response_code(502);
        echo json_encode(['ok' => false, 'erreur' => $paiement['erreur']]);
        exit;
    }
    echo json_encode(['ok' => true, 'url' => $paiement['url']]);
    exit;
}

/* ---------------------------------------------------------------------
   Sondage de la file d'attente : le navigateur revient régulièrement
   demander « c'est bon ? » avec son jeton. On ne pousse rien nous-mêmes,
   c'est lui qui vient chercher la réponse.
   --------------------------------------------------------------------- */
if (($_GET['attente'] ?? '') !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    mtx_commandes_purger();                              // l'occasion de faire avancer la file si quelque chose a expiré
    $jeton = trim((string) ($_POST['jeton'] ?? ''));
    $e = $jeton !== '' ? mtx_file_etat($jeton) : ['etat' => 'inconnu'];

    if ($e['etat'] === 'promue') {
        $cmd = $e['commande'];
        $paiement = mtx_fedapay_demarrer($cmd);
        if (!$paiement['ok']) {
            mtx_commande_reservation_liberer((string) $cmd['ref'], 'erreur_fedapay');
            http_response_code(502);
            echo json_encode(['ok' => false, 'erreur' => $paiement['erreur']]);
        } else {
            echo json_encode(['ok' => true, 'url' => $paiement['url']]);
        }
    } elseif ($e['etat'] === 'attente') {
        echo json_encode(['ok' => false, 'patiente' => true, 'position' => $e['position']]);
    } else {
        echo json_encode(['ok' => false, 'expire' => true,
            'erreur' => 'Ton ticket n\'est plus valide. Réessaie.']);
    }
    exit;
}

/* ---------------------------------------------------------------------
   Retour de FedaPay : le statut réel est vérifié côté serveur avec l'API.
   --------------------------------------------------------------------- */
if (($_GET['ref'] ?? '') !== '') {
    $ref     = (string) $_GET['ref'];
    $c = mtx_commande_par_ref($ref);

    if (!$c) {
        $alerte = 'Cette référence est introuvable.';
    } elseif (in_array($c['statut'], ['payee', 'livree'], true)) {
        $commande = $c;
        $ecran = !empty($c['complete']) ? 'merci' : 'livraison';
    } elseif ($c['statut'] === 'a_confirmer') {
        $commande = $c;
        $ecran = 'attente';
    } elseif ($mode === 'api' && !empty($c['transac']) && $c['statut'] === 'attente') {
        $v = mtx_fedapay_verifier($c);
        if (!$v['ok']) {
            if (!empty($v['terminal'])) {
                mtx_commande_reservation_liberer($ref, 'paiement_refuse');
                $commande = mtx_commande_par_ref($ref);
                $ecran = 'expire';
                $alerte = 'FedaPay indique que le paiement n’a pas abouti. Tu peux réessayer.';
            } else {
                $commande = $c;
                $ecran = 'paiement_attente';
                $alerte = $v['erreur'] ?: 'FedaPay n’a pas encore confirmé ce paiement (état : ' . $v['statut'] . ').';
            }
        } else {
            $r = mtx_commande_confirmer($ref, (string) $c['transac'], $v['montant']);
            if ($r['ok']) { $commande = $r['commande']; $ecran = 'livraison'; }
            else          { $commande = $c; $ecran = 'paiement_attente'; $alerte = $r['erreur']; }
        }
    } elseif ($c['statut'] === 'attente') {
        $commande = $c;
        $ecran = 'paiement_attente';
    } elseif ($c['statut'] === 'expiree') {
        $commande = $c;
        $ecran = 'expire';                             // trop tard : sa place a été donnée à la file d'attente
    }
}

/* Les coordonnées, après paiement confirmé (mode API uniquement). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'livraison') {
    $ref = (string) ($_POST['ref'] ?? '');
    $commande = mtx_commande_par_ref($ref);
    if (!$commande) {
        $alerte = 'Référence introuvable.';
    } else {
        $erreurs = mtx_verifier_livraison($_POST);
        if ($erreurs) {
            $ecran = 'livraison';
        } else {
            $commande = mtx_commande_completer($ref, $_POST) ?? $commande;
            $ecran = 'merci';
        }
    }
}

$choisie = $_POST['couleur'] ?? ($_GET['c'] ?? $couleurs[0]['id']);
$place   = (int) ($commande['place'] ?? 0);
$total   = mtx_total_places();

/* Combien il a pris d'avance sur le suivant — c'est ça qu'il retient. */
$avance = ($commande && $suivant) ? max(0, $suivant - (int) $commande['prix']) : 0;

$titres = ['choix' => 'Réserver', 'paiement_attente' => 'Paiement en cours', 'attente' => 'Vérification',
           'expire' => 'Temps écoulé', 'livraison' => 'Ta place', 'merci' => 'Ta place'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titres[$ecran] ?? 'Réserver') ?> — M'trix</title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;500;600&family=Chakra+Petch:wght@500;600;700&display=swap">
<link rel="stylesheet" href="assets/site.css?v=<?= @filemtime(__DIR__ . '/assets/site.css') ?>">
<link rel="stylesheet" href="assets/commande.css?v=<?= @filemtime(__DIR__ . '/assets/commande.css') ?>">
</head>
<body class="cmd-page<?= $ecran !== 'choix' ? ' cmd-page--fin' : '' ?>">

<header class="bar bar--mince">
  <a href="./" class="bar__logo"><img src="assets/mtrix-logo.jpg" alt="M&rsquo;trix"></a>
  <a class="bar__retour" href="./">Retour au site</a>
</header>

<?php if ($alerte): ?>
  <p class="alerte" role="alert"><?= h($alerte) ?></p>
<?php endif; ?>

<?php /* ================================================================
        ÉCRAN 1 — le choix, puis on paie.
        ================================================================ */ ?>
<?php if ($ecran === 'choix'): ?>

  <main class="res">
    <!-- La paire, en grand, à gauche -->
    <div class="res__vue">
      <span class="res__fond" aria-hidden="true"><?= $actif ? number_format($actif['prix'], 0, ',', ' ') : '—' ?></span>
      <?php foreach ($couleurs as $c): ?>
        <img class="res__photo<?= $choisie === $c['id'] ? ' est-vue' : '' ?>"
             data-pour="<?= h($c['id']) ?>"
             src="assets/<?= h(mtx_photo_taguee($c)) ?>"
             alt="M&rsquo;trix <?= h($c['nom']) ?>"
             <?= $choisie === $c['id'] ? '' : 'loading="lazy"' ?>>
      <?php endforeach; ?>
      <p class="res__leg"><span data-nom-couleur><?= h($couleurs[0]['nom']) ?></span></p>
    </div>

    <!-- Le panneau de réservation, à droite -->
    <div class="res__panneau">

      <?php if ($actif): ?>
        <p class="res__eyebrow">Prévente &mdash; <?= $total ?> pièces, pas une de plus</p>

        <div class="res__prix">
          <b><?= number_format($actif['prix'], 0, ',', ' ') ?></b>
          <span>FCFA</span>
        </div>

        <p class="res__reste">
          <i aria-hidden="true"></i>
          <?php if ($actif['restant'] === 1): ?>
            <strong>Dernière place</strong> à ce prix.
          <?php else: ?>
            <strong><?= $actif['restant'] ?> places</strong> restent à ce prix.
          <?php endif; ?>
          <?php if ($suivant): ?>
            Ensuite ça passe à <?= number_format($suivant, 0, ',', ' ') ?>.
          <?php else: ?>
            Après, c&rsquo;est le tarif normal.
          <?php endif; ?>
        </p>

        <!-- L'échelle des paliers : on voit d'un coup où on se situe -->
        <ol class="echelle" aria-label="Les paliers de la prévente">
          <?php foreach (mtx_paliers() as $p): ?>
            <li class="echelle__pas est-<?= h($p['statut']) ?>">
              <span class="echelle__prix"><?= number_format($p['prix'], 0, ',', ' ') ?></span>
              <span class="echelle__barre"><i style="--part:<?= $p['places'] ? round(100 * $p['pris'] / $p['places']) : 0 ?>%"></i></span>
              <span class="echelle__etat"><?php
                echo $p['statut'] === 'epuise' ? 'parti'
                   : ($p['statut'] === 'encours' ? $p['restant'] . ' libre' . ($p['restant'] > 1 ? 's' : '') : $p['places'] . ' pièces');
              ?></span>
            </li>
          <?php endforeach; ?>
          <li class="echelle__pas est-normal">
            <span class="echelle__prix">Normal</span>
            <span class="echelle__barre"></span>
            <span class="echelle__etat">après</span>
          </li>
        </ol>

        <!-- Le coloris -->
        <fieldset class="teintes">
          <legend>Le coloris</legend>
          <div class="teintes__rang">
            <?php foreach ($couleurs as $c): ?>
              <label class="teintes__opt" data-clair="<?= $c['id'] === 'blanc' ? '1' : '0' ?>">
                <input type="radio" name="couleur" value="<?= h($c['id']) ?>"
                       data-nom="<?= h($c['nom']) ?>" <?= $choisie === $c['id'] ? 'checked' : '' ?>>
                <span class="teintes__puce" style="background:<?= h($c['puce']) ?>"></span>
                <span class="teintes__txt"><?= h($c['nom']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <!-- Le paiement, selon ce qui est branché -->
        <?php if ($mode === 'api'): ?>
          <button class="payer" type="button" id="btnPayer">
            <span class="payer__txt">Payer et bloquer ma place</span>
            <span class="payer__prix"><?= number_format($actif['prix'], 0, ',', ' ') ?> F</span>
          </button>
          <p class="payer__sous">
            Paiement s&eacute;curis&eacute; par FedaPay. Tu seras redirig&eacute; vers leur page de paiement.
          </p>

          <!-- File d'attente : cachée tant que personne ne patiente, JS s'en charge -->
          <div id="zoneAttente" class="file" hidden>
            <span class="file__pastille" aria-hidden="true"></span>
            <p data-attente-texte>Patiente un instant&hellip;</p>
          </div>

        <?php else: ?>
          <p class="payer__sous">
            <b>Le paiement en ligne est momentan&eacute;ment indisponible.</b>
            R&eacute;essaie plus tard ou contacte-nous sur WhatsApp.
          </p>
        <?php endif; ?>

        <p class="res__fine">
          Remise en main propre le <b><?= MTX_LANCEMENT ?></b> &agrave; Cotonou.
        </p>

      <?php else: ?>
        <p class="res__eyebrow">Prévente</p>
        <div class="res__prix res__prix--fin"><b>Complet</b></div>
        <p class="res__reste">Les <?= $total ?> pi&egrave;ces de la pr&eacute;vente sont parties.
          Le tarif normal s&rsquo;applique d&eacute;sormais.</p>
        <a class="payer" href="https://wa.me/<?= MTX_WHATSAPP ?>?text=<?= rawurlencode('Bonjour, la prevente est complete. Je veux une M\'trix au prix normal.') ?>"
           target="_blank" rel="noopener">
          <span class="payer__txt">Nous &eacute;crire sur WhatsApp</span>
        </a>
      <?php endif; ?>
    </div>
  </main>

<?php /* ================================================================
        ÉCRAN « paiement_attente » — FedaPay n'a pas encore confirmé.
        ================================================================ */ ?>
<?php elseif ($ecran === 'paiement_attente'): ?>

  <main class="fin">
    <section class="bravo">
      <p class="bravo__eyebrow bravo__eyebrow--attend">Paiement en cours de vérification</p>
      <h1 class="bravo__titre">On vérifie ton paiement.</h1>
      <p class="bravo__dit">
        Ta place <b><?= h($commande['couleur']) ?></b> &agrave;
        <b><?= number_format($commande['prix'], 0, ',', ' ') ?> F</b> reste r&eacute;serv&eacute;e.
        <span>FedaPay peut mettre quelques instants &agrave; confirmer la transaction. Recharge cette page dans un instant.</span>
      </p>
    </section>
    <section class="ou" style="border-top:0;padding-top:0;text-align:center">
      <a class="payer" href="commander.php?ref=<?= rawurlencode((string) $commande['ref']) ?>">
        <span class="payer__txt">V&eacute;rifier &agrave; nouveau</span>
      </a>
      <p class="payer__sous" style="text-align:center">R&eacute;f&eacute;rence de commande : <b><?= h($commande['ref']) ?></b></p>
    </section>
  </main>

<?php /* Les anciennes commandes déclarées manuellement restent consultables. */ ?>
<?php elseif ($ecran === 'attente'): ?>

  <main class="fin">
    <section class="bravo">
      <p class="bravo__eyebrow bravo__eyebrow--attend">Place retenue &mdash; en vérification</p>
      <p class="bravo__place bravo__place--attend"><?= sprintf('%02d', $place) ?><i>/<?= $total ?></i></p>
      <h1 class="bravo__titre">Presque bon.</h1>
      <p class="bravo__dit">
        <?= h(explode(' ', trim((string) $commande['nom']))[0] ?: 'Salut') ?>, ta place
        <b><?= h($commande['couleur']) ?></b> &agrave; <b><?= number_format($commande['prix'], 0, ',', ' ') ?> F</b>
        est retenue pendant la v&eacute;rification de ton paiement.
        <span>Cette ancienne commande attend une v&eacute;rification manuelle. Garde cette r&eacute;f&eacute;rence si tu nous contactes.</span>
      </p>
    </section>
    <section class="ou" style="border-top:0;padding-top:0;text-align:center">
      <a class="payer" target="_blank" rel="noopener"
         href="https://wa.me/<?= MTX_WHATSAPP ?>?text=<?= rawurlencode('Bonjour, je viens de payer ma M\'trix — ref ' . $commande['ref'] . ($commande['transac'] ? (' — transaction ' . $commande['transac']) : '') . '.') ?>">
        <span class="payer__txt">Envoyer la preuve sur WhatsApp</span>
      </a>
      <p class="payer__sous" style="text-align:center">R&eacute;f&eacute;rence &agrave; garder : <b><?= h($commande['ref']) ?></b></p>
    </section>
  </main>

<?php /* ================================================================
        ÉCRAN « expire » — les 5 minutes sont passées sans confirmation :
        la place est repartie, honnêtement annoncé plutôt qu'une erreur.
        ================================================================ */ ?>
<?php elseif ($ecran === 'expire'): ?>

  <main class="fin">
    <section class="bravo">
      <p class="bravo__eyebrow" style="color:#8C8D93">D&eacute;sol&eacute;</p>
      <h1 class="bravo__titre">Le temps &eacute;tait &eacute;coul&eacute;.</h1>
      <p class="bravo__dit">
        Ta r&eacute;servation <b><?= h($commande['couleur']) ?></b> &agrave;
        <b><?= number_format($commande['prix'], 0, ',', ' ') ?> F</b> n&rsquo;a pas &eacute;t&eacute; confirm&eacute;e.
        <span><?= ($commande['motif_expiration'] ?? '') === 'paiement_refuse'
          ? 'FedaPay a refusé ou annulé le paiement. La place est libérée ; tu peux réessayer au prix actuel.'
          : 'Le délai de réservation est écoulé. La place est repartie — donnée directement'
        ?>
        &agrave; la prochaine personne qui l&rsquo;attendait.</span>
      </p>
    </section>
    <section class="ou" style="border-top:0;padding-top:0;text-align:center">
      <a class="payer" href="./#prevente">
        <span class="payer__txt">R&eacute;essayer au prix actuel</span>
      </a>
    </section>
  </main>

<?php /* ================================================================
        ÉCRAN « livraison » — mode api : payé, on demande où livrer.
        ================================================================ */ ?>
<?php elseif ($ecran === 'livraison'): ?>

  <main class="fin fin--livraison">

    <div class="livr__hero">
      <div class="livr__photo">
        <img src="assets/mtrix-<?= h(mb_strtolower($commande['couleur'])) ?>.jpg" alt="M'trix <?= h($commande['couleur']) ?>" loading="eager">
      </div>
      <div class="livr__haut">
        <p class="livr__eyebrow">Félicitations !</p>
        <h1 class="livr__titre">Elle est <span>à toi.</span></h1>
        <p class="livr__couleur"><?= h($commande['couleur']) ?></p>
        <div class="livr__place"><?= sprintf('%02d', $place) ?><span>/<?= $total ?></span></div>
        <p class="livr__prix">
          Tu as payé <b><?= number_format($commande['prix'], 0, ',', ' ') ?> F</b>
          <?php if ($avance > 0): ?>
            <span>Tu as <?= number_format($avance, 0, ',', ' ') ?> F d&rsquo;avance sur la suivante.</span>
          <?php else: ?>
            <span>Le dernier prix de la prévente.</span>
          <?php endif; ?>
        </p>
      </div>
    </div>

    <section class="livr__form-zone">
      <h2>Il reste une chose</h2>
      <p class="livr__form-sous">Où et comment on te livre ? Ta place <b><?= h($commande['ref']) ?></b> est d'ores et déjà bloquée — personne ne peut plus te la prendre.</p>

      <form method="post" class="livr__form" novalidate>
        <input type="hidden" name="action" value="livraison">
        <input type="hidden" name="ref" value="<?= h($commande['ref']) ?>">

        <div class="livr__grid">
          <label class="livr__champ">
            <span class="livr__label">Ton nom</span>
            <input type="text" name="nom" value="<?= h($_POST['nom'] ?? $commande['nom']) ?>" autocomplete="name" autofocus required>
            <?php if (!empty($erreurs['nom'])): ?><span class="livr__err"><?= h($erreurs['nom']) ?></span><?php endif; ?>
          </label>
          <label class="livr__champ">
            <span class="livr__label">WhatsApp</span>
            <input type="tel" name="tel" value="<?= h($_POST['tel'] ?? $commande['tel']) ?>" placeholder="01 46 02 56 58" autocomplete="tel" required>
            <?php if (!empty($erreurs['tel'])): ?><span class="livr__err"><?= h($erreurs['tel']) ?></span><?php endif; ?>
          </label>
        </div>

        <label class="livr__champ livr__champ--full">
          <span class="livr__label">Email <small>— ta confirmation part dessus</small></span>
          <input type="email" name="email" value="<?= h($_POST['email'] ?? $commande['email']) ?>" autocomplete="email" required>
          <?php if (!empty($erreurs['email'])): ?><span class="livr__err"><?= h($erreurs['email']) ?></span><?php endif; ?>
        </label>

        <label class="livr__champ livr__champ--full">
          <span class="livr__label">Quartier et repère</span>
          <input type="text" name="lieu" value="<?= h($_POST['lieu'] ?? $commande['lieu']) ?>" placeholder="Fidjrosse, en face de la pharmacie" required>
          <?php if (!empty($erreurs['lieu'])): ?><span class="livr__err"><?= h($erreurs['lieu']) ?></span><?php endif; ?>
        </label>

        <label class="livr__champ livr__champ--full">
          <span class="livr__label">Un mot <small>— facultatif</small></span>
          <textarea name="note" rows="2" placeholder="Ex. vendredi après 14h, ou samedi matin"><?= h($_POST['note'] ?? $commande['note']) ?></textarea>
          <?php if (!empty($erreurs['note'])): ?><span class="livr__err"><?= h($erreurs['note']) ?></span><?php endif; ?>
        </label>

        <button class="livr__btn" type="submit">Terminer la commande</button>
      </form>
    </section>
  </main>

<?php /* ================================================================
        ÉCRAN « merci » — tout est fait. Sa carte de place, à poster.
        ================================================================ */ ?>
<?php else: ?>

  <main class="fin">
    <section class="bravo bravo--court">
      <p class="bravo__eyebrow">C&rsquo;est bouclé</p>
      <h1 class="bravo__titre">On se voit le <?= MTX_LANCEMENT ?>.</h1>
      <p class="bravo__dit">
        <?= h(explode(' ', trim((string) $commande['nom']))[0] ?: 'Salut') ?>, ta confirmation part sur
        <b><?= h($commande['email']) ?></b>.
        <span>On t&rsquo;&eacute;crit sur WhatsApp pour l&rsquo;heure et le point de rendez-vous.</span>
      </p>
    </section>

    <!-- La carte de place : faite pour la story, pas pour l'archive -->
    <section class="carte-zone">
      <p class="carte-zone__dit">Capture cet &eacute;cran et poste-le. C&rsquo;est ta place.</p>

      <div class="carte">
        <span class="carte__trame" aria-hidden="true"></span>
        <div class="carte__haut">
          <span class="carte__marque">M&rsquo;TRIX</span>
          <span class="carte__an"><?= MTX_LANCEMENT ?></span>
        </div>

        <p class="carte__num"><?= sprintf('%02d', $place) ?><i>/<?= $total ?></i></p>
        <p class="carte__mot">Place verrouill&eacute;e</p>

        <div class="carte__bas">
          <span><i>Coloris</i><b><?= h($commande['couleur']) ?></b></span>
          <span><i>Prix bloqu&eacute;</i><b><?= number_format($commande['prix'], 0, ',', ' ') ?> F</b></span>
          <span><i>R&eacute;f.</i><b><?= h($commande['ref']) ?></b></span>
        </div>
      </div>

      <div class="carte-zone__liens">
        <a class="payer" target="_blank" rel="noopener"
           href="https://wa.me/<?= MTX_WHATSAPP ?>?text=<?= rawurlencode('Bonjour, je suis ' . $commande['nom'] . ' — commande ' . $commande['ref'] . ', place ' . $place . '/' . $total . '.') ?>">
          <span class="payer__txt">Nous &eacute;crire sur WhatsApp</span>
        </a>
        <a class="lien-nu" href="https://instagram.com/<?= MTX_INSTAGRAM ?>" target="_blank" rel="noopener">Suivre M&rsquo;trix</a>
        <a class="lien-nu" href="./">Retour au site</a>
      </div>
    </section>
  </main>

<?php endif; ?>

<footer class="foot">
  <span>M&rsquo;trix</span>
  <a href="https://instagram.com/<?= MTX_INSTAGRAM ?>" target="_blank" rel="noopener">Instagram</a>
  <span class="foot__year"><?= date('Y') ?></span>
</footer>

<script src="assets/commande.js?v=<?= @filemtime(__DIR__ . '/assets/commande.js') ?>"></script>
</body>
</html>
