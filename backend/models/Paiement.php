<?php
/**
 * Modèle Paiement
 * Gère les paiements et transactions FedaPay
 */

class Paiement
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Créer un paiement en attente
     */
    public function create($commande_id, $montant, $devise = 'XOF')
    {
        $id = $this->db->insert('paiements', [
            'commande_id' => $commande_id,
            'montant' => $montant,
            'devise' => $devise,
            'statut' => 'initiée',
        ]);

        return [
            'id' => $id,
            'montant' => $montant,
            'devise' => $devise,
        ];
    }

    /**
     * Récupérer un paiement par ID
     */
    public function getById($id)
    {
        return $this->db->queryOne(
            'SELECT * FROM paiements WHERE id = ?',
            [$id]
        );
    }

    /**
     * Récupérer le paiement d'une commande
     */
    public function getByCommande($commande_id)
    {
        return $this->db->queryOne(
            'SELECT * FROM paiements WHERE commande_id = ? ORDER BY creer_le DESC LIMIT 1',
            [$commande_id]
        );
    }

    /**
     * Récupérer par ID FedaPay
     */
    public function getByFedaPayId($fedapay_id)
    {
        return $this->db->queryOne(
            'SELECT * FROM paiements WHERE fedapay_id = ?',
            [$fedapay_id]
        );
    }

    /**
     * Mettre à jour le statut FedaPay
     */
    public function updateFedaPayStatus($id, $fedapay_id, $fedapay_statut)
    {
        return $this->db->update(
            'paiements',
            [
                'fedapay_id' => $fedapay_id,
                'fedapay_statut' => $fedapay_statut,
                'statut' => $this->mapFedaPayStatut($fedapay_statut),
            ],
            'id = ?',
            [$id]
        );
    }

    /**
     * Confirmer un paiement
     */
    public function confirm($id, $valideur = 'admin')
    {
        return $this->db->update(
            'paiements',
            [
                'statut' => 'reussie',
                'valide_le' => date('Y-m-d H:i:s'),
                'valideur' => $valideur,
            ],
            'id = ?',
            [$id]
        );
    }

    /**
     * Rejeter un paiement
     */
    public function reject($id)
    {
        return $this->db->update(
            'paiements',
            ['statut' => 'refusee'],
            'id = ?',
            [$id]
        );
    }

    /**
     * Mapper statut FedaPay vers notre système
     */
    private function mapFedaPayStatut($fedapay_statut)
    {
        $mapping = [
            'approved' => 'reussie',
            'pending' => 'en_cours',
            'declined' => 'refusee',
            'failed' => 'refusee',
            'timeout' => 'timeout',
        ];

        return $mapping[$fedapay_statut] ?? 'en_cours';
    }

    /**
     * Lister les paiements (admin)
     */
    public function listAll($limit = 100, $offset = 0)
    {
        return $this->db->query(
            'SELECT p.*, c.ref FROM paiements p JOIN commandes c ON p.commande_id = c.id ORDER BY p.creer_le DESC LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    /**
     * Obtenir les statistiques
     */
    public function getStats()
    {
        $stats = $this->db->queryOne(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN statut = "reussie" THEN montant ELSE 0 END) as encaisse,
                SUM(CASE WHEN statut = "en_cours" THEN montant ELSE 0 END) as en_attente,
                COUNT(CASE WHEN statut = "reussie" THEN 1 END) as reussies,
                COUNT(CASE WHEN statut = "refusee" THEN 1 END) as refusees
            FROM paiements'
        );

        return $stats;
    }
}
