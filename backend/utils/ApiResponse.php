<?php
/**
 * ApiResponse - Réponses standardisées
 */

class ApiResponse
{
    /**
     * Succès
     */
    public static function success($data = [], $message = 'Success', $code = 200)
    {
        self::json($code, [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Erreur
     */
    public static function error($message, $code = 400, $details = null)
    {
        self::json($code, [
            'status' => 'error',
            'message' => $message,
            'details' => $details,
        ]);
    }

    /**
     * Non autorisé
     */
    public static function unauthorized($message = 'Unauthorized')
    {
        self::error($message, 401);
    }

    /**
     * Interdit
     */
    public static function forbidden($message = 'Forbidden')
    {
        self::error($message, 403);
    }

    /**
     * Non trouvé
     */
    public static function notFound($message = 'Not found')
    {
        self::error($message, 404);
    }

    /**
     * Erreur interne
     */
    public static function serverError($message = 'Internal server error')
    {
        self::error($message, 500);
    }

    /**
     * Non implémenté
     */
    public static function notImplemented($message = 'Not implemented')
    {
        self::error($message, 501);
    }

    /**
     * Valider les données (simple validation)
     */
    public static function validate($data, $required = [])
    {
        foreach ($required as $key) {
            if (!isset($data[$key]) || empty($data[$key])) {
                self::error("Missing required field: $key", 400);
            }
        }

        return true;
    }

    /**
     * Envoyer une réponse JSON et quitter
     */
    private static function json($code, $data)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Paginer une réponse
     */
    public static function paginate($items, $page = 1, $perPage = 50)
    {
        $total = count($items);
        $pages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'has_next' => $page < $pages,
            'has_prev' => $page > 1,
        ];
    }
}
