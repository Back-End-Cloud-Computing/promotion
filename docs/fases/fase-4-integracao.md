# Fase 4 — Integração e mensageria

**Quando:** mensageria após 15/09; integração mirando a atividade formativa de 22/09.
**Vale:** preparo técnico pra N2 — pontuação direta na N1 não confirmada (N1 é um lab do professor, não o repo), ver [pendências](../README.md#pendências-fora-do-código).
**Entrega:** contrato REST consumido de verdade pelos colegas, spec OpenAPI publicada e consumidor RabbitMQ.

## Por que esta fase existe

É a maior fatia da nota individual. E o "e/ou" da rubrica é importante: **o REST interno já satisfaz o
critério sozinho**. O RabbitMQ é reforço, não bloqueador — o que protege a nota caso a integração com o
serviço de Pedido atrase.

Esta é também a primeira fase que **não roda sozinha**. Até aqui tudo foi entregável sem depender de ninguém;
mensageria exige combinar nome de fila e formato de payload com outra pessoa.

## Tarefas

### 1. Integração REST com Carrinho e Pedido

OpenAPI já foi antecipada pra Fase 2 ([addendum](fase-2-docker.md#addendum--openapi-e-publicação-de-imagem)) —
`/docs/api` e `/docs/api.json` já estão no ar via Scramble. Os colegas já têm a spec pra integrar; nada a fazer
aqui além de manter os controllers/FormRequests atualizados, que é o que a spec lê.

`POST /internal/discounts/calculate` já existe desde a Fase 1. O trabalho aqui é fazer o consumo acontecer de
verdade:

| Serviço | Chama | Quando |
|---|---|---|
| Carrinho (João Liz, Node) | `/internal/discounts/calculate` | A cada mudança do carrinho — idempotente, não consome uso |
| Pedido (Rodrigo, C#) | `/internal/discounts/calculate` + `/internal/coupons/{code}/consume` | No fechamento |

O que precisa ser combinado:

- O valor de `INTERNAL_SECRET` (mesmo segredo dos dois lados).
- A URL do serviço no cluster (`http://promotion:8000`, via DNS do Kubernetes).
- Que o cálculo **não** consome uso e o fechamento **sim** — se o Carrinho chamar `consumir`, um cupom de 500
  usos se esgota sem nenhuma venda.

### Decisão de 21/10/2026: REST interno agora, fila pendente do Order

O consumo do cupom no fechamento do pedido fica no **REST interno** (`POST /internal/coupons/{code}/consume`,
já em produção no serviço e coberto por teste). O consumidor RabbitMQ **não será implementado antes** de o
Order aceitar um contrato de evento.

Por quê:

- **A rubrica aceita REST "e/ou" fila.** O REST interno já satisfaz o critério sozinho (ver acima); a fila é
  reforço, não requisito.
- **O contrato não é nosso.** Exchange, fila, routing key e payload dependem do Order (Rodrigo), que ainda
  não confirmou nada. Inventar o contrato do lado do consumidor obriga o Order a se adaptar ou gera
  retrabalho nos dois lados.
- **Custo e risco.** Consumidor novo traz dependência (`php-amqplib`), Deployment separado, broker no cluster
  e teste de reentrega. É a parte da fase que mais pode atrasar a entrega de 10/11.
- **O REST já está integrado ao resto.** A cotação e o consumo usam o mesmo `INTERNAL_SECRET` e o mesmo
  caminho do Carrinho e do Pedido (INT-002 e INT-006).

Limite conhecido do REST: o `consume` **não recebe `pedido_id`**, então um retry do Pedido depois de um
timeout consome o cupom duas vezes. Mitigação até a fila existir: o Order não repete a chamada de consumo
em timeout. A idempotência por `pedido_id` entra junto do consumidor, quando o contrato for fechado.

Sai do pendente quando o Rodrigo aceitar o contrato abaixo; a issue [#50](https://github.com/Back-End-Cloud-Computing/promotion/issues/50) segue aberta e
[#51](https://github.com/Back-End-Cloud-Computing/promotion/issues/51) (consumidor) depende dela.

**Proposta de contrato para o Rodrigo aceitar ou corrigir:** exchange `pedidos` (direct), routing key
`pedido.confirmado`, fila durável `promocao.pedido-confirmado`, payload JSON
`{"pedido_id": "<guid>", "cupom_codigo": "INVERNO20|null", "itens": [{"product_id": "<string>", "quantidade": 1}]}`,
idempotência por `pedido_id`.

### 2. Consumidor RabbitMQ (pendente do contrato com o Order)

**O evento:** o serviço de Pedido publica `pedido.confirmado` com `{pedido_id, cupom_codigo, itens}`. Este
serviço consome e incrementa `usos` do cupom.

Isso substitui a chamada HTTP síncrona de `consumir` no caminho crítico do checkout — que é exatamente o
problema que mensageria resolve: se o serviço de Promoção estiver fora do ar, o pedido ainda é fechado e o
consumo do cupom acontece quando o serviço voltar.

```bash
composer require php-amqplib/php-amqplib
php artisan make:command ConsumirPedidosConfirmados
```

O consumidor roda como processo separado (`php artisan promocao:consumir-pedidos`), em outro Deployment no
Kubernetes — **nunca dentro do request HTTP**.

**O que combinar com o Rodrigo:** nome da exchange, nome da fila, routing key e o formato exato do payload.
É o único contrato compartilhado além do OpenAPI.

**Idempotência:** a mesma mensagem pode ser entregue duas vezes (é garantia at-least-once, não exactly-once).
Consumir duas vezes o mesmo `pedido_id` não pode incrementar `usos` duas vezes. Guardar o `pedido_id`
processado, ou tornar o incremento condicional a ele.

> Este é o bug clássico de mensageria e não aparece em teste feliz — aparece quando a rede oscila e o broker
> reentrega.

### 3. Teste ponta a ponta

Antes de 22/09: carrinho monta pedido, chama o cálculo, fecha, evento publicado, uso incrementado. Com os
serviços reais dos colegas, no cluster.

## Verificação

| O quê | Como | Esperado |
|---|---|---|
| Spec acessível | `curl .../docs/api.json` | OpenAPI válido com todas as rotas |
| Cálculo pelo Carrinho | Requisição real do serviço do João Liz | Detalhamento correto |
| Consumo pelo Pedido | Fechamento real do serviço do Rodrigo | `usos` incrementa em 1 |
| Cupom no limite | Fechar pedido com cupom esgotado | 409, sem incrementar |
| Evento consumido | Publicar `pedido.confirmado` manualmente no broker | `usos` incrementa |
| Idempotência | Publicar **a mesma** mensagem duas vezes | `usos` incrementa **uma** vez |
| Resiliência | Derrubar o consumidor, publicar, subir de novo | Mensagem processada ao voltar |

Os dois últimos são os que provam que a mensageria foi entendida, não só ligada.

## Concluída quando

- [x] OpenAPI publicada e acessível (antecipada pra Fase 2)
- [ ] Carrinho consome o cálculo com sucesso
- [ ] Pedido consome e o uso incrementa
- [ ] Consumidor RabbitMQ processa `pedido.confirmado`
- [ ] Reentrega da mesma mensagem não duplica o consumo
- [ ] Consumidor roda como Deployment separado no cluster
- [ ] Fluxo ponta a ponta demonstrado antes de 06/10

## Riscos

**Dependência de colega.** Único ponto do projeto onde a entrega depende de terceiro. Mitigação: o REST
interno já satisfaz o "e/ou" da rubrica sozinho. Se a mensageria não sair, R2 ainda pontua — mas combinar o
contrato do evento **logo após 15/09** dá margem para o atraso.

**Segredo compartilhado em trânsito.** `INTERNAL_SECRET` combinado por WhatsApp acaba em screenshot. Passar
por canal privado e usar valores diferentes em desenvolvimento e apresentação.

## Próxima

[Fase 5 — Segurança e observabilidade](fase-5-seguranca.md), mirando a N2 em 10/11.
