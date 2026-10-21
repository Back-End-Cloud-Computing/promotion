# Teste de integração, 21/10/2026

Os 11 repos clonados limpos da `main` e rodados primeiro no Docker Compose, depois no Minikube. Mac arm64, Colima, Minikube v1.37 (driver docker), 1 réplica por serviço por falta de memória. Chaves RS256, segredos e senhas são valores de teste, só locais e fora do git. O support não tem código e ficou fora.

## Compose

10 serviços sobem e respondem. Smoke pelo gateway: 24 PASS, 0 FAIL.

- Registro e login pelo gateway devolvem token.
- Sem token e com token inválido: 401 em `/clients`, `/products`, `/api/cart`, `/api/pedidos`, `/api/promotions`.
- Rotas públicas respondem; `/metrics` e `/internal` não passam pelo gateway (404).
- O promotion exige `x-internal-secret` em `/internal`.

## Minikube

17 pods Ready (com as correções só no cluster de teste descritas abaixo). Smoke pelo Ingress: 23 PASS, 0 FAIL. O limite de 10 logins por minuto do gateway devolveu 429 quando excedido.

| Achado | Evidência | Issue |
| --- | --- | --- |
| INT-009: cart e order chamam `http://product:8000`, o Service é `product-api` | cart: `ENOTFOUND product`, 500 em "Error checking stock" | order #11, shopping-cart #9, product #8 |
| Order devolve 500 em `POST /api/pedidos` | `InvalidOperationException` em `CreateOrderRequest` (.NET 10) | order, `fix: POST /api/pedidos returns 500` |
| Cart chama rotas que o Product não expõe | `/api/products/{id}` e `/internal/products/{id}/stock` dão 404 | shopping-cart, `fix: product contract mismatch` |
| Product sem preço e estoque | schema `ProductCreate` | product, `feat: expose price and stock` |
| `POST /products` leva ~15 s, gateway corta em 10 s | 504 com produto criado; retry duplica SKU | product, `fix: POST /products takes 15 s` |
| vector-db morre se o Chroma não está pronto | `Could not connect to a Chroma server` | vector-db, `fix: add healthcheck and restart` |
| Promotion exige `product_id` inteiro, Product usa id string | `items.0.product_id must be an integer` | promotion #58 |
| llm-provider, embedding-reranking e vector-db respondem 200 sem token pelo Ingress | `curl /health` pelo Ingress | #8 dos 3 repos |
| Health sem padrão | order só `/health/live` e `/health/ready` | order, `fix: align health paths` |
| Composes de product, vector-db, embedding-reranking e llm-provider só funcionam em monorepo | `build: ./product` | #7 dos 4 repos |
| Imagens dos manifests inexistentes ou só de uma arquitetura | `ErrImageNeverPull` (order), `no match for platform` | #5, #4 e #3 de build/publish de cada repo e api-gateway #16 |

Correções aplicadas só no cluster de teste, sem tocar nos repos: imagens arm64 construídas localmente e carregadas com as tags dos manifests, SQL Server 2022 trocado por `azure-sql-edge` (não roda em arm64), réplicas reduzidas para 1.

## Helm do promotion

`helm lint` e `helm template` passam. `helm install` em namespace próprio (1 réplica) sobe a app e o MySQL, e `/api/sale` responde 200 pelo Ingress. `/health/ready` dá 404 pelo Ingress porque ele só roteia os caminhos da API.

## Ainda não testado

Pedido criado, cotação do cart no promotion e consumo do pedido no promotion não puderam rodar: dependem das correções de order, cart e product. Support, RabbitMQ e Prometheus/Grafana (aula 03/11) ficam para depois.
