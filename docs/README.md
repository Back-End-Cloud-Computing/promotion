# Documentação — Microsserviço de Promoção (GANJJ)

Documentação de arquitetura e planejamento do serviço. Escrita para ser lida na ordem abaixo.

## Índice

| Documento | O que responde |
|---|---|
| [Arquitetura e plano geral](arquitetura.md) | Por que este serviço existe, modelo de dados, camadas, contrato REST, decisões e o porquê de cada uma |
| [Contrato da API](contrato-api.md) | Referência de endpoints para quem vai **consumir** este serviço (Carrinho, Pedido, Gateway) |
| [Regras de negócio](regras-de-negocio.md) | As regras de desconto e cupom, com os casos de teste que provam cada uma |
| [Alinhamento JWT/RS256](alinhamento-jwt-rs256.md) | 🟡 Pendente — o contrato real do serviço de Autorização diverge do assumido; plano técnico pronto, aguardando confirmação |

## Fases de execução

Cada fase é entregável por si só e está ancorada numa data do cronograma da disciplina.

| Fase | Documento | Quando | Entrega |
|---|---|---|---|
| 0 | [Fundação](fases/fase-0-fundacao.md) | agora | Projeto, esteira, banco, primeiro commit |
| 1 | [MVP do laboratório](fases/fase-1-mvp.md) | antes de 18/08 | Domain/Service/Controller, CRUD, cálculo de desconto testado |
| 2 | [Docker](fases/fase-2-docker.md) | após 18/08 | Imagem, compose, health checks |
| 3 | [Kubernetes](fases/fase-3-kubernetes.md) | após 25/08 | Manifests, probes, secrets |
| 4 | [Integração e mensageria](fases/fase-4-integracao.md) | após 15/09 | REST interno + RabbitMQ |
| 5 | [Segurança e observabilidade](fases/fase-5-seguranca.md) | após 20/10 | JWT alinhado, métricas — pesa na N2 |

## Datas que não se movem

| Data | Evento |
|---|---|
| 18/08 | Aula de Docker |
| 25/08 | Aula de Kubernetes e Service Discovery |
| 01/09 | Aula de NGINX Ingress Controller |
| 15/09 | Aula de Mensageria com RabbitMQ |
| 22/09 | Atividade formativa: integração de microsserviços |
| **06/10** | **N1 — avaliação individual, 60% da nota (lab passado pelo professor, não o repo)** |
| 20/10 | Aula de Segurança com JWT |
| 27/10 | Aula de Helm |
| 03/11 | Aula de Observabilidade |
| **10/11** | **N2 — apresentação em equipe, 40% da nota** |

## Pendências fora do código

Estas dependem de conversa, não de commit:

- [ ] Confirmar com o professor se **PHP/Laravel** é aceito — os slides citam Java, Python e Node com framework, e Kotlin, C# e Go como alternativas. A lista não se apresenta como fechada, mas confirmar custa uma pergunta.
- [ ] Definir com a equipe quem constrói o **microsserviço de suporte** (e-mail/SMS/WhatsApp) e o **API Gateway** — são exigências do professor que nenhum dos 5 serviços de domínio cobre.
- [ ] Avisar o Rodrigo (Pedido) que ambos usarão MySQL. Não quebra o requisito de "≥2 bancos distintos", mas o grupo deve saber.
- [ ] Combinar com o Rodrigo o nome da exchange/fila e o payload do evento de pedido confirmado (pré-requisito da Fase 4).
- [ ] Confirmar com o time de Autorização a chave pública RS256, o formato exato dos claims e os valores de `role` — ver [alinhamento-jwt-rs256.md](alinhamento-jwt-rs256.md).
- [ ] Entender o formato exato da N1 agora que é um lab do professor, não o repo individual — o que isso muda pra pontuação das fases 2/3/4 (se muda algo).
