# Fase 4 — Integração e mensageria

**Quando:** mensageria após 15/09; integração mirando a atividade formativa de 22/09.
**Vale:** preparo técnico pra N2 — pontuação direta na N1 não confirmada (N1 é um lab do professor, não o repo), ver [pendências](../README.md#pendências-fora-do-código).
**Entrega:** contrato REST consumido de verdade pelos colegas, spec OpenAPI publicada e consumidor RabbitMQ.

## Por que esta fase existe

Na rubrica da N2 (Canvas, R3) o RabbitMQ é obrigatório: precisa existir um fluxo funcional com publicação,
consumo e processamento, e subir o broker não basta. O REST interno continua como caminho síncrono, mas não
substitui a fila.

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

### Decisão atualizada em 25/10/2026: contrato da fila aceito

O REST interno (`POST /internal/coupons/{code}/consume`) continua disponível,
mas Order e Promotion aceitaram o contrato RabbitMQ de `OrderConfirmed`. A fila é
o alvo do fechamento integrado porque permite reentrega e idempotência.

Por quê:

- **A rubrica da N2 exige RabbitMQ num fluxo funcional (R3).** O REST segue
  disponível, mas a fila é o que cobre publicação, consumo e processamento.
- **Contrato único.** Os dois repositórios registram os mesmos nomes, payload,
  ack e regra de idempotência.
- **Responsabilidades separadas.** Order publica por outbox; Promotion mantém a
  fila, consome e deduplica por `order_id`.

Limite conhecido do REST: o `consume` não recebe `order_id`, então um retry pode
consumir o cupom duas vezes. O consumidor RabbitMQ resolve isso com uma restrição
única sobre `order_id` na mesma transação que incrementa o uso.

O contrato está fechado na issue [#50](https://github.com/Back-End-Cloud-Computing/promotion/issues/50).
A [#51](https://github.com/Back-End-Cloud-Computing/promotion/issues/51)
implementa o consumidor. O publisher do Order ainda depende de o Cart fornecer
`coupon_code` numa cotação autoritativa.

**Contrato aceito:** exchange direct durável `ganjj.orders`, routing key
`order.confirmed`, fila durável `promotion.order-confirmed` e payload versão 1
documentado em [`contrato-api.md`](../contrato-api.md#mensageria--orderconfirmed).
Entrega é pelo menos uma vez; a idempotência é por `order_id`.

### 2. Consumidor RabbitMQ (implementado em 27/10/2026, issue #51)

**O evento:** Order publica `OrderConfirmed` em `ganjj.orders` com routing key
`order.confirmed`. Este serviço consome `coupon_code` e incrementa `usage_count`.

Isso substitui a chamada HTTP síncrona de `consumir` no caminho crítico do checkout — que é exatamente o
problema que mensageria resolve: se o serviço de Promoção estiver fora do ar, o pedido ainda é fechado e o
consumo do cupom acontece quando o serviço voltar.

O consumidor roda como processo separado (`php artisan promotion:consume-orders`), no Deployment
`promotion-consumer` ([`k8s/consumer.yaml`](../../k8s/consumer.yaml)) — **nunca dentro do request HTTP**.
Usa `php-amqplib` (exige `ext-sockets`, instalada na imagem).

Como funciona, em `app/Console/Commands/ConsumeOrderConfirmed.php` e
`app/Domain/Coupons/Services/OrderConfirmedHandler.php`:

- Declara a exchange, a fila `promotion.order-confirmed` (com `x-dead-letter` apontando para
  `promotion.order-confirmed.dlq`) e o binding; prefetch 1.
- O handler valida o payload v1 (versão, tipo do evento, `order_id` UUID, itens), consome o cupom e grava o
  `order_id` (chave primária de `processed_orders`) **na mesma transação**.
- Resultados: `consumed`, `duplicate`, `no_coupon`, `coupon_not_found`, `limit_reached`. Os quatro últimos
  também recebem ack: o pedido já existe, então cupom inexistente ou esgotado não deve travar a fila.
- Ack só depois do commit. Payload inválido ou JSON quebrado: `reject` sem requeue, a mensagem vai para a DLQ e
  fica visível. Qualquer outra falha (banco fora): `nack` com requeue, depois de 1 s de espera.

Os nomes, o payload e as propriedades AMQP estão congelados em
[`contrato-api.md`](../contrato-api.md#mensageria--orderconfirmed).

**Idempotência:** a mesma mensagem pode ser entregue duas vezes (garantia
at-least-once). O consumidor grava `order_id` único na mesma transação MySQL do
incremento. Duplicata recebe ack sem alterar `usage_count`.

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
| Evento consumido | Publicar `OrderConfirmed` com routing key `order.confirmed` | `usage_count` incrementa |
| Idempotência | Publicar **a mesma** mensagem duas vezes | `usos` incrementa **uma** vez |
| Resiliência | Derrubar o consumidor, publicar, subir de novo | Mensagem processada ao voltar |
| Payload inválido | Publicar `schema_version: 2` e JSON quebrado | As duas na `promotion.order-confirmed.dlq`, fila principal vazia |

Os dois últimos são os que provam que a mensageria foi entendida, não só ligada.

**Rodado em 27/10/2026** com RabbitMQ 4.1 real (container local) e MySQL real: mensagem válida consumiu um uso
(`usage_count` 0 para 1, `outcome=consumed`); a mesma `order_id` com outro `event_id` voltou `duplicate` sem
novo incremento; cupom nulo voltou `no_coupon`; `schema_version: 2` e JSON quebrado foram para a DLQ (2
mensagens, fila principal com 0). Com a tabela `processed_orders` indisponível, o consumidor fez `nack` com
requeue 5 vezes sem tocar no cupom (`usage_count` continuou em 1); ao voltar a tabela, a mensagem foi
processada uma única vez (`usage_count` 2). Testes automatizados em `tests/Feature/OrderConfirmedTest.php`.

## Concluída quando

- [x] OpenAPI publicada e acessível (antecipada pra Fase 2)
- [ ] Carrinho consome o cálculo com sucesso
- [ ] Pedido consome e o uso incrementa
- [x] Consumidor RabbitMQ processa `OrderConfirmed` (e2e local em 27/10)
- [x] Reentrega da mesma mensagem não duplica o consumo
- [x] Consumidor tem Deployment separado (`k8s/consumer.yaml`); falta rodar no cluster (EKS)
- [ ] Fluxo ponta a ponta demonstrado antes de 06/10

## Riscos

**Dependência de colega.** Único ponto do projeto onde a entrega depende de terceiro. Mitigação: o REST
interno cobre o cálculo e o consumo síncrono, mas o RabbitMQ é exigido pela rubrica (R3) e depende do Order
publicar `OrderConfirmed`. O consumidor do Promotion já está pronto e testado com mensagens publicadas à mão;
combinar o contrato cedo, como foi feito, é o que dá margem para o atraso do publisher.

**Segredo compartilhado em trânsito.** `INTERNAL_SECRET` combinado por WhatsApp acaba em screenshot. Passar
por canal privado e usar valores diferentes em desenvolvimento e apresentação.

## Próxima

[Fase 5 — Segurança e observabilidade](fase-5-seguranca.md), mirando a N2 em 10/11.
