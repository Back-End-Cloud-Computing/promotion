# Alinhamento — JWT real do serviço de Autorização

**Status:** 🟡 pendente — aguardando confirmação do time de Autorização antes de implementar.

## O problema

O `promotion` verifica JWT desde a Fase 1 assumindo um contrato que a [Fase 5](fases/fase-5-seguranca.md) ainda
lista como hipótese a confirmar. Comparando com o que o serviço de Autorização real está emitindo hoje, os dois
lados divergem em algoritmo **e** em formato de claim — não é só o segredo estar diferente:

| | `promotion` verifica hoje | Autorização emite de verdade |
|---|---|---|
| Algoritmo | HS256, segredo simétrico compartilhado (`JWT_SECRET`) | RS256, par de chaves assimétrico |
| Claims | `{id, email, isAdmin}` (`isAdmin` booleano) | `{sub, email, role}` (`role` string) |

**Impacto se isso não for ajustado:** todo token real do serviço de Autorização cai em 401 no `promotion` hoje —
`JWT::decode()` rejeita por algoritmo errado antes mesmo de olhar os claims. Nenhuma rota admin
(`campaigns`/`promotions`/`coupons`) funciona com um token de verdade.

## O que precisamos confirmar com o time de Autorização

- [ ] **URL do JWKS.** RS256 exige a chave pública pra verificar assinatura — vamos buscar via endpoint JWKS
  (não copiar um `.pem` fixo em env, pra sobreviver a rotação de chave). Falta o endereço real.
- [ ] **Confirmar os nomes exatos dos claims** — `sub`/`email`/`role` como observado, ou há variação (`roles`
  no plural, namespace tipo `https://.../role`, etc.)?
- [ ] **Confirmar os valores possíveis de `role`.** Por ora `promotion` só reconhece `role === 'admin'` como
  administrador — existe algum outro papel (`staff`, `operator`...) que também deveria ter acesso às rotas
  admin?
- [ ] **Rotação de chave.** Existe cadência prevista? `promotion` vai cachear o JWKS por 1h — se a rotação for
  mais frequente que isso, o cache precisa ser mais curto.

## Proposta técnica (pronta, aguardando os pontos acima)

Muda só `VerifyJwt.php` — `VerifyAdmin.php` e o resto do domínio não precisam saber que o formato do claim
mudou, porque a tradução fica isolada num único ponto de entrada:

```
Authorization: Bearer <token>
        │
        ▼
 busca JWKS (cache 1h) ──falha──▶ 500 "Falha ao obter chaves de verificação do token"
        │ ok
        ▼
 JWT::decode($token, $keys)  [RS256, chave por `kid`]
        │
        ├─ expirado ──────────▶ 401 "Token expirado"
        ├─ assinatura inválida ▶ 401 "Assinatura do token inválida"
        ├─ kid/formato inválido ▶ 401 "Token inválido"
        │ ok
        ▼
 mapeia: sub→id, email→email, (role === 'admin')→isAdmin
        │
        ▼
 request->attributes->set('user', [...])   ← mesma forma de hoje
```

| Arquivo | Mudança |
|---|---|
| `app/Http/Middleware/VerifyJwt.php` | Troca verificação HS256+segredo por busca+cache de JWKS e decode RS256; mapeia `sub`/`role` pros nomes internos (`id`/`isAdmin`) que o resto do código já usa |
| `config/service.php` | `jwt_secret` sai, entra `jwks_uri` |
| `.env.example` | `JWT_SECRET=` vira `JWKS_URI=` (vazio até termos a URL real) |
| `tests/Feature/AuthenticationTest.php` | Reescreve os casos de JWT pro novo formato + 3 casos novos (JWKS não configurado, inacessível, malformado) |
| `tests/Feature/PromotionTest.php`, `tests/Feature/CouponTest.php` | Helpers de token trocam de HS256 manual pro helper novo — sem mudança de asserção de negócio |

Sem dependência nova no `composer.json` — `firebase/php-jwt` (já instalado) faz o parse do JWKS, e o `Http`
facade do Laravel (Guzzle, já instalado) faz a busca.

Plano de execução completo (arquivo por arquivo, com o código da mudança) está registrado internamente e é
retomado assim que os pontos de confirmação acima fecharem.

## Fora de escopo aqui

- `order` e `shopping-cart` provavelmente têm a mesma inconsistência — não é este repo que resolve isso, mas
  vale avisar quem mantém os dois.
- `VerifyInternalSecret`/rotas `/internal` — mecanismo de segredo compartilhado separado, não relacionado a
  este contrato de JWT.

## Referências

- [Fase 5 — Segurança e observabilidade](fases/fase-5-seguranca.md) — onde este alinhamento está previsto no
  cronograma da disciplina.
- [Contrato da API](contrato-api.md) — rotas afetadas (`campaigns`, `promotions`, `coupons`).
