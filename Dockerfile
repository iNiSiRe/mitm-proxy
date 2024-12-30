FROM php:8.2-cli AS base

# Install additional soft
RUN export DEBIAN_FRONTEND=noninteractive && \
    apt-get -qq update && \
    apt-get -y install zip unzip git git \
    make \
    libssl-dev \
    inetutils-ping telnet

# Install extensions
RUN docker-php-ext-install --ini-name 00-sockets.ini sockets \
    && docker-php-ext-install pcntl

# ext-event
RUN apt install -y libevent-dev \
    && pecl install event && docker-php-ext-enable event

# Install composer
ENV COMPOSER_HOME=/tmp/.composer

RUN curl -XGET https://getcomposer.org/installer > composer-setup.php && \
    php composer-setup.php --install-dir=/bin --filename=composer && \
    rm composer-setup.php

ARG UID=1000
ARG GID=1000

RUN groupmod -g $GID www-data && usermod -u $UID www-data

WORKDIR /var/www/html

FROM base AS build

ADD ./ ./

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install

CMD ["php", "./bin/server.php"]
