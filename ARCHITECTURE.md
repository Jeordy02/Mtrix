# Architecture M'trix

## 🎯 Principes de conception

**M'trix** est conçu selon une architecture **backend/frontend séparé** avec :
- Backend API (PHP/MySQL) accessible via HTTP
- Frontend statique (HTML/CSS/JS) qui consomme l'API
- Pas de dépendances entre frontend et backend
- Sécurité par JWT et signature de webhooks
- Scalabilité par base de données persistante

---

## 📁 Structure des fichiers

```
mtrix-refactor/
├── public/                  # ← Web root
│   ├── index.php           # Routeur API unique
│   └── .htaccess           # Routing (mod_rewrite)
│
├── backend/
│   ├── utils/              # Fonctions utilitaires
│   │   ├── Database.php    # Singleton PDO
│   │   ├── Security.php    # Hash, JWT, validation
│   │   ├── Logger.php      # Logs et audit
│   │   └── ApiResponse.php # Réponses JSON
│   │
│   ├── models/             # Classes métier (ORM-less)
│   │   ├── Commande.php
│   │   ├── Palier.php
│   │   ├── FileAttente.php
│   │   └── Paiement.php
│   │
│   ├── middleware/         # Middlewares
│   │   └── auth.php        # Authentification admin
│   │
│   └── api/                # Logique métier (à venir)
│       ├── commandes.php
│       ├── paiements.php
│       └── admin.php
│
├── config/
│   ├── config.php          # Charger .env
│   ├── constants.php       # Constantes métier
│   ├── autoload.php        # Autoloader PSR-0
│   └── schema.sql          # Schéma BD
│
├── frontend/               # À venir
│   ├── index.html
│   ├── commander.html
│   └── admin.html
│
├── storage/
│   ├── logs/               # Logs applicatifs
│   └── data/               # Données temporaires
│
├── bin/                    # Scripts CLI
│   ├── console.php         # Commandes utiles
│   └── init-db.php         # Initialiser la BD
│
├── .env                    # Configuration sécurisée
├── .gitignore              # Ignorer secrets
├── README.md               # Vue d'ensemble
├── SETUP.md                # Installation
└── ARCHITECTURE.md         # Ce fichier
```

---

## 🔄 Flux de données

### 1. Réservation de place

```
Client
  ↓ POST /api/reserve
  ↓ {palier_id: 1, couleur: "noir"}
Backend (public/index.php)
  ↓ Valide les données
  ↓ Commande::create() → INSERT commandes
  ↓ Vérifier si places disponibles
  ├─ OUI → Retourner {ref, prix, statut: "attente"}
  └─ NON → FileAttente::enqueue() + Retourner {position, jeton}
Client (sessionStorage)
  ↓ Enregistre {ref, jeton}
  ↓ Affiche écran de paiement ou file d'attente
```

### 2. Paiement via FedaPay

```
Client
  ↓ Clique "Payer"
  ↓ Redirect vers FedaPay.com
FedaPay
  ↓ Client valide paiement
  ↓ FedaPay appelle /api/webhooks/fedapay
Backend
  ↓ Vérifie la signature
  ↓ Paiement::create() → INSERT paiements (statut: en_cours)
  ↓ Commande::updateStatut("a_confirmer")
  ↓ Stocke le webhook dans webhooks table
Admin (Dashboard)
  ↓ Voit "Paiement en attente - $ref"
  ↓ Clique "Confirmer"
Backend
  ↓ Commande::updateStatut("payee")
  ↓ Assigne une place
  ↓ Envoie email via Brevo
  ↓ Logger::payment()
Client
  ↓ Formule "Infos livraison" reçoit les données
```

### 3. File d'attente (palier plein)

```
Palier 1 = 1 place
  Client A clique → place réservée (attente 5 min)
  Client B clique → ajouté à file_attente (position 1)
  Client C clique → ajouté à file_attente (position 2)
  
Après 5 min de Client A (pas payé)
  Commande A → statut "expiree"
  Palier 1 → une place se libère
  FileAttente::promote(B) → B peut payer au palier 1
  Client B → reçoit jeton validé (appel /api/attente?jeton=xxx)
```

---

## 🗄️ Modèles de données

### Commande
```php
[
  'id' => 1,
  'ref' => 'MTX-A2B3C',
  'palier_id' => 1,
  'couleur' => 'noir',
  'prix' => 3500,
  'statut' => 'payee',
  'nom' => 'Jean Dupont',
  'tel' => '+229...',
  'email' => 'jean@example.com',
  'reserve_jusqua' => '2026-10-01 14:35:00',
]
```

### Palier
```php
[
  'id' => 1,
  'numero' => 1,
  'prix' => 3500,
  'places' => 1,
  'restant' => 1,
  'ouvert' => false,  // Admin contrôle
]
```

