#!/bin/bash
# Sauvegarde de la base de production, à planifier avec cron sur le serveur :
#   crontab -e  →  0 3 * * * bash ~/streemi/backup.sh
# Conserve les 7 dernières sauvegardes dans ~/backups.
set -euo pipefail
cd ~/streemi

file=~/backups/streemi-$(date +%Y-%m-%d_%H%M).sql.gz
docker compose -f compose.prod.yaml exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB"' | gzip > "$file"
ls -1t ~/backups/streemi-*.sql.gz | tail -n +8 | xargs -r rm --
echo "Sauvegarde : $file"
