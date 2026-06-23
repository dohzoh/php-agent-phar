FROM php:8.1-cli-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN git config --global --add safe.directory /app

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
