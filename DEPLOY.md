# Déploiement sur AWS EC2

Streemi tourne en production sur un serveur **AWS EC2** (Ubuntu), dans des conteneurs **Docker**. À chaque `git push` sur `main`, **GitHub Actions** lance les tests puis déploie automatiquement si tout est vert.

## Architecture

```
            git push (main)
                  │
                  ▼
   ┌──────────── GitHub Actions ────────────┐
   │ 1. Tests PHPUnit (PostgreSQL de test)  │
   │ 2. Construction de l'image Docker      │──► GitHub Container Registry (ghcr.io)
   │ 3. Déploiement par SSH                 │
   └───────────────────┬────────────────────┘
                       ▼
   ┌──────────── Serveur AWS EC2 ───────────┐
   │  app      FrankenPHP (PHP 8.3 + Caddy) │◄── HTTPS 443 (certificat Let's Encrypt automatique)
   │  worker   envoi des emails (Messenger) │
   │  database PostgreSQL 16                │
   │  volumes  base, images, certificats    │
   └────────────────────────────────────────┘
```

| Fichier | Rôle |
|---|---|
| `Dockerfile` | Image de production (PHP 8.3, FrankenPHP, OPcache, dépendances sans les outils de dev) |
| `docker/Caddyfile` | Serveur web : HTTPS automatique, compression, en-têtes de sécurité |
| `docker/entrypoint.sh` | Au démarrage : attend la base puis applique les migrations |
| `compose.prod.yaml` | Les 3 conteneurs et leurs volumes persistants |
| `.github/workflows/ci-cd.yml` | Tests → image → déploiement |
| `deploy/setup-ec2.sh` | Installation du serveur (Docker, swap) |
| `deploy/.env.prod.example` | Modèle des variables de production |
| `deploy/backup.sh` | Sauvegarde quotidienne de la base |

---

## 1. Créer le serveur sur AWS

1. Créez un compte sur https://aws.amazon.com. Configurez tout de suite une **alerte de budget** (Billing → Budgets → par exemple 5 €) pour éviter les mauvaises surprises. Vérifiez les conditions de l'offre gratuite proposée aux nouveaux comptes.
2. **EC2 → Lancer une instance** :
   - **Nom** : `streemi`
   - **Image** : Ubuntu Server 24.04 LTS
   - **Type** : `t3.small` (2 Go, confortable) ou `t3.micro` / `t2.micro` (1 Go, suffisant grâce au swap ajouté par le script)
   - **Paire de clés** : créez-en une (format `.pem`) et gardez le fichier précieusement : il sert à se connecter au serveur
   - **Paramètres réseau → Groupe de sécurité**, autorisez :
     - SSH (22) depuis **partout** : GitHub Actions s'y connecte depuis des IP qui changent. La connexion reste protégée par la clé `.pem`, et les mots de passe sont désactivés par défaut sur Ubuntu.
     - HTTP (80) et HTTPS (443) depuis partout
     - Règle personnalisée UDP 443 depuis partout (HTTP/3, facultatif)
   - **Stockage** : 20 Go
3. **EC2 → Adresses IP Elastic → Allouer**, puis **Associer** à l'instance. L'IP du serveur ne changera plus, même après un redémarrage.

## 2. Nom de domaine

Le certificat HTTPS demande un nom de domaine qui pointe vers l'IP Elastic.

- **Avec votre propre domaine** : chez votre registrar (OVH, Gandi, Route 53…), créez un enregistrement **A** `streemi.mondomaine.fr` vers l'IP Elastic.
- **Sans domaine (gratuit, idéal pour une démo)** : utilisez **sslip.io**, qui transforme une IP en nom de domaine. Pour l'IP `13.38.42.7`, le domaine est `13-38-42-7.sslip.io`. Rien à configurer.

## 3. Préparer le serveur

```bash
# Depuis votre ordinateur
chmod 400 ~/Téléchargements/streemi.pem
scp -i ~/Téléchargements/streemi.pem deploy/setup-ec2.sh deploy/backup.sh deploy/.env.prod.example ubuntu@IP_ELASTIQUE:~
ssh -i ~/Téléchargements/streemi.pem ubuntu@IP_ELASTIQUE

# Sur le serveur
bash setup-ec2.sh
exit            # puis reconnectez-vous pour utiliser docker sans sudo
```

Créez ensuite le fichier des secrets de production sur le serveur :

```bash
mv ~/backup.sh ~/streemi/
mv ~/.env.prod.example ~/streemi/.env.prod
nano ~/streemi/.env.prod      # remplacez TOUTES les valeurs
chmod 600 ~/streemi/.env.prod
```

