<?php
/**
 * Logger - Enregistrer les événements importants
 */

class Logger
{
    private static $logFile;

    public static function init($path = null)
    {
        if (!$path) {
            $path = dirname(__DIR__) . '/../' . LOG_PATH . '/app.log';
        }

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        self::$logFile = $path;
    }

    /**
     * Enregistrer un log
     */
    public static function log($level, $message, $context = [])
    {
        if (!self::$logFile) {
            self::init();
        }

        if (!self::shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $line = "[$timestamp] [$level] $message $contextStr\n";

        @file_put_contents(self::$logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug($message, $context = [])
    {
        self::log('DEBUG', $message, $context);
    }

    /**
     * Log les actions admin (audit trail)
     */
    public static function audit($action, $reference = null, $details = [])
    {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';

            $db->insert('admin_logs', [
                'action' => $action,
                'reference' => $reference,
                'details' => json_encode($details),
                'ip_adresse' => $ip,
            ]);

            self::info("AUDIT: $action (ref: $reference)");
        } catch (Exception $e) {
            self::error("Audit failed: " . $e->getMessage());
        }
    }

    /**
     * Log les paiements (important)
     */
    public static function payment($action, $commande_ref, $montant, $details = [])
    {
        self::log('PAYMENT', "$action - $commande_ref ($montant F)", $details);
        self::audit("payment_$action", $commande_ref, $details);
    }

    /**
     * Vérifier si le niveau de log doit être enregistré
     */
    private static function shouldLog($level)
    {
        $levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
        $currentLevel = array_search(LOG_LEVEL, $levels) ?: 1;
        $logLevel = array_search($level, $levels) ?: 1;

        return $logLevel >= $currentLevel;
    }

    /**
     * Vider les logs (admin)
     */
    public static function clear()
    {
        if (self::$logFile && file_exists(self::$logFile)) {
            file_put_contents(self::$logFile, '');
        }
    }

    /**
     * Lire les derniers logs
     */
    public static function tail($lines = 50)
    {
        if (!self::$logFile || !file_exists(self::$logFile)) {
            return [];
        }

        $file = new SplFileObject(self::$logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $end = $file->key();

        $start = max(0, $end - $lines);
        $file->seek($start);

        $logs = [];
        while (!$file->eof() && $file->key() <= $end) {
            $logs[] = trim($file->current());
            $file->next();
        }

        return array_filter($logs);
    }
}

// Initialiser au chargement
Logger::init();
