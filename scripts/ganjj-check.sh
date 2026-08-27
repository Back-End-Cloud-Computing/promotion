#!/usr/bin/env bash
# Sync the 9 GANJJ repos, re-check the facts behind integration-issues.md, optionally boot
# everything and hit health endpoints. Only reads/pulls the other 8 repos — never writes to
# them, never pushes. promotion is the only repo this script (or anyone using it) commits to.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ORG_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
REPOS=(authorization client order product promotion shopping-cart llm-provider embedding-reranking vector-db)

sync_repos() {
  echo "## Sync (git fetch + pull --ff-only)"
  for repo in "${REPOS[@]}"; do
    local_dir="$ORG_DIR/$repo"
    [ -d "$local_dir/.git" ] || { echo "- $repo: SKIP (não clonado)"; continue; }
    ( cd "$local_dir" && git fetch --quiet 2>/dev/null )
    branch=$(cd "$local_dir" && git rev-parse --abbrev-ref HEAD)
    dirty=$(cd "$local_dir" && git status --porcelain)
    behind=$(cd "$local_dir" && git rev-list --count "HEAD..@{u}" 2>/dev/null || echo 0)
    ahead=$(cd "$local_dir" && git rev-list --count "@{u}..HEAD" 2>/dev/null || echo 0)

    if [ -n "$dirty" ]; then
      echo "- $repo: SUJO — mudanças locais não commitadas, não mexi ($branch)"
    elif [ "$behind" -gt 0 ]; then
      if (cd "$local_dir" && git pull --ff-only --quiet) 2>/dev/null; then
        echo "- $repo: ATUALIZADO — $behind commit(s) novo(s) puxado(s) ($branch)"
      else
        echo "- $repo: DIVERGIU — não deu ff-only, olhar manual ($branch)"
      fi
    elif [ "$ahead" -gt 0 ]; then
      echo "- $repo: à FRENTE do remoto em $ahead commit(s), nada puxado ($branch)"
    else
      echo "- $repo: em dia ($branch)"
    fi
  done
  echo ""
}

# Re-checks the code-level claims behind each INT-* entry in integration-issues.md.
# Only greps — never edits the doc. A DRIFT line means the doc is probably stale.
check_docs() {
  echo "## Alinhamento com docs/integration-issues.md"

  # INT-001 — order/shopping-cart ainda em HS256?
  order_auth="$ORG_DIR/order/src/Ganjj.Order.Api/Authentication/AuthenticationExtensions.cs"
  if [ -f "$order_auth" ] && grep -q "SymmetricSecurityKey" "$order_auth"; then
    echo "- INT-001 (order): ainda HS256 — doc segue válida"
  elif [ -f "$order_auth" ]; then
    echo "- INT-001 (order): DRIFT — SymmetricSecurityKey sumiu de AuthenticationExtensions.cs, checar se migrou pra RS256"
  else
    echo "- INT-001 (order): arquivo não encontrado, checar path"
  fi

  cart_jwt="$ORG_DIR/shopping-cart/utils/jwt.js"
  if [ -f "$cart_jwt" ] && grep -q "JWT_SECRET" "$cart_jwt"; then
    echo "- INT-001 (shopping-cart): ainda HS256 — doc segue válida"
  elif [ -f "$cart_jwt" ]; then
    echo "- INT-001 (shopping-cart): DRIFT — JWT_SECRET sumiu de utils/jwt.js, checar se migrou pra RS256"
  else
    echo "- INT-001 (shopping-cart): arquivo não encontrado, checar path"
  fi

  # INT-009 — porta do product ainda desalinhada?
  product_port=$(grep -oE '"[0-9]+:8000"' "$ORG_DIR/product/docker-compose.yml" 2>/dev/null | head -1 | grep -oE '^"[0-9]+' | tr -d '"')
  for consumer in order shopping-cart; do
    env_file="$ORG_DIR/$consumer/.env.example"
    [ -f "$env_file" ] || { echo "- INT-009 ($consumer): .env.example não encontrado"; continue; }
    consumer_port=$(grep -oE 'PRODUCT_SERVICE_URL=.*:[0-9]+' "$env_file" | grep -oE '[0-9]+$')
    if [ -z "$product_port" ] || [ -z "$consumer_port" ]; then
      echo "- INT-009 ($consumer): não consegui extrair porta, checar manual"
    elif [ "$product_port" = "$consumer_port" ]; then
      echo "- INT-009 ($consumer): RESOLVIDO — PRODUCT_SERVICE_URL já aponta pra :$product_port, atualizar doc"
    else
      echo "- INT-009 ($consumer): ainda desalinhado — .env.example usa :$consumer_port, product publica :$product_port"
    fi
  done
  echo ""
}

