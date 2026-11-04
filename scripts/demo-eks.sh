#!/usr/bin/env bash
# Fluxo da demonstração no EKS (ensaio de 03/11): imprime só status HTTP, ids e totais, nunca senha ou token.
# Uso: bash scripts/demo-eks.sh (kubeconfig do cluster ativo).
set -u
ELB=$(kubectl -n ingress-nginx get svc ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')
B="http://$ELB"
st(){ echo "$1 -> $2"; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
echo "ELB resolve: $(curl -s -o /dev/null -w '%{http_code}' --max-time 10 $B/health 2>&1)"
kubectl exec deploy/promotion -c promotion -- php artisan db:seed --force >/dev/null 2>&1; st "seed promotion" $?
APASS=$(kubectl get secret authorization-credentials -o jsonpath='{.data.admin-password}' | base64 -d)
AT=$(curl -s -X POST $B/auth/login -H 'content-type: application/json' -d "{\"email\":\"admin@ganjj.com\",\"password\":\"$APASS\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin)["accessToken"])')
st "login ADMIN" $([ -n "$AT" ] && echo ok || echo FALHOU)
CEMAIL="cliente$(date +%s)@ganjj.com"; CPASS="Aa1-$(openssl rand -hex 8)"
st "register CLIENTE" $(code -X POST $B/auth/register -H 'content-type: application/json' -d "{\"email\":\"$CEMAIL\",\"password\":\"$CPASS\"}")
CT=$(curl -s -X POST $B/auth/login -H 'content-type: application/json' -d "{\"email\":\"$CEMAIL\",\"password\":\"$CPASS\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin)["accessToken"])')
st "login CLIENTE" $([ -n "$CT" ] && echo ok || echo FALHOU)
st "JWT: CLIENTE em rota admin /api/campaigns (esperado 403)" $(code $B/api/campaigns -H "Authorization: Bearer $CT")
st "JWT: ADMIN em /api/campaigns (esperado 200)" $(code $B/api/campaigns -H "Authorization: Bearer $AT")
st "JWT: sem token (esperado 401)" $(code $B/api/campaigns)
PID=$(curl -s --max-time 90 -X POST $B/products -H "Authorization: Bearer $AT" -H 'content-type: application/json' -d '{"name":"Produto Ensaio","sku":"ENS-'$(date +%s)'","sale_type":"eletronico","brand":"GANJJ","category":"ensaio","price":100,"stock":10,"generate_description_with_ai":false,"description":"Produto do ensaio N2"}' | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d.get("id") or d.get("_id") or d)')
st "POST /products id" "$PID"
st "cart add" $(code -X POST $B/api/cart/items -H "Authorization: Bearer $CT" -H 'content-type: application/json' -d "{\"product_id\":\"$PID\",\"quantity\":2}")
st "cart PUT coupon BEMVINDO10" $(code -X PUT $B/api/cart/coupon -H "Authorization: Bearer $CT" -H 'content-type: application/json' -d '{"code":"BEMVINDO10"}')
echo "cart: $(curl -s $B/api/cart -H "Authorization: Bearer $CT" | python3 -c 'import sys,json;d=json.load(sys.stdin);print({k:d.get(k) for k in ("subtotal","total","coupon","discount_total")})')"
echo "usage_count antes: $(kubectl exec deploy/promotion -c promotion -- php artisan tinker --execute='echo \App\Domain\Coupons\Entities\Coupon::where("code","BEMVINDO10")->value("usage_count");' 2>/dev/null | tail -1)"
IK=$(uuidgen)
R=$(curl -s -o /tmp/ord.json -w '%{http_code}' -X POST $B/api/pedidos -H "Authorization: Bearer $CT" -H "Idempotency-Key: $IK" -H 'content-type: application/json' -d '{"endereco_entrega":{"rua":"Rua das Flores","numero":"123","complemento":"Apto 42","bairro":"Centro","cidade":"Curitiba","estado":"PR","cep":"80000-000","pais":"BR"}}')
st "POST /api/pedidos" $R
python3 -c 'import json;d=json.load(open("/tmp/ord.json"));print({k:d.get(k) for k in ("id","status","total","numero")})'
st "POST /api/pedidos mesma Idempotency-Key" $(code -X POST $B/api/pedidos -H "Authorization: Bearer $CT" -H "Idempotency-Key: $IK" -H 'content-type: application/json' -d '{"endereco_entrega":{"rua":"Rua das Flores","numero":"123","complemento":"Apto 42","bairro":"Centro","cidade":"Curitiba","estado":"PR","cep":"80000-000","pais":"BR"}}')
sleep 8
kubectl exec deploy/rabbitmq -- rabbitmqctl list_queues name messages consumers 2>/dev/null
echo "consumer log:"; kubectl logs deploy/promotion-consumer --tail=5 2>/dev/null | cut -c1-260
echo "usage_count depois: $(kubectl exec deploy/promotion -c promotion -- php artisan tinker --execute='echo \App\Domain\Coupons\Entities\Coupon::where("code","BEMVINDO10")->value("usage_count");' 2>/dev/null | tail -1)"
rm -f /tmp/ord.json
