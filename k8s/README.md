# Kubernetes — `promotion`

Ver [docs/fases/fase-3-kubernetes.md](../docs/fases/fase-3-kubernetes.md) para o porquê de cada decisão.

## 1. Build da imagem dentro do minikube

O cluster não enxerga o Docker do host por padrão:

```bash
eval $(minikube docker-env)
docker build -t promotion:local .
```

Repetir sempre que o código mudar — o Deployment usa `imagePullPolicy: IfNotPresent`, então uma imagem já
carregada não é rebuildada sozinha.

## 2. Criar o Secret

Sem template versionado — nem com placeholder. `kind: Secret` no git é o tipo de coisa que alguém edita local
pra testar, esquece de reverter e o placeholder vira valor real no histórico. As chaves que o Deployment espera
encontrar em `promotion-secrets`: `APP_KEY`, `DB_PASSWORD`, `INTERNAL_SECRET`.

```bash
kubectl create secret generic promotion-secrets \
  --from-literal=APP_KEY="$(docker run --rm promotion:local php artisan key:generate --show)" \
  --from-literal=DB_PASSWORD=promotion \
  --from-literal=INTERNAL_SECRET=troque-por-um-segredo-de-verdade
```

Precisa rodar de novo sempre que o cluster for recriado (`minikube delete`) — o Secret não é gerenciado pelo
kustomize, só o resto (`kubectl apply -k k8s/`).

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

## 5. Testar

```bash
kubectl port-forward svc/promotion 8000:8000 &
curl localhost:8000/health
curl localhost:8000/health/ready

# via Ingress, sem port-forward:
curl http://$(minikube ip)/health
# se o IP do minikube não for alcançável direto do host, use `minikube tunnel`
# em outro terminal e troque pra `curl localhost/health`
```

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
