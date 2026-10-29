# Roteiro da apresentação N2 — 10/11/2026

Rubrica do Canvas ([`rubrica-n2.md`](rubrica-n2.md)): **R1** microsserviços, duas linguagens e dois bancos (3 pts)
· **R2** Amazon EKS com Docker, Services, Service Discovery, Ingress e balanceamento entre réplicas (4 pts) ·
**R3** REST e RabbitMQ com publicação, consumo e processamento (2 pts). Apresentação em equipe, em sala, com a
solução rodando no **Amazon EKS**; cada integrante apresenta o próprio serviço. Este roteiro cobre a parte do
`promotion`; a ordem final depende do grupo.

## Demonstração

| # | O quê | Como mostrar | Rubrica |
|---|---|---|---|
| 1 | Serviço no EKS | `kubectl get pods -l app=promotion` (réplicas `1/1`), `promotion-db` e `promotion-consumer` | R2 |
| 2 | Banco próprio | Service `promotion-db` (MySQL) usado só pelo `promotion`; nenhum outro serviço tem a credencial | R1 |
| 3 | Acesso externo pelo Ingress | `curl http://<ELB>/api/sale` pelo gateway (200, rota pública) | R2 |
| 4 | Balanceamento | 10 requisições pelo Ingress mostrando `X-Served-By` (pod do `promotion`) e `X-Gateway-Served-By` (pod do gateway) diferentes | R2 |
| 5 | Service Discovery | ConfigMap do Cart com `http://promotion:8000`; o Cart chama `calculate` pelo nome do Service | R2 |
| 6 | REST | carrinho com cupom pelo gateway: preço do Product e desconto do Promotion | R3 |
| 7 | RabbitMQ | pedido gera `OrderConfirmed`; `kubectl exec deploy/rabbitmq -- rabbitmqctl list_queues name messages consumers` mostra a fila com consumidor; `kubectl logs deploy/promotion-consumer` mostra `consumed`; `usage_count` do cupom sobe 1 | R3 |
| 8 | Idempotência | republicar a mesma mensagem: log `duplicate`, `usage_count` não muda | R3 |
| 9 | Autorrecuperação (extra) | `kubectl delete pod <um>` e `get pods -w` | R2 |

