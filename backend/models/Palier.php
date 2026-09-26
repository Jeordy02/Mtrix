<?php
/**
 * Modèle Palier
 * Gère les tiers de prix
 */

class Palier
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupérer un palier par ID
     */
    public function getById($id)
    {
        return $this->db->queryOne(
            'SELECT * FROM paliers WHERE id = ?',
            [$id]
        );
    }

    /**
     * Récupérer un palier par numéro (1-5)
     */
    public function getByNumero($numero)
    {
        return $this->db->queryOne(
            'SELECT * FROM paliers WHERE numero = ?',
            [$numero]
        );
    }

    /**
     * Lister tous les paliers
     */
    public function listAll()
    {
        return $this->db->query('SELECT * FROM paliers ORDER BY numero ASC');
    }

    /**
     * Obtenir le palier actif (suivant à acheter)
     */
    public function getActive()
    {
        // Le palier actif = le premier débloqué qui a encore de la place
        return $this->db->queryOne(
            'SELECT * FROM paliers WHERE ouvert = TRUE AND restant > 0 ORDER BY numero ASC LIMIT 1'
        );
    }

    /**
     * Déverrouiller un palier (admin)
     */
    public function unlock($numero)
    {
        return $this->db->update(
            'paliers',
            ['ouvert' => true],
            'numero = ?',
            [$numero]
        );
    }

    /**
     * Verrouiller un palier (admin)
     */
    public function lock($numero)
    {
        return $this->db->update(
            'paliers',
            ['ouvert' => false],
            'numero = ?',
            [$numero]
        );
    }

    /**
     * Réduire le nombre de places restantes
     */
    public function reduceRestant($id, $amount = 1)
    {
        $palier = $this->getById($id);
        if (!$palier) {
            return false;
        }

        $newRestant = max(0, $palier['restant'] - $amount);

        return $this->db->update(
            'paliers',
            ['restant' => $newRestant],
            'id = ?',
            [$id]
        );
    }

    /**
     * Augmenter le nombre de places restantes
     */
    public function increaseRestant($id, $amount = 1)
    {
        $palier = $this->getById($id);
        if (!$palier) {
            return false;
        }

        $newRestant = $palier['restant'] + $amount;

        return $this->db->update(
            'paliers',
            ['restant' => $newRestant],
            'id = ?',
            [$id]
        );
    }

    /**
     * Vérifier si un palier a de la place
     */
    public function hasSpace($id)
    {
        $palier = $this->getById($id);
        return $palier && $palier['restant'] > 0 && $palier['ouvert'];
    }

    /**
     * Obtenir le prix d'un palier
     */
    public function getPrice($id)
    {
        $palier = $this->getById($id);
        return $palier ? $palier['prix'] : null;
    }

    /**
     * Réinitialiser les places (test)
     */
    public function resetRestant($id)
    {
        $palier = $this->getById($id);
        if (!$palier) {
            return false;
        }

        return $this->db->update(
            'paliers',
            ['restant' => $palier['places']],
            'id = ?',
            [$id]
        );
    }
}
