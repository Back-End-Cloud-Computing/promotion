#!/usr/bin/env bash
# Sync the 11 GANJJ repos, re-check the facts behind integration-issues.md, report what changed
# in their issues, PRs and commits since the last run, optionally boot everything and hit health
# endpoints. Only reads/pulls the other 10 repos — never writes to them, never pushes, never
# comments. promotion is the only repo this script (or anyone using it) commits to.
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ORG_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
ORG="Back-End-Cloud-Computing"
REPOS=(authorization client order product promotion shopping-cart llm-provider embedding-reranking vector-db api-gateway support)
# Cursor of the last issues/PRs report; first run (no file) looks back DEFAULT_DAYS.
STATE_FILE="${XDG_CACHE_HOME:-$HOME/.cache}/ganjj-check/last-run"
DEFAULT_DAYS=3
DIVERGENCES=()

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
  if [ -f "$order_auth" ] && grep -q "RsaSecurityKey" "$order_auth"; then
    echo "- INT-001 (order): migrado pra RS256 — doc alinhada"
  elif [ -f "$order_auth" ] && grep -q "SymmetricSecurityKey" "$order_auth"; then
    echo "- INT-001 (order): ainda HS256 — doc desatualizada"
  else
    echo "- INT-001 (order): arquivo não encontrado, checar path"
  fi

  cart_jwt="$ORG_DIR/shopping-cart/utils/jwt.js"
  if [ -f "$cart_jwt" ] && grep -q "RS256" "$cart_jwt"; then
    echo "- INT-001 (shopping-cart): migrado pra RS256 — doc alinhada"
  elif [ -f "$cart_jwt" ] && grep -q "JWT_SECRET" "$cart_jwt"; then
    echo "- INT-001 (shopping-cart): ainda HS256 — doc desatualizada"
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

iso_days_ago() {
  date -u -v-"$1"d +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || date -u -d "$1 days ago" +%Y-%m-%dT%H:%M:%SZ
}

# Sets WINDOW_FROM (start of the report) and RUN_START (stamped at the start so nothing that
# changes while the script runs is lost). SAVE_CURSOR=1 only when the window came from the
# cursor or the default, so an ad-hoc --since/--days look never moves it.
resolve_window() {
  RUN_START=$(date -u +%Y-%m-%dT%H:%M:%SZ)
  SAVE_CURSOR=0
  if [ -n "$OPT_SINCE" ]; then
    WINDOW_FROM="$OPT_SINCE"
  elif [ -n "$OPT_DAYS" ]; then
    WINDOW_FROM=$(iso_days_ago "$OPT_DAYS")
  elif [ -f "$STATE_FILE" ] && grep -qE '^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9:]{8}Z$' "$STATE_FILE"; then
    WINDOW_FROM=$(cat "$STATE_FILE"); SAVE_CURSOR=1
  else
    WINDOW_FROM=$(iso_days_ago "$DEFAULT_DAYS"); SAVE_CURSOR=1
  fi
  [ "$NO_SAVE" = 1 ] && SAVE_CURSOR=0
}

save_cursor() {
  [ "$SAVE_CURSOR" = 1 ] || return 0
  mkdir -p "$(dirname "$STATE_FILE")" && echo "$RUN_START" > "$STATE_FILE"
}

print_window() {
  echo "Janela: $WINDOW_FROM até $RUN_START"
}

