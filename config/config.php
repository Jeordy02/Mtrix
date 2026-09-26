<?php
/**
 * Configuration globale
 * Charge les variables .env et les rend disponibles
 */

class Config
{
    private static $vars = [];
    private static $loaded = false;

    /**
     * Charger le fichier .env
     */
    public static function load($path = null)
    {
        if (self::$loaded) return;

        $file = $path ?? dirname(__DIR__) . '/.env';

        if (!file_exists($file)) {
            throw new Exception("Fichier .env manquant: $file");
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Supprimer les guillemets
            if (($value[0] ?? '') === '"' && ($value[-1] ?? '') === '"') {
                $value = substr($value, 1, -1);
            }

            self::$vars[$key] = $value;
        }

        self::$loaded = true;
    }

    /**
     * Récupérer une variable
     */
    public static function get($key, $default = null)
    {
        return self::$vars[$key] ?? $default;
    }

    /**
     * Tous les paramètres (attention: sensible!)
     */
    public static function all()
    {
        return self::$vars;
    }

    /**
     * Tester si charge
     */
    public static function has($key)
    {
        return isset(self::$vars[$key]);
    }
}

// Charger au démarrage
try {
    Config::load();
} catch (Exception $e) {
    die("Config Error: " . $e->getMessage());
}

// Raccourcis globaux pour les clés les plus fréquentes
define('APP_NAME', Config::get('APP_NAME', 'M\'trix'));
define('APP_ENV', Config::get('APP_ENV', 'production'));
define('APP_DEBUG', Config::get('APP_DEBUG', 'false') === 'true');
define('APP_URL', Config::get('APP_URL', 'http://localhost'));

define('DB_HOST', Config::get('DB_HOST', 'localhost'));
define('DB_PORT', Config::get('DB_PORT', 3306));
define('DB_NAME', Config::get('DB_NAME', 'mtrix_prod'));
define('DB_USER', Config::get('DB_USER', 'root'));
define('DB_PASSWORD', Config::get('DB_PASSWORD', ''));
define('DB_CHARSET', Config::get('DB_CHARSET', 'utf8mb4'));

define('FEDAPAY_API_KEY', Config::get('FEDAPAY_API_KEY'));
define('FEDAPAY_WEBHOOK_SECRET', Config::get('FEDAPAY_WEBHOOK_SECRET'));

define('MAIL_HOST', Config::get('MAIL_HOST'));
define('MAIL_PORT', Config::get('MAIL_PORT', 587));
define('MAIL_PASSWORD', Config::get('MAIL_PASSWORD'));

define('JWT_SECRET', Config::get('JWT_SECRET', 'default-secret-change-me'));
define('JWT_EXPIRY', Config::get('JWT_EXPIRY', 3600));

define('LOG_LEVEL', Config::get('LOG_LEVEL', 'info'));
define('LOG_PATH', Config::get('LOG_PATH', 'storage/logs'));
