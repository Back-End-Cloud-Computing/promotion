# Regras de negócio

Este é o documento que define o que o serviço **deve fazer com dinheiro**. Cada regra tem um caso de teste que
prova que ela vale. Os testes vêm antes do código — é regra de negócio, cálculo e dinheiro.

Implementação em `app/Domain/Discounts/Services/DiscountCalculator.php`, testes em
`tests/Unit/DiscountCalculatorTest.php`.

## Cálculo e arredondamento

### R1 — Desconto percentual
`preco * (1 - pct/100)`, arredondado para duas casas.

> Teste: R$ 49,90 com 30% → R$ 34,93. É o mesmo resultado do `ROUND()` do repo base — compatibilidade
> verificável.

### R2 — Desconto fixo
Subtrai valor absoluto do subtotal.

> Teste: subtotal R$ 100,00 com cupom fixo de R$ 15,00 → R$ 85,00.

### R3 — Arredondamento em centavo
O arredondamento acontece por item, e os itens são somados depois. Somar preços cheios e arredondar no fim dá
resultado diferente de arredondar cada item — a ordem é fixada e testada.

> Teste: três itens de R$ 19,99 com 15% de desconto não podem produzir resto de ponto flutuante
> (`16.99150000000001`). Meia-casa arredonda para cima, de forma consistente.

### R4 — Total nunca negativo
Desconto maior que o total resulta em zero, não em valor negativo.

> Teste: subtotal R$ 10,00 com cupom fixo de R$ 50,00 → total R$ 0,00.

## Cupom

### R5 — Expirado
Campanha com `ends_at` no passado → recusado, motivo `Cupom expirado`.

### R6 — Ainda não vigente
Campanha com `starts_at` no futuro → recusado, motivo `Cupom ainda não vigente`.

> R5 e R6 comparam sempre com `Carbon::now()` em UTC, nunca com data em string. É onde bug de fuso se esconde.

### R7 — Limite de uso
`usage_count >= usage_limit` → recusado. `usage_limit = null` significa ilimitado e nunca recusa.

### R8 — Valor mínimo
`subtotal < minimum_value` → recusado, com o valor exigido na mensagem.

> O subtotal comparado é o **já descontado pelas promoções**, não o preço cheio.

### R9 — Inativo
`active = false` → recusado, motivo `Cupom inativo`.

### R10 — Inexistente
Código que não existe → recusado com motivo, sem exceção e sem 404. O consumidor recebe 200 com
`applied: false`.

### R11 — Case-insensitive
`inverno20`, `Inverno20` e `INVERNO20` são o mesmo cupom.

> Garantido por mutator no Model que normaliza para maiúsculo antes de gravar e ao buscar — **não** pela
> collation do banco. MySQL 8 é case-insensitive por padrão e SQLite não é; depender da collation faz este
> teste passar em um banco e falhar no outro.

### R12 — Unicidade de código
Criar cupom com código já existente → 409. Mesma regra vale para promoção: produto que já tem uma → 409.

> Teste de feature, contra o banco real: prova que a constraint `UNIQUE` está lá, não só a validação da
> aplicação.
>
> Nasceu quebrada: a primeira versão usava a regra `unique` do Laravel no FormRequest, que intercepta antes de
> qualquer INSERT acontecer e devolve 422. O teste foi escrito contra esse 422 — verde, mas provando a
> validação da aplicação, não a constraint do banco, que é exatamente o que este parágrafo dizia estar
> testado. Achado ao testar `POST /api/promotions` na mão contra a API viva: o contrato documentado promete 409
> para os dois recursos, mas nenhum dos dois devolvia isso. Corrigido tirando a regra `unique` da validação e
> deixando o `QueryException` da constraint virar 409 no controller — aí sim o teste exercita o banco de
> verdade.

### R13 — Consumo concorrente não estoura o limite
Duas requisições simultâneas no último uso disponível: uma passa, a outra recebe 409.

> Garantido pela trava no próprio `UPDATE`:
> ```sql
> UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ? AND (usage_limit IS NULL OR usage_count < usage_limit)
> ```
> Zero linhas afetadas significa limite estourado. Sem `SELECT ... FOR UPDATE`, sem lock de aplicação.

## Composição

### R14 — Ordem de aplicação
Promoção aplica no item; cupom aplica no subtotal já descontado. A ordem inversa produz total diferente, então
está fixada e testada.

> Teste: 2 × R$ 49,90 com promoção de 30% e cupom de 20%.
> Correto: `(49,90 × 0,7) × 2 = 69,86` → `69,86 × 0,8 = 55,89`.
> Invertido daria R$ 55,89 também neste caso — o teste usa valores em que os dois caminhos divergem, para que
> o assert tenha valor.

### R15 — Produto sem promoção
Passa com preço cheio, `discount_percentage: 0`. Não quebra, não some do resultado.

### R16 — O desconto é sempre o do próprio produto
Campanha agrupa promoções e define vigência, mas **não carrega percentual próprio**. Não existe "desconto de
categoria" disputando com o do produto.

> Esta regra nasceu errada. A primeira versão derivava um desconto de categoria a partir da maior promoção
> daquela categoria e aplicava a todos os seus produtos — o que fazia um produto de 30% ser cobrado a 40% só
> porque um vizinho de categoria estava mais barato. O teste original confirmava esse comportamento, porque foi
> escrito olhando a implementação em vez da regra.
>
> O erro só apareceu ao conferir a resposta da API contra a conta feita à mão. É a razão de a verificação
> manual estar na lista de checagens da Fase 1, e não é cerimônia: uma suíte verde pode estar provando que o
> código faz consistentemente a coisa errada.
>
> Teste: dois produtos na mesma categoria e campanha, 40% e 10%. O de 10% paga 10%.

Se um dia campanha precisar de desconto próprio, ela ganha uma coluna `discount_percentage` e a regra passa a ser o
maior entre os dois — nunca a soma, que viraria desconto duplo acidental.

---

## O que não é testado

CRUD puro, formatação de Resource, rotas triviais e as validações que o próprio Laravel já garante. YAGNI vale
para teste: o esforço vai para dinheiro, vigência, limite e parsing. O resto é boilerplate de framework, e o
framework já vem testado.

## Onde cada regra é verificada

| Tipo | Onde | Contra o quê |
|---|---|---|
| R1–R11, R14–R16 | `tests/Unit/DiscountCalculatorTest.php` | Nada — lógica pura, sem banco, instantâneo |
| R12, R13 | `tests/Feature/CouponTest.php` | **MySQL real** em container |

R12 e R13 exigem banco de verdade de propósito: são sobre constraint e concorrência, exatamente o que o SQLite
em memória não reproduz fielmente.
