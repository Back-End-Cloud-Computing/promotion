# Chart Helm — `promotion`

Mesmo desenho do [`k8s/`](../../k8s/README.md): Deployment com migration em initContainer, Service, Ingress por lista de
permissão (`/api` e `/docs`), ConfigMap, MySQL com PVC e o consumidor de `OrderConfirmed` (`promotion-consumer`). Os valores que mudam entre ambientes estão em
[`values.yaml`](values.yaml).

O Secret continua fora do chart (nenhum `kind: Secret` no git). Crie `promotion-secrets` como no passo 2 do
[`k8s/README.md`](../../k8s/README.md) e passe outro nome com `--set existingSecret=...` se precisar.
O consumidor lê usuário e senha do broker do Secret `rabbitmq-credentials`, criado pelo `support`.

```bash
helm lint helm/promotion
helm upgrade --install promotion helm/promotion
kubectl get pods -w
helm uninstall promotion                       # o PVC do MySQL permanece
```

Ajustes comuns:

| Quero | Faço |
|---|---|
| Outra versão da imagem | `--set image.tag=1.0.1` |
| Mais réplicas | `--set replicaCount=3` |
| Outro host do Ingress | `--set ingress.host=promotion.exemplo.local` |
| Banco externo | `--set mysql.enabled=false --set config.DB_HOST=<host>` |
| Sem Ingress | `--set ingress.enabled=false` |
| Sem RabbitMQ no cluster | `--set consumer.enabled=false` |

Testado em 21/10/2026 num Minikube limpo (v1.37): `helm lint` limpo, `helm install` com o `values.yaml` padrão
(2 réplicas, `existingSecret`) sobe 2 pods `1/1` e o MySQL, com a imagem puxada do Docker Hub. Pelo Ingress,
`/api/sale` responde 200 e `/internal`, `/metrics` e `/health/ready` dão 404: o Ingress só roteia `/api` e `/docs`,
e as probes do Kubernetes chamam o pod direto, sem passar por ele.
Se a turma definir outro padrão na aula de 27/10, o chart se ajusta a ele.

Em 28/10/2026 o chart foi alinhado ao `k8s/` da imagem 1.2.0 (consumidor, `RABBITMQ_HOST` e `RABBITMQ_PORT`):
`helm template` contra `kubectl kustomize k8s/` dá os mesmos 8 objetos, fora a anotação `checksum/config`.
