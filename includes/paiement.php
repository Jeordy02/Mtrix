<?php
/* M'trix — intégration serveur FedaPay. */

function mtx_fedapay_cle(): string {
    $cle = getenv('FEDAPAY_API_KEY');
    return is_string($cle) ? trim($cle) : '';
}

function mtx_fedapay_environnement(): string {
    $environnement = getenv('FEDAPAY_ENV');
    return is_string($environnement) && in_array($environnement, ['sandbox', 'live'], true)
        ? $environnement
        : '';
}

function mtx_fedapay_url_base(): string {
    $url = getenv('MTX_BASE_URL');
    if (!is_string($url)) return '';

    $url = rtrim(trim($url), '/');
    $parties = parse_url($url);
    if (!$parties || !in_array($parties['scheme'] ?? '', ['http', 'https'], true)
        || empty($parties['host']) || isset($parties['user']) || isset($parties['pass'])
        || isset($parties['query']) || isset($parties['fragment'])) {
        return '';
    }
    if (mtx_fedapay_environnement() === 'live' && $parties['scheme'] !== 'https') return '';
    return $url;
}

function mtx_paiement_mode(): string {
    return mtx_fedapay_cle() !== '' && mtx_fedapay_environnement() !== ''
        && mtx_fedapay_url_base() !== '' ? 'api' : 'indisponible';
}

function mtx_paiement_pret(): bool {
    return mtx_paiement_mode() === 'api';
}

function mtx_fedapay_api_base(): string {
    return mtx_fedapay_environnement() === 'live'
        ? 'https://api.fedapay.com/v1'
        : 'https://sandbox-api.fedapay.com/v1';
}

function mtx_fedapay_donnees(array $reponse): array {
    foreach (['v1/transaction', 'v1/token', 'transaction', 'token', 'data'] as $cle) {
        if (isset($reponse[$cle]) && is_array($reponse[$cle])) return $reponse[$cle];
    }
    return $reponse;
}

function mtx_fedapay_requete(string $methode, string $chemin, ?array $donnees = null): array {
    if (!mtx_paiement_pret()) {
        return ['ok' => false, 'donnees' => [], 'erreur' => 'Le paiement FedaPay n’est pas configuré.'];
    }
    if (!function_exists('curl_init')) {
        error_log('FedaPay: l’extension PHP cURL est indisponible.');
        return ['ok' => false, 'donnees' => [], 'erreur' => 'Le service de paiement est momentanément indisponible.'];
    }

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . mtx_fedapay_cle(),
        ],
    ];
    if ($donnees !== null) {
        $json = json_encode($donnees);
        if ($json === false) {
            error_log('FedaPay: impossible d’encoder la requête JSON.');
            return ['ok' => false, 'donnees' => [], 'erreur' => 'Le service de paiement est momentanément indisponible.'];
        }
        $options[CURLOPT_POSTFIELDS] = $json;
    }

    $ch = curl_init(mtx_fedapay_api_base() . $chemin);
    curl_setopt_array($ch, $options);
    $corps = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erreurCurl = curl_error($ch);
    curl_close($ch);

    if ($corps === false) {
        error_log('FedaPay: échec de la requête API (' . $erreurCurl . ').');
        return ['ok' => false, 'donnees' => [], 'erreur' => 'FedaPay est momentanément injoignable. Réessaie dans un instant.'];
    }
    if ($code < 200 || $code >= 300) {
        error_log('FedaPay: réponse HTTP ' . $code . ' pour ' . $methode . ' ' . $chemin . '.');
        return ['ok' => false, 'donnees' => [], 'erreur' => 'FedaPay n’a pas pu traiter le paiement. Réessaie dans un instant.'];
    }

    $reponse = json_decode((string) $corps, true);
    if (!is_array($reponse)) {
        error_log('FedaPay: réponse JSON illisible.');
        return ['ok' => false, 'donnees' => [], 'erreur' => 'La réponse de FedaPay est illisible. Réessaie dans un instant.'];
    }
    return ['ok' => true, 'donnees' => mtx_fedapay_donnees($reponse), 'erreur' => null];
}

function mtx_fedapay_url_valide(string $url): bool {
    $parties = parse_url($url);
    if (!$parties || ($parties['scheme'] ?? '') !== 'https' || empty($parties['host'])) return false;
    $hote = strtolower((string) $parties['host']);
    return $hote === 'fedapay.com'
        || (strlen($hote) > 12 && substr($hote, -12) === '.fedapay.com');
}

