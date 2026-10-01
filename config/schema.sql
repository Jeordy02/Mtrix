-- M'trix Database Schema
-- Toutes les tables nécessaires pour une prévente sécurisée et scalable

-- --- 0. MTX_SITE_DATA (stockage du frontend historique) ---
-- Le site qui tourne aujourd'hui (public/*.php via includes/store.php) lit et
-- écrit ici, sous forme de JSON indexé par clé : etat, commandes, admin,
-- attente_file, attente_promues. Les tables normalisées ci-dessous sont celles
-- du backend refactorisé, que les endpoints API consommeront ; tant qu'ils ne
-- sont pas écrits, c'est cette table seule qui porte l'état du site.
-- Sans elle, les trois pages publiques échouent sur une PDOException.
CREATE TABLE mtx_site_data (
  data_key   VARCHAR(64) NOT NULL,
  payload    LONGTEXT    NOT NULL COMMENT 'Document JSON',
  updated_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (data_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 1. PALIERS (Tiers de prix) ---
CREATE TABLE paliers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  numero INT NOT NULL UNIQUE,
  prix INT NOT NULL COMMENT 'Prix en FCFA',
  places INT NOT NULL COMMENT 'Nombre total de places',
  restant INT NOT NULL COMMENT 'Places restantes',
  ouvert BOOLEAN DEFAULT FALSE COMMENT 'Débloqué par admin ?',
  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ouvert)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 2. COMMANDES (Orders) ---
CREATE TABLE commandes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  ref VARCHAR(20) UNIQUE NOT NULL COMMENT 'MTX-XXXXX (unique ref client)',

  -- Réservation
  palier_id INT NOT NULL,
  place INT NOT NULL COMMENT 'Numéro de place (01-15)',
  statut ENUM('attente', 'a_confirmer', 'payee', 'livree', 'annulee', 'expiree') DEFAULT 'attente',

  -- Couleur/produit
  couleur VARCHAR(30) NOT NULL,

  -- Paiement
  prix INT NOT NULL,
  paye_le TIMESTAMP NULL,
  transac VARCHAR(100) COMMENT 'Numéro de transaction (FedaPay)',
  fedapay_order_id VARCHAR(100) COMMENT 'ID commande FedaPay',

  -- Client (rempli après paiement confirmé)
  nom VARCHAR(100),
  tel VARCHAR(25),
  email VARCHAR(120),
  lieu VARCHAR(200) COMMENT 'Quartier et repère',
  note TEXT COMMENT 'Instructions livraison',
  complete BOOLEAN DEFAULT FALSE COMMENT 'Formulaire complet ?',
  mail_envoye BOOLEAN DEFAULT FALSE,

  -- Timing
  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reserve_jusqua TIMESTAMP NULL COMMENT 'Expiration réservation (5 min)',

  -- Tracking
  ip_adresse VARCHAR(45),
  user_agent VARCHAR(255),

  FOREIGN KEY (palier_id) REFERENCES paliers(id),
  INDEX (statut),
  INDEX (email),
  INDEX (tel),
  INDEX (ref),
  INDEX (reserve_jusqua)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 3. FILE D'ATTENTE ---
CREATE TABLE file_attente (
  id INT PRIMARY KEY AUTO_INCREMENT,
  commande_id INT NOT NULL,
  position INT NOT NULL,
  jeton VARCHAR(100) UNIQUE NOT NULL COMMENT 'Jeton unique pour polling client',
  etat ENUM('attente', 'promue', 'expir', 'servie') DEFAULT 'attente',

  promue_le TIMESTAMP NULL,
  servie_le TIMESTAMP NULL,

  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (commande_id) REFERENCES commandes(id),
  INDEX (jeton),
  INDEX (etat),
  INDEX (position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 4. PAIEMENTS (Historique détaillé) ---
CREATE TABLE paiements (
  id INT PRIMARY KEY AUTO_INCREMENT,
  commande_id INT NOT NULL,

  montant INT NOT NULL,
  devise VARCHAR(3) DEFAULT 'XOF',
  statut ENUM('initiée', 'en_cours', 'reussie', 'refusee', 'timeout') DEFAULT 'initiée',

  -- FedaPay
  fedapay_id VARCHAR(100),
  fedapay_statut VARCHAR(50),

  -- Validation
  valide_le TIMESTAMP NULL,
  valideur VARCHAR(50) COMMENT 'admin ou system',

  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (commande_id) REFERENCES commandes(id),
  INDEX (statut),
  INDEX (fedapay_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 5. ADMIN (Gestion) ---
CREATE TABLE admin_sessions (
  id INT PRIMARY KEY AUTO_INCREMENT,

  token VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,

  derniere_connexion TIMESTAMP NULL,
  ip_derniere VARCHAR(45),

  active BOOLEAN DEFAULT TRUE,

  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX (token),
  INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 6. LOGS D'ADMIN (Audit) ---
CREATE TABLE admin_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,

  action VARCHAR(100) NOT NULL COMMENT 'lancer_palier, confirmer_paiement, etc',
  reference VARCHAR(100),
  details JSON,

  ip_adresse VARCHAR(45),
  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX (action),
  INDEX (creer_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 7. ÉTAT GLOBAL ---
CREATE TABLE etat_global (
  id INT PRIMARY KEY AUTO_INCREMENT,

  cle VARCHAR(100) UNIQUE NOT NULL,
  valeur VARCHAR(500),
  type ENUM('int', 'string', 'json') DEFAULT 'string',

  modifie_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- 8. WEBHOOKS (FedaPay) ---
CREATE TABLE webhooks (
  id INT PRIMARY KEY AUTO_INCREMENT,

  type VARCHAR(100) NOT NULL,
  fedapay_id VARCHAR(100),
  payload JSON,
  traite BOOLEAN DEFAULT FALSE,

  creer_le TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  traite_le TIMESTAMP NULL,

  INDEX (type),
  INDEX (traite),
  INDEX (creer_le)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- INITIAL DATA ---
INSERT INTO etat_global (cle, valeur, type) VALUES
  ('paliers_debloque', '0', 'int'),
  ('total_vendues', '0', 'int'),
  ('encaisse_total', '0', 'int');

INSERT INTO paliers (numero, prix, places, restant, ouvert) VALUES
  (1, 3500, 1, 1, FALSE),
  (2, 4500, 2, 2, FALSE),
  (3, 5000, 2, 2, FALSE),
  (4, 6000, 5, 5, FALSE),
  (5, 7000, 5, 5, FALSE);
