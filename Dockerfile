FROM php:8.3-alpine
LABEL authors="Ben"

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o
ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "-v"]