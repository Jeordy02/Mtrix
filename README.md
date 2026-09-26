# M'trix — Architecture Refactorisée

## 🎯 Vue d'ensemble

**M'trix** est une plateforme de prévente de lunettes de soleil avec système de paliers de prix et file d'attente.

- **Frontend** : HTML, CSS, JavaScript (Client-side)
- **Backend** : API REST en PHP (Server-side)
- **Data** : MySQL/MariaDB (Base de données)
- **Config** : `.env` (Variables d'environnement sécurisées)

---

## 📁 Structure du projet

```
mtrix-refactor/
├── .env                          # Variables d'environnement (NE PAS VERSIONNER)
├── .gitignore                    # Ignorer .env, storage/, vendor/
│
├── public/                       # Point d'entrée web
│   └── index.php                # Routeur API principal
│
├── backend/
│   ├── api/
│   │   ├── routes.php           # Définition des routes
│   │   ├── commandes.php        # Logique commandes
│   │   ├── paiements.php        # Intégration FedaPay
│   │   ├── admin.php            # Actions admin
│   │   └── file_attente.php     # Gestion queue
│   │
│   ├── middleware/
│   │   ├── auth.php             # Authentification JWT/Session
│   │   ├── validate.php         # Validation des données
│   │   └── cors.php             # CORS pour frontend séparé
│   │
│   ├── models/
│   │   ├── Commande.php
│   │   ├── Palier.php
│   │   ├── Paiement.php
│   │   └── FileAttente.php
│   │
│   └── utils/
│       ├── Database.php         # Connexion DB
│       ├── Mail.php             # Envoi emails Brevo
│       ├── Logger.php           # Logs et audit
│       └── Security.php         # Hash, validation, etc
│
├── frontend/
│   ├── index.html               # Page d'accueil
│   ├── commander.html           # Page de commande
│   ├── admin.html               # Dashboard admin
│   │
│   ├── css/
│   │   ├── site.css             # Styles généraux
│   │   ├── commande.css         # Styles commande
│   │   └── admin.css            # Styles admin
│   │
│   └── js/
│       ├── api.js               # Client API (fetch)
│       ├── commande.js          # Logique commande
│       ├── admin.js             # Logique admin
│       └── utils.js             # Helpers
│
├── config/
│   ├── schema.sql               # Schéma base de données
│   ├── config.php               # Charger .env, configuration
│   └── constants.php            # Constantes de l'app
│
├── storage/
│   ├── data/                    # Fichiers JSON (temporaire si besoin)
│   └── logs/                    # Logs applicatifs
│
└── docs/
    ├── API.md                   # Documentation API (endpoints)
    ├── SETUP.md                 # Installation et déploiement
    └── SECURITY.md              # Considérations sécurité
```

---

## 🗄️ Base de données - Tables

### 1. **paliers** (Tiers de prix)
```
- id: INT (PK)
- numero: INT (1-5, unique)
- prix: INT (3500, 4500, 5000, 6000, 7000 FCFA)
- places: INT (total de places dans ce palier)
- restant: INT (places encore disponibles)
- ouvert: BOOLEAN (débloqué par admin ?)
- creer_le: TIMESTAMP
```

### 2. **commandes** (Réservations/Commandes)
```
- id: INT (PK)
- ref: VARCHAR (MTX-XXXXX - identifiant unique client)
- palier_id: INT (FK → paliers)
- place: INT (01-15)
- statut: ENUM (attente, a_confirmer, payee, livree, annulee, expiree)
- couleur: VARCHAR (noir, rouge, blanc, marron, noir-fume)
- prix: INT
- paye_le: TIMESTAMP
- transac: VARCHAR (numéro transaction FedaPay)
- nom, tel, email, lieu, note: Client info
- complete: BOOLEAN (formulaire rempli ?)
- reserve_jusqua: TIMESTAMP (expiration réservation - 5 min)
```

### 3. **file_attente** (Queue système)
```
- id: INT (PK)
- commande_id: INT (FK → commandes)
- position: INT (ordre dans la file)
- jeton: VARCHAR (unique token pour polling client)
- etat: ENUM (attente, promue, expir, servie)
- creer_le: TIMESTAMP
```

### 4. **paiements** (Historique paiements)
```
- id: INT (PK)
- commande_id: INT (FK → commandes)
- montant: INT
- statut: ENUM (initiée, en_cours, reussie, refusee, timeout)
- fedapay_id: VARCHAR (ID FedaPay)
- valide_le: TIMESTAMP
```

### 5. **admin_sessions** (Authentification)
```
- id: INT (PK)
- token: VARCHAR (JWT ou session token)
- password_hash: VARCHAR
- derniere_connexion: TIMESTAMP
- active: BOOLEAN
```

### 6. **admin_logs** (Audit)
```
- id: INT (PK)
- action: VARCHAR (lancer_palier, confirmer_paiement, etc)
- reference: VARCHAR (ref commande, etc)
- details: JSON (données de l'action)
- ip_adresse: VARCHAR
- creer_le: TIMESTAMP
```

### 7. **etat_global** (Variables globales)
```
- cle: VARCHAR (paliers_debloque, total_vendues, encaisse_total)
- valeur: VARCHAR
- type: ENUM (int, string, json)
```

### 8. **webhooks** (FedaPay callbacks)
```
- id: INT (PK)
- type: VARCHAR (payment.success, payment.failed, etc)
- fedapay_id: VARCHAR
- payload: JSON
- traite: BOOLEAN
- creer_le: TIMESTAMP
```

---

## 🔐 Sécurité - Variables .env

```env
# Ne JAMAIS committer .env
# Chaque environnement a ses propres clés

FEDAPAY_API_KEY=xxx              # Clé API FedaPay
FEDAPAY_WEBHOOK_SECRET=xxx       # Secret webhook FedaPay
MAIL_PASSWORD=xxx                # Clé SMTP Brevo
JWT_SECRET=xxx                   # Secret pour tokens JWT
ADMIN_PASSWORD_HASH=xxx          # Hash du mot de passe admin
```

---

## 🚀 Flux de fonctionnement

### 1️⃣ **Réservation (Client)**
```
CLIENT → Frontend → API /reserve
                  → Crée commande (statut: attente)
                  → Retourne ref + prix
                  → Si pas de place → ajoute à file_attente
                  → Client reçoit jeton + position
```

### 2️⃣ **Paiement FedaPay**
```
CLIENT → Frontend → Redirect FedaPay
      → Paye
      → FedaPay callback → API /webhook
                        → Crée paiement (statut: en_cours)
                        → Admin peut vérifier et confirmer
      → Commande statut: a_confirmer
```

### 3️⃣ **Confirmation Admin**
```
ADMIN → Frontend → Click "Confirmer paiement"
              → API /admin/confirm
              → Vérifie sur FedaPay
              → Commande statut: payee
              → Email confirmation envoyé
```

### 4️⃣ **Formulaire Livraison**
```
CLIENT → Complète formulaire (nom, tel, email, lieu)
      → API /commandes/:ref/livraison
      → Commande statut: livree
      → Email reçu, commande terminée
```

### 5️⃣ **File d'attente**
```
Si palier est PLEIN → nouvelle commande va dans file_attente
Admin lance prochain palier → Les gens de la file sont PROMUS
                           → Commande passe à statut: attente (nouveau palier)
                           → Ils peuvent payer au nouveau prix
```

---

## 🛠️ Installation

### 1. Cloner et setup
```bash
cd /xampp/htdocs
git clone <repo> mtrix-refactor
cd mtrix-refactor
cp .env.example .env          # Créer .env avec tes clés
```

### 2. Base de données
```bash
mysql -u root -p mtrix_prod < config/schema.sql
# Ou depuis phpmyadmin : upload schema.sql
```

### 3. Remplir .env
```env
DB_PASSWORD=tonMotDePasse
FEDAPAY_API_KEY=ta_cle_api
FEDAPAY_WEBHOOK_SECRET=ton_secret
MAIL_PASSWORD=ta_cle_brevo
ADMIN_PASSWORD_HASH=hash_du_mot_de_passe
```

### 4. Lancer le serveur
```bash
php -S localhost:8099 -t public/
```

### 5. Accéder
- Frontend : `http://localhost:8099`
- Admin : `http://localhost:8099/admin.html`

---

## 📡 API Endpoints

### Commandes
```
POST   /api/reserve              # Créer une réservation
GET    /api/attente              # Vérifier position file d'attente
POST   /api/paiement/:ref        # Confirmer paiement
POST   /api/livraison/:ref       # Remplir infos livraison
```

### Admin
```
POST   /api/admin/login          # Connexion
POST   /api/admin/palier/launch  # Lancer un palier
GET    /api/admin/commandes      # Lister toutes commandes
GET    /api/admin/stats          # Statistiques
```

### Webhooks
```
POST   /api/webhooks/fedapay     # Callback FedaPay (secret-protected)
```

---

## ✅ Checklist avant Render

- [ ] `.env` avec toutes les clés FedaPay et Brevo
- [ ] Base de données créée et schéma chargé
- [ ] Frontend fonctionne avec API
- [ ] Admin panel fonctionne
- [ ] Tests paiement en mode sandbox FedaPay
- [ ] Logs activés dans `storage/logs/`
- [ ] `.gitignore` contient `.env`

---

## 🔗 Ressources

- **FedaPay** : https://fedapay.com (webhook, API doc)
- **Brevo** : https://brevo.com (SMTP, clés)
- **MySQL** : https://dev.mysql.com/doc/

---

**Maintenant tu as une structure PRO, sécurisée et scalable.** ✨
