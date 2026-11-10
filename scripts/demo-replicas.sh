#!/usr/bin/env bash
# Réplicas e balanceamento por serviço no EKS: 3 chamadas pelo Ingress a cada serviço e os pods atrás do Service.
# Uso: bash scripts/demo-replicas.sh [servico ...]   (sem argumentos, todos). DEMO_STEP=1 espera Enter por serviço.
# Imprime só status HTTP e nomes de pod, nunca senha ou token.
set -u
pause(){ [ -n "${DEMO_STEP:-}" ] && { read -r -p "[Enter] $1 " _; echo; }; return 0; }
ELB=$(kubectl -n ingress-nginx get svc ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
B="http://$ELB"
APASS=$(kubectl get secret authorization-credentials -o jsonpath='{.data.admin-password}' | base64 -d)
AT=$(curl -s -X POST $B/auth/login -H 'content-type: application/json' -d "{\"email\":\"admin@ganjj.com\",\"password\":\"$APASS\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin)["accessToken"])')

# servico:rota pelo gateway ("-" = sem rota externa, só os pods atrás do Service)
SERVICOS="api-gateway:/api/sale promotion:/api/sale authorization:/auth/public-key client:/clients order:/api/pedidos/meus shopping-cart:/api/cart product-api:/products support:- vector-db:- llm-provider:- embedding-reranking:-"
[ $# -gt 0 ] && SERVICOS=$(for s in "$@"; do for e in $SERVICOS; do [ "${e%%:*}" = "$s" ] && echo "$e"; done; done)

pods(){ kubectl get endpoints "$1" -o jsonpath='{range .subsets[*].addresses[*]}{.targetRef.name}{"\n"}{end}' 2>/dev/null; }

for e in $SERVICOS; do
  svc=${e%%:*}; rota=${e#*:}
  pause "$svc"
  echo "=== $svc"
  kubectl get deploy "$svc" --no-headers 2>/dev/null | awk '{print "réplicas prontas: " $2}'
  case $svc in api-gateway) hdr='x-gateway-served-by';; promotion) hdr='x-served-by';; *) hdr='^$';; esac
  if [ "$rota" != "-" ]; then
    for i in 1 2 3; do
      h=$(curl -s -o /dev/null -D - -w 'HTTP %{http_code}\n' "$B$rota" -H "Authorization: Bearer $AT" | tr -d '\r' | grep -iE "^(HTTP |$hdr)" | tr '\n' ' ')
      echo "  chamada $i: $h"
    done
  fi
  echo "  pods atrás do Service $svc:"
  pods "$svc" | sed 's/^/    /'
done
