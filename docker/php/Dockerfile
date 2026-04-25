FROM php:8.3-cli-alpine

ARG UID=1000
ARG GID=1000

RUN apk add --no-cache \
    bash \
    git \
    unzip \
    zip \
    linux-headers \
    $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    if ! getent group ${GID} > /dev/null 2>&1; then \
        addgroup -g ${GID} -S appuser; \
    fi; \
    adduser -u ${UID} -S appuser -G "$(getent group ${GID} | cut -d: -f1)"

WORKDIR /app

USER appuser
