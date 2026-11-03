# Guia de estudo da N2 (10/11/2026)

Material para o Lucas estudar o projeto inteiro (11 repos), cruzar com a rubrica do Canvas e saber o que o professor
pode perguntar e como mostrar cada ponto. Baseado em leitura do código e dos docs em 03/11/2026. O que não foi
rodado no EKS vem marcado como **não testado**. Nenhuma senha, token ou chave aparece aqui.

Rubrica ([`rubrica-n2.md`](rubrica-n2.md)): **R1** 3 pts, **R2** 4 pts, **R3** 2 pts (somam 9, a tarefa vale 10).

## 1. Mapa dos serviços

| Serviço | Dono | Linguagem | Banco | Réplicas (manifest) | No EKS (`apply.sh`) | RabbitMQ |
|---|---|---|---|---|---|---|
| authorization | Eduardo | Java 21 / Spring Boot | Oracle | 3 | 1 | não usa |
| client | Eduardo | Go (net/http) | PostgreSQL 17 | 3 | 1 | não usa |
| support | Eduardo | Java / Spring Boot | SQLite (PVC) | 1 (Recreate) | 1 | consome `order.created` |
| order | Rodrigo | C# / ASP.NET Core | SQL Server 2022 | 3 | 1 | publica (outbox) |
| shopping-cart | João Liz | Node.js / Express | Redis | 3 | 1 | não usa |
| product | João Correa | Python / FastAPI | MongoDB 7 | 3 | 1 | não usa |
| llm-provider | João Correa | Python / FastAPI | nenhum (modo `mock`) | 3 | 1 | não usa |
| embedding-reranking | João Correa | Python / FastAPI | nenhum | 3 | 1 | não usa |
| vector-db | João Correa | Python / FastAPI | ChromaDB 0.5.20 | 3 | 1 | não usa |
| promotion | Lucas | PHP / Laravel | MySQL 8 | 2 | 2 | consome `order.confirmed` |
| api-gateway | Lucas | TypeScript / Fastify | nenhum | 2 | 2 | não usa |

Contagem para R1: **7 linguagens** (Java, Go, C#, Node, Python, PHP, TypeScript) e **8 armazenamentos** (Oracle,
PostgreSQL, SQL Server, MySQL, MongoDB, Redis, SQLite, ChromaDB). Cinco pessoas, onze serviços, todos com imagem
Docker. Dúvida possível: o professor pode não aceitar Redis ou Chroma como "SGBD"; os cinco relacionais mais o Mongo
cobrem a exigência sozinhos.

Só `promotion` e `api-gateway` têm duas réplicas no EKS. Por isso o balanceamento se demonstra neles, não no Order nem
no Cart. O Service `authorization` e o `client` expõem `/instance` com o nome do pod, mas só com 1 réplica no kit EKS
isso não prova nada.

## 2. Rubrica, requisito por requisito

