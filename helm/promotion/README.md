# Chart Helm — `promotion`

Mesmo desenho do [`k8s/`](../../k8s/README.md): Deployment com migration em initContainer, Service, Ingress por lista de
permissão (`/api` e `/docs`), ConfigMap e MySQL com PVC. Os valores que mudam entre ambientes estão em
[`values.yaml`](values.yaml).

O Secret continua fora do chart (nenhum `kind: Secret` no git). Crie `promotion-secrets` como no passo 2 do
[`k8s/README.md`](../../k8s/README.md) e passe outro nome com `--set existingSecret=...` se precisar.

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

Ainda não testado em cluster: `helm lint` e `helm template` passam e a saída tem os mesmos recursos do `k8s/`.
Se a turma definir outro padrão na aula de 27/10, o chart se ajusta a ele.
