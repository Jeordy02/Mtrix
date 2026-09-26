<?php
/**
 * Modèle Commande
 * Gère les réservations et commandes
 */

class Commande
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer une nouvelle commande
     */
    public function create($palier_id, $couleur, $prix)
    {
        $ref = Security::generateOrderRef();
        $jeton = Security::generateToken(32);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $id = $this->db->insert('commandes', [
            'ref' => $ref,
            'palier_id' => $palier_id,
            'couleur' => $couleur,
            'prix' => $prix,
            'statut' => 'attente',
            'place' => 0, // Sera assignée lors du paiement confirmé
            'ip_adresse' => $ip,
            'user_agent' => $userAgent,
            'reserve_jusqua' => date('Y-m-d H:i:s', time() + 300), // +5 min
        ]);

        return [
            'id' => $id,
            'ref' => $ref,
            'palier_id' => $palier_id,
            'prix' => $prix,
        ];
    }

    /**
     * Récupérer une commande par référence
     */
    public function getByRef($ref)
    {
        return $this->db->queryOne(
            'SELECT * FROM commandes WHERE ref = ?',
            [$ref]
        );
    }

    /**
     * Récupérer une commande par ID
     */
    public function getById($id)
    {
        return $this->db->queryOne(
            'SELECT * FROM commandes WHERE id = ?',
            [$id]
        );
    }

    /**
     * Mettre à jour le statut
     */
    public function updateStatut($id, $statut)
    {
        return $this->db->update(
            'commandes',
            ['statut' => $statut],
            'id = ?',
            [$id]
        );
    }

    /**
     * Mettre à jour les infos de livraison
     */
    public function updateDeliveryInfo($id, $nom, $tel, $email, $lieu, $note = null)
    {
        return $this->db->update(
            'commandes',
            [
                'nom' => $nom,
                'tel' => $tel,
                'email' => $email,
                'lieu' => $lieu,
                'note' => $note,
                'complete' => true,
            ],
            'id = ?',
            [$id]
        );
    }

    /**
     * Lister toutes les commandes (admin)
     */
    public function listAll($limit = 100, $offset = 0)
    {
        return $this->db->query(
            'SELECT * FROM commandes ORDER BY creer_le DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Compter les commandes d'un palier
     */
    public function countByPalier($palier_id)
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM commandes WHERE palier_id = ? AND statut IN ("a_confirmer", "payee")',
            [$palier_id]
        );
    }

    /**
     * Vérifier les expirations (5 min)
     */
    public function expireOldReservations()
    {
        $sql = "
            UPDATE commandes
            SET statut = 'expiree'
            WHERE statut = 'attente'
            AND reserve_jusqua < NOW()
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }

    /**
     * Obtenir les statuts possibles
     */
    public static function getStatuts()
    {
        return ['attente', 'a_confirmer', 'payee', 'livree', 'annulee', 'expiree'];
    }
}
