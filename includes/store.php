<?php
/* M'trix — stockage MySQL/MariaDB des données du site. */

function mtx_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $hote = getenv('DB_HOST');
    $nom = getenv('DB_NAME');
    $utilisateur = getenv('DB_USER');
    $motDePasse = getenv('DB_PASSWORD');
    $port = getenv('DB_PORT');
    $hote = is_string($hote) && trim($hote) !== '' ? trim($hote) : '127.0.0.1';
    $nom = is_string($nom) && trim($nom) !== '' ? trim($nom) : 'mtrix';
    $utilisateur = is_string($utilisateur) ? $utilisateur : '';
    $motDePasse = is_string($motDePasse) ? $motDePasse : '';
    $port = is_string($port) && ctype_digit($port) ? (int) $port : 3306;

    if ($utilisateur === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $nom)) {
        throw new RuntimeException('Configuration MySQL manquante ou invalide. Définis MTX_DB_USER et consulte FEDAPAY_SETUP.md.');
    }

    $dsn = 'mysql:host=' . $hote . ';port=' . $port . ';dbname=' . $nom . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, $utilisateur, $motDePasse, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('M’trix: connexion MySQL impossible: ' . $e->getMessage());
        throw new RuntimeException('La base de données est inaccessible. Vérifie la configuration MySQL du serveur.');
    }
    return $pdo;
}

function mtx_read(string $nom, array $defaut = []): array {
    $requete = mtx_db()->prepare('SELECT payload FROM mtx_site_data WHERE data_key = :data_key');
    $requete->execute(['data_key' => $nom]);
    $json = $requete->fetchColumn();
    if ($json === false) return $defaut;

    $donnees = json_decode((string) $json, true);
    if (!is_array($donnees)) {
        error_log('M’trix: données JSON invalides dans MySQL pour la clé ' . $nom . '.');
        throw new RuntimeException('Les données du site sont illisibles dans la base de données.');
    }
    return $donnees;
}

function mtx_write(string $nom, array $data): void {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Les données du site n’ont pas pu être encodées pour la base de données.');
    }

    $requete = mtx_db()->prepare(
        'INSERT INTO mtx_site_data (data_key, payload) VALUES (:data_key, :payload)
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP'
    );
    $requete->execute(['data_key' => $nom, 'payload' => $json]);
}

function h($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function fcfa($n): string { return number_format((float) $n, 0, ',', ' ') . ' FCFA'; }
function mtx_uid(): string { return substr(bin2hex(random_bytes(6)), 0, 10); }

/* Verrou MySQL partagé par toutes les requêtes du site. */
function mtx_verrou(callable $fn) {
    $pdo = mtx_db();
    $requete = $pdo->query("SELECT GET_LOCK('mtrix_site_data', 15)");
    if ((int) $requete->fetchColumn() !== 1) {
        throw new RuntimeException('Impossible de verrouiller les données du site. Réessaie dans un instant.');
    }

    try {
        return $fn();
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('mtrix_site_data')");
    }
}