# Issues, PRs, comments and commits that changed in the window, for every repo. Only reads the
# GitHub API. Anything that looks off (issue closed by hand, commit straight to the default
# branch, red CI) goes into DIVERGENCES and is printed again at the end.
report_issues() {
  echo "## Issues, PRs e commits"
  print_window
  command -v gh >/dev/null 2>&1 || { echo "- ERRO: gh não instalado"; echo ""; return; }
  local active
  active=$(gh api user --jq .login 2>/dev/null)
  [ "$active" = "LucasStop" ] || echo "- AVISO: conta ativa do gh é '${active:-desconhecida}', não LucasStop (só leitura, mas confira)"

  local repo
  for repo in "${REPOS[@]}"; do
    local open_count
    open_count=$(gh issue list -R "$ORG/$repo" --state open --limit 300 --json number --jq length 2>/dev/null)
    echo ""
    echo "### $repo — ${open_count:-?} issue(s) aberta(s)"

    # Issues (the issues endpoint also returns PRs; those are filtered out here).
    local issues
    issues=$(gh api "repos/$ORG/$repo/issues?state=all&since=$WINDOW_FROM&sort=updated&direction=desc&per_page=100" --paginate 2>/dev/null | jq -s 'add // []')
    if [ -z "$issues" ]; then echo "- ERRO ao ler issues"; continue; fi
    echo "$issues" | jq -r --arg since "$WINDOW_FROM" '.[] | select(.pull_request == null)
      | (if .created_at >= $since then "NOVA"
         elif .state == "closed" and (.closed_at // "") >= $since then "FECHADA"
         else "ATUALIZADA" end) as $tag
      | "- #\(.number) \($tag)\(if .state == "closed" then " (\(.state_reason // "?"))" else "" end) — \(.title)"
        + (if (.assignees | length) > 0 then " [\(.assignees | map(.login) | join(","))]" else "" end)
        + (if (.issue_dependencies_summary.blocked_by // 0) > 0 then " BLOQUEADA por \(.issue_dependencies_summary.blocked_by) aberta(s)" else "" end)'

    # Closed as completed in the window: by a PR or a commit, or by hand? (not_planned is a
    # deliberate manual close, so it is not flagged.)
    local n manual="" who=""
    for n in $(echo "$issues" | jq -r --arg since "$WINDOW_FROM" '.[] | select(.pull_request == null and .state == "closed" and .state_reason != "not_planned" and (.closed_at // "") >= $since) | .number'); do
      local linked
      linked=$(gh api "repos/$ORG/$repo/issues/$n/timeline" --paginate 2>/dev/null | jq -s '[add[]? | select((.event == "cross-referenced" and (.source.issue.pull_request != null)) or (.event == "referenced" and .commit_id != null) or (.event == "closed" and .commit_id != null))] | length')
      if [ "${linked:-0}" = "0" ]; then
        manual="$manual #$n"
        who=$(gh api "repos/$ORG/$repo/issues/$n/events" --paginate 2>/dev/null | jq -rs '[add[]? | select(.event == "closed")] | last | .actor.login // "?"')
      fi
    done
    [ -n "$manual" ] && DIVERGENCES+=("$repo: fechada(s) sem PR nem commit referenciando:$manual (por $who)")

    # Comments in the window (issues and PRs).
    gh api "repos/$ORG/$repo/issues/comments?since=$WINDOW_FROM&per_page=100" --paginate 2>/dev/null | jq -rs --arg since "$WINDOW_FROM" '
      add[]? | select(.created_at >= $since)
      | "- comentário de \(.user.login) em #\(.issue_url | split("/") | last): \(.body | gsub("\\s+"; " ") | .[0:110])"'

    # PRs touched in the window.
    gh pr list -R "$ORG/$repo" --state all --search "updated:>=$WINDOW_FROM" --limit 50 \
      --json number,title,state,isDraft,reviewDecision,statusCheckRollup,author \
      --jq '.[] | (.statusCheckRollup // []) as $c
        | (if ($c | length) == 0 then "sem CI"
           elif any($c[]; .conclusion == "FAILURE" or .conclusion == "TIMED_OUT" or .conclusion == "CANCELLED" or .state == "FAILURE" or .state == "ERROR") then "CI FALHOU"
           elif any($c[]; (.status != null and .status != "COMPLETED") or .state == "PENDING") then "CI pendente"
           else "CI verde" end) as $ci
        | "- PR #\(.number) \(if .isDraft then "RASCUNHO" else .state end) — \(.title) (\(.author.login)) \($ci)\(if .reviewDecision != "" and .reviewDecision != null then ", \(.reviewDecision)" else "" end)"' 2>/dev/null

    # Commits on the default branch in the window; the ones without a PR are flagged.
    local shas
    shas=$(gh api "repos/$ORG/$repo/commits?since=$WINDOW_FROM&per_page=100" 2>/dev/null | jq -r '.[] | select((.parents | length) == 1) | .sha')
    local total
    total=$(echo "$shas" | grep -c . || true)
    if [ "$total" -gt 40 ]; then
      echo "- $total commits na janela, não verifiquei PR por commit"
    elif [ "$total" -gt 0 ]; then
      local sha direct=0 viaPr=0
      for sha in $shas; do
        if [ "$(gh api "repos/$ORG/$repo/commits/$sha/pulls" --jq length 2>/dev/null)" = "0" ]; then
          direct=$((direct + 1))
          echo "- commit direto na main: ${sha:0:7} $(gh api "repos/$ORG/$repo/commits/$sha" --jq '"\(.commit.author.name) — \(.commit.message | split("\n")[0])"' 2>/dev/null)"
        else
          viaPr=$((viaPr + 1))
        fi
      done
      echo "- commits: $viaPr via PR, $direct direto(s) na main"
      [ "$direct" -gt 0 ] && DIVERGENCES+=("$repo: $direct commit(s) direto(s) na main, sem PR")
    fi
  done

  echo ""
  echo "## Divergências a revisar"
  if [ "${#DIVERGENCES[@]}" -eq 0 ]; then
    echo "- nenhuma"
  else
    local d
    for d in "${DIVERGENCES[@]}"; do echo "- $d"; done
  fi
  echo ""
  save_cursor
}

# Boots the 9 services with the same runtime overrides validated in the geralzão session
# (nothing here is committed to any repo — env vars and one compose override file only).
run_integration() {
  echo "## Subindo os 9 serviços (integração real)"
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
  AUTH_PORT=$(cd "$ORG_DIR/authorization" && docker compose port authorization 8081 2>/dev/null | awk -F: '{print $NF}')
  AUTH_PORT="${AUTH_PORT:-8081}"
  wait_ok "http://localhost:${AUTH_PORT}/auth/public-key" 240 && echo "- authorization: PASS (porta :${AUTH_PORT})" || echo "- authorization: FAIL (ver /tmp/gc-authorization.log)"

  ( cd "$ORG_DIR/client" && AUTH_URL="http://localhost:${AUTH_PORT}" ./scripts/fetch-public-key.sh ) >/dev/null 2>&1
  ( cd "$ORG_DIR/client" && docker compose up -d --build ) >/tmp/gc-client.log 2>&1
  wait_ok "http://localhost:8082/health" 90 && echo "- client: PASS" || echo "- client: FAIL (ver /tmp/gc-client.log)"

  [ -f "$ORG_DIR/product/.env" ] || cp "$ORG_DIR/product/.env.example" "$ORG_DIR/product/.env"
  ( cd "$ORG_DIR/product" && docker compose up -d --build ) >/tmp/gc-product.log 2>&1
  wait_ok "http://localhost:8000/health" 150 && echo "- product: PASS" || echo "- product: FAIL (ver /tmp/gc-product.log)"

  ( cd "$ORG_DIR/promotion" && docker compose up -d --build ) >/tmp/gc-promotion.log 2>&1
  PROMO_PORT=$(cd "$ORG_DIR/promotion" && docker compose port promotion-app 8000 2>/dev/null | awk -F: '{print $NF}')
  PROMO_PORT="${PROMO_PORT:-8005}"
  wait_ok "http://localhost:${PROMO_PORT}/health" 90 && echo "- promotion: PASS (porta :${PROMO_PORT})" || echo "- promotion: FAIL (ver /tmp/gc-promotion.log)"

  ( cd "$ORG_DIR/shopping-cart" && docker compose up -d --build ) >/tmp/gc-shopping-cart.log 2>&1
  wait_ok "http://localhost:3003/health" 90 && echo "- shopping-cart: PASS" || echo "- shopping-cart: FAIL (ver /tmp/gc-shopping-cart.log)"

  ( cd "$ORG_DIR/order" && docker compose up -d --build ) >/tmp/gc-order.log 2>&1
  wait_ok "http://localhost:3004/health/live" 150 && echo "- order: PASS" || echo "- order: FAIL (ver /tmp/gc-order.log)"

  [ -f "$ORG_DIR/vector-db/.env" ] || cp "$ORG_DIR/vector-db/.env.example" "$ORG_DIR/vector-db/.env"
  ( cd "$ORG_DIR/vector-db" && docker compose up -d --build ) >/tmp/gc-vector-db.log 2>&1
  wait_ok "http://localhost:8002/health" 60 && echo "- vector-db: PASS" || echo "- vector-db: FAIL (ver /tmp/gc-vector-db.log)"

  [ -f "$ORG_DIR/embedding-reranking/.env" ] || cp "$ORG_DIR/embedding-reranking/.env.example" "$ORG_DIR/embedding-reranking/.env"
  ( cd "$ORG_DIR/embedding-reranking" && docker compose up -d --build ) >/tmp/gc-embedding.log 2>&1
  wait_ok "http://localhost:8003/health" 60 && echo "- embedding-reranking: PASS" || echo "- embedding-reranking: FAIL (ver /tmp/gc-embedding.log)"

  [ -f "$ORG_DIR/llm-provider/.env" ] || cp "$ORG_DIR/llm-provider/.env.example" "$ORG_DIR/llm-provider/.env"
  ( cd "$ORG_DIR/llm-provider" && docker compose up -d --build ) >/tmp/gc-llm.log 2>&1
  wait_ok "http://localhost:8004/health" 60 && echo "- llm-provider: PASS" || echo "- llm-provider: FAIL (ver /tmp/gc-llm.log)"
  echo ""
}

usage() {
  echo "Uso: scripts/ganjj-check.sh [--sync|--docs|--issues|--window|--up|--all] [--since AAAA-MM-DD] [--days N] [--no-save]"
  echo "  --sync     só git fetch/pull nos 11 repos"
  echo "  --docs     só re-checa os fatos por trás de docs/integration-issues.md"
  echo "  --issues   issues, PRs, comentários e commits que mudaram na janela, nos 11 repos"
  echo "  --window   só imprime a janela de tempo (para o passo de e-mail do comando)"
  echo "  --up       sync + docs + sobe os 9 serviços e testa health (pesado, builda imagem)"
  echo "  --all      sync + docs + issues (padrão, sem subir nada)"
  echo "Janela: desde a última execução (guardada em $STATE_FILE); na primeira vez, $DEFAULT_DAYS dias."
  echo "  --since / --days  janela avulsa, não move o marcador. --no-save  não move o marcador."
}

MODE="--all"; OPT_SINCE=""; OPT_DAYS=""; NO_SAVE=0
while [ $# -gt 0 ]; do
  case "$1" in
    --sync|--docs|--issues|--window|--up|--all) MODE="$1" ;;
    --since)
      [ -n "${2:-}" ] || { usage; exit 1; }
      case "$2" in
        [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]) OPT_SINCE="${2}T00:00:00Z" ;;
        *) echo "--since espera AAAA-MM-DD"; exit 1 ;;
      esac
      shift ;;
    --days)
      case "${2:-}" in ''|*[!0-9]*) echo "--days espera um número"; exit 1 ;; esac
      OPT_DAYS="$2"; shift ;;
    --no-save) NO_SAVE=1 ;;
    -h|--help) usage; exit 0 ;;
    *) usage; exit 1 ;;
  esac
  shift
done

resolve_window
case "$MODE" in
  --sync) sync_repos ;;
  --docs) check_docs ;;
  --issues) report_issues ;;
  --window) print_window ;;
  --up) sync_repos; check_docs; run_integration ;;
  --all) sync_repos; check_docs; report_issues ;;
esac
