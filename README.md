# GANJJ Promotion Service

Microsserviço de Promoção do e-commerce GANJJ. Responsável por promoções por
produto, cupons de desconto e campanhas com vigência.

API REST pura, sem frontend e sem autenticação própria — o JWT é emitido por
outro microsserviço e aqui é apenas verificado.

## Stack

PHP 8.2 + Laravel 12 + MySQL 8.

## Como rodar local

```bash
docker compose up -d
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Testes

```bash
./vendor/bin/pest
```

## Documentação

A documentação de arquitetura e o plano por fases estão em `docs/`.
