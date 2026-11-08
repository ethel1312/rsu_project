FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends unzip libonig-dev \
    && docker-php-ext-install pdo_mysql bcmath mbstring \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /srv/rsu
COPY composer.json composer.lock artisan ./
COPY app app
COPY bootstrap/app.php bootstrap/providers.php bootstrap/
COPY config config
COPY database/migrations database/migrations
COPY routes routes
COPY lang lang
COPY resources/views resources/views
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache
COPY deploy/mobile/index.php /var/www/html/index.php
COPY deploy/mobile/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --chmod=755 deploy/mobile/rsu-apache.sh /usr/local/bin/rsu-apache
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr PORT=8080
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
    && rsu-apache apache2ctl -t
CMD ["rsu-apache", "apache2-foreground"]
