#!/bin/sh
set -e

# The container has no .env of its own; docker-compose.yml supplies the values that differ.
if [ ! -f .env ]; then
    cp .env.example .env
fi

# `artisan serve` hands its workers the values in .env rather than the container's environment,
# so the settings docker-compose.yml passes in are written into .env as well.
for key in APP_URL APP_TIMEZONE DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD; do
    value=$(printenv "$key" || true)
    if [ -n "$value" ]; then
        sed -i "s|^$key=.*|$key=$value|" .env
    fi
done

# MySQL's first start initialises the data directory before it takes connections; wait for it.
until mysqladmin ping --skip-ssl -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" -p"$DB_PASSWORD" --silent > /dev/null 2>&1; do
    echo "Waiting for the database..."
    sleep 2
done

if ! grep -q '^APP_KEY=base64' .env; then
    php artisan key:generate --force
fi

# A fresh database has no migrations table yet; that is the one time demo data is loaded.
if php artisan migrate:status > /dev/null 2>&1; then
    php artisan migrate --force
else
    php artisan migrate --force --seed
fi

exec php artisan serve --host=0.0.0.0 --port=8000
