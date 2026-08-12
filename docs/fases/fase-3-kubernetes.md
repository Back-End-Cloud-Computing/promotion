# Fase 3 — Kubernetes

**Quando:** após a aula de 25/08 (Ingress após 01/09).
**Vale:** 3,0 pontos na N1 (R3 — distribuição no cluster com padrões arquiteturais).
**Entrega:** manifests, probes ligadas aos health checks, configuração externalizada e segredos fora do
repositório.

## Por que esta fase existe

É o terço final da nota individual, e é o que transforma "tenho um container" em "tenho um serviço
distribuído" — que é o tema da disciplina.

## Pré-requisitos

Instalar antes da aula, não durante:

```bash
brew install kubectl minikube
minikube start
```

Nenhum dos dois está instalado na máquina hoje.

## Tarefas

### 1. Conferir o 12-factor

O Kubernetes assume aplicação stateless e configurada por ambiente. O que já deve estar valendo desde a
Fase 0:

| Fator | Estado |
|---|---|
| Config por env var, zero hardcode | Vem de graça no Laravel se nada for hardcoded |
| Log para stdout | `LOG_CHANNEL=stderr`, Fase 2 |
| Sem estado em disco local | Sem upload neste serviço; sessão em `array` |
| Processo descartável | `artisan serve` encerra limpo no SIGTERM |

Vale conferir de verdade, não presumir: um `config('app.url')` hardcoded ou um cache em arquivo local só
aparecem quando a segunda réplica sobe.

### 2. Manifests em `k8s/`

| Arquivo | Conteúdo |
|---|---|
| `deployment.yaml` | Deployment da aplicação, réplicas, probes, recursos |
| `service.yaml` | `ClusterIP` expondo a porta 8000 |
| `configmap.yaml` | Config não sensível (`APP_NAME`, `DB_HOST`, `APP_TIMEZONE`) |
| `secret.yaml` | Template com placeholder — **nunca com valor real** |
| `mysql.yaml` | Banco com PVC |

### 3. Probes ligadas aos health checks

```yaml
livenessProbe:
  httpGet: { path: /health, port: 8000 }
  initialDelaySeconds: 10
readinessProbe:
  httpGet: { path: /health/ready, port: 8000 }
  initialDelaySeconds: 5
```

Aqui a distinção da Fase 2 paga: a liveness não pode testar o banco. Se testasse, uma indisponibilidade
momentânea do MySQL faria o Kubernetes matar e recriar todos os pods da aplicação — transformando um problema
de banco em um apagão completo.

### 4. Segredos

`JWT_SECRET`, `INTERNAL_SECRET` e `DB_PASSWORD` entram via `kubectl create secret`, com o comando documentado
no README. O `secret.yaml` versionado tem apenas placeholders.

> Secret do Kubernetes é base64, não criptografia. Um `secret.yaml` com valor real commitado é um segredo em
> texto claro com um passo a mais.

### 5. MySQL no cluster

**Deployment + PersistentVolumeClaim**, não StatefulSet.

StatefulSet existe para identidade estável de rede e volume por réplica — necessário em banco replicado. Aqui é
uma réplica de MySQL para fins acadêmicos: Deployment + PVC entrega o mesmo resultado com metade do YAML.

**Confirmar em aula.** Alguns cursos cobram StatefulSet para banco como padrão arquitetural pontuável no R3.
Se for o caso, trocar — é uma exigência acadêmica que justifica a complexidade extra. Pergunta barata, ponto
caro.

### 6. Ingress — após 01/09

`k8s/ingress.yaml` expondo as rotas sob o Ingress controller compartilhado da equipe.

Depende de alinhamento: quem sobe o controller comum, qual host, qual prefixo de path cabe a cada serviço.
Esta é a primeira tarefa da fase que não roda sozinha.

## Verificação

| O quê | Como | Esperado |
|---|---|---|
| Manifests válidos | `kubectl apply --dry-run=client -k k8s/` | Sem erro |
| Deploy | `kubectl apply -k k8s/` | Pods criados |
| Readiness funciona | `kubectl get pods` | `READY 1/1` — prova que a probe passa |
| Serviço responde | `kubectl port-forward svc/promotion 8000:8000` e `curl .../health` | 200 |
| Config externalizada | `kubectl describe pod` | Env vem de ConfigMap e Secret, não da imagem |
| Recuperação | `kubectl delete pod <nome>` | Novo pod sobe e fica Ready |
| Sem segredo no repo | `gitleaks detect` | Nenhum achado |

`READY 1/1` é a verificação mais informativa da fase: significa que a readiness probe passou de verdade, ou
seja, que o pod conectou no banco. Um pod `Running` mas `0/1` parece saudável na listagem e não recebe
tráfego.

## Concluída quando

- [ ] `kubectl apply -k k8s/` sobe aplicação e banco
- [ ] Pods ficam `READY 1/1`
- [ ] Endpoint responde via `port-forward`
- [ ] Config vem de ConfigMap; segredos de Secret criado fora do git
- [ ] Pod deletado é recriado sozinho
- [ ] Ingress funcionando (após 01/09, depende da equipe)

## Riscos

**Imagem local no minikube.** O cluster não enxerga a imagem do Docker local por padrão. Resolver com
`eval $(minikube docker-env)` antes do build, ou `minikube image load`. É o erro mais comum da primeira
tentativa e se manifesta como `ImagePullBackOff`.

**Migrations em múltiplas réplicas.** Duas réplicas subindo juntas rodam `migrate` simultaneamente. Com uma
réplica não acontece; se escalar, migration vira `initContainer` ou `Job`.

## Próxima

[Fase 4 — Integração e mensageria](fase-4-integracao.md), após 15/09. Vale 4,0 pontos na N1 — a maior fatia.
