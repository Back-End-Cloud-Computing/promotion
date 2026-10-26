# Mapa de issues para a entrega final (N2, 10/11/2026)

Levantamento de **20/10/2026** feito com `gh`, Docker Hub e clones limpos de cada repo (branch `main`), com as seções 2 e 3 atualizadas em **26/10/2026** contra o `main` de cada repo, as issues e o Docker Hub. Responde duas perguntas: o que falta de issue em cada repositório e o que precisa estar pronto para a apresentação rodar de ponta a ponta.

Rubrica da N2 (Plano de Ensino, aula 12): **R1** arquitetura distribuída em Kubernetes (4,0), **R2** segurança, distribuição e observabilidade (3,0), **R3** apresentação técnica (3,0). É em equipe, para a sala toda, sem frontend.

> As issues dos repos dos outros integrantes foram **criadas por mim com o dono como assignee**, a pedido do Lucas (seção 4). Nenhum código ou texto de issue deles foi editado; as únicas mudanças em issues existentes foram os labels de origem no `support` (#1 a #14) e o fechamento das 3 duplicadas que eu mesmo criei lá.

## 1. Definição de pronto: "tudo funcionando"

A entrega está pronta quando, a partir de clones limpos e sem imagem local:

- [ ] Os serviços sobem num Minikube com `kubectl apply` (ou `helm install`) e todos os pods ficam `Ready`, sem `ImagePullBackOff` nem `exec format error`.
- [ ] Fluxo ponta a ponta pelo gateway: registrar e logar no `authorization`, usar o token em `/clients`, `/products`, carrinho, pedido e promoções; sem token ou com token inválido, 401.
- [ ] Cotação e consumo de cupom: o carrinho chama o `promotion` com `x-internal-secret` e o pedido consome o cupom (REST interno e/ou fila).
- [ ] Pedido criado gera notificação pelo serviço de suporte (e-mail no Mailpit), sem derrubar o pedido se o suporte falhar.
- [ ] Observabilidade da solução (professor): Prometheus e Grafana instalados no cluster, raspando o gateway, com um painel único em JSON. `/metrics` e dashboard por serviço são extra nosso.
- [ ] Helm: um chart por serviço, instalando com `values.yaml` (imagem, réplicas, recursos, host).
- [ ] Segurança: `/internal` e `/metrics` não aparecem no Ingress, serviços internos sem Ingress público, nenhuma senha em texto nos manifests, JWT RS256 validado com chave pública real.
- [ ] Roteiro e ensaio com o grupo, com tempo por pessoa.

Datas: Helm em **27/10**, Prometheus e Grafana em **03/11**, teste integrado completo antes de **03/11**, ensaio até **07/11**, N2 em **10/11**.

## 2. Situação por repositório

Fonte: manifests em `k8s/` de cada `main`, `gh run list` e a API do Docker Hub.

| Repo (dono) | CI na main | `/metrics` | Imagem publicada | Helm | Grafana JSON | Observação |
|---|---|---|---|---|---|---|
| `promotion` (Lucas) | verde | sim (negócio e HTTP) | `bascratki/promotion:1.1.0`, amd64 e arm64 | chart instalado no Minikube em 21/10 (1 réplica) | falta | Ingress só com `/api` e `/docs` |
| `api-gateway` (Lucas) | verde | sim (porta 9100) | `bascratki/api-gateway:1.0.0`, amd64 e arm64 (21/10) | falta | falta | testado no Minikube e com token real em 21/10 |
| `support` (Eduardo) | verde | sim (25/10) | `eduardofabri/support:1.0.2`, amd64 e arm64 | chart (25/10) | JSON (25/10) | implementação em Java mergeada (PR #19, #20); manifest em `1.0.2` (conferido em 26/10); consumidor RabbitMQ testado com broker real, mas o publisher do Order ainda é HTTP (#11 reaberta); evidência Order → Support → Mailpit em `support/docs/evidence/2026-10-25/` |
| `order` (Rodrigo) | verde | sim (25/10) | `ghcr.io/back-end-cloud-computing/order:latest` multi-arch (25/10) | `helm/order` (25/10) | JSON (25/10) | sem Ingress por decisão do grupo (order#9); `product-api:8000` (order#11); `POST /api/pedidos` com Cart e Product reais ainda dá 502 (order#18, rotas `/api/cesta` e `/internal/produtos/...` diferentes das expostas); com fixtures de Cart e Product o pedido deu 201 (evidência do Rodrigo, 25/10); publisher RabbitMQ desligado até o Cart enviar o cupom |
| `shopping-cart` (João Liz) | verde (23/10) | sim (23/10) | `jcliz/shopping-cart:1.1.0`, amd64 e arm64 (23/10) | falta (cart#5) | parcial (cart#6) | `.env.example` voltou; aponta para `product-api:8000` e chama o `promotion`; preço vem do Product, que o expõe desde 24/10 (product#9); falta rodar o fluxo |
| `authorization` (Eduardo) | verde (25/10) | sim (25/10) | `eduardofabri/authorization:1.2.1`, amd64 e arm64 | chart (25/10) | JSON (25/10) | senhas do banco e do admin saíram do manifest (PR #11); falta a chave e o usuário oficiais para o PROMO-002 (authorization#8) |
| `client` (Eduardo) | verde (25/10) | sim (25/10) | `eduardofabri/client:1.2.0`, amd64 e arm64 | chart (25/10) | JSON (25/10) | senha do Postgres saiu do manifest (PR #9) |
| `product` (João Correa) | verde | sim (24/10) | `joao2006/product:1.1.0`, amd64 e arm64 (24/10) | chart em `helm/product` (24/10) | `grafana/product-dashboard.json` (24/10) | Service `product-api:8000` confirmado; expõe `price` e `stock`; `POST /products` responde antes do pipeline de LLM |
| `llm-provider` (João Correa) | verde | sim (25/10) | `joao2006/llm-provider:1.1.0`, amd64 e arm64 | chart (25/10) | JSON (25/10) | Ingress removido; compose autônomo |
| `embedding-reranking` (João Correa) | verde | sim (24/10) | `joao2006/embedding-reranking:1.1.0`, amd64 e arm64 | chart (24/10) | JSON (24/10) | Ingress removido (24/10) |
| `vector-db` (João Correa) | verde | sim (24/10) | `joao2006/vector-db:1.1.0`, amd64 e arm64 (24/10) | chart (24/10) | JSON (24/10) | Ingress removido (24/10) |

Achados que atravessam repositórios:

- **Arquitetura mista de imagem.** Resolvida em 25/10 nas tags publicadas: as do Eduardo (`authorization:1.2.1`, `client:1.2.0`, `support:1.0.2`) e as do João Correa (`1.1.0`) têm `linux/amd64` e `linux/arm64` no Docker Hub, conferido pela API. A do `order` está no GHCR e o Rodrigo declara multi-arch (order#5); não conferi. Falta alinhar os manifests: `support/k8s/support.yaml` ainda usa `1.0.1`.
- **Nome do Service do Product (INT-009).** O Service se chama `product-api` (`product/k8s/product.yaml`), mas `order/k8s/order.yaml` ainda usa `http://product:8000` (order#11); no cluster esse nome não resolve. O gateway e o `shopping-cart` (23/10) já usam `product-api`.
- **Compose dos serviços de IA e do Product.** Corrigido em 24/10 em `product`, `vector-db` e `embedding-reranking` (compose autônomo, a partir de um clone isolado; não rodei). Falta o `llm-provider` (#7).
- **Quem chama o `promotion`** (INT-002): o Cart chama `calculate` desde 23/10 e a cotação com desconto foi rodada em 25/10 (160 sem cupom, 144 com cupom de 10%). O consumo do cupom ainda não tem chamador: o consumer RabbitMQ do `promotion` (#51) não existe e o Order não recebe `coupon_code` de uma cotação do Cart. O contrato `OrderConfirmed` está aceito (promotion#69).
- **Degradação** (shopping-cart#12): sem timeout no `fetch` do Cart, com o `promotion` fora o resultado não é determinístico (um 504, três 200 sem desconto em 25/10).

## 3. Issues do `promotion` e do `api-gateway`

Criadas em 20/10/2026, dono `@LucasStop`. Rastreio no `promotion`: [#44](https://github.com/Back-End-Cloud-Computing/promotion/issues/44) e [#56](https://github.com/Back-End-Cloud-Computing/promotion/issues/56). A [#37](https://github.com/Back-End-Cloud-Computing/promotion/issues/37) é a **issue mãe** do `promotion` e as #44 a #54 são sub-issues dela.

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

**`api-gateway`** (já abertas: #9 multi-arch no Docker Hub, #11 Helm, #12 painel Grafana único da solução, #13 e2e com token real, #14 ensaio; sem issue mãe)

| Issue | Título | Rubrica | Depende de |
|---|---|---|---|
| [#16](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/16) | `fix: align the k8s image reference with a published tag` | R1 | #9 |
| [#17](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/17) | `test: confirm routes against the real upstream paths and service names` | R2 | serviços de pé |
| [#18](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/18) | `test: deploy on minikube with all upstreams behind api.ganjj.local` | R1, R2 | #9, #13, aula de 03/11 |
| [#19](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/19) | `feat: install prometheus and grafana in the cluster` | R2 | aulas de 27/10 e 03/11; a #12 depende dela |

### Estado em 21/10/2026

Depois do [teste de integração](teste-integracao-2026-10.md) e das entregas do dia.

| Estado | Issues |
|---|---|
| Feito e fechado | `promotion` #45 Helm no Minikube, #46 pull real da imagem, #48 métricas HTTP, #53 teste dos 11, #58 `product_id` string; `api-gateway` #9 e #16 imagem publicada, #17 rotas, #22 yarn, #23 timeout de `POST /products` |
| Executado com a chave de lab, falta a oficial do Eduardo | `promotion` #49 e `api-gateway` #13 (todos os itens rodados em 21/10; bloqueadas por authorization#8) |
| Parcial | `api-gateway` #18 deploy no Minikube (falta o scrape do Prometheus, depende da #13 e da aula de 03/11) |
| Decidido, pendente de outro repo | `promotion` #50 e #51 (REST interno agora; fila depende do aceite do Rodrigo, order#10), #52 (shopping-cart#11, order#13 e order#10) |
| Depende das aulas | `api-gateway` #11 Helm (27/10), #19 e #12 Prometheus e Grafana (03/11), `promotion` #47 dashboard (03/11, depende da api-gateway#19) |
| Ensaio | `promotion` #54, `api-gateway` #14 (até 07/11) |

### Estado em 26/10/2026

Conferido com `ganjj-check --all` (janela de 25/10 16:22Z a 26/10 12:48Z), sem divergência de commit direto na `main` nem issue fechada à mão.

| Estado | Itens |
|---|---|
| Rodado em 25/10, com evidência no lab local | cadeia pelo gateway com a imagem publicada `promotion:1.1.0` (29 PASS, 1 FAIL: o pedido); `/metrics` dos 11 serviços; `/internal` e `/metrics` em 404 pelo gateway |
| Bloqueado por outro dono | order#18 (Rodrigo): rotas do Cart e do Product; shopping-cart#12 (João Liz): timeout para degradar |
| Nosso, pendente | `promotion` #51 (consumer RabbitMQ), #49 (chave oficial do Eduardo), #47 (dashboard), #52 (depende de order#18 e shopping-cart#12) |
| Depende das aulas | Helm de todos os serviços (27/10), Prometheus e Grafana (03/11) |
| Ensaio | `promotion` #54, `api-gateway` #14, order#8 e support#14 (até 07/11) |

Em 24/10 João Correa fechou product#9 e #10 e vector-db#8 e #9 (commits diretos na `main`, sem PR); esses bloqueadores caíram. Desde 23/10 esses bloqueios também estão como dependência formal do GitHub (`blocked by`, 17 vínculos entre os repos): o cartão da issue mostra "Blocked", mas o GitHub não impede fechá-la.

## 4. Issues dos outros repos (criadas em 20/10/2026)

As 65 issues abaixo foram criadas em 20/10/2026 por [#56](https://github.com/Back-End-Cloud-Computing/promotion/issues/56), cada uma com o dono como assignee. Em `order`, `shopping-cart`, `authorization`, `client`, `product`, `llm-provider`, `embedding-reranking` e `vector-db` a #1 ("N2 readiness") é a **issue mãe** e as novas são **sub-issues** dela: a barra de progresso da #1 mostra o avanço e ela só fecha quando todas as filhas fecharem. No `support` as 3 issues foram criadas soltas, sem mãe, e depois fechadas como duplicadas (ver abaixo). No `api-gateway` não há mãe; o repo já nasceu com issues próprias.

### Origem: professor x nossa

Cada issue tem o label `origem: professor` ou `origem: nossa`, para filtrar. O critério é o Plano de Ensino (`_PE Backend Cloud Computing.pdf`):

- **professor**: está no cronograma ou na rubrica da N2. Helm (aula 10, 27/10), JWT (aula 9, 20/10), integração ponta a ponta e deploy em Kubernetes (R1, "demonstrando arquitetura, distribuição, segurança e integração entre serviços"), serviço de suporte e gateway (Aula 07) e a apresentação (R3).
- **nossa**: decisão do grupo ou necessidade técnica. `/metrics` por serviço (a rubrica pede observabilidade da solução, não de cada serviço), CI, `.env.example`, imagem multi-arch, remover Ingress público, senha fora do manifest, compose, health, nome e porta do Product, contrato RabbitMQ, dashboard por serviço.

A observabilidade da solução vem do gateway (porta de entrada de tudo): [api-gateway#19](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/19) instala Prometheus e Grafana no cluster e a [api-gateway#12](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/12) é o painel único da solução (`origem: professor`). Filtro: `gh issue list -R Back-End-Cloud-Computing/<repo> --label "origem: professor"`.

| Repo (dono) | Mãe | Filhas | Professor | Nossa |
|---|---|---|---|---|
| `order` (Rodrigo) | [#1](https://github.com/Back-End-Cloud-Computing/order/issues/1) | [#4](https://github.com/Back-End-Cloud-Computing/order/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/order/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/order/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/order/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/order/issues/8), [#9](https://github.com/Back-End-Cloud-Computing/order/issues/9), [#10](https://github.com/Back-End-Cloud-Computing/order/issues/10), [#11](https://github.com/Back-End-Cloud-Computing/order/issues/11), [#12](https://github.com/Back-End-Cloud-Computing/order/issues/12) | 3 | 6 |
| `shopping-cart` (João Liz) | [#1](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/8), [#9](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/9), [#10](https://github.com/Back-End-Cloud-Computing/shopping-cart/issues/10) | 2 | 7 |
| `authorization` (Eduardo) | [#1](https://github.com/Back-End-Cloud-Computing/authorization/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/authorization/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/authorization/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/authorization/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/authorization/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/authorization/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/authorization/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/authorization/issues/8), [#9](https://github.com/Back-End-Cloud-Computing/authorization/issues/9), [#10](https://github.com/Back-End-Cloud-Computing/authorization/issues/10) | 4 | 5 |
| `client` (Eduardo) | [#1](https://github.com/Back-End-Cloud-Computing/client/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/client/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/client/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/client/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/client/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/client/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/client/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/client/issues/8) | 2 | 5 |
| `product` (João Correa) | [#1](https://github.com/Back-End-Cloud-Computing/product/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/product/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/product/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/product/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/product/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/product/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/product/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/product/issues/8) | 2 | 5 |
| `llm-provider` (João Correa) | [#1](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/llm-provider/issues/8) | 2 | 5 |
| `embedding-reranking` (João Correa) | [#1](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/embedding-reranking/issues/8) | 2 | 5 |
| `vector-db` (João Correa) | [#1](https://github.com/Back-End-Cloud-Computing/vector-db/issues/1) | [#2](https://github.com/Back-End-Cloud-Computing/vector-db/issues/2), [#3](https://github.com/Back-End-Cloud-Computing/vector-db/issues/3), [#4](https://github.com/Back-End-Cloud-Computing/vector-db/issues/4), [#5](https://github.com/Back-End-Cloud-Computing/vector-db/issues/5), [#6](https://github.com/Back-End-Cloud-Computing/vector-db/issues/6), [#7](https://github.com/Back-End-Cloud-Computing/vector-db/issues/7), [#8](https://github.com/Back-End-Cloud-Computing/vector-db/issues/8) | 2 | 5 |

Títulos, "Pronto quando" e rubrica de cada uma estão na própria issue.

### `support` (Eduardo)

Já tinha as issues #1 a #14 (scaffold, endpoint, SQLite, e-mail, SMS e WhatsApp, métricas, imagem, manifests, Helm, Grafana, RabbitMQ, e2e e ensaio). As três que criei ([#16](https://github.com/Back-End-Cloud-Computing/support/issues/16), [#17](https://github.com/Back-End-Cloud-Computing/support/issues/17), [#18](https://github.com/Back-End-Cloud-Computing/support/issues/18)) duplicavam a #8, #2, #11 e #13 e foram **fechadas como not planned**, com comentário apontando a issue que já cobre. Labels: `origem: professor` nas #1 a #5, #8, #9, #13 e #14; `origem: nossa` nas #6, #7, #10, #11 e #12.

### `order`: issues já existentes

Em 25/10 o Rodrigo fechou #3, #4, #5, #6, #7, #11, #13 e #14 (commits diretos na `main`); ficam abertas #1 (mãe), #2 (suporte, lado do Order feito), #8 (ensaio), #10 (contrato RabbitMQ, aceito no promotion#69) e #12 (e2e pelo gateway, bloqueada). A #9 (Ingress `order.ganjj.local`) foi fechada como **not planned** em 23/10 pelo Rodrigo: decisão do grupo de manter o gateway como única entrada externa do Order, sem Ingress próprio, e `/metrics` só dentro do cluster (ADR 0003 do gateway).

### Decisões do grupo (sem dono único)

Combinar no grupo (registrar em issue do `promotion` se virar tarefa):

- **Padrão de Helm**: um chart por serviço ou guarda-chuva; `values.yaml` com imagem, réplicas, recursos e host. Sugestão: um chart por serviço, igual ao `helm/promotion`.
- **Padrão de Prometheus e Grafana**: a rubrica pede observabilidade da solução, então o mínimo é o gateway. `GET /metrics`, anotações `prometheus.io/*` e `/metrics` fora do Ingress por serviço é decisão nossa, não do professor.
- **Ambiente da demo**: Minikube (padrão dos laboratórios, sem custo) ou Amazon EKS (laboratório da aula 06, consome crédito do Learner Lab).
- **Imagem**: todas multi-arch (`docker buildx build --platform linux/amd64,linux/arm64`), com a mesma tag do manifest.
- **Ordem das falas** e quem conduz a demo ao vivo.

## 5. Como manter este documento

Rodar o `ganjj-check` (sincroniza os clones, reconfere a integração e olha issues, PRs e e-mail) depois de cada aula e antes do ensaio, e atualizar as tabelas das seções 2 e 3.

O `ganjj-check` cobre os 11 repos (os 9 de antes mais `api-gateway` e `support`) e olha o que mudou desde a última execução (primeira vez, ou com `--since`/`--days`: 3 dias). Ele lê tudo e não escreve em nenhum repo:

- **Script** (`scripts/ganjj-check.sh --issues`): issues novas, atualizadas e fechadas (com bloqueios `blocked by` abertos), PRs (estado, rascunho, CI, revisão) e commits na `main`. Marca como divergência a issue fechada à mão, sem commit nem PR, e o commit direto na `main`, sem PR.
- **E-mail** (passo do comando `/ganjj-check`, pelo Gmail, somente leitura): notificações do GitHub da mesma janela, cruzadas com a saída do script, para achar o que só o e-mail mostra, como comentários.
- **Divergências**: o comando relata e deixa rascunhos de comentário prontos para as issues. Nada é postado sem a aprovação do Lucas, e comentar em repo de outro integrante depende da autorização dele. O teste integrado ([#53](https://github.com/Back-End-Cloud-Computing/promotion/issues/53)) vai confirmar ou refutar os achados da seção 2 e alimentar [`integration-issues.md`](integration-issues.md).
