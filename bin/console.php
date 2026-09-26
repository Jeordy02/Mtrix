#!/usr/bin/env php
<?php
/**
 * Console M'trix - Utilitaires
 * Usage: php bin/console.php <command> [options]
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/autoload.php';

$command = $argv[1] ?? 'help';
$args = array_slice($argv, 2);

try {
    switch ($command) {
        case 'init':
            // php bin/console.php init [--reset]
            require 'init-db.php';
            break;

        case 'admin:hash':
            // php bin/console.php admin:hash <password>
            $password = $args[0] ?? null;
            if (!$password) {
                echo "Usage: php bin/console.php admin:hash <password>\n";
                exit(1);
            }
            echo Security::hashPassword($password) . "\n";
            break;

        case 'jwt:secret':
            // Générer une clé JWT aléatoire
            echo bin2hex(random_bytes(32)) . "\n";
            break;

        case 'token:generate':
            // Générer un token aléatoire
            echo Security::generateToken(32) . "\n";
            break;

        case 'db:status':
            // Vérifier l'état de la DB
            $db = Database::getInstance();
            echo "✅ Connexion DB: OK\n";

            $count = $db->count('SELECT COUNT(*) FROM commandes');
            echo "   Commandes: $count\n";

            $paliers = $db->query('SELECT COUNT(*) as cnt FROM paliers WHERE ouvert = 1');
            echo "   Paliers ouverts: " . $paliers[0]['cnt'] . "\n";
            break;

        case 'queue:status':
            // État de la file d'attente
            $db = Database::getInstance();
            $waiting = $db->count('SELECT COUNT(*) FROM file_attente WHERE etat = "attente"');
            $promoted = $db->count('SELECT COUNT(*) FROM file_attente WHERE etat = "promue"');

            echo "📊 File d'attente:\n";
            echo "  En attente: $waiting\n";
            echo "  Promues: $promoted\n";
            break;

        case 'expire:check':
            // Vérifier les expirations
            $commande = new Commande();
            $commande->expireOldReservations();

            $file = new FileAttente();
            $file->expireOld(30);

            echo "✅ Expirations vérifiées\n";
            break;

        case 'stats:reset':
            // Réinitialiser les stats (test)
            $db = Database::getInstance();
            $db->update(
                'etat_global',
                ['valeur' => '0'],
                'cle IN ("total_vendues", "encaisse_total")'
            );
            echo "✅ Stats réinitialisées\n";
            break;

        case 'help':
        default:
            echo "M'trix Console v1\n\n";
            echo "Commands:\n";
            echo "  init [--reset]         Initialiser la base de données\n";
            echo "  admin:hash <pwd>       Générer un hash bcrypt\n";
            echo "  jwt:secret             Générer une clé JWT\n";
            echo "  token:generate         Générer un token aléatoire\n";
            echo "  db:status              Vérifier l'état de la DB\n";
            echo "  queue:status           État de la file d'attente\n";
            echo "  expire:check           Vérifier les expirations\n";
            echo "  stats:reset            Réinitialiser les stats (test)\n";
            echo "  help                   Afficher ce message\n";
            break;
    }
} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
