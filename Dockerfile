FROM php:8.3-alpine@sha256:8281275c2b7fb10c149a049e832fae6e936890f839561dbe2ca0d69fc322853f
LABEL authors="Ben"
LABEL org.opencontainers.image.source = "https://github.com/WebProject-xyz/docker-hosts-file-sync"
LABEL org.opencontainers.image.description="php-docker-api-client app syncs you hosts file on docker api system events"
LABEL org.opencontainers.image.licenses=MIT
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest@sha256:b148074c5cf8e5c564e92baa3f0d2e28ffa0361ede57a03de3bf4b9cf80de54a /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o \
    && touch /app/hosts

ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "--hosts_file=/app/hosts", "-v"]