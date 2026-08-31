---
description: Sincroniza os 9 repos GANJJ, re-checa integração e realinha docs/integration-issues.md
argument-hint: "[--sync|--docs|--up|--all]"
---

Rode `scripts/ganjj-check.sh ${ARGUMENTS:---all}` a partir da raiz do repo `promotion`.

Depois de ver a saída:

1. Para cada repo que o script marcou `SUJO` ou `DIVERGIU`: reporte, não tente resolver sozinho
   (git reset/rebase nesses repos é proibido — ver memória `no-edits-outside-promotion`).
2. Para cada linha `DRIFT` ou `RESOLVIDO` na seção de alinhamento com docs: isso significa que
   `docs/integration-issues.md` está desatualizado em relação ao código real dos outros repos.
   Investigue a mudança (git log/diff no repo em questão, só leitura) e proponha a atualização do
   trecho correspondente do doc — mas só edite `docs/integration-issues.md` depois de entender o
   que de fato mudou, nunca só porque o script sinalizou.
3. Se `--up` foi usado, resuma o resultado por serviço (PASS/FAIL) e não deixe os containers no
   ar sem avisar o usuário.
4. Nunca edite, comite ou dê push em nenhum repo que não seja `promotion`. Nunca comite/dê push
   em `promotion` sem confirmar antes com o usuário.
