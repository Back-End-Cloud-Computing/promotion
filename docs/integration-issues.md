# Pendências de integração entre os microsserviços GANJJ

Status: Em acompanhamento
Última atualização: 2026-10-25
Idioma: Português (PT-BR)

## 1. Objetivo

Central de pendências que envolvem `promotion` e pelo menos mais um microsserviço do
ecossistema GANJJ. Não substitui `docs/contrato-api.md` — depois que uma decisão for tomada
pelo grupo, o contrato correspondente deve ser atualizado e a pendência marcada como resolvida.

IDs `INT-*` são compartilhados com `order/docs/INTEGRATION-ISSUES.md`: quando o mesmo problema
cruza os dois repos, mantemos o mesmo número em vez de abrir uma numeração paralela pra a mesma
coisa. IDs `PROMO-*` são específicos deste serviço.

## 2. Resumo

| ID | Tema | Serviços afetados | Situação | Bloqueia agora? |
| --- | --- | --- | --- | --- |
| INT-001 | Contrato de JWT (RS256 vs HS256) | Authorization, Order, Cart, Promotion | Resolvido — Order e Cart migraram pra RS256 | Não — falta só validar ponta a ponta com token real |
| INT-002 | Cotação final do carrinho | Cart, Product, Promotion e Order | Cart chama `calculate` desde 23/10 (`82dbbfb`, `2bf1dfa`). O Product expõe `price` e `stock` desde 24/10 (product#9 fechada, `fb7b26b`); falta rodar Cart → Product → Promotion em execução. O consumo do cupom segue sem chamador (Order) | Sim, para integração final |
| INT-006 | Autenticação interna | Cart, Order e Promotion | Parcial — Cart envia `x-internal-secret` ao Promotion (23/10); Order ainda não chama; valor idêntico não conferido em execução | Não |
| PROMO-001 | Consumo de `OrderConfirmed` via RabbitMQ | Order e Promotion | Contrato aceito em 25/10; implementação pendente | Sim para RabbitMQ |
| INT-009 | Porta errada do Product nos consumidores | Order, Cart, Product | Cart resolvido em 23/10 (`2017c3b`, aponta para `product-api:8000`). Product confirmou a porta em 24/10 (product#8, `330bb05`). Order resolvido em 25/10 (order#11 fechada, `11a3e4f`) | Não — resolvido nos três; falta rodar |
| INT-010 | `shopping-cart` sem `.env.example` no repo | Cart | Resolvido (23/10, `d09fa67`) | Não |
| PROMO-003 | Ingress expunha `/internal` | Promotion | Resolvido (18/10) — allowlist `/api` + `/docs` | Não |
| PROMO-004 | Observabilidade (`/metrics`, Prometheus, Grafana) | Todos | Promotion, API Gateway, Cart, Product, vector-db e embedding-reranking expõem `/metrics`; faltam Order, Authorization, Client, llm-provider e Support | Sim, para R2 da N2 |
| PROMO-005 | Helm (aula 27/10) | Todos | Promotion, Product, vector-db e embedding-reranking têm chart; faltam os demais | Sim, para R2 da N2 |
| PROMO-002 | Validar JWT RS256 ponta a ponta | Authorization, Order, Cart, Promotion | Executado em 21/10 com a chave de lab (todos os itens, Promotion incluído); falta repetir com a chave oficial do Eduardo | Não — mas fecha de vez o risco de INT-001 |
| INT-011 | `POST /api/pedidos` devolve 500 (validação de record no .NET 10) | Order | Confirmado em Compose e Minikube (21/10) | Sim — nenhum pedido é criado |
| INT-012 | `POST /products` leva ~15 s e o gateway corta em 10 s | Product, Gateway | Resolvido nos dois lados: gateway com timeout de 30 s só nessa rota (21/10) e Product respondendo 201 antes do pipeline de LLM e indexação, que roda em segundo plano (product#10, `c134557`, 24/10). Não rodado em execução | Não |
| INT-013 | `product_id` inteiro no promotion, id string no Product | Promotion, Product | Resolvido no Promotion em 21/10 (`fe1328c`): aceita string, inteiro continua aceito. O Cart já envia string | Não |
| INT-014 | llm-provider, embedding-reranking e vector-db públicos pelo Ingress | IA | Confirmado no Minikube (21/10) | Não, falha de segurança |

Teste de ponta a ponta de 21/10: [teste-integracao-2026-10.md](teste-integracao-2026-10.md).

Desde 23/10 os bloqueios entre repos estão registrados como dependência formal do GitHub (`blocked by`, 17 vínculos). Exemplo: promotion#52 depende de shopping-cart#11, order#13 e order#10. O cartão mostra o bloqueio, mas o GitHub não impede fechar a issue.

## 3. Prontidão N1/N2 por serviço

Cruza os dois eixos da rubrica de N2 (10/11, 40% — [arquitetura.md](arquitetura.md), "Arquitetura
em K8s 4,0 · Segurança e observabilidade 3,0") com o estado real de cada repo, pra não descobrir
gap de infraestrutura na véspera da apresentação. A rubrica de N1 ("Contêineres 3,0 · Integração
4,0 · K8s 3,0", também em [arquitetura.md](arquitetura.md)) é a rubrica antiga do repositório
individual — **não está confirmada**, porque N1 virou um lab aplicado em sala pelo professor,
separado do repo. Este quadro mede prontidão real de infraestrutura, não pontuação garantida de
N1.

| Serviço | K8s | Docker/Compose | JWT RS256 | Observação |
| --- | --- | --- | --- | --- |
| authorization | ✅ | ✅ | é o emissor | — |
| client | ✅ | ✅ | ✅ valida | — |
| order | ✅ (16/09) | ⚠️ | ✅ valida | tem `compose.yaml`, não `docker-compose.yml`; K8s só local (Minikube, [ADR 0007](../../order/docs/adr/0007-local-kubernetes-deployment.md)) — Deployment 3 réplicas + StatefulSet SQL Server, sem Ingress por decisão do grupo (23/10, order#9 fechada como not planned: o Order só é alcançável pelo gateway), exposto via `kubectl port-forward` para teste local |
| product | ✅ (28/09) | ✅ | ✅ valida | inclui `llm-provider`, `embedding-reranking` e `vector-db` — nenhum dos três é chamado fora de `product` (coupling rule, `product/README.md`), então não entram como linhas próprias; os 4 pegaram K8s + Ingress + JWT RS256 (HTTPBearer contra a chave pública do `authorization`) juntos em 28/09, `product` publica `:8000` (confirmado, bate com INT-009) |
| promotion | ✅ | ✅ | ✅ | falta só o teste ponta a ponta — ver PROMO-002 |
| shopping-cart | ✅ (08/09) | ✅ | ✅ valida | desde 23/10: CI verde, `/metrics`, imagem `jcliz/shopping-cart:1.1.0` multi-arch, Ingress só com `/api/cart`, `/docs`, `/openapi.json` e `/health`; sem Helm |

Observabilidade (Prometheus/métricas): expõem `/metrics` o `promotion` (18/10), o `api-gateway` (porta 9100) e o
`shopping-cart` (23/10, `37f8945`); em 24/10 entraram `product`, `vector-db` e `embedding-reranking` (commits diretos na `main`, issues #2 de cada um fechadas). Em 25/10 entraram `order`, `authorization`, `client`, `llm-provider` e `support`: os 9 repos que não são nossos expõem `/metrics`. O Plano de Ensino (aula 11, 03/11) cobra Prometheus
e Grafana na N2, e Helm (aula 10, 27/10): ambos entram no R2 (3,0). Têm chart Helm o `promotion` (instalado no
Minikube em 21/10) e, desde 24/10 e 25/10, todos os outros repos exceto o `shopping-cart` (cart#5) e o `api-gateway` (#11), ainda não instalados por nós; dashboards em JSON existem em todos os repos menos o `promotion` (#47) e o dashboard da solução (gateway#12).

## 4. Pendências detalhadas

### INT-001 — Contrato de JWT: Order e Cart migraram pra RS256

**Situação original, confirmada em teste real (2026-08-26):** subi os 9 serviços da org juntos,
logei de verdade em `authorization` (`POST /auth/login`) e usei o `accessToken` real (RS256,
claims `sub`/`email`/`role`/`typ`, `iss=ganjj-authorization`) contra cada consumidor — `order` e
`shopping-cart` devolveram 401 porque ainda validavam com `SymmetricSecurityKey`/`JWT_SECRET`
(HS256), o mesmo problema que este serviço já tinha resolvido no
[ADR 0002](adr/0002-jwt-rs256-chave-estatica.md).

**Atualização 2026-08-30, confirmada lendo os dois repos:**

- `order`, commit `a6f5e35` ("align order authentication with authorization service", 29/08):
  `AuthenticationExtensions.cs` trocou `SymmetricSecurityKey` por `RsaSecurityKey` lida de
  `Authentication:Jwt:PublicKeyPath` (PEM), fixou `ValidAlgorithms = [RsaSha256]`, passou a exigir
  `typ=access` e `sub` como Guid válido, e trocou a policy de admin pra `RequireClaim("role",
  "ADMIN")` — mesmo formato de claim que `authorization` emite.
- `shopping-cart`, commit `9c5b09a` ("validate access tokens against the authorization service's
  RS256 key", 30/08): `utils/jwt.js` trocou `jwt.verify(token, JWT_SECRET)` por verificação RS256
  contra `keys/public.pem`, com `issuer: 'ganjj-authorization'` explícito.

**Impacto:** resolvido no código dos dois lados. Falta só validar ponta a ponta com um token real
emitido pelo `authorization` desta sessão — não repeti o teste multi-serviço de 26/08 depois dessas
mudanças.

**Responsáveis:** Rodrigo Alves (Order) e João Liz (Cart) — mudança já entregue, só falta a
validação ponta a ponta.

### PROMO-002 — Validar JWT RS256 ponta a ponta com token real do authorization

**Situação atual:** o código dos dois lados (`order`, `shopping-cart`) já foi corrigido — ver
INT-001 acima. O teste multi-serviço de 26/08 era anterior a essas correções; o do `promotion`
foi refeito em 21/10 (execução abaixo).

**Checklist do teste:**

- [x] `GET /auth/public-key` no `authorization`, pegar o PEM.
- [x] Configurar `JWT_PUBLIC_KEY` no `.env` do `promotion` com esse PEM.
- [x] Subir `authorization` + `promotion`.
- [x] `POST /auth/login` no `authorization`, pegar um `accessToken` real (RS256, claims
      `sub`/`email`/`role`/`typ`, `iss=ganjj-authorization`).
- [x] Chamar uma rota admin do `promotion` (campaigns/promotions/coupons) com
      `Authorization: Bearer <token>` e confirmar 200 em vez de 401.
- [x] Confirmar o mapeamento de claims: `sub→id`, `role==='ADMIN'→isAdmin` (comparação exata
      maiúscula); `role=CLIENTE` sem acesso admin.
- [x] Confirmar `iss==='ganjj-authorization'` e `typ==='access'` — um refresh token não deve
      passar.
- [x] Exercitar os casos de erro: `JWT_PUBLIC_KEY` vazia → 500; token expirado → 401; assinatura
      inválida → 401; formato inválido → 401.

**Execução de 21/10/2026 (chave de lab):** rodado contra o `authorization` local, subido do clone com um par de chaves gerado por `scripts/generate-keys.sh`. Falta repetir só com a chave e o usuário oficiais do Eduardo, o que troca `JWT_PUBLIC_KEY` e não muda código.

Resultados em `GET /api/campaigns`: admin 200; sem token 401; role CLIENTE 403; refresh token como bearer 401; token expirado 401; `iss` errado 401; assinatura adulterada ou de outra chave 401; formato inválido 401; `JWT_PUBLIC_KEY` vazia 500.

**Nota:** nenhuma env var nova além de `JWT_PUBLIC_KEY`, nenhuma dependência nova (`firebase/php-jwt`
já instalado) — não é um bloqueio de escopo, só falta repetir com a chave oficial.

**Responsáveis:** Lucas Stopinski (Promotion) executa o teste; Rodrigo Alves (Order) e João Liz
(Cart) só precisam ser avisados do resultado, já que o código do lado deles não muda.

### INT-002 — Cotação final do carrinho

**Situação atual, do lado de `promotion`:** `POST /internal/discounts/calculate` e
`POST /internal/coupons/{code}/consume` existem, estão testados (`DiscountInternalTest.php`,
`CouponTest.php`) e seguem o contrato documentado em `docs/contrato-api.md`.

**Atualização 2026-10-24, lendo o `main` do `shopping-cart`:** o Cart passou a chamar
`POST /internal/discounts/calculate` em `GET /api/cart` (commit `82dbbfb`, 23/10; cart#10 fechada).
Manda `x-internal-secret`, `items[{product_id (string), unit_price, quantity}]` e `coupon` (`null` ou o
`?coupon=` da consulta). Lê `subtotal`, `promotions_discount`, `coupon_discount`, `total` e, por item,
`discount_percentage` e `discounted_price`, todos presentes no contrato. Se o Promotion falhar, o
carrinho volta sem desconto com `discounts_applied: false`, em vez de derrubar a leitura. O Service
`promotion:8000` configurado no manifest do Cart bate com o nosso. O Cart não chama `consume`.

**Ainda não rodei Cart → Promotion em execução:** o contrato foi conferido lendo o código. O preço
do item vem do Product, que passou a expor `price` e `stock` em 24/10 (product#9 fechada, `fb7b26b`,
lido no código, não rodado). Antes disso o Cart devolvia 502 "Product has no price" ao adicionar item.
O cart#11 segue aberto (o lado do Cart foi feito em `2bf1dfa`); com o Product atualizado, falta
repetir o fluxo no Compose ou no Minikube. O estoque (`/internal/products/{id}/stock/decrement` e
`restore`, com `Idempotency-Key`) é para o Order; não afeta o Promotion.

**Consumo do cupom:** `POST /internal/coupons/{code}/consume` continua sem chamador. `order` ainda
não faz essa chamada (promotion#50 e #52).

**Recomendação, alinhada com `order/docs/adr/0002-cart-authoritative-checkout-quote.md`:** Cart
deve compor Product + Promotion numa cotação de checkout, e Order só consumir essa cotação
pronta. `order` já decidiu formalmente (ADR 0002) que nunca vai chamar `promotion` diretamente —
a parte do Cart dessa composição está entregue; falta o Product fornecer o preço.

**Responsáveis:** João Liz (Cart), João Correa (Product), Lucas Stopinski (Promotion) e Rodrigo
Alves (Order) — mesmos nomes já citados em `order/docs/INTEGRATION-ISSUES.md`.

### INT-006 — Autenticação das chamadas internas

**Situação atual:** `promotion` protege `/internal/discounts/calculate` e
`/internal/coupons/{code}/consume` com o header `x-internal-secret`
(`VerifyInternalSecret.php`), lido de `INTERNAL_SECRET` (sem valor padrão — o serviço recusa
subir configurado de forma insegura).

**Decisão necessária:** confirmar que `order` e `shopping-cart` apontam pro mesmo valor de
segredo configurado aqui. Sem isso, não há garantia de que as chamadas internas entre os três
serviços estão de fato autenticadas — cada lado pode estar validando contra um segredo
diferente sem que ninguém perceba até testar de ponta a ponta.

**Atualização 2026-10-24:** o Cart já envia `x-internal-secret` com o `INTERNAL_SECRET` do próprio
ambiente (`http/promotionClient.js`) e o `k8s/create-secret.sh` dele avisa que o valor precisa ser o
mesmo do Promotion. Falta conferir em execução que os dois valores batem e o Order, que ainda não
chama o Promotion.

**Responsável:** todo o grupo.

### PROMO-001 — Consumo de `OrderConfirmed` via RabbitMQ

**Decisão atualizada em 25/10/2026:** Order e Promotion aceitaram o contrato
documentado em `docs/contrato-api.md`: exchange direct durável `ganjj.orders`,
routing key `order.confirmed` e fila durável `promotion.order-confirmed`.

**Situação atual:** contrato concluído; código ainda não implementado. Continua
sem `php-amqplib` no `composer.json` e sem consumidor. O publisher do Order
depende de o Cart fornecer `coupon_code` autoritativo.

**Ainda não construído:** publisher/outbox no Order e consumidor no Promotion.
O consumidor deve gravar `order_id` único na mesma transação MySQL que incrementa
`usage_count`; reentrega recebe ack sem segundo incremento.

**Responsáveis:** Rodrigo Alves (Order) e Lucas Stopinski (Promotion).

### INT-009 — Order e Cart apontam pra porta errada do Product

**Situação atual, confirmada lendo os três repos:** `order/.env.example` e
`shopping-cart/.env.example` têm o mesmo default —
`PRODUCT_SERVICE_URL=http://host.docker.internal:3002` — mas `product/docker-compose.yml`
publica o serviço em `8000:8000`, não em `3002`. Nenhum dos dois arquivos foi atualizado depois
que essa porta mudou (ou nunca foi `3002` de verdade e o default nasceu errado).

**Impacto:** sem sobrescrever `PRODUCT_SERVICE_URL` manualmente, toda chamada de `order` ou
`shopping-cart` pro `product` cai em connection refused, não em erro de negócio — não dá pra saber
que o resto da integração funciona sem primeiro descobrir esse detalhe. Confirmado no teste com os
9 serviços juntos desta sessão: só depois de sobrescrever pra `:8000` que
`shopping-cart → product` e `order → product` responderam de verdade.

**Recomendação:** `product` (João Correa) confirma qual é a porta canônica publicada — hoje é
`8000` — e `order` (Rodrigo) e `shopping-cart` (João Liz) atualizam o default nos respectivos
`.env.example` pra bater.

**Responsáveis:** João Correa (Product), Rodrigo Alves (Order) e João Liz (Cart).

**Atualização 2026-08-27:** `product` removeu o próprio `docker-compose.yml` do repo (commit
`e29464d`, "split product-service out of the vector/embedding/LLM monolith") — não dá mais pra
confirmar a porta publicada lendo esse arquivo. O `README.md` do `product` ainda mostra a porta
`8000` no diagrama de arquitetura, então o valor não mudou, só a forma de verificar. Vale
reconfirmar com o João Correa se `product` vai continuar publicando `8000` sozinho ou se isso
passa a depender de um compose no nível da organização (o README já fala em "orquestrado a
partir da raiz do repo, junto com os outros serviços" — esse arquivo raiz ainda não existe).

**Atualização 2026-10-25:** o Order também corrigiu (order#11, `11a3e4f`): `Services__Product__BaseUrl=http://product-api:8000` em `k8s/order.yaml` e default `:8000` no `compose.yaml`. Conferido lendo os arquivos, não rodado. Resolvido nos três consumidores.

**Atualização 2026-10-24:** o Cart corrigiu em 23/10 (`2017c3b`): `k8s/shopping-cart.yaml` aponta para
`http://product-api:8000` e o `.env.example` voltou com `http://localhost:8000` (cart#9 fechada). O
Order continua com `Services__Product__BaseUrl=http://product:8000` em `k8s/order.yaml` e `:3002` no
`.env.example` (order#11 aberta); a confirmação de `product-api:8000` pelo Product é a product#8.

### INT-010 — `shopping-cart` sem `.env.example` versionado

**Situação atual, confirmada lendo o repo (2026-08-27):** `shopping-cart/.gitignore` tem o
comentário `# O .env.example é versionado de propósito — serve de template.` e a regra
`!.env.example` pra garantir isso — mas o arquivo não existe no working tree hoje. O próprio
`README.md` do `shopping-cart` instrui `cp .env.example .env` na seção "Rodando", então o passo
de setup documentado quebra pra quem clona o repo do zero.

**Impacto:** não afeta quem já tem um `.env` local funcionando (nosso caso, testado no geralzão
desta sessão), mas qualquer pessoa nova no time — ou o professor rodando o repo pra avaliar —
esbarra nisso no primeiro passo. A tabela de variáveis no README (`PORT`, `REDIS_URL`,
`REDIS_PREFIX`, `CART_TTL_SECONDS`, `JWT_SECRET`, `PRODUCT_SERVICE_URL`, `INTERNAL_SECRET`) ainda
serve de referência enquanto o arquivo não volta.

**Recomendação:** João Liz recria o `.env.example` a partir da tabela do próprio README (ele já
documenta os 7 valores necessários) e comita.

**Resolvido em 2026-10-23:** `d09fa67` restaurou o `.env.example`, só com valores de exemplo
(`PORT`, `REDIS_*`, `CART_TTL_SECONDS`, `PRODUCT_SERVICE_URL`, `PROMOTION_SERVICE_URL`,
`INTERNAL_SECRET`), e o passo `cp .env.example .env` do README volta a funcionar (cart#8 fechada).

**Responsável:** João Liz (Cart).

### PROMO-003 — Ingress do promotion expõe /internal fora do cluster

**Resolvido em 2026-10-18.** `k8s/ingress.yaml` virou lista de permissão (`/api` e `/docs`) com host
`promotion.ganjj.local`; `/internal/*`, `/metrics` e `/health*` devolvem 404. Verificado no minikube. O texto
abaixo é o diagnóstico original.

**Situação atual, confirmada lendo o repo:** `k8s/ingress.yaml` só tem uma regra `path: /` com
`pathType: Prefix` apontando pro serviço inteiro — não há exclusão de `/internal/*`. O checklist
de `docs/fases/fase-5-seguranca.md` ("`/internal` não alcançável de fora do cluster") segue em
aberto por causa disso; a exigência já estava documentada lá ("rotas `/internal` fora do Ingress
público").

**Nota lateral:** `APP_DEBUG=false` já está correto em `k8s/configmap.yaml` — só o ingress está
pendente, não é um problema de configuração geral.

**Impacto:** hoje `/internal/discounts/calculate` e `/internal/coupons/{code}/consume` (os
mesmos endpoints de INT-002/INT-006) são alcançáveis publicamente se alguém souber a URL e o
`x-internal-secret` — a exposição de rede e o segredo são defesas independentes, então esse gap
reduz a segurança mesmo que INT-006 seja resolvido.

**Recomendação:** ajustar `ingress.yaml` pra excluir `/internal` (ex. anotação de
`configuration-snippet` do NGINX negando o path, ou dividir em duas regras). Decisão de
implementação fica pra quando isso for corrigido de fato — não faz parte deste levantamento.

**Responsável:** Lucas Stopinski (Promotion) — único responsável, sem dependência de outro
serviço.

## 5. Checklist

- [x] Avisar Rodrigo e João Liz sobre o contrato de JWT quebrado em `order` e `shopping-cart`
      (INT-001) — os dois já migraram pra RS256, falta só validar ponta a ponta.
- [x] Rodar o teste ponta a ponta do JWT RS256 com token real do authorization (PROMO-002) — 21/10,
      com a chave de lab.
- [ ] Repetir o PROMO-002 com a chave pública e o usuário oficiais do Eduardo (authorization#8,
      promotion#49).
- [x] Corrigir o Ingress do promotion pra excluir /internal do acesso externo (PROMO-003) — 18/10.
- [ ] Confirmar quem chama `/internal/discounts/calculate` e `/internal/coupons/{code}/consume`
      (INT-002) — `calculate`: o Cart, desde 23/10 (ainda não rodado em execução); `consume`: ninguém.
- [ ] Confirmar que `INTERNAL_SECRET` é idêntico em `order`, `shopping-cart` e `promotion`
      (INT-006).
- [x] Fechar contrato da mensageria `OrderConfirmed` com Order (PROMO-001).
- [ ] Implementar idempotência do consumidor de `OrderConfirmed` (PROMO-001).
- [x] Avisar João Correa, Rodrigo e João Liz sobre a porta errada do Product em
      `PRODUCT_SERVICE_URL` (INT-009) — issues abertas em 20/10 (product#8, order#11, shopping-cart#9).
- [ ] Confirmar com João Correa se `product` mantém a porta `8000` sozinho ou passa a depender
      de um compose raiz da organização, já que o `docker-compose.yml` do repo sumiu (INT-009).
- [x] Avisar João Liz que o `.env.example` do `shopping-cart` sumiu do repo (INT-010) — resolvido em
      23/10 (`d09fa67`).

## 6. Revisão cruzada para a N2 (2026-10-18)

Levantamento feito lendo os 9 repos contra a rubrica do Plano de Ensino (N2 em 10/11/2026: R1 K8s 4,0, R2
segurança/distribuição/observabilidade 3,0, R3 apresentação 3,0). Cada linha vira uma issue no repo do dono; aqui
fica o mapa. As linhas de `order`, `shopping-cart` e API Gateway foram atualizadas em 24/10; o mapa
completo e atual de issues está em [issues-entrega-final.md](issues-entrega-final.md).

| Repo | Dono | Gaps |
| --- | --- | --- |
| `order` | Rodrigo | sem Ingress por decisão do grupo; contrato `OrderConfirmed` aceito, publisher/outbox pendente (PROMO-001); demais itens acompanhados nas issues do Order |
| `shopping-cart` | João Liz | entregues no `main` em 23/10: CI, `.env.example`, `/metrics`, porta do Product, imagem multi-arch e chamada ao Promotion (INT-002). Faltam Helm (cart#5), dashboard (cart#6, parcial) e o preço vindo do Product (cart#11) |
| `authorization` | Eduardo | entregue em 25/10 (PR #11): CI, `/metrics`, Helm, Grafana, imagem `1.2.1` multi-arch, senhas fora do manifest. Falta a chave e o usuário oficiais para o PROMO-002 (authorization#8) |
| `client` | Eduardo | entregue em 25/10 (PR #9): CI, `/metrics`, Helm, Grafana, imagem `1.2.0` multi-arch |
| `product` | João Correa | entregue no `main` em 24/10: `/metrics`, porta 8000 confirmada, preço e estoque, `POST /products` assíncrono, Helm, Grafana, imagem `1.1.0` multi-arch, Ingress sem `/internal` nem `/metrics`. Falta só o ensaio (#6) e fechar a mãe (#1) |
| `vector-db` | João Correa | entregue em 24/10: Ingress público removido, `/metrics`, Helm, Grafana, compose autônomo, imagem `1.1.0` multi-arch. Falta o ensaio (#6) |
| `embedding-reranking` | João Correa | em 24/10: Ingress removido, `/metrics`, Helm, Grafana, compose autônomo. Imagem `1.1.0` multi-arch publicada em 25/10. Falta o ensaio (#6) |
| `llm-provider` | João Correa | entregue em 25/10: Ingress removido, `/metrics`, Helm, Grafana, compose autônomo, imagem `1.1.0` multi-arch. Falta o ensaio (#6) |
| API Gateway | Lucas | repo [`api-gateway`](https://github.com/Back-End-Cloud-Computing/api-gateway) criado em 19/10/2026; implementado, com imagem `bascratki/api-gateway:1.0.0` (amd64 e arm64), testado no Minikube e com token real em 21/10. Faltam Helm, Prometheus e Grafana no cluster e o dashboard da solução |
| Serviço de suporte (e-mail/SMS/WhatsApp) | Eduardo | repo [`support`](https://github.com/Back-End-Cloud-Computing/support) implementado em Java (PR #19, 25/10) com `/metrics`, Helm, Grafana e imagem `1.0.2` multi-arch; o `order` já chama o endpoint interno de notificação (order#2, lado do Order feito em `0b138e6`); consumidor RabbitMQ planejado (support#11) |

Fora do código do `promotion`: Helm e dashboard existem em `product`, `vector-db` e `embedding-reranking`; Prometheus e Grafana no cluster ainda não rodam em nenhum (aula de 03/11). Padrão do grupo a combinar.
