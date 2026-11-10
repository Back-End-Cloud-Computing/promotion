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

A documentação de arquitetura e o plano por fases estão em `docs/`. Os docs da N2 e do Amazon EKS (infra e roteiro do dia, guia de estudo, evidências), que cobrem os 11 serviços, ficam no [`api-gateway/docs/`](https://github.com/Back-End-Cloud-Computing/api-gateway/blob/main/docs/README.md). A rubrica da N2 e como o projeto atende cada critério estão em [`docs/rubrica-n2.md`](https://github.com/Back-End-Cloud-Computing/api-gateway/blob/main/docs/rubrica-n2.md).
