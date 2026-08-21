# Convenções

## Commits

Formato [Conventional Commits](https://www.conventionalcommits.org/pt-br/):

```
tipo(escopo): resumo breve

Corpo opcional, explicando o porquê da mudança — não o que ela faz,
que o diff já mostra.
```

Regras do resumo: imperativo, minúsculo, sem ponto final, até 72 caracteres.
O escopo é opcional; o tipo, não.

### Tipos

| Tipo | Quando |
|---|---|
| `feat` | Nova funcionalidade |
| `fix` | Correção de defeito |
| `refactor` | Muda a estrutura sem mudar o comportamento |
| `perf` | Melhora desempenho |
| `test` | Adiciona ou corrige teste |
| `docs` | Só documentação |
| `build` | Dependências, Composer, Dockerfile |
| `ci` | Workflow, lefthook, pipeline |
| `chore` | Manutenção que não entra nas anteriores |
| `style` | Formatação, sem efeito em comportamento |
| `revert` | Desfaz um commit anterior |

### Escopos

Domínio: `campaign` · `promotion` · `coupon` · `discount`
Transversal: `api` · `db` · `auth` · `health`
Infra: `docker` · `k8s` · `rabbitmq` · `deps`

### Exemplos

```
feat(coupon): valida limite de uso e valor mínimo do carrinho
fix(discount): arredonda por item antes de somar o subtotal
test(discount): cobre promoção e cupom aplicados na mesma compra
refactor(api): achata erro de validação no formato {"error": ...}
build(deps): adiciona firebase/php-jwt para verificar token
ci: publica cobertura de testes no pipeline
docs: descreve o contrato do endpoint interno de cálculo
```

Uma mudança que quebra contrato leva `!` antes dos dois-pontos:

```
feat(coupon)!: renomeia usage_limit para max_uses
```

### Por que o corpo importa

O diff mostra o que mudou. O corpo existe para o que o diff não conta: por que a
mudança foi necessária, que alternativa foi descartada, que armadilha o próximo
leitor evita. Commit de uma linha basta quando a mudança é óbvia — e a maioria é.

### Validação automática

O hook `commit-msg` do lefthook recusa mensagem fora do padrão. Para pular
pontualmente: `LEFTHOOK=0 git commit`.

## Branches

`feat/nome-descritivo` ou `fix/nome-descritivo`, em inglês, kebab-case.

## Pull Requests

Título no mesmo formato do commit. PR via `gh pr create`.

> A N1 de DevOps (14–18/09) avalia versionamento, criação de Pull Requests e
> execução de pipeline de CI. Trabalhar por PR desde agora, mesmo sozinho no
> repositório, constrói o histórico que essa avaliação pede.

## Idioma

| O quê | Idioma |
|---|---|
| Commits, branches, PRs | Inglês |
| Documentação e comentários | Português |
| Identificadores de código (classes, colunas, rotas, campos de JSON) | Inglês (`Coupon`, `discount_percentage`, `/api/coupons`) |
| Valores do enum `category` | Português (`Superiores`, `Inferiores`, `Inverno`) |
| Mensagens de erro e texto de negócio devolvido na API | Português (`"Cupom expirado"`) |

Os identificadores de código eram em português (espelhando o repo base da equipe) até a reorganização em
`app/Domain/<Module>` — nessa migração, código virou inglês. Duas exceções deliberadas continuam em
português: os valores do enum `category`, porque espelham a categorização real do catálogo do serviço de
Produto (outro microsserviço) e traduzir só o nosso lado quebraria o casamento em runtime; e as mensagens de
erro/negócio devolvidas na API, que são texto para humano lerem, não identificador. `GET /api/sale` também
foge à regra por completo — mantém rota e payload em português de propósito, para bater com o contrato do
projeto de referência da equipe que este endpoint espelha.
