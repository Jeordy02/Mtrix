<?php
/* M'trix — page privée : commandes et prévente. */

session_start();
require_once __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../includes/commandes.php';

$err = '';

/* --- Première mise en route : choisir le mot de passe --- */
if (mtx_admin_a_configurer()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nouveau'])) {
        $p = (string) $_POST['nouveau'];
        if (mb_strlen($p) < 6) {
            $err = 'Choisis un mot de passe d\'au moins 6 caractères.';
        } else {
            mtx_admin_definir($p);
            mtx_admin_connecter($p);
            header('Location: admin.php');
            exit;
        }
    }
    if (!mtx_admin_connecte()) { $ecran = 'installer'; }
}

/* --- Connexion / déconnexion --- */
if (isset($_GET['sortir'])) { mtx_admin_sortir(); header('Location: admin.php'); exit; }

if (empty($ecran) && !mtx_admin_connecte()) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['motdepasse'])) {
        if (!mtx_admin_connecter((string) $_POST['motdepasse'])) $err = 'Mot de passe incorrect.';
        else { header('Location: admin.php'); exit; }
    }
    $ecran = 'entrer';
}

/* --- Actions, une fois connecté --- */
if (empty($ecran) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['action'] ?? '';
    if ($a === 'statut')          mtx_commande_statut((string) $_POST['ref'], (string) $_POST['statut']);
    elseif ($a === 'supprimer')   mtx_commande_supprimer((string) $_POST['ref']);
    elseif ($a === 'vente_directe') mtx_set_vendues(mtx_vendues() + 1);
    elseif ($a === 'rendre')      mtx_set_vendues(mtx_vendues() - 1);
    elseif ($a === 'fixer')       mtx_set_vendues((int) ($_POST['n'] ?? 0));
    elseif ($a === 'lancer')      mtx_palier_debloquer_suivant();
    header('Location: admin.php');
    exit;
}

$vendues  = mtx_vendues();
$total    = mtx_total_places();
$actif    = mtx_palier_actif();
$debloque = mtx_palier_debloque();
$paliers  = mtx_paliers();
$relancer = mtx_a_relancer();
$verifier = mtx_a_verifier();
$attente  = mtx_file_lire();
$cmds     = array_reverse(mtx_commandes());

