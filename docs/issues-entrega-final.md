# Mapa de issues para a entrega final (N2, 10/11/2026)

Levantamento de **20/10/2026** feito com `gh`, Docker Hub e clones limpos de cada repo (branch `main`). Responde duas perguntas: o que falta de issue em cada repositório e o que precisa estar pronto para a apresentação rodar de ponta a ponta.

Rubrica da N2 (Plano de Ensino, aula 12): **R1** arquitetura distribuída em Kubernetes (4,0), **R2** segurança, distribuição e observabilidade (3,0), **R3** apresentação técnica (3,0). É em equipe, para a sala toda, sem frontend.

> Os repos dos outros integrantes não são editados por este projeto. Para eles, as issues abaixo são **texto pronto para o dono colar**. As do `promotion` e do `api-gateway` já foram criadas (links na seção 3).

## 1. Definição de pronto: "tudo funcionando"

A entrega está pronta quando, a partir de clones limpos e sem imagem local:

- [ ] Os serviços sobem num Minikube com `kubectl apply` (ou `helm install`) e todos os pods ficam `Ready`, sem `ImagePullBackOff` nem `exec format error`.
- [ ] Fluxo ponta a ponta pelo gateway: registrar e logar no `authorization`, usar o token em `/clients`, `/products`, carrinho, pedido e promoções; sem token ou com token inválido, 401.
- [ ] Cotação e consumo de cupom: o carrinho chama o `promotion` com `x-internal-secret` e o pedido consome o cupom (REST interno e/ou fila).
- [ ] Pedido criado gera notificação pelo serviço de suporte (e-mail no Mailpit), sem derrubar o pedido se o suporte falhar.
- [ ] Observabilidade: cada serviço expõe `/metrics` (fora do Ingress), o Prometheus raspa e há um dashboard Grafana em JSON por serviço.
- [ ] Helm: um chart por serviço, instalando com `values.yaml` (imagem, réplicas, recursos, host).
- [ ] Segurança: `/internal` e `/metrics` não aparecem no Ingress, serviços internos sem Ingress público, nenhuma senha em texto nos manifests, JWT RS256 validado com chave pública real.
- [ ] Roteiro e ensaio com o grupo, com tempo por pessoa.

Datas: Helm em **27/10**, Prometheus e Grafana em **03/11**, teste integrado completo antes de **03/11**, ensaio até **07/11**, N2 em **10/11**.

## 2. Situação por repositório

Fonte: manifests em `k8s/` de cada `main`, `gh run list` e a API do Docker Hub.

| Repo (dono) | CI na main | `/metrics` | Imagem publicada | Helm | Grafana JSON | Observação |
|---|---|---|---|---|---|---|
| `promotion` (Lucas) | verde | sim | `bascratki/promotion:1.0.0`, amd64 e arm64 | chart pronto, sem teste em cluster | falta | Ingress só com `/api` e `/docs` |
| `api-gateway` (Lucas) | verde | sim (porta 9100) | só no GHCR; `bascratki/api-gateway` não existe no Docker Hub | falta | falta | manifest aponta para o Docker Hub |
| `support` (Eduardo) | verde (scaffold) | falta | sem código, sem imagem | falta | falta | exigência do professor; 14 issues abertas |
| `order` (Rodrigo) | verde | falta | `ganjj-order:local`, não publicada | falta | falta | sem Ingress; compose usa `host.docker.internal` |
| `shopping-cart` (João Liz) | **sem CI** | falta | `jcliz/shopping-cart:1.0.0`, só amd64 | falta | falta | sem `.env.example` |
| `authorization` (Eduardo) | **sem CI** | falta | `eduardofabri/authorization:1.1.0`, só arm64 | falta | falta | senhas do banco e do admin em texto no manifest |
| `client` (Eduardo) | **sem CI** | falta | `eduardofabri/client:1.1.0`, só arm64 | falta | falta | senha do Postgres em texto no manifest |
| `product` (João Correa) | verde | falta | `joao2006/product:1.0.0`, só amd64 | falta | falta | Service chama `product-api` |
| `llm-provider` (João Correa) | verde | falta | `joao2006/llm-provider:1.0.0`, só amd64 | falta | falta | Ingress público |
| `embedding-reranking` (João Correa) | verde | falta | `joao2006/embedding-reranking:1.0.0`, só amd64 | falta | falta | Ingress público |
| `vector-db` (João Correa) | verde | falta | `joao2006/vector-db:1.0.0`, só amd64 | falta | falta | Ingress público |

Achados que atravessam repositórios:

- **Arquitetura mista de imagem.** As imagens do Eduardo são só arm64 e as do João Correa e da Liz só amd64. Numa máquina Intel as do Eduardo dão `exec format error`; num Mac M-series as demais rodam por emulação. Para a demo, todas precisam de `linux/amd64` e `linux/arm64`.
- **Nome do Service do Product (INT-009).** O Service se chama `product-api` (`product/k8s/product.yaml`), mas `order/k8s/order.yaml` e `shopping-cart/k8s/shopping-cart.yaml` usam `http://product:8000`. No cluster esse nome não resolve. O gateway já usa `product-api`.
- **Compose dos serviços de IA e do Product.** O `docker-compose.yml` versionado usa `build: ./product` e `env_file: ./product/.env` (layout de monorepo). Num clone do repo isolado não funciona; o `docker-compose.override.yml` que corrige isso não está no remoto.
- **Ninguém chama o `promotion`** (INT-002) e não há fila RabbitMQ em nenhum manifest (PROMO-001).