| Requisito do Canvas | Como atendemos | Como mostrar | Evidência existente | Risco |
|---|---|---|---|---|
| Um microsserviço por integrante | 5 pessoas, 11 serviços | cada um fala do seu | repos da organização | participação identificável: commits e PRs de cada um |
| Duas linguagens, dois bancos | ver tabela da seção 1 | `k8s/` de cada repo | [`eks-2026-10-27.md`](evidencias/eks-2026-10-27.md) | Redis/Chroma como "banco" |
| Docker | imagem por serviço em Docker Hub/GHCR | `kubectl get pods -o wide` e `describe pod` (campo Image) | idem | `order` usa `:latest` com `Always`, não reproduzível |
| Amazon EKS | cluster `ganjj`, 2 nodes `t3.large`, 23 pods | `kubectl get nodes`, `kubectl get pods -A` | idem e [`jwt-eks-2026-11-02.md`](evidencias/jwt-eks-2026-11-02.md) | cluster foi apagado em 03/11; recriar leva ~20 min e a sessão do lab dura 4 h |
| Deployments, Pods, Services | manifests `k8s/` de cada repo | `kubectl get deploy,svc` | idem | |
| Database per Service | cada banco tem Service próprio (`promotion-db`, `order-sqlserver`, `postgres-client`, `oracle-auth`, `mongodb`, `chromadb`, redis) | `kubectl get svc`; mostrar que só o dono tem a credencial | `k8s/` | todos os bancos sobem com `emptyDir` ou sem volume: dado some se o pod for recriado |
| REST | gateway para todos; Cart para Product e Promotion; Order para Cart, Product e Support; Product para llm, embedding e vector | `POST /api/pedidos` pelo gateway | [`e2e-eks-2026-11-02.md`](evidencias/e2e-eks-2026-11-02.md) | |
| RabbitMQ | Order publica `OrderCreated` e `OrderConfirmed` (outbox); Support e Promotion consomem | `rabbitmqctl list_queues`, logs do `promotion-consumer` e do `support` | idem | cupom não chega ao evento (ver seção 5) |
| Service Discovery | URLs por nome: `http://promotion:8000`, `http://product-api:8000`, `rabbitmq:5672` | ConfigMaps (`kubectl get cm -o yaml`) | ConfigMaps | mostrar que nenhum usa IP de pod |
| Escalabilidade horizontal | `promotion` e `api-gateway` com 2 réplicas | 10 requisições mostrando `X-Served-By` e `X-Gateway-Served-By` | [`eks-2026-10-27.md`](evidencias/eks-2026-10-27.md) | os demais têm 1 réplica no kit EKS |
| Ingress NGINX | uma regra sem host para o `api-gateway`, que roteia por path | `kubectl get ingress`, `curl http://<ELB>/api/sale` | idem | o Canvas fala em "regras de roteamento para os microsserviços"; o roteamento por serviço está no gateway (`api-gateway/src/routes.ts`) |

Os repos também trazem `Ingress` próprios (`auth.ganjj.local`, `client.ganjj.local`, `product.ganjj.local`,
`shopping-cart.ganjj.local`, `promotion.ganjj.local`). O kit `eks/apply.sh` aplica só o do gateway. Se o professor
perguntar por regras por serviço: existem nos manifests de cada repo, mas no cluster a decisão de 27/10 foi entrada única
pelo gateway. Dado que o Canvas pede "regras de roteamento para os microsserviços", vale decidir com o grupo se aplicar os
Ingress por serviço no dia reduz esse risco (custo: nomes de host que não resolvem pelo ELB, só com `Host:` no curl).

Os 8 itens que o professor verifica na apresentação:

1. Arquitetura e responsabilidade de cada serviço: seção 1 e diagrama de [`arquitetura.md`](arquitetura.md).
2. Serviços no EKS: `kubectl get nodes` e `kubectl get pods -A`.
3. Linguagens e bancos: tabela da seção 1.
4. Isolamento da persistência: um banco e um Service por serviço; ninguém acessa tabela de outro.
5. Acesso externo pelo Ingress: `curl` no hostname do ELB.
6. Service Discovery: ConfigMaps com nomes de Service.
7. Réplicas e balanceamento: headers de pod.
8. Fluxo REST e RabbitMQ integrado: seção 6.

## 3. Autenticação (revisão)

**Emissor.** Só o `authorization` assina tokens, com RS256, e só ele guarda a chave privada
(`authorization/.../RsaKeyProvider.java:22-28`). Issuer `ganjj-authorization`. Os demais só têm a chave pública, então
não conseguem forjar token.

**Tokens** (`TokenService.java`):

| Token | Claims | Validade |
|---|---|---|
| access | `iss`, `sub` (UUID da conta), `email`, `role` (ADMIN/CLIENTE), `typ=access`, `iat`, `exp`; sem `jti` | 120 min |
| refresh | `iss`, `sub`, `jti`, `typ=refresh`; sem `role` nem `email` | 7 dias |

**Fluxo.** O cliente chama `POST /auth/login` pelo gateway (rota pública). Recebe `accessToken` e `refreshToken`. Chama as
demais rotas com `Authorization: Bearer <access>` (ou cookie `accessToken`). O gateway confere assinatura RS256, `iss` e
`typ=access`, e repassa o header original. Cada serviço revalida localmente com a chave pública; nenhum chama o
`authorization` a cada requisição. Papel ADMIN é checado no serviço de destino, nunca no gateway.

