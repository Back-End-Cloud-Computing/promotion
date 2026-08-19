FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql opcache zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --optimize-autoloader

COPY . .

# storage/framework/* fica fora da imagem (.dockerignore, evita cache de dev vazando —
# ver docs/fases/fase-2-docker.md). Isso também derruba os diretórios vazios que o
# Laravel precisa pra compilar view Blade (ex.: a UI do Scramble em /docs/api).
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