## 3. Issues do `promotion` e do `api-gateway`

Criadas em 20/10/2026, dono `@LucasStop`. Rastreio no `promotion`: [#44](https://github.com/Back-End-Cloud-Computing/promotion/issues/44) e guarda-chuva da N2: [#37](https://github.com/Back-End-Cloud-Computing/promotion/issues/37).

**`promotion`**

| Issue | Título | Rubrica | Depende de |
|---|---|---|---|
| [#45](https://github.com/Back-End-Cloud-Computing/promotion/issues/45) | `test: helm chart on minikube` | R1, R2 | aula de 27/10 |
| [#46](https://github.com/Back-End-Cloud-Computing/promotion/issues/46) | `test: published image with real imagepullpolicy in the cluster` | R1 | Minikube |
| [#47](https://github.com/Back-End-Cloud-Computing/promotion/issues/47) | `feat: grafana dashboard exported as json` | R2 | aula de 03/11 |
| [#48](https://github.com/Back-End-Cloud-Computing/promotion/issues/48) | `feat: http request rate and latency metrics` | R2 | nada |
| [#49](https://github.com/Back-End-Cloud-Computing/promotion/issues/49) | `test: jwt rs256 end-to-end with a real token (promo-002)` | R2 | Eduardo (chave pública e usuário) |
| [#50](https://github.com/Back-End-Cloud-Computing/promotion/issues/50) | `docs: close the rabbitmq contract with order (promo-001)` | R1 | Rodrigo |
| [#51](https://github.com/Back-End-Cloud-Computing/promotion/issues/51) | `feat: rabbitmq consumer for pedido.confirmado with idempotency` | R1 | #50 |
| [#52](https://github.com/Back-End-Cloud-Computing/promotion/issues/52) | `test: cart quote and order consume against promotion (int-002, int-006)` | R1, R2 | João Liz e Rodrigo |
| [#53](https://github.com/Back-End-Cloud-Computing/promotion/issues/53) | `test: full stack on compose and minikube with the 11 services` | R1 | nada |
| [#54](https://github.com/Back-End-Cloud-Computing/promotion/issues/54) | `docs: rehearse the n2 demo` | R3 | todos os serviços de pé |

**`api-gateway`** (já abertas: #9 multi-arch no Docker Hub, #11 Helm, #12 Grafana, #13 e2e com token real, #14 ensaio)

| Issue | Título | Rubrica | Depende de |
|---|---|---|---|
| [#16](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/16) | `fix: align the k8s image reference with a published tag` | R1 | #9 |
| [#17](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/17) | `test: confirm routes against the real upstream paths and service names` | R2 | serviços de pé |
| [#18](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/18) | `test: deploy on minikube with all upstreams behind api.ganjj.local` | R1, R2 | #9, #13, aula de 03/11 |

## 4. Issues que faltam nos outros repos (texto pronto)

Cada repo já tem uma issue "N2 readiness" em formato de checklist (CI, métricas, Helm, Ingress, porta do Product, chave pública). Abaixo está só o que **não** está coberto por elas. Padrão do projeto: título em inglês `tipo: resumo`, corpo curto com **Pronto quando** e **Rubrica**.

### Todos os outros 8 repos (repetir em cada um; o `support` já tem as duas: #10 e #14)

- `feat: grafana dashboard exported as json`. Dashboard do serviço exportado em JSON e versionado no repo, importável sem edição. **Pronto quando:** painéis de requisições, erros e latência do serviço. **Depende:** aula de 03/11. **Rubrica:** R2.
- `docs: rehearse the n2 demo`. Cada dono sabe explicar o próprio serviço (porquê da stack, banco, segurança) e demonstrar o fluxo que passa por ele. **Pronto quando:** ensaio feito até 07/11. **Rubrica:** R3.

### `support` (Eduardo)

Já tem 14 issues (#1 a #14) cobrindo scaffold, endpoint, SQLite, e-mail, SMS e WhatsApp, métricas, imagem, manifests, Helm, Grafana, RabbitMQ e e2e. Faltam:

- `feat: health and ready endpoints`. `GET /health` e `/health/ready` para as probes do Kubernetes. **Pronto quando:** manifest usa as duas rotas. **Rubrica:** R1.
- `docs: record the internal secret and the event contract agreed with order`. `INTERNAL_SECRET` e o payload `OrderCreated` alinhados com o Rodrigo (issue #2 do `order`). **Pronto quando:** contrato igual nos dois repos e secret igual nos dois Secrets. **Rubrica:** R2.
- `chore: align service name and port with the gateway and k8s manifests`. Service `support` na porta 8090, como o `order` espera. **Rubrica:** R1.

### `order` (Rodrigo)

Existentes: #1 (Ingress, imagem, métricas, contrato RabbitMQ, porta do Product, Helm), #2 (notificar o suporte), #3 (headers atrás do gateway). Faltam:

- `fix: use the real product service name and port (int-009)`. `k8s/order.yaml` usa `http://product:8000` e o Service do Product é `product-api`; o `.env.example` aponta para a porta 3002 e o Product publica 8000. **Pronto quando:** `Services__Product__BaseUrl` resolve no cluster e o default do `.env.example` é 8000. **Rubrica:** R1.
- `build: publish multi-arch image to docker hub`. Hoje o manifest usa `ganjj-order:local`. **Pronto quando:** `docker buildx imagetools inspect <imagem>:<tag>` lista amd64 e arm64 e o manifest aponta para ela. **Rubrica:** R1.
- `test: end-to-end through the gateway`. Criar pedido por `api.ganjj.local` com token real e ver cotação do carrinho e e-mail do suporte. **Rubrica:** R1, R2.

### `shopping-cart` (João Liz)

Existente: #1 (`.env.example`, CI, métricas, porta do Product, cotação do Promotion, JWT, Helm). Faltam:

- `fix: use the real product service name`. `k8s/shopping-cart.yaml` usa `http://product:8000`; o Service é `product-api`. **Rubrica:** R1.
- `build: publish multi-arch image`. `jcliz/shopping-cart:1.0.0` existe só para amd64. **Pronto quando:** amd64 e arm64 na mesma tag. **Rubrica:** R1.
- `feat: call the promotion quote and consume endpoints (int-002)`. Já é item da #1; vale uma issue própria para rastrear com o `promotion` ([#52](https://github.com/Back-End-Cloud-Computing/promotion/issues/52)). **Rubrica:** R1.

### `authorization` (Eduardo)

Existente: #1 (CI, métricas, chave pública e usuário de teste, aula de JWT, Helm). Faltam:

- `build: publish multi-arch image`. `eduardofabri/authorization:1.1.0` existe só para arm64; numa máquina Intel o pod falha com `exec format error`. **Rubrica:** R1.
- `fix: move database and admin passwords out of the k8s manifest`. `DB_PASSWORD`, `ADMIN_PASSWORD` e `ORACLE_PASSWORD` estão em texto em `k8s/authorization.yaml` e `k8s/oracle.yaml`. **Pronto quando:** valores vêm de um Secret criado por script, como já é feito para `jwt-keys`. **Rubrica:** R2.

### `client` (Eduardo)

Existente: #1 (CI, métricas, Helm). Faltam:

- `build: publish multi-arch image`. `eduardofabri/client:1.1.0` só arm64. **Rubrica:** R1.
- `fix: move the database password out of the k8s manifest`. `DB_PASSWORD` e `POSTGRES_PASSWORD` em texto em `k8s/client.yaml` e `k8s/postgres.yaml`. **Rubrica:** R2.

### `product` (João Correa)

Existente: #1 (métricas, porta publicada, imagem pública, Helm). Faltam:

- `fix: committed docker-compose only works inside a monorepo`. `build: ./product` e `env_file: ./product/.env` não existem no repo isolado. **Pronto quando:** `docker compose up` funciona num clone limpo. **Rubrica:** R1.
- `build: publish multi-arch image`. `joao2006/product:1.0.0` só amd64. **Rubrica:** R1.
- `docs: tell order and cart the service name is product-api`. Confirmar `product-api:8000` como nome canônico (ou criar o Service `product`) e avisar os dois. **Rubrica:** R1.

### `llm-provider`, `embedding-reranking`, `vector-db` (João Correa)

Existentes: #1 em cada um (Ingress público, métricas, Helm). Faltam, em cada repo:

- `fix: committed docker-compose only works inside a monorepo` (mesmo problema do `product`).
- `build: publish multi-arch image`. Tags `1.0.0` só amd64. **Rubrica:** R1.

### Decisões do grupo (sem dono único)

Abrir como issue no `promotion` (#37) ou combinar no grupo:

- **Padrão de Helm**: um chart por serviço ou guarda-chuva; `values.yaml` com imagem, réplicas, recursos e host. Sugestão: um chart por serviço, igual ao `helm/promotion`.
- **Padrão de Prometheus e Grafana**: `GET /metrics`, anotações `prometheus.io/*`, `/metrics` fora do Ingress, um dashboard por serviço.
- **Ambiente da demo**: Minikube (padrão dos laboratórios, sem custo) ou Amazon EKS (laboratório da aula 06, consome crédito do Learner Lab).
- **Imagem**: todas multi-arch (`docker buildx build --platform linux/amd64,linux/arm64`), com a mesma tag do manifest.
- **Ordem das falas** e quem conduz a demo ao vivo.

## 5. Como manter este documento

Rodar o `ganjj-check` (sincroniza os clones e reconfere a integração) depois de cada aula e antes do ensaio, e atualizar as tabelas das seções 2 e 3. O teste integrado ([#53](https://github.com/Back-End-Cloud-Computing/promotion/issues/53)) vai confirmar ou refutar os achados da seção 2 e alimentar [`integration-issues.md`](integration-issues.md).
