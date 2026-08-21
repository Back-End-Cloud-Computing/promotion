# Contrato da API

Referência para quem vai **consumir** este serviço: Carrinho, Pedido e o API Gateway.

A fonte de verdade em runtime é a spec OpenAPI gerada a partir do código (`/docs/api` e `/docs/api.json`).
Este documento existe para leitura humana e para permitir que os colegas integrem antes de o serviço estar no
ar.

## Convenções

- Rotas e payload em **inglês**, seguindo a reorganização do serviço em módulos (`app/Domain/<Module>`).
  Exceção: os valores do enum `category` (`Superiores`/`Inferiores`/`Inverno`) ficam em português — espelham a
  categorização real do catálogo do serviço de Produto (outro microsserviço), não um nome nosso.
- Erro sempre `{"error": "mensagem"}`. Sem envelope de sucesso, sem paginação.
- Dinheiro em string decimal com duas casas (`"49.90"`), nunca float no JSON.
- Datas em ISO 8601.

### Status codes

| Código | Quando |
|---|---|
| 200 | Leitura ou cálculo bem-sucedido |
| 201 | Recurso criado |
| 204 | Removido |
| 401 | Token ausente ou inválido |
| 403 | Sem permissão de admin, ou `x-internal-secret` errado |
| 404 | Recurso não encontrado |
| 409 | Conflito — código de cupom duplicado, produto já em promoção |
| 422 | Erro de validação de formato |
| 500 | Erro interno |

### Autenticação

| Escopo | Como |
|---|---|
| Público | Nada |
| Admin | JWT via `Authorization: Bearer <token>` ou cookie `accessToken`, com `isAdmin: true` no payload |
| Interno | Header `x-internal-secret`, fora do API Gateway |

---

## Público

### `GET /api/sale`

Compatível com o contrato existente do repo base — mesmo shape, para que o consumidor atual não precise mudar.
Única rota do serviço que mantém nomes de campo em português: é o único ponto que existe justamente para
bater com o contrato antigo.

Query: `?categoria=Superiores|Inferiores|Inverno` (opcional; `Todos` é tratado como ausente).

```json
[
  {
    "produto_id": 1,
    "desconto_pct": 30,
    "categoria": "Inverno",
    "ativo": true
  }
]
```

> Diferença em relação ao repo base: lá o endpoint fazia JOIN com `produto` e devolvia nome, preço e
> `preco_sale` calculado. Aqui o catálogo vive em outro serviço, então este endpoint devolve **apenas os dados
> da promoção**. Quem precisa do preço final chama `POST /internal/discounts/calculate` com os preços em mãos.

### `GET /api/coupons/{code}/validate`

Consulta leve, sem consumir uso.

Query: `?subtotal=199.90`

```json
{
  "code": "INVERNO20",
  "valid": true,
  "type": "percentage",
  "value": "20.00",
  "discount": "39.98"
}
```

Cupom recusado devolve **200**, não erro — com o motivo legível:

```json
{ "code": "INVERNO20", "valid": false, "reason": "Cupom expirado" }
```

Motivos possíveis: `Cupom não encontrado`, `Cupom inativo`, `Cupom expirado`, `Cupom ainda não vigente`,
`Limite de uso atingido`, `Valor mínimo de R$ X não atingido`.

---

## Administração

Todas exigem JWT com `isAdmin: true`.

### Promoções — `/api/promotions`

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/promotions` | Lista. Filtros: `?category=&active=` |
| `GET` | `/api/promotions/{id}` | Detalhe |
| `POST` | `/api/promotions` | Cria |
| `PUT` | `/api/promotions/{id}` | Atualiza |
| `DELETE` | `/api/promotions/{id}` | Remove |

```json
// POST request
{ "product_id": 42, "discount_percentage": 20, "category": "Superiores", "campaign_id": null }

// 201
{ "id": 1, "product_id": 42, "discount_percentage": 20, "category": "Superiores",
  "campaign_id": null, "active": true, "created_at": "2026-08-12T14:00:00Z" }