O publisher do Order está mergeado ([order#21](https://github.com/Back-End-Cloud-Computing/order/pull/21)), mas publica `coupon_code` nulo: o Promotion consome e dá ack sem incrementar cupom. Por isso o item 7 mostra a publicação real do Order na fila e, para o `usage_count` subir, publica `OrderConfirmed` com cupom por AMQP de dentro do pod do `promotion-consumer`, como na [evidência de 27/10](evidencias/eks-2026-10-27.md). Dizer isso na apresentação: publicação, consumo e processamento existem, mas o Order ainda não leva o cupom na mensagem. Não há painel do
RabbitMQ: a imagem do support (`rabbitmq:4.1-alpine`) não traz o plugin de management.

## Decisões para defender

- Gateway como entrada única: o Ingress NGINX tem regra para o `api-gateway`, que roteia por path para cada
  serviço. Regras de Ingress por serviço foram descartadas pelo prazo (decisão de 27/10).
- Fila para o consumo do cupom, com idempotência por `order_id` gravado na mesma transação do incremento: o
  REST `consume` não recebe `order_id`, então um retry do Order consumiria o cupom duas vezes.
- Ack só depois do commit; payload inválido vai para a DLQ `promotion.order-confirmed.dlq`; falha de banco
  volta para a fila.
- Consumidor em Deployment próprio (`promotion-consumer`), nunca dentro do request HTTP.
- Serviço autocontido: roda e é avaliável sem depender de nenhum colega.
- Três tabelas em vez de modelo unificado (ver `arquitetura.md`).
- Dinheiro em centavos inteiros; percentual é a única operação em ponto flutuante.
- Migration em initContainer, não no entrypoint, para não rodar em paralelo com várias réplicas.
- Liveness sem banco, readiness com banco: lentidão do MySQL não derruba todos os pods.
- `/internal` e `/metrics` fora do gateway: protegidos por rede e por segredo.
- `product_id` é string porque o Product usa ObjectId do Mongo; inteiro ainda é aceito.

## Perguntas prováveis

- Por que PHP/Laravel? Stack livre confirmada pelo professor; o foco é a arquitetura.
- E se a mesma mensagem chegar duas vezes? `order_id` é chave primária de `processed_orders`; a segunda vira
  `duplicate` e o incremento é desfeito na mesma transação (rodado com broker real em 27/10, ver
  `fases/fase-4-integracao.md`).
- E se o banco cair no meio? Nada é gravado e a mensagem volta para a fila; quando o banco volta, é processada
  uma vez.
- Cupom inexistente ou esgotado no pedido? O pedido já existe, então a mensagem recebe ack com o resultado
  registrado, sem travar a fila.
- Como provam o balanceamento? Pelos headers com o hostname do pod em cada resposta.
- Helm, Prometheus e Grafana? Não estão na rubrica. Extra: o `promotion` tem chart, `/metrics` e painel
  ([`k8s/observability/`](../k8s/observability/README.md)), e o Grafana da solução sobe com o `install.sh` do api-gateway.

## Antes do dia

- [x] Imagem `bascratki/promotion:1.2.0` (consumer e `X-Served-By`), amd64 e arm64, publicada em 27/10/2026.
- [x] Consumer RabbitMQ testado com broker e MySQL reais (27/10).
- [x] Cluster no EKS com os 11 serviços (api-gateway#30), 23 pods prontos, Ingress, balanceamento e consumo do
  `OrderConfirmed` rodados em 27/10 ([evidência](evidencias/eks-2026-10-27.md)). Gateway em `bascratki/api-gateway:1.1.1`, rodado no EKS em 28/10.
- [x] Publisher `OrderConfirmed` no Order mergeado (order#21, 28/10). Falta rodá-lo no EKS e decidir se o `coupon_code` entra na mensagem. O Support segue em `order.created`, que o Order publica (order#22).
- [x] Cart com timeout no `fetch` do promotion (shopping-cart#12, resolvido em 28/10). Reconferir a degradação no EKS antes de demonstrá-la.

## O que falta (29/10/2026)

O cluster `ganjj` de 27/10 já não existe: o `eksctl delete` foi cortado pelo fim do lab e o control plane pode ter
sobrado. A evidência do EKS é daquele cluster, então o ensaio precisa recriá-lo.

**Lucas**

- [ ] Na próxima sessão do Learner Lab, terminar a remoção (`eksctl delete cluster --name ganjj --region us-east-1`)
  e conferir que `aws eks list-clusters --region us-east-1` volta vazio. O control plane cobra crédito.
- [ ] Recriar o cluster (`eks/create-cluster.sh` e `eks/apply.sh`, 15 a 20 min mais o apply) e cronometrar. A sessão do lab dura 4 h.
- [ ] Rodar o fluxo completo no EKS: pedido pelo gateway (`chain-v2.sh`), publisher do Order e consumer do Promotion. Nunca foi feito com o Order real. Registrar o arquivo de evidência.
- [ ] `php artisan db:seed --force` no promotion depois do deploy: os bancos estão em `emptyDir` e o cupom `BEMVINDO10` some a cada recriação de pod.
- [ ] Perguntar ao professor sobre 9 x 10 pontos.
- [ ] Ensaio com o grupo no EKS, com tempo por pessoa ([promotion#54](https://github.com/Back-End-Cloud-Computing/promotion/issues/54), [api-gateway#14](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/14)).
- [ ] JWT com token real, extra fora da nota ([promotion#49](https://github.com/Back-End-Cloud-Computing/promotion/issues/49), [api-gateway#13](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/13)): depende do Eduardo.
- [ ] Cotação do carrinho e consumo no pedido ([promotion#52](https://github.com/Back-End-Cloud-Computing/promotion/issues/52)): destravada em parte pelo order#20, mas esbarra no `coupon_code` nulo.

**Rodrigo (Order)**

- [ ] Decidir se `coupon_code` entra na mensagem `OrderConfirmed`. Sem isso o `usage_count` só sobe com mensagem manual. O Cart precisa fornecer a cotação.
- [ ] Rodar o publisher no cluster compartilhado e fechar o E2E pelo gateway ([order#12](https://github.com/Back-End-Cloud-Computing/order/issues/12)).
- [ ] Ensaio ([order#8](https://github.com/Back-End-Cloud-Computing/order/issues/8)) e readiness ([order#1](https://github.com/Back-End-Cloud-Computing/order/issues/1)).

**Eduardo (Support, Authorization, Client)**

- [ ] Ensaio ([support#14](https://github.com/Back-End-Cloud-Computing/support/issues/14)).
- [ ] Token real do Authorization para o teste do JWT (ver acima).
- [ ] Opcional: ligar o Support também em `order.confirmed` ([support#12](https://github.com/Back-End-Cloud-Computing/support/issues/12) é SMS e WhatsApp reais, extra).

**Todos**

- [ ] Imagem amd64 pública de cada serviço, conferida pelo `apply.sh` no cluster novo (pod `Running`, não `ImagePullBackOff`).
- [ ] Trabalhar por PR: o shopping-cart teve 5 commits direto na `main` e issues fechadas sem commit referenciando.

**Se não fechar até 10/11:** apresentar o RabbitMQ com a mensagem publicada por AMQP e dizer que o Order ainda não leva o cupom. O roteiro acima já prevê isso. O ensaio no EKS é o único item que não dá para pular.
