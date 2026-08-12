# Fase 0 — Fundação

**Quando:** agora, sem depender de nenhuma aula.
**Entrega:** repositório com projeto Laravel funcional, esteira de qualidade, banco no ar e primeiro commit.

## Por que esta fase existe

O repositório está vazio — sem um único commit. Nada pode ser construído em cima do nada, e a esteira
(formatador, detecção de segredo, CI) custa mais para ser adicionada depois, quando já há código para
reformatar e histórico para varrer.

Nada aqui depende de aula, de colega ou de decisão pendente. É a fase que pode acontecer inteira hoje.

## Tarefas

### 1. Scaffold do Laravel preservando o repositório

**A armadilha:** `composer create-project laravel/laravel .` dentro do diretório existente sobrescreve ou
recusa por causa de `.git` e `.claude/`. Perder o `.git` significa perder o remote configurado.

O caminho seguro é criar em pasta temporária e copiar por cima:

```bash
composer create-project laravel/laravel /tmp/laravel-tmp
rsync -a /tmp/laravel-tmp/ /Users/lucasstopinski/Downloads/GitHub/promotion/
```

Como `.git` e `.claude` não existem na origem, nada é sobrescrito.

### 2. Limpar o que o scaffold traz e não se aplica

Este serviço não tem usuários próprios, não tem sessão e não tem frontend. Some:

- Migration `0001_01_01_000000_create_users_table.php` (cria `users`, `password_reset_tokens`, `sessions`).
- `app/Models/User.php` e `database/factories/UserFactory.php`.
- `vite.config.js`, `package.json`, `resources/css/`, `resources/js/`.
- `SESSION_DRIVER=array` — stateless, sem tabela de sessão.

Ficam as migrations de `cache` e `jobs`: o Laravel usa por padrão e a fila será útil na fase de mensageria.

> Deletar aqui é mais barato que deletar depois. Uma tabela `users` órfã num serviço que não autentica ninguém
> é exatamente o tipo de coisa que gera pergunta na apresentação.

### 3. Dependências

```bash
composer require firebase/php-jwt
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint
php artisan pest:install
```

`firebase/php-jwt` porque este serviço só **verifica** token de terceiro — Sanctum e Passport resolvem emissão
e sessão, problema que é de outro serviço.

### 4. `.claude/settings.json`

O arquivo já existe com `enabledMcpjsonServers`. Adicionar `"model": "opus[1m]"` preservando o resto —
`settings.json` não é herdado de pasta ancestral, então sem isso o repositório roda em Sonnet.

### 5. `.env.example`

Só placeholders, nenhum valor real:

```env
APP_NAME="GANJJ Promotion Service"
APP_TIMEZONE=UTC
DB_CONNECTION=mysql
DB_HOST=promotion-db
DB_PORT=3306
DB_DATABASE=promotion
DB_USERNAME=promotion
DB_PASSWORD=
JWT_SECRET=
INTERNAL_SECRET=
SESSION_DRIVER=array
LOG_CHANNEL=stderr
```

`APP_TIMEZONE=UTC` é decisão de arquitetura, não default preguiçoso: vigência de cupom com fuso trocado é bug
silencioso de dinheiro. Conversão acontece na borda.

`LOG_CHANNEL=stderr` porque em container o log vai para a saída padrão, não para arquivo.

### 6. `docker-compose.yml` — só o MySQL

O Dockerfile do app é a Fase 2. Aqui basta o banco:

- Serviço `promotion-db`, imagem `mysql:8.0`, porta `3306`, volume nomeado para persistir.
- `healthcheck` com `mysqladmin ping` — necessário na Fase 2 para o app esperar o banco ficar pronto.

### 7. `lefthook.yml`

Pre-commit com dois jobs:

- `gitleaks protect --staged --no-banner` — segredo não entra no histórico. Depois que entra, rotacionar é a
  única saída.
- `./vendor/bin/pint --dirty` — formatador oficial do Laravel, só nos arquivos alterados.

### 8. `.github/workflows/ci.yml`

Roda em pull request e push na main: PHP 8.2, cache de Composer, serviço MySQL 8, e três passos — Pint em modo
`--test`, PHPStan via Larastan, e Pest.

Testes contra **MySQL real**, não SQLite: o serviço é sobre dinheiro e constraint, e SQLite não reproduz
`ENUM`, `DECIMAL` nem unicidade da mesma forma.

**Regra:** passo que não passa hoje entra com `continue-on-error: true`. CI vermelho por padrão vira CI
ignorado — e CI ignorado não protege nada.

`phpstan.neon` começa em level 5. Subir depois é fácil; começar em level 9 num projeto novo é garantir que
ninguém olhe o resultado.

### 9. `README.md`

Substituir o README padrão do Laravel: o que é o serviço, stack, como rodar local, como rodar os testes, e
ponteiro para `docs/`.

### 10. Primeiro commit e push

Branch `feat/fundacao-do-servico`, PR via `gh pr create`.

> Antes de commitar: confirmar que `.env` está no `.gitignore` e que o gitleaks passou. Um `JWT_SECRET` real no
> primeiro commit de um repositório de faculdade é fácil de fazer e chato de desfazer.

## Verificação

Cada item tem uma checagem executável — "parece certo" não conta:

| O quê | Comando | Esperado |
|---|---|---|
| Laravel de pé | `php artisan --version` | Imprime a versão |
| Rotas carregam | `php artisan route:list` | Sem erro (a limpeza do item 2 pode quebrar isso) |
| Banco conecta | `docker compose up -d && php artisan migrate` | Migrations rodam no MySQL |
| Testes rodam | `./vendor/bin/pest` | Verde, mesmo com só o teste de exemplo |
| Formatador | `./vendor/bin/pint --test` | Sem diferença pendente |
| Segredo | `gitleaks detect --no-banner` | Nenhum achado |
| Git | `git log --oneline` | Pelo menos um commit |

## Concluída quando

- [ ] `php artisan route:list` roda sem erro
- [ ] `php artisan migrate` cria as tabelas no MySQL do compose
- [ ] `./vendor/bin/pest` passa
- [ ] `lefthook.yml` e `.github/workflows/ci.yml` existem e o workflow ficou verde no PR
- [ ] `.env` **não** está no repositório; `.env.example` está
- [ ] `docs/` está commitada
- [ ] O repositório tem histórico

## Próxima

[Fase 1 — MVP do laboratório](fase-1-mvp.md), que constrói o domínio em cima desta base e fecha a exigência
Domain/Service/Controller + CRUD antes de 18/08.
