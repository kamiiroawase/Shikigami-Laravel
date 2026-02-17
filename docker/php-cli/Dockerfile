FROM php:8.4.18-cli-bookworm

RUN groupadd -g 1000 ubuntu && \
    useradd -u 1000 -g ubuntu -m ubuntu

RUN apt update \
    && apt install -y net-tools procps supervisor \
    && docker-php-ext-install pcntl

RUN pecl install redis \
    && docker-php-ext-enable redis

#RUN apt install -y libbrotli-dev libz-dev

#RUN pecl install swoole \
#    && docker-php-ext-enable swoole

WORKDIR /app
