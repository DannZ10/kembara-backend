#!/usr/bin/env bash
set -e
cd /app

[ -f .env ] || cp .env.example .env

# Generate an app key only if neither the env nor .env already provides one.
if [ -z "${APP_KEY}" ] && ! grep -q '^APP_KEY=base64' .env; then
  php artisan key:generate --force
fi

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
until php -r '
  try {
    new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
  } catch (Throwable $e) { exit(1); }
'; do
  sleep 2
done
echo "MySQL is up."

php artisan migrate --force

# Seed only when the users table is empty, so runtime data survives restarts
# while a fresh volume still gets the demo/seed data.
USERS=$(php -r '
  try {
    $p = new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
    echo (int) $p->query("SELECT COUNT(*) FROM users")->fetchColumn();
  } catch (Throwable $e) { echo 0; }
')
if [ "${USERS}" = "0" ]; then
  echo "Seeding database..."
  php artisan db:seed --force
fi

php artisan config:clear
exec php artisan serve --host=0.0.0.0 --port=8000
