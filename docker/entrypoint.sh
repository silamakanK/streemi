#!/bin/sh
set -e

# Attend que PostgreSQL accepte les connexions (au démarrage du serveur, la base démarre en parallèle)
tries=0
until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "La base de données ne répond pas après 60 secondes." >&2
        exit 1
    fi
    echo "En attente de la base de données..."
    sleep 2
done

# Seul le conteneur « app » applique les migrations, pour éviter que le worker le fasse en même temps
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec docker-php-entrypoint "$@"
