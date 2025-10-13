FROM php:8.3-alpine@sha256:925a5a800db2abe3bcda64da19d2637c515ddfed277f25741d3ff06a814efafa
LABEL authors="Ben"
LABEL org.opencontainers.image.source = "https://github.com/WebProject-xyz/docker-hosts-file-sync"
LABEL org.opencontainers.image.description="php-docker-api-client app syncs you hosts file on docker api system events"
LABEL org.opencontainers.image.licenses=MIT
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest@sha256:3ca62a8176c743eebef305ac2b93094a733dd5a34b5c1e3d3cf6cbbbd0792649 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o \
    && touch /app/hosts

ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "--hosts_file=/app/hosts", "-v"]