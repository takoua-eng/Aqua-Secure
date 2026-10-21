# =====================================================================
# Image Docker d'AquaSecure (Laravel 12, PHP 8.2, Apache)
# Trois étapes :
#   - vendor     : dépendances PHP de production (Composer)
#   - test       : image de tests (dépendances de dev + PCOV pour la couverture), utilisée par Jenkins
#   - production : image livrée (Apache sans privilèges root, port 8080)
# =====================================================================

# =========================================================
# Base commune : PHP 8.2 + Apache + extensions nécessaires
# =========================================================
FROM php:8.2-apache AS base

# Extensions PHP : MySQL, images (dompdf, photos de profil), zip, intl, bcmath, opcache
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev libicu-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip intl bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# =========================================================
# Étape vendor : dépendances de production
# =========================================================
FROM composer:2 AS vendor
WORKDIR /app

# Les dépendances d'abord : cette couche est réutilisée tant que composer.lock ne change pas
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist \
        --no-interaction --no-progress --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# =========================================================
# Étape test : dépendances de développement + PCOV (couverture de code pour SonarQube)
# =========================================================
FROM base AS test

# PCOV : couverture de code pour SonarQube (3 essais si pecl.php.net ne répond pas)
RUN pecl channel-update pecl.php.net || true; \
    for essai in 1 2 3; do pecl install pcov && break || sleep 15; done \
    && docker-php-ext-enable pcovCOPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-scripts \
    && php artisan package:discover --ansi

# Lancé par Jenkins avec une base MySQL de test (voir Jenkinsfile)
CMD ["php", "vendor/bin/phpunit"]

# =========================================================
# Étape production : image livrée sur Nexus puis Kubernetes
# =========================================================
FROM base AS production

# PHP en mode production + cache OPcache
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/aquasecure.ini

# Apache : racine = public/, réécriture d'URL (routes Laravel), port 8080 (pas besoin d'être root)
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:8080>!' /etc/apache2/sites-available/000-default.conf \
    && sed -ri 's!^Listen 80$!Listen 8080!' /etc/apache2/ports.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && printf "ServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-enabled/securite.conf \
    && a2enmod rewrite headers

# Code de l'application + dépendances de production
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Découverte des paquets, dossiers d'écriture, lien public/storage (photos de profil)
RUN php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions \
                storage/framework/views storage/logs bootstrap/cache \
    && rm -rf public/storage && ln -s ../storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chown -R www-data:www-data /var/run/apache2 /var/lock/apache2 /var/log/apache2

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
# sed : retire les fins de ligne Windows (CRLF) si le fichier a été édité sous Windows
RUN sed -i "s/\r$//" /usr/local/bin/entrypoint && chmod +x /usr/local/bin/entrypoint

# Apache tourne avec l'utilisateur www-data (aucun processus root dans le conteneur)
USER www-data
EXPOSE 8080

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]