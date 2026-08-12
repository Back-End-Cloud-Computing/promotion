# Arquitetura e plano geral

## Contexto

Disciplina **Backend: Cloud Computing** (PUCPR, 6º período, prof. Geucimar Briatore). Uma equipe de cinco
pessoas constrói o e-commerce de roupas **GANJJ** como arquitetura de microsserviços Cloud Native, um serviço
por integrante:

| Integrante | Serviço | Stack |
|---|---|---|
| Eduardo Fabri | Cliente | Java / Spring Boot + PostgreSQL |
| João Correa | Produto | Python / FastAPI + MongoDB |
| João Liz | Carrinho | Node.js / Express + Redis |
| Rodrigo Alves | Pedido | C# / ASP.NET Core + MySQL |
| **Lucas Stopinski** | **Promoção** | **PHP / Laravel + MySQL** |

Este repositório é o serviço de **Promoção**. Não há frontend — a avaliação é sobre código, banco e as técnicas
de cloud aplicadas.

## Por que este serviço existe

O projeto de referência da equipe (`ganjj-api-main`, Node + Express + PostgreSQL) trata promoção como um
detalhe do catálogo. Tudo o que existe lá é uma tabela `sale` e **um único endpoint read-only** dentro do
product-service:

```sql
CREATE TABLE sale (
    id           SERIAL PRIMARY KEY,
    produto_id   INTEGER NOT NULL REFERENCES produto(id) ON DELETE CASCADE,
    desconto_pct INTEGER NOT NULL CHECK (desconto_pct > 0 AND desconto_pct <= 100),
    categoria    VARCHAR(50) NOT NULL CHECK (categoria IN ('Superiores','Inferiores','Inverno')),
    ativo        BOOLEAN NOT NULL DEFAULT TRUE,
    criado_em    TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(produto_id)
);
```

`GET /api/sale?categoria=` faz um JOIN com produto e devolve `preco_sale` calculado on-the-fly. Não há CRUD
administrativo, não há cupom, não há vigência, não há limite de uso — e o preço com desconto nunca é
persistido no pedido.

Extrair isso para um serviço próprio e transformá-lo em um domínio de verdade é o recorte deste trabalho.

## O que a disciplina cobra

| Avaliação | Peso | Data | Rubrica |
|---|---|---|---|
| **N1** — prática individual | 60% | 06/10 | Contêineres 3,0 · Integração REST/mensageria 4,0 · Cluster Kubernetes 3,0 |
| **N2** — apresentação em equipe | 40% | 10/11 | Arquitetura em K8s 4,0 · Segurança e observabilidade 3,0 · Apresentação técnica 3,0 |

Requisitos declarados pelo professor: sem frontend; um microsserviço por integrante; pelo menos duas linguagens
distintas na equipe; pelo menos dois bancos distintos; mais um microsserviço de suporte (e-mail/SMS/WhatsApp);
API Gateway na frente.

O laboratório mínimo da Aula 02 pede: recurso principal com camadas **Domain/Service/Controller**, CRUD
completo (GET/POST/PUT/DELETE) e código versionado.

**A consequência prática:** o CRUD é a menor parte do trabalho. A maior parte da nota individual está em
Docker, Kubernetes e integração — por isso as fases 2, 3 e 4 existem e têm data.

## Decisões de arquitetura

### Stack: PHP 8.2 + Laravel 12 + MySQL 8

A divisão original da equipe atribuía Go + SQLite a este serviço. A escolha foi trocada por PHP + Laravel +
MySQL.

