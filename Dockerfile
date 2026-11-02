ARG PHP_SAPI=cli
FROM php:8.4-${PHP_SAPI}-bookworm

ARG PHP_SAPI

ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install --no-install-recommends --yes \
        git \
        libicu-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
    && pecl install pcov \
    && docker-php-ext-enable pcov opcache \
    && if [ "$PHP_SAPI" = fpm ]; then apt-get install --no-install-recommends --yes libfcgi-bin; fi \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${APP_GID}" app \
    && useradd --create-home --gid "${APP_GID}" --uid "${APP_UID}" app \
    && mkdir --parents /home/app/.cache/composer \
    && chown --recursive app:app /home/app

USER app

ENV COMPOSER_CACHE_DIR=/home/app/.cache/composer

WORKDIR /app
