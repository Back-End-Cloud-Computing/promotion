# Contrato da API

Referência para quem vai **consumir** este serviço: Carrinho, Pedido e o API Gateway.

A fonte de verdade em runtime é a spec OpenAPI gerada a partir do código (`/docs/api` e `/docs/api.json`).
Este documento existe para leitura humana e para permitir que os colegas integrem antes de o serviço estar no
ar.

## Convenções

- Rotas públicas em **português**, para casar com o resto do sistema.
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
> da promoção**. Quem precisa do preço final chama `POST /internal/descontos/calcular` com os preços em mãos.

### `GET /api/cupons/{codigo}/validar`

Consulta leve, sem consumir uso.

Query: `?subtotal=199.90`

```json
{
  "codigo": "INVERNO20",
  "valido": true,
  "tipo": "percentual",
  "valor": "20.00",
  "desconto": "39.98"
}
```

Cupom recusado devolve **200**, não erro — com o motivo legível:

```json
{ "codigo": "INVERNO20", "valido": false, "motivo": "Cupom expirado" }
```

Motivos possíveis: `Cupom não encontrado`, `Cupom inativo`, `Cupom expirado`, `Cupom ainda não vigente`,
`Limite de uso atingido`, `Valor mínimo de R$ X não atingido`.

---

## Administração

Todas exigem JWT com `isAdmin: true`.

### Promoções — `/api/promocoes`

| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/api/promocoes` | Lista. Filtros: `?categoria=&ativo=` |
| `GET` | `/api/promocoes/{id}` | Detalhe |
| `POST` | `/api/promocoes` | Cria |
| `PUT` | `/api/promocoes/{id}` | Atualiza |
| `DELETE` | `/api/promocoes/{id}` | Remove |

```json
// POST request
{ "produto_id": 42, "desconto_pct": 20, "categoria": "Superiores", "campanha_id": null }

// 201
{ "id": 1, "produto_id": 42, "desconto_pct": 20, "categoria": "Superiores",
  "campanha_id": null, "ativo": true, "created_at": "2026-08-12T14:00:00Z" }
```

Produto que já tem promoção → **409** `{"error": "Produto já possui promoção"}` (constraint `UNIQUE`).

### Cupons — `/api/cupons`

CRUD completo nos mesmos cinco verbos.

```json
// POST request
{
  "codigo": "INVERNO20",
  "tipo": "percentual",
  "valor": 20,
  "valor_minimo": 100,
  "limite_uso": 500,
  "campanha_id": 3
}
```

`codigo` é normalizado para maiúsculo antes de gravar — `inverno20` e `INVERNO20` são o mesmo cupom.
Duplicado → **409** `{"error": "Código de cupom já existe"}`.

`tipo: "fixo"` faz `valor` ser reais (`"15.00"`); `tipo: "percentual"` faz `valor` ser porcentagem (1 a 100).

### Campanhas — `/api/campanhas`

CRUD completo. É o guarda-chuva de vigência que promoções e cupons podem referenciar.

```json
{ "nome": "Liquida Inverno 2026", "descricao": null,
  "inicia_em": "2026-09-01T00:00:00Z", "termina_em": "2026-09-30T23:59:59Z" }
```

---

## Interno

Fora do API Gateway. Exige `x-internal-secret`; sem ele, **403**.

### `POST /internal/descontos/calcular`

**O endpoint principal para Carrinho e Pedido.** Recebe os itens com preço (o chamador já tem esse dado do
serviço de Produto) e devolve o detalhamento do desconto.

```json
// request
{
  "itens": [
    { "produto_id": 1, "preco_unitario": 49.90, "quantidade": 2 },
    { "produto_id": 7, "preco_unitario": 120.00, "quantidade": 1 }
  ],
  "cupom": "INVERNO20"
}
```

```json
// 200
{
  "subtotal": "219.80",
  "desconto_promocoes": "29.94",
  "desconto_cupom": "37.97",
  "total": "151.89",
  "itens": [
    { "produto_id": 1, "preco_unitario": "49.90", "quantidade": 2,
      "desconto_pct": 30, "preco_com_desconto": "34.93", "subtotal": "69.86" },
    { "produto_id": 7, "preco_unitario": "120.00", "quantidade": 1,
      "desconto_pct": 0, "preco_com_desconto": "120.00", "subtotal": "120.00" }
  ],
  "cupom": { "codigo": "INVERNO20", "tipo": "percentual", "valor": "20.00", "aplicado": true }
}
```

**Cupom inválido devolve 200, não 4xx.** O carrinho precisa do cálculo dos itens mesmo quando o cupom é
recusado — o usuário digitou um código errado, não fez uma requisição errada:

```json
{
  "subtotal": "219.80", "desconto_promocoes": "29.94", "desconto_cupom": "0.00",
  "total": "189.86",
  "cupom": { "codigo": "INVERNO20", "aplicado": false, "motivo": "Cupom expirado" }
}
```

**422** fica reservado para erro de formato de verdade: item sem `preco_unitario`, quantidade negativa,
`itens` vazio.

Esta chamada **não consome** o uso do cupom — é idempotente e pode ser chamada a cada mudança do carrinho.

### `POST /internal/cupons/{codigo}/consumir`

Incrementa `usos`. Chamado pelo **Pedido** no fechamento da compra, nunca pelo Carrinho.

Separado do cálculo de propósito: se o consumo acontecesse na validação, todo preview de carrinho queimaria
um uso e um cupom de 500 usos se esgotaria sem nenhuma venda.

```json
// 200
{ "codigo": "INVERNO20", "usos": 13, "limite_uso": 500 }

// 409 — limite estourou entre a validação e o fechamento
{ "error": "Limite de uso atingido" }
```

O incremento é atômico (`UPDATE ... WHERE usos < limite_uso`), então duas compras simultâneas no último uso
disponível não passam as duas.

### `GET /health` e `GET /health/ready`

Sem autenticação — são os alvos das probes do Kubernetes.

- `/health` — liveness. Responde 200 se o processo está de pé.
- `/health/ready` — readiness. Responde 200 se a conexão com o banco funciona; 503 se não.

---

## Ordem de aplicação do desconto

Importa para quem for conferir o total, porque a ordem inversa dá resultado diferente:

1. **Promoção do produto** incide sobre o preço unitário do item.
2. Se o produto tem promoção individual **e** campanha vigente na categoria, vale **o maior desconto entre os
   dois — nunca a soma**.
3. **Cupom** incide sobre o subtotal, já com as promoções aplicadas.
4. O total nunca fica negativo: desconto maior que o subtotal resulta em zero.
