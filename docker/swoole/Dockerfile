FROM php:8.4.17-cli-bookworm

RUN apt update \
    && apt install -y net-tools procps \
    && docker-php-ext-install pcntl

#RUN pecl install redis \
#    && docker-php-ext-enable redis

#RUN apt install -y libbrotli-dev libz-dev

#RUN pecl install swoole \
#    && docker-php-ext-enable swoole

WORKDIR /app
