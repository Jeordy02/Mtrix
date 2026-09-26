# Installation et Déploiement M'trix

## 1️⃣ Installation locale (XAMPP)

### Prérequis
- XAMPP 7.4+ (PHP, MySQL)
- Git
- Un éditeur (VS Code, etc.)

### Setup

```bash
# Cloner ou copier le projet
cd /xampp/htdocs
cp -r mtrix-refactor .

# Ou via git
git clone <url> mtrix-refactor
cd mtrix-refactor

# Créer le fichier .env
cp .env .env.local  # Ou copier manuellement et remplir
nano .env           # Éditer les clés

# Créer les dossiers manquants
mkdir -p storage/logs storage/data
chmod 755 storage/{logs,data}
```

### Base de données

**Option 1: Via phpMyAdmin**
1. Ouvrir http://localhost/phpmyadmin
2. Créer une BD nommée `mtrix_prod`
3. Aller à l'onglet SQL
4. Importer le fichier `config/schema.sql`

**Option 2: Via CLI**
```bash
mysql -u root -p mtrix_prod < config/schema.sql
```

**Option 3: Manuel**
```bash
mysql -u root -p
> CREATE DATABASE mtrix_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> EXIT;

mysql -u root -p mtrix_prod < config/schema.sql
```

### Vérifier que tout fonctionne

```bash
# Depuis la racine du projet
php -S localhost:8099 -t public/
```

Puis ouvrir : **http://localhost:8099**

Vous devriez voir :
```json
{
  "status": "ok",
  "message": "M'trix API v1",
  "endpoints": { ... }
}
```

---

## 2️⃣ Remplir le `.env`

Les variables **critiques** à changer :

```env
# Base de données
DB_PASSWORD=votreMotDePasse

# FedaPay (récupérer depuis https://merchant.fedapay.com)
FEDAPAY_API_KEY=fpk_live_xxx
FEDAPAY_PUBLIC_KEY=fpk_pub_xxx
FEDAPAY_MERCHANT_ID=xxx
FEDAPAY_WEBHOOK_SECRET=xxx

# Brevo/Sendinblue (récupérer depuis https://app.brevo.com)
MAIL_USERNAME=votre_email@example.com
MAIL_PASSWORD=xsmtpxxx

# Sécurité
ADMIN_PASSWORD_HASH=$(php -r 'echo password_hash("votreMotDePasse", PASSWORD_BCRYPT);')
JWT_SECRET=generer_une_cle_aleatoire_longue

# Contacts (vos réseaux)
WHATSAPP=22958779933
INSTAGRAM=mtrix229
TIKTOK=mtrix2290
```

### Générer les hashs

```bash
# Hash du mot de passe admin
php -r 'echo password_hash("monMotDePasse123", PASSWORD_BCRYPT);'

# Clé JWT aléatoire
php -r 'echo bin2hex(random_bytes(32));'
```

---

## 3️⃣ Structure de déploiement

### Dossiers importants

```
mtrix-refactor/
├── public/              ← Point d'entrée HTTP (web root)
├── backend/             ← Logique serveur (sécurisé)
├── config/              ← Fichiers système (.env, DB)
├── storage/
│   ├── logs/           ← Logs de l'app
│   └── data/           ← Fichiers temporaires
└── frontend/            ← HTML/CSS/JS statiques (à venir)
```

**Important** : Seul le dossier `public/` doit être accessible via le web.

Exemple de configuration Nginx :
```nginx
server {
    root /var/www/mtrix/public;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

## 4️⃣ Déploiement sur Render (Gratuit)

### Créer un compte Render
https://render.com

### Connecter le repo
1. Push le code sur GitHub (ou GitLab)
2. Sur Render.com : New Web Service
3. Connecter le repo
4. Build command : `composer install` (si dépendances)
5. Start command : `php -S 0.0.0.0:10000 -t public`

### Ajouter les variables d'environnement

Sur Render, aller à **Settings → Environment Variables** et ajouter :

```
DB_HOST=votre_db_host
DB_NAME=mtrix_prod
DB_USER=votre_user
DB_PASSWORD=xxx

FEDAPAY_API_KEY=fpk_live_xxx
FEDAPAY_PUBLIC_KEY=fpk_pub_xxx
FEDAPAY_WEBHOOK_SECRET=xxx

MAIL_PASSWORD=xsmtpxxx
ADMIN_PASSWORD_HASH=xxx
JWT_SECRET=xxx
```

### URL de webhook FedaPay

Une fois déployé sur Render, copier l'URL du service.

Puis sur FedaPay (https://merchant.fedapay.com/settings/webhooks) :
- Ajouter webhook : `https://your-render-app.onrender.com/api/webhooks/fedapay`
- Signer les requêtes avec le secret

---

## 5️⃣ Vérifier la santé de l'app

### Logs locaux
```bash
tail -f storage/logs/app.log
```

### Endpoint de santé
```bash
curl http://localhost:8099/api/

# Doit retourner un JSON valide avec status: "ok"
```

### Test de DB
```bash
# Depuis PHP
php -r "require 'config/config.php'; $db = Database::getInstance(); echo 'OK';"
```

---

## 6️⃣ Checklist avant lancer en production

- [ ] `.env` rempli avec les vraies clés
- [ ] Base de données créée (`mtrix_prod`)
- [ ] Schema chargé (`config/schema.sql`)
- [ ] Dossier `storage/` accessible en écriture
- [ ] HTTPS activé (auto sur Render)
- [ ] Webhook FedaPay configuré
- [ ] Email Brevo en mode test
- [ ] Admin password changé
- [ ] `.gitignore` exclut le `.env`
- [ ] Logs activés
- [ ] CORS configuré pour le domaine réel

---

## 🆘 Troubleshooting

### "Connection refused" (DB)
```
Vérifier que MySQL est lancé
Vérifier DB_HOST/DB_PORT dans .env
Vérifier que la base mtrix_prod existe
```

### "Missing .env"
```
Vérifier que .env existe à la racine du projet
Ne pas renommer en .env.local ou autre
Vérifier les permissions (readable par PHP)
```

### "CORS error"
```
Vérifier CORS_ORIGINS dans .env
S'assure que votre domaine est inclus
Tester depuis le même localhost d'abord
```

### 404 sur /api/...
```
Vérifier que mod_rewrite est activé (Apache)
Vérifier que .htaccess existe dans public/
Vérifier que la route est implémentée dans public/index.php
```

---

## 📞 Support

- **Docs** : Lire `README.md`
- **API** : Voir les endpoints dans `README.md`
- **Code** : Commentaires dans les fichiers