/* Le prochain palier à lancer, s'il en reste un. */
$prochain = null;
foreach (mtx_paliers_def() as $p) if ((int) $p['n'] === $debloque + 1) { $prochain = $p; break; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>M'trix — commandes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Archivo:wght@400;500;600;700&family=Chakra+Petch:wght@500;600;700&display=swap">
<style>
  :root {
    --ink:    #0C0C0D;
    --paper:  #FFFFFF;
    --fond:   #F1EFEA;
    --smoke:  #F6F4F0;
    --grey:   #75767C;
    --grey-d: #78766E;   /* était #9C9A93 : 2,8:1 sur blanc, illisible pour
                            des libellés de 10,5px. Celui-ci tient 4,6:1. */
    --line:   #E7E4DC;
    --orange: #E8822B;
    --vert:   #1E7A3C; --vert-bg:#E7F5EC; --vert-l:#CDE9D6;
    --rouge:  #B23A22; --rouge-bg:#FCEAE5; --rouge-l:#F0C3B5;
    --ambre:  #9A6212; --ambre-bg:#FBF0DD; --ambre-l:#EFD3A0;
    --radius: 10px;
    --ombre: 0 1px 2px rgba(20,14,8,.04), 0 10px 26px -12px rgba(20,14,8,.14);
  }
  * { box-sizing: border-box; }
  html {
    scrollbar-gutter: stable;
    /* La gouttiere reservée par scrollbar-gutter était peinte avec la
       piste d'ascenseur par défaut, quasi blanche : une bande claire de
       15 px le long du bord droit. On donne un fond à <html> et on
       teinte l'ascenseur aux couleurs de la page. */
    background: var(--fond);
    scrollbar-color: #C9C6BC var(--fond);
  }

  /* Rien n'avait d'anneau de focus : le panneau était inutilisable au
     clavier. Une règle, tous les contrôles. */
  :where(a, button, input, select, textarea, summary):focus-visible {
    outline: 2px solid var(--orange);
    outline-offset: 2px;
    border-radius: 4px;
  }
  .barre-haut :where(a, button):focus-visible { outline-color: var(--orange); }

  .visuellement-cache {
    position: absolute; width: 1px; height: 1px; overflow: hidden;
    clip-path: inset(50%); white-space: nowrap;
  }
  body {
    margin: 0; background: var(--fond); color: var(--ink);
    font-family: 'Archivo', system-ui, sans-serif; font-size: 15px; line-height: 1.5;
    -webkit-font-smoothing: antialiased;
  }
  ::selection { background: #F5D3B0; }

  /* ---------- Barre du haut ---------- */
  .barre-haut {
    position: sticky; top: 0; z-index: 20;
    display: flex; align-items: center; justify-content: space-between; gap: 16px;
    padding: 16px clamp(18px, 4vw, 40px);
    background: var(--ink); color: #F4F3F0;
  }
  .barre-haut__marque {
    display: flex; align-items: baseline; gap: 10px; margin: 0;
    font-family: 'Archivo Black', sans-serif; font-size: 16px; letter-spacing: .01em;
  }
  .barre-haut__marque span {
    font-family: 'Chakra Petch', sans-serif; font-size: 10.5px; font-weight: 600;
    letter-spacing: .22em; text-transform: uppercase; color: #8C8D93;
  }
  .barre-haut__nav { display: flex; gap: 8px; }
  .barre-haut__nav a {
    font-family: 'Chakra Petch', sans-serif; font-size: 11px; font-weight: 600;
    letter-spacing: .1em; text-transform: uppercase; text-decoration: none;
    color: #D8D8DA; padding: 9px 15px; border-radius: 99px; border: 1px solid #303032;
    transition: border-color .18s ease, color .18s ease, background .18s ease;
  }
  .barre-haut__nav a:hover { border-color: #4A4A4E; color: #fff; background: #17171A; }
  .barre-haut__nav a.accent { color: #14100C; background: var(--orange); border-color: var(--orange); }
  .barre-haut__nav a.accent:hover { background: #F2933F; color: #14100C; }

  .page { max-width: 1120px; margin: 0 auto; padding: clamp(20px, 4vw, 36px) clamp(18px, 4vw, 40px) 80px; }

  /* ---------- Petits écrans (installation / connexion) ---------- */
  .porte {
    min-height: 100vh; display: flex; align-items: center; justify-content: center;
    padding: 24px; background:
      radial-gradient(circle at 15% 15%, rgba(232,130,43,.12), transparent 40%), var(--ink);
  }
  .porte__carte {
    width: 100%; max-width: 380px; background: var(--paper); border-radius: 16px;
    padding: 32px 28px; box-shadow: 0 30px 70px -20px rgba(0,0,0,.55);
  }
  .porte__marque {
    margin: 0 0 22px; font-family: 'Archivo Black', sans-serif; font-size: 15px;
    letter-spacing: .06em;
  }
  .porte h1 {
    font-family: 'Chakra Petch', sans-serif; font-size: 12px; font-weight: 600; letter-spacing: .2em;
    text-transform: uppercase; color: var(--orange); margin: 0 0 10px;
  }
  .porte p.sous { color: var(--grey); font-size: 14px; line-height: 1.6; margin: 0 0 22px; }
  .porte input[type=password] {
    width: 100%; font: inherit; font-size: 15px; padding: 13px 14px; margin-bottom: 12px;
    border: 1px solid var(--line); border-radius: 8px; background: var(--smoke);
    transition: border-color .15s ease, background .15s ease;
  }
  .porte input[type=password]:focus { outline: none; border-color: var(--orange); background: var(--paper); }

  /* ---------- Boutons ---------- */
  button, .btn {
    font: inherit; font-family: 'Chakra Petch', sans-serif; font-size: 12px; font-weight: 600;
    letter-spacing: .06em; cursor: pointer; text-decoration: none; display: inline-flex;
    align-items: center; gap: 6px; white-space: nowrap;
    padding: 10px 16px; border-radius: 8px; border: 1px solid var(--line);
    background: var(--paper); color: var(--ink);
    transition: border-color .15s ease, background .15s ease, transform .1s ease, box-shadow .15s ease;
  }
  button:hover, .btn:hover { border-color: #C9C6BC; background: var(--smoke); }
  button:active, .btn:active { transform: translateY(1px); }
  .btn-fort { background: var(--ink); border-color: var(--ink); color: #fff; }
  .btn-fort:hover { background: #26262A; border-color: #26262A; }
  .btn-accent { background: var(--orange); border-color: var(--orange); color: #14100C; }
  .btn-accent:hover { background: #F2933F; border-color: #F2933F; }
  .btn-danger { background: var(--rouge); border-color: var(--rouge); color: #fff; }
  .btn-danger:hover { background: #C7432A; border-color: #C7432A; }
  .btn-ghost-danger { color: var(--rouge); border-color: var(--rouge-l); background: var(--paper); }
  .btn-ghost-danger:hover { background: var(--rouge-bg); }
  .btn-sm { padding: 7px 12px; font-size: 11px; border-radius: 7px; }
  /* La croix faisait 28x30 : trop petit pour un doigt, et c'est l'action
     irréversible de la page. Elle passe à la taille recommandée. */
  .btn-icone {
    padding: 0; min-width: 44px; min-height: 44px;
    justify-content: center; font-size: 13px;
  }
  .full { width: 100%; justify-content: center; }
  form.inline { display: inline-block; }
  .rang-btn { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }

  /* ---------- Cartes / sections ---------- */
  .carte {
    background: var(--paper); border: 1px solid var(--line); border-radius: var(--radius);
    box-shadow: var(--ombre);
  }
  .section { margin-top: 32px; }
  .section__tete {
    display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
    margin-bottom: 14px;
  }
  .section h2 {
    font-family: 'Chakra Petch', sans-serif; font-size: 11.5px; font-weight: 700; letter-spacing: .2em;
    text-transform: uppercase; color: var(--grey); margin: 0;
  }
  .section__sous { font-size: 12.5px; color: var(--grey-d); }

  /* ---------- KPI ---------- */
  .kpis { display: grid; grid-template-columns: repeat(2,1fr); gap: 12px; }
  @media (min-width: 760px) { .kpis { grid-template-columns: repeat(5,1fr); } }
  .kpi {
    position: relative; overflow: hidden; padding: 18px 18px 16px;
    display: flex; flex-direction: column; gap: 10px; min-height: 104px;
  }
  .kpi__haut { display: flex; align-items: center; justify-content: space-between; }
  .kpi__label {
    font-family: 'Chakra Petch', sans-serif; font-size: 10.5px; font-weight: 700;
    letter-spacing: .14em; text-transform: uppercase; color: var(--grey-d);
  }
  .kpi__point { width: 7px; height: 7px; border-radius: 50%; background: var(--line); flex: 0 0 auto; }
  /* Le point seul disait « urgent » ou « ça va » : une information portée
     par la couleur uniquement. On lui ajoute un mot. */
  .kpi__etat {
    font-family: 'Chakra Petch', sans-serif; font-size: 9.5px; font-weight: 700;
    letter-spacing: .12em; text-transform: uppercase; color: var(--grey-d);
  }
  .kpi--urgent .kpi__etat { color: var(--rouge); }
  .kpi--chaud  .kpi__etat { color: var(--ambre); }
  .kpi--bien   .kpi__etat { color: var(--vert); }
  .kpi b { font-family: 'Archivo Black', sans-serif; font-size: 27px; letter-spacing: -.02em; line-height: 1; }
  .kpi__barre { height: 4px; border-radius: 99px; background: var(--line); overflow: hidden; margin-top: auto; }
  .kpi__barre i { display: block; height: 100%; background: var(--ink); border-radius: 99px; transition: width .5s cubic-bezier(.16,1,.3,1); }

  .kpi--urgent .kpi__point { background: var(--rouge); box-shadow: 0 0 0 3px var(--rouge-bg); }
  .kpi--urgent b { color: var(--rouge); }
  .kpi--chaud .kpi__point { background: var(--ambre); box-shadow: 0 0 0 3px var(--ambre-bg); }
  .kpi--chaud b { color: var(--ambre); }
  .kpi--bien .kpi__point { background: var(--vert); box-shadow: 0 0 0 3px var(--vert-bg); }

  /* ---------- Alertes / bandeaux ---------- */
  .bandeau {
    display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; margin-top: 12px;
  }
  .bandeau i.puce { flex: 0 0 auto; margin-top: 5px; width: 7px; height: 7px; border-radius: 50%; }
  .bandeau p { margin: 0; font-size: 13.5px; line-height: 1.55; }
  .bandeau--ambre { background: var(--ambre-bg); border-color: var(--ambre-l); }
  .bandeau--ambre i.puce { background: var(--ambre); }
  .bandeau--ambre a { color: inherit; text-decoration: underline; text-underline-offset: 2px; }
  .bandeau--info { background: var(--smoke); }
  .bandeau--info i.puce { background: var(--grey-d); }

  /* ---------- Panneau de lancement ---------- */
  .lancement {
    padding: 22px clamp(18px, 3vw, 26px);
    display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap;
  }
  .lancement__eyebrow {
    margin: 0 0 6px; font-family: 'Chakra Petch', sans-serif; font-size: 10.5px; font-weight: 700;
    letter-spacing: .18em; text-transform: uppercase; color: var(--rouge);
  }
  .lancement__titre { margin: 0 0 6px; font-family: 'Archivo Black', sans-serif; font-size: 19px; letter-spacing: -.01em; }
  .lancement__txt { margin: 0; font-size: 13.5px; line-height: 1.6; color: var(--grey); max-width: 56ch; }
  .lancement--zero { border-color: var(--rouge-l); background: linear-gradient(180deg, var(--rouge-bg), var(--paper) 130%); }
  .lancement--zero .lancement__titre { color: var(--rouge); }
  .lancement--pret { border-color: #F0C89A; background: linear-gradient(180deg, #FFF5E7, var(--paper) 140%); }

  /* ---------- Le grand moment : lancer la prévente ---------- */
  .lance-hero {
    position: relative; overflow: hidden; border-radius: 18px;
    background: var(--ink); color: #F4F3F0;
    display: grid; grid-template-columns: 1fr;
    box-shadow: 0 30px 70px -24px rgba(0,0,0,.5);
  }
  @media (min-width: 680px) { .lance-hero { grid-template-columns: minmax(200px, 320px) 1fr; } }
  .lance-hero__glow {
    position: absolute; inset: 0; pointer-events: none;
    background:
      radial-gradient(circle at 85% 15%, rgba(232,130,43,.22), transparent 45%),
      radial-gradient(circle at 10% 100%, rgba(232,130,43,.10), transparent 40%);
  }
  .lance-hero__photo {
    position: relative; display: flex; align-items: center; justify-content: center;
    overflow: hidden; max-height: 340px;
    background: linear-gradient(155deg, #FBFAF7, var(--smoke));
  }
  @media (min-width: 680px) { .lance-hero__photo { max-height: none; } }
  .lance-hero__photo img {
    width: 100%; height: 100%; object-fit: cover; object-position: center; mix-blend-mode: multiply;
  }
  .lance-hero__corps {
    position: relative; padding: clamp(26px, 4vw, 42px) clamp(24px, 4vw, 40px);
    display: flex; flex-direction: column; justify-content: center; gap: 4px;
  }
  .lance-hero__eyebrow {
    margin: 0 0 10px; font-family: 'Chakra Petch', sans-serif; font-size: 11px; font-weight: 700;
    letter-spacing: .22em; text-transform: uppercase; color: var(--orange);
  }
  .lance-hero__titre {
    margin: 0 0 12px; font-family: 'Archivo Black', sans-serif;
    font-size: clamp(22px, 3vw, 30px); line-height: 1.08; letter-spacing: -.01em;
  }
  .lance-hero__txt { margin: 0 0 22px; font-size: 14px; line-height: 1.65; color: #B9BABF; max-width: 46ch; }
  .lance-hero__btn {
    justify-content: space-between; width: 100%; max-width: 380px;
    padding: 16px 20px; border-radius: 10px; font-size: 13px;
  }
  .lance-hero__btn span {
    font-family: 'Archivo', sans-serif; font-weight: 700; font-size: 13.5px; letter-spacing: 0;
  }

  /* ---------- Tableaux ---------- */
  .table-scroll { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
  thead th {
    position: sticky; top: 0; background: var(--paper);
    text-align: left; padding: 13px 14px; border-bottom: 1px solid var(--line);
    font-family: 'Chakra Petch', sans-serif; font-size: 10px; font-weight: 700;
    letter-spacing: .12em; text-transform: uppercase; color: var(--grey-d); white-space: nowrap;
  }
  td { padding: 13px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
  tbody tr { transition: background .12s ease; }
  tbody tr:hover { background: var(--smoke); }
  tbody tr:last-child td { border-bottom: 0; }
  td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .place-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 30px; padding: 0 8px; border-radius: 7px;
    background: var(--smoke); font-family: 'Archivo Black', sans-serif; font-size: 14px;
  }
  .place-badge--vide { background: none; color: var(--grey-d); font-family: 'Archivo', sans-serif; font-size: 14px; }
  .ref-num { font-family: 'Chakra Petch', sans-serif; font-weight: 700; letter-spacing: .02em; }
  .meta { font-size: 11.5px; color: var(--grey-d); }
  .transac { font-family: 'Chakra Petch', sans-serif; font-size: 11px; color: var(--grey); }
  .manque {
    display: inline-block; margin-top: 4px; font-family: 'Chakra Petch', sans-serif;
    font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--ambre);
  }
  a.tel { color: var(--ink); text-decoration: none; border-bottom: 1px solid var(--line); }
  a.tel:hover { border-color: var(--orange); color: var(--orange); }

  /* ---------- Pastilles d'état ---------- */
  .et {
    display: inline-flex; align-items: center; gap: 5px;
    font-family: 'Chakra Petch', sans-serif; font-size: 10px; font-weight: 700;
    letter-spacing: .06em; text-transform: uppercase; padding: 4px 10px 4px 8px; border-radius: 99px;
    white-space: nowrap;
  }
  .et::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
  .et-attente     { background: var(--smoke); color: var(--grey); }
  .et-a_confirmer { background: var(--rouge-bg); color: var(--rouge); }
  .et-payee       { background: var(--vert-bg); color: var(--vert); }
  .et-livree      { background: var(--ink); color: #fff; }
  .et-annulee     { background: var(--smoke); color: var(--grey-d); text-decoration: line-through; }
  .et-verrouille  { background: var(--smoke); color: var(--grey-d); }

  .vide { padding: 46px 10px; color: var(--grey-d); text-align: center; font-size: 14px; }
  .err {
    background: var(--rouge-bg); border: 1px solid var(--rouge-l); color: var(--rouge);
    padding: 11px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13.5px;
  }
  input[type=number] {
    font: inherit; padding: 10px 12px; width: 100px; border: 1px solid var(--line);
    border-radius: 8px; background: var(--paper);
  }
  .note {
    margin-top: 26px; padding: 16px 18px; font-size: 12.5px; color: var(--grey);
    line-height: 1.7; background: var(--smoke); border-radius: var(--radius);
  }
  .note strong { color: var(--ink); }
  .note code {
    background: var(--paper); border: 1px solid var(--line); border-radius: 4px;
    padding: 1px 5px; font-size: 11.5px;
  }
</style>
</head>
<body>

<?php if (($ecran ?? '') === 'installer'): ?>
  <div class="porte">
    <div class="porte__carte">
      <p class="porte__marque">M&rsquo;TRIX</p>
      <h1>Première mise en route</h1>
      <p class="sous">Choisis le mot de passe qui protégera tes commandes. Tu seras le seul à voir les noms et numéros de tes clients.</p>
      <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
      <form method="post">
        <input type="password" name="nouveau" placeholder="Nouveau mot de passe" autofocus required>
        <button class="btn-accent full" type="submit">Enregistrer</button>
      </form>
    </div>
  </div>

<?php elseif (($ecran ?? '') === 'entrer'): ?>
  <div class="porte">
    <div class="porte__carte">
      <p class="porte__marque">M&rsquo;TRIX</p>
      <h1>Commandes</h1>
      <p class="sous">Accès privé.</p>
      <?php if ($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
      <form method="post">
        <input type="password" name="motdepasse" placeholder="Mot de passe" autofocus required>
        <button class="btn-accent full" type="submit">Entrer</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <header class="barre-haut">
    <p class="barre-haut__marque">M&rsquo;TRIX <span>Commandes</span></p>
    <nav class="barre-haut__nav">
      <a href="./">Voir le site</a>
      <a class="accent" href="admin.php?sortir=1">Sortir</a>
    </nav>
  </header>

  <div class="page">

    <div class="kpis">
      <div class="carte kpi kpi--bien">
        <div class="kpi__haut"><span class="kpi__label">Places prises</span><i class="kpi__point"></i></div>
        <b><?= $vendues ?> <span style="font-size:15px;color:var(--grey-d);font-family:'Archivo',sans-serif;font-weight:500">/ <?= $total ?></span></b>
        <div class="kpi__barre"><i style="width:<?= $total ? round(100 * $vendues / $total) : 0 ?>%"></i></div>
      </div>
      <div class="carte kpi<?= $verifier ? ' kpi--urgent' : '' ?>">
        <div class="kpi__haut"><span class="kpi__label">À vérifier</span><i class="kpi__point"></i></div>
        <b><?= count($verifier) ?></b>
        <span class="kpi__etat"><?= $verifier ? 'à traiter' : 'rien en attente' ?></span>
      </div>
      <div class="carte kpi<?= $relancer ? ' kpi--chaud' : '' ?>">
        <div class="kpi__haut"><span class="kpi__label">À relancer</span><i class="kpi__point"></i></div>
        <b><?= count($relancer) ?></b>
        <span class="kpi__etat"><?= $relancer ? 'à relancer' : 'rien en attente' ?></span>
      </div>
      <div class="carte kpi">
        <div class="kpi__haut"><span class="kpi__label">Encaissé</span><i class="kpi__point"></i></div>
        <b style="font-size:22px"><?= h(fcfa(mtx_encaisse())) ?></b>
      </div>
      <div class="carte kpi">
        <div class="kpi__haut"><span class="kpi__label"><?= $actif ? 'Prix en cours' : 'Statut' ?></span><i class="kpi__point"></i></div>
        <b style="font-size:22px"><?= $actif ? h(fcfa($actif['prix'])) : 'Complet' ?></b>
      </div>
    </div>

    <?php if (!mtx_paiement_pret()): ?>
      <div class="carte bandeau bandeau--ambre">
        <i class="puce"></i>
        <p><strong>FedaPay n&rsquo;est pas configur&eacute;.</strong> Les clients ne peuvent pas payer en ligne. Configure les variables d&rsquo;environnement indiqu&eacute;es dans <code>FEDAPAY_SETUP.md</code>.</p>
      </div>
    <?php endif; ?>

    <?php if ($verifier): ?>
      <div class="carte bandeau bandeau--ambre">
        <i class="puce"></i>
        <p><strong><?= count($verifier) ?> ancienne<?= count($verifier) > 1 ? 's' : '' ?> commande<?= count($verifier) > 1 ? 's' : '' ?> à vérifier</strong> manuellement dans le tableau de bord FedaPay.</p>
      </div>
    <?php endif; ?>

    <?php if ($attente): ?>
      <div class="carte bandeau bandeau--info">
        <i class="puce"></i>
        <p><strong><?= count($attente) ?> personne<?= count($attente) > 1 ? 's' : '' ?> dans la file d&rsquo;attente</strong> —
        servies automatiquement, dans l&rsquo;ordre, dès qu&rsquo;une place se libère.</p>
      </div>
    <?php endif; ?>

    <div class="section">
      <?php if ($debloque === 0): ?>
        <div class="lance-hero">
          <div class="lance-hero__glow" aria-hidden="true"></div>
          <div class="lance-hero__photo">
            <img src="assets/mtrix-gamme-logo.jpg" alt="Les cinq coloris M&rsquo;trix">
          </div>
          <div class="lance-hero__corps">
            <p class="lance-hero__eyebrow">Tout est prêt</p>
            <h3 class="lance-hero__titre">La prévente<br>n&rsquo;a pas encore commencé</h3>
            <p class="lance-hero__txt">Le site, les paliers, la file d&rsquo;attente : tout tourne déjà.
            Il ne manque que ton feu vert pour que le premier client puisse payer.</p>
            <form method="post">
              <input type="hidden" name="action" value="lancer">
              <button class="btn-accent lance-hero__btn" type="submit">
                Lancer la prévente
                <span><?= h(fcfa(mtx_paliers_def()[0]['prix'])) ?> — palier 1</span>
              </button>
            </form>
          </div>
        </div>
      <?php elseif ($prochain): ?>
        <div class="carte lancement lancement--pret">
          <div>
            <p class="lancement__eyebrow">Palier <?= $debloque ?> en vente</p>
            <p class="lancement__titre"><?= ($actif && $actif['n'] === $debloque)
              ? 'Encore ' . $actif['restant'] . ' place' . ($actif['restant'] > 1 ? 's' : '') . ' disponible' . ($actif['restant'] > 1 ? 's' : '')
              : 'Ce palier est épuisé' ?></p>
            <p class="lancement__txt">Quand il n&rsquo;y a plus rien à vendre dedans, lance le palier suivant.</p>
          </div>
          <form method="post"><input type="hidden" name="action" value="lancer">
            <button class="btn-accent" type="submit">Lancer le palier <?= $prochain['n'] ?> — <?= h(fcfa($prochain['prix'])) ?></button>
          </form>
        </div>
      <?php else: ?>
        <div class="carte bandeau bandeau--info"><i class="puce"></i>
          <p>Tous les paliers sont lancés — il n&rsquo;y a plus rien à débloquer.</p>
        </div>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section__tete">
        <h2>Réglage manuel</h2>
        <span class="section__sous">Pour une vente main à la main ou hors site</span>
      </div>
      <div class="carte" style="padding:16px 18px">
        <div class="rang-btn">
          <form method="post" class="inline"><input type="hidden" name="action" value="vente_directe">
            <button class="btn-fort btn-sm" type="submit">+ Vente hors site</button></form>
          <form method="post" class="inline"><input type="hidden" name="action" value="rendre">
            <button class="btn-sm" type="submit">− 1 place</button></form>
          <form method="post" class="inline rang-btn" style="gap:6px">
            <input type="hidden" name="action" value="fixer">
            <input type="number" name="n" min="0" max="<?= $total ?>" value="<?= $vendues ?>">
            <button class="btn-sm" type="submit">Fixer</button>
          </form>
        </div>
      </div>
    </div>

    <div class="section">
      <div class="section__tete">
        <h2>Les commandes</h2>
        <span class="section__sous"><?= count($cmds) ?> au total</span>
      </div>
      <?php if (!$cmds): ?>
        <div class="carte vide">Aucune commande pour l&rsquo;instant. Elles arriveront ici dès qu&rsquo;un client remplit le formulaire.</div>
      <?php else: ?>
        <div class="carte table-scroll">
        <table>
          <thead><tr>
            <th class="num">Place</th><th>Réf.</th><th>Client</th><th>Coloris</th><th>Livraison</th>
            <th class="num">Prix</th><th>État</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($cmds as $c): $s = $c['statut'] ?? 'attente'; ?>
            <tr>
              <td class="num">
                <?php if ($c['place']): ?>
                  <span class="place-badge"><?= sprintf('%02d', $c['place']) ?></span>
                <?php else: ?>
                  <span class="place-badge place-badge--vide">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="ref-num"><?= h($c['ref']) ?></span><br>
                <span class="meta"><?= h(substr($c['cree_le'], 0, 16)) ?></span>
                <?php if (!empty($c['transac'])): ?>
                  <br><span class="transac">réf. transac : <?= h($c['transac']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (trim((string) $c['nom']) !== ''): ?>
                  <?= h($c['nom']) ?><br>
                  <a class="tel" href="https://wa.me/<?= h(preg_replace('/\D/', '', $c['tel'])) ?>" target="_blank" rel="noopener"><?= h($c['tel']) ?></a>
                  <?php if (!empty($c['email'])): ?><br><span class="meta"><?= h($c['email']) ?></span><?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--grey-d)">—</span>
                  <?php if (in_array($c['statut'], ['payee','livree'], true)): ?>
                    <span class="manque">a payé sans laisser ses coordonnées</span>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
              <td><?= h($c['couleur']) ?></td>
              <td style="max-width:200px">
                <?= trim((string) $c['lieu']) !== '' ? h($c['lieu']) : '<span style="color:var(--grey-d)">—</span>' ?>
                <?php if (!empty($c['note'])): ?><br><span class="meta"><?= h($c['note']) ?></span><?php endif; ?>
              </td>
              <td class="num" style="font-weight:600"><?= h(fcfa($c['prix'])) ?></td>
              <td><span class="et et-<?= h($s) ?>"><?= h(MTX_STATUTS[$s] ?? $s) ?></span></td>
              <td style="white-space:nowrap">
                <div class="rang-btn" style="gap:6px">
                  <?php if ($s === 'a_confirmer'): ?>
                    <form method="post" class="inline"><input type="hidden" name="action" value="statut">
                      <input type="hidden" name="ref" value="<?= h($c['ref']) ?>">
                      <button name="statut" value="payee" class="btn-danger btn-sm">Confirmer</button></form>
                  <?php elseif ($s === 'attente'): ?>
                    <form method="post" class="inline"><input type="hidden" name="action" value="statut">
                      <input type="hidden" name="ref" value="<?= h($c['ref']) ?>">
                      <button name="statut" value="payee" class="btn-accent btn-sm">Payée</button></form>
                  <?php elseif ($s === 'payee'): ?>
                    <form method="post" class="inline"><input type="hidden" name="action" value="statut">
                      <input type="hidden" name="ref" value="<?= h($c['ref']) ?>">
                      <button name="statut" value="livree" class="btn-accent btn-sm">Livrée</button></form>
                  <?php endif; ?>
                  <?php if ($s !== 'annulee'): ?>
                    <form method="post" class="inline"><input type="hidden" name="action" value="statut">
                      <input type="hidden" name="ref" value="<?= h($c['ref']) ?>">
                      <button name="statut" value="annulee" class="btn-sm">Annuler</button></form>
                  <?php endif; ?>
                  <form method="post" class="inline" onsubmit="return confirm('Supprimer définitivement <?= h($c['ref']) ?> ?')">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="ref" value="<?= h($c['ref']) ?>">
                    <button class="btn-ghost-danger btn-icone" type="submit">
                      <span aria-hidden="true">✕</span>
                      <span class="visuellement-cache">Supprimer la commande <?= h($c['ref']) ?></span>
                    </button></form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="section">
      <div class="section__tete"><h2>Les paliers</h2></div>
      <div class="carte table-scroll">
      <table>
        <thead><tr><th>Palier</th><th>Prix</th><th class="num">Places</th><th class="num">Restant</th><th>Inventaire</th><th>Vente</th></tr></thead>
        <tbody>
        <?php foreach ($paliers as $p): $lance = (int) $p['n'] <= $debloque; ?>
          <tr>
            <td style="font-weight:600">Palier <?= $p['n'] ?></td>
            <td><?= h(fcfa($p['prix'])) ?></td>
            <td class="num"><?= $p['places'] ?></td>
            <td class="num" style="font-weight:600"><?= $p['restant'] ?></td>
            <td><span class="et et-<?= $p['statut'] === 'epuise' ? 'annulee' : ($p['statut'] === 'encours' ? 'payee' : 'attente') ?>">
              <?= $p['statut'] === 'epuise' ? 'épuisé' : ($p['statut'] === 'encours' ? 'en cours' : 'à venir') ?></span></td>
            <td>
              <?php if ($p['ouvert']): ?>
                <span class="et et-payee">ouvert</span>
              <?php elseif ($lance): ?>
                <span class="et et-annulee">terminé</span>
              <?php else: ?>
                <span class="et et-verrouille">verrouillé</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>

    <p class="note">
      Les commandes créées avant le passage à FedaPay et marquées <strong>« paiement à vérifier »</strong> doivent être vérifiées dans le tableau de bord FedaPay avant de cliquer « Confirmer ».<br>
      Annuler une commande payée <strong>rend sa place</strong> : la roue recule d&rsquo;un cran et le prix peut redescendre.<br>
      La configuration FedaPay se fait dans les variables d&rsquo;environnement du serveur, jamais dans le code. Consulte le guide <code>FEDAPAY_SETUP.md</code>.
    </p>
  </div>
<?php endif; ?>

</body>
</html>
