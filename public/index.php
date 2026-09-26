<?php
require_once __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/roue.php';
$couleurs = mtx_couleurs();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>M'trix</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;500;600&family=Chakra+Petch:wght@500;600&display=swap">
<link rel="stylesheet" href="assets/site.css?v=<?= @filemtime(__DIR__ . '/assets/site.css') ?>">
</head>
<body class="intro-on">

<!-- Entrée : le nom se décode, puis l'écran s'ouvre -->
<div class="intro" id="intro">
  <span class="intro__panneau intro__panneau--h" aria-hidden="true"></span>
  <span class="intro__panneau intro__panneau--b" aria-hidden="true"></span>
  <div class="intro__coeur">
    <h1 class="intro__mot" id="introMot" aria-label="M&rsquo;trix"></h1>
    <span class="intro__trait" aria-hidden="true"></span>
  </div>
  <button type="button" class="intro__skip" id="introSkip">Passer</button>
</div>

<header class="bar">
  <a href="." class="bar__logo"><img src="assets/mtrix-logo.jpg" alt="M&rsquo;trix"></a>
  <nav class="bar__nav">
    <?php $actifNav = mtx_palier_actif(); ?>
    <a href="#prevente" class="bar__flash"><i></i>Prévente <b><?= $actifNav ? h(fcfa($actifNav['prix'])) : 'bientôt' ?></b></a>
    <a href="#coloris">Coloris</a>
    <a href="https://wa.me/<?= MTX_WHATSAPP ?>" target="_blank" rel="noopener">Nous écrire</a>
  </nav>
</header>