**Refresh e logout.** O refresh gira o token: o `jti` usado vai para a tabela `token_revogado` e a chave primária faz a
checagem (duplicado = 401), o que impede reuso mesmo com dois pedidos concorrentes. O logout revoga o `jti` do refresh.
**O access token continua válido até expirar (2 h)**; está documentado no README do authorization.

**Onde cada serviço pega a chave pública:**

| Serviço | Como |
|---|---|
| api-gateway | `GET /auth/public-key` no boot, com retry até 30 s; `/health/ready` dá 503 até chegar. Alternativa: `JWT_PUBLIC_KEY` |
| product, llm, embedding, vector-db | `GET /auth/public-key` no startup, retry de 60 s; sem a chave o pod aborta |
| order, client, shopping-cart | arquivo PEM montado de ConfigMap (`order-jwt-public-key`, `jwt-public-key`) |
| promotion | `JWT_PUBLIC_KEY` no ConfigMap `promotion-config`. Sem chave, 500 (nunca deixa passar) |
| support | não usa JWT; só o header `x-internal-secret` |

**Pontos fracos que o professor pode cutucar:**

- Rotação de chave: o gateway e os serviços com `GET` no boot só pegam a nova chave ao reiniciar; os que leem ConfigMap
  exigem atualizar o ConfigMap. Não há rotação automática.
