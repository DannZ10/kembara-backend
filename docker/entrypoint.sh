#!/usr/bin/env bash
set -e
cd /app

# A bundled .env (sqlite defaults from .env.example) shadows the real
# environment under Laravel's env(). When the platform injects config
# (APP_KEY present), drop it so the real env is authoritative; otherwise fall
# back to .env.example for local runs and generate a key.
if [ -n "${APP_KEY}" ]; then
  rm -f .env
else
  [ -f .env ] || cp .env.example .env
  grep -q '^APP_KEY=base64' .env || php artisan key:generate --force
fi

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
until php -r '
  try {
    $o = getenv("DB_SSL") ? [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false] : [];
    new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306), getenv("DB_USERNAME"), getenv("DB_PASSWORD"), $o);
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
    $o = getenv("DB_SSL") ? [PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false] : [];
    $p = new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"), $o);
    echo (int) $p->query("SELECT COUNT(*) FROM users")->fetchColumn();
  } catch (Throwable $e) { echo 0; }
')
if [ "${USERS}" = "0" ]; then
  echo "Seeding database..."
  php artisan db:seed --force
fi

php artisan config:clear
# Railway (and most PaaS) inject the port to bind via $PORT; fall back to 8000 locally.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
