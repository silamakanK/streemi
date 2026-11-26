# Streemi 🎬

Plateforme de streaming (type Netflix) développé avec **Symfony 7** dans le cadre d'un projet scolaire à l'EEMI.

---

## Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.3 · Symfony 7 |
| Base de données | PostgreSQL 16 (Docker) |
| ORM | Doctrine |
| Templates | Twig |
| CSS | Tailwind CSS (CDN) |
| Emails (dev) | Mailpit (Docker) |
| Connexion sociale | Google OAuth 2 (`knpuniversity/oauth2-client-bundle`) |
| Assistant IA | Google Gemini (offre gratuite) |

---

## Fonctionnalités

- **Authentification** — Inscription, connexion, déconnexion, réinitialisation de mot de passe, **connexion avec Google**
- **Catalogue** — Films, séries et animés avec casting, staff, saisons et épisodes
- **Onglets Séries / Films / Animés** — Sur l'accueil et la page Découvrir
- **Recommandations personnalisées** — « Recommandé pour vous » selon les médias regardés et consultés récemment
- **Assistant IA** — Chatbot (bulle en bas à droite) qui propose des films, séries ou animés selon vos envies
- **Catégories** — Navigation par genre avec chargement progressif
- **Recherche** — Recherche sur les titres
- **Commentaires** — Système de commentaires avec modération admin
- **Playlists** — Créer des listes, ajouter/retirer des médias
- **Historique** — Suivi des visionnages par utilisateur
- **Abonnements** — Plans HD / 4K avec souscription
- **Profil** — Modification du pseudo, email et mot de passe
- **Back-office** — Gestion des films, séries, saisons, épisodes, catégories, utilisateurs et commentaires (voir [Back-office](#back-office))

---

## Prérequis

- PHP 8.3+ avec les extensions `pdo_pgsql`, `intl`, `mbstring`
- Composer
- Docker et Docker Compose
- Symfony CLI (`symfony server:start`)
- *(optionnel)* Un compte Google pour la connexion Google et l'assistant IA

---

## Installation

### 1. Cloner le projet

```bash
git clone <url-du-repo>
cd streemi
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Démarrer la base de données et le serveur mail

```bash
docker compose up -d
```

Cela lance :
- **PostgreSQL** sur le port `5433`
- **Mailpit** (emails de dev) sur le port `1025` (SMTP) et `8025` (UI web)

### 4. Configurer l'environnement

Le fichier `.env` est déjà configuré pour le développement local. Vérifiez que les valeurs correspondent :

```env
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5433/app?serverVersion=16&charset=utf8"
MAILER_DSN=smtp://localhost:1025
APP_ENV=dev
```

Les **clés secrètes** (Google, Gemini) ne doivent jamais être commitées : mettez-les dans un fichier **`.env.local`** à la racine du projet (il est ignoré par git).

```env
# .env.local

# Connexion avec Google (voir « Connexion avec Google » ci-dessous)
OAUTH_GOOGLE_CLIENT_ID=xxxxxxxx.apps.googleusercontent.com
OAUTH_GOOGLE_CLIENT_SECRET=xxxxxxxx

# Assistant IA (voir « Assistant IA » ci-dessous)
GEMINI_API_KEY=xxxxxxxx
```

| Variable | Obligatoire | Rôle | Valeur par défaut (`.env`) |
|---|---|---|---|
| `OAUTH_GOOGLE_CLIENT_ID` | Non* | Identifiant client OAuth Google | vide |
| `OAUTH_GOOGLE_CLIENT_SECRET` | Non* | Secret client OAuth Google | vide |
| `GEMINI_API_KEY` | Non* | Clé API Gemini pour l'assistant | vide |
| `GEMINI_MODEL` | Non | Modèle Gemini utilisé par l'assistant | `gemini-3.5-flash-lite` |
| `TRUSTED_HOSTS` | Non | Noms d'hôte autorisés (regex) | `^(localhost\|127\.0\.0\.1)$` |

\* Le site fonctionne sans ces clés : le bouton Google et l'assistant affichent simplement un message indiquant qu'ils ne sont pas configurés.

> ⚠️ Ouvrez le site via `127.0.0.1` ou `localhost` : ce sont les seuls noms d'hôte autorisés par `TRUSTED_HOSTS` (protection contre l'empoisonnement des liens de réinitialisation de mot de passe). Pour un autre domaine, ajoutez-le dans `.env.local`.

Après toute modification de `.env.local` :

```bash
php bin/console cache:clear
```

### 5. Créer la base de données et appliquer les migrations

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
```

### 6. Charger les données de test

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

Cela génère :
- 10 utilisateurs de test + 1 admin + 1 démo
- 100 médias (films et séries aléatoires)
- 24 animés réels, classiques et récents (Akira, Le Voyage de Chihiro, Death Note, L'Attaque des Titans, Frieren…)
- Des catégories (dont « Animation »), langues, playlists, abonnements, commentaires

### 7. Lancer le serveur

```bash
symfony server:start
```

L'application est accessible sur **http://127.0.0.1:8000**

---

## Comptes de test

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | `admin@example.com` | `motdepasse` |
| Utilisateur | `demo@example.com` | `motdepasse` |
| Utilisateurs générés | `test_0@example.com` … `test_9@example.com` | `motdepasse` |

---

## Connexion avec Google

1. Ouvrez la [Google Cloud Console](https://console.cloud.google.com/apis/credentials) → **Créer des identifiants** → **ID client OAuth** → type **Application Web**.
   - Si demandé, configurez d'abord l'**écran de consentement OAuth** (type « Externe ») et ajoutez votre adresse Gmail dans les **utilisateurs test**.
2. Dans **URI de redirection autorisés**, ajoutez exactement :
   ```
   http://127.0.0.1:8000/connect/google/check
   ```
   (et `http://localhost:8000/connect/google/check` si vous utilisez `localhost`).
3. Copiez l'ID client et le secret dans `.env.local` (`OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET`), puis `php bin/console cache:clear`.

Comportement :
- Si un compte existe déjà avec la même adresse email, il est relié à Google.
- Sinon, un compte est créé automatiquement (avec un mot de passe aléatoire, utilisable via « mot de passe oublié »).
- Les adresses Google non vérifiées sont refusées.

**Erreurs fréquentes** : `redirect_uri_mismatch` → l'URI déclarée chez Google ne correspond pas exactement à l'adresse utilisée ; `access_denied` → votre compte n'est pas dans les utilisateurs test.

---

## Assistant IA

Une bulle rouge en bas à droite de chaque page ouvre un chat : l'utilisateur décrit son humeur, ses loisirs ou ce qu'il a aimé, et l'assistant propose 1 à 4 médias **du catalogue** sous forme de cartes cliquables. Il tient compte de l'historique de visionnage et se souvient de la conversation (bouton ↻ pour recommencer). Réservé aux utilisateurs connectés.

Configuration :
1. Créez une clé gratuite sur **https://aistudio.google.com/apikey**.
2. Ajoutez-la dans `.env.local` : `GEMINI_API_KEY=...`, puis `php bin/console cache:clear`.

À savoir :
- L'offre gratuite de Gemini est **limitée en requêtes par minute et par jour** (quotas visibles dans AI Studio). Quand la limite est atteinte, l'assistant l'indique.
- En offre gratuite, Google peut utiliser les conversations pour améliorer ses modèles : n'y saisissez pas d'informations personnelles.
- Pour des réponses plus fines (un peu plus lentes), mettez `GEMINI_MODEL=gemini-3.8-flash` dans `.env.local`.
- Les identifiants renvoyés par l'IA sont revérifiés côté serveur : elle ne peut proposer que des médias existants.
- En cas d'erreur, le détail est dans `var/log/dev.log` (lignes « Assistant : … »).

---

## Back-office

Accessible depuis **Administration** dans le menu de gauche (compte avec `ROLE_ADMIN`, par exemple `admin@example.com`).

| Section | URL | Possibilités |
|---|---|---|
| Tableau de bord | `/admin` | Nombre de films, séries, animés et comptes |
| Films / Séries | `/admin/movies` | Recherche, filtres (type, catégorie), pagination ; ajout, modification, suppression |
| Saisons et épisodes | `/admin/series/{id}/seasons` | Ajouter / renommer / supprimer des saisons ; ajouter / modifier / supprimer des épisodes |
| Catégories | `/admin/categories` | Ajouter, renommer, supprimer ; lien vers les médias de chaque catégorie |
| Utilisateurs | `/admin/users` | Nommer ou retirer un administrateur |
| Commentaires | `/admin/comments` | Valider ou rejeter les commentaires |

Détails utiles :
- **Animés** : un média apparaît dans l'onglet Animés s'il a la catégorie **« Animation »**. Cette catégorie est protégée (non renommable, non supprimable).
- **Images de couverture** : envoyez une image depuis votre ordinateur (JPG, PNG, WebP, GIF) ou collez une URL. Les images envoyées sont stockées dans `public/uploads/covers/` (non versionné) et supprimées quand elles sont remplacées ou que le média est supprimé.
- **Taille maximale des images** : 5 Mo, dans la limite de `upload_max_filesize` de PHP (2 Mo par défaut). Pour l'augmenter, créez un `php.ini` à la racine du projet (pris en compte par `symfony server:start`) :
  ```ini
  upload_max_filesize = 5M
  post_max_size = 8M
  ```
- Supprimer une série supprime aussi ses saisons, épisodes, commentaires et son historique de visionnage.

---

## Pages principales

| URL | Description |
|---|---|
| `/` | Accueil — onglets Séries / Films / Animés, tendances, recommandations |
| `/discover` | Découverte par catégorie |
| `/category/{id}` | Médias d'une catégorie |
| `/detail/{id}` | Page de détail d'un média |
| `/search?q=...` | Recherche |
| `/lists` | Mes playlists |
| `/history` | Historique de visionnage |
| `/subscriptions` | Plans d'abonnement |
| `/profile` | Mon profil |
| `/connect/google` | Connexion avec Google |
| `/admin` | Back-office (ROLE_ADMIN) |

---

## Emails en développement

Les emails (réinitialisation de mot de passe...) sont interceptés par **Mailpit**.  
Consultez l'interface web sur **http://localhost:8025** pour les lire.

---

## Commandes utiles

```bash
# Vider le cache (après chaque modification de .env.local)
php bin/console cache:clear

# Voir toutes les routes
php bin/console debug:router

# Vérifier les variables d'environnement chargées
php bin/console debug:dotenv

# Relancer les fixtures (remet la BDD à zéro)
php bin/console doctrine:fixtures:load --no-interaction

# Arrêter Docker
docker compose down
```

---

## Tests

```bash
composer test
```

Cette commande recrée la base de test (`app_test`), applique les migrations, charge les fixtures puis lance les ~100 tests PHPUnit. Docker doit être démarré.

Pour relancer les tests sans recréer la base (plus rapide) :

```bash
php bin/phpunit                                    # tous les tests
php bin/phpunit tests/Functional/Admin             # un dossier
php bin/phpunit --filter testCreateAMovie          # un test précis
```

| Dossier | Contenu |
|---|---|
| `tests/Unit` | Code sans base de données (upload des couvertures, onglets) |
| `tests/Integration` | Requêtes du catalogue, assistant IA (réponses Gemini simulées, aucun appel réseau) |
| `tests/Functional` | Parcours dans le navigateur de test : sécurité, inscription, mot de passe oublié, onglets, recommandations, playlists, commentaires, assistant |
| `tests/Functional/Admin` | Back-office : médias, saisons/épisodes, catégories, utilisateurs, upload d'images |

Chaque test s'exécute dans une transaction annulée à la fin (`dama/doctrine-test-bundle`) : les tests ne modifient pas la base et peuvent être relancés à volonté. En environnement de test, aucun email n'est envoyé et aucune clé Google / Gemini n'est utilisée.

---

## Déploiement

L'application se déploie sur **AWS EC2** avec Docker (FrankenPHP, HTTPS automatique) et un pipeline **GitHub Actions** : tests, puis image Docker, puis déploiement à chaque push sur `main`. Le guide complet est dans **[DEPLOY.md](DEPLOY.md)**.

### Production actuelle

| | |
|---|---|
| **URL** | https://15-224-8-36.sslip.io |
| **Serveur** | AWS EC2, région **Europe (Paris) eu-west-3**, Ubuntu 24.04, IP Elastic `15.224.8.36` |
| **Conteneurs** | `app` (FrankenPHP + Caddy), `worker` (emails), `database` (PostgreSQL 16) |
| **Domaine** | [sslip.io](https://sslip.io) : l'IP écrite **avec des tirets** sert de nom de domaine, sans rien acheter |
| **Certificat HTTPS** | Let's Encrypt, obtenu et renouvelé automatiquement par Caddy |

### Étapes réalisées

1. **Instance EC2** créée à Paris : `t3.micro`, 20 Go de stockage, système de fichiers « Aucun » (les données sont dans les volumes Docker), groupe de sécurité ouvert sur les ports 22, 80 et 443, puis IP Elastic associée.
2. **Serveur préparé** avec `deploy/setup-ec2.sh` (Docker et swap), puis `~/streemi/.env.prod` créé à partir de `deploy/.env.prod.example`.
3. **GitHub Actions configuré** (Settings → Secrets and variables → Actions) :
   - variables `EC2_HOST` = `15.224.8.36` et `EC2_USER` = `ubuntu`
   - secret `EC2_SSH_KEY` = le contenu de la clé `.pem`
4. **Premier déploiement** avec `git push origin main` : tests, image publiée sur ghcr.io, puis mise à jour des conteneurs par SSH.
5. **Catalogue copié** depuis la base locale, car la production démarre vide et les fixtures sont réservées au dev :
   ```bash
   # En local
   docker compose up -d
   docker compose exec -T database pg_dump -U app --clean --if-exists --no-owner app > streemi.sql
   scp -i Streemi.pem streemi.sql ubuntu@15.224.8.36:~

   # Sur le serveur
   cd ~/streemi
   docker compose -f compose.prod.yaml exec -T database sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' < ~/streemi.sql
   docker compose -f compose.prod.yaml exec app php bin/console cache:clear
   rm ~/streemi.sql
   ```
   Cette copie **remplace** toute la base de production, comptes utilisateurs compris. Ne commitez jamais `streemi.sql`.
6. **Administrateur créé** sur le serveur :
   ```bash
   docker compose -f compose.prod.yaml exec app php bin/console app:user:promote email@exemple.com
   ```
7. **Google et Gemini activés** en production :
   - ajout de `OAUTH_GOOGLE_CLIENT_ID`, `OAUTH_GOOGLE_CLIENT_SECRET` et `GEMINI_API_KEY` dans `~/streemi/.env.prod`, puis `docker compose -f compose.prod.yaml up -d` pour recréer les conteneurs (un `restart` ne relit pas `.env.prod`)
   - dans la Google Cloud Console, ajout de l'origine `https://15-224-8-36.sslip.io` et de l'URI de redirection `https://15-224-8-36.sslip.io/connect/google/check`

### Problèmes rencontrés et solutions

| Symptôme | Cause | Solution |
|---|---|---|
| `ssh: Could not resolve hostname ip` | `IP` laissé tel quel dans la commande | Remplacer par la vraie adresse : `ubuntu@15.224.8.36` |
| `can't connect without a private SSH key` (GitHub Actions) | Secret `EC2_SSH_KEY` absent ou mal nommé (créé dans « Variables » par exemple) | Le créer dans l'onglet **Secrets** avec ce nom exact |
| `ssh.ParsePrivateKey: ssh: no key found` | Contenu du secret incomplet ou incorrect | `gh secret set EC2_SSH_KEY < Streemi.pem` pour envoyer le fichier entier |
| `ERR_SSL_PROTOCOL_ERROR` dans le navigateur | Adresse tapée avec des points (`15.224.8.36.sslip.io`) | Utiliser des tirets : `15-224-8-36.sslip.io`, le seul domaine qui a un certificat |
| Site en ligne mais sans films ni séries | Base de production vide | Copier la base locale (étape 5) |
| « La connexion Google n'est pas configurée » | Clés absentes de `.env.prod` | Étape 7 |

**Coûts** : arrêter l'instance stoppe la facturation du calcul, mais une IP Elastic non associée à une instance en marche est facturée. Après la soutenance, il faut **résilier l'instance et libérer l'IP Elastic** (région Paris).

---

## Structure du projet

```
streemi/
├── src/
│   ├── Controller/
│   │   └── Admin/      # Back-office (médias, saisons/épisodes, catégories)
│   ├── Entity/         # Entités Doctrine (Media, User, Playlist…)
│   ├── Repository/     # Requêtes BDD
│   ├── Enum/           # Enums PHP (CatalogType, CommentStatus, UserStatus…)
│   ├── Security/       # Authentification Google
│   ├── Service/        # Recommandations, assistant IA, upload des couvertures
│   └── DataFixtures/   # Données de test
├── templates/
│   ├── auth/           # Login, inscription, reset password
│   ├── admin/          # Back-office
│   ├── movie/          # Détail film/série
│   └── parts/          # Composants réutilisables (menu, onglets, assistant…)
├── public/uploads/     # Images de couverture envoyées (non versionné)
├── config/             # Configuration Symfony
├── migrations/         # Migrations Doctrine
├── tests/              # Tests PHPUnit (unitaires, intégration, fonctionnels)
├── docker/             # Configuration de l'image de production (Caddy, PHP, démarrage)
├── deploy/             # Scripts du serveur EC2 (installation, sauvegardes, modèle .env.prod)
├── .github/workflows/  # Pipeline CI/CD
├── Dockerfile          # Image de production
├── compose.prod.yaml   # Conteneurs de production
└── compose.yaml        # Docker Compose de dev (PostgreSQL + Mailpit)
```
