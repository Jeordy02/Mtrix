<?php
/* M'trix — l'email de confirmation, envoyé via Brevo.

   ┌──────────────────────────────────────────────────────────────────────┐
   │  POUR ACTIVER LES EMAILS                                             │
   │                                                                      │
   │  1. Compte gratuit sur brevo.com (300 emails/jour, ça suffit large)  │
   │  2. Menu SMTP & API › Clés API › en créer une → colle-la ci-dessous  │
   │  3. Menu Expéditeurs › ajoute ton adresse et valide le mail reçu     │
   │     (sans ça Brevo refuse d'envoyer en ton nom)                      │
   │                                                                      │
   │  Clé vide = les emails ne partent pas, le reste du site marche.      │
   └──────────────────────────────────────────────────────────────────────┘ */

const MTX_BREVO_CLE      = '';
const MTX_BREVO_EXPE     = '';            // ton adresse validée chez Brevo
const MTX_BREVO_EXPE_NOM = "M'trix";

function mtx_mail_pret(): bool {
    return MTX_BREVO_CLE !== '' && MTX_BREVO_EXPE !== '';
}

/* Envoi brut. Renvoie ['ok' => bool, 'erreur' => ?string].
   Un email qui ne part pas ne doit jamais casser une commande déjà payée :
   on note l'échec et on continue. */
function mtx_mail_envoyer(string $vers, string $nom, string $sujet, string $html): array {
    if (!mtx_mail_pret())              return ['ok' => false, 'erreur' => 'Brevo non configuré.'];
    if (!filter_var($vers, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'erreur' => 'Adresse invalide.'];

    $charge = [
        'sender'      => ['email' => MTX_BREVO_EXPE, 'name' => MTX_BREVO_EXPE_NOM],
        'to'          => [['email' => $vers, 'name' => $nom !== '' ? $nom : $vers]],
        'subject'     => $sujet,
        'htmlContent' => $html,
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . MTX_BREVO_CLE,
        ],
        CURLOPT_POSTFIELDS     => json_encode($charge, JSON_UNESCAPED_UNICODE),
    ]);
    $corps = curl_exec($ch);
    $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($corps === false)          return ['ok' => false, 'erreur' => 'Brevo injoignable : ' . $err];
    if ($code < 200 || $code > 299) return ['ok' => false, 'erreur' => 'Brevo a répondu ' . $code . ' : ' . substr((string) $corps, 0, 200)];
    return ['ok' => true, 'erreur' => null];
}

/* ---------------------------------------------------------------------
   Le mail de confirmation.

   Écrit comme une confirmation de place gagnée, pas comme un reçu :
   ce client a obtenu un prix que les suivants n'auront plus, et c'est
   ça qui doit lui rester en tête quand il rouvre le message.
   Tout en tableaux et styles en ligne — les boîtes mail ignorent le CSS
   moderne, et la moitié n'affichent aucune image par défaut.
   --------------------------------------------------------------------- */
