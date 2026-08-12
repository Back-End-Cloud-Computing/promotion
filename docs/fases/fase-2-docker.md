# Fase 2 — Docker

**Quando:** após a aula de 18/08.
**Vale:** 3,0 pontos na N1 (R1 — implementação de microsserviços com contêineres).
**Entrega:** imagem do serviço, compose completo, health checks e testes rodando em container.

## Por que esta fase existe

Três pontos da nota individual dependem só disto. E é pré-requisito real da Fase 3: sem imagem, não há o que
o Kubernetes orquestre.

## Tarefas

### 1. `Dockerfile`

Duas opções, e a escolha é deliberada:

| | Complexidade | Quando |
|---|---|---|
| `php:8.2-cli` + `artisan serve` | Baixa | Padrão desta fase |
| Multi-stage PHP-FPM + Nginx | Alta | Só se o professor exigir |

A rubrica avalia **conteinerização**, não tuning de servidor de aplicação. Um container CLI servindo a
aplicação satisfaz o critério e é muito mais rápido de escrever e depurar. O Ingress da Fase 3 fará o papel do
Nginx de qualquer forma.

```dockerfile
FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql opcache
WORKDIR /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader
COPY . .
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

Copiar `composer.json`/`composer.lock` antes do resto faz o Docker reusar a camada de dependências quando só o
código muda — a diferença entre rebuild de segundos e de minutos, dezenas de vezes por dia.

### 2. `.dockerignore`

`vendor/`, `.env`, `.git/`, `node_modules/`, `tests/`.

> `.env` no `.dockerignore` não é detalhe de tamanho: sem ele, credenciais de desenvolvimento vão para dentro
> da imagem e viajam junto em qualquer push de registry.

`bootstrap/cache/*.php` entra na lista por um motivo específico: são artefatos locais, no `.gitignore` mas
**não** no `.dockerignore` original — e Docker não lê `.gitignore`, só `.dockerignore`. Sem essa linha, o cache
de descoberta de pacotes gerado no host (com `laravel/pail` e outras dev deps) vazava para dentro da imagem
`--no-dev`, e `php artisan` quebrava em runtime com `Class "Laravel\Pail\PailServiceProvider" not found` — um
provider listado no cache que o `composer install --no-dev` nunca instalou. Encontrado ao rodar
`docker run --rm promotion php artisan --version` como fumaça antes de subir o compose.

### 3. Entrypoint com migrations

`php artisan migrate --force` antes de subir o servidor. `--force` porque em ambiente não interativo o Laravel
pede confirmação e trava o container.

O entrypoint precisa **esperar o banco**. O `healthcheck` do compose (Fase 0) mais `depends_on: condition:
service_healthy` resolvem sem script de retry.

### 4. `docker compose` completo

O compose da Fase 0 só tinha MySQL. Agora ganha o serviço da aplicação, com `depends_on` no healthcheck do
banco e variáveis vindas do `.env`.

### 5. Health checks

Duas rotas, sem autenticação, alvos das probes do Kubernetes na próxima fase:

| Rota | Papel | Responde |
|---|---|---|
| `GET /health` | Liveness | 200 se o processo está vivo |
| `GET /health/ready` | Readiness | 200 se `DB::connection()->getPdo()` funciona; 503 se não |

A distinção é o que impede o Kubernetes de reiniciar um pod saudável só porque o banco está lento — liveness
que testa dependência externa causa reinício em cascata.

Custam poucas linhas e entram aqui porque a Fase 3 depende delas.

### 6. Logs para stdout

`LOG_CHANNEL=stderr`. Container não tem quem rotacione arquivo de log; quem coleta é o runtime.

### 7. Testes contra MySQL do container

Rodar a suíte contra o MySQL real, não SQLite. É o que valida as regras R12 (unicidade) e R13 (concorrência),
que SQLite não reproduz fielmente.

## Verificação

| O quê | Como | Esperado |
|---|---|---|
| Build limpo | `docker build -t promotion .` | Sucesso, sem `.env` na imagem |
| Stack sobe | `docker compose up -d` | App e banco ficam healthy |
| Migrations | `docker compose logs promotion-app` | Rodaram no boot |
| Liveness | `curl localhost:8000/health` | 200 |
| Readiness | `curl localhost:8000/health/ready` | 200 |
| Readiness honesta | `docker compose stop promotion-db && curl .../health/ready` | **503**, não 200 |
| Testes | `./vendor/bin/pest` do host, apontando para o MySQL do compose | Verde contra MySQL |
| Sem segredo na imagem | `docker run --rm promotion sh -c "test -f .env"` | Falha — arquivo não existe |

> A imagem é `--no-dev` de propósito (é a que vai pra produção/Kubernetes) — Pest não existe dentro dela. A
> suíte roda do host ou do CI contra o MySQL exposto pelo compose, não `docker compose exec`.

A checagem da readiness com o banco derrubado é a que importa: uma readiness que responde 200 sempre é pior
que não ter readiness, porque o Kubernetes vai mandar tráfego para um pod que não consegue atender.

## Concluída quando

- [x] `docker compose up -d` sobe tudo do zero em máquina sem PHP instalado
- [x] Migrations rodam automaticamente
- [x] `/health` e `/health/ready` respondem, e a readiness falha quando o banco cai
- [x] Suíte passa contra o MySQL do container (42 testes, 85 assertions)
- [x] Imagem não contém `.env` nem `.git`
- [x] CI builda a imagem

## Próxima

[Fase 3 — Kubernetes](fase-3-kubernetes.md), após a aula de 25/08. Mais 3,0 pontos da N1.