- O `promotion` subiu no EKS com a chave de exemplo do `k8s/configmap.yaml` e respondia 401 a qualquer token real.
  Corrigido em [api-gateway#40](https://github.com/Back-End-Cloud-Computing/api-gateway/pull/40): o `apply.sh` agora
  troca o valor pela chave do `authorization` e reinicia os pods.
- Conta apagada ou desativada continua com access token válido por até 2 h, exceto no próprio `authorization`, cujo
  filtro consulta o banco (`SecurityFilter.java:45-52`).
- O gateway não valida `role` nem `jti`. Um CLIENTE chegar a rota ADMIN depende de o serviço devolver 403.
- Se `RsaKeyProvider` subir sem chaves configuradas, gera um par temporário e avisa no log: com 3 réplicas cada uma teria
  chave diferente. No cluster isso não ocorre porque as chaves vêm do Secret `jwt-keys`.
- **Não testado no EKS** (checklist PROMO-002): CLIENTE em rota ADMIN (esperado 403), chave pública vazia no promotion
  (esperado 500) e `Idempotency-Key` no pedido. Passaram em 02/11: admin 200; sem token, refresh-as-bearer, expirado,
  `iss` errado, `typ=refresh`, assinatura adulterada e formato inválido, todos 401; refresh 200.
- A senha do admin está no Secret `authorization-credentials` do cluster. Ler direto para variável de ambiente, nunca
  imprimir nem colar em issue, commit ou chat.

## 4. Compartilhar AWS, Amazon e EKS (a confirmar)

Nada disso foi testado; o cluster não existe agora. Tratar como hipóteses para checar no ensaio.

- No AWS Academy Learner Lab cada aluno tem a própria conta e o próprio cluster. O cluster pertence à conta de quem o
  criou, e o `kubeconfig` só funciona com as credenciais dessa sessão (vencem em 4 h).
- O ELB é público: qualquer colega consegue chamar a API pelo hostname, sem acesso à conta.
- Dar acesso de `kubectl` a outra conta exigiria access entry ou role IAM entre contas. O lab já negou
  `eks:DescribeNodegroup` por SCP, então é provável que bloqueie. Para confirmar sem custo: `aws sts get-caller-identity`
  e `aws eks list-access-entries --cluster-name ganjj` com o cluster de pé.
- Credenciais do lab nunca em Git, issue ou chat; compartilhar só por canal privado.

Opções para o dia 10/11:

1. **Um hospedeiro** (recomendado): quem tiver a sessão de lab sobe o cluster e projeta o terminal. Cada integrante fala do
   próprio serviço e pede ao hospedeiro os comandos (`kubectl logs deploy/<serviço>`), ou roda `curl` contra o ELB do
   próprio notebook.
2. **Cada um com a sua cópia:** cada integrante sobe o próprio cluster (20 min, 2 nodes) e mostra só o seu serviço. Gasta
   crédito de todos e exige as 11 imagens públicas e as chaves do `authorization` na máquina de cada um.

Antes do dia: um ensaio completo com o hospedeiro, cronometrado, e o cluster subido pelo menos 30 min antes da aula
(o DNS do ELB demora a propagar).

## 5. O limite do cupom (promotion#52, order#12)

**Estado em 03/11.** O Cart já persiste o cupom (shopping-cart#13, `edc549e`, imagem `1.1.1`): `PUT` e `DELETE
/api/cart/coupon` guardam o cupom em `cart:{user}:coupon` depois que o promotion o aceita, e `GET /api/cart` sempre cota
com ele. O desconto é distribuído nas unidades, então `discounted_price × quantity` soma o total que o Order cobra.
O Order passou a ler o campo `coupon` e a preencher `coupon_code` (order#25, `d2bf0ef`, 03/11) e order#12 fechou com E2E no
Minikube (`outcome=consumed`). Os pontos 2 a 4 abaixo descrevem o estado até 02/11; o E2E com cupom ainda não foi rodado no EKS.

Até 02/11 o cupom se perdia em quatro pontos, nesta ordem:

1. **Carrinho (resolvido em 03/11):** o cupom existia só como `?coupon=` em `getCart`; nada era gravado, e o hash Redis
   `cart:{user}` guardava só itens.
2. **Order lendo o carrinho (resolvido em 03/11):** `CartClient` lia só `items`; agora lê `coupon` e `CartSnapshot` tem `CouponCode`.
3. **Evento (resolvido em 03/11):** `CreateOrderHandler` passava `null` como `CouponCode`; agora passa `cart.CouponCode`.
4. **Consumidor:** `OrderConfirmedHandler.php:63-64` devolve `NO_COUPON` quando o evento vem sem cupom (E2E de 02/11, `outcome=no_coupon`); com cupom, `outcome=consumed` e o `usage_count` sobe (Minikube, 03/11).

Também: `POST /internal/coupons/{code}/consume` existe e é testado, mas ninguém o chama; o contrato alvo com `quote_id` do
`INTEGRATION-CONTRACTS.md` não foi implementado. O runbook do Order manda **não** apresentar uma mensagem AMQP manual com
cupom como se viesse do Order, a menos que o E2E do EKS tenha sido rodado. Dizer a verdade na banca: o cupom vai do Cart
ao evento e foi consumido no Minikube; no EKS isso só vale depois do ensaio.

## 6. Fluxo integrado para mostrar (R3)

1. `POST /api/pedidos` pelo gateway, com `Idempotency-Key`.
2. O Order lê o carrinho (REST), baixa estoque no Product (REST, `x-internal-secret`) e, numa transação SQL, grava
   pedido, registro de idempotência e duas linhas de outbox.
3. O `OutboxBackgroundService` publica `order.created` e `order.confirmed` na exchange `ganjj.orders` (confirms ligados,
   entrega "pelo menos uma vez").
4. O Support consome `support.order-created` (DLQ `support.order-created.dlq`), grava no SQLite com `event_id` como chave e
   envia o e-mail (Mailpit).
5. O Promotion consome `promotion.order-confirmed` (DLQ `promotion.order-confirmed.dlq`), ack só depois do commit,
   idempotente por `order_id`.

Evidência rodada em 02/11: pedido 201, mesma `Idempotency-Key` devolve o mesmo id, estoque 10 para 8, 1 e-mail no Mailpit,
`outcome=no_coupon`, filas e DLQs zeradas ([`e2e-eks-2026-11-02.md`](evidencias/e2e-eks-2026-11-02.md)).

Se o Support ou o Promotion falhar, o pedido continua válido: o Support também recebe um `POST` HTTP de fallback do Order
com o mesmo `event_id`, e o outbox reenvia o que ficou pendente.

## 7. Banco de perguntas

### Conceitos

- **Service Discovery.** O pod acha outro pela DNS interna do cluster (CoreDNS), pelo nome do Service. IP de pod muda a
  cada recriação. Ex.: o Cart chama `http://promotion:8000`.
- **Ingress x Service x gateway.** O Service ClusterIP dá nome e balanceamento interno. O Ingress NGINX recebe tráfego de
  fora (por um ELB) e aplica regras L7. O gateway é um serviço nosso atrás do Ingress: allowlist de rotas, JWT, rate limit.
- **Como prova o balanceamento?** Pelos headers `X-Served-By` (hostname do pod do promotion) e `X-Gateway-Served-By`
  (pod do gateway) variando em 10 requisições; ou `kubectl logs` por pod.
- **Database per Service.** Cada serviço tem banco e Service próprios; ninguém lê tabela de outro. Os dados cruzam por
  REST ou por evento, nunca por SQL.
- **Por que RabbitMQ além de REST?** Notificar e consumir cupom não pode travar nem falhar o pedido. A fila desacopla e
  tolera o consumidor fora do ar.
- **Outbox.** O evento é gravado na mesma transação do pedido; um worker publica depois. Evita o dual-write (pedido salvo
  sem evento, ou evento sem pedido).
- **Idempotência.** `Idempotency-Key` no pedido (hash do request + status); `event_id`/`order_id` como chave nos
  consumidores. A entrega é pelo menos uma vez, então o consumidor precisa deduplicar.
- **DLQ.** Mensagem inválida (JSON quebrado, fora do contrato) vai para a fila morta. Falha transitória volta para a fila
  (Promotion: `nack(requeue)` com espera de 1 s; Support: 3 tentativas locais e depois DLQ).
- **Por que RS256 e não HS256?** Os consumidores só precisam da chave pública; nenhum consegue forjar token.
- **emptyDir no lugar de PVC?** O node role do Learner Lab não tem a policy do EBS CSI; um PVC ficaria `Pending`. O preço:
  dado some quando o pod é recriado (rodar o seed do promotion de novo).
- **Por que réplicas em 1 fora do gateway e do promotion?** Caber em 2 nodes `t3.large` e ainda provar balanceamento.
- **Liveness sem banco, readiness com banco.** Lentidão do banco não pode derrubar todos os pods; readiness só tira o pod
  do balanceamento.
- **Migration em initContainer.** Não roda em paralelo com várias réplicas (promotion).

### Perguntas armadilha

- **Por que o gateway e não uma regra de Ingress por serviço?** Decisão de 27/10, por prazo e por concentrar JWT, rate
  limit e allowlist num ponto. Os manifests por serviço existem nos repos. Risco assumido, ver seção 2.
- **O que acontece se o RabbitMQ cair?** O Order continua criando pedidos; o outbox guarda os eventos pendentes e reenvia
  com backoff (até 300 s). Os consumidores reconectam. Observação: o broker não tem volume, então as mensagens que já
  estavam na fila se perdem se o pod do broker for recriado.
- **E se o banco do promotion cair no meio do consumo?** Nada é gravado e a mensagem volta para a fila; quando o banco
  volta, é processada uma vez.
- **Quem pode ler o banco do promotion?** Só o promotion; a senha está no Secret `promotion-secrets` e o Service
  `promotion-db` é ClusterIP.
- **O que acontece se o Authorization cair?** O gateway já tem a chave em memória e continua validando tokens; só login,
  refresh e logout falham. No boot, sem a chave, o gateway fica não pronto (503) e não recebe tráfego.
- **O token de um usuário apagado ainda vale?** Sim, por até 2 h, nos serviços que só validam a assinatura.
- **Por que PHP/Laravel?** Stack livre confirmada pelo professor; o foco é a arquitetura.
- **Quantos serviços usam RabbitMQ?** Três de onze: Order (publica), Support e Promotion (consomem). Cart, Product e IA
  só usam REST.
- **Os pedidos somem se o pod do SQL Server reiniciar?** No EKS sim (`emptyDir`). Nos manifests dos repos há PVC.

### Por serviço (dono responde, você deve saber)

**authorization (Eduardo).** O que o JWT leva? Como evita reuso do refresh? (INSERT do `jti` com chave primária.) Como se
cria o primeiro ADMIN? (seed por `ADMIN_EMAIL`/`ADMIN_PASSWORD`; o cadastro público sempre cria CLIENTE.) Por que Oracle?
(rubrica de dois bancos; JPA.) `/instance` serve para provar pod.

**client (Eduardo).** O id do cliente vem do `sub` do token, nunca do corpo. `UNIQUE(account_id)` impede perfil
duplicado. Go: binário leve (limite de 128 Mi), segunda linguagem. Valida offline com a chave de um ConfigMap.

**support (Eduardo).** Consome `order.created`; ack manual só depois de gravar no SQLite; duplicata (`event_id`) é ack sem
reenviar. Uma réplica porque SQLite com PVC `ReadWriteOnce` (estratégia `Recreate`). `/internal/*` protegido por
`x-internal-secret` e nunca roteado pelo gateway.

**order (Rodrigo).** Saga com compensação: se algo falha depois de baixar estoque, restaura em ordem reversa com
`operation_id` determinístico. Chave igual com request diferente dá 409. Se o processo cair entre a baixa e o commit, a
idempotência fica `InProgress`, o retry responde 409 e o estoque não é restaurado sozinho. Sem retry nem circuit breaker
nos clientes HTTP (timeouts de 3 s e 2 s). Sem Ingress próprio.

**shopping-cart (João Liz).** Redis por ser dado temporário com TTL de 7 dias. Se o Promotion cair, o carrinho vem sem
desconto (`discounts_applied:false`, timeout de 2 s). Liveness TCP para um Redis lento não matar o pod. Guarda o cupom
aplicado (`PUT /api/cart/coupon`) desde 03/11.

**product (João Correa).** MongoDB: documento flexível e índice de texto em português. `POST /products` responde 201 e
gera descrição, resumo e embedding em segundo plano (o fluxo síncrono levava ~15 s e o gateway corta em 10 s). Erro nessa
etapa fica no campo `error` do produto, sem retry. A busca semântica cai para a lexical se o embedding falhar.

**llm-provider / embedding-reranking / vector-db (João Correa).** O llm-provider roda em modo `mock` por padrão (texto
falso) e só usa OpenRouter com Secret. O embedding usa `paraphrase-multilingual-MiniLM-L12-v2` em CPU; o primeiro
`/embed` de cada pod pode estourar o timeout de 30 s do Product se o modelo não vier na imagem (a conferir). O Chroma é
o banco vetorial; reiniciar o pod apaga os embeddings e não há reindexação.

**promotion (Lucas).** Ver [`apresentacao-n2.md`](apresentacao-n2.md): ack depois do commit, `order_id` como chave
primária em `processed_orders`, DLQ para payload inválido, consumer em Deployment próprio, dinheiro em centavos inteiros.

**api-gateway (Lucas).** Fastify com proxy por stream. Allowlist: o que não está em `routes.ts` dá 404, o que esconde
`/internal`, `/metrics` e `/health` dos serviços. Rate limit por IP em memória (100/min geral, 10/min em login e registro):
cada réplica conta separado, então o limite efetivo é multiplicado pelas réplicas. Remove `x-internal-secret` do pedido e
`Host`/`X-Powered-By` da resposta. Timeout de 10 s (30 s no `POST /products`).

## 8. Roteiro sugerido (cinco pessoas)

Tempo total depende do professor; ordem que cobre as rubricas:

1. Arquitetura (1 min): diagrama e tabela da seção 1. Quem fala: qualquer um.
2. EKS e Docker (2 min): `kubectl get nodes`, `kubectl get pods -A -o wide`, `kubectl get svc`.
3. Ingress e Service Discovery (2 min): `curl http://$ELB/api/sale`; `kubectl get ingress`; ConfigMaps do gateway e do Cart.
4. Balanceamento (2 min): loop de 10 requisições com `X-Served-By` e `X-Gateway-Served-By`; `kubectl delete pod <um>` e
   `get pods -w` (autorrecuperação, extra).
5. Cada integrante (2 min cada): o próprio serviço, o banco e uma chamada real.
6. Fluxo integrado (3 min): login, produto, item no carrinho, `POST /api/pedidos`, e-mail no Mailpit,
   `rabbitmqctl list_queues name messages consumers`, `kubectl logs deploy/promotion-consumer`.
7. Perguntas.

Comandos:

```bash
ELB=$(kubectl -n ingress-nginx get svc ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
curl -i "http://$ELB/api/sale"
for i in $(seq 10); do curl -s -o /dev/null -D - "http://$ELB/api/sale" | grep -i served-by; done
kubectl exec deploy/rabbitmq -- rabbitmqctl list_queues name messages consumers
kubectl logs deploy/promotion-consumer --tail=20
```

**Pré-voo (a fazer no ensaio):**

- [ ] Credenciais do lab ativas; `aws eks list-clusters --region us-east-1` vazio antes de criar.
- [ ] `bash eks/create-cluster.sh` e `bash eks/apply.sh`; se os nodes ficarem `NotReady` com
  `cni plugin not initialized`: `aws eks create-addon --cluster-name ganjj --region us-east-1 --addon-name vpc-cni`.
- [ ] 23 pods `Running`; imagens amd64 públicas de todos os serviços (nada em `ImagePullBackOff`).
- [ ] `kubectl exec deploy/promotion -c promotion -- php artisan db:seed --force` (cupom `BEMVINDO10`).
- [ ] Criar um produto pelo gateway (o Mongo sobe vazio).
- [ ] Rodar o fluxo de pedido uma vez antes da banca.
- [ ] Ao final, apagar tudo (`eksctl delete cluster`) e conferir `aws eks list-clusters`.

**Plano B** se o ELB não resolver ou o lab cair: mostrar as evidências em `docs/evidencias/` (27/10 e 02/11), os manifests e
os logs gravados, dizendo claramente que a execução é de data anterior. A implantação no EKS é obrigatória; o plano B
perde pontos de R2.

## 9. Estado dos repos (03/11/2026)

| Repo | Issues abertas |
|---|---|
| authorization | #7 ensaio |
| client | #7 ensaio |
| order | #12 E2E pelo gateway; #8 ensaio; #1 readiness |
| product | #6 ensaio; #1 readiness (métricas, porta) |
| promotion | #54 ensaio; #52 cotação do carrinho e consumo no pedido (Cart pronto em 03/11, falta o Order) |
| shopping-cart | nenhuma; #13 (cupom persistido) e #14 (imagem `1.1.1`) fechadas em 03/11, a #7 de ensaio foi fechada sem registro |
| llm-provider, embedding-reranking, vector-db | #6 ensaio; #1 readiness (remover Ingress público, métricas) |
| api-gateway | #14 ensaio |
| support | #22 README e docs RabbitMQ (Eduardo); #14 ensaio |

`promotion#49` e `api-gateway#13` foram fechadas em 03/11 (rodadas no EKS em 02/11). O Cart (`jcliz/shopping-cart:1.1.1`, 03/11) traz os timeouts de 28/10 e o cupom persistido.

## 10. Incoerências encontradas na leitura (para corrigir ou saber explicar)

- A seção "O que falta (29/10/2026)" de [`apresentacao-n2.md`](apresentacao-n2.md) está desatualizada: o cluster de 27/10 já
  foi apagado, o publisher do Order já roda e o JWT já passou no EKS.
- `support/docs/rabbitmq.md` ainda tem título e frases de "integração pendente" com o checklist todo marcado (support#22).
- `support/k8s/kustomization.yaml` lista só `support.yaml` e `mailpit.yaml`, sem `rabbitmq.yaml`: `kubectl apply -k` não
  sobe o broker (o `apply.sh` do gateway sobe).
- O Dockerfile do embedding-reranking não tem passo de download do modelo.
- Sem volume (perdem dado ao recriar o pod): Oracle, PostgreSQL do client, Mongo, Chroma, Redis e o broker. Só o Support
  tem PVC. No EKS tudo é `emptyDir`.
- A issue de readiness "remover Ingress público" (product#1, llm-provider#1 e outras) conflita com `product/k8s/ingress.yaml`
  e com o chart do product, que ainda têm Ingress. Decidir se o gateway é a única entrada.
- Os dashboards do Grafana dos serviços de IA foram validados só em kind; no EKS depende de rodar o `install.sh` do gateway.
- Rate limit do gateway não é global entre réplicas.
- `shopping-cart` versiona `package-lock.json`.
- O README do Cart diz que o Redis tem `appendonly`, mas sem volume isso não protege contra recriação do pod.
