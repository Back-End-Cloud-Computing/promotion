# Pendências de integração entre os microsserviços GANJJ

Status: Em acompanhamento
Última atualização: 2026-09-16
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
| INT-002 | Cotação final do carrinho | Cart, Product, Promotion e Order | Pendente | Não para desenvolvimento; sim para integração final |
| INT-006 | Autenticação interna | Cart, Order e Promotion | Pendente | Não |
| PROMO-001 | Consumo de `pedido.confirmado` via RabbitMQ | Order e Promotion | Pendente | Não |
| INT-009 | Porta errada do Product nos consumidores | Order, Cart, Product | Confirmado (código) | Sim — Order↔Product e Cart↔Product não conectam sem sobrescrever a env var |
| INT-010 | `shopping-cart` sem `.env.example` no repo | Cart | Confirmado (repo) | Não bloqueia outros serviços, mas quebra o setup de quem clona `shopping-cart` do zero |
| PROMO-002 | Validar JWT RS256 ponta a ponta | Authorization, Order, Cart, Promotion | Pendente (teste) | Não — mas fecha de vez o risco de INT-001 |

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
| order | ✅ (16/09) | ⚠️ | ✅ valida | tem `compose.yaml`, não `docker-compose.yml`; K8s só local (Minikube, [ADR 0007](../../order/docs/adr/0007-local-kubernetes-deployment.md)) — Deployment 3 réplicas + StatefulSet SQL Server, sem Ingress, exposto via `kubectl port-forward` |
| product | ❌ | ❌ | ❌ sem auth | inclui `llm-provider`, `embedding-reranking` e `vector-db` — nenhum dos três é chamado fora de `product` (coupling rule, `product/README.md`: "this service knows embedding-reranking... and llm-provider... plus a single direct call to vector-db..."), então não entram como linhas próprias; se `product` não tem K8s/JWT, o guarda-chuva inteiro não tem |
| promotion | ✅ | ✅ | ✅ | falta só o teste ponta a ponta — ver PROMO-002 |
| shopping-cart | ✅ (08/09) | ✅ | ✅ valida | — |

Observabilidade (Prometheus/métricas): nenhum repo implementou. Não é atraso — a fase 5 do
cronograma só começa após 20/10.

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
INT-001 acima. O teste multi-serviço de 26/08 é anterior a essas correções e nunca foi
re-executado depois delas.

**Checklist do teste:**

- [ ] `GET /auth/public-key` no `authorization`, pegar o PEM.
- [ ] Configurar `JWT_PUBLIC_KEY` no `.env` do `promotion` com esse PEM.
- [ ] Subir `authorization` + `promotion`.
- [ ] `POST /auth/login` no `authorization`, pegar um `accessToken` real (RS256, claims
      `sub`/`email`/`role`/`typ`, `iss=ganjj-authorization`).
- [ ] Chamar uma rota admin do `promotion` (campaigns/promotions/coupons) com
      `Authorization: Bearer <token>` e confirmar 200 em vez de 401.
- [ ] Confirmar o mapeamento de claims: `sub→id`, `role==='ADMIN'→isAdmin` (comparação exata
      maiúscula); `role=CLIENTE` sem acesso admin.
- [ ] Confirmar `iss==='ganjj-authorization'` e `typ==='access'` — um refresh token não deve
      passar.
- [ ] Exercitar os casos de erro: `JWT_PUBLIC_KEY` vazia → 500; token expirado → 401; assinatura
      inválida → 401; formato inválido → 401.

**Nota:** nenhuma env var nova além de `JWT_PUBLIC_KEY`, nenhuma dependência nova (`firebase/php-jwt`
já instalado) — não é um bloqueio de escopo, só falta executar.

**Responsáveis:** Lucas Stopinski (Promotion) executa o teste; Rodrigo Alves (Order) e João Liz
(Cart) só precisam ser avisados do resultado, já que o código do lado deles não muda.

### INT-002 — Cotação final do carrinho

**Situação atual, do lado de `promotion`:** `POST /internal/discounts/calculate` e
`POST /internal/coupons/{code}/consume` existem, estão testados (`DiscountInternalTest.php`,
`CouponTest.php`) e seguem o contrato documentado em `docs/contrato-api.md`. Nenhum dos dois é
chamado por nenhum outro serviço hoje — nem `shopping-cart` nem `order` fazem essa chamada.

**Recomendação, alinhada com `order/docs/adr/0002-cart-authoritative-checkout-quote.md`:** Cart
deve compor Product + Promotion numa cotação de checkout, e Order só consumir essa cotação
pronta. `order` já decidiu formalmente (ADR 0002) que nunca vai chamar `promotion` diretamente —
então a integração real só acontece quando `shopping-cart` implementar essa composição.

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

**Responsável:** todo o grupo.

### PROMO-001 — Consumo de `pedido.confirmado` via RabbitMQ

**Situação atual:** planejado em `docs/fases/fase-4-integracao.md`, não implementado. Sem
`php-amqplib` no `composer.json`, sem consumidor.

**Decisão necessária com Order (Rodrigo):**

- nome da exchange, fila e routing key;
- formato exato do payload (`{pedido_id, cupom_codigo, itens}` é a hipótese registrada em
  `fase-4-integracao.md`, ainda não confirmada com Order);
- confirmar que `INTERNAL_SECRET` é o mesmo valor dos dois lados (ver INT-006).

**Ainda não construído:** idempotência do consumidor — guardar `pedido_id` já processado, pra
uma mensagem reentregue não consumir cupom duas vezes.

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

**Responsável:** João Liz (Cart).

### PROMO-003 — Ingress do promotion expõe /internal fora do cluster

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
- [ ] Rodar o teste ponta a ponta do JWT RS256 com token real do authorization (PROMO-002).
- [ ] Corrigir o Ingress do promotion pra excluir /internal do acesso externo (PROMO-003).
- [ ] Confirmar quem chama `/internal/discounts/calculate` e `/internal/coupons/{code}/consume`
      (INT-002).
- [ ] Confirmar que `INTERNAL_SECRET` é idêntico em `order`, `shopping-cart` e `promotion`
      (INT-006).
- [ ] Fechar contrato da mensageria `pedido.confirmado` com Order (PROMO-001).
- [ ] Implementar idempotência do consumidor de `pedido.confirmado` (PROMO-001).
- [ ] Avisar João Correa, Rodrigo e João Liz sobre a porta errada do Product em
      `PRODUCT_SERVICE_URL` (INT-009).
- [ ] Confirmar com João Correa se `product` mantém a porta `8000` sozinho ou passa a depender
      de um compose raiz da organização, já que o `docker-compose.yml` do repo sumiu (INT-009).
- [ ] Avisar João Liz que o `.env.example` do `shopping-cart` sumiu do repo (INT-010).
