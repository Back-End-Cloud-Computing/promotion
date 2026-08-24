# ADR 0001 — Cupom inválido devolve 200, não 4xx

Status: Aceito
Data: 2026-08-24

## Contexto

`POST /internal/discounts/calculate` e `GET /api/coupons/{code}/validate` recebem um código de
cupom que pode não existir, estar fora de vigência, esgotado ou não atingir o valor mínimo do
carrinho. Isso é diferente de uma requisição malformada: o cliente não errou o formato de nada,
o cupom simplesmente não se aplica àquele carrinho — é um resultado de negócio esperado, não
uma falha do serviço.

Tratar isso como 404/409 obrigaria todo consumidor (`order`, `shopping-cart`) a diferenciar,
por status HTTP, "erro de verdade" de "cupom não aplicável" — os dois tratamentos são bem
diferentes: um é log + retry/alerta, o outro é só mostrar o motivo pro usuário.

## Decisão

`promotion` sempre responde **200** nesses dois endpoints, mesmo quando o cupom não é
aplicado. O corpo inclui `applied: false` (ou `valid: false` em `/coupons/{code}/validate`) e
um campo `reason` legível. Quem integra deve checar esse campo, não o status HTTP, para saber
se o cupom foi aceito.

Só vira erro HTTP de verdade quando a requisição está malformada (422) ou quando o segredo
interno é inválido (403/500) — nunca por causa do estado do cupom em si.

## Consequências

- Consumidores (`order`, `shopping-cart`) não podem usar `try/catch` de erro HTTP pra saber se
  o cupom foi aplicado — precisam ler o campo `applied`/`valid` no corpo de uma resposta 200.
- Simplifica o cliente: não existe "erro de cupom" separado de "erro de rede/serviço fora do
  ar" — só o segundo caso é, de fato, uma falha.
- Documentado em `docs/contrato-api.md`; testado em
  `tests/Feature/DiscountInternalTest.php` (`it devolve 200 com o motivo quando o cupom não
  existe`).
