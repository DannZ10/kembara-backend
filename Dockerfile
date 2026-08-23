# Kembara.id API — Laravel 13 on PHP 8.3 (predis = no redis extension needed)
FROM php:8.3-cli AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP deps first (better layer caching). Skip scripts: app not copied yet.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts

COPY . .
RUN cp -n .env.example .env \
    && composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
