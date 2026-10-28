# Dashboard do Grafana

[`dashboard.json`](dashboard.json) (`GANJJ / promotion`) lê as métricas `promocao_*` do `GET /metrics`. O
Prometheus e o Grafana da solução são instalados pelo
[`install.sh` do api-gateway](https://github.com/Back-End-Cloud-Computing/api-gateway/tree/main/k8s/observability),
que carrega este arquivo sozinho quando o `promotion` está clonado ao lado.

| Painel | Métrica |
|---|---|
| Requisições/s por rota | `promocao_http_requests_total` por `rota` |
| Latência p95 por rota | `promocao_http_request_duration_seconds` |
| Requisições/s por pod | `promocao_http_requests_total` por `pod`: balanceamento entre as réplicas |
| Cálculos de desconto/s | `promocao_calculos_total` |
| Cupons aplicados e recusados/s | `promocao_cupons_aplicados_total`, `promocao_cupons_recusados_total` por `motivo` |
| Respostas 5xx/s | `promocao_http_requests_total{classe="5xx"}` |

Fora do cluster: Grafana > Dashboards > Import e escolher a fonte Prometheus na variável `datasource`. Conferido
no EKS em 28/10/2026, com as duas réplicas do `promotion` aparecendo no painel por pod.
