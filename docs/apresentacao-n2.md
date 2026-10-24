# Roteiro da apresentação N2 — 10/11/2026

Rubrica (Plano de Ensino): R1 arquitetura distribuída em Kubernetes (4,0) · R2 segurança, distribuição e
observabilidade (3,0) · R3 apresentação técnica, decisões e tecnologias (3,0). Apresentação em equipe, para a
sala toda. Este roteiro cobre a parte do `promotion`; a ordem final depende do grupo.

## Demonstração

| # | O quê | Como mostrar | Rubrica |
|---|---|---|---|
| 1 | Serviço rodando no cluster | `kubectl get pods` (2 réplicas `1/1`, MySQL, init container de migration) | R1 |
| 2 | Autorrecuperação | `kubectl delete pod <um>` e `get pods -w` | R1 |
| 3 | Entrada pelo Ingress | `curl -H "Host: promotion.ganjj.local" .../api/sale` (200) | R1 |
| 4 | `/internal` e `/metrics` não vazam | mesma chamada em `/internal/...` e `/metrics`: 404 | R2 |
| 5 | JWT RS256 | token real do `authorization`; admin 200, sem token 401 (ver PROMO-002) | R2 |
| 6 | Cálculo de desconto | `POST /internal/discounts/calculate` com `x-internal-secret` | R1 |
| 7 | Observabilidade | `/metrics` por `port-forward`; painel no Grafana com os contadores de negócio, requisições por rota e latência | R2 |
| 8 | Distribuição | `helm install` (após a aula de 27/10) | R2 |

## Decisões para defender (R3)

- Serviço autocontido: roda e é avaliável sem depender de nenhum colega.
- Três tabelas em vez de modelo unificado (ver `arquitetura.md`).
- Dinheiro em centavos inteiros; percentual é a única operação em ponto flutuante.
- Migration em initContainer, não no entrypoint, para não rodar em paralelo com 2 réplicas.
- Liveness sem banco, readiness com banco: lentidão do MySQL não derruba todos os pods.
- Ingress por lista de permissão (`/api`, `/docs`); `/internal` protegido por rede e por segredo.
- Métricas de domínio (`motivo` de recusa) em vez de só métricas HTTP; contadores por pod.
- Consumo do cupom por REST interno, não por fila: a rubrica aceita "REST e/ou fila" e o contrato do evento depende do Order (ver `fases/fase-4-integracao.md`).
- `product_id` é string porque o Product usa ObjectId do Mongo; inteiro ainda é aceito.
- RS256 com chave pública em ConfigMap: não é segredo. Segredos só em Secret.

## Perguntas prováveis

- Por que PHP/Laravel? Stack livre confirmada pelo professor; o foco é a arquitetura.
- Por que não promphp? Três contadores não justificam a dependência; ver `fases/fase-5-seguranca.md`.
- Os contadores zeram se o pod reinicia? Sim; o Prometheus trata reset de counter (`rate()`/`increase()`).
- Quem chama o `promotion`? Pelo ADR do Order, o Cart compõe a cotação (INT-002). O Cart chama `calculate` desde 23/10; o Product expõe preço e estoque desde 24/10 (product#9, lido no código, não rodado); falta rodar o fluxo ponta a ponta e o consumo do cupom ainda não tem chamador.

- Vocês testaram tudo junto? Sim, em 21/10: 11 repos no Compose e no Minikube, com achados e issues por dono (`teste-integracao-2026-10.md`).
- Por que não tem fila? Ver a decisão acima; a proposta de contrato do evento está escrita e depende do aceite do Order.

## Antes do dia

- [x] Imagem publicada: `bascratki/promotion:1.0.0`, multi-arch (amd64 e arm64), em 19/10/2026.
- [x] Chart Helm instalado no Minikube (21/10, 1 réplica, `/api/sale` 200 pelo Ingress).
- [x] Imagem testada com `ImagePullPolicy` real no cluster (21/10, Minikube limpo, pull do Docker Hub).
- [x] PROMO-002 executado com token real (21/10, chave de lab; repetir com a chave oficial do Eduardo).
- [ ] Dashboard do Grafana exportado em JSON (promotion #47; já existem os de product, vector-db, embedding-reranking e shopping-cart).
- [ ] Ensaio com o grupo; tempo por pessoa.
