FROM php:8.3-alpine
LABEL authors="Ben"
LABEL org.opencontainers.image.source=https://github.com/WebProject-xyz/php-docker-api-client
LABEL org.opencontainers.image.description="php-docker-api-client app syncs you hosts file on docker api system events"
LABEL org.opencontainers.image.licenses=MIT
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o
ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "-v"]