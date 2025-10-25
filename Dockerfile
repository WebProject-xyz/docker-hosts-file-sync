FROM php:8.3-alpine@sha256:925a5a800db2abe3bcda64da19d2637c515ddfed277f25741d3ff06a814efafa
LABEL authors="Ben"
LABEL org.opencontainers.image.source = "https://github.com/WebProject-xyz/docker-hosts-file-sync"
LABEL org.opencontainers.image.description="php-docker-api-client app syncs you hosts file on docker api system events"
LABEL org.opencontainers.image.licenses=MIT
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=composer:latest@sha256:24f538daf6d1e33e0859033ca2b50955b68fa69f7f3d2d1cba9a51b6cfdcf606 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY --link . /app
RUN composer install --no-progress --prefer-dist -o \
    && touch /app/hosts

ENTRYPOINT ["/app/bin/docker-api", "synchronize-hosts", "--hosts_file=/app/hosts", "-v"]