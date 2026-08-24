# Fase 5 — Segurança e observabilidade

**Quando:** JWT após 20/10, Helm após 27/10, observabilidade após 03/11.
**Vale:** peso na N2 (R2 — segurança, distribuição e observabilidade, 3,0 pontos) e sustenta a apresentação
técnica (R3, 3,0).
**Entrega:** JWT alinhado com o serviço real de Cliente, métricas expostas e o serviço pronto para a
apresentação em equipe.

## Por que esta fase existe

A N1 é individual; a N2 é da equipe. Aqui o serviço deixa de ser uma peça isolada e passa a ser parte de uma
solução que precisa ser defendida na frente da turma.

Grande parte do trabalho já está feita desde as fases anteriores — esta fase é de alinhamento e polimento,
não de construção.

## Tarefas

### 1. Alinhar o JWT com o serviço de Autorização

> **Atualização (24/08):** as suposições abaixo eram do repo base e **não batem** com o que o serviço de
> Autorização emite de verdade — não é só o segredo, o algoritmo e o formato do claim também mudaram. Detalhe
> completo e plano técnico em [alinhamento-jwt-rs256.md](../alinhamento-jwt-rs256.md), pendente de confirmação
> com o time de Autorização antes de implementar.

A verificação de JWT existe desde a Fase 1, mas com um contrato hipotético. O que descobrimos até agora:

- ~~O `JWT_SECRET` é o mesmo dos dois lados.~~ Não existe segredo simétrico — Autorização assina com RS256
  (par de chaves). `promotion` vai ler a chave pública direto de uma var de ambiente (`JWT_PUBLIC_KEY`), sem
  endpoint JWKS — mais simples enquanto não há rotação de chave confirmada.
- ~~O payload é mesmo `{id, email, isAdmin}`.~~ O payload real é `{sub, email, role}` — `role` é string, não
  o booleano `isAdmin` que o código assume hoje.
- ~~O algoritmo bate (HS256).~~ É RS256.
- Token expirado devolve 401 com mensagem clara, não 500 — isso continua valendo e já está coberto.

> **Nunca aceitar `alg: none`.** É a falha clássica de JWT: uma biblioteca mal configurada aceita um token sem
> assinatura e qualquer pessoa vira admin. A `firebase/php-jwt` exige o algoritmo explícito no `decode()`, o
> que já protege — desde que o algoritmo não venha do próprio token.

### 2. Revisar a superfície de ataque

Vale uma passada consciente antes da apresentação:

| Item | Verificar |
|---|---|
| Rotas admin | Todas exigem `VerifyJwt` **e** `VerifyAdmin` |
| Rotas `/internal` | Fora do Ingress público — não devem ser alcançáveis de fora do cluster |
| Mensagens de erro | Não vazam SQL, stack trace nem nome de tabela |
| `APP_DEBUG` | `false` em qualquer ambiente que não seja local |
| Segredos | Só via Secret do Kubernetes, nunca em ConfigMap nem na imagem |

`APP_DEBUG=true` em produção transforma qualquer erro numa página com variáveis de ambiente, credenciais de
banco e trecho de código. É a falha de configuração mais comum em Laravel exposto.

### 3. Métricas para Prometheus

Só faz sentido se a equipe padronizar — métrica de um serviço só não vira dashboard.

Se for adiante: `promphp/prometheus_client_php` expondo `/metrics`, com contadores que dizem algo do domínio,
não só do HTTP:

| Métrica | Por quê |
|---|---|
| `promocao_calculos_total` | Volume de uso do endpoint principal |
| `promocao_cupons_aplicados_total` | Cupom aplicado com sucesso |
| `promocao_cupons_recusados_total{motivo}` | **A mais útil** — separada por motivo de recusa |

A terceira é a que responde perguntas de negócio na apresentação: quantos cupons são recusados por valor
mínimo? Por expiração? É a diferença entre um gráfico bonito e um gráfico que ensina algo.

### 4. Logs estruturados

`LOG_CHANNEL=stderr` já vale desde a Fase 2. Aqui, garantir que o log em JSON inclua o suficiente para
correlacionar uma requisição entre serviços — um `request_id` propagado por header, se a equipe adotar.

### 5. Helm — opcional

Se a equipe padronizar Helm no deploy, empacotar os manifests da Fase 3 num chart, com `values.yaml`
parametrizando imagem, réplicas e recursos.

**Só se a equipe adotar.** Um chart para um serviço enquanto os outros quatro usam `kubectl apply` adiciona
uma ferramenta sem eliminar nenhuma.

### 6. Preparar a apresentação

A rubrica dá 3,0 pontos para a apresentação técnica demonstrando decisões arquiteturais. As decisões deste
serviço e o porquê de cada uma estão em [arquitetura.md](../arquitetura.md) — as que rendem discussão:

- Por que três tabelas em vez de um modelo unificado com discriminador.
- Por que não há foreign key para `produto_id` (e por que isso é consequência do estilo arquitetural, não
  descuido).
- Por que cupom inválido devolve 200 e não 4xx.
- Como a corrida no limite de uso é resolvida sem lock de aplicação.
- Por que a liveness probe não testa o banco.

> Uma decisão explicada com o trade-off que ela resolve vale mais que cinco recursos listados. A pergunta que
> costuma vir é "por que não fez de outro jeito" — e a resposta já está escrita.

## Verificação

| O quê | Como | Esperado |
|---|---|---|
| JWT real | Token emitido pelo serviço do Eduardo | Aceito |
| Token adulterado | Alterar o payload e reenviar | 401 |
| Token expirado | Token vencido | 401 com mensagem clara |
| Sem admin | Token válido, `role` diferente de `admin`, em rota admin | 403 |
| `/internal` de fora | Chamar pelo Ingress público | Inalcançável |
| Debug | `curl` numa rota com erro proposital | Sem stack trace |
| Métricas | `curl .../metrics` | Formato Prometheus válido |
| Recusa por motivo | Aplicar cupom expirado e conferir `/metrics` | Contador do motivo incrementa |

## Concluída quando

- [ ] JWT validado contra token real do serviço de Cliente
- [ ] Token adulterado, expirado e sem admin recusados corretamente
- [ ] `/internal` não alcançável de fora do cluster
- [ ] `APP_DEBUG=false` fora do local
- [ ] Métricas expostas (se a equipe adotar Prometheus)
- [ ] Decisões arquiteturais prontas para defesa oral

## Fecha o projeto

Com esta fase o serviço tem: domínio testado, container, cluster, integração REST e assíncrona, segurança e
observabilidade. É a peça da equipe na N2 de 10/11.
