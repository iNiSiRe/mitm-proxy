FROM php:7.3-fpm AS base

# Install additional soft
RUN export DEBIAN_FRONTEND=noninteractive && \
    apt-get -qq update && \
    apt-get -y install zip unzip git zlib1g-dev libmemcached-dev supervisor git libevent-dev \
    make \
    libssl-dev \
    inetutils-ping

# Install extensions
RUN docker-php-ext-install pdo_mysql \
 && docker-php-ext-install sockets \
 && docker-php-ext-install pcntl \
 && pecl install memcached-3.1.4 && docker-php-ext-enable memcached \
 && pecl install event && docker-php-ext-enable event

ARG XDEBUG=0

RUN if [ "$XDEBUG" -eq 1 ]; then \
    pecl install xdebug-2.7.2 && \
    docker-php-ext-enable xdebug; \
fi

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
