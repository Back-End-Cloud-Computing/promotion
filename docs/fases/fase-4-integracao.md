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

### 1. OpenAPI publicada

```bash
composer require dedoc/scramble
```

Scramble gera a spec a partir dos FormRequests, Resources e type hints que já existem — sem anotação manual.
Serve em `/docs/api` e `/docs/api.json`.

A alternativa (`zircote/swagger-php`) exige anotar cada controller com PHPDoc, o que duplica em comentário o
que o código já declara — e comentário duplicado é comentário que desatualiza.

> O repo base é a prova do problema: a spec do gateway é um stub vazio enquanto a spec real está num arquivo
> estático desatualizado. Documentação que não é gerada do código diverge do código.

**Por que isso vem primeiro:** os colegas precisam da spec para integrar. Publicar antes de combinar qualquer
coisa reduz a conversa a "está em `/docs/api`".

### 2. Integração REST com Carrinho e Pedido

`POST /internal/descontos/calcular` já existe desde a Fase 1. O trabalho aqui é fazer o consumo acontecer de
verdade:

| Serviço | Chama | Quando |
|---|---|---|
| Carrinho (João Liz, Node) | `/internal/descontos/calcular` | A cada mudança do carrinho — idempotente, não consome uso |
| Pedido (Rodrigo, C#) | `/internal/descontos/calcular` + `/internal/cupons/{codigo}/consumir` | No fechamento |

O que precisa ser combinado:

- O valor de `INTERNAL_SECRET` (mesmo segredo dos dois lados).
- A URL do serviço no cluster (`http://promotion:8000`, via DNS do Kubernetes).
- Que o cálculo **não** consome uso e o fechamento **sim** — se o Carrinho chamar `consumir`, um cupom de 500
  usos se esgota sem nenhuma venda.

### 3. Consumidor RabbitMQ

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

### 4. Teste ponta a ponta

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

- [ ] OpenAPI publicada e acessível
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
