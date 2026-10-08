FROM php:8.4-cli-bookworm

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
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN groupadd --gid "${APP_GID}" app \
    && useradd --create-home --gid "${APP_GID}" --uid "${APP_UID}" app \
    && mkdir --parents /home/app/.cache/composer \
    && chown --recursive app:app /home/app

USER app

ENV COMPOSER_CACHE_DIR=/home/app/.cache/composer

WORKDIR /app
