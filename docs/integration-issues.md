# Pendências de integração entre os microsserviços GANJJ

Status: Em acompanhamento
Última atualização: 2026-08-27
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
| INT-001 | Contrato de JWT (RS256 vs HS256) | Authorization, Order, Cart, Promotion | Confirmado quebrado em Order e Cart | Sim — nenhuma conta real autentica nesses dois hoje |
| INT-002 | Cotação final do carrinho | Cart, Product, Promotion e Order | Pendente | Não para desenvolvimento; sim para integração final |
| INT-006 | Autenticação interna | Cart, Order e Promotion | Pendente | Não |
| PROMO-001 | Consumo de `pedido.confirmado` via RabbitMQ | Order e Promotion | Pendente | Não |
| INT-009 | Porta errada do Product nos consumidores | Order, Cart, Product | Confirmado (código) | Sim — Order↔Product e Cart↔Product não conectam sem sobrescrever a env var |
| INT-010 | `shopping-cart` sem `.env.example` no repo | Cart | Confirmado (repo) | Não bloqueia outros serviços, mas quebra o setup de quem clona `shopping-cart` do zero |

## 3. Pendências detalhadas

### INT-001 — Contrato de JWT: Order e Cart ainda validam contra o contrato antigo

**Situação atual, confirmada em teste real (2026-08-26):** subi os 9 serviços da org juntos,
logei de verdade em `authorization` (`POST /auth/login`) e usei o `accessToken` real (RS256,
claims `sub`/`email`/`role`/`typ`, `iss=ganjj-authorization`) contra cada consumidor:

| Serviço | Resultado | Motivo |
| --- | --- | --- |
| `client` | Aceito (404 de negócio, não de auth) | Já migrado pra RS256 com chave pública — mesmo contrato deste ADR 0002 |
| `promotion` | 401 "Token inválido" | Esperado — `JWT_PUBLIC_KEY` do `.env` local está desatualizada, não é o mesmo par de chaves que o `authorization` gerou nesta sessão (pendência já registrada em `docs/README.md`) |
| `order` | 401, corpo vazio | `AuthenticationExtensions.cs` usa `SymmetricSecurityKey` (HS256) com `Authentication:Jwt:SigningKey` e espera `isAdmin` booleano — nunca foi migrado pro contrato real |
| `shopping-cart` | 401 `"Token inválido"` | `utils/jwt.js` usa `jwt.verify(token, JWT_SECRET)` — mesmo problema, HS256 simétrico |

**Impacto:** nenhuma conta real (emitida pelo `authorization`) consegue autenticar em `order` ou
`shopping-cart` hoje. Não é falta de integração — é incompatibilidade de algoritmo, a mesma
categoria de problema que este serviço resolveu no
[ADR 0002](adr/0002-jwt-rs256-chave-estatica.md).

**Recomendação:** aplicar o mesmo ajuste em `order` e `shopping-cart` — RS256 com a chave pública
de `authorization` lida de env, mapeando `sub`/`role` pros nomes internos que cada serviço já usa
(`isAdmin` no `order`, o que for equivalente no `shopping-cart`). O `ADR 0002` deste repo serve de
referência de implementação pros dois.

**Responsáveis:** Rodrigo Alves (Order) e João Liz (Cart).

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

## 4. Checklist

- [ ] Avisar Rodrigo e João Liz sobre o contrato de JWT quebrado em `order` e `shopping-cart`
      (INT-001).
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
