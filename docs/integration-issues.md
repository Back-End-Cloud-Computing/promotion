# Pendências de integração entre os microsserviços GANJJ

Status: Em acompanhamento
Última atualização: 2026-08-24
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
| INT-002 | Cotação final do carrinho | Cart, Product, Promotion e Order | Pendente | Não para desenvolvimento; sim para integração final |
| INT-006 | Autenticação interna | Cart, Order e Promotion | Pendente | Não |
| PROMO-001 | Consumo de `pedido.confirmado` via RabbitMQ | Order e Promotion | Pendente | Não |

## 3. Pendências detalhadas

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

- [ ] Confirmar quem chama `/internal/discounts/calculate` e `/internal/coupons/{code}/consume`
      (INT-002).
- [ ] Confirmar que `INTERNAL_SECRET` é idêntico em `order`, `shopping-cart` e `promotion`
      (INT-006).
- [ ] Fechar contrato da mensageria `pedido.confirmado` com Order (PROMO-001).
- [ ] Implementar idempotência do consumidor de `pedido.confirmado` (PROMO-001).
