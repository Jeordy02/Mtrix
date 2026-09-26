<?php
/**
 * Middleware d'authentification
 */

class Auth
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Vérifier si l'utilisateur est admin (via JWT)
     */
    public function check()
    {
        $token = $this->getToken();

        if (!$token) {
            return false;
        }

        $payload = Security::verifyJWT($token);

        if (!$payload || !isset($payload['admin_id'])) {
            return false;
        }

        return $payload;
    }

    /**
     * Middleware: vérifier l'authentification
     */
    public function require()
    {
        $payload = $this->check();

        if (!$payload) {
            ApiResponse::unauthorized('Invalid or missing token');
        }

        return $payload;
    }

    /**
     * Obtenir le token du header
     */
    private function getToken()
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (preg_match('/Bearer\s+(.+)/', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Créer une session admin
     */
    public function login($password)
    {
        $hash = ADMIN_PASSWORD_HASH;

        if (!Security::verifyPassword($password, $hash)) {
            return null;
        }

        $token = Security::createJWT(['admin_id' => 1]);

        // Enregistrer la session
        $this->db->insert('admin_sessions', [
            'token' => $token,
            'password_hash' => $hash,
            'derniere_connexion' => date('Y-m-d H:i:s'),
            'ip_derniere' => $_SERVER['REMOTE_ADDR'] ?? '',
            'active' => true,
        ]);

        Logger::audit('admin_login', 'admin', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);

        return $token;
    }

    /**
     * Logout
     */
    public function logout($token)
    {
        $this->db->update(
            'admin_sessions',
            ['active' => false],
            'token = ?',
            [$token]
        );

        Logger::audit('admin_logout', 'admin');
    }
}
