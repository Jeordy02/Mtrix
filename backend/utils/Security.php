<?php
/**
 * Utilitaires de sécurité
 * Hash, validation, tokens JWT, sanitization
 */

class Security
{
    /**
     * Hash un mot de passe avec bcrypt
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Vérifier un mot de passe
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Générer un token aléatoire sécurisé
     */
    public static function generateToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Générer un JWT
     */
    public static function createJWT($payload = [], $expiresIn = null)
    {
        $expiresIn = $expiresIn ?? JWT_EXPIRY;
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;

        $headerEncoded = self::base64urlEncode(json_encode($header));
        $payloadEncoded = self::base64urlEncode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            JWT_SECRET,
            true
        );
        $signatureEncoded = self::base64urlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    /**
     * Vérifier et décoder un JWT
     */
    public static function verifyJWT($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        $signature = hash_hmac(
            'sha256',
            "$headerEncoded.$payloadEncoded",
            JWT_SECRET,
            true
        );
        $signatureEncodedExpected = self::base64urlEncode($signature);

        if (!hash_equals($signatureEncoded, $signatureEncodedExpected)) {
            return null;
        }

        $payload = json_decode(self::base64urlDecode($payloadEncoded), true);

        if (!$payload || ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Valider une adresse email
     */
    public static function validateEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valider un numéro de téléphone (format simple)
     */
    public static function validatePhone($phone)
    {
        // Enlever espaces, tirets, +
        $clean = preg_replace('/[^0-9]/', '', $phone);
        // Au moins 8 chiffres
        return strlen($clean) >= 8;
    }

    /**
     * Vérifier la signature d'un webhook FedaPay
     */
    public static function verifyFedaPaySignature($payload, $signature)
    {
        $expected = hash_hmac(
            'sha512',
            $payload,
            FEDAPAY_WEBHOOK_SECRET
        );
        return hash_equals($expected, $signature);
    }

    /**
     * Nettoyer une chaîne pour éviter XSS
     */
    public static function sanitizeHtml($str)
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valider une référence commande (format MTX-XXXXX)
     */
    public static function validateOrderRef($ref)
    {
        return preg_match('/^MTX-[A-Z0-9]{5,}$/', $ref) === 1;
    }

    /**
     * Valider un UUID
     */
    public static function validateUUID($uuid)
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }

    /**
     * Helpers base64url (JWT)
     */
    private static function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 4 - (strlen($data) % 4)));
    }

    /**
     * Générer une référence commande unique (MTX-XXXXX)
     */
    public static function generateOrderRef()
    {
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        return "MTX-$random";
    }

    /**
     * Valider le CORS pour une requête
     */
    public static function validateCORS($origin)
    {
        $allowed = explode(',', CORS_ORIGINS);
        $allowed = array_map('trim', $allowed);
        return in_array($origin, $allowed, true);
    }
}
