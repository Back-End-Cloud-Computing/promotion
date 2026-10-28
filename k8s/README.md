# Kubernetes — `promotion`

Ver [docs/fases/fase-3-kubernetes.md](../docs/fases/fase-3-kubernetes.md) para o porquê de cada decisão.

## 1. Imagem

O Deployment usa a imagem publicada `bascratki/promotion:1.2.0` (Docker Hub), como os outros serviços do grupo.
Para publicar uma versão nova (precisa de `docker login` com a conta `bascratki`):

```bash
docker build -t bascratki/promotion:1.2.0 .
docker push bascratki/promotion:1.2.0
```

Para testar código local sem publicar, builde dentro do minikube com a mesma tag — o `IfNotPresent` usa a imagem
já carregada em vez de baixar:

```bash
eval $(minikube docker-env)
docker build -t bascratki/promotion:1.2.0 .
```

## 2. Criar o Secret

Sem template versionado — nem com placeholder. `kind: Secret` no git é o tipo de coisa que alguém edita local
pra testar, esquece de reverter e o placeholder vira valor real no histórico. As chaves que o Deployment espera
encontrar em `promotion-secrets`: `APP_KEY`, `DB_PASSWORD`, `INTERNAL_SECRET`.

```bash
kubectl create secret generic promotion-secrets \
  --from-literal=APP_KEY="$(docker run --rm --entrypoint php bascratki/promotion:1.2.0 artisan key:generate --show)" \
  --from-literal=DB_PASSWORD=promotion \
  --from-literal=INTERNAL_SECRET=troque-por-um-segredo-de-verdade
```

Precisa rodar de novo sempre que o cluster for recriado (`minikube delete`) — o Secret não é gerenciado pelo
kustomize, só o resto (`kubectl apply -k k8s/`).

### Credenciais do RabbitMQ

O `promotion-consumer` lê usuário e senha do Secret `rabbitmq-credentials` (chaves `username` e `password`), o mesmo
que o `support` usa para subir o broker (`kubectl apply -f k8s/rabbitmq.yaml` no repositório `support`). Sem esse
Secret o pod do consumidor fica em `CreateContainerConfigError`; sem o Service `rabbitmq` ele reinicia até o broker
responder. Ver [fase 4](../docs/fases/fase-4-integracao.md).

## 3. Ativar o Ingress Controller

O `k8s/ingress.yaml` depende do addon de Ingress do minikube (NGINX Ingress Controller):

```bash
minikube addons enable ingress
kubectl get pods -n ingress-nginx -w   # aguardar Running antes do apply
```

## 4. Subir o resto

```bash
kubectl apply -k k8s/
kubectl get pods -w
```

Além das réplicas `promotion` e do MySQL, sobe o `promotion-consumer`, que consome a fila
`promotion.order-confirmed` (`kubectl logs deploy/promotion-consumer`).

## 5. Testar

```bash
kubectl port-forward svc/promotion 8000:8000 &
curl localhost:8000/health
curl localhost:8000/health/ready

# via Ingress, por host (adicione `promotion.ganjj.local` ao /etc/hosts apontando pro IP do minikube,
# ou use `minikube tunnel` e 127.0.0.1):
curl http://promotion.ganjj.local/api/sale
curl -i http://promotion.ganjj.local/internal/discounts/calculate   # 404: não existe rota pública
curl -i http://promotion.ganjj.local/metrics                        # 404

# métricas, só de dentro (port-forward ou Prometheus):
curl localhost:8000/metrics
```

Painel do Grafana com essas métricas: [`observability/`](observability/README.md).

## 6. Provar autorrecuperação

```bash
kubectl get pods
kubectl delete pod <nome-de-um-pod-promotion>
kubectl get pods -w
```

## Derrubar tudo

```bash
kubectl delete -k k8s/
kubectl delete secret promotion-secrets
kubectl delete pvc promotion-db-data
```
