# Image for the Laravel side of the local stack (K-08): the API, the rollup consumer and the
# scheduler all run from it. Built from the repo root so the weights file and the modules
# folder sit where config/engine.php looks for them (two levels up from apps/api).

FROM php:8.3-cli

# libpq-dev for pdo_pgsql; git and unzip so Composer can fetch packages.
RUN apt-get update \
    && apt-get install --yes --no-install-recommends libpq-dev git unzip \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /srv/navuuna/apps/api

# Dependencies first, so a code change does not re-download every package.
COPY apps/api/composer.json apps/api/composer.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader

COPY apps/api ./
# The whole signal service folder, for weights.yml and modules/*/findings.yml (Devyan's files).
COPY services/signals /srv/navuuna/services/signals
RUN composer dump-autoload --optimize

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