| Variable | Valeur |
|---|---|
| `APP_SECRET` | résultat de `openssl rand -hex 32` |
| `SERVER_NAME` | votre domaine, ex. `13-38-42-7.sslip.io` |
| `TRUSTED_HOSTS` | le même domaine en regex, ex. `'^13-38-42-7\.sslip\.io$'` |
| `POSTGRES_PASSWORD` | un mot de passe long, **identique** dans `DATABASE_URL` |
| `MAILER_DSN` | un vrai service d'envoi (Gmail avec mot de passe d'application, Brevo, Amazon SES…) |
| `OAUTH_GOOGLE_*`, `GEMINI_API_KEY` | comme en local (facultatif) |

## 4. Brancher GitHub Actions

Dans le dépôt GitHub → **Settings → Secrets and variables → Actions** :

| Type | Nom | Valeur |
|---|---|---|
| Variable | `EC2_HOST` | l'IP Elastic (ou le domaine) |
| Variable | `EC2_USER` | `ubuntu` |
| Secret | `EC2_SSH_KEY` | le contenu complet du fichier `.pem` (de `-----BEGIN` à `-----END`) |

Tant que `EC2_HOST` n'est pas défini, GitHub Actions lance seulement les tests, sans tenter de déployer.

## 5. Premier déploiement

```bash
git push origin main
```

Suivez l'exécution dans l'onglet **Actions** du dépôt (5 minutes environ la première fois). Ensuite, ouvrez `https://votre-domaine` : le certificat HTTPS est obtenu automatiquement au premier accès.

**Créer le compte administrateur** : inscrivez-vous sur le site (ou connectez-vous avec Google), puis, sur le serveur :

```bash
cd ~/streemi
docker compose -f compose.prod.yaml exec app php bin/console app:user:promote votre@email.com
```

**Connexion Google** : dans la Google Cloud Console, ajoutez l'URI de redirection `https://votre-domaine/connect/google/check`.

**Reprendre le catalogue de dev** (facultatif) : la production démarre avec une base vide (les fixtures sont réservées au dev). Pour y copier votre base locale :

```bash
# Sur votre ordinateur
docker compose exec -T database pg_dump -U app --clean --if-exists --no-owner app > streemi.sql
scp -i streemi.pem streemi.sql ubuntu@IP_ELASTIQUE:~

# Sur le serveur
cd ~/streemi
docker compose -f compose.prod.yaml exec -T database sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"' < ~/streemi.sql
```

Les images de couverture envoyées en local ne sont pas copiées par cette commande (seules les URL externes fonctionneront).

---

## Exploitation au quotidien

```bash
cd ~/streemi
C="docker compose -f compose.prod.yaml"

$C ps                          # état des conteneurs
$C logs -f app                 # journaux du site (Ctrl+C pour quitter)
$C logs -f worker              # journaux de l'envoi d'emails
$C exec app php bin/console …  # commande Symfony
$C restart app                 # redémarrer le site
```

**Sauvegardes automatiques** (chaque nuit à 3 h, 7 jours conservés dans `~/backups`) :

```bash
crontab -e
# ajoutez la ligne :
0 3 * * * bash ~/streemi/backup.sh >> ~/backups/backup.log 2>&1
```

**Restaurer une sauvegarde** :

```bash
gunzip -c ~/backups/streemi-AAAA-MM-JJ_0300.sql.gz | docker compose -f compose.prod.yaml exec -T database sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"'
```

**Revenir à une version précédente** : chaque image est aussi publiée avec l'identifiant du commit.

```bash
IMAGE=ghcr.io/silamakank/streemi:IDENTIFIANT_DU_COMMIT docker compose -f compose.prod.yaml up -d
```

## Tester l'image de production en local

```bash
docker build -t streemi:local .
```

La stack complète se lance avec `compose.prod.yaml` et un `.env.prod` local contenant `SERVER_NAME=:80` (HTTP simple, sans certificat).

## Problèmes fréquents

| Symptôme | Cause probable |
|---|---|
| Le site ne répond pas | Groupe de sécurité : ports 80/443 non ouverts, ou conteneur arrêté (`$C ps`, `$C logs app`) |
| Erreur de certificat HTTPS | Le domaine ne pointe pas (encore) vers l'IP Elastic ; la propagation DNS peut prendre quelques minutes |
| « 400 Bad Request » | Le domaine utilisé n'est pas dans `TRUSTED_HOSTS` |
| Le déploiement échoue à l'étape SSH | `EC2_SSH_KEY` incomplet, ou port 22 limité à votre IP dans le groupe de sécurité (voir étape 1) |
| Les emails ne partent pas | `MAILER_DSN` incorrect : voir `$C logs worker` |
| `ssh.ParsePrivateKey: no key found` | Secret `EC2_SSH_KEY` incomplet : `gh secret set EC2_SSH_KEY < streemi.pem` |
| `ERR_SSL_PROTOCOL_ERROR` avec sslip.io | IP écrite avec des points au lieu de tirets (`13-38-42-7.sslip.io`) |
| Une variable de `.env.prod` n'est pas prise en compte | `restart` ne relit pas le fichier : lancez `$C up -d` |
