#!/usr/bin/env bash
# Roteiro automático da apresentação N2: mostra o PRÓXIMO comando e só roda quando você aperta Enter.
# Uso (na raiz do clone do promotion): bash scripts/roteiro-n2.sh
# Teclas: Enter executa, p pula o passo, q sai. No passo com -w, Ctrl+C encerra só o watch.
set -u
cd "$(dirname "$0")/.."

# bloco | quem fala | comando
PASSOS=(
  "1 Abertura|Lucas|kubectl get nodes"
  "1 Abertura|Lucas|kubectl get deploy,pods,svc"
  "1 Abertura|Lucas|kubectl get ingress"
  "2 Diversidade|Todos|kubectl get pods -o 'custom-columns=POD:.metadata.name,IMAGE:.spec.containers[*].image'"
  "3 Réplicas|Lucas|bash scripts/demo-replicas.sh"
  "3 Réplicas|Lucas|kubectl scale deploy/promotion --replicas=5"
  "3 Réplicas|Lucas|kubectl get pods -l app=promotion -w"
  "3 Réplicas|Lucas|kubectl scale deploy/promotion --replicas=3"
  "4 Fluxo REST e RabbitMQ|Eduardo, João Correa, João Liz, Rodrigo, Lucas|DEMO_STEP=1 bash scripts/demo-eks.sh"
)
N=${#PASSOS[@]}
trap ':' INT   # Ctrl+C mata o filho (watch) e não o roteiro

i=0
for p in "${PASSOS[@]}"; do
  i=$((i+1))
  IFS='|' read -r bloco quem cmd <<<"$p"
  prox=""
  [ $i -lt $N ] && prox="${PASSOS[$i]##*|}"
  echo
  echo "=== passo $i/$N | bloco $bloco | fala: $quem"
  echo "    PRÓXIMO: $cmd"
  [ -n "$prox" ] && echo "    depois:  $prox" || echo "    depois:  bloco 5, fechamento (sem comando)"
  read -r -p "    [Enter] executa  [p] pula  [q] sai > " k </dev/tty
  case $k in q) exit 0;; p) continue;; esac
  echo "+ $cmd"
  eval "$cmd"
done
echo
echo "=== bloco 5 | Lucas | fechamento, sem comando"