O material do professor lista MySQL explicitamente entre os bancos aceitos e **não menciona SQLite em momento
algum** — a troca de banco aproxima o projeto do material, não o contrário. A equipe continua cumprindo os dois
requisitos coletivos com folga: cinco linguagens distintas (Java, Python, Node, C#, PHP) e quatro bancos
distintos (PostgreSQL, MongoDB, Redis, MySQL), mesmo com o serviço de Pedido também em MySQL.

**Risco registrado:** PHP/Laravel não aparece na lista de linguagens dos slides. A lista não se apresenta como
fechada, mas confirmar com o professor é uma pendência aberta.

### Escopo: três recursos

O serviço gerencia **promoções por produto** (espelha `sale`), **cupons de desconto por código** e **campanhas
com vigência**.

### Isolamento: nenhuma dependência de colega

O serviço é autocontido. Faz CRUD completo dos próprios recursos, calcula desconto sozinho e publica o contrato
em OpenAPI para que Carrinho e Pedido consumam quando estiverem prontos. **Se ninguém da equipe entregar nada,
este serviço ainda é entregável e avaliável** — o que importa quando 60% da nota é individual.

A única exceção inevitável é o contrato do evento de mensageria na Fase 4, que exige combinar nome de fila e
payload com o serviço de Pedido.

## Modelo de dados

Três tabelas separadas, não um modelo unificado com discriminador.

Promoção-por-produto e cupom têm gatilhos diferentes — uma incide automaticamente sobre um item, a outra é
resgatada por código sobre o total — e ciclos de vida diferentes. Unificar exigiria colunas nuláveis mutuamente
exclusivas (`codigo` nulo em promoção, `produto_id` nulo em cupom) mais uma coluna de tipo para desempatar.
Isso é mais código para ler e testar, não menos. Se um quarto tipo de desconto aparecer, cria-se a quarta
tabela — generalizar antes de precisar é apostar em um futuro que pode não vir.

### `campanhas` — vigência e agrupamento

```
id · nome · descricao (null) · inicia_em DATETIME · termina_em DATETIME
ativo BOOL default true · timestamps
```

### `promocoes` — desconto automático por produto

```
id
produto_id UNSIGNED BIGINT        -- referência lógica, sem FK
campanha_id (null, FK campanhas nullOnDelete)
desconto_pct TINYINT UNSIGNED     -- 1..100
categoria ENUM('Superiores','Inferiores','Inverno')
ativo BOOL default true · timestamps
UNIQUE(produto_id)
```

### `cupons` — desconto por código resgatável

```
id
codigo VARCHAR(32) UNIQUE         -- normalizado em maiúsculo pela aplicação
tipo ENUM('percentual','fixo')
valor DECIMAL(10,2)               -- 1..100 se percentual; > 0 se fixo
valor_minimo DECIMAL(10,2) default 0
limite_uso UNSIGNED INT (null = ilimitado)
usos UNSIGNED INT default 0
campanha_id (null, FK campanhas nullOnDelete)
ativo BOOL default true · timestamps
```

### As decisões que importam

**Sem foreign key para `produto_id`.** No repo base, `sale.produto_id` referenciava `produto(id)` porque as
duas tabelas viviam no mesmo PostgreSQL. Aqui o catálogo vive no MongoDB de outro serviço — uma FK atravessando
banco e serviço é impossível tecnicamente e proibida pelo estilo arquitetural. `produto_id` é um inteiro
validado por formato; a existência do produto é responsabilidade do serviço de Produto.

**A vigência mora só em `campanhas`.** Uma promoção ou cupom sem campanha vale enquanto estiver `ativo`. Isso
evita repetir `inicia_em`/`termina_em` em três tabelas e ter três lugares para errar a mesma comparação de
data.

**`DECIMAL`, nunca `float`.** É dinheiro. `TINYINT` no percentual porque 1..100 cabe em um byte.

**`usos` é contador na própria linha**, incrementado com a trava no próprio `WHERE`:

```sql
UPDATE cupons SET usos = usos + 1
WHERE id = ? AND (limite_uso IS NULL OR usos < limite_uso)
```

Se o `UPDATE` afeta zero linhas, o limite estourou. Isso resolve corrida sem `SELECT ... FOR UPDATE` e sem
lock de aplicação. Uma tabela de histórico de resgate por cliente é a evolução natural, mas não entra agora —
nada a consome ainda.

**Datas em UTC no banco**, conversão apenas na borda. Vigência com fuso trocado é um bug silencioso que só
aparece quando um cupom expira uma hora antes ou depois do combinado.

**`codigo` normalizado em maiúsculo por um mutator do Model**, não pela collation do banco. MySQL 8 usa
`utf8mb4_0900_ai_ci`, que é case-insensitive; SQLite é case-sensitive. Depender da collation faz o teste de
unicidade passar em um e falhar no outro. Normalizar na aplicação torna a garantia idêntica em qualquer banco.

## Camadas Domain / Service / Controller

A disciplina exige as três camadas visíveis. Em Laravel isso mapeia na convenção do framework, sem inventar
camada extra:

```
app/
  Models/                        Domain — Eloquent, casts, scopes de vigência
    Campanha.php
    Promocao.php
    Cupom.php
  Services/
    CalculadoraDesconto.php      Domain/Service — a regra de dinheiro, pura, sem HTTP nem banco
    CupomService.php             Service — casos de uso com transação (consumo de cupom)
  Http/
    Controllers/Api/             Controller — só HTTP: recebe, delega, responde
    Requests/                    Validação declarativa na borda
    Resources/                   Shape do JSON de resposta
    Middleware/
      VerificaJwt.php
      VerificaAdmin.php
      VerificaSegredoInterno.php
```

**Sem camada Repository.** O Eloquent já é a abstração de persistência; um repositório por cima seria uma
interface com uma implementação só — a definição de abstração especulativa. Se o professor cobrar Repository
explicitamente, entra depois; é aditivo e não quebra nada.

**Nem todo recurso precisa de Service.** Campanha e promoção são CRUD quase puro — Controller + Model resolvem.
Só cupom e cálculo têm regra de negócio de verdade. A exigência acadêmica é que as três camadas existam e
sejam demonstráveis no projeto, não que cada recurso tenha as três.

`CalculadoraDesconto` é o coração do serviço: recebe itens e um cupom opcional, devolve o detalhamento. É pura
— sem banco, sem HTTP — o que a torna trivial de testar. É exatamente onde incidem os testes obrigatórios de
dinheiro.

## Autenticação

O JWT é emitido pelo serviço de Cliente (Eduardo). Este serviço **apenas verifica a assinatura** com o
`JWT_SECRET` compartilhado — mesmo padrão do repo base, onde cada serviço tem sua cópia do `jwt.js` em vez de
chamar o auth-service por HTTP a cada requisição.

Biblioteca: `firebase/php-jwt`. **Não** Sanctum nem Passport — eles resolvem emissão de token e gestão de
sessão, que são problema de outro serviço. Aqui só é preciso verificar um token de terceiro.

Payload esperado: `{id, email, isAdmin}`, aceito via header `Authorization: Bearer` ou cookie `accessToken`.

## Convenções herdadas do repo base

Mantidas para que os cinco serviços falem a mesma língua:

- Domínio em **português** (promocao, cupom, campanha, produto, pedido).
- Erro sempre no formato `{"error": "mensagem"}`. Sem envelope, sem paginação.
- Rotas serviço-a-serviço sob `/internal/*`, protegidas por header `x-internal-secret`, fora do API Gateway.
- Dinheiro em `DECIMAL(10,2)`.

A única fricção: o Laravel devolve erro de validação como `{"message": ..., "errors": {...}}`. Sobrescrever
`failedValidation()` nos FormRequests achata isso em `{"error": "..."}`. É o único ponto onde a convenção da
equipe custa código extra.

## Estratégia de teste

**Pest**, que roda sobre PHPUnit com menos cerimônia.

Testes de `CalculadoraDesconto` são unitários puros — sem banco, sem migration, instantâneos. É onde mora a
maior parte dos casos de regra de negócio.

Testes de feature (rotas, unicidade, contador de uso) rodam contra **MySQL em container**, não SQLite em
memória. SQLite mascara justamente o que precisa ser verificado aqui: não tem `ENUM` real, trata `DECIMAL` com
afinidade numérica frouxa, e é permissivo com constraint. Testar dinheiro em SQLite e rodar em produção no
MySQL é o tipo de diferença que passa verde no CI e quebra na apresentação. O container do MySQL já existe para
o desenvolvimento — usar o mesmo no teste não custa nada.

Ver [regras-de-negocio.md](regras-de-negocio.md) para a lista completa de casos.

## O que não é testado

CRUD puro, formatação de Resource e rotas triviais. YAGNI vale para teste também: o que se testa é dinheiro,
vigência, limite e parsing — o resto é boilerplate do framework, e o framework já é testado.

## Pontos onde a exigência acadêmica força complexidade

Registrado para honestidade técnica — em um projeto de produção pequeno, estas escolhas seriam outras:

1. **Três camadas explícitas mesmo em recursos de CRUD puro.** Campanha e promoção não precisariam de
   FormRequest e Resource dedicados; ganham para tornar a camada visível na avaliação.
2. **MySQL como carga de trabalho no Kubernetes.** Em produção real seria um banco gerenciado, e não haveria
   manifest de banco nenhum.
3. **OpenAPI formal em um serviço isolado.** Justificável aqui porque é contrato entre cinco pessoas, mas um
   serviço solo não precisaria.
4. **Contrato de evento RabbitMQ combinado com outro integrante**, que arranha a promessa de isolamento total —
   é a única forma de pontuar a parte de mensageria. O REST síncrono existe como caminho de fallback caso o
   RabbitMQ não saia a tempo.
