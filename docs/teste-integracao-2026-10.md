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

## Rodada de 25/10/2026

Minikube (Colima, arm64) com os 11 serviços a partir dos manifests dos repos no `main` de 25/10. Cadeia pelo gateway (`api.ganjj.local`, port-forward do Ingress). Evidência: `chain-k8s-4.txt` (26 PASS, 4 FAIL na execução; o passo 8 foi refeito à mão e passou, ver abaixo), `deg-k8s-1.txt`, `smoke-k8s-4.txt` (23 PASS, 0 FAIL), `metrics-k8s-1.txt`. Os arquivos ficam no laboratório local, fora dos repos.

Ambiente: o promotion rodou com build local do `main` (`promotion:lab`), não com a imagem publicada; Order com `azure-sql-edge` e probes de laboratório (SQL Server 2022 não roda em arm64); Secrets de laboratório gerados no cluster.

| Passo | Resultado | Observação |
| --- | --- | --- |
| Token RS256 real do `authorization` pelo gateway (admin e cliente) | rodado, PASS | |
| `POST /products` com `price` e `stock` | rodado, PASS | 201 em menos de 10 s; SKU repetido: 409 (product#10) |
| `GET /products/{id}` devolve `price` e `stock` | rodado, PASS | product#9 |
| Promoção 20% e cupom 10% (admin) | rodado, PASS | `category` aceita só `Superiores`, `Inferiores`, `Inverno` |
| `POST /api/cart/items` | rodado, PASS (201) | shopping-cart#11 funciona; pode fechar |
| `GET /api/cart` com desconto | rodado, PASS | total 160 sem cupom, 144 com cupom de 10% (INT-002) |
| Calculate direto com `product_id` string | rodado, PASS | 200 com segredo, 403 sem segredo e com segredo errado; desconto de 20% aplicado |
| Estoque interno do Product | rodado, PASS | mesma `Idempotency-Key` duas vezes: estoque 7 e 7; sem segredo: 401 |
| `/internal` e `/metrics` pelo gateway | rodado, PASS | 404 |
| Sem token em `/clients /products /api/cart /api/pedidos /api/promotions` | rodado, PASS | 401 |
| `/metrics` dos 11 serviços | rodado, PASS | promotion com 5 famílias (3 de negócio e 2 HTTP) na build local; a imagem 1.0.0 publicada só tem as 3 de negócio |
| Degradação com o promotion em 0 pods | rodado, FAIL | `GET /api/cart` devolve 504; esperado 200 sem desconto |
| `POST /api/pedidos` | rodado, FAIL | 502 `invalid_dependency_contract` |

### Achados da rodada

| Achado | Evidência | Dono |
| --- | --- | --- |
| Cart não degrada: o `fetch` de `http/promotionClient.js` não tem timeout, então a chamada pendura e o gateway corta (504). O `catch` de `buildSummary` já devolveria o carrinho sem desconto | `deg-k8s-1.txt`; pods do promotion em 0 | shopping-cart |
| Order chama `GET /api/cesta` e o Cart expõe `/api/cart` | 502 `Cart returned invalid data: unexpected HTTP status 404` | order (Rodrigo) |
| Order chama `/internal/produtos/{long id}/reserve-stock` e `restore-stock`; o Product expõe `/internal/products/{id}/stock/decrement` e `restore` com `Idempotency-Key` | leitura do código dos dois repos | order (Rodrigo) e product |
| Imagem publicada `bascratki/promotion:1.0.0` está velha (19/10): sem `product_id` string (#58) e sem métricas HTTP (#48) | `POST /api/promotions` com id string e calculate deram 422 na 1.0.0 | promotion (publicar 1.1.0 multi-arch) |
| Manifest do Order depende de SQL Server 2022, sem opção arm64 | pod do SQL em `CrashLoop` até trocar a imagem | order |
| PR #69 aceitou o contrato `OrderConfirmed` no RabbitMQ; publisher e consumer ainda não existem | #51 vira trabalho obrigatório | promotion (#51) e order |

### Não rodado

Pedido criado (depende do Order alinhar as rotas), consumo do cupom por evento, Helm com `helm install` do grupo (aula de 27/10), Prometheus e Grafana (aula de 03/11), 11 serviços a partir das imagens publicadas (o promotion ainda depende da 1.1.0).
