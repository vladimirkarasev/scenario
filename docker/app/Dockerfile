FROM ghcr.io/roadrunner-server/velox:latest AS velox

COPY ./docker/app/velox.toml velox.toml

ENV CGO_ENABLED=0

# --mount=type=secret keeps RT_TOKEN out of the image layers/history (unlike ARG/ENV, which would
# bake it in permanently) — only visible to this one RUN step.
RUN --mount=type=secret,id=rt_token \
    RT_TOKEN="$(cat /run/secrets/rt_token)" vx build -c velox.toml -o /usr/bin/

FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        gd \
        pcntl \
        pdo_pgsql \
        sockets \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=velox /usr/bin/rr /usr/local/bin/rr

COPY ./docker/app/bin/ /usr/local/bin/

RUN chmod +x /usr/local/bin/run-roadrunner

WORKDIR /var/www/html
