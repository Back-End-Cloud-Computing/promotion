# Fase 1 — MVP do laboratório

**Quando:** antes de 18/08 (não depende de aula nenhuma).
**Entrega:** microsserviço funcional e testável por Postman — domínio completo, CRUD dos três recursos e o
cálculo de desconto coberto por testes.
**Fecha:** a exigência do laboratório da Aula 02 (Domain/Service/Controller + CRUD completo + versionado).

## Por que esta fase existe

É o serviço propriamente dito. Tudo que vem depois — Docker, Kubernetes, mensageria — é sobre *como entregar*
este código. Se esta fase não existir, não há o que conteinerizar.

Também é a fase que roda inteira sem depender de ninguém: nenhum colega precisa estar pronto, nenhuma aula
precisa ter acontecido.

## Ordem de execução

A ordem importa. O cálculo de desconto vem **antes** do CRUD, porque é a regra de negócio de dinheiro e é
escrito com teste primeiro.

### 1. Migrations e Models

Três tabelas conforme [arquitetura.md](../arquitetura.md#modelo-de-dados): `campanhas`, `promocoes`, `cupons`.

```bash
php artisan make:model Campanha -m
php artisan make:model Promocao -m
php artisan make:model Cupom -m
```

Pontos que não são detalhe:

- `produto_id` **sem foreign key** — o catálogo vive no MongoDB de outro serviço. Comentar o porquê na
  migration, senão parece esquecimento.
- Dinheiro em `decimal(10,2)`, com cast `'decimal:2'` no Model. Nunca `float`.
- `codigo` do cupom normalizado em maiúsculo por mutator no Model, **não** por collation do banco.
- Scopes de vigência nos Models (`scopeVigente`, `scopeAtivo`) — é onde a comparação de data mora, em um lugar
  só.

### 2. `CalculadoraDesconto` — com teste primeiro

O coração do serviço. Classe pura em `app/Services/CalculadoraDesconto.php`: recebe itens e cupom opcional,
devolve o detalhamento. Sem banco, sem HTTP, sem Eloquent.

Escrever `tests/Unit/CalculadoraDescontoTest.php` **antes** da implementação, cobrindo os casos R1–R11 e
R14–R16 de [regras-de-negocio.md](../regras-de-negocio.md).

> Por que teste primeiro aqui e não no resto: é cálculo de dinheiro. Um erro de arredondamento não estoura —
> ele cobra R$ 0,03 a mais de cada cliente e ninguém percebe. O teste é a única forma de saber que está certo.

Ser pura é o que torna esta classe testável em milissegundos. Se ela precisar consultar o banco para saber a
promoção de um produto, o teste vira lento e frágil — então quem busca os dados é o Controller, e a
calculadora só recebe.

### 3. CRUD dos três recursos

```bash
php artisan make:controller Api/PromocaoController --api
php artisan make:controller Api/CupomController --api
php artisan make:controller Api/CampanhaController --api
php artisan make:request StorePromocaoRequest      # e Update*, para os três
php artisan make:resource PromocaoResource         # idem
```

Controllers finos: recebem FormRequest validado, chamam Model ou Service, devolvem Resource. Sem SQL, sem
regra de negócio.

Campanha e promoção são CRUD quase puro — Controller + Model bastam. Só cupom ganha `CupomService`, porque o
consumo de uso tem transação e trava de concorrência.

**Sobrescrever `failedValidation()`** nos FormRequests para achatar o erro do Laravel em `{"error": "..."}`,
mantendo a convenção da equipe. Um trait compartilhado resolve para todos de uma vez.

### 4. Middlewares

| Middleware | O quê |
|---|---|
| `VerificaJwt` | Decodifica `Authorization: Bearer` ou cookie `accessToken` com `firebase/php-jwt` |
| `VerificaAdmin` | 403 se `isAdmin` não for true |
| `VerificaSegredoInterno` | Compara header `x-internal-secret`; 403 se não bater |

O JWT é apenas verificado, nunca emitido — quem emite é o serviço de Cliente.

### 5. Endpoints

Conforme [contrato-api.md](../contrato-api.md):

- `GET /api/sale?categoria=` — público, compatível com o repo base
- `GET /api/cupons/{codigo}/validar?subtotal=` — público, não consome uso
- CRUD admin dos três recursos
- `POST /internal/descontos/calcular` — o endpoint que Carrinho e Pedido vão consumir
- `POST /internal/cupons/{codigo}/consumir` — incremento atômico, chamado só pelo Pedido

### 6. Seeders

Dados de demonstração para exercitar a API no Postman sem cadastrar tudo à mão: algumas promoções, uma
campanha vigente, cupons em estados variados (válido, expirado, esgotado, inativo).

> Os cupons em estado inválido não são enfeite — são o que permite demonstrar as regras de recusa na
> apresentação sem precisar mexer no relógio do sistema.

### 7. Coleção do Postman/Insomnia

Versionada no repositório. A disciplina exige teste por ferramenta de requisição, e uma coleção pronta é a
diferença entre demonstrar em dois minutos e digitar JSON na frente da turma.

## Verificação

| O quê | Como | Esperado |
|---|---|---|
| Regra de negócio | `./vendor/bin/pest` | Os casos R1–R16 passam |
| CRUD | Coleção do Postman | Os cinco verbos respondem nos três recursos |
| Compatibilidade | `GET /api/sale?categoria=Inverno` | Mesmo shape do repo base |
| Cálculo interno | `curl -H "x-internal-secret: ..." -d @exemplo.json .../internal/descontos/calcular` | Detalhamento bate com a conta feita à mão |
| Sem segredo | Mesma chamada sem o header | 403 |
| Unicidade | Criar dois cupons com o mesmo código | 409 |
| Concorrência | Duas chamadas simultâneas no último uso | Uma 200, uma 409 |

O teste de conferir o cálculo **à mão** parece bobo e não é: é o único que valida que a regra implementada é a
regra pretendida, e não uma implementação internamente consistente do erro.

## Concluída quando

- [ ] `./vendor/bin/pest` verde com os 16 casos de regra de negócio
- [ ] Os três recursos respondem aos cinco verbos
- [ ] `POST /internal/descontos/calcular` devolve detalhamento correto
- [ ] Cupom inválido devolve 200 com motivo, não 4xx
- [ ] Rotas `/internal` recusam sem `x-internal-secret`
- [ ] Coleção do Postman versionada
- [ ] Seeders populam um cenário demonstrável

## Riscos

**Arredondamento de ponto flutuante.** `round()` nativo do PHP resolve o caso aqui (duas casas, sem cascata de
operações). Se um teste mostrar erro de precisão, promover para `bcmath` — que já vem com o PHP. Não instalar
biblioteca de dinheiro antes de um teste provar que precisa.

**Ordem de aplicação do desconto.** Promoção no item, cupom no subtotal. Fixar no código e no teste, senão a
resposta muda conforme quem implementa.

## Próxima

[Fase 2 — Docker](fase-2-docker.md), após a aula de 18/08. Vale 3,0 pontos na N1.
