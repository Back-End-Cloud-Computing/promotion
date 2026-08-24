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
exclusivas (`code` nulo em promoção, `product_id` nulo em cupom) mais uma coluna de tipo para desempatar.
Isso é mais código para ler e testar, não menos. Se um quarto tipo de desconto aparecer, cria-se a quarta
tabela — generalizar antes de precisar é apostar em um futuro que pode não vir.

Nomes de tabela e coluna em inglês, como o resto dos identificadores do serviço (ver adendo de tradução em
[docs/convencoes.md](convencoes.md)). Exceção: os valores do enum `category` (`Superiores`/`Inferiores`/
`Inverno`) ficam em português — espelham a categorização real do catálogo do serviço de Produto, não um nome
nosso, e traduzir só o nosso lado quebraria o casamento silenciosamente.

### `campaigns` — vigência e agrupamento

```
id · name · description (null) · starts_at DATETIME · ends_at DATETIME
active BOOL default true · timestamps
```

### `promotions` — desconto automático por produto

```
id
product_id UNSIGNED BIGINT        -- referência lógica, sem FK
campaign_id (null, FK campaigns nullOnDelete)
discount_percentage TINYINT UNSIGNED     -- 1..100
category ENUM('Superiores','Inferiores','Inverno')
active BOOL default true · timestamps
UNIQUE(product_id)
```

### `coupons` — desconto por código resgatável

```
id
code VARCHAR(32) UNIQUE          -- normalizado em maiúsculo pela aplicação
type ENUM('percentage','fixed')
value DECIMAL(10,2)              -- 1..100 se percentage; > 0 se fixed
minimum_value DECIMAL(10,2) default 0
usage_limit UNSIGNED INT (null = ilimitado)
usage_count UNSIGNED INT default 0
campaign_id (null, FK campaigns nullOnDelete)
active BOOL default true · timestamps
```

### As decisões que importam

**Sem foreign key para `product_id`.** No repo base, `sale.produto_id` referenciava `produto(id)` porque as
duas tabelas viviam no mesmo PostgreSQL. Aqui o catálogo vive no MongoDB de outro serviço — uma FK atravessando
banco e serviço é impossível tecnicamente e proibida pelo estilo arquitetural. `product_id` é um inteiro
validado por formato; a existência do produto é responsabilidade do serviço de Produto.

**A vigência mora só em `campaigns`.** Uma promoção ou cupom sem campanha vale enquanto estiver `active`. Isso
evita repetir `starts_at`/`ends_at` em três tabelas e ter três lugares para errar a mesma comparação de
data.

**`DECIMAL`, nunca `float`.** É dinheiro. `TINYINT` no percentual porque 1..100 cabe em um byte.

**`usage_count` é contador na própria linha**, incrementado com a trava no próprio `WHERE`:

```sql
UPDATE coupons SET usage_count = usage_count + 1
WHERE id = ? AND (usage_limit IS NULL OR usage_count < usage_limit)
```

Se o `UPDATE` afeta zero linhas, o limite estourou. Isso resolve corrida sem `SELECT ... FOR UPDATE` e sem
lock de aplicação. Uma tabela de histórico de resgate por cliente é a evolução natural, mas não entra agora —
nada a consome ainda.

**Datas em UTC no banco**, conversão apenas na borda. Vigência com fuso trocado é um bug silencioso que só
aparece quando um cupom expira uma hora antes ou depois do combinado.

**`code` normalizado em maiúsculo por um mutator do Model**, não pela collation do banco. MySQL 8 usa
`utf8mb4_0900_ai_ci`, que é case-insensitive; SQLite é case-sensitive. Depender da collation faz o teste de
unicidade passar em um e falhar no outro. Normalizar na aplicação torna a garantia idêntica em qualquer banco.

## Camadas Domain / Service / Controller

