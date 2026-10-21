# Documentação — Microsserviço de Promoção (GANJJ)

Documentação de arquitetura e planejamento do serviço. Escrita para ser lida na ordem abaixo.

## Índice

| Documento | O que responde |
|---|---|
| [Arquitetura e plano geral](arquitetura.md) | Por que este serviço existe, modelo de dados, camadas, contrato REST, decisões e o porquê de cada uma |
| [Contrato da API](contrato-api.md) | Referência de endpoints para quem vai **consumir** este serviço (Carrinho, Pedido, Gateway) |
| [Regras de negócio](regras-de-negocio.md) | As regras de desconto e cupom, com os casos de teste que provam cada uma |
| [Mapa de issues da entrega final](issues-entrega-final.md) | O que falta de issue em cada repo e o que precisa estar pronto para a N2 rodar de ponta a ponta |
| [Teste de integração 21/10](teste-integracao-2026-10.md) | Os 11 repos no Compose e no Minikube: o que sobe, o que quebra e as issues geradas |
| [Alinhamento JWT/RS256](alinhamento-jwt-rs256.md) | ✅ Resolvido — ver [ADR 0002](adr/0002-jwt-rs256-chave-estatica.md); falta só validar com token real do Autorização |

## Fases de execução

Cada fase é entregável por si só e está ancorada numa data do cronograma da disciplina.

| Fase | Documento | Quando | Entrega |
|---|---|---|---|
| 0 | [Fundação](fases/fase-0-fundacao.md) | agora | Projeto, esteira, banco, primeiro commit |
| 1 | [MVP do laboratório](fases/fase-1-mvp.md) | antes de 18/08 | Domain/Service/Controller, CRUD, cálculo de desconto testado |
| 2 | [Docker](fases/fase-2-docker.md) | após 18/08 | Imagem, compose, health checks |
| 3 | [Kubernetes](fases/fase-3-kubernetes.md) | após 25/08 | Manifests, probes, secrets |
| 4 | [Integração e mensageria](fases/fase-4-integracao.md) | após 15/09 | REST interno + RabbitMQ |
| 5 | [Segurança e observabilidade](fases/fase-5-seguranca.md) | após 20/10 | JWT alinhado, métricas, Helm — **obrigatórios** pelo Plano de Ensino (R2 da N2) |
| N2 | [Roteiro da apresentação](apresentacao-n2.md) | 10/11/2026 | O que mostrar, em que ordem, e o que responder |

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
| **10/11/2026 (terça)** | **N2 — apresentação em equipe, 40% da nota** (confirmado no Plano de Ensino oficial, aula 12; o Canvas não tem tarefa nem data da N2) |

## Pendências fora do código

Estas dependem de conversa, não de commit:

- [x] ~~Confirmar com o professor se PHP/Laravel é aceito~~ — confirmado em 18/10/2026: stack livre.
- [x] ~~Definir quem constrói o **microsserviço de suporte** e o **API Gateway**~~ — definido em 19/10/2026: Gateway é do Lucas ([`api-gateway`](https://github.com/Back-End-Cloud-Computing/api-gateway)) e suporte é do Eduardo ([`support`](https://github.com/Back-End-Cloud-Computing/support)). Repos novos, com docs, issues e scaffold próprios.
- [x] ~~Avisar o Rodrigo (Pedido) que ambos usarão MySQL~~ — desnecessário: o Pedido usa SQL Server (conferido em 19/10/2026 no repo `order`); a doc estava errada.
- [ ] Combinar com o Rodrigo o nome da exchange/fila e o payload do evento de pedido confirmado (pré-requisito da Fase 4).
- [ ] Pedir ao Eduardo (Autorização) a chave pública real de deploy pra colar em `JWT_PUBLIC_KEY` e testar ponta a ponta — o contrato já está fechado ([ADR 0002](adr/0002-jwt-rs256-chave-estatica.md)), falta só essa validação com token real.
- [ ] Entender o formato exato da N1 agora que é um lab do professor, não o repo individual — o que isso muda pra pontuação das fases 2/3/4 (se muda algo).
- [x] ~~Definir a Gate `viewApiDocs` (Scramble)~~ — feito: flag `API_DOCS_PUBLIC` (ConfigMap, ligada na demo). Contexto original: (Scramble) se a demonstração da API for feita fora de `APP_ENV=local` — sem
  isso, `/docs/api` devolve 403 no cluster K8s (`APP_ENV=production`). Decisão: testes manuais via OpenAPI, sem coleção Postman (ver [Fase 1](fases/fase-1-mvp.md#7-testes-manuais-via-openapi)).