# Boots the 9 services with the same runtime overrides validated in the geralzão session
# (nothing here is committed to any repo — env vars and one compose override file only).
run_integration() {
  echo "## Subindo os 9 serviços (integração real)"
  OVERRIDE="/tmp/ganjj-promotion-port-override.yml"
  wait_ok() {
    local url="$1" max="$2" i=0
    while [ $i -lt "$max" ]; do
      [ "$(curl -s -o /dev/null -w '%{http_code}' "$url" 2>/dev/null)" = "200" ] && return 0
      sleep 5; i=$((i+5))
    done
    return 1
  }

  docker network create ganjj-net >/dev/null 2>&1

  ( cd "$ORG_DIR/authorization" && [ -f keys/private.pem ] || ./scripts/generate-keys.sh ) >/dev/null 2>&1
  ( cd "$ORG_DIR/authorization" && docker compose up -d --build ) >/tmp/gc-authorization.log 2>&1
  wait_ok "http://localhost:8081/auth/public-key" 240 && echo "- authorization: PASS" || echo "- authorization: FAIL (ver /tmp/gc-authorization.log)"

  ( cd "$ORG_DIR/client" && ./scripts/fetch-public-key.sh ) >/dev/null 2>&1
  ( cd "$ORG_DIR/client" && docker compose up -d --build ) >/tmp/gc-client.log 2>&1
  wait_ok "http://localhost:8082/health" 90 && echo "- client: PASS" || echo "- client: FAIL (ver /tmp/gc-client.log)"

  [ -f "$ORG_DIR/product/.env" ] || cp "$ORG_DIR/product/.env.example" "$ORG_DIR/product/.env"
  ( cd "$ORG_DIR/product" && docker compose up -d --build ) >/tmp/gc-product.log 2>&1
  wait_ok "http://localhost:8000/health" 150 && echo "- product: PASS" || echo "- product: FAIL (ver /tmp/gc-product.log)"

  cat > "$OVERRIDE" <<'EOF'
services:
  promotion-app:
    ports: !override
      - "8005:8000"
EOF
  ( cd "$ORG_DIR/promotion" && docker compose -f docker-compose.yml -f docker-compose.override.yml -f "$OVERRIDE" up -d --build ) >/tmp/gc-promotion.log 2>&1
  wait_ok "http://localhost:8005/health" 90 && echo "- promotion: PASS (remapeado pra :8005 só neste teste)" || echo "- promotion: FAIL (ver /tmp/gc-promotion.log)"

  export PRODUCT_SERVICE_URL="http://host.docker.internal:8000"
  ( cd "$ORG_DIR/shopping-cart" && docker compose up -d --build ) >/tmp/gc-shopping-cart.log 2>&1
  wait_ok "http://localhost:3003/health" 90 && echo "- shopping-cart: PASS" || echo "- shopping-cart: FAIL (ver /tmp/gc-shopping-cart.log)"

  ( cd "$ORG_DIR/order" && docker compose up -d --build ) >/tmp/gc-order.log 2>&1
  wait_ok "http://localhost:3004/health/live" 150 && echo "- order: PASS" || echo "- order: FAIL (ver /tmp/gc-order.log)"
  unset PRODUCT_SERVICE_URL

  VDB_ENV="/tmp/ganjj-vector-db.env"
  cp "$ORG_DIR/vector-db/.env.example" "$VDB_ENV"
  sed -i '' 's#CHROMA_HOST=chromadb#CHROMA_HOST=host.docker.internal#; s#CHROMA_PORT=8000#CHROMA_PORT=8001#' "$VDB_ENV"
  ( cd "$ORG_DIR/vector-db" && docker build -q -t ganjj-vector-db . ) >/tmp/gc-vector-db-build.log 2>&1
  docker rm -f ganjj-vector-db >/dev/null 2>&1
  ( cd "$ORG_DIR/vector-db" && docker run -d --env-file "$VDB_ENV" -p 8002:8002 --name ganjj-vector-db ganjj-vector-db ) >/tmp/gc-vector-db-run.log 2>&1
  wait_ok "http://localhost:8002/health" 60 && echo "- vector-db: PASS" || echo "- vector-db: FAIL (ver /tmp/gc-vector-db-run.log)"

  ER_ENV="/tmp/ganjj-embedding-reranking.env"
  cp "$ORG_DIR/embedding-reranking/.env.example" "$ER_ENV"
  sed -i '' 's#VECTOR_DB_BASE_URL=http://vector-db:8002#VECTOR_DB_BASE_URL=http://host.docker.internal:8002#' "$ER_ENV"
  ( cd "$ORG_DIR/embedding-reranking" && docker build -q -t ganjj-embedding-reranking . ) >/tmp/gc-embedding-build.log 2>&1
  docker rm -f ganjj-embedding-reranking >/dev/null 2>&1
  ( cd "$ORG_DIR/embedding-reranking" && docker run -d --env-file "$ER_ENV" -p 8003:8003 --name ganjj-embedding-reranking ganjj-embedding-reranking ) >/tmp/gc-embedding-run.log 2>&1
  wait_ok "http://localhost:8003/health" 60 && echo "- embedding-reranking: PASS" || echo "- embedding-reranking: FAIL (ver /tmp/gc-embedding-run.log)"

  [ -f "$ORG_DIR/llm-provider/.env" ] || cp "$ORG_DIR/llm-provider/.env.example" "$ORG_DIR/llm-provider/.env"
  ( cd "$ORG_DIR/llm-provider" && docker build -q -t ganjj-llm-provider . ) >/tmp/gc-llm-build.log 2>&1
  docker rm -f ganjj-llm-provider >/dev/null 2>&1
  ( cd "$ORG_DIR/llm-provider" && docker run -d --env-file "$ORG_DIR/llm-provider/.env" -p 8004:8004 --name ganjj-llm-provider ganjj-llm-provider ) >/tmp/gc-llm-run.log 2>&1
  wait_ok "http://localhost:8004/health" 60 && echo "- llm-provider: PASS" || echo "- llm-provider: FAIL (ver /tmp/gc-llm-run.log)"
  echo ""
}

usage() {
  echo "Uso: scripts/ganjj-check.sh [--sync|--docs|--up|--all]"
  echo "  --sync   só git fetch/pull nos 9 repos"
  echo "  --docs   só re-checa os fatos por trás de docs/integration-issues.md"
  echo "  --up     sync + docs + sobe os 9 serviços e testa health (pesado, builda imagem)"
  echo "  --all    sync + docs (padrão, sem subir nada)"
}

case "${1:---all}" in
  --sync) sync_repos ;;
  --docs) check_docs ;;
  --up) sync_repos; check_docs; run_integration ;;
  --all) sync_repos; check_docs ;;
  -h|--help) usage ;;
  *) usage; exit 1 ;;
esac
