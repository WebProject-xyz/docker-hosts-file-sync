FROM php:8.3-alpine@sha256:3ef6ab36e9f60607575efb088b34eeaa4f4cc0eab7c9209ff2d1fd8f01791ca5
LABEL authors="Ben"
LABEL org.opencontainers.image.source = "https://github.com/WebProject-xyz/docker-hosts-file-sync"
LABEL org.opencontainers.image.description="php-docker-api-client app syncs you hosts file on docker api system events"
LABEL org.opencontainers.image.licenses=MIT
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest@sha256:22a95bd2bc3cf6ff19550a246208138d35f0015602db3d67dc6841945324acc1 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o \
    && touch /app/hosts

ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "--hosts_file=/app/hosts", "-v"]