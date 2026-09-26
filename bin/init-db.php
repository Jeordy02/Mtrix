<?php
/**
 * Script d'initialisation de la base de données
 * Usage: php bin/init-db.php [--reset]
 *
 * --reset : Vider et recréer toutes les tables (ATTENTION)
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/autoload.php';

$reset = isset($argv[1]) && $argv[1] === '--reset';

if ($reset) {
    echo "⚠️  ATTENTION: Vous allez vider TOUTE la base de données.\n";
    echo "Tapez 'OUI' en majuscules pour confirmer : ";
    $confirm = trim(fgets(STDIN));

    if ($confirm !== 'OUI') {
        echo "❌ Annulé.\n";
        exit(1);
    }
}

try {
    $db = Database::getInstance();
    $pdo = $db->getPDO();

    if ($reset) {
        echo "🗑️  Suppression des tables...\n";
        $tables = [
            'webhooks',
            'etat_global',
            'admin_logs',
            'admin_sessions',
            'paiements',
            'file_attente',
            'commandes',
            'paliers',
        ];

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS $table");
            echo "  ✓ $table\n";
        }
    }

    echo "📋 Création des tables...\n";

    $schema = file_get_contents(dirname(__DIR__) . '/config/schema.sql');

    // Exécuter les requêtes du schema
    $statements = array_filter(array_map('trim', explode(';', $schema)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }

    echo "✅ Base de données initialisée avec succès!\n";
    echo "\n📊 État initial :\n";

    // Afficher un résumé
    $paliers = $db->query('SELECT numero, prix, places FROM paliers');
    echo "  Paliers :\n";
    foreach ($paliers as $p) {
        echo "    - Palier {$p['numero']} : {$p['prix']} F ({$p['places']} places)\n";
    }

    $count = $db->count('SELECT COUNT(*) FROM commandes');
    echo "  Commandes : $count\n";

    echo "\n✨ Prêt pour démarrer!\n";

} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    exit(1);
}