function mtx_mail_confirmation(array $c): string {
    $site     = mtx_site_url();
    $place    = (int) ($c['place'] ?? 0);
    $total    = mtx_total_places();
    $suivant  = mtx_prochain_prix();
    $nom      = trim((string) ($c['nom'] ?? ''));
    $prenom   = $nom !== '' ? explode(' ', $nom)[0] : 'Salut';

    $eco = $suivant ? max(0, $suivant - (int) $c['prix']) : 0;

    $ligne = function (string $g, string $d): string {
        return '<tr>'
             . '<td style="padding:11px 0;border-bottom:1px solid #26262A;color:#8C8D93;font-size:13px;">' . h($g) . '</td>'
             . '<td style="padding:11px 0;border-bottom:1px solid #26262A;color:#FFFFFF;font-size:14px;font-weight:600;text-align:right;">' . h($d) . '</td>'
             . '</tr>';
    };

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
          . '<meta name="viewport" content="width=device-width,initial-scale=1">'
          . '<title>Ta place M&rsquo;trix</title></head>'
          . '<body style="margin:0;padding:0;background:#0C0C0D;">'
          . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">'
          . 'Place ' . $place . ' sur ' . $total . ' — ton prix est bloqu&eacute; &agrave; ' . h(fcfa($c['prix'])) . '.'
          . '</div>'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0C0C0D;padding:34px 16px;">'
          . '<tr><td align="center">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;">';

    /* Bandeau */
    $html .= '<tr><td style="padding-bottom:26px;">'
           . '<span style="font-family:Georgia,serif;font-size:21px;font-weight:bold;letter-spacing:.24em;color:#FFFFFF;">M&rsquo;TRIX</span>'
           . '</td></tr>';

    /* Le coup de poing */
    $html .= '<tr><td style="background:#E8822B;padding:26px 24px;">'
           . '<p style="margin:0 0 6px;font-family:Arial,sans-serif;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:#5C3005;">Place verrouill&eacute;e</p>'
           . '<p style="margin:0;font-family:Arial,sans-serif;font-size:44px;line-height:1;font-weight:bold;color:#14100C;">'
           . sprintf('%02d', $place) . '<span style="font-size:20px;color:#7A4409;"> / ' . $total . '</span></p>'
           . '</td></tr>';

    /* Le message */
    $html .= '<tr><td style="background:#141416;padding:26px 24px;">'
           . '<p style="margin:0 0 14px;font-family:Arial,sans-serif;font-size:17px;line-height:1.5;color:#FFFFFF;">'
           . h($prenom) . ', c&rsquo;est bon. Ta M&rsquo;trix ' . h($c['couleur']) . ' est &agrave; toi.</p>';

    if ($eco > 0) {
        $html .= '<p style="margin:0 0 18px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#B9BABF;">'
               . 'Tu as pay&eacute; <strong style="color:#E8822B;">' . h(fcfa($c['prix'])) . '</strong>. '
               . 'La personne qui r&eacute;serve apr&egrave;s toi paie <strong style="color:#FFFFFF;">' . h(fcfa($suivant)) . '</strong>. '
               . 'Tu as pris ' . h(fcfa($eco)) . ' d&rsquo;avance sur elle.</p>';
    } else {
        $html .= '<p style="margin:0 0 18px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#B9BABF;">'
               . 'Tu as pay&eacute; <strong style="color:#E8822B;">' . h(fcfa($c['prix'])) . '</strong>, '
               . 'le dernier prix de la pr&eacute;vente. Apr&egrave;s toi, c&rsquo;est le tarif normal.</p>';
    }

    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Arial,sans-serif;">'
           . $ligne('R&eacute;f&eacute;rence', $c['ref'])
           . $ligne('Coloris', $c['couleur'])
           . $ligne('Pay&eacute;', fcfa($c['prix']))
           . $ligne('Livraison', trim((string) ($c['lieu'] ?? '')) !== '' ? $c['lieu'] : '&agrave; pr&eacute;ciser')
           . $ligne('Remise en main propre', MTX_LANCEMENT)
           . '</table></td></tr>';

    /* Ce qui se passe ensuite */
    $html .= '<tr><td style="background:#1B1B1E;padding:22px 24px;">'
           . '<p style="margin:0 0 10px;font-family:Arial,sans-serif;font-size:11px;letter-spacing:.18em;text-transform:uppercase;color:#8C8D93;">La suite</p>'
           . '<p style="margin:0;font-family:Arial,sans-serif;font-size:14px;line-height:1.7;color:#C8C9CE;">'
           . 'On t&rsquo;&eacute;crit sur WhatsApp le <strong style="color:#FFFFFF;">' . MTX_LANCEMENT . '</strong> pour convenir de l&rsquo;heure et du point de rendez-vous. '
           . 'Garde ta r&eacute;f&eacute;rence <strong style="color:#E8822B;">' . h($c['ref']) . '</strong> sous la main.</p>'
           . '</td></tr>';

    /* Bouton WhatsApp */
    $html .= '<tr><td style="padding:22px 0;" align="center">'
           . '<a href="https://wa.me/' . MTX_WHATSAPP . '?text=' . rawurlencode('Bonjour, je suis ' . $nom . ' — commande ' . $c['ref']) . '" '
           . 'style="display:inline-block;background:#FFFFFF;color:#0C0C0D;font-family:Arial,sans-serif;font-size:13px;font-weight:bold;'
           . 'letter-spacing:.12em;text-transform:uppercase;text-decoration:none;padding:15px 30px;">Nous &eacute;crire sur WhatsApp</a>'
           . '</td></tr>';

    /* Pied */
    $html .= '<tr><td style="padding-top:10px;border-top:1px solid #26262A;">'
           . '<p style="margin:14px 0 0;font-family:Arial,sans-serif;font-size:12px;line-height:1.7;color:#6E6F75;">'
           . 'M&rsquo;trix &mdash; Cotonou. '
           . ($site !== '' ? '<a href="' . h($site) . '" style="color:#8C8D93;">Le site</a> &middot; ' : '')
           . '<a href="https://instagram.com/' . MTX_INSTAGRAM . '" style="color:#8C8D93;">Instagram</a><br>'
           . 'Tu re&ccedil;ois ce message parce que tu as r&eacute;serv&eacute; une paire en pr&eacute;vente.</p>'
           . '</td></tr>';

    return $html . '</table></td></tr></table></body></html>';
}

/* L'adresse publique du site, devinée depuis la requête en cours. */
function mtx_site_url(): string {
    if (empty($_SERVER['HTTP_HOST'])) return '';
    $s = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return $s . '://' . $_SERVER['HTTP_HOST'] . $d . '/';
}

/* Envoie la confirmation si on a une adresse. Ne lève jamais d'erreur. */
function mtx_mail_commande(array $c): array {
    $mail = trim((string) ($c['email'] ?? ''));
    if ($mail === '') return ['ok' => false, 'erreur' => 'Pas d\'adresse email.'];

    $sujet = 'Place ' . sprintf('%02d', (int) ($c['place'] ?? 0)) . '/' . mtx_total_places()
           . ' — ta M\'trix est réservée';
    return mtx_mail_envoyer($mail, (string) ($c['nom'] ?? ''), $sujet, mtx_mail_confirmation($c));
}
