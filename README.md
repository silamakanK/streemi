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

---

## Fonctionnalités

- **Authentification** — Inscription, connexion, déconnexion, réinitialisation de mot de passe
- **Catalogue** — Films et séries avec casting, staff, saisons et épisodes
- **Catégories** — Navigation par genre avec chargement progressif
- **Recherche** — Recherche full-text sur les titres
- **Commentaires** — Système de commentaires avec modération admin
- **Playlists** — Créer des listes, ajouter/retirer des médias
- **Historique** — Suivi des visionnages par utilisateur
- **Abonnements** — Plans HD / 4K avec souscription
- **Profil** — Modification du pseudo, email et mot de passe
- **Admin** — Gestion des films, utilisateurs, catégories et commentaires

---

## Prérequis

- PHP 8.3+ avec les extensions `pdo_pgsql`, `intl`, `mbstring`
- Composer
- Docker et Docker Compose
- Symfony CLI (`symfony server:start`)

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
- Des catégories, langues, playlists, abonnements, commentaires

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

## Pages principales

| URL | Description |
|---|---|
| `/` | Accueil — médias populaires |
| `/discover` | Découverte par catégorie |
| `/category/{id}` | Médias d'une catégorie |
| `/detail/{id}` | Page de détail d'un média |
| `/search?q=...` | Recherche |
| `/lists` | Mes playlists |
| `/history` | Historique de visionnage |
| `/subscriptions` | Plans d'abonnement |
| `/profile` | Mon profil |
| `/admin` | Panel d'administration (ROLE_ADMIN) |

---

## Emails en développement

Les emails (réinitialisation de mot de passe...) sont interceptés par **Mailpit**.  
Consultez l'interface web sur **http://localhost:8025** pour les lire.

---

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Voir toutes les routes
php bin/console debug:router

# Relancer les fixtures (remet la BDD à zéro)
php bin/console doctrine:fixtures:load --no-interaction

# Arrêter Docker
docker compose down
```

---

## Structure du projet

```
streemi/
├── src/
│   ├── Controller/     # Contrôleurs Symfony
│   ├── Entity/         # Entités Doctrine (Media, User, Playlist…)
│   ├── Repository/     # Requêtes BDD
│   ├── Enum/           # Enums PHP (CommentStatus, UserStatus…)
│   └── DataFixtures/   # Données de test
├── templates/
│   ├── auth/           # Login, inscription, reset password
│   ├── admin/          # Panel admin
│   ├── movie/          # Détail film/série
│   └── parts/          # Composants réutilisables (menu, sidebar…)
├── config/             # Configuration Symfony
├── migrations/         # Migrations Doctrine
└── compose.yaml        # Docker Compose (PostgreSQL + Mailpit)
```