function mtx_fedapay_demarrer(array $commande): array {
    if (!mtx_paiement_pret()) {
        return ['ok' => false, 'url' => null, 'erreur' => 'Le paiement en ligne est momentanément indisponible.'];
    }

    $ref = (string) $commande['ref'];
    $id = trim((string) ($commande['transac'] ?? ''));

    if ($id === '') {
        $retour = mtx_fedapay_url_base() . '/commander.php?ref=' . rawurlencode($ref);
        $creation = mtx_fedapay_requete('POST', '/transactions', [
            'description' => 'Commande M’trix ' . $ref,
            'amount' => (int) $commande['prix'],
            'currency' => ['iso' => 'XOF'],
            'callback_url' => $retour,
            'custom_metadata' => ['order_ref' => $ref],
        ]);
        if (!$creation['ok']) return ['ok' => false, 'url' => null, 'erreur' => $creation['erreur']];

        $transaction = $creation['donnees'];
        $id = (string) ($transaction['id'] ?? '');
        if ($id === '' || !ctype_digit($id)) {
            error_log('FedaPay: la création de transaction n’a pas retourné d’identifiant valide.');
            return ['ok' => false, 'url' => null, 'erreur' => 'FedaPay n’a pas retourné de transaction valide.'];
        }
        if (isset($transaction['amount']) && (int) $transaction['amount'] !== (int) $commande['prix']) {
            error_log('FedaPay: le montant retourné après création ne correspond pas à la commande.');
            return ['ok' => false, 'url' => null, 'erreur' => 'Le montant transmis à FedaPay ne correspond pas à la commande.'];
        }

        $enregistre = mtx_commande_maj($ref, ['transac' => $id]);
        if (!$enregistre) {
            error_log('FedaPay: impossible d’associer la transaction à la commande ' . $ref . '.');
            return ['ok' => false, 'url' => null, 'erreur' => 'Impossible d’enregistrer la transaction. Contacte-nous avant de réessayer.'];
        }
    }

    $jeton = mtx_fedapay_requete('POST', '/transactions/' . rawurlencode($id) . '/token', []);
    if (!$jeton['ok']) return ['ok' => false, 'url' => null, 'erreur' => $jeton['erreur']];

    $url = (string) ($jeton['donnees']['url'] ?? '');
    if (!mtx_fedapay_url_valide($url)) {
        error_log('FedaPay: URL de paiement absente ou provenant d’un hôte non autorisé.');
        return ['ok' => false, 'url' => null, 'erreur' => 'FedaPay n’a pas retourné de lien de paiement valide.'];
    }

    return ['ok' => true, 'url' => $url, 'erreur' => null];
}

function mtx_fedapay_verifier(array $commande): array {
    $id = trim((string) ($commande['transac'] ?? ''));
    if (!mtx_paiement_pret() || $id === '' || !ctype_digit($id)) {
        return ['ok' => false, 'statut' => '', 'montant' => 0, 'erreur' => 'Transaction FedaPay introuvable.'];
    }

    $resultat = mtx_fedapay_requete('GET', '/transactions/' . rawurlencode($id));
    if (!$resultat['ok']) {
        return ['ok' => false, 'statut' => '', 'montant' => 0, 'erreur' => $resultat['erreur']];
    }

    $transaction = $resultat['donnees'];
    $montant = isset($transaction['amount']) ? (int) $transaction['amount'] : 0;
    $statut = (string) ($transaction['status'] ?? '');
    $metadata = $transaction['custom_metadata'] ?? null;

    if (isset($transaction['id']) && (string) $transaction['id'] !== $id) {
        return ['ok' => false, 'statut' => $statut, 'montant' => $montant, 'erreur' => 'La transaction retournée ne correspond pas à la commande.'];
    }
    if (is_array($metadata) && isset($metadata['order_ref']) && !hash_equals((string) $commande['ref'], (string) $metadata['order_ref'])) {
        return ['ok' => false, 'statut' => $statut, 'montant' => $montant, 'erreur' => 'La référence de commande ne correspond pas à la transaction.'];
    }
    if (isset($transaction['currency']) && is_array($transaction['currency'])
        && isset($transaction['currency']['iso']) && strtoupper((string) $transaction['currency']['iso']) !== 'XOF') {
        error_log('FedaPay: devise incorrecte pour la commande ' . $commande['ref'] . '.');
        return ['ok' => false, 'statut' => $statut, 'montant' => $montant, 'erreur' => 'La devise reçue ne correspond pas à la commande.'];
    }
    if ($statut === 'approved' && $montant !== (int) $commande['prix']) {
        error_log('FedaPay: montant incorrect pour la commande ' . $commande['ref'] . '.');
    }

    return [
        'ok' => $statut === 'approved' && $montant === (int) $commande['prix'],
        'statut' => $statut,
        'montant' => $montant,
        'terminal' => in_array($statut, ['declined', 'canceled', 'cancelled', 'expired'], true),
        'erreur' => null,
    ];
}
