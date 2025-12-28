FROM dunglas/frankenphp:1.11.1-php8.4.16-bookworm

RUN apt update \
    && apt install -y net-tools procps npm \
    && docker-php-ext-install pcntl

RUN pecl install redis \
    && docker-php-ext-enable redis

RUN apt install -y libbrotli-dev libz-dev

RUN pecl install swoole \
    && docker-php-ext-enable swoole

WORKDIR /app
