FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY packages/core /app/packages/core
COPY sandbox /app/sandbox

RUN cd /app/sandbox \
    && composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && mkdir -p /app/sandbox/storage

ENV DB_PATH=/tmp/momokit-sandbox.sqlite
ENV PORT=8080
EXPOSE 8080

WORKDIR /app/sandbox

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public public/index.php"]
