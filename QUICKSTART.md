# 🚀 Quickstart - 5 minutes

## Étape 1️⃣ : Copier et configurer

```bash
# Copier le fichier de configuration
cp .env.example .env

# Éditer .env avec tes paramètres
# Au minimum, remplir :
# - DB_PASSWORD (MySQL root)
# - ADMIN_PASSWORD_HASH (générer avec console.php)
# - FEDAPAY_API_KEY, WEBHOOK_SECRET, etc
```

### Générer les hashs (optionnel pour test local)

```bash
# Hash du mot de passe admin (ex: "password123")
php bin/console.php admin:hash password123
# Copier le résultat et coller dans ADMIN_PASSWORD_HASH du .env

# Clé JWT secrète
php bin/console.php jwt:secret
# Copier et coller dans JWT_SECRET du .env
```

---

## Étape 2️⃣ : Base de données

### Via ligne de commande
```bash
# Créer la base
mysql -u root -p -e "CREATE DATABASE mtrix_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Charger le schéma
mysql -u root -p mtrix_prod < config/schema.sql
```

### Via phpMyAdmin
1. Ouvrir http://localhost/phpmyadmin
2. Créer BD `mtrix_prod`
3. Onglet SQL → Importer `config/schema.sql`

### Vérifier
```bash
php bin/console.php db:status
# Doit afficher : ✅ Connexion DB: OK
```

---

## Étape 3️⃣ : Lancer le serveur

```bash
# Depuis le dossier racine
php -S localhost:8099 -t public/
```

Puis ouvrir : **http://localhost:8099**

Vous verrez :
```json
{
  "status": "ok",
  "message": "M'trix API v1",
  "endpoints": { ... }
}
```

---

## 🎯 Tester l'API

### Test simple avec curl

```bash
# Test de santé
curl http://localhost:8099/api/

# Réponse attendue :
# {"status":"ok","message":"M'trix API v1",...}
```

---

## 🔧 Prochaines étapes

### 1. Implémenter les endpoints
Commencer par `/api/reserve` :
1. Ouvrir `public/index.php`
2. Remplacer `handleReserve()` placeholder
3. Utiliser les modèles `Commande`, `Palier`

### 2. Créer le frontend
1. Créer `frontend/index.html`
2. Créer `frontend/js/api.js` (client API)
3. Appeler `/api/reserve` depuis JavaScript

### 3. Intégrer FedaPay
1. S'inscrire : https://merchant.fedapay.com
2. Récupérer clés API
3. Remplir `.env`
4. Implémenter `/api/webhooks/fedapay`

---

## 📝 Fichiers importants à connaître

| Fichier | Rôle |
|---------|------|
| `public/index.php` | Routeur API |
| `config/config.php` | Charger .env |
| `backend/utils/Database.php` | Connexion DB |
| `backend/models/Commande.php` | Logique commandes |
| `SETUP.md` | Installation complète |
| `ARCHITECTURE.md` | Design technique |

---

## 🆘 Erreurs courantes

### "Connection refused"
```
→ MySQL n'est pas lancé
→ Vérifier DB_HOST/DB_PORT dans .env
→ Vérifier que base mtrix_prod existe
```

### "Missing .env"
```
→ Créer le fichier .env
→ Copier depuis .env.example
→ Remplir avec tes valeurs
```

### 404 sur /api/...
```
→ Vérifier mod_rewrite activé (Apache)
→ Vérifier .htaccess dans public/
→ Vérifier que la route est implémentée
```

---

## ✅ Checklist

- [ ] `.env` créé et rempli
- [ ] Base de données `mtrix_prod` créée
- [ ] Schéma SQL chargé
- [ ] `php -S localhost:8099 -t public/` lancé
- [ ] `curl http://localhost:8099/api/` retourne JSON valide
- [ ] `ADMIN_PASSWORD_HASH` changé (pas vide)
- [ ] `JWT_SECRET` changé (pas vide)
- [ ] `FEDAPAY_API_KEY` rempli (ou "test" en dev)

---

## 💡 Tips

```bash
# Voir les logs en temps réel
tail -f storage/logs/app.log

# Réinitialiser la DB (attention!)
php bin/console.php init --reset

# Vérifier les expirations
php bin/console.php expire:check

# Voir la file d'attente
php bin/console.php queue:status
```

---

**C'est tout! Tu as une API prête pour développer.** 🎉

Prochaine étape : implémenter les endpoints réels dans `public/index.php`