A disciplina exige as três camadas visíveis. Em vez de agrupar por camada técnica (`Models/`, `Services/`,
`Http/`), o projeto agrupa por **módulo de negócio** em `app/Domain/<Module>/` — cada módulo carrega suas
próprias sub-pastas técnicas por dentro. Mesmo raciocínio de camadas do parágrafo abaixo, outra localização
física: mais fácil mexer num domínio inteiro (campanha, cupom, promoção, cálculo de desconto) sem varrer o
projeto todo.

```
app/
  Domain/
    Campaigns/
      Entities/Campaign.php            Domain + Repository — Eloquent (Active Record), casts, scopes
      Controllers/CampaignController.php
      Requests/                        Validação declarativa na borda
      Resources/                       Shape do JSON de resposta
      routes/api.php
    Promotions/
      Entities/Promotion.php
      Enums/ProductCategory.php        Valores em português — categoria real do serviço de Produto
      Controllers/{PromotionController,SaleController}.php
      Requests/ Resources/ routes/
    Coupons/
      Entities/Coupon.php
      Enums/CouponType.php
      Exceptions/DuplicateCouponCodeException.php
      Services/CouponService.php       Service — casos de uso com transação (consumo de cupom)
      Controllers/CouponController.php
      Requests/ Resources/ routes/
    Discounts/
      Services/DiscountCalculator.php  Domain/Service — a regra de dinheiro, pura, sem HTTP nem banco
      Controllers/DiscountController.php
      routes/                          api.php (validate) e internal.php (calculate/consume)
  Http/
    Controllers/Controller.php         Base abstrata — cross-cutting, sem módulo dono
    Requests/Concerns/RespondsWithSimpleError.php
    Middleware/
      VerifyJwt.php
      VerifyAdmin.php
      VerifyInternalSecret.php
```

`routes/api.php` e `routes/internal.php` na raiz viram arquivos finos que só dão `require` no arquivo de rotas
de cada módulo — o `Route::middleware('api')->prefix('api')->group(...)` que o `withRouting()` do Laravel monta
em cima do arquivo raiz é herdado por um `require` aninhado, sem precisar de um `RouteServiceProvider` por
módulo.

### Sobre a camada Repository

A Aula 02 define o fluxo `Controller → Service → Repository → Domain → Banco`. Este projeto tem as quatro
camadas — mas Domain e Repository ocupam o mesmo arquivo (a Entity), e isso é consequência do framework, não
descuido.

O slide pressupõe **Spring Boot com JPA**, que implementa o padrão **Data Mapper**: a entidade é um objeto
burro e um `Repository` separado sabe como carregá-la e gravá-la. São necessariamente duas classes.

O Eloquent do Laravel implementa **Active Record**, o padrão oposto: o próprio Model carrega o mapeamento e
as operações de persistência. `Coupon::where('code', $code)->first()` já *é* a chamada de repositório —
`Coupon` é a entidade de domínio e o ponto de acesso a dados ao mesmo tempo.

Envolver isso em um `CouponRepository` que apenas delega para o Eloquent produziria uma classe sem
comportamento próprio, com uma única implementação, cuja única função seria parecer com Java. A camada não
ganharia nada além de um arquivo a mais entre o Service e o dado.

Onde o acesso a dados tem lógica de verdade — filtros compostos, a query de vigência, o incremento atômico do
contador de uso — ela vive em **scopes** da Entity (`scopeValid`) e em `CouponService`. São os lugares que um
Repository ocuparia; o nome é que difere.

> Esta é a resposta para "cadê o Repository?" na apresentação: ele existe, fundido ao Domain, porque Active
> Record e Data Mapper resolvem o mesmo problema com desenhos diferentes. Trocar de framework trocaria a
> resposta.

**Nem todo módulo precisa de Service.** Campaign e Promotion são CRUD quase puro — Controller + Entity
resolvem. Só Coupon e Discount têm regra de negócio de verdade. A exigência acadêmica é que as três camadas
existam e sejam demonstráveis no projeto, não que cada módulo tenha as três.

`DiscountCalculator` é o coração do serviço: recebe itens e um cupom opcional, devolve o detalhamento. É pura
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

Testes de `DiscountCalculator` são unitários puros — sem banco, sem migration, instantâneos. É onde mora a
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