<main>

  <section class="hero">
    <h1 class="hero__title">
      <span class="ln anim anim--2"><span>Aura</span></span>
      <span class="ln anim anim--3"><span>salade</span></span>
    </h1>
    <p class="hero__sub anim anim--4">Format masque, verres enveloppants, cinq coloris. Portée, elle ne passe pas inaperçue — c&rsquo;est le but.</p>

    <div class="stage anim anim--5">
      <div class="stage__glow" aria-hidden="true"></div>
      <?php foreach ($couleurs as $i => $c): ?>
        <img class="stage__img<?= $i === 0 ? ' is-on' : '' ?>"
             src="assets/<?= h($c['fichier']) ?>"
             alt="M&rsquo;trix <?= h($c['nom']) ?>"
             data-couleur="<?= h($c['id']) ?>"
             <?= $i === 0 ? '' : 'loading="lazy"' ?>>
      <?php endforeach; ?>
    </div>

    <div class="picker anim anim--6" id="picker" role="tablist" aria-label="Coloris">
      <?php foreach ($couleurs as $i => $c): ?>
        <button type="button" class="picker__dot<?= $i === 0 ? ' is-on' : '' ?>"
                role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                data-cible="<?= h($c['id']) ?>" title="<?= h($c['nom']) ?>"
                style="--d:<?= $i * 70 ?>ms">
          <span style="background:<?= h($c['puce']) ?>"></span>
        </button>
      <?php endforeach; ?>
    </div>
    <p class="picker__nom anim anim--7" id="pickerNom"><?= h($couleurs[0]['nom']) ?></p>
  </section>

  <!-- Preventeial : le cadran -->
  <?php
    $actif   = mtx_palier_actif();
    $courant = mtx_palier_courant();     // le palier en tête par inventaire, lancé ou non
    $debloque = mtx_palier_debloque();
  ?>
  <section class="prev" id="prevente">
    <div class="prev__haut">
      <h2 class="prev__titre">Pr&eacute;vente</h2>
      <p class="prev__principe">
        <b>15 pi&egrave;ces seulement</b> sont en pr&eacute;vente, r&eacute;parties en 5 paliers &agrave; prix croissant.
        Le premier arriv&eacute; paie le moins cher ; une fois un palier &eacute;puis&eacute;, le suivant s&rsquo;ouvre &agrave; un prix plus haut.
        Une fois les 15 parties, c&rsquo;est le tarif normal pour le reste du stock.
      </p>
      <p class="prev__accroche">
        <?php if ($actif): ?>
          <?php if (mtx_prochain_prix()): ?>Il reste <b><?= $actif["restant"] ?> place<?= $actif["restant"] > 1 ? "s" : "" ?> &agrave; <?= h(fcfa($actif["prix"])) ?></b> &mdash; apr&egrave;s, c&rsquo;est <?= h(fcfa(mtx_prochain_prix())) ?>.<?php else: ?>Dernier palier de la pr&eacute;vente : <b><?= $actif["restant"] ?> place<?= $actif["restant"] > 1 ? "s" : "" ?> &agrave; <?= h(fcfa($actif["prix"])) ?></b>.<?php endif; ?>
        <?php elseif (!$courant): ?>
          Les 15 pi&egrave;ces sont parties &mdash; le tarif normal s&rsquo;applique d&eacute;sormais.
        <?php elseif ($debloque === 0): ?>
          &Ccedil;a n&rsquo;a pas encore commenc&eacute; &mdash; le premier palier ouvre tr&egrave;s bient&ocirc;t.
        <?php else: ?>
          Le palier en cours vient de se remplir &mdash; le suivant, &agrave; <b><?= h(fcfa($courant['prix'])) ?></b>, ouvre tr&egrave;s bient&ocirc;t.
        <?php endif; ?>
      </p>
    </div>

    <div class="prev__scene">
      <div class="cadran"><?= mtx_roue() ?></div>
    </div>

    <?= mtx_bande() ?>

    <?php if ($actif): ?>
      <a class="prev__btn" href="commander.php">R&eacute;server &agrave; <?= h(fcfa($actif["prix"])) ?></a>
    <?php elseif ($courant): ?>
      <a class="prev__btn prev__btn--attente" href="https://wa.me/<?= MTX_WHATSAPP ?>?text=<?= rawurlencode('Bonjour, je veux etre prevenu quand la prevente M\'trix ouvre.') ?>" target="_blank" rel="noopener">
        Se faire pr&eacute;venir sur WhatsApp
      </a>
    <?php endif; ?>
  </section>

  <!-- Bandeau défilant -->
  <div class="ticker" aria-hidden="true">
    <div class="ticker__track">
      <?php for ($i = 0; $i < 2; $i++): ?>
        <span>01 / 11 / 26</span><i>&#10022;</i><span>Cinq coloris</span><i>&#10022;</i><span>Format masque</span><i>&#10022;</i><span>M&rsquo;trix</span><i>&#10022;</i>
      <?php endfor; ?>
    </div>
  </div>

  <section class="strip">
    <div class="strip__item"><span>01</span><p>Verre enveloppant, protection intégrale</p></div>
    <div class="strip__item"><span>02</span><p>Monture légère, tenue ferme</p></div>
    <div class="strip__item"><span>03</span><p>Cinq coloris, même forme</p></div>
  </section>

  <!-- Portee : la monture sur un visage -->
  <section class="porte">
    <img class="porte__img" src="assets/mtrix-buste.jpg" alt="M&rsquo;trix portee, vue de profil" loading="lazy">
    <div class="porte__txt">
      <h2>Elle tient</h2>
      <p>La branche large epouse la tempe et bloque la monture. Pas de glissement, pas de pincement sur le nez.</p>
    </div>
  </section>

  <!-- Les cinq coloris : le nom en géant, la monture posée dessus -->
  <section class="cinq" id="coloris">
    <h2 class="cinq__titre">Les cinq</h2>

    <div class="fond" aria-hidden="true">
      <div class="fond__grille">
        <?php for ($i = 0; $i < 200; $i++): ?><span>M&rsquo;TRIX</span><?php endfor; ?>
      </div>
    </div>

    <?php foreach ($couleurs as $i => $c): ?>
      <article class="teinte<?= $i % 2 ? ' teinte--inv' : '' ?>">
        <span class="teinte__num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3 class="teinte__nom"><?= h($c['nom']) ?></h3>
        <img class="teinte__img" src="assets/<?= h($c['fichier']) ?>" alt="M&rsquo;trix <?= h($c['nom']) ?>" loading="lazy">
      </article>
    <?php endforeach; ?>
  </section>

  <!-- Portée : une seule carte, les photos s'y remplacent l'une après l'autre -->
  <?php
    $photos = glob(__DIR__ . '/assets/porte-*.jpg');
    sort($photos);
    $photos = array_map('basename', $photos);
  ?>
  <section class="defil">
    <div class="defil__tete">
      <h2 class="defil__titre">Portée</h2>
      <p class="defil__compteur"><span id="defilNum">01</span> <i>/</i> <?= str_pad((string) count($photos), 2, '0', STR_PAD_LEFT) ?></p>
    </div>

    <div class="defil__carte" id="defil"
         data-photos='<?= h(json_encode(array_map(fn($p) => 'assets/' . $p, $photos), JSON_UNESCAPED_SLASHES)) ?>'>
      <div class="defil__tranche" style="--k:0"></div>
      <div class="defil__tranche" style="--k:1"></div>
      <div class="defil__tranche" style="--k:2"></div>
      <div class="defil__tranche" style="--k:3"></div>
      <span class="defil__coin defil__coin--hg" aria-hidden="true"></span>
      <span class="defil__coin defil__coin--hd" aria-hidden="true"></span>
      <span class="defil__coin defil__coin--bg" aria-hidden="true"></span>
      <span class="defil__coin defil__coin--bd" aria-hidden="true"></span>
      <span class="defil__marque" aria-hidden="true">M&rsquo;TRIX</span>
    </div>
  </section>

  <section class="cta">
    <p class="cta__eyebrow">Rendez-vous le</p>
    <h2><?= MTX_LANCEMENT ?></h2>
    <p>Écris-nous pour être prévenu du lancement.</p>
    <a class="cta__btn" href="https://wa.me/<?= MTX_WHATSAPP ?>" target="_blank" rel="noopener">Nous écrire sur WhatsApp</a>
  </section>

</main>

<footer class="foot">
  <div class="foot__haut">
    <div class="foot__marque">
      <img src="assets/mtrix-logo.jpg" alt="M&rsquo;trix" class="foot__logo">
      <p>Lunettes en pr&eacute;vente &mdash; Cotonou, B&eacute;nin.</p>
    </div>

    <nav class="foot__col">
      <p class="foot__titre">Le site</p>
      <a href="./#prevente">Pr&eacute;vente</a>
      <a href="./#coloris">Coloris</a>
      <a href="commander.php">Commander</a>
    </nav>

    <nav class="foot__col">
      <p class="foot__titre">Nous suivre</p>
      <a href="https://wa.me/<?= MTX_WHATSAPP ?>" target="_blank" rel="noopener">WhatsApp</a>
      <a href="https://instagram.com/<?= MTX_INSTAGRAM ?>" target="_blank" rel="noopener">Instagram</a>
      <?php if (MTX_TIKTOK !== ''): ?>
        <a href="https://tiktok.com/@<?= MTX_TIKTOK ?>" target="_blank" rel="noopener">TikTok</a>
      <?php endif; ?>
    </nav>
  </div>

  <div class="foot__bas">
    <span>M&rsquo;trix</span>
    <span class="foot__year">&copy; <?= date('Y') ?></span>
  </div>
</footer>

<script src="assets/site.js?v=<?= @filemtime(__DIR__ . '/assets/site.js') ?>"></script>
</body>
</html>
