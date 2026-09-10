FROM php:8.2-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        libzip-dev \
        procps \
        unzip \
    && docker-php-ext-install pdo_mysql sockets pcntl zip opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GROUP_ID} appuser \
    && useradd -u ${USER_ID} -g appuser -m -s /bin/sh appuser

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www

USER appuser

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
