# Production image: PHP 8.6 + Apache serving Laravel from /public.
FROM php:8.6-apache

# System libraries and PHP extensions: pdo_pgsql talks to PostgreSQL (Neon) and mbstring is
# required by Laravel (the 8.6 base image no longer ships it, and Composer does not notice
# because symfony/polyfill-mbstring claims to provide it while missing functions).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev unzip \
    && docker-php-ext-install pdo_pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*

# Fail the build, not production, if a required extension is missing.
RUN php -r 'foreach (["mbstring", "pdo_pgsql", "openssl", "tokenizer", "ctype", "fileinfo"] as $ext) { if (! extension_loaded($ext)) { fwrite(STDERR, "Missing PHP extension: $ext\n"); exit(1); } }'

# Production php.ini + our tweaks (OPcache, no PHP version header).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

# Apache: serve /public, enable pretty URLs, and listen on $PORT (Render sets it).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    PORT=8080
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && sed -ri 's!Listen 80!Listen ${PORT}!' /etc/apache2/ports.conf \
    && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!' /etc/apache2/sites-available/000-default.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

WORKDIR /var/www/html

# Dependencies first (cached layer while composer.lock does not change), without dev packages.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

# Application code, optimized autoloader, and write permissions for Laravel.
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
