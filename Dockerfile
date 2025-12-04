FROM php:8.3-alpine@sha256:d5ed1691083bf185296281f8bbdfb4a65573e225b2f07eac8f0f37d95ae71279
LABEL authors="Ben"
LABEL org.opencontainers.image.source = "https://github.com/WebProject-xyz/docker-hosts-file-sync"
LABEL org.opencontainers.image.description="php-docker-api-client app syncs you hosts file on docker api system events"
LABEL org.opencontainers.image.licenses=MIT
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest@sha256:1997db9e43fde2a5a20f32fcfd50f26f698d951b3787334ebd6493d0562de97f /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o \
    && touch /app/hosts

ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "--hosts_file=/app/hosts", "-v"]