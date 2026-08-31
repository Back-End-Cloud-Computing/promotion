# Pendências de integração entre os microsserviços GANJJ

Status: Em acompanhamento
Última atualização: 2026-08-30
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

## 3. Pendências detalhadas

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

## 4. Checklist

- [x] Avisar Rodrigo e João Liz sobre o contrato de JWT quebrado em `order` e `shopping-cart`
      (INT-001) — os dois já migraram pra RS256, falta só validar ponta a ponta.
- [ ] Confirmar quem chama `/internal/discounts/calculate` e `/internal/coupons/{code}/consume`
      (INT-002).
- [ ] Confirmar que `INTERNAL_SECRET` é idêntico em `order`, `shopping-cart` e `promotion`
      (INT-006).
- [ ] Fechar contrato da mensageria `pedido.confirmado` com Order (PROMO-001).
- [ ] Implementar idempotência do consumidor de `pedido.confirmado` (PROMO-001).