### Paiement
```php
[
  'id' => 1,
  'commande_id' => 1,
  'montant' => 3500,
  'statut' => 'reussie',
  'fedapay_id' => 'pay_xxxxx',
  'fedapay_statut' => 'approved',
]
```

---

## 🔐 Sécurité

### Niveau 1 : Validation entrante
```php
// Toujours valider au point d'entrée
$data = json_decode(file_get_contents('php://input'), true);
ApiResponse::validate($data, ['palier_id', 'couleur']);
```

### Niveau 2 : Authentification admin
```php
// JWT + session
$auth = new Auth();
$admin = $auth->require();  // Lève erreur si pas autorisé

// Ou login
$token = $auth->login($password);  // Vérifie bcrypt
```

### Niveau 3 : Signature FedaPay
```php
// Webhook = signature HMAC-SHA512
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_FEDAPAY_SIGNATURE'];

if (!Security::verifyFedaPaySignature($payload, $signature)) {
    // Rejeter
}
```

### Niveau 4 : SQL Injections
```php
// Toujours prepared statements
$db->query('SELECT * FROM commandes WHERE ref = ?', [$ref]);
// Jamais : "SELECT * FROM commandes WHERE ref = '$ref'"
```

### Niveau 5 : XSS
```php
// Toujours echapper les données affichées
echo Security::sanitizeHtml($data);
```

### Niveau 6 : CORS
```php
// Défini dans config.php / .env
header('Access-Control-Allow-Origin: ' . CORS_ORIGINS);
// Frontend ne peut consommer API que si origine whitelistée
```

---

## 🚀 Flow de déploiement

### Local (XAMPP)
```bash
php -S localhost:8099 -t public/
# API sur http://localhost:8099/api
# Frontend sert depuis public/ (HTML statique)
```

### Production (Render)
```
Render → Node.js/PHP → MySQL (Railway/Render)
```

1. Git push
2. Render build (`php -S 0.0.0.0:10000 -t public/`)
3. Render déploie
4. FedaPay webhook → `https://app.onrender.com/api/webhooks/fedapay`

---

## 📡 API Endpoints - Vue globale

### Public
```
POST   /api/reserve              Créer réservation
GET    /api/attente              Vérifier file (polling client)
POST   /api/webhooks/fedapay     Callback paiement (FedaPay)
```

### Admin (requires JWT)
```
POST   /api/admin/login          Connexion admin
POST   /api/admin/logout         Déconnexion
POST   /api/admin/palier/launch  Lancer un palier
GET    /api/admin/stats          Statistiques
GET    /api/admin/commandes      Lister commandes
GET    /api/admin/logs           Logs d'audit
```

---

## 🔧 Patterns utilisés

### Singleton (Database)
```php
$db = Database::getInstance();
// Une seule connexion PDO par request
```

### Factory (Models)
```php
$commande = new Commande();
$palier = new Palier();
// Chaque modèle a sa own DB instance
```

### JWT (Stateless auth)
```php
$token = Security::createJWT(['admin_id' => 1]);
// Token = header.payload.signature
// Pas de session server-side
```

### Audit Trail (Compliance)
```php
Logger::audit('action', 'reference', ['details' => ...]);
// Tous les actes admin enregistrés
```

---

## ⚡ Performance

- **Indexing** : Clés primaires + indexes sur statut, email, ref
- **Caching** : Stats cachées 5 min en etat_global
- **Locks** : Flock() pour éviter race conditions sur places
- **Lazy loading** : Modèles chargent données à la demande

---

## 🧪 Testing

Chaque modèle peut être testé en isolation :

```bash
# Test DB
php -r "require 'config/config.php'; $db = Database::getInstance(); echo 'OK';"

# Test models
php -r "
require 'config/autoload.php';
\$c = new Commande();
\$cmd = \$c->create(1, 'noir', 3500);
var_dump(\$cmd);
"

# Test security
php -r "
require 'config/autoload.php';
echo Security::generateToken(32) . PHP_EOL;
"
```

---

## 📚 Conventions de code

### Nommage
- Tables : **pluriel** (`commandes`, `paliers`)
- Colonnes : **snake_case** (`palier_id`, `reserve_jusqua`)
- Méthodes : **camelCase** (`getById()`, `updateStatut()`)
- Classes : **PascalCase** (`Commande`, `FileAttente`)

### Styles
- Pas de framework ORM (Eloquent, Doctrine) = **Simplicité**
- PDO + prepared statements = **Sécurité**
- Logique dans les modèles, pas partout = **Maintenabilité**

### Comments
- Uniquement sur le **pourquoi**, pas le **quoi**
- Code lisible = meilleure doc qu'un commentaire

---

## 🔗 Ressources

- **PHPDoc** : https://www.phpdoc.org
- **PDO** : https://www.php.net/manual/en/class.pdo
- **JWT** : https://jwt.io
- **FedaPay API** : https://docs.fedapay.com
