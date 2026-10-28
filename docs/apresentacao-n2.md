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

Se o publisher do Order não estiver pronto até o ensaio, o item 7 é feito publicando `OrderConfirmed` por AMQP de
dentro do pod do `promotion-consumer`, como na [evidência de 27/10](evidencias/eks-2026-10-27.md), e isso é dito
na apresentação: o consumo e o processamento ficam demonstrados, a publicação pelo Order não. Não há painel do
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
  `OrderConfirmed` rodados em 27/10 ([evidência](evidencias/eks-2026-10-27.md)). Gateway em `bascratki/api-gateway:1.1.0`.
- [ ] Publisher `OrderConfirmed` no Order (order#20) e Support ligado em `order.confirmed` (support#11).
- [ ] Cart com timeout no `fetch` do promotion (shopping-cart#12). Não demonstrar a degradação antes disso.
- [ ] Pedido pelo gateway no EKS (`chain-v2.sh`) com o order#19 já mergeado; registrar o arquivo de evidência.
- [ ] Perguntar ao professor sobre 9 x 10 pontos.
- [ ] Ensaio com o grupo no EKS; tempo por pessoa.
