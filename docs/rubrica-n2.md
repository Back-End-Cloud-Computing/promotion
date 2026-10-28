# Rubrica da N2 (Canvas) e como o projeto atende

Fonte: tarefa "Avaliação Somativa N2" no Canvas, colada pelo Lucas em 27/10/2026. Substitui a rubrica do Plano
de Ensino (R1 4,0 · R2 3,0 · R3 3,0) que os docs usavam até 26/10.

## O que o Canvas pede

Apresentação prática em equipe, em sala, em **10/11/2026**, valendo 10 pontos. Ambiente: **Amazon AWS, Amazon
EKS**. Tema: arquitetura Cloud Native com microsserviços, Kubernetes e mensageria.

Requisitos do projeto:

1. **Desenvolvimento individual:** cada integrante implementa pelo menos um microsserviço com responsabilidade clara.
2. **Diversidade tecnológica:** pelo menos duas linguagens e dois SGBDs diferentes.
3. **Conteinerização:** todos os microsserviços com imagem Docker, rodando em container.
4. **Amazon EKS:** implantação num cluster gerenciado pelo EKS, com Deployments, Pods e Services.
5. **Database per Service:** banco próprio por serviço que persiste, sem compartilhar tabelas ou dados.
6. **Comunicação síncrona:** APIs REST entre microsserviços.
7. **Comunicação assíncrona:** RabbitMQ para publicação e consumo de mensagens entre microsserviços.
8. **Service Discovery:** comunicação interna pelos nomes dos Services, sem IP de Pod.
9. **Escalabilidade horizontal:** pelo menos um serviço com várias réplicas, mostrando o balanceamento entre Pods.
10. **Ingress:** APIs expostas pelo NGINX Ingress Controller, com regras de roteamento para os microsserviços.

Regras importantes (texto do Canvas, resumido):

- A participação de cada integrante tem de ser identificável; cada um apresenta o próprio serviço.
- **EKS é obrigatório.** Docker Desktop e Minikube servem para desenvolver, mas não substituem a implantação na AWS.
- O RabbitMQ precisa participar de um fluxo funcional. **Não basta implantar o broker.**
- A escalabilidade precisa de várias réplicas **e evidência de balanceamento**.
- A apresentação deve permitir ao professor verificar objetivamente os critérios das três rubricas.

## Critérios e pontos

| Critério | Completo | Incompleto | Não realizou |
|---|---|---|---|
| **R1** | 3 pts: cada integrante com um microsserviço funcional; duas linguagens e dois bancos, persistência independente | 1,5 pts | 0 |
| **R2** | 4 pts: implantado no Amazon EKS, com Docker, Services, Service Discovery, Ingress e balanceamento entre réplicas | 2 pts | 0 |
| **R3** | 2 pts: integração por REST e RabbitMQ, com publicação, consumo e processamento de mensagens | 1 pt | 0 |

Os critérios somam 9, mas a tarefa vale 10. Pergunta em aberto para o professor.

Helm, Prometheus, Grafana e JWT **não aparecem** na rubrica. Continuam no projeto como extra (`origem: nossa`).

## Como o projeto atende (27/10/2026)

| Requisito | Como atendemos | Evidência | Estado | Dono |
|---|---|---|---|---|
| 1 microsserviço por integrante | 5 pessoas, 11 serviços: Lucas (`promotion`, `api-gateway`), Eduardo (`authorization`, `client`, `support`), Rodrigo (`order`), João Liz (`shopping-cart`), João Correa (`product`, `llm-provider`, `embedding-reranking`, `vector-db`) | repos da organização | ok | todos |
| 2 linguagens / 2 SGBDs | PHP (`promotion`), TypeScript (`api-gateway`), Node.js (`shopping-cart`), C# (`order`), Java (`support`, `authorization`), Go (`client`), Python (`product` e serviços de IA). MySQL, SQL Server, PostgreSQL, MongoDB, Oracle, Redis, SQLite, Chroma | `k8s/` de cada repo | ok | todos |
| Conteinerização | imagens publicadas, multi-arch (amd64 e arm64) | Docker Hub e GHCR; `promotion` em `bascratki/promotion:1.2.0` | ok | todos |
| Amazon EKS | kit `eks/` no `api-gateway` (cluster eksctl no padrão do lab L06 e script de apply) | — | **pendente** ([api-gateway#30](https://github.com/Back-End-Cloud-Computing/api-gateway/issues/30)) | Lucas |
| Database per Service | um banco por serviço, cada um com Service próprio (`promotion-db`, `sqlserver`, `postgres`...) | `k8s/` | ok | todos |
| REST | Cart → Product (preço e estoque), Cart → Promotion (cotação), Order → Cart e Product, gateway → todos | cotação 160/144 em 25/10 ([teste](teste-integracao-2026-10.md)) | ok; pedido completo depende do order#19 rodado no cluster | Lucas, João Liz, Rodrigo |
| RabbitMQ | Order publica `OrderConfirmed` em `ganjj.orders`; Promotion consome (`promotion:consume-orders`, idempotente por `order_id`) e incrementa o uso do cupom; Support consome e notifica | consumer do Promotion rodado com broker real em 27/10 ([fase 4](fases/fase-4-integracao.md)) | consumers ok; **publisher do Order pendente** ([order#20](https://github.com/Back-End-Cloud-Computing/order/issues/20)); routing key do Support a alinhar (`order.created` x `order.confirmed`, [support#11](https://github.com/Back-End-Cloud-Computing/support/issues/11)) | Rodrigo, Eduardo, Lucas |
| Service Discovery | URLs por nome de Service: `http://promotion:8000`, `http://product-api:8000`, `rabbitmq:5672` | ConfigMaps dos serviços | ok | todos |
| Escalabilidade horizontal | `promotion` e `api-gateway` com várias réplicas; header `X-Served-By` (promotion) e `X-Gateway-Served-By` (gateway) com o hostname do pod | 10 requisições pelo Ingress mostrando pods diferentes | código pronto (27/10); evidência no EKS pendente | Lucas |
| Ingress NGINX | NGINX Ingress Controller com uma regra para o `api-gateway`, que roteia por path para cada serviço | `api-gateway/k8s/ingress.yaml` | ok no Minikube; EKS pendente | Lucas |

**Decisão do Ingress (27/10):** o gateway é a entrada única. O Ingress tem regra para o gateway e o gateway faz o
roteamento por path para os microsserviços. Regras de Ingress por serviço foram descartadas pelo prazo. Risco: o
Canvas fala em "regras de roteamento para os microsserviços"; na apresentação, mostrar a tabela de rotas do
gateway como essa camada de roteamento.

## Checklist da apresentação (itens do Canvas)

- [ ] Arquitetura da solução e responsabilidade de cada microsserviço.
- [ ] Microsserviços rodando no Amazon EKS (`kubectl get nodes` e `kubectl get pods -A`).
- [ ] Linguagens e bancos utilizados.
- [ ] Isolamento da persistência (um banco e um Service por serviço).
- [ ] Acesso externo às APIs pelo Ingress (hostname do ELB).
- [ ] Comunicação interna por Service Discovery (ConfigMaps com nomes de Service).
- [ ] Serviço com várias réplicas e balanceamento (headers de pod).
- [ ] Fluxo integrado REST + RabbitMQ (pedido gera `OrderConfirmed`, painel do RabbitMQ com publish e ack, uso do cupom incrementado).

O roteiro da parte do `promotion` está em [`apresentacao-n2.md`](apresentacao-n2.md).
