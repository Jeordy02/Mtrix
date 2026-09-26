<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST DEBUG M'TRIX ===\n\n";

// Test 1: Vérifier les chemins
echo "1. Vérification des chemins:\n";
echo "   __DIR__ = " . __DIR__ . "\n";
echo "   config exists? " . (file_exists(__DIR__ . '/../includes/config.php') ? 'OUI' : 'NON') . "\n";
echo "   store exists? " . (file_exists(__DIR__ . '/../includes/store.php') ? 'OUI' : 'NON') . "\n\n";

// Test 2: Charger les includes
echo "2. Chargement des includes:\n";
try {
    require_once __DIR__ . '/../includes/config.php';
    echo "   ✓ config.php chargé\n";
} catch (Exception $e) {
    echo "   ✗ Erreur config.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    require_once __DIR__ . '/../includes/roue.php';
    echo "   ✓ roue.php chargé\n";
} catch (Exception $e) {
    echo "   ✗ Erreur roue.php: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: Vérifier les constantes
echo "\n3. Constantes PHP:\n";
echo "   MTX_WHATSAPP = " . (defined('MTX_WHATSAPP') ? MTX_WHATSAPP : 'NON DÉFINI') . "\n";
echo "   MTX_INSTAGRAM = " . (defined('MTX_INSTAGRAM') ? MTX_INSTAGRAM : 'NON DÉFINI') . "\n";

// Test 4: Appeler les fonctions
echo "\n4. Appels des fonctions:\n";
try {
    $couleurs = mtx_couleurs();
    echo "   ✓ mtx_couleurs(): " . count($couleurs) . " coloris\n";
} catch (Exception $e) {
    echo "   ✗ mtx_couleurs(): " . $e->getMessage() . "\n";
}

try {
    $paliers = mtx_paliers();
    echo "   ✓ mtx_paliers(): " . count($paliers) . " paliers\n";
} catch (Exception $e) {
    echo "   ✗ mtx_paliers(): " . $e->getMessage() . "\n";
}

try {
    $actif = mtx_palier_actif();
    echo "   ✓ mtx_palier_actif(): " . ($actif ? 'OUI' : 'NON') . "\n";
} catch (Exception $e) {
    echo "   ✗ mtx_palier_actif(): " . $e->getMessage() . "\n";
}

echo "\n=== FIN TEST ===\n";
?>
