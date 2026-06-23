FROM ghcr.io/roadrunner-server/roadrunner:2025.1.5 AS roadrunner

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
        supervisor \
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

COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

COPY ./docker/app/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY ./docker/app/supervisor/conf.d/ /etc/supervisor/conf.d/
COPY ./docker/app/bin/ /usr/local/bin/

RUN chmod +x /usr/local/bin/run-octane \
    /usr/local/bin/run-queue-worker \
    /usr/local/bin/run-scheduler

WORKDIR /var/www/html
