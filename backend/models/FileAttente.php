<?php
/**
 * Modèle FileAttente
 * Gère la file d'attente
 */

class FileAttente
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Ajouter une commande à la file
     */
    public function enqueue($commande_id)
    {
        // Trouver la position suivante
        $lastPosition = $this->db->queryOne(
            'SELECT MAX(position) as max_pos FROM file_attente WHERE etat != "servie"'
        );

        $nextPosition = ($lastPosition['max_pos'] ?? 0) + 1;
        $jeton = Security::generateToken(32);

        $id = $this->db->insert('file_attente', [
            'commande_id' => $commande_id,
            'position' => $nextPosition,
            'jeton' => $jeton,
            'etat' => 'attente',
        ]);

        return [
            'id' => $id,
            'position' => $nextPosition,
            'jeton' => $jeton,
        ];
    }

    /**
     * Vérifier la position d'une commande (polling client)
     */
    public function getStatus($jeton)
    {
        return $this->db->queryOne(
            'SELECT fa.*, c.ref, c.prix FROM file_attente fa JOIN commandes c ON fa.commande_id = c.id WHERE fa.jeton = ?',
            [$jeton]
        );
    }

    /**
     * Promouvoir une commande (quand une place se libère)
     */
    public function promote($id)
    {
        return $this->db->update(
            'file_attente',
            ['etat' => 'promue', 'promue_le' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }

    /**
     * Marquer comme servie (commande payée)
     */
    public function markServed($id)
    {
        return $this->db->update(
            'file_attente',
            ['etat' => 'servie', 'servie_le' => date('Y-m-d H:i:s')],
            'id = ?',
            [$id]
        );
    }

    /**
     * Obtenir la position d'attente suivante
     */
    public function getNextPosition()
    {
        $last = $this->db->queryOne(
            'SELECT MAX(position) as max_pos FROM file_attente WHERE etat = "attente"'
        );
        return ($last['max_pos'] ?? 0) + 1;
    }

    /**
     * Lister la file (admin)
     */
    public function listQueue($limit = 50)
    {
        return $this->db->query(
            'SELECT fa.*, c.ref, c.prix FROM file_attente fa JOIN commandes c ON fa.commande_id = c.id WHERE fa.etat IN ("attente", "promue") ORDER BY fa.position ASC LIMIT ?',
            [$limit]
        );
    }

    /**
     * Compter les gens en attente
     */
    public function countWaiting()
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM file_attente WHERE etat = "attente"'
        );
    }

    /**
     * Marquer les anciennes entrées comme expirées
     */
    public function expireOld($minutes = 30)
    {
        $sql = "
            UPDATE file_attente
            SET etat = 'expir'
            WHERE etat = 'attente'
            AND creer_le < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$minutes]);
    }
}
