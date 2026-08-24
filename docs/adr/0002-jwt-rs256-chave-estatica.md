# ADR 0002 — Verificação de JWT via RS256 com chave pública estática

Status: Aceito
Data: 2026-08-24

## Contexto

Desde a Fase 1, `promotion` verificava token assumindo um contrato que nunca foi confirmado
com o time de Autorização: HS256 com segredo simétrico compartilhado e claims
`{id, email, isAdmin}`. O serviço de Autorização real emite RS256 (par de chaves assimétrico) e
claims `{sub, email, role}` — todo token real caía em 401 antes mesmo de considerar o valor dos
claims (registrado em `docs/alinhamento-jwt-rs256.md`).

`authorization` distribui a chave pública por `GET /auth/public-key`, que devolve o PEM em
texto puro — não existe endpoint JWKS, `kid` ou rotação de chave confirmada. Um mecanismo de
JWKS de verdade (cache, busca periódica, múltiplas chaves) seria complexidade sem
contrapartida: não há rotação pra justificar.

## Decisão

`VerifyJwt.php` decodifica RS256 direto com a chave pública lida de uma variável de ambiente
estática (`JWT_PUBLIC_KEY`), sem chamada de rede em request-time nem cache — é só um valor de
configuração, colado uma vez no deploy. O algoritmo é fixado no código (nunca lido do header
`alg` do próprio token, que é o que permite o ataque "alg: none").

Além da assinatura, o middleware confere `iss === "ganjj-authorization"` e `typ === "access"` —
mesma validação que `authorization/examples/verify_token.py` documenta como o contrato esperado
para qualquer serviço consumidor. Sem isso, um refresh token (assinado pela mesma chave, mas
sem `role`) passaria pela verificação de assinatura sem ser rejeitado explicitamente por não
ser um token de acesso.

Claims mapeadas pros nomes internos que o resto do código já usa: `sub → id`,
`role → isAdmin` (comparação exata com o literal `'ADMIN'`, já que a claim vem de um enum Java
que serializa em maiúsculas — `UserRole.name()`).

## Consequências

- Se `authorization` confirmar rotação de chave ativa no futuro, esta decisão precisa ser
  revisitada — aí sim compensaria buscar a chave via JWKS (com cache) em vez de env var
  estático.
- Redeploy manual é o único jeito de atualizar a chave hoje, se ela mudar.
- `docs/alinhamento-jwt-rs256.md` passa a `Status: ✅ resolvido — ver este ADR`.
- Nenhuma dependência nova: `firebase/php-jwt` (já instalado) faz todo o trabalho.