```

Produto que já tem promoção → **409** `{"error": "Produto já possui promoção"}` (constraint `UNIQUE`).

### Cupons — `/api/coupons`

CRUD completo nos mesmos cinco verbos.

```json
// POST request
{
  "code": "INVERNO20",
  "type": "percentage",
  "value": 20,
  "minimum_value": 100,
  "usage_limit": 500,
  "campaign_id": 3
}
```

`code` é normalizado para maiúsculo antes de gravar — `inverno20` e `INVERNO20` são o mesmo cupom.
Duplicado → **409** `{"error": "Código de cupom já existe"}`.

`type: "fixed"` faz `value` ser reais (`"15.00"`); `type: "percentage"` faz `value` ser porcentagem (1 a 100).

### Campanhas — `/api/campaigns`

CRUD completo. É o guarda-chuva de vigência que promoções e cupons podem referenciar.

```json
{ "name": "Liquida Inverno 2026", "description": null,
  "starts_at": "2026-09-01T00:00:00Z", "ends_at": "2026-09-30T23:59:59Z" }
```

---

## Interno

Fora do API Gateway. Exige `x-internal-secret`; sem ele, **403**.

### `POST /internal/discounts/calculate`

**O endpoint principal para Carrinho e Pedido.** Recebe os itens com preço (o chamador já tem esse dado do
serviço de Produto) e devolve o detalhamento do desconto.

```json
// request
{
  "items": [
    { "product_id": 1, "unit_price": 49.90, "quantity": 2 },
    { "product_id": 7, "unit_price": 120.00, "quantity": 1 }
  ],
  "coupon": "INVERNO20"
}
```

```json
// 200
{
  "subtotal": "219.80",
  "promotions_discount": "29.94",
  "coupon_discount": "37.97",
  "total": "151.89",
  "items": [
    { "product_id": 1, "unit_price": "49.90", "quantity": 2,
      "discount_percentage": 30, "discounted_price": "34.93", "subtotal": "69.86" },
    { "product_id": 7, "unit_price": "120.00", "quantity": 1,
      "discount_percentage": 0, "discounted_price": "120.00", "subtotal": "120.00" }
  ],
  "coupon": { "code": "INVERNO20", "type": "percentage", "value": "20.00", "applied": true }
}
```

**Cupom inválido devolve 200, não 4xx.** O carrinho precisa do cálculo dos itens mesmo quando o cupom é
recusado — o usuário digitou um código errado, não fez uma requisição errada:

```json
{
  "subtotal": "219.80", "promotions_discount": "29.94", "coupon_discount": "0.00",
  "total": "189.86",
  "coupon": { "code": "INVERNO20", "applied": false, "reason": "Cupom expirado" }
}
```

**422** fica reservado para erro de formato de verdade: item sem `unit_price`, quantidade negativa,
`items` vazio.

Esta chamada **não consome** o uso do cupom — é idempotente e pode ser chamada a cada mudança do carrinho.

### `POST /internal/coupons/{code}/consume`

Incrementa `usage_count`. Chamado pelo **Pedido** no fechamento da compra, nunca pelo Carrinho.

Separado do cálculo de propósito: se o consumo acontecesse na validação, todo preview de carrinho queimaria
um uso e um cupom de 500 usos se esgotaria sem nenhuma venda.

```json
// 200
{ "code": "INVERNO20", "usage_count": 13, "usage_limit": 500 }

// 409 — limite estourou entre a validação e o fechamento
{ "error": "Limite de uso atingido" }
```

O incremento é atômico (`UPDATE ... WHERE usage_count < usage_limit`), então duas compras simultâneas no
último uso disponível não passam as duas.

### `GET /health` e `GET /health/ready`

Sem autenticação — são os alvos das probes do Kubernetes.

- `/health` — liveness. Responde 200 se o processo está de pé.
- `/health/ready` — readiness. Responde 200 se a conexão com o banco funciona; 503 se não.

---

## Ordem de aplicação do desconto

Importa para quem for conferir o total, porque a ordem inversa dá resultado diferente:

1. **Promoção do produto** incide sobre o preço unitário do item. O percentual é sempre o da promoção daquele
   produto — campanha define vigência, não desconto.
2. Promoção cuja campanha não está vigente é ignorada, e o item sai com preço cheio.
3. **Cupom** incide sobre o subtotal, já com as promoções aplicadas.
4. O total nunca fica negativo: desconto maior que o subtotal resulta em zero.
