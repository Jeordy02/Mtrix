<?php
/**
 * Autoloader - charge automatiquement les classes
 */

$base = dirname(__DIR__);

// Dossiers à auto-charger
$paths = [
    'backend/utils',
    'backend/models',
    'backend/middleware',
    'backend/api',
];

spl_autoload_register(function ($class) use ($base, $paths) {
    foreach ($paths as $path) {
        $file = "$base/$path/$class.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Charger le .env
$env_file = "$base/.env";
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            putenv("$key=$value");
        }
    }
}

// Charger les constants
require_once "$base/config/constants.php";
